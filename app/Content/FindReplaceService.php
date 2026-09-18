<?php
/**
 * Mova CMS — Find & Replace across content (unlimited, with full changelog + revert)
 */

namespace Mova\Content;

use Mova\Core\Database;
use Mova\Auth\Auth;
use Mova\Security\Audit;
use Mova\Cache\PageCache;

class FindReplaceService
{
    private const FIELDS = ['title', 'excerpt', 'body'];

    /**
     * Preview matches without writing.
     *
     * @param array{
     *   find: string,
     *   match_case?: bool,
     *   statuses?: string[],
     *   content_ids?: int[],
     *   fields?: string[],
     *   content_search?: string,
     *   limit?: int
     * } $opts
     * @return array{matches: array, total_occurrences: int, total_contents: int}
     */
    public function preview(array $opts): array
    {
        $find = (string) ($opts['find'] ?? '');
        if ($find === '') {
            return ['matches' => [], 'total_occurrences' => 0, 'total_contents' => 0];
        }

        $matchCase = !empty($opts['match_case']);
        $fields = $this->normalizeFields($opts['fields'] ?? self::FIELDS);
        $rows = $this->loadCandidates($opts);

        $matches = [];
        $totalOcc = 0;
        $contentIds = [];

        foreach ($rows as $row) {
            $contentMatches = [];
            $contentOcc = 0;
            foreach ($fields as $field) {
                $value = (string) ($row[$field] ?? '');
                if ($value === '') {
                    continue;
                }
                $count = $this->countOccurrences($value, $find, $matchCase);
                if ($count > 0) {
                    $snippets = $this->buildSnippets($value, $find, $matchCase, 3);
                    $contentMatches[] = [
                        'field' => $field,
                        'count' => $count,
                        'snippets' => $snippets,
                    ];
                    $contentOcc += $count;
                }
            }
            if ($contentOcc > 0) {
                $matches[] = [
                    'id' => (int) $row['id'],
                    'title' => (string) $row['title'],
                    'slug' => (string) $row['slug'],
                    'status' => (string) $row['status'],
                    'type' => (string) ($row['type'] ?? ''),
                    'occurrences' => $contentOcc,
                    'fields' => $contentMatches,
                ];
                $totalOcc += $contentOcc;
                $contentIds[] = (int) $row['id'];
            }
        }

        return [
            'matches' => $matches,
            'total_occurrences' => $totalOcc,
            'total_contents' => count($contentIds),
        ];
    }

    /**
     * Apply find/replace and record a reversible action.
     *
     * @return array{action_id: int, match_count: int, content_count: int}
     */
    public function apply(array $opts): array
    {
        $find = (string) ($opts['find'] ?? '');
        $replace = (string) ($opts['replace'] ?? '');
        if ($find === '') {
            throw new \InvalidArgumentException('Find text is required.');
        }

        $matchCase = !empty($opts['match_case']);
        $fields = $this->normalizeFields($opts['fields'] ?? self::FIELDS);
        $rows = $this->loadCandidates($opts);

        $changes = [];
        $totalOcc = 0;
        $touchedIds = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $update = [];
            foreach ($fields as $field) {
                $old = (string) ($row[$field] ?? '');
                if ($old === '') {
                    continue;
                }
                $count = $this->countOccurrences($old, $find, $matchCase);
                if ($count === 0) {
                    continue;
                }
                $new = $this->replaceAll($old, $find, $replace, $matchCase);
                if ($new === $old) {
                    continue;
                }
                $update[$field] = $new;
                $changes[] = [
                    'content_id' => $id,
                    'field_name' => $field,
                    'old_value' => $old,
                    'new_value' => $new,
                    'occurrence_count' => $count,
                ];
                $totalOcc += $count;
            }
            if ($update !== []) {
                $update['updated_at'] = date('c');
                Database::update('content', $update, 'id = :id', ['id' => $id]);
                $touchedIds[$id] = true;
                // Keep FTS in sync if present
                $this->syncFts($id, array_merge($row, $update));
            }
        }

        $contentCount = count($touchedIds);
        $scope = !empty($opts['content_ids']) ? 'selected' : 'all';
        $statusFilter = null;
        if (!empty($opts['statuses']) && is_array($opts['statuses'])) {
            $statusFilter = implode(',', array_map('strval', $opts['statuses']));
        }

