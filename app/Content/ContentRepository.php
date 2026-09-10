<?php
/**
 * Mova CMS - Content Repository
 */

namespace Mova\Content;

use Mova\Core\Database;
use Mova\Core\Bootstrap;

class ContentRepository
{
    public function find(int $id): ?array
    {
        $row = Database::fetch("SELECT * FROM content WHERE id = :id", ['id' => $id]);
        return $row ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM content WHERE slug = :slug AND status = 'published'",
            ['slug' => $slug]
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function findBySlugAnyStatus(string $slug): ?array
    {
        $row = Database::fetch("SELECT * FROM content WHERE slug = :slug", ['slug' => $slug]);
        return $row ? $this->hydrate($row) : null;
    }

    public function all(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'type = :type';
            $params['type'] = $filters['type'];
        }
        if (!empty($filters['author_id'])) {
            $where[] = 'author_id = :author_id';
            $params['author_id'] = $filters['author_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(title LIKE :search OR excerpt LIKE :search OR body LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql = "SELECT * FROM content WHERE " . implode(' AND ', $where)
             . " ORDER BY COALESCE(published_at, created_at) DESC LIMIT :limit OFFSET :offset";

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $rows = Database::fetchAll($sql, $params);
        return array_map([$this, 'hydrate'], $rows);
    }

    public function count(array $filters = []): int
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'type = :type';
            $params['type'] = $filters['type'];
        }

        return Database::count('content', implode(' AND ', $where), $params);
    }

    public function published(int $limit = 20, int $offset = 0): array
    {
        return $this->all(['status' => 'published'], $limit, $offset);
    }

    public function create(array $data): int
    {
        $now = date('c');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $status = $data['status'] ?? 'draft';
        if ($status === 'published' && empty($data['published_at'])) {
            $data['published_at'] = $now;
        }
        if ($status === 'scheduled' && empty($data['published_at'])) {
            // fall back: schedule 1 hour ahead if no date given
            $data['published_at'] = date('c', time() + 3600);
        }

        // Auto-generate excerpt from body when left empty
        $data = $this->ensureExcerpt($data);

        $id = Database::insert('content', $this->filterFields($data));

        if (!empty($data['meta'])) {
            $this->saveMeta($id, $data['meta']);
        }

        return $id;
    }

    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('c');

        $status = $data['status'] ?? null;
        if ($status === 'published') {
            $existing = $this->find($id);
            if ($existing && empty($existing['published_at']) && empty($data['published_at'])) {
                $data['published_at'] = date('c');
            }
        }

        $meta = $data['meta'] ?? null;
        unset($data['meta']);

        // Auto-generate excerpt from body when excerpt is empty/missing
        $excerptEmpty = !array_key_exists('excerpt', $data) || trim((string) ($data['excerpt'] ?? '')) === '';
        if ($excerptEmpty) {
            $bodySource = (string) ($data['body'] ?? '');
            if ($bodySource === '') {
                $row = Database::fetch('SELECT body FROM content WHERE id = :id', ['id' => $id]);
                $bodySource = (string) ($row['body'] ?? '');
            }
            if ($bodySource !== '') {
                $data['excerpt'] = self::excerptFromBody($bodySource);
            }
        }

        // Always write the row. PDO rowCount() is often 0 on MySQL when values are unchanged,
        // which incorrectly looked like a failed save after re-editing published content.
        Database::update('content', $this->filterFields($data), 'id = :id', ['id' => $id]);

        if ($meta !== null) {
            $this->saveMeta($id, $meta);
        }

