<?php
namespace MovaSiteAi;

use Mova\Core\Database;

/**
 * Anonymous site AI sessions + feedback (server mirror of browser localStorage).
 * Keyed by visitor_id cookie (msa_vid), no login required.
 */
class SiteAiSessions
{
    public static function ensureSchema(): void
    {
        KnowledgeBank::ensureSchema();
    }

    public static function resolveVisitorId(?string $fromRequest = null): string
    {
        $vid = trim((string) $fromRequest);
        if ($vid === '' && isset($_COOKIE['msa_vid'])) {
            $vid = trim((string) $_COOKIE['msa_vid']);
        }
        if ($vid === '' || !preg_match('/^[a-f0-9\-]{8,64}$/i', $vid)) {
            $vid = self::generateId();
        }
        // Refresh cookie (~14 days)
        if (!headers_sent()) {
            setcookie('msa_vid', $vid, [
                'expires' => time() + 14 * 86400,
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        return $vid;
    }

    public static function generateId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            return uniqid('msa', true);
        }
    }

    /** @return list<array<string,mixed>> */
    public static function listSessions(string $visitorId, int $limit = 20): array
    {
        self::ensureSchema();
        $limit = max(1, min(50, (int) $limit));
        return Database::fetchAll(
            "SELECT id, visitor_id, client_id, title, created_at, updated_at
             FROM site_ai_sessions
             WHERE visitor_id = :v
             ORDER BY updated_at DESC
             LIMIT {$limit}",
            ['v' => $visitorId]
        );
    }

    public static function getSession(int $id, string $visitorId): ?array
    {
        self::ensureSchema();
        return Database::fetch(
            "SELECT * FROM site_ai_sessions WHERE id = :id AND visitor_id = :v",
            ['id' => $id, 'v' => $visitorId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function listMessages(int $sessionId, string $visitorId): array
    {
        self::ensureSchema();
        $session = self::getSession($sessionId, $visitorId);
        if (!$session) {
            return [];
        }
        $rows = Database::fetchAll(
            "SELECT id, role, content, links, created_at FROM site_ai_messages
             WHERE session_id = :s ORDER BY id ASC LIMIT 100",
            ['s' => $sessionId]
        );
        foreach ($rows as &$r) {
            $links = [];
            if (!empty($r['links'])) {
                $decoded = json_decode((string) $r['links'], true);
                if (is_array($decoded)) {
                    $links = $decoded;
                }
            }
            $r['links'] = $links;
        }
        unset($r);
        return $rows;
    }

    public static function createSession(string $visitorId, string $title = 'New chat', ?string $clientId = null): array
    {
        self::ensureSchema();
        $now = date('c');
        $id = Database::insert('site_ai_sessions', [
            'visitor_id' => $visitorId,
            'client_id' => $clientId,
            'title' => mb_substr($title !== '' ? $title : 'New chat', 0, 120),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return [
            'id' => $id,
            'visitor_id' => $visitorId,
            'client_id' => $clientId,
            'title' => mb_substr($title !== '' ? $title : 'New chat', 0, 120),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    public static function deleteSession(int $id, string $visitorId): bool
    {
        self::ensureSchema();
        $session = self::getSession($id, $visitorId);
        if (!$session) {
            return false;
        }
        Database::query("DELETE FROM site_ai_messages WHERE session_id = :s", ['s' => $id]);
        Database::query("DELETE FROM site_ai_sessions WHERE id = :id AND visitor_id = :v", [
            'id' => $id,
            'v' => $visitorId,
        ]);
        return true;
    }

    /**
     * Append a message; creates session if needed.
     * @param list<array{label?:string,path:string}>|null $links
     * @return array{session_id:int,message_id:int}
     */
    public static function appendMessage(
        string $visitorId,
        ?int $sessionId,
        string $role,
        string $content,
        ?array $links = null,
        ?string $clientId = null,
        ?string $titleHint = null
    ): array {
        self::ensureSchema();
        if (!$sessionId) {
            $title = $titleHint ?: self::titleFromMessage($content);
            $session = self::createSession($visitorId, $title, $clientId);
            $sessionId = (int) $session['id'];
        } else {
            $session = self::getSession($sessionId, $visitorId);
            if (!$session) {
                $title = $titleHint ?: self::titleFromMessage($content);
                $session = self::createSession($visitorId, $title, $clientId);
                $sessionId = (int) $session['id'];
            }
        }
        $now = date('c');
        $msgId = Database::insert('site_ai_messages', [
            'session_id' => $sessionId,
            'role' => $role === 'user' ? 'user' : 'assistant',
            'content' => $content,
            'links' => $links ? json_encode(array_values($links)) : null,
            'created_at' => $now,
        ]);
        Database::query(
            "UPDATE site_ai_sessions SET updated_at = :t WHERE id = :id",
            ['t' => $now, 'id' => $sessionId]
        );
        // Cap messages per session
        $count = (int) (Database::fetch(
            "SELECT COUNT(*) AS c FROM site_ai_messages WHERE session_id = :s",
            ['s' => $sessionId]
        )['c'] ?? 0);
        if ($count > 80) {
            $n = (int) ($count - 60);
            Database::query(
                "DELETE FROM site_ai_messages WHERE session_id = :s AND id IN (
                    SELECT id FROM site_ai_messages WHERE session_id = :s2 ORDER BY id ASC LIMIT {$n}
                )",
                ['s' => $sessionId, 's2' => $sessionId]
            );
        }
        return ['session_id' => $sessionId, 'message_id' => $msgId];
    }

    public static function saveFeedback(
        string $visitorId,
        string $rating,
        ?string $sessionId = null,
        ?string $messageHash = null,
        ?string $pagePath = null
    ): bool {
        self::ensureSchema();
        $rating = $rating === 'down' ? 'down' : 'up';
        Database::insert('site_ai_feedback', [
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'message_hash' => $messageHash ? mb_substr($messageHash, 0, 64) : null,
            'rating' => $rating,
            'page_path' => $pagePath ? mb_substr($pagePath, 0, 255) : null,
            'created_at' => date('c'),
        ]);
        return true;
    }

    private static function titleFromMessage(string $content): string
    {
        $t = trim(preg_replace('/\s+/', ' ', $content) ?? $content);
        if (mb_strlen($t) > 48) {
            $t = mb_substr($t, 0, 45) . '…';
        }
        return $t !== '' ? $t : 'New chat';
    }
}