        $actionId = Database::insert('find_replace_actions', [
            'user_id' => Auth::id(),
            'find_text' => $find,
            'replace_text' => $replace,
            'match_case' => $matchCase ? 1 : 0,
            'scope' => $scope,
            'status_filter' => $statusFilter,
            'content_ids' => !empty($opts['content_ids'])
                ? json_encode(array_values(array_map('intval', $opts['content_ids'])))
                : null,
            'fields' => implode(',', $fields),
            'match_count' => $totalOcc,
            'content_count' => $contentCount,
            'status' => 'applied',
            'created_at' => date('c'),
        ]);

        foreach ($changes as $ch) {
            Database::insert('find_replace_changes', [
                'action_id' => $actionId,
                'content_id' => $ch['content_id'],
                'field_name' => $ch['field_name'],
                'old_value' => $ch['old_value'],
                'new_value' => $ch['new_value'],
                'occurrence_count' => $ch['occurrence_count'],
            ]);
        }

        Audit::log('find_replace', 'find_replace_action', $actionId, [
            'find' => $find,
            'replace' => $replace,
            'match_case' => $matchCase,
            'occurrences' => $totalOcc,
            'contents' => $contentCount,
        ]);

        // Bust page cache after bulk edit
        if (class_exists(PageCache::class)) {
            try {
                PageCache::flush();
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return [
            'action_id' => $actionId,
            'match_count' => $totalOcc,
            'content_count' => $contentCount,
        ];
    }

    /**
     * Revert an applied action by restoring old field values.
     */
    public function revert(int $actionId): bool
    {
        $action = Database::fetch(
            'SELECT * FROM find_replace_actions WHERE id = :id',
            ['id' => $actionId]
        );
        if (!$action || ($action['status'] ?? '') !== 'applied') {
            return false;
        }

        $changes = Database::fetchAll(
            'SELECT * FROM find_replace_changes WHERE action_id = :aid ORDER BY id DESC',
            ['aid' => $actionId]
        );

        $touched = [];
        foreach ($changes as $ch) {
            $cid = (int) $ch['content_id'];
            $field = (string) $ch['field_name'];
            if (!in_array($field, self::FIELDS, true)) {
                continue;
            }
            // Only restore if current value still matches what we wrote (avoid clobbering later edits)
            $current = Database::fetch(
                "SELECT id, title, excerpt, body FROM content WHERE id = :id",
                ['id' => $cid]
            );
            if (!$current) {
                continue;
            }
            if ((string) ($current[$field] ?? '') !== (string) $ch['new_value']) {
                // Content was edited after the replace — skip this field, keep changelog
                continue;
            }
            Database::update(
                'content',
                [$field => $ch['old_value'], 'updated_at' => date('c')],
                'id = :id',
                ['id' => $cid]
            );
            $touched[$cid] = $current;
            $touched[$cid][$field] = $ch['old_value'];
        }

        foreach ($touched as $cid => $row) {
            $this->syncFts((int) $cid, $row);
        }

        Database::update('find_replace_actions', [
            'status' => 'reverted',
            'reverted_at' => date('c'),
            'reverted_by' => Auth::id(),
        ], 'id = :id', ['id' => $actionId]);

        Audit::log('find_replace_revert', 'find_replace_action', $actionId, [
            'contents_restored' => count($touched),
        ]);

        if (class_exists(PageCache::class)) {
            try {
                PageCache::flush();
            } catch (\Throwable $e) {
            }
        }

        return true;
    }

    /**
     * @return array<int, array>
     */
    public function listActions(int $limit = 50, int $offset = 0): array
    {
        $rows = Database::fetchAll(
            'SELECT a.*, u.username, u.name AS display_name
             FROM find_replace_actions a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC
             LIMIT :limit OFFSET :offset',
            ['limit' => $limit, 'offset' => $offset]
        );
        return $rows ?: [];
    }

    public function getAction(int $id): ?array
    {
        $row = Database::fetch(
            'SELECT a.*, u.username, u.name AS display_name
             FROM find_replace_actions a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.id = :id',
            ['id' => $id]
        );
        if (!$row) {
            return null;
        }
        $row['changes'] = Database::fetchAll(
            'SELECT c.*, ct.title AS content_title, ct.slug AS content_slug, ct.status AS content_status
             FROM find_replace_changes c
             LEFT JOIN content ct ON ct.id = c.content_id
             WHERE c.action_id = :aid
             ORDER BY c.id ASC',
            ['aid' => $id]
        );
        return $row;
    }

    public function countActions(): int
    {
        return Database::count('find_replace_actions');
    }

    // ── internals ──────────────────────────────────────────────

    private function normalizeFields($fields): array
    {
        if (!is_array($fields) || $fields === []) {
            return self::FIELDS;
        }
        $out = [];
        foreach ($fields as $f) {
            $f = strtolower(trim((string) $f));
            if (in_array($f, self::FIELDS, true)) {
                $out[] = $f;
            }
        }
        return $out ?: self::FIELDS;
    }

    /**
     * Load content rows matching scope / status / optional content search.
     */
    private function loadCandidates(array $opts): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($opts['content_ids']) && is_array($opts['content_ids'])) {
            $ids = array_values(array_filter(array_map('intval', $opts['content_ids'])));
            if ($ids === []) {
                return [];
            }
            $placeholders = [];
            foreach ($ids as $i => $id) {
                $key = 'cid' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $id;
            }
            $where[] = 'id IN (' . implode(',', $placeholders) . ')';
        } elseif (!empty($opts['statuses']) && is_array($opts['statuses'])) {
            $statuses = array_values(array_filter(array_map('strval', $opts['statuses'])));
            if ($statuses !== []) {
                $placeholders = [];
                foreach ($statuses as $i => $st) {
                    $key = 'st' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $st;
                }
                $where[] = 'status IN (' . implode(',', $placeholders) . ')';
            }
        }