        return true;
    }

    public function delete(int $id): bool
    {
        return Database::delete('content', 'id = :id', ['id' => $id]) > 0;
    }

    public function trash(int $id): bool
    {
        return $this->update($id, ['status' => 'trash']);
    }

    public function restore(int $id): bool
    {
        return $this->update($id, ['status' => 'draft']);
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM content WHERE slug = :slug";
        $params = ['slug' => $slug];
        if ($excludeId) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }
        return (bool) Database::fetch($sql, $params);
    }

    public function generateSlug(string $title, ?int $excludeId = null): string
    {
        $slug = $this->slugify($title);
        $base = $slug;
        $i = 1;

        while ($this->slugExists($slug, $excludeId) || $this->isReserved($slug)) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    public function search(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', mb_strtolower($query)) ?: [];
        $tokens = array_values(array_filter($tokens, static function ($t) {
            return mb_strlen($t) >= 2;
        }));
        if (!$tokens) {
            $tokens = [mb_strtolower($query)];
        }

        // Prefer FTS with OR of tokens for long-tail
        try {
            $ftsQ = implode(' OR ', array_map(static function ($t) {
                $t = str_replace(['"', "'"], '', $t);
                return $t;
            }, $tokens));
            $rows = Database::fetchAll(
                "SELECT c.* FROM content_fts fts
                 JOIN content c ON c.id = fts.rowid
                 WHERE content_fts MATCH :q AND c.status = 'published'
                 ORDER BY rank
                 LIMIT :limit",
                ['q' => $ftsQ, 'limit' => $limit * 3]
            );
            $rows = array_map([$this, 'hydrate'], $rows);
        } catch (\Throwable $e) {
            $rows = $this->all(['status' => 'published'], 200);
        }

        // Score by token hits in title / excerpt / body; build snippet
        $scored = [];
        foreach ($rows as $row) {
            $title = mb_strtolower((string) ($row['title'] ?? ''));
            $excerpt = mb_strtolower((string) ($row['excerpt'] ?? ''));
            $bodyPlain = mb_strtolower(strip_tags((string) ($row['body'] ?? '')));
            $score = 0;
            $matched = 0;
            foreach ($tokens as $tok) {
                $hit = false;
                if ($tok !== '' && mb_strpos($title, $tok) !== false) {
                    $score += 12;
                    $hit = true;
                }
                if ($tok !== '' && mb_strpos($excerpt, $tok) !== false) {
                    $score += 6;
                    $hit = true;
                }
                if ($tok !== '' && mb_strpos($bodyPlain, $tok) !== false) {
                    $score += 3;
                    $hit = true;
                }
                if ($hit) {
                    $matched++;
                }
            }
            // Require at least one token hit for long-tail relevance
            if ($matched < 1) {
                continue;
            }
            $score += $matched * 2;
            $row['_score'] = $score;
            $row['search_snippet'] = self::snippetAroundTokens((string) ($row['body'] ?? $row['excerpt'] ?? ''), $tokens, 180);
            $scored[] = $row;
        }

        usort($scored, static function ($a, $b) {
            return ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0);
        });

        return array_slice($scored, 0, $limit);
    }

    /** Plain-text snippet centered on the first matched token */
    public static function snippetAroundTokens(string $html, array $tokens, int $maxLen = 180): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $lower = mb_strtolower($text);
        $pos = false;
        $found = '';
        foreach ($tokens as $tok) {
            if ($tok === '') continue;
            $p = mb_strpos($lower, $tok);
            if ($p !== false) {
                $pos = $p;
                $found = $tok;
                break;
            }
        }
        if ($pos === false) {
            return mb_substr($text, 0, $maxLen) . (mb_strlen($text) > $maxLen ? '…' : '');
        }
        $half = (int) floor($maxLen / 2);
        $start = max(0, $pos - $half);
        $chunk = mb_substr($text, $start, $maxLen);
        if ($start > 0) {
            $chunk = '…' . $chunk;
        }
        if ($start + $maxLen < mb_strlen($text)) {
            $chunk .= '…';
        }
        return $chunk;
    }

    public function getMeta(int $contentId): array
    {
        $rows = Database::fetchAll(
            "SELECT meta_key, meta_value FROM content_meta WHERE content_id = :id",
            ['id' => $contentId]
        );
        $meta = [];
        foreach ($rows as $row) {
            $meta[$row['meta_key']] = $row['meta_value'];
        }
        return $meta;
    }

    public function saveMeta(int $contentId, array $meta): void
    {
        foreach ($meta as $key => $value) {
            Database::query(
                "INSERT INTO content_meta (content_id, meta_key, meta_value)
                 VALUES (:cid, :key, :val)
                 ON CONFLICT(content_id, meta_key) DO UPDATE SET meta_value = :val2",
                ['cid' => $contentId, 'key' => $key, 'val' => $value, 'val2' => $value]
            );
        }
    }

    public function createRevision(int $contentId, array $snapshot, ?int $authorId = null): void
    {
        Database::insert('revisions', [
            'content_id' => $contentId,
            'title'      => $snapshot['title'] ?? null,
            'body'       => $snapshot['body'] ?? null,
            'excerpt'    => $snapshot['excerpt'] ?? null,
            'meta'       => json_encode($snapshot['meta'] ?? []),
            'author_id'  => $authorId,
            'created_at' => date('c'),
        ]);
    }

    public function listRevisions(int $contentId, int $limit = 30): array
    {
        return Database::fetchAll(
            "SELECT r.*, u.name AS author_name
             FROM revisions r
             LEFT JOIN users u ON u.id = r.author_id
             WHERE r.content_id = :cid
             ORDER BY r.created_at DESC
             LIMIT :lim",
            ['cid' => $contentId, 'lim' => $limit]
        );
    }

    public function findRevision(int $revisionId): ?array
    {
        $row = Database::fetch("SELECT * FROM revisions WHERE id = :id", ['id' => $revisionId]);
        if ($row && !empty($row['meta'])) {
            $row['meta'] = json_decode($row['meta'], true) ?: [];
        }
        return $row;
    }

    public function restoreRevision(int $contentId, int $revisionId, ?int $authorId = null): bool
    {
        $rev = $this->findRevision($revisionId);
        if (!$rev || (int) $rev['content_id'] !== $contentId) {
            return false;
        }

        // Snapshot current before restore
        $current = $this->find($contentId);
        if ($current) {
            $this->createRevision($contentId, $current, $authorId);
        }

        $this->update($contentId, [
            'title'   => $rev['title'] ?? $current['title'] ?? '',
            'body'    => $rev['body'] ?? '',
            'excerpt' => $rev['excerpt'] ?? '',
            'meta'    => is_array($rev['meta'] ?? null) ? $rev['meta'] : [],
        ]);

        return true;
    }


    /**
     * Promote scheduled content whose published_at has arrived.
     * Returns number of items published.
     */
    public function publishScheduled(): int
    {
        $now = date('c');
        $rows = Database::fetchAll(
            "SELECT id, slug FROM content
             WHERE status = 'scheduled'
               AND published_at IS NOT NULL
               AND published_at <= :now",
            ['now' => $now]
        );
        $count = 0;
        foreach ($rows as $row) {
            Database::update('content', [
                'status'     => 'published',
                'updated_at' => $now,
            ], 'id = :id', ['id' => $row['id']]);
            $count++;
        }
        return $count;
    }

    private function hydrate(array $row): array
    {
        $row['meta'] = $this->getMeta((int) $row['id']);
        // Fallback for older rows saved without an excerpt
        if (trim((string) ($row['excerpt'] ?? '')) === '' && !empty($row['body'])) {
            $row['excerpt'] = self::excerptFromBody((string) $row['body']);
        }
        return $row;
    }

    /**
     * Ensure excerpt is populated from body when empty.
     */
    private function ensureExcerpt(array $data): array
    {
        if (trim((string) ($data['excerpt'] ?? '')) === '' && !empty($data['body'])) {
            $data['excerpt'] = self::excerptFromBody((string) $data['body']);
        }
        return $data;
    }

    /**
     * Build a plain-text excerpt from HTML/Markdown body content.
     */
    public static function excerptFromBody(string $body, int $maxLength = 160): string
    {
        // Strip scripts/styles first
        $text = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $body) ?? $body;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }
        $cut = mb_substr($text, 0, $maxLength);
        // Prefer breaking on a word boundary
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > (int) ($maxLength * 0.6)) {
            $cut = mb_substr($cut, 0, $space);
        }
        return rtrim($cut, " \t\n\r\0\x0B.,;:!-") . '…';
    }

    private function filterFields(array $data): array
    {
        $allowed = [
            'type', 'title', 'slug', 'excerpt', 'body', 'status',
            'author_id', 'featured_image', 'template', 'canonical_url',
            'visibility', 'parent_id', 'sort_order', 'published_at',
            'created_at', 'updated_at',
        ];
        return array_intersect_key($data, array_flip($allowed));
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-') ?: 'content';
    }

    private function isReserved(string $slug): bool
    {
        $reserved = Bootstrap::config('reserved_routes', []);
        return in_array(strtolower($slug), array_map('strtolower', $reserved), true);
    }
}
