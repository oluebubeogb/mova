<?php

declare(strict_types=1);

namespace Mova\Hq;

use Mova\Core\Bootstrap;
use Mova\Core\Database;

/**
 * Server-side HQ search.
 *
 * One table (hq_search) holds every searchable thing in HQ:
 *  - every registered HQ page (RouteRegistry),
 *  - the visible text of every HQ view (headings, labels, help text, buttons, table headers,
 *    placeholders), one row per heading section and one per form field, with deep links,
 *  - data: content, media, people, plugins, themes, design templates, galleries, blogs,
 *    categories, tags, content types and feedback forms.
 *
 * Engine: SQLite FTS5 with the trigram tokenizer (substring / partial-word matching), or a plain
 * table + LIKE where FTS5 trigram is unavailable. The index rebuilds itself lazily when its
 * signature (view/registry/plugin/theme mtimes + per-table count/max timestamp) changes, and
 * content writes update it incrementally.
 */
final class SearchIndex
{
    public const VERSION = 3;

    private const COLS = ['title', 'body', 'keywords', 'kind', 'ref', 'url', 'grp', 'crumb', 'icon', 'badge', 'perm', 'weight'];

    private const GROUP_ORDER = [
        'Pages' => 0, 'Settings & fields' => 1, 'In HQ pages' => 2, 'Content' => 3, 'Media' => 4,
        'People' => 5, 'Sections' => 6, 'Themes' => 7, 'Plugins' => 8, 'Collections' => 9,
        'Taxonomy' => 10, 'Forms' => 11,
    ];

    private static ?string $engine = null;
    private static bool $checked = false;

