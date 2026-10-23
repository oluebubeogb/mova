<?php
/**
 * Mova AI — single source of truth for AI provider configuration.
 *
 * Resolution order for every key (first non-empty wins):
 *   1. HQ Settings (settings table) — explicit overrides made by the site owner
 *   2. Environment (MOVA_AI_API_URL, MOVA_AI_API_KEY, MOVA_AI_MODEL, MOVA_AI_IMG_*)
 *   3. config/config.php → 'ai' block
 *   4. Platform defaults below (the Mova AI gateway)
 *
 * The gateway holds the real provider credentials; the client key below only
 * identifies Mova installs to the gateway. Per-site attribution is sent as
 * X-Mova-Site / X-Mova-Site-Host request headers (see requestHeaders()).
 */

declare(strict_types=1);

namespace Mova\AI;

use Mova\Core\Bootstrap;
use Mova\Core\Database;

final class AiConfig
{
    public const DEFAULT_API_URL      = 'https://movaai.collab.name.ng/v1';
    public const DEFAULT_API_KEY      = 'mova-ai-key';
    public const DEFAULT_MODEL        = 'mova-ai';
    public const DEFAULT_PROVIDER     = 'Mova AI';
    public const DEFAULT_IMG_MODEL    = 'mova-img';
    public const DEFAULT_IMG_PROVIDER = 'Mova Image';

    /** Keys that hold secrets — never echo these back into HTML. */
    public const SECRET_KEYS = ['ai_api_key', 'ai_img_api_key'];

    private const ENV = [
        'ai_api_url'      => 'MOVA_AI_API_URL',
        'ai_api_key'      => 'MOVA_AI_API_KEY',
        'ai_model'        => 'MOVA_AI_MODEL',
        'ai_provider'     => 'MOVA_AI_PROVIDER',
        'ai_img_api_url'  => 'MOVA_AI_IMG_API_URL',
        'ai_img_api_key'  => 'MOVA_AI_IMG_API_KEY',
        'ai_img_model'    => 'MOVA_AI_IMG_MODEL',
        'ai_img_provider' => 'MOVA_AI_IMG_PROVIDER',
    ];

    /** @var array<string,string>|null */
    private static ?array $settings = null;

    public static function defaults(): array
    {
        return [
            'ai_api_url'      => self::DEFAULT_API_URL,
            'ai_api_key'      => self::DEFAULT_API_KEY,
            'ai_model'        => self::DEFAULT_MODEL,
            'ai_provider'     => self::DEFAULT_PROVIDER,
            'ai_img_api_url'  => '', // falls back to chat URL
            'ai_img_api_key'  => '', // falls back to chat key
            'ai_img_model'    => self::DEFAULT_IMG_MODEL,
            'ai_img_provider' => self::DEFAULT_IMG_PROVIDER,
        ];
    }

    /** Effective value for an AI key (see class doc for order). */
    public static function get(string $key): string
    {
        $v = self::explicit($key);
        if ($v !== '') {
            return $key === 'ai_api_url' || $key === 'ai_img_api_url' ? rtrim($v, '/') : $v;
        }
        // Image endpoint/key inherit the chat endpoint/key when not set separately
        if ($key === 'ai_img_api_url') {
            return self::get('ai_api_url');
        }
        if ($key === 'ai_img_api_key') {
            return self::get('ai_api_key');
        }
        return (string) (self::defaults()[$key] ?? '');
    }

    /** Value from settings/env/config only (no platform default). */
    public static function explicit(string $key): string
    {
        $db = self::setting($key);
        if ($db !== '') {
            return $db;
        }
        $envName = self::ENV[$key] ?? '';
        if ($envName !== '') {
            $env = getenv($envName);
            if (is_string($env) && trim($env) !== '') {
                return trim($env);
            }
        }
        return self::configValue($key);
    }

    /**
     * Values the pre-gateway config.php template pinned (RunPod called directly, key read from
     * RUNPOD_API_KEY, which Mova no longer uses). Upgraded installs still carry that file, so these
     * are ignored unless the same block also sets its own ai_api_key — otherwise every AI call would
     * go straight to RunPod without credentials instead of through the Mova AI gateway.
     */
    private const LEGACY_CONFIG = [
        'ai_api_url'  => ['https://api.runpod.ai/v2/granite-4-0-h-small/openai/v1'],
        'ai_model'    => ['ibm-granite/granite-4.0-h-small'],
        'ai_provider' => ['RunPod IBM Granite 4.0 H Small'],
    ];

