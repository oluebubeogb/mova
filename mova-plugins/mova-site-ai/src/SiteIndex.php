<?php
/**
 * Site AI retrieval index — SQLite FTS5 over published public content + knowledge-bank chunks.
 *
 * - Lazily (re)built when the content signature changes; no cron needed.
 * - BM25 ranking with title boost; porter stemming ("prices" finds "pricing").
 * - Falls back to a plain table + LIKE scoring when the SQLite build lacks FTS5.
 * Only published, public content is indexed — drafts and private pages never reach the visitor model.
 */

namespace MovaSiteAi;

use Mova\Core\Database;

class SiteIndex
{
    private const TABLE = 'site_ai_index';
    private const SIG_KEY = 'site_ai_index_sig';
    private const STOP = ['the', 'and', 'for', 'are', 'you', 'your', 'with', 'what', 'how', 'can', 'does', 'this', 'that', 'have', 'has',
        'about', 'from', 'tell', 'me', 'is', 'do', 'of', 'to', 'in', 'on', 'a', 'an', 'i', 'we', 'our', 'us', 'it', 'be', 'or', 'any', 'there', 'where', 'when', 'who', 'which', 'please'];

    private static ?string $engine = null;

    public static function engine(): string
    {
        if (self::$engine !== null) {
            return self::$engine;
        }
        try {
            $row = Database::fetch("SELECT sql FROM sqlite_master WHERE name = :n", ['n' => self::TABLE]);
            if ($row) {
                return self::$engine = stripos((string) $row['sql'], 'fts5') !== false ? 'fts5' : 'like';
            }
        } catch (\Throwable $e) {
        }
        return 'none';
    }

    private static function create(): void
    {
        $db = Database::connection();
        $db->exec('DROP TABLE IF EXISTS ' . self::TABLE);
        try {
            $db->exec('CREATE VIRTUAL TABLE ' . self::TABLE . " USING fts5(title, body, path UNINDEXED, kind UNINDEXED, ref UNINDEXED, tokenize = 'porter unicode61 remove_diacritics 2')");
            self::$engine = 'fts5';
        } catch (\Throwable $e) {
            $db->exec('CREATE TABLE ' . self::TABLE . ' (title TEXT, body TEXT, path TEXT, kind TEXT, ref TEXT)');
            self::$engine = 'like';
        }
    }

    public static function signature(): string
    {
        $c = Database::fetch("SELECT COUNT(*) n, MAX(updated_at) u FROM content WHERE status = 'published'") ?: [];
        $k = ['n' => 0, 'm' => 0];
        try {
            $k = Database::fetch("SELECT COUNT(*) n, MAX(c.id) m FROM site_ai_chunks c JOIN site_ai_sources s ON s.id = c.source_id WHERE s.status = 'ready'") ?: $k;
        } catch (\Throwable $e) {
        }
        return md5(json_encode([$c['n'] ?? 0, $c['u'] ?? '', $k['n'] ?? 0, $k['m'] ?? 0, 2]));
    }

    public static function ensureFresh(bool $force = false): void
    {
        try {
            $sig = self::signature();
            $row = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = :k", ['k' => self::SIG_KEY]);
            if (!$force && $row && (string) $row['setting_value'] === $sig && self::engine() !== 'none') {
                return;
            }
            self::rebuild($sig);
        } catch (\Throwable $e) {
            // index is an optimisation; search() falls back to KnowledgeBank keyword search
        }
    }