    public static function available(): bool
    {
        try {
            return (bool) Database::fetch("SELECT name FROM sqlite_master WHERE name = 'hq_search'");
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function engine(): string
    {
        if (self::$engine !== null) {
            return self::$engine;
        }
        try {
            $row = Database::fetch("SELECT sql FROM sqlite_master WHERE name = 'hq_search'");
            self::$engine = ($row && stripos((string) $row['sql'], 'fts5') !== false) ? 'fts5' : 'like';
        } catch (\Throwable $e) {
            self::$engine = 'like';
        }
        return self::$engine;
    }

    // ------------------------------------------------------------------ freshness

    public static function signature(): string
    {
        $root = self::root();
        $files = [__FILE__, $root . '/app/Hq/RouteRegistry.php'];
        foreach (glob($root . '/public/hq/views/*.php') ?: [] as $f) {
            $files[] = $f;
        }
        foreach (glob($root . '/public/hq/views/*/*.php') ?: [] as $f) {
            $files[] = $f;
        }
        foreach (glob(self::pluginsDir() . '/*/plugin.json') ?: [] as $f) {
            $files[] = $f;
        }
        foreach (glob(self::themesDir() . '/*/theme.json') ?: [] as $f) {
            $files[] = $f;
        }
        $files[] = $root . '/resources/templates/manifest.json';
        $m = 0;
        foreach ($files as $f) {
            $t = @filemtime($f);
            if ($t && $t > $m) {
                $m = $t;
            }
        }
        $parts = ['v' . self::VERSION, 'f' . count($files) . ':' . $m];
        $tables = [
            'content' => 'updated_at', 'media' => 'created_at', 'users' => 'updated_at', 'plugins' => 'activated_at',
            'design_templates' => 'updated_at', 'galleries' => 'updated_at', 'blogs' => 'updated_at',
            'categories' => 'created_at', 'tags' => 'created_at', 'content_types' => 'updated_at', 'feedback_forms' => 'updated_at',
        ];
        foreach ($tables as $t => $col) {
            try {
                $row = Database::fetch("SELECT COUNT(*) AS c, MAX({$col}) AS m, MAX(id) AS i FROM {$t}");
                $parts[] = $t . ':' . ($row['c'] ?? 0) . ':' . ($row['m'] ?? '') . ':' . ($row['i'] ?? '');
            } catch (\Throwable $e) {
                $parts[] = $t . ':-';
            }
        }
        return sha1(implode('|', $parts));
    }

    /** Rebuild when stale. Cheap when fresh (one signature per request). */
    public static function ensureFresh(bool $force = false): bool
    {
        if (!self::available()) {
            return false;
        }
        if (self::$checked && !$force) {
            return false;
        }
        self::$checked = true;
        $sig = self::signature();
        $stored = '';
        try {
            $stored = (string) (Database::fetch("SELECT setting_value FROM settings WHERE setting_key = 'hq_search_sig'")['setting_value'] ?? '');
        } catch (\Throwable $e) {
        }
        if (!$force && $stored === $sig) {
            return false;
        }
        self::rebuild($sig);
        return true;
    }

    // ------------------------------------------------------------------ build

    /** @return int rows indexed */
    public static function rebuild(?string $sig = null): int
    {
        if (!self::available()) {
            return 0;
        }
        $rows = array_merge(self::routeRows(), self::viewRows(), self::dataRows());
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->exec('DELETE FROM hq_search');
            $stmt = $pdo->prepare('INSERT INTO hq_search (' . implode(',', self::COLS) . ') VALUES (:' . implode(',:', self::COLS) . ')');
            foreach ($rows as $r) {
                $stmt->execute(self::normalizeRow($r));
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        self::saveSig($sig ?? self::signature());
        return count($rows);
    }

    /** Incremental: one content row changed (create/update/trash/restore). */
    public static function syncContent(int $id): void
    {
        if ($id <= 0 || !self::available()) {
            return;
        }
        try {
            Database::query("DELETE FROM hq_search WHERE kind = 'content' AND ref = :r", ['r' => 'content:' . $id]);
            $row = Database::fetch('SELECT id, type, title, slug, excerpt, body, status FROM content WHERE id = :id', ['id' => $id]);
            if ($row) {
                $stmt = Database::connection()->prepare('INSERT INTO hq_search (' . implode(',', self::COLS) . ') VALUES (:' . implode(',:', self::COLS) . ')');
                $stmt->execute(self::normalizeRow(self::contentRow($row)));
            }
            self::saveSig(self::signature());
        } catch (\Throwable $e) {
            // index is a cache; next search rebuilds if needed
        }
    }

    public static function removeContent(int $id): void
    {
        if ($id <= 0 || !self::available()) {
            return;
        }
        try {
            Database::query("DELETE FROM hq_search WHERE kind = 'content' AND ref = :r", ['r' => 'content:' . $id]);
            self::saveSig(self::signature());
        } catch (\Throwable $e) {
        }
    }

    private static function saveSig(string $sig): void
    {
        try {
            $exists = Database::fetch("SELECT id FROM settings WHERE setting_key = 'hq_search_sig'");
            if ($exists) {
                Database::query("UPDATE settings SET setting_value = :v WHERE setting_key = 'hq_search_sig'", ['v' => $sig]);
            } else {
                Database::query("INSERT INTO settings (setting_key, setting_value) VALUES ('hq_search_sig', :v)", ['v' => $sig]);
            }
        } catch (\Throwable $e) {
        }
    }

    private static function normalizeRow(array $r): array
    {
        $out = [];
        foreach (self::COLS as $c) {
            $v = $r[$c] ?? '';
            $out[$c] = $c === 'weight' ? (int) $v : self::clip((string) $v, $c === 'body' ? 6000 : 400);
        }
        return $out;
    }

    private static function routeRows(): array
    {
        $rows = [];
        foreach (RouteRegistry::destinations(true) as $d) {
            if (!RouteRegistry::usable(['perm' => null] + (RouteRegistry::get($d['id']) ?? []))) {
                continue; // plugin-gated page whose plugin is inactive
            }
            $crumb = implode(' › ', array_column(RouteRegistry::breadcrumb(RouteRegistry::get($d['id'])), 'label'));
            $subLabels = array_map(static fn($s) => (string) ($s['label'] ?? ''), $d['sub'] ?? []);
            $rows[] = [
                'title' => $d['label'], 'body' => trim($d['desc'] . ' ' . implode(' · ', $subLabels)),
                'keywords' => implode(' ', $d['keywords']) . ' ' . $d['section_label'], 'kind' => 'page', 'ref' => 'route:' . $d['id'],
                'url' => $d['path'], 'grp' => 'Pages', 'crumb' => $crumb, 'icon' => $d['icon'], 'badge' => $d['section_label'],
                'perm' => (string) $d['perm'], 'weight' => 30,
            ];
            foreach ($d['sub'] ?? [] as $s) {
                if (empty($s['href']) || ($s['href'] ?? '') === $d['path']) {
                    continue;
                }
                $rows[] = [
                    'title' => $s['label'], 'body' => $d['label'] . ' — ' . $d['desc'], 'keywords' => $d['label'] . ' ' . $d['section_label'],
                    'kind' => 'page', 'ref' => 'route:' . $d['id'] . ':' . ($s['layer'] ?? $s['label']), 'url' => $s['href'], 'grp' => 'Pages',
                    'crumb' => $crumb . ' › ' . $s['label'], 'icon' => $s['icon'] ?? $d['icon'], 'badge' => $d['label'],
                    'perm' => (string) $d['perm'], 'weight' => 18,
                ];
            }
        }
        return $rows;
    }

    /** Index the visible text of each HQ view, grouped under its headings, plus each form field. */
    private static function viewRows(): array
    {
        $root = self::root() . '/public/hq/views/';
        $rows = [];
        $seenViews = [];
        foreach (RouteRegistry::all() as $route) {
            $view = $route['view'] ?? null;
            if (!empty($route['plugin']) && !RouteRegistry::usable(['perm' => null] + $route)) {
                continue;
            }
            if (!$view || isset($seenViews[$view])) {
                continue;
            }
            $seenViews[$view] = true;
            $file = $root . $view . '.php';
            if (!is_file($file)) {
                continue;
            }
            $sections = self::extractViewText((string) file_get_contents($file));
            $layerLabels = [];
            foreach ($route['sub'] ?? [] as $s) {
                if (!empty($s['layer'])) {
                    $layerLabels[$s['layer']] = $s['label'];
                }
            }
            $baseCrumb = implode(' › ', array_column(RouteRegistry::breadcrumb($route), 'label'));
            $basePath = (string) parse_url($route['path'], PHP_URL_PATH);
            $fieldCount = 0;
            $seenFields = [];
            foreach ($sections as $sec) {
                $layer = $sec['layer'];
                $url = $basePath . ($layer !== '' ? '?layer=' . rawurlencode($layer) : '');
                $crumb = $baseCrumb . ($layer !== '' && isset($layerLabels[$layer]) ? ' › ' . $layerLabels[$layer] : '');
                $title = $sec['title'] !== '' ? $sec['title'] : $route['label'];
                $body = implode(' · ', array_unique($sec['texts']));
                if ($body !== '' || $sec['title'] !== '') {
                    $rows[] = [
                        'title' => $title, 'body' => $body, 'keywords' => $route['label'], 'kind' => 'section',
                        'ref' => 'view:' . $view . ':' . $title, 'url' => $url . ($sec['title'] !== '' ? '#hqf=' . rawurlencode($sec['title']) : ''),
                        'grp' => 'In HQ pages', 'crumb' => $crumb, 'icon' => $route['icon'], 'badge' => $route['label'],
                        'perm' => (string) $route['perm'], 'weight' => 8,
                    ];
                }
                foreach ($sec['fields'] as $f) {
                    $key = mb_strtolower($f['label']);
                    if (isset($seenFields[$key]) || $fieldCount >= 120) {
                        continue;
                    }
                    $seenFields[$key] = true;
                    $fieldCount++;
                    $rows[] = [
                        'title' => $f['label'], 'body' => trim($f['hint'] . ' ' . ($sec['title'] !== '' ? '(' . $sec['title'] . ')' : '')),
                        'keywords' => $route['label'] . ' ' . $f['name'], 'kind' => 'field', 'ref' => 'field:' . $view . ':' . $f['label'],
                        'url' => $url . '#hqf=' . rawurlencode($f['label']), 'grp' => 'Settings & fields',
                        'crumb' => $crumb . ($sec['title'] !== '' ? ' › ' . $sec['title'] : ''), 'icon' => 'fa-solid fa-sliders',
                        'badge' => $route['label'], 'perm' => (string) $route['perm'], 'weight' => 12,
                    ];
                }
            }
        }
        return $rows;
    }

    /**
     * @return list<array{title:string,layer:string,texts:list<string>,fields:list<array{label:string,hint:string,name:string}>}>
     */
    public static function extractViewText(string $source): array
    {
        // Drop PHP blocks (incl. an unterminated trailing block) and inline scripts/styles
        $html = (string) preg_replace('/<\?(?:php|=)?.*?(?:\?>|\z)/s', ' ', $source);
        $html = (string) preg_replace('#<(script|style|template|svg)\b[^>]*>.*?</\1>#is', ' ', $html);
        if (trim($html) === '') {
            return [];
        }
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div id="__root">' . $html . '</div>', LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xp = new \DOMXPath($doc);
        $nodes = $xp->query('//h1|//h2|//h3|//h4|//label|//legend|//p|//button|//th|//summary|//small|//li[not(*)]|//a[contains(@class,"btn")]|//*[@placeholder]|//*[contains(@class,"hint")]|//*[contains(@class,"help")]|//*[contains(@class,"desc")]');
        $sections = [];
        $cur = ['title' => '', 'layer' => '', 'texts' => [], 'fields' => []];
        $push = static function () use (&$sections, &$cur): void {
            if ($cur['title'] !== '' || $cur['texts'] || $cur['fields']) {
                $sections[] = $cur;
            }
        };
        foreach ($nodes ?: [] as $node) {
            /** @var \DOMElement $node */
            $tag = strtolower($node->nodeName);
            $layer = '';
            $panel = $xp->query('ancestor-or-self::*[@data-layer-panel][1]', $node);
            if ($panel && $panel->length) {
                $layer = (string) $panel->item(0)->getAttribute('data-layer-panel');
            }
            $text = self::clean($node->textContent);
            if (in_array($tag, ['h1', 'h2', 'h3', 'h4'], true)) {
                if ($text === '' || mb_strlen($text) > 120) {
                    continue;
                }
                $push();
                $cur = ['title' => $text, 'layer' => $layer, 'texts' => [], 'fields' => []];
                continue;
            }
            if ($cur['layer'] === '' && $layer !== '') {
                $cur['layer'] = $layer;
            }
            if ($node->hasAttribute('placeholder')) {
                $ph = self::clean($node->getAttribute('placeholder'));
                if ($ph !== '' && !preg_match('/^[\d\W]+$/u', $ph)) {
                    $cur['texts'][] = $ph;
                }
                if ($text === '') {
                    continue;
                }
            }
            if ($text === '' || mb_strlen($text) < 2 || preg_match('/^[\d\W_]+$/u', $text)) {
                continue;
            }
            if ($tag === 'label' && mb_strlen($text) <= 80) {
                $name = '';
                $input = $xp->query('.//input|.//select|.//textarea', $node);
                if ($input && $input->length) {
                    $name = (string) $input->item(0)->getAttribute('name');
                } elseif ($node->getAttribute('for') !== '') {
                    $name = (string) $node->getAttribute('for');
                }
                $hint = '';
                $sib = $node->nextSibling;
                for ($i = 0; $sib && $i < 6; $i++, $sib = $sib->nextSibling) {
                    if ($sib instanceof \DOMElement && preg_match('/hint|help|desc|muted/i', (string) $sib->getAttribute('class')) || ($sib instanceof \DOMElement && strtolower($sib->nodeName) === 'small')) {
                        $hint = self::clean($sib->textContent);
                        break;
                    }
                }
                $cur['fields'][] = ['label' => $text, 'hint' => mb_substr($hint, 0, 240), 'name' => str_replace(['_', '-', '[', ']'], ' ', $name)];
            }
            $cur['texts'][] = mb_substr($text, 0, 400);
        }
        $push();
        return $sections;
    }

    private static function dataRows(): array
    {
        $rows = [];
        // Content
        try {
            foreach (Database::fetchAll('SELECT id, type, title, slug, excerpt, body, status FROM content ORDER BY id DESC LIMIT 5000') as $c) {
                $rows[] = self::contentRow($c);
            }
        } catch (\Throwable $e) {
        }
        // Media
        try {
            foreach (Database::fetchAll('SELECT id, original_name, filename, alt_text, mime_type, path FROM media ORDER BY id DESC LIMIT 5000') as $m) {
                $name = (string) ($m['original_name'] ?: $m['filename']);
                $mime = (string) $m['mime_type'];
                $icon = str_starts_with($mime, 'image/') ? 'fa-regular fa-image' : (str_starts_with($mime, 'video/') ? 'fa-solid fa-film' : (str_starts_with($mime, 'audio/') ? 'fa-solid fa-music' : 'fa-regular fa-file'));
                $rows[] = [
                    'title' => $name, 'body' => trim((string) $m['alt_text'] . ' ' . (string) $m['path']), 'keywords' => $mime . ' media',
                    'kind' => 'media', 'ref' => 'media:' . $m['id'], 'url' => '/hq/media?focus=' . (int) $m['id'], 'grp' => 'Media',
                    'crumb' => 'Content › Media', 'icon' => $icon, 'badge' => strtoupper((string) pathinfo($name, PATHINFO_EXTENSION)),
                    'perm' => 'manage_media', 'weight' => 6,
                ];
            }
        } catch (\Throwable $e) {
        }
        // People
        try {
            foreach (Database::fetchAll('SELECT id, name, username, email, role, status FROM users ORDER BY id LIMIT 2000') as $u) {
                $rows[] = [
                    'title' => (string) ($u['name'] ?: $u['username']), 'body' => trim($u['email'] . ' @' . $u['username']),
                    'keywords' => 'user person ' . $u['role'], 'kind' => 'person', 'ref' => 'user:' . $u['id'],
                    'url' => '/hq/people/edit/' . (int) $u['id'], 'grp' => 'People', 'crumb' => 'Audience › People',
                    'icon' => 'fa-regular fa-user', 'badge' => ucfirst((string) $u['role']), 'perm' => 'admin', 'weight' => 7,
                ];
            }
        } catch (\Throwable $e) {
        }
        // Plugins (filesystem + install state)
        $state = [];
        try {
            foreach (Database::fetchAll('SELECT slug, status FROM plugins') as $p) {
                $state[$p['slug']] = $p['status'];
            }
        } catch (\Throwable $e) {
        }
        foreach (glob(self::pluginsDir() . '/*/plugin.json') ?: [] as $f) {
            $slug = basename(dirname($f));
            $meta = json_decode((string) @file_get_contents($f), true) ?: [];
            $rows[] = [
                'title' => (string) ($meta['name'] ?? $slug), 'body' => (string) ($meta['description'] ?? ''),
                'keywords' => 'plugin extension ' . $slug, 'kind' => 'plugin', 'ref' => 'plugin:' . $slug,
                'url' => '/hq/plugins#hqf=' . rawurlencode((string) ($meta['name'] ?? $slug)), 'grp' => 'Plugins',
                'crumb' => 'Extend › Plugins', 'icon' => 'fa-solid fa-plug', 'badge' => ucfirst((string) ($state[$slug] ?? 'available')),
                'perm' => 'admin', 'weight' => 7,
            ];
        }
        // Themes
        foreach (glob(self::themesDir() . '/*/theme.json') ?: [] as $f) {
            $slug = basename(dirname($f));
            $meta = json_decode((string) @file_get_contents($f), true) ?: [];
            $rows[] = [
                'title' => (string) ($meta['name'] ?? $slug), 'body' => (string) ($meta['description'] ?? ''),
                'keywords' => 'theme look ' . $slug, 'kind' => 'theme', 'ref' => 'theme:' . $slug,
                'url' => '/hq/brand?layer=theme#hqf=' . rawurlencode((string) ($meta['name'] ?? $slug)), 'grp' => 'Themes',
                'crumb' => 'Design › Brand › Theme package', 'icon' => 'fa-solid fa-swatchbook', 'badge' => 'v' . ($meta['version'] ?? '1'),
                'perm' => 'manage_appearance', 'weight' => 7,
            ];
        }
        // Section library (stored as design templates)
        try {
            foreach (\Mova\Theme\TemplateService::listAll() as $t) {
                $rows[] = [
                    'title' => $t['name'], 'body' => $t['description'], 'keywords' => 'template ' . $t['category'] . ' ' . $t['id'],
                    'kind' => 'template', 'ref' => 'template:' . $t['id'], 'url' => '/hq/templates/' . rawurlencode($t['id']),
                    'grp' => 'Sections', 'crumb' => 'Design › Sections', 'icon' => 'fa-solid fa-table-cells-large',
                    'badge' => ucfirst($t['category']), 'perm' => 'manage_appearance', 'weight' => 6,
                ];
            }
        } catch (\Throwable $e) {
        }
        // Collections, taxonomy, types, forms
        $simple = [
            ['SELECT id, title AS t, description AS d, status AS s FROM galleries', 'gallery', '/hq/galleries/edit/', 'Collections', 'Content › Galleries', 'fa-solid fa-images', 'Gallery', 'manage_media'],
            ['SELECT id, title AS t, description AS d, status AS s FROM blogs', 'blog', '/hq/blogs/edit/', 'Collections', 'Content › Blogs', 'fa-solid fa-rss', 'Blog', 'manage_content'],
            ['SELECT id, name AS t, description AS d, slug AS s FROM categories', 'category', '/hq/categories#hqf=', 'Taxonomy', 'Content › Categories', 'fa-regular fa-folder', 'Category', 'manage_content'],
            ['SELECT id, name AS t, slug AS d, slug AS s FROM tags', 'tag', '/hq/tags#hqf=', 'Taxonomy', 'Content › Tags', 'fa-solid fa-tag', 'Tag', 'manage_content'],
            ['SELECT id, name AS t, description AS d, slug AS s FROM content_types', 'type', '/hq/types/edit/', 'Taxonomy', 'Content › Types & fields', 'fa-solid fa-shapes', 'Type', 'admin'],
            ['SELECT id, title AS t, description AS d, status AS s FROM feedback_forms', 'form', '/hq/feedback/forms/edit/', 'Forms', 'Audience › Feedback', 'fa-regular fa-rectangle-list', 'Form', 'manage_content'],
        ];
        foreach ($simple as [$sql, $kind, $base, $grp, $crumb, $icon, $badge, $perm]) {
            try {
                foreach (Database::fetchAll($sql . ' ORDER BY id DESC LIMIT 2000') as $x) {
                    $url = str_ends_with($base, '#hqf=') ? $base . rawurlencode((string) $x['t']) : $base . (int) $x['id'];
                    $rows[] = [
                        'title' => (string) $x['t'], 'body' => (string) ($x['d'] ?? ''), 'keywords' => $kind . ' ' . ($x['s'] ?? ''),
                        'kind' => $kind, 'ref' => $kind . ':' . $x['id'], 'url' => $url, 'grp' => $grp, 'crumb' => $crumb,
                        'icon' => $icon, 'badge' => $badge, 'perm' => $perm, 'weight' => 6,
                    ];
                }
            } catch (\Throwable $e) {
            }
        }
        return $rows;
    }

    private static function contentRow(array $c): array
    {
        $body = self::clean(html_entity_decode(strip_tags((string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', (string) $c['body'])), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $type = (string) ($c['type'] ?? 'page');
        return [
            'title' => (string) ($c['title'] ?: '(untitled)'), 'body' => trim((string) $c['excerpt'] . ' ' . $body),
            'keywords' => $type . ' /' . $c['slug'], 'kind' => 'content', 'ref' => 'content:' . $c['id'],
            'url' => '/hq/content/edit/' . (int) $c['id'], 'grp' => 'Content', 'crumb' => 'Content › ' . ucfirst($type),
            'icon' => $type === 'post' ? 'fa-regular fa-newspaper' : 'fa-regular fa-file-lines', 'badge' => ucfirst((string) $c['status']),
            'perm' => 'manage_content', 'weight' => ($c['status'] ?? '') === 'trash' ? 0 : 9,
        ];
    }

    // ------------------------------------------------------------------ query

    /**
     * @return array{q:string,engine:string,took_ms:float,total:int,groups:list<array{name:string,items:list<array>}>}
     */
    public static function search(string $q, int $limit = 24): array
    {
        $t0 = microtime(true);
        $q = trim((string) preg_replace('/\s+/u', ' ', $q));
        $q = mb_substr($q, 0, 100);
        $out = ['q' => $q, 'engine' => self::engine(), 'took_ms' => 0.0, 'total' => 0, 'groups' => []];
        if ($q === '' || !self::available()) {
            return $out;
        }
        try {
            self::ensureFresh();
        } catch (\Throwable $e) {
            // stale index is better than none
        }

        $tokens = array_values(array_unique(array_filter(
            array_map(static fn($t) => mb_strtolower(trim($t, " \t\"'`.,;:!?()[]{}")), explode(' ', $q)),
            static fn($t) => $t !== ''
        )));
        $tokens = array_slice($tokens, 0, 8);
        if (!$tokens) {
            return $out;
        }

        $cols = 'rowid AS rid, title, body, keywords, kind, ref, url, grp, crumb, icon, badge, perm, weight';
        $params = [];
        $likeParts = [];
        $long = [];
        foreach ($tokens as $i => $tok) {
            if (self::engine() === 'fts5' && mb_strlen($tok) >= 3) {
                $long[] = '"' . str_replace('"', '""', $tok) . '"';
            } else {
                $params['l' . $i] = '%' . addcslashes($tok, '%_\\') . '%';
                $likeParts[] = "(title LIKE :l{$i} ESCAPE '\\' OR keywords LIKE :l{$i} ESCAPE '\\' OR body LIKE :l{$i} ESCAPE '\\')";
            }
        }
        $where = [];
        $order = 'weight DESC';
        if ($long) {
            $where[] = 'hq_search MATCH :m';
            $params['m'] = implode(' AND ', $long);
            $cols .= ', bm25(hq_search, 10.0, 1.0, 4.0) AS rnk';
            $order = 'rnk';
        }
        $where = array_merge($where, $likeParts);
        $sql = "SELECT {$cols} FROM hq_search WHERE " . implode(' AND ', $where) . " ORDER BY {$order} LIMIT 400";
        try {
            $rows = Database::fetchAll($sql, $params);
        } catch (\Throwable $e) {
            $rows = [];
        }

        $qLower = mb_strtolower($q);
        $allowed = [];
        $scored = [];
        foreach ($rows as $r) {
            $perm = (string) $r['perm'];
            if (!array_key_exists($perm, $allowed)) {
                $allowed[$perm] = RouteRegistry::allowed($perm === '' ? null : $perm);
            }
            if (!$allowed[$perm]) {
                continue;
            }
            $title = mb_strtolower((string) $r['title']);
            $kw = mb_strtolower((string) $r['keywords']);
            $score = (int) $r['weight'];
            if (isset($r['rnk'])) {
                $score += (int) round(min(30, max(0, -(float) $r['rnk'])));
            }
            if ($title === $qLower) {
                $score += 60;
            } elseif (str_starts_with($title, $qLower)) {
                $score += 40;
            } elseif (str_contains($title, $qLower)) {
                $score += 25;
            }
            foreach ($tokens as $tok) {
                if (str_contains($title, $tok)) {
                    $score += 10;
                    if (preg_match('/(^|\W)' . preg_quote($tok, '/') . '/u', $title)) {
                        $score += 6; // word-prefix match
                    }
                }
                if (str_contains($kw, $tok)) {
                    $score += 5;
                }
            }
            $r['_score'] = $score;
            $scored[] = $r;
        }
        usort($scored, static fn($a, $b) => $b['_score'] <=> $a['_score']);

        // De-duplicate identical url+title, group, cap per group
        $groups = [];
        $seen = [];
        $count = 0;
        foreach ($scored as $r) {
            $key = $r['url'] . '|' . mb_strtolower((string) $r['title']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $g = (string) $r['grp'];
            if (!isset($groups[$g])) {
                $groups[$g] = ['name' => $g, 'best' => $r['_score'], 'items' => []];
            }
            if (count($groups[$g]['items']) >= ($g === 'Pages' ? 6 : 5)) {
                continue;
            }
            $groups[$g]['items'][] = [
                'title' => (string) $r['title'],
                'title_html' => self::highlight((string) $r['title'], $tokens),
                'snippet_html' => self::snippet((string) $r['body'], $tokens),
                'url' => self::safeUrl((string) $r['url']),
                'icon' => preg_match('/^[a-z0-9 \-]+$/i', (string) $r['icon']) ? (string) $r['icon'] : 'fa-regular fa-file',
                'crumb' => (string) $r['crumb'],
                'badge' => (string) $r['badge'],
                'kind' => (string) $r['kind'],
                'score' => $r['_score'],
            ];
            if (++$count >= $limit) {
                break;
            }
        }
        $groups = array_values($groups);
        usort($groups, static function ($a, $b) {
            if ($a['best'] !== $b['best'] && abs($a['best'] - $b['best']) > 15) {
                return $b['best'] <=> $a['best'];
            }
            return (self::GROUP_ORDER[$a['name']] ?? 50) <=> (self::GROUP_ORDER[$b['name']] ?? 50);
        });
        foreach ($groups as &$g) {
            unset($g['best']);
        }
        unset($g);
        $out['groups'] = $groups;
        $out['total'] = $count;
        $out['took_ms'] = round((microtime(true) - $t0) * 1000, 1);
        return $out;
    }

    /** Escape text and wrap token matches in <mark>. */
    public static function highlight(string $text, array $tokens): string
    {
        $tokens = array_values(array_filter($tokens, static fn($t) => $t !== ''));
        if (!$tokens) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
        usort($tokens, static fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $re = '/(' . implode('|', array_map(static fn($t) => preg_quote($t, '/'), $tokens)) . ')/iu';
        $parts = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
        $html = '';
        foreach ($parts as $i => $p) {
            $e = htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
            $html .= ($i % 2 === 1) ? '<mark>' . $e . '</mark>' : $e;
        }
        return $html;
    }

    public static function snippet(string $body, array $tokens, int $len = 150): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }
        $lower = mb_strtolower($body);
        $pos = -1;
        foreach ($tokens as $t) {
            $p = mb_strpos($lower, $t);
            if ($p !== false) {
                $pos = $p;
                break;
            }
        }
        $start = $pos < 0 ? 0 : max(0, $pos - (int) ($len / 3));
        $chunk = mb_substr($body, $start, $len);
        $html = self::highlight($chunk, $tokens);
        return ($start > 0 ? '…' : '') . $html . ($start + $len < mb_strlen($body) ? '…' : '');
    }

    private static function safeUrl(string $url): string
    {
        return str_starts_with($url, '/hq') ? $url : '/hq';
    }

    private static function clean(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    private static function clip(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) : $s;
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function pluginsDir(): string
    {
        try {
            $p = Bootstrap::path('plugins');
            if ($p !== '' && is_dir($p)) {
                return rtrim($p, '/');
            }
        } catch (\Throwable $e) {
        }
        return self::root() . '/mova-plugins';
    }

    private static function themesDir(): string
    {
        try {
            $p = Bootstrap::path('themes');
            if ($p !== '' && is_dir($p)) {
                return rtrim($p, '/');
            }
        } catch (\Throwable $e) {
        }
        return self::root() . '/mova-themes';
    }
}
