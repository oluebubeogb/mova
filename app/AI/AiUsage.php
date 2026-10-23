<?php
/**
 * Mova AI — local usage ledger (one row per gateway call).
 *
 * Records feature, model, token usage (from the OpenAI-style `usage` object when
 * the gateway returns one), latency, status and user. Powers HQ → AI usage.
 * Logging never throws: a failed insert must not break an AI feature.
 */

declare(strict_types=1);

namespace Mova\AI;

use Mova\Core\Database;

final class AiUsage
{
    /** Feature label for calls made while a feature scope is active. */
    private static array $featureStack = [];

    /** Run $fn with all AI calls attributed to $feature. */
    public static function withFeature(string $feature, callable $fn): mixed
    {
        self::$featureStack[] = $feature;
        try {
            return $fn();
        } finally {
            array_pop(self::$featureStack);
        }
    }

    public static function currentFeature(): string
    {
        if (self::$featureStack) {
            return (string) end(self::$featureStack);
        }
        return self::inferFeature();
    }

    /**
     * Best-effort attribution from the call stack when no explicit scope is set.
     */
    public static function inferFeature(): string
    {
        $map = [
            'SiteAiService'      => 'site_ai.chat',
            'KnowledgeBank'      => 'site_ai.knowledge',
            'SetupWizardService' => 'setup_wizard',
            'AiChatService'      => 'hq.chat',
            'AiJobService'       => 'hq.job',
            'AiActionService'    => 'hq.action',
            'ImageGenService'    => 'image',
            'StudioService'      => 'studio',
        ];
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 14) as $f) {
            $cls = (string) ($f['class'] ?? '');
            if ($cls === '' || str_ends_with($cls, 'AiUsage') || str_ends_with($cls, 'AiAssistService')) {
                continue;
            }
            foreach ($map as $needle => $label) {
                if ($cls === $needle || str_ends_with($cls, '\\' . $needle)) {
                    return $label;
                }
            }
        }
        return 'hq.assist';
    }

    /**
     * Extract an OpenAI-style usage block from a decoded gateway response.
     *
     * @param mixed $data
     * @return array{prompt_tokens:?int,completion_tokens:?int,total_tokens:?int}
     */
    public static function usageFrom(mixed $data): array
    {
        $u = null;
        if (is_array($data)) {
            $u = $data['usage'] ?? ($data['completion']['usage'] ?? ($data['output']['usage'] ?? null));
        }
        $pick = static function (array $keys) use ($u): ?int {
            if (!is_array($u)) {
                return null;
            }
            foreach ($keys as $k) {
                if (isset($u[$k]) && is_numeric($u[$k])) {
                    return (int) $u[$k];
                }
            }
            return null;
        };
        // OpenAI (prompt/completion), Anthropic-style (input_tokens/output_tokens), RunPod native (input/output).
        $p = $pick(['prompt_tokens', 'input_tokens', 'input']);
        $c = $pick(['completion_tokens', 'output_tokens', 'output']);
        $t = $pick(['total_tokens']);
        if ($t === null && ($p !== null || $c !== null)) {
            $t = (int) $p + (int) $c;
        }
        return ['prompt_tokens' => $p, 'completion_tokens' => $c, 'total_tokens' => $t];
    }

    /**
     * @param array{feature?:string,kind?:string,model?:string,endpoint?:string,prompt_tokens?:?int,completion_tokens?:?int,total_tokens?:?int,latency_ms?:int,status?:string,error?:string,job_id?:string,estimated?:bool} $row
     */
    public static function record(array $row): void
    {
        try {
            $userId = null;
            if (class_exists(\Mova\Auth\Auth::class)) {
                try {
                    $userId = \Mova\Auth\Auth::id();
                } catch (\Throwable $e) {
                    $userId = null;
                }
            }
            $endpointHost = '';
            if (!empty($row['endpoint'])) {
                $endpointHost = (string) (parse_url((string) $row['endpoint'], PHP_URL_HOST) ?: '');
            }
            Database::insert('ai_usage', [
                'created_at'        => date('c'),
                'day'               => date('Y-m-d'),
                'feature'           => mb_substr((string) ($row['feature'] ?? self::currentFeature()), 0, 60),
                'kind'              => mb_substr((string) ($row['kind'] ?? 'chat'), 0, 20),
                'model'             => mb_substr((string) ($row['model'] ?? ''), 0, 120),
                'endpoint_host'     => mb_substr($endpointHost, 0, 120),
                'prompt_tokens'     => $row['prompt_tokens'] ?? null,
                'completion_tokens' => $row['completion_tokens'] ?? null,
                'total_tokens'      => $row['total_tokens'] ?? null,
                'estimated'         => !empty($row['estimated']) ? 1 : 0,
                'latency_ms'        => (int) ($row['latency_ms'] ?? 0),
                'status'            => ($row['status'] ?? 'ok') === 'ok' ? 'ok' : 'error',
                'error'             => isset($row['error']) ? mb_substr((string) $row['error'], 0, 300) : null,
                'user_id'           => $userId,
                'job_id'            => isset($row['job_id']) ? mb_substr((string) $row['job_id'], 0, 80) : null,
            ]);
        } catch (\Throwable $e) {
            // table missing / DB locked → ignore
        }
    }

    /** True when the gateway itself estimated (it flags `usage.estimated`). */
    public static function gatewayEstimated(mixed $data): bool
    {
        return is_array($data) && is_array($data['usage'] ?? null) && !empty($data['usage']['estimated']);
    }

    /**
     * Gateway-reported usage for THIS site (GET {api}/usage, gateway ≥ 1.4). Cached 5 minutes.
     * Returns null when the gateway does not support it or is unreachable.
     *
     * @return array<string,mixed>|null
     */
    public static function gatewayReport(int $days = 30, bool $refresh = false): ?array
    {
        $days = max(1, min(365, $days));
        $cacheKey = 'ai_gateway_usage_' . $days;
        if (!$refresh) {
            $c = json_decode(AiConfig::setting($cacheKey), true);
            if (is_array($c) && (int) ($c['_at'] ?? 0) > time() - 300) {
                return $c['data'] ?? null;
            }
        }
        $data = null;
        try {
            $caps = AiCapabilities::get();
            if (!empty($caps['usage'])) {
                $res = AiCapabilities::request('GET', '/usage?days=' . $days, null, 15, 'hq.ai_usage');
                if ($res['status'] === 200 && is_array($res['json']) && isset($res['json']['totals'])) {
                    $data = $res['json'];
                    $data['fetched_at'] = date('c');
                }
            }
        } catch (\Throwable $e) {
            $data = null;
        }
        AiConfig::put($cacheKey, (string) json_encode(['_at' => time(), 'data' => $data]));
        return $data;
    }

    /** Rough token estimate (~4 chars/token) when the gateway omits `usage`. */
    public static function estimateTokens(string $text): int
    {
        return (int) max(1, ceil(mb_strlen($text) / 4));
    }

    /**
     * @return array{totals:array,by_day:list<array>,by_feature:list<array>,by_model:list<array>,recent:list<array>}
     */
    public static function report(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $since = date('Y-m-d', time() - ($days - 1) * 86400);
        $p = ['s' => $since];
        $sel = "COUNT(*) AS calls,
                SUM(CASE WHEN status = 'ok' THEN 0 ELSE 1 END) AS errors,
                COALESCE(SUM(prompt_tokens),0) AS prompt_tokens,
                COALESCE(SUM(completion_tokens),0) AS completion_tokens,
                COALESCE(SUM(total_tokens),0) AS total_tokens,
                SUM(estimated) AS estimated_calls,
                CAST(COALESCE(AVG(latency_ms),0) AS INTEGER) AS avg_latency_ms";
        try {
            $totals = Database::fetch("SELECT {$sel} FROM ai_usage WHERE day >= :s", $p) ?: [];
            $byDay = Database::fetchAll("SELECT day, {$sel} FROM ai_usage WHERE day >= :s GROUP BY day ORDER BY day DESC", $p);
            $byFeature = Database::fetchAll("SELECT feature, {$sel} FROM ai_usage WHERE day >= :s GROUP BY feature ORDER BY calls DESC", $p);
            $byModel = Database::fetchAll("SELECT model, {$sel} FROM ai_usage WHERE day >= :s GROUP BY model ORDER BY calls DESC", $p);
            $recent = Database::fetchAll(
                "SELECT u.*, us.name AS user_name FROM ai_usage u LEFT JOIN users us ON us.id = u.user_id
                 ORDER BY u.created_at DESC, u.id DESC LIMIT 25"
            );
        } catch (\Throwable $e) {
            return ['totals' => [], 'by_day' => [], 'by_feature' => [], 'by_model' => [], 'recent' => [], 'error' => $e->getMessage()];
        }
        return ['totals' => $totals, 'by_day' => $byDay, 'by_feature' => $byFeature, 'by_model' => $byModel, 'recent' => $recent];
    }
}
