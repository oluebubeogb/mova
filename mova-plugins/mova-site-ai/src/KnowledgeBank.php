<?php
namespace MovaSiteAi;

use Mova\Core\Database;

class KnowledgeBank
{
    public static function ensureSchema(): void
    {
        try {
            $db = Database::connection();
            $db->exec("CREATE TABLE IF NOT EXISTS site_ai_sources (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                type TEXT NOT NULL,
                source_ref TEXT,
                status TEXT NOT NULL DEFAULT 'ready',
                meta TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS site_ai_chunks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source_id INTEGER NOT NULL,
                chunk_index INTEGER NOT NULL DEFAULT 0,
                content TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (source_id) REFERENCES site_ai_sources(id) ON DELETE CASCADE
            )");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_site_ai_chunks_source ON site_ai_chunks(source_id)");
            $db->exec("CREATE TABLE IF NOT EXISTS site_ai_settings (
                setting_key TEXT PRIMARY KEY,
                setting_value TEXT,
                updated_at TEXT NOT NULL
            )");

            // Anonymous sessions (server mirror of browser localStorage)
            $db->exec("CREATE TABLE IF NOT EXISTS site_ai_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                visitor_id TEXT NOT NULL,
                client_id TEXT,
                title TEXT NOT NULL DEFAULT 'New chat',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_site_ai_sessions_visitor ON site_ai_sessions(visitor_id)");
            $db->exec("CREATE TABLE IF NOT EXISTS site_ai_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id INTEGER NOT NULL,
                role TEXT NOT NULL,
                content TEXT NOT NULL,
                links TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY (session_id) REFERENCES site_ai_sessions(id) ON DELETE CASCADE
            )");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_site_ai_messages_session ON site_ai_messages(session_id)");

            // Like / dislike feedback
            $db->exec("CREATE TABLE IF NOT EXISTS site_ai_feedback (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                visitor_id TEXT,
                session_id TEXT,
                message_hash TEXT,
                rating TEXT NOT NULL,
                page_path TEXT,
                created_at TEXT NOT NULL
            )");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_site_ai_feedback_visitor ON site_ai_feedback(visitor_id)");
        } catch (\Throwable $e) {
            // ignore until DB ready
        }
    }

    /** @return list<array<string,mixed>> */
    public static function listSources(): array
    {
        self::ensureSchema();
        return Database::fetchAll("SELECT * FROM site_ai_sources ORDER BY updated_at DESC");
    }

    public static function deleteSource(int $id): void
    {
        Database::query("DELETE FROM site_ai_chunks WHERE source_id = :id", ['id' => $id]);
        Database::query("DELETE FROM site_ai_sources WHERE id = :id", ['id' => $id]);
    }

    /**
     * Index plain text into chunks.
     */
    public static function addTextSource(string $title, string $type, string $text, ?string $ref = null): int
    {
        self::ensureSchema();
        $now = date('c');
        $id = Database::insert('site_ai_sources', [
            'title' => mb_substr($title, 0, 200),
            'type' => $type,
            'source_ref' => $ref,
            'status' => 'ready',
            'meta' => json_encode(['chars' => mb_strlen($text)]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $chunks = self::chunkText($text, 900);
        foreach ($chunks as $i => $chunk) {
            Database::insert('site_ai_chunks', [
                'source_id' => $id,
                'chunk_index' => $i,
                'content' => $chunk,
                'created_at' => $now,
            ]);
        }
        return $id;
    }

    /**
     * Create a source in indexing state and store raw text in meta for processSource.
     */
    public static function queueUpload(string $tmpPath, string $filename): int
    {
        self::ensureSchema();
        $text = self::extractTextFromUpload($tmpPath, $filename);
        if (trim($text) === '') {
            return 0;
        }
        $text = mb_substr($text, 0, 100000);
        $now = date('c');
        $id = Database::insert('site_ai_sources', [
            'title' => mb_substr($filename, 0, 200),
            'type' => 'file',
            'source_ref' => $filename,
            'status' => 'indexing',
            'meta' => json_encode(['pending_text' => $text, 'chars' => mb_strlen($text)]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $id;
    }

    public static function processSource(int $id): bool
    {
        self::ensureSchema();
        $row = Database::fetch('SELECT * FROM site_ai_sources WHERE id = :id', ['id' => $id]);
        if (!$row) {
            return false;
        }
        $meta = [];
        if (!empty($row['meta'])) {
            $decoded = json_decode((string) $row['meta'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }
        $text = (string) ($meta['pending_text'] ?? '');
        if ($text === '') {
            Database::query(
                "UPDATE site_ai_sources SET status = 'ready', updated_at = :t WHERE id = :id",
                ['t' => date('c'), 'id' => $id]
            );
            return true;
        }
        Database::query("DELETE FROM site_ai_chunks WHERE source_id = :id", ['id' => $id]);
        $now = date('c');
        $chunks = self::chunkText($text, 900);
        foreach ($chunks as $i => $chunk) {
            Database::insert('site_ai_chunks', [
                'source_id' => $id,
                'chunk_index' => $i,
                'content' => $chunk,
                'created_at' => $now,
            ]);
        }
        unset($meta['pending_text']);
        $meta['chars'] = mb_strlen($text);
        $meta['chunks'] = count($chunks);
        Database::query(
            "UPDATE site_ai_sources SET status = 'ready', meta = :m, updated_at = :t WHERE id = :id",
            ['m' => json_encode($meta), 't' => $now, 'id' => $id]
        );
        return true;
    }

    /** @return list<string> */
    public static function chunkText(string $text, int $size = 900): array
    {
        $text = trim(preg_replace("/\r\n?/", "\n", $text) ?? $text);
        if ($text === '') {
            return [];
        }
        $parts = [];
        $len = mb_strlen($text);
        for ($i = 0; $i < $len; $i += $size) {
            $parts[] = mb_substr($text, $i, $size);
        }
        return $parts;
    }

    /**
     * Simple keyword retrieval for RAG context.
     * @return list<array{content:string,title:string}>
     */
    public static function search(string $query, int $limit = 6): array
    {
        self::ensureSchema();
        $words = preg_split('/\s+/', mb_strtolower($query)) ?: [];
        $words = array_values(array_filter($words, static fn($w) => mb_strlen($w) > 2));
        if ($words === []) {
            return [];
        }
        $rows = Database::fetchAll(
            "SELECT c.content, s.title FROM site_ai_chunks c
             JOIN site_ai_sources s ON s.id = c.source_id
             WHERE s.status = 'ready'
             ORDER BY c.id DESC LIMIT 400"
        );
        $scored = [];
        foreach ($rows as $row) {
            $hay = mb_strtolower($row['content']);
            $score = 0;
            foreach ($words as $w) {
                if (str_contains($hay, $w)) {
                    $score += 1;
                }
            }
            if ($score > 0) {
                $scored[] = ['score' => $score, 'content' => $row['content'], 'title' => $row['title']];
            }
        }
        usort($scored, static fn($a, $b) => $b['score'] <=> $a['score']);
        $out = [];
        foreach (array_slice($scored, 0, $limit) as $r) {
            $out[] = ['content' => $r['content'], 'title' => $r['title']];
        }
        return $out;
    }

    public static function extractTextFromUpload(string $tmpPath, string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $raw = @file_get_contents($tmpPath);
        if ($raw === false) {
            return '';
        }
        if (in_array($ext, ['txt', 'md', 'csv', 'html', 'htm', 'json', 'xml'], true)) {
            if ($ext === 'html' || $ext === 'htm') {
                return trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            return $raw;
        }
        if ($ext === 'docx') {
            return self::extractDocx($tmpPath);
        }
        if ($ext === 'doc') {
            return self::extractDocLegacy($raw);
        }
        if ($ext === 'xlsx' || $ext === 'xls') {
            return self::extractSpreadsheet($tmpPath, $ext);
        }
        if ($ext === 'pdf') {
            // best-effort: strip binary noise
            $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]/u', ' ', $raw) ?? '';
            return trim(preg_replace('/\s+/', ' ', $text) ?? '');
        }
        return $raw;
    }

    private static function extractDocx(string $path): string
    {
        if (!class_exists('ZipArchive')) {
            return '';
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            return '';
        }
        $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
        return trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** Best-effort for old .doc binary — extract printable runs. */
    private static function extractDocLegacy(string $raw): string
    {
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $raw) ?? '';
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private static function extractSpreadsheet(string $path, string $ext): string
    {
        if ($ext === 'xlsx' && class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                return '';
            }
            $shared = [];
            $ss = $zip->getFromName('xl/sharedStrings.xml');
            if ($ss !== false) {
                if (preg_match_all('/<t[^>]*>([^<]*)<\/t>/', $ss, $m)) {
                    $shared = $m[1];
                }
            }
            $parts = [];
            for ($i = 1; $i <= 20; $i++) {
                $sheet = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
                if ($sheet === false) {
                    break;
                }
                // Inline strings
                if (preg_match_all('/<t[^>]*>([^<]*)<\/t>/', $sheet, $im)) {
                    foreach ($im[1] as $t) {
                        $parts[] = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                }
                // Shared string references
                if (preg_match_all('/<c[^>]*t="s"[^>]*>\s*<v>(\d+)<\/v>/', $sheet, $cm)) {
                    foreach ($cm[1] as $idx) {
                        $idx = (int) $idx;
                        if (isset($shared[$idx])) {
                            $parts[] = html_entity_decode($shared[$idx], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        }
                    }
                }
            }
            $zip->close();
            return trim(implode("\n", array_filter($parts, static fn($p) => trim($p) !== '')));
        }
        // xls or fallback: printable text
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return '';
        }
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $raw) ?? '';
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }
}
