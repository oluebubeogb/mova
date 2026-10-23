<?php
/**
 * Mova AI — gateway capability detection + low-level HTTP + "Test connection".
 *
 * Reads GET {base-without-/v1}/health (gateway ≥ 1.4 advertises `features`:
 * usage, tools, quotas). Cached for an hour in settings (ai_gateway_caps).
 * Any OpenAI-compatible provider without /health simply gets the defaults
 * (no gateway usage, no native tools unless forced in settings).
 *
 * Secrets: keys are only ever sent as Authorization headers; nothing here returns them.
 */

declare(strict_types=1);

namespace Mova\AI;

final class AiCapabilities
{
    private const CACHE_KEY = 'ai_gateway_caps';
    private const TTL = 3600;

    /**
     * @return array{reachable:bool,version:string,usage:bool,tools:bool,quotas:bool,checked_at:string}
     */
    public static function get(bool $refresh = false): array
    {
        $sig = md5(AiConfig::get('ai_api_url'));
        if (!$refresh) {
            $c = json_decode(AiConfig::setting(self::CACHE_KEY), true);
            if (is_array($c) && ($c['sig'] ?? '') === $sig && (int) ($c['_at'] ?? 0) > time() - self::TTL) {
                return $c['caps'];
            }
        }
        $caps = self::probeHealth(AiConfig::get('ai_api_url'), AiConfig::get('ai_api_key'));
        AiConfig::put(self::CACHE_KEY, (string) json_encode(['_at' => time(), 'sig' => $sig, 'caps' => $caps]));
        return $caps;
    }

    /** Whether to send OpenAI `tools` (setting ai_tool_mode: auto|native|text). */
    public static function useNativeTools(): bool
    {
        $mode = AiConfig::setting('ai_tool_mode') ?: 'auto';
        if ($mode === 'text') {
            return false;
        }
        if ($mode === 'native') {
            return true;
        }
        // auto: gateway must say it forwards tools; a previous native failure disables it for a day
        $fail = (int) AiConfig::setting('ai_tools_failed_at');
        if ($fail > time() - 86400) {
            return false;
        }
        return !empty(self::get()['tools']);
    }

    public static function markToolsFailed(): void
    {
        AiConfig::put('ai_tools_failed_at', (string) time());
    }

    /** @return array{reachable:bool,version:string,usage:bool,tools:bool,quotas:bool,checked_at:string} */
    private static function probeHealth(string $apiUrl, string $key): array
    {
        $caps = ['reachable' => false, 'version' => '', 'usage' => false, 'tools' => false, 'quotas' => false, 'checked_at' => date('c')];
        if ($apiUrl === '') {
            return $caps;
        }
        $res = self::rawRequest('GET', self::healthUrl($apiUrl), $key, null, 8, '');
        if ($res['status'] === 200 && is_array($res['json'])) {
            $f = (array) ($res['json']['features'] ?? []);
            $caps['reachable'] = true;
            $caps['version'] = (string) ($res['json']['version'] ?? '');
            $caps['usage'] = !empty($f['usage']);
            $caps['tools'] = !empty($f['tools']);
            $caps['quotas'] = !empty($f['quotas']);
        }
        return $caps;
    }

    /** …/v1 → …/health (gateway serves both /health and /v1/health). */
    public static function healthUrl(string $apiUrl): string
    {
        $base = rtrim($apiUrl, '/');
        return $base . '/health';
    }

