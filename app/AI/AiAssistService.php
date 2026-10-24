<?php
/**
 * Mova AI — built-in AI assist for content, SEO, and design.
 * OpenAI-compatible client against the Mova AI gateway (or any custom provider).
 *
 * Resolution: see AiConfig (HQ Settings → env → config.php → gateway defaults).
 * Every call sends X-Mova-Site headers and is logged to ai_usage (AiUsage).
 * Long work: chatRawAsync() → gateway POST /v1/jobs + poll GET /v1/jobs/{id}
 * so PHP workers can wait for RunPod without one fragile multi-minute HTTP call.
 */

namespace Mova\AI;

use Mova\Core\Bootstrap;
use Mova\Core\Database;

class AiAssistService
{
    /** Public platform gateway (provider secrets live only on the gateway). */
    private const PLATFORM_API_URL  = AiConfig::DEFAULT_API_URL;
    private const PLATFORM_MODEL    = AiConfig::DEFAULT_MODEL;
    private const PLATFORM_PROVIDER = AiConfig::DEFAULT_PROVIDER;

    /** Feature label for usage logging (null → inferred from caller). */
    private ?string $feature = null;

    /** Attribute subsequent calls on this instance to a feature (usage ledger + X-Mova-Feature). */
    public function forFeature(string $feature): self
    {
        $c = clone $this;
        $c->feature = $feature;
        return $c;
    }

    private function featureLabel(): string
    {
        return $this->feature ?? AiUsage::currentFeature();
    }

    public function isConfigured(): bool
    {
        return $this->resolveApiKey() !== '';
    }

    public function providerLabel(): string
    {
        return $this->isConfigured()
            ? ($this->resolve('ai_provider', self::PLATFORM_PROVIDER) ?: self::PLATFORM_PROVIDER)
            : 'heuristic';
    }

    /**
     * @return array{ok:bool,result?:string,error?:string,provider:string}
     */
    public function run(string $action, array $context): array
    {
        $title = trim((string) ($context['title'] ?? ''));
        $body = trim(strip_tags((string) ($context['body'] ?? '')));
        $excerpt = trim((string) ($context['excerpt'] ?? ''));

        if ($this->isConfigured()) {
            try {
                $result = ($this->feature === null ? $this->forFeature('assist.' . preg_replace('/[^a-z_]/', '', $action)) : $this)
                    ->callProvider($action, $title, $body, $excerpt);
                // Leaked "\u2019"-style escapes → real characters; U+2011/U+202F → "-"/" ".
                return ['ok' => true, 'result' => AiText::clean((string) $result), 'provider' => $this->providerLabel()];
            } catch (\Throwable $e) {
                $heuristic = $this->heuristic($action, $title, $body, $excerpt);
                return [
                    'ok' => true,
                    'result' => $heuristic,
                    'error' => 'Mova AI unavailable: ' . $e->getMessage() . ' — used local suggestions.',
                    'provider' => 'heuristic',
                ];
            }
        }

        return [
            'ok' => true,
            'result' => $this->heuristic($action, $title, $body, $excerpt),
            'provider' => 'heuristic',
        ];
    }

