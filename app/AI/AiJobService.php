<?php
/**
 * Mova AI background jobs — queue long chat/coding work, poll for results.
 * Jobs are session-linked and survive page navigation.
 */

namespace Mova\AI;

use Mova\Core\Database;
use Mova\Core\Schema;

class AiJobService
{
    public static function ensureSchema(): void
    {
        try {
            Schema::migrate();
        } catch (\Throwable $e) {
            // best effort
        }
    }

    /**
     * @param array{route?:string,area?:string,layer?:string,entityId?:int|null} $pageContext
     * @return array<string,mixed>
     */
    public function enqueue(
        int $userId,
        string $prompt,
        ?int $sessionId = null,
        array $pageContext = [],
        string $kind = 'chat',
        ?string $clientKey = null
    ): array {
        self::ensureSchema();
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new \InvalidArgumentException('Empty prompt');
        }

        $kind = self::detectKind($prompt, $kind);
        $now = date('c');

        // Deduplicate recent identical client_key
        if ($clientKey) {
            $existing = Database::fetch(
                "SELECT * FROM ai_jobs WHERE user_id = :u AND client_key = :k AND status IN ('queued','running') ORDER BY id DESC LIMIT 1",
                ['u' => $userId, 'k' => $clientKey]
            );
            if ($existing) {
                return $this->publicJob($existing);
            }
        }

        $id = Database::insert('ai_jobs', [
            'user_id' => $userId,
            'session_id' => $sessionId,
            'status' => 'queued',
            'kind' => $kind,
            'prompt' => $prompt,
            'page_context' => json_encode($pageContext),
            'progress' => self::initialProgress($kind),
            'reply' => null,
            'actions' => null,
            'provider' => null,
            'error' => null,
            'client_key' => $clientKey,
            'created_at' => $now,
            'updated_at' => $now,
            'started_at' => null,
            'finished_at' => null,
        ]);