    private static function configValue(string $key): string
    {
        try {
            $cfg = trim((string) Bootstrap::config('ai.' . $key, ''));
            if ($cfg !== '' && in_array(rtrim($cfg, '/'), self::LEGACY_CONFIG[$key] ?? [], true)
                && trim((string) Bootstrap::config('ai.ai_api_key', '')) === '') {
                return '';
            }
        } catch (\Throwable $e) {
            $cfg = '';
        }
        return $cfg;
    }

    /** Where the effective value comes from: settings|env|config|default. */
    public static function source(string $key): string
    {
        if (self::setting($key) !== '') {
            return 'settings';
        }
        $envName = self::ENV[$key] ?? '';
        if ($envName !== '' && is_string(getenv($envName)) && trim((string) getenv($envName)) !== '') {
            return 'env';
        }
        if (self::configValue($key) !== '') {
            return 'config';
        }
        return 'default';
    }

    public static function usesPlatformGateway(): bool
    {
        return stripos(self::get('ai_api_url'), 'movaai.collab.name.ng') !== false;
    }

    /**
     * Mask a secret for display: "mova…-key" style, never the full value.
     */
    public static function mask(string $secret): string
    {
        $secret = trim($secret);
        if ($secret === '') {
            return '';
        }
        $len = mb_strlen($secret);
        if ($len <= 6) {
            return str_repeat('•', 6);
        }
        return mb_substr($secret, 0, 3) . str_repeat('•', 6) . mb_substr($secret, -3);
    }

    /** Stable per-install identifier sent to the gateway as X-Mova-Site. */
    public static function siteId(): string
    {
        $id = self::setting('site_uuid');
        if ($id !== '') {
            return $id;
        }
        $id = self::uuid4();
        try {
            Database::query(
                "INSERT OR IGNORE INTO settings (setting_key, setting_value, updated_at) VALUES ('site_uuid', :v, :t)",
                ['v' => $id, 't' => date('c')]
            );
            $row = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = 'site_uuid'");
            if ($row && !empty($row['setting_value'])) {
                $id = (string) $row['setting_value'];
            }
        } catch (\Throwable $e) {
            // not installed yet
        }
        self::$settings['site_uuid'] = $id;
        return $id;
    }

    /** Public host of this site (app_url → request host → last known). */
    public static function siteHost(): string
    {
        $appUrl = '';
        try {
            $appUrl = (string) Bootstrap::config('app_url', '');
        } catch (\Throwable $e) {
        }
        if ($appUrl !== '') {
            $h = parse_url($appUrl, PHP_URL_HOST);
            if (is_string($h) && $h !== '') {
                return strtolower($h);
            }
        }
        $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
        if ($host !== '' && preg_match('/^[a-z0-9.\-]+$/', $host)) {
            if (self::setting('site_host') !== $host) {
                try {
                    Database::query(
                        "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('site_host', :v, :t)
                         ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at",
                        ['v' => $host, 't' => date('c')]
                    );
                    self::$settings['site_host'] = $host;
                } catch (\Throwable $e) {
                }
            }
            return $host;
        }
        return self::setting('site_host');
    }

    /**
     * Headers sent with every gateway request so the gateway can attribute usage per site.
     *
     * @return list<string>
     */
    public static function requestHeaders(string $feature = ''): array
    {
        $h = [
            'X-Mova-Site: ' . self::siteId(),
            'X-Mova-Site-Host: ' . self::siteHost(),
        ];
        if ($feature !== '') {
            $h[] = 'X-Mova-Feature: ' . preg_replace('/[^a-z0-9_.\-]/i', '', $feature);
        }
        try {
            $h[] = 'X-Mova-Version: ' . (string) Bootstrap::config('app_version', '');
        } catch (\Throwable $e) {
        }
        $h[] = 'User-Agent: Mova/' . (string) (Bootstrap::config('app_version', '') ?: '1') . ' (+' . self::siteHost() . ')';
        return $h;
    }

    public static function setting(string $key): string
    {
        if (self::$settings === null) {
            self::$settings = [];
            try {
                foreach (Database::fetchAll('SELECT setting_key, setting_value FROM settings') as $r) {
                    self::$settings[(string) $r['setting_key']] = (string) $r['setting_value'];
                }
            } catch (\Throwable $e) {
                self::$settings = [];
            }
        }
        return isset(self::$settings[$key]) ? trim((string) self::$settings[$key]) : '';
    }

    /** Upsert a small internal setting (caches, heartbeats). Never throws. */
    public static function put(string $key, string $value): void
    {
        try {
            Database::query(
                "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :t)
                 ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at",
                ['k' => $key, 'v' => $value, 't' => date('c')]
            );
            if (self::$settings !== null) {
                self::$settings[$key] = $value;
            }
        } catch (\Throwable $e) {
        }
    }

    /** Drop the per-request settings cache (after Settings save). */
    public static function flush(): void
    {
        self::$settings = null;
    }

    private static function uuid4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