    /**
     * Sync OpenAI chat completions (short prompts).
     *
     * @param list<array{role:string,content:string}> $messages
     */
    public function chatRaw(array $messages, int $maxTokens = 800, int $timeoutSeconds = 90, ?float $temperature = null): string
    {
        $endpoint = rtrim($this->resolve('ai_api_url', self::PLATFORM_API_URL), '/') . '/chat/completions';
        $model = $this->resolve('ai_model', self::PLATFORM_MODEL) ?: self::PLATFORM_MODEL;
        $key = $this->resolveApiKey();
        $timeoutSeconds = max(15, min(600, $timeoutSeconds));

        if ($key === '') {
            throw new \RuntimeException('AI is not configured.');
        }

        if ($temperature === null) {
            $temperature = $maxTokens >= 3000 ? 0.7 : 0.5;
        }
        $temperature = max(0.0, min(1.2, (float) $temperature));

        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ]);

        $feature = $this->featureLabel();
        $t0 = microtime(true);
        try {
            $raw = $this->httpPostJson($endpoint, $key, $payload, $timeoutSeconds, $feature);
            $text = $this->parseCompletionText($raw);
        } catch (\Throwable $e) {
            AiUsage::record([
                'feature' => $feature, 'kind' => 'chat', 'model' => $model, 'endpoint' => $endpoint,
                'latency_ms' => (int) round((microtime(true) - $t0) * 1000),
                'status' => 'error', 'error' => $e->getMessage(),
            ]);
            throw $e;
        }
        $this->logSuccess('chat', $feature, $model, $endpoint, $t0, json_decode($raw, true), $messages, $text);
        return $text;
    }

    /**
     * Native OpenAI tool calling (sync). Returns the assistant message; tool_calls are normalised to
     * [{id,name,arguments(array)}]. Throws on transport/HTTP errors so callers can fall back to the
     * JSON text protocol.
     *
     * @param list<array<string,mixed>> $messages
     * @param list<array<string,mixed>> $tools OpenAI "function" tool specs
     * @return array{content:string,tool_calls:list<array{id:string,name:string,arguments:array}>}
     */
    public function chatTools(array $messages, array $tools, int $maxTokens = 1200, int $timeoutSeconds = 120, ?float $temperature = 0.3): array
    {
        $endpoint = rtrim($this->resolve('ai_api_url', self::PLATFORM_API_URL), '/') . '/chat/completions';
        $model = $this->resolve('ai_model', self::PLATFORM_MODEL) ?: self::PLATFORM_MODEL;
        $key = $this->resolveApiKey();
        if ($key === '') {
            throw new \RuntimeException('AI is not configured.');
        }
        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'temperature' => max(0.0, min(1.2, (float) ($temperature ?? 0.3))),
            'max_tokens' => $maxTokens,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $feature = $this->featureLabel();
        $t0 = microtime(true);
        try {
            $raw = $this->httpPostJson($endpoint, $key, (string) $payload, max(15, min(600, $timeoutSeconds)), $feature);
            $data = json_decode($raw, true);
            if (!is_array($data) || !isset($data['choices'][0]['message'])) {
                throw new \RuntimeException('Provider did not return a chat message' . (is_array($data) && isset($data['detail']) ? ': ' . (is_string($data['detail']) ? $data['detail'] : json_encode($data['detail'])) : '.'));
            }
        } catch (\Throwable $e) {
            AiUsage::record([
                'feature' => $feature, 'kind' => 'chat_tools', 'model' => $model, 'endpoint' => $endpoint,
                'latency_ms' => (int) round((microtime(true) - $t0) * 1000), 'status' => 'error', 'error' => $e->getMessage(),
            ]);
            throw $e;
        }
        $msg = (array) $data['choices'][0]['message'];
        $calls = [];
        foreach ((array) ($msg['tool_calls'] ?? []) as $tc) {
            if (!is_array($tc)) {
                continue;
            }
            $fn = (array) ($tc['function'] ?? []);
            $name = (string) ($fn['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $args = $fn['arguments'] ?? [];
            if (is_string($args)) {
                $args = json_decode($args, true);
            }
            $calls[] = ['id' => (string) ($tc['id'] ?? ('call_' . bin2hex(random_bytes(4)))), 'name' => $name, 'arguments' => is_array($args) ? $args : []];
        }
        $content = trim((string) ($msg['content'] ?? ''));
        $this->logSuccess('chat_tools', $feature, $model, $endpoint, $t0, $data, $messages, $content . json_encode($calls));
        return ['content' => $content, 'tool_calls' => $calls];
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     */
    private function logSuccess(string $kind, string $feature, string $model, string $endpoint, float $t0, mixed $decoded, array $messages, string $text, ?string $jobId = null): void
    {
        $usage = AiUsage::usageFrom($decoded);
        $estimated = AiUsage::gatewayEstimated($decoded);
        if ($usage['total_tokens'] === null) {
            $promptChars = '';
            foreach ($messages as $m) {
                $promptChars .= (string) ($m['content'] ?? '');
            }
            $usage = [
                'prompt_tokens' => AiUsage::estimateTokens($promptChars),
                'completion_tokens' => AiUsage::estimateTokens($text),
                'total_tokens' => null,
            ];
            $usage['total_tokens'] = $usage['prompt_tokens'] + $usage['completion_tokens'];
            $estimated = true;
        }
        AiUsage::record($usage + [
            'feature' => $feature, 'kind' => $kind, 'model' => is_array($decoded) && !empty($decoded['model']) ? (string) $decoded['model'] : $model,
            'endpoint' => $endpoint, 'latency_ms' => (int) round((microtime(true) - $t0) * 1000),
            'status' => 'ok', 'estimated' => $estimated, 'job_id' => $jobId,
        ]);
    }

    /**
     * Async path for long coding / background jobs.
     * Submits to gateway /v1/jobs (RunPod /run) and polls /v1/jobs/{id} until done.
     * Safe for AiJobService workers with ignore_user_abort — user can leave the chat.
     *
     * @param list<array{role:string,content:string}> $messages
     */
    public function chatRawAsync(array $messages, int $maxTokens = 2000, int $maxWaitSeconds = 900, ?float $temperature = null): string
    {
        $base = rtrim($this->resolve('ai_api_url', self::PLATFORM_API_URL), '/');
        $model = $this->resolve('ai_model', self::PLATFORM_MODEL) ?: self::PLATFORM_MODEL;
        $key = $this->resolveApiKey();

        if ($key === '') {
            throw new \RuntimeException('AI is not configured.');
        }

        if ($temperature === null) {
            $temperature = $maxTokens >= 3000 ? 0.7 : 0.5;
        }
        $temperature = max(0.0, min(1.2, (float) $temperature));
        $maxTokens = max(16, min(8192, $maxTokens));
        $maxWaitSeconds = max(60, min(1800, $maxWaitSeconds));

        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ]);

        $feature = $this->featureLabel();
        $t0 = microtime(true);

        // 1) Start job (should return quickly)
        try {
            $startRaw = $this->httpPostJson($base . '/jobs', $key, $payload, 60, $feature);
        } catch (\Throwable $e) {
            AiUsage::record([
                'feature' => $feature, 'kind' => 'chat_async', 'model' => $model, 'endpoint' => $base,
                'latency_ms' => (int) round((microtime(true) - $t0) * 1000), 'status' => 'error', 'error' => $e->getMessage(),
            ]);
            throw $e;
        }
        $start = json_decode($startRaw, true);
        if (!is_array($start) || empty($start['id'])) {
            // Fallback: gateway may be older — try sync once
            return ($this->feature === null ? $this->forFeature($feature) : $this)
                ->chatRaw($messages, $maxTokens, min(300, $maxWaitSeconds), $temperature);
        }
        try {
            $text = $this->pollAsyncJob($base, $key, (string) $start['id'], (string) ($start['status'] ?? 'IN_QUEUE'), $maxWaitSeconds, $feature, $status);
        } catch (\Throwable $e) {
            AiUsage::record([
                'feature' => $feature, 'kind' => 'chat_async', 'model' => $model, 'endpoint' => $base,
                'latency_ms' => (int) round((microtime(true) - $t0) * 1000), 'status' => 'error',
                'error' => $e->getMessage(), 'job_id' => (string) $start['id'],
            ]);
            throw $e;
        }
        $this->logSuccess('chat_async', $feature, $model, $base, $t0, $status, $messages, $text, (string) $start['id']);
        return $text;
    }

    /**
     * Poll gateway /jobs/{id}; $finalStatus receives the decoded COMPLETED payload (for usage).
     */
    private function pollAsyncJob(string $base, string $key, string $jobId, string $lastStatus, int $maxWaitSeconds, string $feature, mixed &$finalStatus = null): string
    {

        $deadline = time() + $maxWaitSeconds;
        $pollInterval = 3;

        // 2) Poll until completed / failed / deadline
        while (time() < $deadline) {
            sleep($pollInterval);
            // Back off slightly over time (3s → 8s)
            $pollInterval = min(8, $pollInterval + 1);

            $statusRaw = $this->httpGetJson($base . '/jobs/' . rawurlencode($jobId), $key, 45, $feature);
            $status = json_decode($statusRaw, true);
            if (!is_array($status)) {
                continue;
            }

            $lastStatus = strtoupper((string) ($status['status'] ?? ''));

            if ($lastStatus === 'COMPLETED') {
                $finalStatus = $status;
                if (!empty($status['text'])) {
                    return trim((string) $status['text']);
                }
                if (!empty($status['completion'])) {
                    return $this->parseCompletionText(json_encode($status['completion']));
                }
                throw new \RuntimeException('Job completed but returned empty content.');
            }

            if ($lastStatus === 'FAILED' || $lastStatus === 'CANCELLED' || $lastStatus === 'CANCELED') {
                $err = $status['error'] ?? 'job failed';
                if (is_array($err)) {
                    $err = json_encode($err);
                }
                throw new \RuntimeException('AI job failed: ' . $err);
            }
        }

        throw new \RuntimeException(
            'AI job still running after ' . $maxWaitSeconds . 's (last status: ' . $lastStatus
            . '). Job id: ' . $jobId . '. It may still finish on the provider; check Jobs later.'
        );
    }

    private function httpPostJson(string $url, string $key, string $payload, int $timeoutSeconds, string $feature = ''): string
    {
        $res = AiHttp::request('POST', $url, array_merge([
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ], AiConfig::requestHeaders($feature)), $payload, $timeoutSeconds);
        if ($res['body'] === false) {
            throw new \RuntimeException('No response from AI provider (' . ($res['error'] ?: 'connection failed or timed out') . ').');
        }
        return $res['body'];
    }

    private function httpGetJson(string $url, string $key, int $timeoutSeconds, string $feature = ''): string
    {
        $res = AiHttp::request('GET', $url, array_merge([
            'Authorization: Bearer ' . $key,
            'Accept: application/json',
        ], AiConfig::requestHeaders($feature)), null, $timeoutSeconds);
        if ($res['body'] === false) {
            throw new \RuntimeException('No response polling AI job (' . ($res['error'] ?: 'connection failed or timed out') . ').');
        }
        return $res['body'];
    }

    private function parseCompletionText(string $raw): string
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $snippet = mb_substr(preg_replace('/\s+/', ' ', $raw) ?? '', 0, 240);
            throw new \RuntimeException('AI provider returned non-JSON. First bytes: ' . $snippet);
        }
        if (!empty($data['error'])) {
            $errMsg = is_array($data['error'])
                ? (string) ($data['error']['message'] ?? json_encode($data['error']))
                : (string) $data['error'];
            $code = is_array($data['error']) ? (string) ($data['error']['code'] ?? $data['error']['type'] ?? '') : '';
            throw new \RuntimeException(
                'AI provider error' . ($code !== '' ? " [{$code}]" : '') . ': ' . $errMsg
            );
        }
        // FastAPI HTTPException detail
        if (isset($data['detail']) && !isset($data['choices'])) {
            $detail = is_array($data['detail']) ? json_encode($data['detail']) : (string) $data['detail'];
            throw new \RuntimeException('AI provider error: ' . $detail);
        }
        $text = $data['choices'][0]['message']['content'] ?? null;
        if (($text === null || $text === '') && isset($data['choices'][0]['text'])) {
            $text = $data['choices'][0]['text'];
        }
        if ($text === null || $text === '') {
            throw new \RuntimeException('Invalid AI response (empty content).');
        }
        return trim((string) $text);
    }

    private function callProvider(string $action, string $title, string $body, string $excerpt): string
    {
        $prompts = [
            'outline' => "Create a clear content outline (markdown bullet headings) for an article titled \"{$title}\". Body context:\n" . mb_substr($body, 0, 3000),
            'rewrite' => "Rewrite the following content to be clearer, more engaging, and better structured. Keep the meaning. Title: {$title}\n\n" . mb_substr($body, 0, 4000),
            'seo' => "Suggest SEO title, meta description (max 155 chars), and 5 focus keywords for: \"{$title}\". Body excerpt:\n" . mb_substr($body !== '' ? $body : $excerpt, 0, 2000),
            'excerpt' => "Write a compelling 1–2 sentence excerpt for an article titled \"{$title}\". Body:\n" . mb_substr($body, 0, 2000),
            'improve' => "Give 5 concrete, prioritized improvements for this content (structure, clarity, SEO, engagement). Title: {$title}\n\n" . mb_substr($body, 0, 3500),
            'design' => "Suggest layout and design improvements for a CMS page titled \"{$title}\". Focus on hierarchy, whitespace, CTAs, and readability. Context:\n" . mb_substr($body, 0, 2000),
        ];
        $prompt = $prompts[$action] ?? $prompts['improve'];

        return $this->chatRaw([
            ['role' => 'system', 'content' => 'You are Mova AI, the built-in editorial assistant for Mova CMS. Be concise, practical, and SEO-aware. Never invent publishing actions or claim to have published content.'],
            ['role' => 'user', 'content' => $prompt],
        ], 900, 90);
    }

    private function heuristic(string $action, string $title, string $body, string $excerpt): string
    {
        $wordCount = str_word_count($body);
        switch ($action) {
            case 'outline':
                $bits = array_filter(array_map('trim', preg_split('/[.!?]+/', mb_substr($body, 0, 800)) ?: []));
                $out = ["# {$title}", '', '## Introduction'];
                $i = 1;
                foreach (array_slice($bits, 0, 5) as $b) {
                    if (mb_strlen($b) > 20) {
                        $out[] = '## Section ' . $i++;
                        $out[] = '- ' . mb_substr($b, 0, 80);
                    }
                }
                $out[] = '## Conclusion';
                return implode("\n", $out);
            case 'excerpt':
                if ($excerpt !== '') {
                    return $excerpt;
                }
                $plain = preg_replace('/\s+/', ' ', $body) ?? '';
                return mb_substr($plain, 0, 160) . (mb_strlen($plain) > 160 ? '…' : '');
            case 'seo':
                $kw = array_slice(array_unique(array_filter(preg_split('/\W+/', strtolower($title . ' ' . mb_substr($body, 0, 400))) ?: [])), 0, 5);
                $meta = mb_substr(preg_replace('/\s+/', ' ', $body) ?? $title, 0, 155);
                return "SEO title: {$title}\nMeta description: {$meta}\nKeywords: " . implode(', ', $kw);
            case 'rewrite':
                return $body !== '' ? $body : $title;
            case 'design':
                return "• Lead with a clear H1 and short intro.\n• Use consistent spacing and a single primary CTA above the fold.\n• Break long text into scannable sections with H2s.\n• Ensure mobile-friendly type size and contrast.\n• Align visuals with the page goal.";
            default:
                $tips = [];
                if ($title === '') {
                    $tips[] = 'Add a clear, keyword-aware title.';
                }
                if ($wordCount < 80) {
                    $tips[] = 'Expand the body — aim for at least a few substantial paragraphs.';
                }
                if ($excerpt === '' && $body !== '') {
                    $tips[] = 'Write a short excerpt for listings and social shares.';
                }
                if (!preg_match('/<h2/i', $body) && $wordCount > 150) {
                    $tips[] = 'Add H2 subheadings to improve structure.';
                }
                if (!$tips) {
                    $tips[] = 'Structure looks okay. Consider internal links and a stronger opening.';
                }
                return '• ' . implode("\n• ", $tips);
        }
    }

    private function resolve(string $key, string $platformDefault = ''): string
    {
        $v = AiConfig::get($key);
        return $v !== '' ? $v : $platformDefault;
    }

    private function resolveApiKey(): string
    {
        return AiConfig::get('ai_api_key');
    }

    /** Effective model / endpoint (for diagnostics in HQ). */
    public function effectiveModel(): string
    {
        return $this->resolve('ai_model', self::PLATFORM_MODEL);
    }

    public function effectiveEndpoint(): string
    {
        return $this->resolve('ai_api_url', self::PLATFORM_API_URL);
    }
}