        return $this->getJob($id, $userId) ?? [
            'id' => $id,
            'status' => 'queued',
            'kind' => $kind,
            'progress' => self::initialProgress($kind),
        ];
    }

    public function getJob(int $id, int $userId): ?array
    {
        self::ensureSchema();
        $row = Database::fetch(
            "SELECT * FROM ai_jobs WHERE id = :id AND user_id = :u",
            ['id' => $id, 'u' => $userId]
        );
        return $row ? $this->publicJob($row) : null;
    }

    /** @return list<array<string,mixed>> */
    public function listJobs(int $userId, int $limit = 30, bool $activeOnly = false): array
    {
        self::ensureSchema();
        $limit = max(1, min(50, $limit));
        $sql = "SELECT * FROM ai_jobs WHERE user_id = :u";
        if ($activeOnly) {
            $sql .= " AND status IN ('queued','running')";
        }
        $sql .= " ORDER BY id DESC LIMIT {$limit}";
        $rows = Database::fetchAll($sql, ['u' => $userId]);
        return array_map(fn($r) => $this->publicJob($r), $rows);
    }

    /**
     * Claim and process one queued job for this user (or any if $anyUser).
     * @return array<string,mixed>|null processed job public shape
     */
    public function processNext(?int $userId = null): ?array
    {
        self::ensureSchema();
        $sql = "SELECT * FROM ai_jobs WHERE status = 'queued'";
        $params = [];
        if ($userId !== null) {
            $sql .= " AND user_id = :u";
            $params['u'] = $userId;
        }
        $sql .= " ORDER BY id ASC LIMIT 1";
        $row = Database::fetch($sql, $params);
        if (!$row) {
            return null;
        }
        return $this->processJob((int) $row['id'], (int) $row['user_id']);
    }

    public function processJob(int $jobId, int $userId): ?array
    {
        self::ensureSchema();
        $row = Database::fetch(
            "SELECT * FROM ai_jobs WHERE id = :id AND user_id = :u",
            ['id' => $jobId, 'u' => $userId]
        );
        if (!$row) {
            return null;
        }
        if (!in_array($row['status'], ['queued', 'running'], true)) {
            return $this->publicJob($row);
        }

        $now = date('c');
        Database::query(
            "UPDATE ai_jobs SET status = 'running', started_at = COALESCE(started_at, :s), updated_at = :u, progress = :p WHERE id = :id AND status IN ('queued','running')",
            [
                's' => $now,
                'u' => $now,
                'p' => self::progressForKind((string) $row['kind'], 1),
                'id' => $jobId,
            ]
        );

        $kind = (string) $row['kind'];
        $isImage = $kind === 'image';
        $isCoding = $kind === 'coding';
        // Allow long runs for coding / image generation / background
        $limit = $isImage ? 1200 : ($isCoding ? 1200 : 300);
        @set_time_limit($limit);
        @ini_set('max_execution_time', (string) $limit);
        @ignore_user_abort(true);

        $pageContext = [];
        if (!empty($row['page_context'])) {
            $decoded = json_decode((string) $row['page_context'], true);
            if (is_array($decoded)) {
                $pageContext = $decoded;
            }
        }

        // Update progress mid-flight (best effort)
        $this->touchProgress($jobId, self::progressForKind($kind, 2));

        try {
            if ($isImage) {
                $img = new ImageGenService();
                $aspect = (string) ($pageContext['aspect'] ?? 'square');
                $negative = (string) ($pageContext['negative_prompt'] ?? '');
                $gen = $img->generateAndIngest(
                    (string) $row['prompt'],
                    $aspect,
                    $negative,
                    $userId,
                    function (string $msg) use ($jobId) {
                        $this->touchProgress($jobId, $msg);
                    }
                );
                if (empty($gen['ok'])) {
                    throw new \RuntimeException((string) ($gen['error'] ?? 'Image generation failed'));
                }
                $media = $gen['media'] ?? [];
                $result = [
                    'reply' => 'Image generated and added to the media library.',
                    'actions' => [[
                        'type' => 'media_created',
                        'media_id' => (int) ($media['id'] ?? 0),
                        'url' => (string) ($gen['url'] ?? ''),
                        'width' => (int) ($media['width'] ?? 0),
                        'height' => (int) ($media['height'] ?? 0),
                        'path' => (string) ($media['path'] ?? ''),
                        'original_name' => (string) ($media['original_name'] ?? ''),
                        'mime_type' => (string) ($media['mime_type'] ?? ''),
                        'variants' => $media['variants'] ?? [],
                    ]],
                    'provider' => (string) ($gen['provider'] ?? 'Mova Image'),
                    'session_id' => 0,
                ];
            } else {
                $sessionId = !empty($row['session_id']) ? (int) $row['session_id'] : null;
                $chat = new AiChatService();
                $result = $chat->chat(
                    $userId,
                    $sessionId,
                    (string) $row['prompt'],
                    $pageContext,
                    [
                        'long_running' => true,
                        'kind' => $kind,
                        'skip_user_message' => false,
                    ]
                );
            }

            $finished = date('c');
            Database::query(
                "UPDATE ai_jobs SET status = 'done', reply = :r, actions = :a, provider = :p, progress = :prog, session_id = COALESCE(session_id, :sid), updated_at = :u, finished_at = :f, error = NULL WHERE id = :id",
                [
                    'r' => (string) ($result['reply'] ?? ''),
                    'a' => json_encode($result['actions'] ?? []),
                    'p' => (string) ($result['provider'] ?? ''),
                    'prog' => 'Done',
                    'sid' => (int) ($result['session_id'] ?? 0) ?: null,
                    'u' => $finished,
                    'f' => $finished,
                    'id' => $jobId,
                ]
            );
        } catch (\Throwable $e) {
            $finished = date('c');
            Database::query(
                "UPDATE ai_jobs SET status = 'failed', error = :e, progress = :prog, updated_at = :u, finished_at = :f WHERE id = :id",
                [
                    'e' => mb_substr($e->getMessage(), 0, 500),
                    'prog' => 'Failed',
                    'u' => $finished,
                    'f' => $finished,
                    'id' => $jobId,
                ]
            );
        }

        return $this->getJob($jobId, $userId);

    }

    public function cancelJob(int $jobId, int $userId): bool
    {
        self::ensureSchema();
        $row = Database::fetch(
            "SELECT id, status FROM ai_jobs WHERE id = :id AND user_id = :u",
            ['id' => $jobId, 'u' => $userId]
        );
        if (!$row || !in_array($row['status'], ['queued', 'running'], true)) {
            return false;
        }
        Database::query(
            "UPDATE ai_jobs SET status = 'cancelled', progress = 'Cancelled', updated_at = :u, finished_at = :f WHERE id = :id",
            ['u' => date('c'), 'f' => date('c'), 'id' => $jobId]
        );
        return true;
    }

    private function touchProgress(int $jobId, string $progress): void
    {
        try {
            Database::query(
                "UPDATE ai_jobs SET progress = :p, updated_at = :u WHERE id = :id AND status = 'running'",
                ['p' => $progress, 'u' => date('c'), 'id' => $jobId]
            );
        } catch (\Throwable $e) {
        }
    }

    /** @param array<string,mixed> $row */
    private function publicJob(array $row): array
    {
        $actions = [];
        if (!empty($row['actions'])) {
            $decoded = json_decode((string) $row['actions'], true);
            if (is_array($decoded)) {
                $actions = $decoded;
            }
        }
        return [
            'id' => (int) $row['id'],
            'session_id' => isset($row['session_id']) ? (int) $row['session_id'] : null,
            'status' => (string) $row['status'],
            'kind' => (string) ($row['kind'] ?? 'chat'),
            'prompt' => (string) ($row['prompt'] ?? ''),
            'progress' => (string) ($row['progress'] ?? ''),
            'reply' => $row['reply'] ?? null,
            'actions' => $actions,
            'provider' => $row['provider'] ?? null,
            'error' => $row['error'] ?? null,
            'client_key' => $row['client_key'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'started_at' => $row['started_at'] ?? null,
            'finished_at' => $row['finished_at'] ?? null,
        ];
    }

    public static function detectKind(string $prompt, string $fallback = 'chat'): string
    {
        // Explicit kinds from callers must not be reclassified
        if ($fallback === 'image') {
            return 'image';
        }
        $q = mb_strtolower($prompt);
        if (preg_match('/\b(html|css|javascript|js|code|snippet|stylesheet|markup|pricing table|pricelist|price list)\b/i', $q)) {
            return 'coding';
        }
        if (preg_match('/```/', $prompt)) {
            return 'coding';
        }
        return $fallback === 'coding' ? 'coding' : 'chat';
    }

    private static function initialProgress(string $kind): string
    {
        return $kind === 'coding' ? 'Queued — coding task' : 'Queued';
    }

    private static function progressForKind(string $kind, int $step): string
    {
        if ($kind === 'coding') {
            $steps = [
                1 => 'Starting coding job…',
                2 => 'Drafting structure…',
                3 => 'Writing HTML & CSS…',
                4 => 'Formatting for copy…',
            ];
            return $steps[$step] ?? 'Working…';
        }
        if ($kind === 'image') {
            $steps = [
                1 => 'Starting image job…',
                2 => 'Generating image…',
                3 => 'Saving to library…',
            ];
            return $steps[$step] ?? 'Generating…';
        }
        $steps = [
            1 => 'Starting…',
            2 => 'Thinking…',
            3 => 'Preparing reply…',
        ];
        return $steps[$step] ?? 'Working…';
    }
}