        if (!empty($opts['content_search'])) {
            $where[] = '(title LIKE :csearch OR slug LIKE :csearch OR excerpt LIKE :csearch)';
            $params['csearch'] = '%' . $opts['content_search'] . '%';
        }

        // Soft cap to protect memory; "unlimited" replaces mean no artificial product limit,
        // but we still process in reasonable batches for preview UI.
        $limit = (int) ($opts['limit'] ?? 5000);
        if ($limit < 1) {
            $limit = 5000;
        }
        if ($limit > 50000) {
            $limit = 50000;
        }
        $params['lim'] = $limit;

        $sql = 'SELECT id, title, slug, status, type, excerpt, body
                FROM content
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY id ASC
                LIMIT :lim';

        return Database::fetchAll($sql, $params) ?: [];
    }

    private function countOccurrences(string $haystack, string $needle, bool $matchCase): int
    {
        if ($needle === '') {
            return 0;
        }
        if ($matchCase) {
            $count = 0;
            $offset = 0;
            $len = strlen($needle);
            while (($pos = strpos($haystack, $needle, $offset)) !== false) {
                $count++;
                $offset = $pos + max(1, $len);
            }
            return $count;
        }
        return substr_count(mb_strtolower($haystack), mb_strtolower($needle));
    }

    private function replaceAll(string $haystack, string $needle, string $replace, bool $matchCase): string
    {
        if ($needle === '') {
            return $haystack;
        }
        if ($matchCase) {
            return str_replace($needle, $replace, $haystack);
        }
        // Case-insensitive replace while preserving non-matched text
        return preg_replace('/' . preg_quote($needle, '/') . '/iu', $replace, $haystack) ?? $haystack;
    }

    /**
     * Build short context snippets around matches for the preview UI.
     */
    private function buildSnippets(string $text, string $needle, bool $matchCase, int $max = 3): array
    {
        $snippets = [];
        $flags = $matchCase ? '' : 'i';
        $pattern = '/' . preg_quote($needle, '/') . '/u' . $flags;
        if (!preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $shown = 0;
        foreach ($m[0] as $hit) {
            if ($shown >= $max) {
                break;
            }
            $pos = $hit[1];
            $len = strlen($hit[0]);
            $start = max(0, $pos - 40);
            $end = min(strlen($text), $pos + $len + 40);
            $before = substr($text, $start, $pos - $start);
            $match = substr($text, $pos, $len);
            $after = substr($text, $pos + $len, $end - ($pos + $len));
            // Collapse whitespace for display
            $before = preg_replace('/\s+/', ' ', $before) ?? $before;
            $after = preg_replace('/\s+/', ' ', $after) ?? $after;
            $snippets[] = [
                'before' => ($start > 0 ? '…' : '') . ltrim($before),
                'match' => $match,
                'after' => rtrim($after) . ($end < strlen($text) ? '…' : ''),
            ];
            $shown++;
        }
        return $snippets;
    }

    private function syncFts(int $id, array $row): void
    {
        try {
            Database::query('DELETE FROM content_fts WHERE rowid = :id', ['id' => $id]);
            Database::query(
                'INSERT INTO content_fts(rowid, title, excerpt, body) VALUES (:id, :title, :excerpt, :body)',
                [
                    'id' => $id,
                    'title' => (string) ($row['title'] ?? ''),
                    'excerpt' => (string) ($row['excerpt'] ?? ''),
                    'body' => (string) ($row['body'] ?? ''),
                ]
            );
        } catch (\Throwable $e) {
            // FTS may not exist on all installs
        }
    }
}
