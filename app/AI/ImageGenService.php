<?php
/**
 * Mova Image generation — async jobs against the Mova Image gateway.
 * Platform defaults match chat gateway host; path is /v1/images/jobs.
 * Real RunPod keys live only on the proxy.
 */

declare(strict_types=1);

namespace Mova\AI;

use Mova\Core\Bootstrap;
use Mova\Core\Database;
use Mova\Media\MediaService;

class ImageGenService
{
    private const PLATFORM_API_URL  = 'https://movaai.collab.name.ng/v1';
    private const PLATFORM_API_KEY  = 'mova-ai-key';
    private const PLATFORM_MODEL    = 'mova-img';
    private const PLATFORM_PROVIDER = 'Mova Image';

    /** @var array<string,array{w:int,h:int,label:string}> */
    public const ASPECTS = [
        'square'    => ['w' => 720,  'h' => 720,  'label' => 'Square'],
        'landscape' => ['w' => 1280, 'h' => 720,  'label' => 'Landscape'],
        'portrait'  => ['w' => 720,  'h' => 1280, 'label' => 'Portrait'],
    ];

    public function isConfigured(): bool
    {
        return $this->resolveApiKey() !== '';
    }

    public function providerLabel(): string
    {
        return $this->isConfigured()
            ? ($this->resolve('ai_img_provider', self::PLATFORM_PROVIDER) ?: self::PLATFORM_PROVIDER)
            : 'none';
    }

    public function sizeForAspect(string $aspect): string
    {
        $a = self::ASPECTS[$aspect] ?? self::ASPECTS['square'];
        return $a['w'] . 'x' . $a['h'];
    }

    /**
     * Full pipeline: submit gateway job, poll until done, ingest into media library.
     *
     * @return array{ok:bool,media?:array,url?:string,provider:string,error?:string,gateway_job_id?:string}
     */
    public function generateAndIngest(
        string $prompt,
        string $aspect = 'square',
        string $negativePrompt = '',
        ?int $userId = null,
        ?callable $onProgress = null
    ): array {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return ['ok' => false, 'provider' => $this->providerLabel(), 'error' => 'Empty prompt'];
        }
        if (!$this->isConfigured()) {
            return ['ok' => false, 'provider' => 'none', 'error' => 'Image AI is not configured'];
        }

        if (!isset(self::ASPECTS[$aspect])) {
            $aspect = 'square';
        }
        $size = $this->sizeForAspect($aspect);
        $dims = self::ASPECTS[$aspect];

        if ($onProgress) {
            $onProgress('Submitting to Mova Image…');
        }

        try {
            $start = $this->startJob($prompt, $size, $aspect, $negativePrompt);
        } catch (\Throwable $e) {
            return ['ok' => false, 'provider' => $this->providerLabel(), 'error' => $e->getMessage()];
        }

        $jobId = (string) ($start['id'] ?? '');
        if ($jobId === '') {
            return ['ok' => false, 'provider' => $this->providerLabel(), 'error' => 'Gateway did not return a job id'];
        }

        if ($onProgress) {
            $onProgress('Generating image (job ' . $jobId . ')…');
        }

