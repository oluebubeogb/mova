<?php
declare(strict_types=1);

namespace Mova\Update;

use Mova\Core\Bootstrap;

/**
 * Core update checker (download/apply comes in a later phase).
 * Never overwrites: mova-uploads, mova-plugins, mova-themes, storage, live config secrets.
 */
class UpdateService
{
    public function __construct(
        private readonly IdentityService $identity = new IdentityService()
    ) {
    }

    public function currentVersion(): string
    {
        return $this->identity->version();
    }

    /**
     * Fetch remote manifest if release_manifest URL is configured.
     *
     * @return array{ok: bool, current: string, latest?: string, changelog_url?: string, package?: array, message?: string}
     */
    public function check(): array
    {
        $current = $this->currentVersion();
        $data = $this->identity->read();
        $url = trim((string) ($data['release_manifest'] ?? ''));
        if ($url === '') {
            return [
                'ok' => true,
                'current' => $current,
                'message' => 'No release_manifest URL configured in mova.json. Set it to enable update checks.',
            ];
        }

        $json = $this->httpGet($url);
        if ($json === null) {
            return [
                'ok' => false,
                'current' => $current,
                'message' => 'Could not reach release manifest.',
            ];
        }
        $manifest = json_decode($json, true);
        if (!is_array($manifest) || empty($manifest['version'])) {
            return [
                'ok' => false,
                'current' => $current,
                'message' => 'Invalid release manifest.',
            ];
        }

        $latest = (string) $manifest['version'];
        return [
            'ok' => true,
            'current' => $current,
            'latest' => $latest,
            'changelog_url' => $manifest['changelog_url'] ?? '',
            'package' => $manifest['package'] ?? null,
            'message' => version_compare($latest, $current, '>')
                ? "Update available: {$current} → {$latest}"
                : 'You are on the latest version.',
        ];
    }

    private function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => 'MovaUpdater/' . $this->currentVersion(),
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body !== false && $code >= 200 && $code < 300) {
                return $body;
            }
            return null;
        }
        $ctx = stream_context_create(['http' => ['timeout' => 15, 'header' => "User-Agent: MovaUpdater\r\n"]]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : $body;
    }
}