    /**
     * "Test connection" for HQ → Settings → AI. Uses the form's URL/model and either the typed key
     * or (when blank) the saved key. Never returns or logs the key.
     *
     * @return array{ok:bool,steps:list<array{name:string,ok:bool,detail:string,ms:int}>,summary:string}
     */
    public static function test(string $apiUrl, string $key, string $model, bool $withChat = false): array
    {
        $apiUrl = rtrim(trim($apiUrl), '/');
        $steps = [];
        if ($apiUrl === '' || !preg_match('#^https?://#i', $apiUrl)) {
            return ['ok' => false, 'steps' => [['name' => 'URL', 'ok' => false, 'detail' => 'Enter an http(s) API URL ending in /v1.', 'ms' => 0]], 'summary' => 'Invalid URL'];
        }
        // 1) health (optional for non-Mova providers)
        $t = microtime(true);
        $h = self::rawRequest('GET', self::healthUrl($apiUrl), $key, null, 10, 'hq.settings_test');
        $ms = (int) round((microtime(true) - $t) * 1000);
        if ($h['status'] === 200 && is_array($h['json'])) {
            $f = (array) ($h['json']['features'] ?? []);
            $bits = ['v' . ($h['json']['version'] ?? '?')];
            if (array_key_exists('runpod_configured', $h['json'])) {
                $bits[] = !empty($h['json']['runpod_configured']) ? 'upstream configured' : 'upstream NOT configured';
            }
            $bits[] = !empty($f['usage']) ? 'per-site usage' : 'no usage ledger';
            $bits[] = !empty($f['tools']) ? 'native tools' : 'text tool protocol';
            $steps[] = ['name' => 'Gateway health', 'ok' => true, 'detail' => implode(' · ', $bits), 'ms' => $ms];
        } else {
            $steps[] = ['name' => 'Gateway health', 'ok' => true, 'detail' => $h['status'] ? 'No /health (HTTP ' . $h['status'] . ') — fine for non-Mova providers' : ('Unreachable: ' . $h['error']), 'ms' => $ms];
            if (!$h['status']) {
                $steps[count($steps) - 1]['ok'] = false;
            }
        }
        // 2) auth + models
        $t = microtime(true);
        $m = self::rawRequest('GET', $apiUrl . '/models', $key, null, 15, 'hq.settings_test');
        $ms = (int) round((microtime(true) - $t) * 1000);
        $okAuth = $m['status'] === 200;
        $ids = [];
        if ($okAuth && is_array($m['json'])) {
            foreach ((array) ($m['json']['data'] ?? []) as $row) {
                if (is_array($row) && !empty($row['id'])) {
                    $ids[] = (string) $row['id'];
                }
            }
        }
        $detail = match (true) {
            $okAuth => $ids ? 'Models: ' . implode(', ', array_slice($ids, 0, 6)) : 'Authenticated',
            $m['status'] === 401 || $m['status'] === 403 => 'Key rejected (HTTP ' . $m['status'] . ')',
            $m['status'] > 0 => 'HTTP ' . $m['status'],
            default => 'Unreachable: ' . $m['error'],
        };
        $steps[] = ['name' => 'API key', 'ok' => $okAuth, 'detail' => $detail, 'ms' => $ms];
        if ($okAuth && $model !== '' && $ids && !in_array($model, $ids, true)) {
            $steps[] = ['name' => 'Model', 'ok' => false, 'detail' => '"' . $model . '" is not offered by this endpoint', 'ms' => 0];
        } elseif ($okAuth && $model !== '') {
            $steps[] = ['name' => 'Model', 'ok' => true, 'detail' => $model, 'ms' => 0];
        }
        // 3) optional tiny completion
        if ($withChat && $okAuth) {
            $t = microtime(true);
            $c = self::rawRequest('POST', $apiUrl . '/chat/completions', $key, [
                'model' => $model ?: AiConfig::DEFAULT_MODEL,
                'messages' => [['role' => 'user', 'content' => 'Reply with the single word: ok']],
                'max_tokens' => 5, 'temperature' => 0,
            ], 90, 'hq.settings_test');
            $ms = (int) round((microtime(true) - $t) * 1000);
            $txt = is_array($c['json']) ? trim((string) ($c['json']['choices'][0]['message']['content'] ?? '')) : '';
            $steps[] = ['name' => 'Test reply', 'ok' => $c['status'] === 200 && $txt !== '', 'detail' => $c['status'] === 200 ? ('“' . mb_substr($txt, 0, 60) . '” in ' . round($ms / 1000, 1) . 's') : ('HTTP ' . $c['status'] . ' ' . self::errText($c)), 'ms' => $ms];
        }
        $ok = !in_array(false, array_column($steps, 'ok'), true);
        return ['ok' => $ok, 'steps' => $steps, 'summary' => $ok ? 'Connected' : 'Connection problem'];
    }

    /**
     * Authenticated request against the configured chat API (path relative to …/v1).
     *
     * @return array{status:int,json:mixed,raw:string,error:string,headers:array<string,string>}
     */
    public static function request(string $method, string $path, ?array $body, int $timeout, string $feature): array
    {
        return self::rawRequest($method, rtrim(AiConfig::get('ai_api_url'), '/') . $path, AiConfig::get('ai_api_key'), $body, $timeout, $feature);
    }

    /**
     * @return array{status:int,json:mixed,raw:string,error:string,headers:array<string,string>}
     */
    public static function rawRequest(string $method, string $url, string $key, ?array $body, int $timeout, string $feature): array
    {
        $headers = ['Accept: application/json'];
        if ($key !== '') {
            $headers[] = 'Authorization: Bearer ' . $key;
        }
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        $payload = $body !== null ? (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $res = AiHttp::request($method, $url, array_merge($headers, AiConfig::requestHeaders($feature)), $payload, max(3, $timeout));
        $status = $res['status'];
        $hdrs = $res['headers'];
        $err = $res['error'];
        $raw = $res['body'] === false ? '' : $res['body'];
        return ['status' => $status, 'json' => $raw !== '' ? json_decode($raw, true) : null, 'raw' => $raw, 'error' => (string) $err, 'headers' => $hdrs];
    }

    private static function errText(array $res): string
    {
        $j = $res['json'];
        if (is_array($j)) {
            $d = $j['detail'] ?? ($j['error'] ?? '');
            if (is_array($d)) {
                $d = $d['error']['message'] ?? ($d['message'] ?? json_encode($d));
            }
            return mb_substr((string) $d, 0, 160);
        }
        return $res['error'];
    }
}