    public static function rebuild(?string $sig = null): int
    {
        KnowledgeBank::ensureSchema();
        $db = Database::connection();
        $db->beginTransaction();
        try {
            self::create();
            $ins = $db->prepare('INSERT INTO ' . self::TABLE . ' (title, body, path, kind, ref) VALUES (?, ?, ?, ?, ?)');
            $n = 0;
            $rows = Database::fetchAll("SELECT id, type, title, slug, excerpt, body FROM content WHERE status = 'published' AND COALESCE(visibility, 'public') = 'public'");
            foreach ($rows as $r) {
                $slug = (string) $r['slug'];
                $path = in_array($slug, ['home', 'index', ''], true) ? '/' : '/' . ltrim($slug, '/');
                $text = self::plain((string) ($r['body'] ?? ''));
                $excerpt = self::plain((string) ($r['excerpt'] ?? ''));
                // Skip auto-generated excerpts (a squashed copy of the body's first words).
                $squash = static fn (string $x) => preg_replace('/[\s…\.]+/u', '', mb_strtolower($x)) ?? '';
                if ($excerpt !== '' && !str_starts_with($squash($text), mb_substr($squash($excerpt), 0, 60))) {
                    $text = $excerpt . "\n" . $text;
                }
                foreach (self::chunks($text) as $i => $chunk) {
                    $ins->execute([(string) $r['title'], $chunk, $path, (string) $r['type'], 'content:' . $r['id'] . ':' . $i]);
                    $n++;
                }
                if ($text === '') {
                    $ins->execute([(string) $r['title'], '', $path, (string) $r['type'], 'content:' . $r['id'] . ':0']);
                    $n++;
                }
            }
            $kb = Database::fetchAll("SELECT c.id, c.content, s.title, s.type FROM site_ai_chunks c JOIN site_ai_sources s ON s.id = c.source_id WHERE s.status = 'ready'");
            foreach ($kb as $r) {
                $ins->execute([(string) $r['title'], (string) $r['content'], '', 'kb', 'kb:' . $r['id']]);
                $n++;
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $sig ??= self::signature();
        Database::query(
            "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :t)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at",
            ['k' => self::SIG_KEY, 'v' => $sig, 't' => date('c')]
        );
        return $n;
    }

    /**
     * @return list<array{title:string,content:string,path:string,kind:string,score:float}>
     */
    public static function search(string $query, int $limit = 6): array
    {
        self::ensureFresh();
        $tokens = self::tokens($query);
        if ($tokens === [] || self::engine() === 'none') {
            return [];
        }
        $rows = [];
        if (self::engine() === 'fts5') {
            $match = implode(' OR ', array_map(static fn ($t) => '"' . $t . '"' . (mb_strlen($t) >= 4 ? '*' : ''), $tokens));
            try {
                $rows = Database::fetchAll(
                    'SELECT title, body, path, kind, bm25(' . self::TABLE . ', 4.0, 1.0) AS rank FROM ' . self::TABLE
                    . ' WHERE ' . self::TABLE . ' MATCH :q ORDER BY rank LIMIT ' . (int) ($limit * 4),
                    ['q' => $match]
                );
            } catch (\Throwable $e) {
                $rows = [];
            }
            // bm25 IDF collapses to ~0 for terms found on most pages (e.g. "volunteer" on a charity site),
            // so add a small term-frequency / title tie-breaker to keep the order meaningful.
            foreach ($rows as &$r) {
                $hayT = mb_strtolower((string) $r['title']);
                $hayB = mb_strtolower((string) $r['body']);
                $tf = 0.0;
                foreach ($tokens as $t) {
                    $tf += (str_contains($hayT, $t) ? 0.5 : 0) + 0.1 * min(5, substr_count($hayB, $t));
                }
                $r['score'] = -(float) $r['rank'] + $tf;
            }
            unset($r);
            usort($rows, static fn ($a, $b) => $b['score'] <=> $a['score']);
        } else {
            $where = [];
            $params = [];
            foreach ($tokens as $i => $t) {
                $where[] = "(LOWER(title) LIKE :t{$i} OR LOWER(body) LIKE :t{$i})";
                $params["t{$i}"] = '%' . $t . '%';
            }
            $cand = Database::fetchAll('SELECT title, body, path, kind FROM ' . self::TABLE . ' WHERE ' . implode(' OR ', $where) . ' LIMIT 400', $params);
            foreach ($cand as $r) {
                $hayT = mb_strtolower((string) $r['title']);
                $hayB = mb_strtolower((string) $r['body']);
                $s = 0.0;
                foreach ($tokens as $t) {
                    $s += (str_contains($hayT, $t) ? 3 : 0) + min(3, substr_count($hayB, $t));
                }
                $r['score'] = $s;
                $rows[] = $r;
            }
            usort($rows, static fn ($a, $b) => $b['score'] <=> $a['score']);
        }
        // One passage per page is plenty; keep knowledge-bank passages individually.
        $out = [];
        $seen = [];
        foreach ($rows as $r) {
            $key = $r['kind'] === 'kb' ? md5((string) $r['body']) : (string) $r['path'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['title' => (string) $r['title'], 'content' => (string) $r['body'], 'path' => (string) $r['path'], 'kind' => (string) $r['kind'], 'score' => round((float) $r['score'], 3)];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /** @return list<string> */
    public static function tokens(string $q): array
    {
        $q = mb_strtolower($q);
        preg_match_all('/[\p{L}\p{N}]{2,}/u', $q, $m);
        $out = [];
        foreach ($m[0] as $t) {
            if (!in_array($t, self::STOP, true) && !in_array($t, $out, true)) {
                $out[] = $t;
            }
        }
        return array_slice($out, 0, 12);
    }

    public static function plain(string $html): string
    {
        $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<(br|/p|/h[1-6]|/li|/div|/section|/article|/header|/footer|/tr|/blockquote)[^>]*>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<[^>]+>#', ' ', $html) ?? $html; // inline tags → space so words never fuse
        $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('/[ \t\x{00A0}]+/u', ' ', $t) ?? $t;
        $t = preg_replace('/\s*\n\s*/', "\n", $t) ?? $t;
        return trim($t);
    }

    /** ~700-char passages on paragraph boundaries. @return list<string> */
    public static function chunks(string $text, int $size = 700): array
    {
        if ($text === '') {
            return [];
        }
        $out = [];
        $cur = '';
        foreach (preg_split('/\n+/', $text) ?: [] as $para) {
            if ($cur !== '' && mb_strlen($cur) + mb_strlen($para) > $size) {
                $out[] = $cur;
                $cur = '';
            }
            $cur .= ($cur === '' ? '' : "\n") . $para;
            while (mb_strlen($cur) > $size * 1.6) {
                $out[] = mb_substr($cur, 0, $size);
                $cur = mb_substr($cur, $size);
            }
        }
        if (trim($cur) !== '') {
            $out[] = $cur;
        }
        return $out;
    }
}