        try {
            $result = $this->pollJob($jobId, 900, $onProgress);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'provider' => $this->providerLabel(),
                'error' => $e->getMessage(),
                'gateway_job_id' => $jobId,
            ];
        }

        $binary = $result['binary'] ?? null;
        $mime = (string) ($result['mime'] ?? 'image/png');
        if (!is_string($binary) || $binary === '') {
            return [
                'ok' => false,
                'provider' => $this->providerLabel(),
                'error' => 'Job completed but no image data returned',
                'gateway_job_id' => $jobId,
            ];
        }

        if ($onProgress) {
            $onProgress('Saving to media library…');
        }

        try {
            $media = (new MediaService())->ingestGenerated(
                $binary,
                $mime,
                $userId,
                [
                    'alt_text' => mb_substr($prompt, 0, 200),
                    'original_name' => 'ai-' . $aspect . '-' . date('Ymd-His') . '.png',
                    'width' => (int) ($result['width'] ?? $dims['w']),
                    'height' => (int) ($result['height'] ?? $dims['h']),
                ]
            );
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'provider' => $this->providerLabel(),
                'error' => 'Image received but save failed: ' . $e->getMessage(),
                'gateway_job_id' => $jobId,
            ];
        }

        $url = (new MediaService())->url($media);

        return [
            'ok' => true,
            'media' => $media,
            'url' => $url,
            'provider' => $this->providerLabel(),
            'gateway_job_id' => $jobId,
        ];
    }

    /**
     * @return array{id:string,status?:string}
     */
    private function startJob(string $prompt, string $size, string $aspect, string $negativePrompt): array
    {
        $base = rtrim($this->resolve('ai_img_api_url', self::PLATFORM_API_URL), '/');
        $model = $this->resolve('ai_img_model', self::PLATFORM_MODEL) ?: self::PLATFORM_MODEL;
        $key = $this->resolveApiKey();

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'size' => $size,
            'aspect' => $aspect,
            'seed' => -1,
            'enable_safety_checker' => true,
        ];
        if (trim($negativePrompt) !== '') {
            $payload['negative_prompt'] = trim($negativePrompt);
        }

        $raw = $this->httpPostJson($base . '/images/jobs', $key, json_encode($payload), 60);
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['id'])) {
            $snippet = mb_substr(preg_replace('/\s+/', ' ', $raw) ?? '', 0, 200);
            throw new \RuntimeException('Invalid start response from image gateway. ' . $snippet);
        }
        return $data;
    }

    /**
     * @return array{binary:string,mime:string,width?:int,height?:int}
     */
    private function pollJob(string $jobId, int $maxWaitSeconds = 900, ?callable $onProgress = null): array
    {
        $base = rtrim($this->resolve('ai_img_api_url', self::PLATFORM_API_URL), '/');
        $key = $this->resolveApiKey();
        $maxWaitSeconds = max(60, min(1800, $maxWaitSeconds));
        $deadline = time() + $maxWaitSeconds;
        $pollInterval = 3;
        $lastStatus = 'IN_QUEUE';

        while (time() < $deadline) {
            sleep($pollInterval);
            $pollInterval = min(8, $pollInterval + 1);

            $statusRaw = $this->httpGetJson($base . '/images/jobs/' . rawurlencode($jobId), $key, 45);
            $status = json_decode($statusRaw, true);
            if (!is_array($status)) {
                continue;
            }

            $lastStatus = strtoupper((string) ($status['status'] ?? ''));
            if ($onProgress) {
                $onProgress('Status: ' . ($lastStatus !== '' ? $lastStatus : 'waiting') . '…');
            }

            if ($lastStatus === 'COMPLETED') {
                return $this->extractImage($status);
            }

            if (in_array($lastStatus, ['FAILED', 'CANCELLED', 'CANCELED'], true)) {
                $err = $status['error'] ?? 'job failed';
                if (is_array($err)) {
                    $err = json_encode($err);
                }
                throw new \RuntimeException('Image job failed: ' . $err);
            }
        }

        throw new \RuntimeException(
            'Image job still running after ' . $maxWaitSeconds . 's (last: ' . $lastStatus
            . '). Gateway job: ' . $jobId . '. It may still finish on the provider.'
        );
    }

    /**
     * @param array<string,mixed> $status
     * @return array{binary:string,mime:string,width?:int,height?:int}
     */
    private function extractImage(array $status): array
    {
        $mime = (string) ($status['mime'] ?? $status['content_type'] ?? 'image/png');
        $width = isset($status['width']) ? (int) $status['width'] : null;
        $height = isset($status['height']) ? (int) $status['height'] : null;

        // Base64 (with or without data: prefix)
        $b64 = $status['image_base64'] ?? $status['b64_json'] ?? null;
        if (is_string($b64) && $b64 !== '') {
            if (preg_match('#^data:([^;]+);base64,(.+)$#s', $b64, $m)) {
                $mime = $m[1] ?: $mime;
                $b64 = $m[2];
            }
            $binary = base64_decode($b64, true);
            if ($binary === false || $binary === '') {
                throw new \RuntimeException('Invalid base64 image from gateway');
            }
            return array_filter([
                'binary' => $binary,
                'mime' => $mime,
                'width' => $width,
                'height' => $height,
            ], static fn ($v) => $v !== null);
        }

        // Nested OpenAI-style data[0].b64_json / url
        if (!empty($status['data'][0]) && is_array($status['data'][0])) {
            $d = $status['data'][0];
            if (!empty($d['b64_json'])) {
                $binary = base64_decode((string) $d['b64_json'], true);
                if ($binary !== false && $binary !== '') {
                    return array_filter([
                        'binary' => $binary,
                        'mime' => $mime,
                        'width' => $width,
                        'height' => $height,
                    ], static fn ($v) => $v !== null);
                }
            }
            if (!empty($d['url']) && is_string($d['url'])) {
                return $this->downloadImageUrl($d['url'], $mime, $width, $height);
            }
        }

        $url = $status['image_url'] ?? $status['url'] ?? null;
        if (is_string($url) && $url !== '') {
            return $this->downloadImageUrl($url, $mime, $width, $height);
        }

        throw new \RuntimeException('Job completed but returned no image_base64 or image_url');
    }

    /**
     * @return array{binary:string,mime:string,width?:int,height?:int}
     */
    private function downloadImageUrl(string $url, string $mime, ?int $width, ?int $height): array
    {
        if (!preg_match('#^https?://#i', $url)) {
            throw new \RuntimeException('Invalid image URL from gateway');
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 120,
                'ignore_errors' => true,
                'header' => "Accept: image/*,*/*\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $binary = @file_get_contents($url, false, $ctx);
        if ($binary === false || $binary === '') {
            throw new \RuntimeException('Failed to download generated image');
        }
        // Detect mime from magic if possible
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->buffer($binary);
        if (is_string($detected) && strpos($detected, 'image/') === 0) {
            $mime = $detected;
        }
        return array_filter([
            'binary' => $binary,
            'mime' => $mime,
            'width' => $width,
            'height' => $height,
        ], static fn ($v) => $v !== null);
    }

    private function httpPostJson(string $url, string $key, string $payload, int $timeoutSeconds): string
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $key,
                    'Accept: application/json',
                ]),
                'content' => $payload,
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new \RuntimeException('Could not reach image gateway at ' . $url);
        }
        return $raw;
    }

    private function httpGetJson(string $url, string $key, int $timeoutSeconds): string
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Authorization: Bearer ' . $key,
                    'Accept: application/json',
                ]),
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new \RuntimeException('Could not poll image gateway');
        }
        return $raw;
    }

    private function resolve(string $key, string $platformDefault = ''): string
    {
        $fromDb = $this->settingRaw($key);
        if ($fromDb !== '') {
            return $fromDb;
        }
        // Fall back to chat URL/key only for base URL if img-specific empty — model stays platform default
        if ($key === 'ai_img_api_url') {
            $chatUrl = $this->settingRaw('ai_api_url');
            if ($chatUrl !== '') {
                return rtrim($chatUrl, '/');
            }
            $fromConfig = (string) Bootstrap::config('ai.ai_api_url', '');
            if ($fromConfig !== '') {
                return rtrim($fromConfig, '/');
            }
        }
        if ($key === 'ai_img_api_key') {
            $chatKey = $this->settingRaw('ai_api_key');
            if ($chatKey !== '') {
                return $chatKey;
            }
        }
        return $platformDefault;
    }

    private function resolveApiKey(): string
    {
        $k = $this->resolve('ai_img_api_key', self::PLATFORM_API_KEY);
        return $k !== '' ? $k : self::PLATFORM_API_KEY;
    }

    private function settingRaw(string $key): string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                $rows = Database::fetchAll('SELECT setting_key, setting_value FROM settings');
                foreach ($rows as $r) {
                    $cache[$r['setting_key']] = (string) $r['setting_value'];
                }
            } catch (\Throwable $e) {
                $cache = [];
            }
        }
        return isset($cache[$key]) ? trim((string) $cache[$key]) : '';
    }
}
