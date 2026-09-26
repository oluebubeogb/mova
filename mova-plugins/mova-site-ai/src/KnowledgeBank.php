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
}
