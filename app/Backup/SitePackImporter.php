<?php
/**
 * Plans and applies a .movapack onto this site.
 *
 * Matching: by ID and by natural key (slug+type for content, slug/path/template_id elsewhere).
 *   ID + key match           → update (when any field / child row differs) or unchanged
 *   ID matches, key differs  → conflict (skipped; may be overridden per item when the pack key is free)
 *   ID free, key used        → conflict (never overridable: would duplicate a slug)
 *   neither                  → add (when "Add new items" is on; the pack ID is kept)
 * Users, keys, secrets, logs and everything outside the selected groups are never touched.
 */

declare(strict_types=1);

namespace Mova\Backup;

use Mova\Core\Bootstrap;
use Mova\Core\Database;

final class SitePackImporter
{
    /** Apply order (parents before children). */
    public const ORDER = ['content_types', 'content_fields', 'media', 'content', 'content_meta', 'content_relations',
        'categories', 'tags', 'content_tags', 'content_categories', 'blogs', 'blog_items', 'galleries', 'gallery_items',
        'design_templates', 'assemblies', 'feedback_forms', 'site_ai_sources', 'site_ai_chunks',
        'subscribers', 'feedback_entries', 'comments'];

    private SitePackReader $r;
    private array $cache = [];
    private int $writes = 0;
    private int $failAfter = 0;
    /** @var list<string> source hosts whose absolute URLs are rewritten to site-relative while reading pack data */
    private array $rewriteHosts = [];

    public function __construct(SitePackReader $reader)
    {
        $this->r = $reader;
    }

    // ── data access ──────────────────────────────────────────────────────

    /** @return list<array> */
    private function packRows(string $table): array
    {
        if (!array_key_exists($table, $this->cache)) {
            $d = $this->data('data/' . $table . '.json');
            $this->cache[$table] = $d === null ? null : $d['rows'];
        }
        return $this->cache[$table] ?? [];
    }

    /** Pack data file, with the optional absolute→relative URL rewrite applied to every string value. */
    private function data(string $name): ?array
    {
        $d = $this->r->data($name);
        if ($d === null || $this->rewriteHosts === []) {
            return $d;
        }
        return self::rewriteDeep($d, $this->rewriteHosts);
    }

    /** Turn rewriting on/off (clears the row cache). */
    private function setRewrite(bool $on): void
    {
        $hosts = $on ? self::sourceHosts($this->r->manifest()) : [];
        if ($hosts !== $this->rewriteHosts) {
            $this->rewriteHosts = $hosts;
            $this->cache = [];
        }
    }

    /** @return list<string> host names the pack was exported from (lower-case, without www.). */
    public static function sourceHosts(array $m): array
    {
        $h = strtolower(trim((string) ($m['site']['host'] ?? '')));
        $h = preg_replace('/:\d+$/', '', $h) ?? $h;
        $h = preg_replace('/^www\./', '', $h) ?? $h;
        if ($h === '' || !preg_match('/^[a-z0-9.-]+$/', $h) || !str_contains($h, '.') || $h === 'localhost') {
            return [];
        }
        return [$h];
    }

    private static function urlPattern(string $host): string
    {
        // http(s)://host, //host and their JSON-escaped forms (https:\/\/host); optional www. and :port
        return '~(?:https?:)?\\\\?/\\\\?/(?:www\.)?' . preg_quote($host, '~') . '(?::\d+)?(?=\\\\?/|[\'"\s)<>,;?#]|$)~i';
    }

    /** Absolute URLs on $hosts → site-relative ("https://example.com/a" → "/a", "https://example.com" → "/"). */
    public static function rewriteUrls(string $v, array $hosts): string
    {
        return self::hasHost($v, $hosts) ? self::rewriteString($v, $hosts) : $v;
    }

    private static function rewriteDeep($v, array $hosts)
    {
        if (is_string($v)) {
            return self::hasHost($v, $hosts) ? self::rewriteString($v, $hosts) : $v;
        }
        if (is_array($v)) {
            foreach ($v as $k => $x) {
                $v[$k] = self::rewriteDeep($x, $hosts);
            }
        }
        return $v;
    }

    /** Each match becomes "" when a path follows it, or "/" when the URL was the bare origin. */
    private static function rewriteString(string $v, array $hosts): string
    {
        foreach ($hosts as $h) {
            if (stripos($v, $h) === false) {
                continue;
            }
            $out = '';
            $pos = 0;
            if (preg_match_all(self::urlPattern($h), $v, $mm, PREG_OFFSET_CAPTURE)) {
                foreach ($mm[0] as [$txt, $off]) {
                    $end = $off + strlen($txt);
                    $next = substr($v, $end, 2);
                    $pathFollows = ($next !== '' && $next[0] === '/') || $next === '\\/';
                    $out .= substr($v, $pos, $off - $pos) . ($pathFollows ? '' : '/');
                    $pos = $end;
                }
                $v = $out . substr($v, $pos);
            }
        }
        return $v;
    }

    private static function hasHost(string $v, array $hosts): bool
    {
        foreach ($hosts as $h) {
            if (stripos($v, $h) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Absolute links back to the pack's source host, found in the pack data.
     * @return array{hosts:list<string>,count:int,tables:array<string,int>,samples:list<string>,same_host:bool}
     */
    public function urlReport(): array
    {
        $m = $this->r->manifest();
        $hosts = self::sourceHosts($m);
        $rep = ['hosts' => $hosts, 'count' => 0, 'tables' => [], 'samples' => [], 'same_host' => false];
        if ($hosts === []) {
            return $rep;
        }
        $here = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
        $rep['same_host'] = $here !== '' && in_array(preg_replace('/^www\./', '', $here), $hosts, true);
        foreach (array_keys((array) ($m['files'] ?? [])) as $name) {
            if (!preg_match('~^data/([a-z0-9_.]+)\.json$~', (string) $name, $mm)) {
                continue;
            }
            $d = $this->r->data((string) $name);
            foreach ((array) ($d['rows'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach ($row as $col => $val) {
                    if (!is_string($val) || !self::hasHost($val, $hosts)) {
                        continue;
                    }
                    $n = 0;
                    foreach ($hosts as $h) {
                        $n += (int) preg_match_all(self::urlPattern($h), $val);
                    }
                    if ($n === 0) {
                        continue;
                    }
                    $rep['count'] += $n;
                    $rep['tables'][$mm[1]] = ($rep['tables'][$mm[1]] ?? 0) + $n;
                    if (count($rep['samples']) < 8) {
                        $ref = isset($row['id']) ? '#' . $row['id'] : (string) ($row['setting_key'] ?? $row['meta_key'] ?? '');
                        if (isset($row['meta_key'])) {
                            $ref .= ' ' . $row['meta_key'];
                        }
                        $rep['samples'][] = $mm[1] . ' ' . trim($ref) . ' · ' . $col;
                    }
                }
            }
        }
        return $rep;
    }

    /** Plugins a pack's content needs, keyed by content signal (meta key = value → plugin slug). */
    private const PLUGIN_SIGNALS = [
        ['meta' => 'editor_mode', 'value' => 'dev', 'plugin' => 'mova-dev-editor', 'reason' => 'Dev Mode pages'],
    ];

    /**
     * Plugins the pack expects: everything active on the source site, plus plugins its content
     * cannot render without. Each entry says what this site has and what the importer would do.
     * @return list<array{slug:string,name:string,version:string,required:bool,reason:string,installed:bool,local_version:string,local_status:string,action:string}>
     */
    public function pluginReport(): array
    {
        $m = $this->r->manifest();
        $want = [];
        foreach ((array) ($m['features']['plugins'] ?? []) as $p) {
            $slug = (string) ($p['slug'] ?? '');
            if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $slug) || ($p['status'] ?? 'active') !== 'active') {
                continue;
            }
            $want[$slug] = ['version' => (string) ($p['version'] ?? ''), 'required' => false, 'reason' => 'Active on the source site'];
        }
        $metaRows = null;
        foreach (self::PLUGIN_SIGNALS as $sig) {
            if ($metaRows === null) {
                $metaRows = $this->r->has('data/content_meta.json') ? (array) ($this->r->data('data/content_meta.json')['rows'] ?? []) : [];
            }
            $n = 0;
            foreach ($metaRows as $row) {
                if (($row['meta_key'] ?? '') === $sig['meta'] && (string) ($row['meta_value'] ?? '') === $sig['value']) {
                    $n++;
                }
            }
            if ($n > 0) {
                $want[$sig['plugin']] = ['version' => $want[$sig['plugin']]['version'] ?? '', 'required' => true,
                    'reason' => $sig['reason'] . ' (' . $n . ')'];
            }
        }
        if ($want === []) {
            return [];
        }
        $mgr = new \Mova\Plugin\PluginManager();
        $local = [];
        try {
            foreach ($mgr->discover() as $p) {
                $local[(string) $p['slug']] = $p;
            }
        } catch (\Throwable $e) {
        }
        $out = [];
        foreach ($want as $slug => $w) {
            $l = $local[$slug] ?? null;
            $status = $l ? ((string) ($l['status'] ?? 'inactive') === 'active' ? 'active' : 'inactive') : 'missing';
            $out[] = [
                'slug' => $slug,
                'name' => (string) ($l['name'] ?? $slug),
                'version' => $w['version'],
                'required' => $w['required'],
                'reason' => $w['reason'],
                'installed' => $l !== null,
                'local_version' => (string) ($l['version'] ?? ''),
                'local_status' => $status,
                'action' => $status === 'active' ? 'ok' : ($status === 'inactive' ? 'activate' : 'missing'),
            ];
        }
        usort($out, static fn ($a, $b) => [$b['required'], $a['slug']] <=> [$a['required'], $b['slug']]);
        return $out;
    }

    /**
     * Activate the plugins a plan marked "activate" (installed but inactive).
     * @return list<string> slugs that were switched on
     */
    public static function activatePlugins(array $plan): array
    {
        $mgr = new \Mova\Plugin\PluginManager();
        $done = [];
        foreach ((array) ($plan['plugins'] ?? []) as $p) {
            $slug = (string) ($p['slug'] ?? '');
            if (($p['action'] ?? '') !== 'activate' || !preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $slug)) {
                continue;
            }
            $row = Database::fetch('SELECT status FROM plugins WHERE slug = :s', ['s' => $slug]);
            if ($row && ($row['status'] ?? '') === 'active') {
                continue;
            }
            if ($mgr->activate($slug)) {
                $done[] = $slug;
            }
        }
        return $done;
    }

    private function packHas(string $table): bool
    {
        return $this->r->has('data/' . $table . '.json');
    }

    private static function norm($v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_float($v) && floor($v) === $v && abs($v) < 1e15) {
            return (string) (int) $v;
        }
        if (is_array($v)) {
            return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return (string) $v;
    }

    private static function keyOf(array $row, array $key): string
    {
        return implode("\x1f", array_map(static fn ($k) => self::norm($row[$k] ?? null), $key));
    }

    private static function keyLabel(array $row, array $key): string
    {
        $parts = [];
        foreach ($key as $k) {
            if ($k !== 'type') {
                $parts[] = (string) ($row[$k] ?? '');
            }
        }
        return implode(' · ', $parts);
    }

    private static function inIds(array $ids): string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        return $ids === [] ? '0' : implode(',', $ids);
    }

    // ── plan (dry run) ───────────────────────────────────────────────────

    /**
     * @param array{groups?:list<string>,add_new?:bool,remove_missing?:bool,overrides?:list<string>} $opts
     */
    public function plan(array $opts = []): array
    {
        $m = $this->r->manifest();
        $packGroups = array_values(array_intersect((array) ($m['groups'] ?? []), array_keys(SitePack::groups())));
        $groups = isset($opts['groups']) ? array_values(array_intersect($packGroups, (array) $opts['groups'])) : $packGroups;
        $addNew = (bool) ($opts['add_new'] ?? true);
        $remove = (bool) ($opts['remove_missing'] ?? false);
        $overrides = array_flip(array_map('strval', (array) ($opts['overrides'] ?? [])));
        $rewrite = (bool) ($opts['rewrite_urls'] ?? false);
        $activate = (bool) ($opts['activate_plugins'] ?? false);
        $urlReport = $this->urlReport();
        $this->setRewrite($rewrite && $urlReport['count'] > 0);
        $defs = SitePack::groups();
        $rules = SitePack::tables();

        $plan = [
            'pack' => $this->r->name(),
            'options' => ['groups' => $groups, 'add_new' => $addNew, 'remove_missing' => $remove, 'overrides' => array_keys($overrides),
                'rewrite_urls' => $rewrite && $urlReport['count'] > 0, 'activate_plugins' => $activate],
            'plugins' => $this->pluginReport(),
            'urls' => $urlReport,
            'groups' => [],
            'actions' => [],
            'children' => [],
            'settings' => [],
            'kv' => [],
            'files' => [],
            'remove_files' => [],
            'blocked' => [],
            'totals' => ['add' => 0, 'update' => 0, 'unchanged' => 0, 'conflict' => 0, 'remove' => 0, 'skip' => 0, 'blocked' => 0],
        ];
        $packContentIds = [];

        foreach ($groups as $gid) {
            $g = $defs[$gid];
            $out = ['label' => $g['label'], 'icon' => $g['icon'], 'items' => [], 'counts' => ['add' => 0, 'update' => 0, 'unchanged' => 0, 'conflict' => 0, 'remove' => 0, 'skip' => 0, 'blocked' => 0]];
            foreach ($g['tables'] as $table) {
                if (!$this->packHas($table) || !SitePack::tableExists($table)) {
                    continue;
                }
                $this->planTable($table, $rules[$table], (array) $g['children'], $addNew, $remove, $overrides, $plan, $out, $m);
                if ($table === 'content') {
                    foreach ($this->packRows('content') as $row) {
                        $packContentIds[(int) $row['id']] = true;
                    }
                }
            }
            if (!empty($g['settings'])) {
                $this->planSettings($gid, $addNew, $plan, $out);
            }
            if (!empty($g['kv'])) {
                $this->planKv((string) $g['kv'], $addNew, $plan, $out);
            }
            if ($gid === 'design') {
                $this->planLooseFiles($plan, $out);
            }
            $plan['groups'][$gid] = $out;
        }
        if (isset($plan['groups']['taxonomy'])) {
            $this->planAssignments($packContentIds, $plan);
        }
        foreach ($plan['groups'] as $g) {
            foreach ($g['counts'] as $k => $v) {
                $plan['totals'][$k] += $v;
            }
        }
        $plan['file_bytes'] = 0;
        foreach ($plan['files'] as $rel => $_) {
            $plan['file_bytes'] += (int) ($m['files']['media/' . $rel]['size'] ?? 0);
        }
        return $plan;
    }

    private function planTable(string $table, array $rule, array $children, bool $addNew, bool $remove, array $overrides, array &$plan, array &$out, array $m): void
    {
        $key = (array) $rule['key'];
        $cols = array_flip(SitePack::tableColumns($table));
        $target = [];
        $byKey = [];
        foreach (Database::fetchAll('SELECT * FROM ' . $table) as $t) {
            $target[(int) $t['id']] = $t;
            $byKey[self::keyOf($t, $key)] = (int) $t['id'];
        }
        $childTables = array_values(array_filter($children, static function ($c) use ($table) {
            $r = SitePack::tables()[$c] ?? [];
            return ($r['parent'][0] ?? '') === $table && !in_array($c, ['content_tags', 'content_categories'], true) && SitePack::tableExists($c);
        }));
        $packChildren = [];
        $targetChildren = [];
        foreach ($childTables as $c) {
            $cr = SitePack::tables()[$c];
            $fk = $cr['parent'][1];
            foreach ($this->packRows($c) as $row) {
                $packChildren[$c][(int) ($row[$fk] ?? 0)][] = $row;
            }
            foreach (Database::fetchAll('SELECT * FROM ' . $c) as $row) {
                $targetChildren[$c][(int) ($row[$fk] ?? 0)][] = $row;
            }
        }
        $ignore = array_flip(array_merge(SitePack::IGNORE_DIFF, ['id'], (array) ($rule['keep'] ?? []), (array) ($rule['drop'] ?? [])));
        $isMedia = $table === 'media';
        $packIds = [];
        $act = ['update' => [], 'add' => [], 'remove' => [], 'override' => []];
        $types = (array) ($m['content_types'] ?? []);

        foreach ($this->packRows($table) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $packIds[$id] = true;
            $k = self::keyOf($row, $key);
            $item = ['table' => $table, 'id' => $id, 'key' => self::keyLabel($row, $key), 'type' => (string) ($row['type'] ?? ''),
                'title' => mb_substr((string) ($row[$rule['title'] ?? ''] ?? ''), 0, 120), 'status' => '', 'fields' => [], 'reason' => '', 'overridable' => false];
            $t = $target[$id] ?? null;
            $status = '';
            if ($t !== null && self::keyOf($t, $key) === $k) {
                $status = 'compare';
            } elseif ($t !== null) {
                $item['status'] = 'conflict';
                $item['reason'] = 'ID #' . $id . ' is "' . self::keyLabel($t, $key) . '"' . (isset($t['type']) && ($t['type'] ?? '') !== ($row['type'] ?? '') ? ' (' . $t['type'] . ')' : '') . ' on this site.';
                $other = $byKey[$k] ?? null;
                $item['overridable'] = $other === null;
                if ($other !== null) {
                    $item['reason'] .= ' The pack key is already used by #' . $other . '.';
                }
                if ($item['overridable'] && isset($overrides[$table . ':' . $id])) {
                    $status = 'compare';
                    $item['override'] = true;
                }
            } else {
                $other = $byKey[$k] ?? null;
                if ($other !== null) {
                    $item['status'] = 'conflict';
                    $item['reason'] = '"' . self::keyLabel($row, $key) . '" already exists here as #' . $other . ' (different ID).';
                } elseif ($addNew) {
                    $item['status'] = 'add';
                } else {
                    $item['status'] = 'skip';
                    $item['reason'] = 'New item — "Add new items" is off.';
                }
            }
            if ($status === 'compare') {
                $t = $target[$id];
                foreach ($row as $col => $v) {
                    if (isset($ignore[$col]) || !isset($cols[$col]) || !array_key_exists($col, $t)) {
                        continue;
                    }
                    if (self::norm($v) !== self::norm($t[$col])) {
                        $item['fields'][] = $col;
                    }
                }
                foreach ($childTables as $c) {
                    $diff = $this->childDiff($c, $packChildren[$c][$id] ?? [], $targetChildren[$c][$id] ?? []);
                    if ($diff !== []) {
                        array_push($item['fields'], ...$diff);
                        $plan['children'][$c][] = $id;
                    }
                }
                $item['status'] = ($item['fields'] !== [] || !empty($item['override'])) ? 'update' : 'unchanged';
            }
            if ($item['status'] === 'add') {
                foreach ($childTables as $c) {
                    if (!empty($packChildren[$c][$id])) {
                        $plan['children'][$c][] = $id;
                    }
                }
            }
            if ($isMedia && in_array($item['status'], ['add', 'update', 'unchanged'], true)) {
                foreach (SitePack::mediaFiles($row) as $rel) {
                    $name = 'media/' . $rel;
                    if (!$this->r->has($name)) {
                        continue;
                    }
                    if ($this->fileDiffers($rel, $m)) {
                        $plan['files'][$rel] = (string) $this->r->sha($name);
                        if ($item['status'] !== 'add') {
                            $item['fields'][] = 'file:' . basename($rel);
                        }
                    }
                }
                if ($item['status'] === 'unchanged' && $item['fields'] !== []) {
                    $item['status'] = 'update';
                }
            }
            if ($table === 'content' && in_array('body', $item['fields'], true)) {
                $item['diff'] = true;
            }
            if ($item['status'] === 'update') {
                $act['update'][] = $id;
                if (!empty($item['override'])) {
                    $act['override'][] = $id;
                }
            } elseif ($item['status'] === 'add') {
                $act['add'][] = $id;
            }
            $out['counts'][$item['status']]++;
            $out['items'][] = $item;
        }
        if ($remove) {
            foreach ($target as $id => $t) {
                if (isset($packIds[$id])) {
                    continue;
                }
                if ($table === 'content' && $types !== [] && !in_array((string) $t['type'], $types, true)) {
                    continue;
                }
                if ($table === 'content_types' && (!empty($t['is_system']) || Database::exists('content', 'type = :t', ['t' => $t['slug']]))) {
                    continue;
                }
                $act['remove'][] = $id;
                $out['counts']['remove']++;
                $out['items'][] = ['table' => $table, 'id' => $id, 'key' => self::keyLabel($t, $key), 'type' => (string) ($t['type'] ?? ''),
                    'title' => mb_substr((string) ($t[$rule['title'] ?? ''] ?? ''), 0, 120), 'status' => 'remove', 'fields' => [], 'reason' => 'Not in the pack.', 'overridable' => false];
                if ($isMedia) {
                    foreach (SitePack::mediaFiles($t) as $rel) {
                        $plan['remove_files'][] = $rel;
                    }
                }
            }
        }
        foreach ($plan['children'] as $c => $ids) {
            $plan['children'][$c] = array_values(array_unique($ids));
        }
        $plan['actions'][$table] = $act;
    }

    /** Field-level labels for differing child rows (meta keys by name). */
    private function childDiff(string $table, array $pack, array $target): array
    {
        $sig = (array) SitePack::tables()[$table]['sig'];
        if ($table === 'content_meta') {
            $a = [];
            foreach ($pack as $r) {
                $a[(string) $r['meta_key']] = self::norm($r['meta_value'] ?? null);
            }
            $b = [];
            foreach ($target as $r) {
                $b[(string) $r['meta_key']] = self::norm($r['meta_value'] ?? null);
            }
            $out = [];
            foreach ($a + $b as $k => $_) {
                if (($a[$k] ?? null) !== ($b[$k] ?? null)) {
                    $out[] = 'meta:' . $k;
                }
            }
            return $out;
        }
        $s = static function (array $rows) use ($sig): array {
            $o = [];
            foreach ($rows as $r) {
                $o[] = implode("\x1f", array_map(static fn ($c) => self::norm($r[$c] ?? null), $sig));
            }
            sort($o);
            return $o;
        };
        $short = ['content_fields' => 'fields', 'content_relations' => 'relations', 'blog_items' => 'items', 'gallery_items' => 'items', 'site_ai_chunks' => 'chunks'];
        return $s($pack) === $s($target) ? [] : [($short[$table] ?? $table)];
    }

    private function fileDiffers(string $rel, array $m): bool
    {
        $abs = Bootstrap::path('uploads') . '/' . $rel;
        if (!is_file($abs)) {
            return true;
        }
        $info = $m['files']['media/' . $rel] ?? [];
        if ((int) ($info['size'] ?? -1) !== (int) filesize($abs)) {
            return true;
        }
        return !hash_equals((string) ($info['sha256'] ?? ''), (string) hash_file('sha256', $abs));
    }

    private function planSettings(string $gid, bool $addNew, array &$plan, array &$out): void
    {
        $d = $this->data('data/settings.' . $gid . '.json');
        if ($d === null) {
            return;
        }
        $current = [];
        foreach (Database::fetchAll('SELECT setting_key, setting_value FROM settings') as $r) {
            $current[(string) $r['setting_key']] = (string) ($r['setting_value'] ?? '');
        }
        if ($gid === 'design') {
            $site = Database::fetch('SELECT theme FROM sites WHERE is_primary = 1 ORDER BY id LIMIT 1');
            if ($site) {
                $current[SitePack::SITE_THEME_KEY] = (string) $site['theme'];
            }
        }
        foreach ($d['rows'] as $r) {
            $k = (string) ($r['setting_key'] ?? '');
            $v = self::norm($r['setting_value'] ?? null);
            $item = ['table' => 'settings', 'id' => $k, 'key' => $k, 'type' => 'setting', 'title' => $k === SitePack::SITE_THEME_KEY ? 'Site theme' : $k, 'status' => '', 'fields' => [], 'reason' => '', 'overridable' => false];
            if (SitePack::settingGroup($k) !== $gid) {
                $item['status'] = 'blocked';
                $item['title'] = '(refused key)';
                $item['key'] = SitePack::isSecretKey($k) ? '[secret-looking key]' : mb_substr($k, 0, 60);
                $item['reason'] = 'Secret, internal or out-of-group settings are never imported.';
                $plan['blocked'][] = 'settings.' . $gid . ': ' . $item['key'];
            } elseif (!array_key_exists($k, $current)) {
                $item['status'] = $addNew ? 'add' : 'skip';
            } elseif ($current[$k] !== $v) {
                $item['status'] = 'update';
                $item['fields'] = ['value'];
            } else {
                $item['status'] = 'unchanged';
            }
            if (in_array($item['status'], ['add', 'update'], true)) {
                $plan['settings'][$gid][] = $k;
            }
            $out['counts'][$item['status']]++;
            $out['items'][] = $item;
        }
    }

    private function planKv(string $table, bool $addNew, array &$plan, array &$out): void
    {
        if (!$this->packHas($table) || !SitePack::tableExists($table)) {
            return;
        }
        $current = [];
        foreach (Database::fetchAll('SELECT setting_key, setting_value FROM ' . $table) as $r) {
            $current[(string) $r['setting_key']] = (string) ($r['setting_value'] ?? '');
        }
        foreach ($this->packRows($table) as $r) {
            $k = (string) ($r['setting_key'] ?? '');
            $item = ['table' => $table, 'id' => $k, 'key' => $k, 'type' => 'setting', 'title' => 'Assistant: ' . $k, 'status' => '', 'fields' => [], 'reason' => '', 'overridable' => false];
            if ($k === '' || SitePack::isSecretKey($k) || !preg_match('/^[A-Za-z0-9_.:-]{1,80}$/', $k)) {
                $item['status'] = 'blocked';
                $item['key'] = '[refused key]';
                $item['reason'] = 'Secret-looking keys are never imported.';
            } else {
                $v = $this->kvValue($r);
                if (!array_key_exists($k, $current)) {
                    $item['status'] = $addNew ? 'add' : 'skip';
                } elseif ($current[$k] !== $v) {
                    $item['status'] = 'update';
                    $item['fields'] = ['value'];
                } else {
                    $item['status'] = 'unchanged';
                }
                if (in_array($item['status'], ['add', 'update'], true)) {
                    $plan['kv'][$table][] = $k;
                }
            }
            $out['counts'][$item['status']]++;
            $out['items'][] = $item;
        }
    }

    private function kvValue(array $r): string
    {
        $v = self::norm($r['setting_value'] ?? null);
        $j = json_decode($v, true);
        if (is_array($j)) {
            $clean = SitePack::scrubSecrets($j);
            if ($clean !== $j) {
                $v = json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }
        return $v;
    }

    private function planLooseFiles(array &$plan, array &$out): void
    {
        $m = $this->r->manifest();
        foreach ((array) ($m['features']['loose_files'] ?? []) as $rel) {
            $rel = SitePack::safeRelPath((string) $rel);
            if ($rel === null || !$this->r->has('media/' . $rel) || isset($plan['files'][$rel])) {
                continue;
            }
            $differs = $this->fileDiffers($rel, $m);
            $exists = is_file(Bootstrap::path('uploads') . '/' . $rel);
            $status = !$differs ? 'unchanged' : ($exists ? 'update' : 'add');
            if ($differs) {
                $plan['files'][$rel] = (string) $this->r->sha('media/' . $rel);
            }
            $out['counts'][$status]++;
            $out['items'][] = ['table' => '_file', 'id' => $rel, 'key' => $rel, 'type' => 'file', 'title' => 'Design file ' . basename($rel), 'status' => $status, 'fields' => $differs ? ['file'] : [], 'reason' => '', 'overridable' => false];
        }
    }

    /** Category/tag assignments, compared per content item. */
    private function planAssignments(array $packContentIds, array &$plan): void
    {
        $out = &$plan['groups']['taxonomy'];
        $contentTitles = [];
        foreach (Database::fetchAll('SELECT id, slug, title FROM content') as $c) {
            $contentTitles[(int) $c['id']] = $c;
        }
        $conflicts = [];
        foreach ($plan['groups']['content']['items'] ?? [] as $it) {
            if ($it['table'] === 'content' && in_array($it['status'], ['conflict', 'skip'], true)) {
                $conflicts[(int) $it['id']] = true;
            }
        }
        foreach (['content_tags' => 'tag_id', 'content_categories' => 'category_id'] as $table => $col) {
            if (!$this->packHas($table) || !SitePack::tableExists($table)) {
                continue;
            }
            $pack = [];
            foreach ($this->packRows($table) as $r) {
                $pack[(int) $r['content_id']][] = (int) $r[$col];
            }
            $target = [];
            foreach (Database::fetchAll("SELECT content_id, $col FROM $table") as $r) {
                $target[(int) $r['content_id']][] = (int) $r[$col];
            }
            $scope = $packContentIds + array_fill_keys(array_keys($pack), true);
            foreach (array_keys($scope) as $cid) {
                if (isset($conflicts[$cid])) {
                    continue;
                }
                $a = $pack[$cid] ?? [];
                $b = $target[$cid] ?? [];
                sort($a);
                sort($b);
                if ($a === $b) {
                    continue;
                }
                $plan['children'][$table][] = $cid;
                $c = $contentTitles[$cid] ?? null;
                $out['counts']['update']++;
                $out['items'][] = ['table' => $table, 'id' => $cid, 'key' => (string) ($c['slug'] ?? ('#' . $cid)), 'type' => 'assignment',
                    'title' => ($table === 'content_tags' ? 'Tags of ' : 'Categories of ') . mb_substr((string) ($c['title'] ?? ('new item #' . $cid)), 0, 80),
                    'status' => 'update', 'fields' => [($table === 'content_tags' ? 'tags' : 'categories') . ' ' . count($b) . '→' . count($a)], 'reason' => '', 'overridable' => false];
            }
        }
    }

    // ── apply: database (single transaction) ─────────────────────────────

    /**
     * @return array{stats:array,undo:array}
     */
    public function applyDatabase(array $plan, array $ctx = []): array
    {
        $this->failAfter = (int) ($ctx['fail_after'] ?? 0);
        $this->writes = 0;
        $this->setRewrite(!empty($plan['options']['rewrite_urls']));
        $userId = (int) ($ctx['user_id'] ?? 0);
        $userId = $userId > 0 && Database::exists('users', 'id = :id', ['id' => $userId]) ? $userId : null;
        $rules = SitePack::tables();
        $pdo = Database::connection();
        $stats = ['updated' => 0, 'added' => 0, 'removed' => 0, 'children' => 0, 'settings' => 0, 'revisions' => 0, 'skipped_refs' => 0];
        $undo = ['tables' => [], 'settings' => [], 'kv' => [], 'site_theme' => null];
        $touched = [];
        $now = date('Y-m-d H:i:s');

        // Orphans that already exist on this site (e.g. meta left behind by an old delete) must not block an import;
        // only references broken *by this import* do.
        $preexisting = [];
        try {
            foreach ($pdo->query('PRAGMA foreign_key_check')->fetchAll(\PDO::FETCH_ASSOC) as $v) {
                $preexisting[($v['table'] ?? '') . '|' . ($v['rowid'] ?? '') . '|' . ($v['parent'] ?? '')] = true;
            }
        } catch (\Throwable $e) {
        }

        $pdo->beginTransaction();
        try {
            $pdo->exec('PRAGMA defer_foreign_keys = ON');
            // 1) removals, children first
            foreach (array_reverse(self::ORDER) as $table) {
                $ids = $plan['actions'][$table]['remove'] ?? [];
                if ($ids === []) {
                    continue;
                }
                foreach (array_chunk($ids, 500) as $chunk) {
                    $n = $pdo->exec('DELETE FROM ' . $table . ' WHERE id IN (' . self::inIds($chunk) . ')');
                    $stats['removed'] += (int) $n;
                    $this->tick();
                }
                $touched[$table] = true;
                foreach (self::referencingTables($table) as $rt) {
                    $touched[$rt] = true; // cascades: restore on undo
                }
            }
            // 2) updates/adds per table, then that table's child rows
            foreach (self::ORDER as $table) {
                $rule = $rules[$table] ?? [];
                $act = $plan['actions'][$table] ?? null;
                if ($act !== null && ($act['update'] || $act['add'])) {
                    $byId = [];
                    $need = array_flip(array_merge($act['update'], $act['add']));
                    foreach ($this->packRows($table) as $row) {
                        if (isset($need[(int) ($row['id'] ?? 0)])) {
                            $byId[(int) $row['id']] = $row;
                        }
                    }
                    $cols = array_flip(SitePack::tableColumns($table));
                    $keep = array_flip((array) ($rule['keep'] ?? []));
                    $drop = array_flip((array) ($rule['drop'] ?? []));
                    foreach ($act['update'] as $id) {
                        $row = $byId[$id] ?? null;
                        if ($row === null) {
                            continue;
                        }
                        if ($table === 'content') {
                            $this->revision($id, $userId);
                            $stats['revisions']++;
                            $touched['revisions'] = true;
                        }
                        $set = [];
                        foreach ($row as $c => $v) {
                            if ($c === 'id' || isset($keep[$c]) || isset($drop[$c]) || !isset($cols[$c])) {
                                continue;
                            }
                            $set[$c] = self::dbValue($v);
                        }
                        if ($set) {
                            Database::update($table, $set, 'id = :__id', ['__id' => $id]);
                            $stats['updated']++;
                            $this->tick();
                        }
                    }
                    foreach ($act['add'] as $id) {
                        $row = $byId[$id] ?? null;
                        if ($row === null) {
                            continue;
                        }
                        if (Database::exists($table, 'id = :id', ['id' => $id])) {
                            throw new \RuntimeException('Cannot add ' . $table . ' #' . $id . ': that ID was taken since the preview. Run the preview again.');
                        }
                        $ins = ['id' => $id];
                        foreach ($row as $c => $v) {
                            if ($c === 'id' || isset($drop[$c]) || !isset($cols[$c])) {
                                continue;
                            }
                            $ins[$c] = self::dbValue($v);
                        }
                        foreach ((array) ($rule['users'] ?? []) as $uc) {
                            if (isset($ins[$uc]) && !Database::exists('users', 'id = :id', ['id' => (int) $ins[$uc]])) {
                                $ins[$uc] = $userId;
                            }
                        }
                        foreach (['created_by', 'updated_by'] as $uc) {
                            if (isset($ins[$uc]) && isset($cols[$uc]) && !Database::exists('users', 'id = :id', ['id' => (int) $ins[$uc]])) {
                                $ins[$uc] = $userId;
                            }
                        }
                        Database::insert($table, $ins);
                        $stats['added']++;
                        $this->tick();
                    }
                    if ($table === 'media') {
                        $this->syncMediaHashes(array_merge($act['update'], $act['add']), $byId);
                    }
                    $touched[$table] = true;
                    $this->nullDanglingRefs($table, $rule, array_merge($act['update'], $act['add']));
                }
                // child rows for this table (when it is a child table)
                $parents = $plan['children'][$table] ?? [];
                if ($parents !== [] && isset($rule['parent'])) {
                    $stats['children'] += $this->replaceChildren($table, $rule, $parents, $stats);
                    $touched[$table] = true;
                }
            }
            // 3) settings
            foreach ($plan['settings'] as $gid => $keys) {
                $d = $this->data('data/settings.' . $gid . '.json');
                $vals = [];
                foreach ($d['rows'] ?? [] as $r) {
                    $vals[(string) $r['setting_key']] = self::norm($r['setting_value'] ?? null);
                }
                foreach ($keys as $k) {
                    if (!array_key_exists($k, $vals) || SitePack::settingGroup($k) !== $gid) {
                        continue;
                    }
                    if ($k === SitePack::SITE_THEME_KEY) {
                        $site = Database::fetch('SELECT id, theme FROM sites WHERE is_primary = 1 ORDER BY id LIMIT 1');
                        if ($site && preg_match('/^[a-z0-9_-]{1,60}$/i', $vals[$k])) {
                            $undo['site_theme'] = ['id' => (int) $site['id'], 'theme' => (string) $site['theme']];
                            Database::update('sites', ['theme' => $vals[$k], 'updated_at' => $now], 'id = :id', ['id' => (int) $site['id']]);
                            $stats['settings']++;
                        }
                        continue;
                    }
                    $old = Database::fetch('SELECT setting_value FROM settings WHERE setting_key = :k', ['k' => $k]);
                    $undo['settings'][$k] = $old ? (string) ($old['setting_value'] ?? '') : null;
                    $this->upsertKv('settings', $k, $vals[$k], $now);
                    $stats['settings']++;
                    $this->tick();
                }
            }
            foreach ($plan['kv'] as $table => $keys) {
                $vals = [];
                foreach ($this->packRows($table) as $r) {
                    $vals[(string) $r['setting_key']] = $this->kvValue($r);
                }
                foreach ($keys as $k) {
                    if (!array_key_exists($k, $vals) || SitePack::isSecretKey($k)) {
                        continue;
                    }
                    $old = Database::fetch('SELECT setting_value FROM ' . $table . ' WHERE setting_key = :k', ['k' => $k]);
                    $undo['kv'][$table][$k] = $old ? (string) ($old['setting_value'] ?? '') : null;
                    $this->upsertKv($table, $k, $vals[$k], $now);
                    $stats['settings']++;
                    $this->tick();
                }
            }
            $viol = $pdo->query('PRAGMA foreign_key_check')->fetchAll(\PDO::FETCH_ASSOC);
            $viol = array_values(array_filter($viol, static fn ($v) => isset($touched[$v['table'] ?? ''])
                && !isset($preexisting[($v['table'] ?? '') . '|' . ($v['rowid'] ?? '') . '|' . ($v['parent'] ?? '')])));
            if ($viol) {
                $v = $viol[0];
                throw new \RuntimeException('Import would break a reference (' . $v['table'] . ' row ' . ($v['rowid'] ?? '?') . ' → ' . $v['parent'] . ').');
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            try {
                $pdo->exec('PRAGMA defer_foreign_keys = OFF');
            } catch (\Throwable $e2) {
            }
            throw $e;
        }
        $undo['tables'] = array_keys($touched);
        return ['stats' => $stats, 'undo' => $undo];
    }

    private function tick(): void
    {
        $this->writes++;
        if ($this->failAfter > 0 && $this->writes >= $this->failAfter) {
            throw new \RuntimeException('Simulated failure after ' . $this->writes . ' writes (test hook).');
        }
    }

    private static function dbValue($v)
    {
        if (is_bool($v)) {
            return $v ? 1 : 0;
        }
        if (is_array($v)) {
            return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return $v;
    }

    private function upsertKv(string $table, string $k, string $v, string $now): void
    {
        $cols = array_flip(SitePack::tableColumns($table));
        if (Database::exists($table, 'setting_key = :k', ['k' => $k])) {
            $set = ['setting_value' => $v];
            if (isset($cols['updated_at'])) {
                $set['updated_at'] = $now;
            }
            Database::update($table, $set, 'setting_key = :__k', ['__k' => $k]);
        } else {
            $ins = ['setting_key' => $k, 'setting_value' => $v];
            if (isset($cols['updated_at'])) {
                $ins['updated_at'] = $now;
            }
            if (isset($cols['created_at'])) {
                $ins['created_at'] = $now;
            }
            Database::insert($table, $ins);
        }
    }

    private function revision(int $contentId, ?int $userId): void
    {
        $old = Database::fetch('SELECT title, body, excerpt FROM content WHERE id = :id', ['id' => $contentId]);
        if (!$old) {
            return;
        }
        $meta = [];
        foreach (Database::fetchAll('SELECT meta_key, meta_value FROM content_meta WHERE content_id = :id', ['id' => $contentId]) as $r) {
            $meta[(string) $r['meta_key']] = $r['meta_value'];
        }
        $meta['_note'] = 'Before site pack import';
        (new \Mova\Content\ContentRepository())->createRevision($contentId, ['title' => $old['title'], 'body' => $old['body'], 'excerpt' => $old['excerpt'], 'meta' => $meta], $userId);
    }

    private function syncMediaHashes(array $ids, array $byId): void
    {
        $cols = array_flip(SitePack::tableColumns('media'));
        foreach ($ids as $id) {
            $row = $byId[$id] ?? null;
            $rel = $row ? SitePack::safeRelPath((string) ($row['path'] ?? '')) : null;
            if ($rel === null) {
                continue;
            }
            $info = $this->r->manifest()['files']['media/' . $rel] ?? null;
            if (!$info) {
                continue;
            }
            $set = [];
            if (isset($cols['file_hash'])) {
                $set['file_hash'] = $info['sha256'];
            }
            if (isset($cols['size'])) {
                $set['size'] = (int) $info['size'];
            }
            if ($set) {
                Database::update('media', $set, 'id = :__id', ['__id' => $id]);
            }
        }
    }

    private function nullDanglingRefs(string $table, array $rule, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $refs = (array) ($rule['refs_null'] ?? []);
        if ($table === 'content') {
            $refs['parent_id'] = 'content';
        }
        $cols = array_flip(SitePack::tableColumns($table));
        foreach ($refs as $col => $rt) {
            if (!isset($cols[$col])) {
                continue;
            }
            foreach (array_chunk($ids, 500) as $chunk) {
                Database::connection()->exec("UPDATE $table SET $col = NULL WHERE id IN (" . self::inIds($chunk) . ") AND $col IS NOT NULL AND $col NOT IN (SELECT id FROM $rt)");
            }
        }
    }

    private function replaceChildren(string $table, array $rule, array $parents, array &$stats): int
    {
        [$parentTable, $fk] = $rule['parent'];
        $cols = array_flip(SitePack::tableColumns($table));
        $noid = !empty($rule['noid']) || !isset($cols['id']);
        $byParent = [];
        foreach ($this->packRows($table) as $row) {
            $byParent[(int) ($row[$fk] ?? 0)][] = $row;
        }
        $n = 0;
        foreach ($parents as $pid) {
            if (!Database::exists($parentTable, 'id = :id', ['id' => (int) $pid])) {
                continue;
            }
            Database::delete($table, $fk . ' = :p', ['p' => (int) $pid]);
            $this->tick();
            foreach ($byParent[(int) $pid] ?? [] as $row) {
                $ok = true;
                foreach ((array) ($rule['refs'] ?? []) as $rc => $rt) {
                    if (($row[$rc] ?? null) !== null && !Database::exists($rt, 'id = :id', ['id' => (int) $row[$rc]])) {
                        $ok = false;
                    }
                }
                if (!$ok) {
                    $stats['skipped_refs']++;
                    continue;
                }
                $ins = [];
                foreach ($row as $c => $v) {
                    if (!isset($cols[$c]) || ($c === 'id' && $noid)) {
                        continue;
                    }
                    $ins[$c] = self::dbValue($v);
                }
                if (isset($ins['id']) && Database::exists($table, 'id = :id', ['id' => (int) $ins['id']])) {
                    unset($ins['id']);
                }
                $ins[$fk] = (int) $pid;
                Database::insert($table, $ins);
                $n++;
                $this->tick();
            }
        }
        return $n;
    }

    /** Tables with a foreign key pointing at $table (rows they lose by cascade are restored by Undo). */
    public static function referencingTables(string $table): array
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (Database::fetchAll("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'") as $t) {
                $name = (string) $t['name'];
                try {
                    foreach (Database::fetchAll('PRAGMA foreign_key_list(' . $name . ')') as $fk) {
                        $map[(string) $fk['table']][$name] = true;
                    }
                } catch (\Throwable $e) {
                }
            }
        }
        return array_keys($map[$table] ?? []);
    }

    // ── media: stage, swap ───────────────────────────────────────────────

    /** Extract planned files into staging (verified). Resumable: returns next index or -1 when done. */
    public function stage(array $files, string $stagingDir, int $from, float $deadline): int
    {
        $list = array_keys($files);
        sort($list);
        $n = count($list);
        for ($i = $from; $i < $n; $i++) {
            $rel = SitePack::safeRelPath((string) $list[$i]);
            if ($rel === null) {
                throw new \RuntimeException('Unsafe media path in plan.');
            }
            $this->r->extractTo('media/' . $rel, $stagingDir . '/' . $rel);
            if (strtolower(pathinfo($rel, PATHINFO_EXTENSION)) === 'svg') {
                // Same sanitiser as a normal upload: strips scripts, event handlers and external references.
                $svc = new \Mova\Media\MediaService();
                \Closure::bind(function (string $f): void {
                    $this->sanitizeSvg($f);
                }, $svc, \Mova\Media\MediaService::class)($stagingDir . '/' . $rel);
            }
            if ($deadline > 0 && microtime(true) > $deadline && $i + 1 < $n) {
                return $i + 1;
            }
        }
        return -1;
    }

    /**
     * Move staged files into the uploads folder; replaced and removed files go to the undo stash.
     * @return array{added:list<string>,replaced:list<string>,removed:list<string>}
     */
    public static function swap(array $files, array $removeFiles, string $stagingDir, string $stashDir): array
    {
        $uploads = rtrim(Bootstrap::path('uploads'), '/');
        if (!is_dir($uploads)) {
            @mkdir($uploads, 0755, true);
        }
        $base = realpath($uploads) ?: $uploads;
        $res = ['added' => [], 'replaced' => [], 'removed' => []];
        $place = static function (string $src, string $dst) use ($base): void {
            $dir = dirname($dst);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $real = realpath($dir);
            if ($real === false || !str_starts_with($real . '/', $base . '/')) {
                throw new \RuntimeException('Refusing to write outside the uploads folder.');
            }
            if (!@rename($src, $dst)) {
                if (!@copy($src, $dst)) {
                    throw new \RuntimeException('Cannot move a media file into place.');
                }
                @unlink($src);
            }
        };
        $stashOne = static function (string $rel) use ($uploads, $stashDir): bool {
            $cur = $uploads . '/' . $rel;
            if (!is_file($cur) || is_link($cur)) {
                return false;
            }
            $to = $stashDir . '/' . $rel;
            @mkdir(dirname($to), 0755, true);
            if (!@rename($cur, $to)) {
                @copy($cur, $to);
                @unlink($cur);
            }
            return true;
        };
        foreach (array_keys($files) as $rel) {
            $rel = SitePack::safeRelPath((string) $rel);
            $src = $rel === null ? '' : $stagingDir . '/' . $rel;
            if ($rel === null || !is_file($src)) {
                continue;
            }
            if ($stashOne($rel)) {
                $res['replaced'][] = $rel;
            } else {
                $res['added'][] = $rel;
            }
            $place($src, $uploads . '/' . $rel);
        }
        foreach ($removeFiles as $rel) {
            $rel = SitePack::safeRelPath((string) $rel);
            if ($rel !== null && !isset($files[$rel]) && $stashOne($rel)) {
                $res['removed'][] = $rel;
            }
        }
        return $res;
    }

    // ── after apply / undo ───────────────────────────────────────────────

    public static function refreshCaches(): array
    {
        $done = [];
        try {
            \Mova\Cache\PageCache::flush();
            $done[] = 'page cache cleared';
        } catch (\Throwable $e) {
        }
        try {
            \Mova\Hq\SearchIndex::rebuild();
            $done[] = 'HQ search rebuilt';
        } catch (\Throwable $e) {
        }
        try {
            $src = Bootstrap::path('plugins') . '/mova-site-ai/src';
            if (!class_exists('\\MovaSiteAi\\SiteIndex', false) && is_dir($src)) {
                spl_autoload_register(static function (string $cls) use ($src): void {
                    if (str_starts_with($cls, 'MovaSiteAi\\')) {
                        $f = $src . '/' . str_replace('\\', '/', substr($cls, 11)) . '.php';
                        if (is_file($f)) {
                            require_once $f;
                        }
                    }
                });
            }
            if (class_exists('\\MovaSiteAi\\SiteIndex')) {
                \MovaSiteAi\SiteIndex::rebuild();
                $done[] = 'site AI index rebuilt';
            }
        } catch (\Throwable $e) {
        }
        try {
            $now = date('Y-m-d H:i:s');
            if (Database::exists('settings', "setting_key = 'pwa_rev'")) {
                Database::update('settings', ['setting_value' => (string) time(), 'updated_at' => $now], "setting_key = 'pwa_rev'");
            } else {
                Database::insert('settings', ['setting_key' => 'pwa_rev', 'setting_value' => (string) time(), 'updated_at' => $now]);
            }
            $css = Bootstrap::path('root') . '/public/assets/css/site.css';
            if (is_file($css)) {
                @touch($css);
            }
            $done[] = 'asset versions bumped';
        } catch (\Throwable $e) {
        }
        return $done;
    }

    /** Restore the touched tables, settings and files from an import's undo snapshot. */
    public static function undo(array $job): array
    {
        $dir = SitePack::importsDir() . '/undo/' . $job['id'];
        $snap = $dir . '/database.sqlite';
        if (!is_file($snap)) {
            throw new \RuntimeException('The undo snapshot for this import is gone; restore the pre-import backup from Backups instead.');
        }
        $undo = (array) ($job['result']['undo'] ?? []);
        $tables = array_values(array_filter((array) ($undo['tables'] ?? []), static fn ($t) => preg_match('/^[a-z0-9_]+$/', (string) $t) && !in_array($t, ['users', 'api_keys', 'sessions'], true)));
        $pdo = Database::connection();
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('ATTACH DATABASE ' . $pdo->quote($snap) . ' AS packsnap');
        $restored = [];
        try {
            $pdo->beginTransaction();
            foreach ($tables as $t) {
                $isTable = $pdo->query("SELECT sql FROM main.sqlite_master WHERE type = 'table' AND name = " . $pdo->quote($t))->fetchColumn();
                $inSnap = $pdo->query("SELECT 1 FROM packsnap.sqlite_master WHERE type = 'table' AND name = " . $pdo->quote($t))->fetchColumn();
                if (!$isTable || !$inSnap || stripos((string) $isTable, 'VIRTUAL TABLE') !== false) {
                    continue;
                }
                $a = array_column($pdo->query('PRAGMA main.table_info(' . $t . ')')->fetchAll(\PDO::FETCH_ASSOC), 'name');
                $b = array_column($pdo->query('PRAGMA packsnap.table_info(' . $t . ')')->fetchAll(\PDO::FETCH_ASSOC), 'name');
                $cols = implode(', ', array_map(static fn ($c) => '"' . str_replace('"', '""', $c) . '"', array_values(array_intersect($a, $b))));
                $pdo->exec('DELETE FROM main.' . $t);
                $pdo->exec('INSERT INTO main.' . $t . ' (' . $cols . ') SELECT ' . $cols . ' FROM packsnap.' . $t);
                $restored[] = $t;
            }
            $now = date('Y-m-d H:i:s');
            foreach ((array) ($undo['settings'] ?? []) as $k => $old) {
                if ($old === null) {
                    Database::delete('settings', 'setting_key = :k', ['k' => (string) $k]);
                } elseif (Database::exists('settings', 'setting_key = :k', ['k' => (string) $k])) {
                    Database::update('settings', ['setting_value' => (string) $old, 'updated_at' => $now], 'setting_key = :__k', ['__k' => (string) $k]);
                } else {
                    Database::insert('settings', ['setting_key' => (string) $k, 'setting_value' => (string) $old, 'updated_at' => $now]);
                }
            }
            foreach ((array) ($undo['kv'] ?? []) as $table => $vals) {
                if (!in_array($table, ['site_ai_settings'], true)) {
                    continue;
                }
                foreach ((array) $vals as $k => $old) {
                    Database::delete($table, 'setting_key = :k', ['k' => (string) $k]);
                    if ($old !== null) {
                        $ins = ['setting_key' => (string) $k, 'setting_value' => (string) $old];
                        if (in_array('updated_at', SitePack::tableColumns($table), true)) {
                            $ins['updated_at'] = $now;
                        }
                        Database::insert($table, $ins);
                    }
                }
            }
            if (!empty($undo['site_theme']['id'])) {
                Database::update('sites', ['theme' => (string) $undo['site_theme']['theme'], 'updated_at' => $now], 'id = :id', ['id' => (int) $undo['site_theme']['id']]);
            }
            if (in_array('content', $restored, true)) {
                try {
                    $pdo->exec("INSERT INTO content_fts(content_fts) VALUES('rebuild')");
                } catch (\Throwable $e) {
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            try {
                $pdo->exec('DETACH DATABASE packsnap');
            } catch (\Throwable $e) {
            }
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        // files
        $uploads = rtrim(Bootstrap::path('uploads'), '/');
        $media = (array) ($job['result']['media'] ?? []);
        $files = 0;
        foreach ((array) ($media['added'] ?? []) as $rel) {
            $rel = SitePack::safeRelPath((string) $rel);
            if ($rel !== null && is_file($uploads . '/' . $rel)) {
                @unlink($uploads . '/' . $rel);
                $files++;
            }
        }
        foreach (array_merge((array) ($media['replaced'] ?? []), (array) ($media['removed'] ?? [])) as $rel) {
            $rel = SitePack::safeRelPath((string) $rel);
            if ($rel === null || !is_file($dir . '/media/' . $rel)) {
                continue;
            }
            @mkdir(dirname($uploads . '/' . $rel), 0755, true);
            if (!@rename($dir . '/media/' . $rel, $uploads . '/' . $rel)) {
                @copy($dir . '/media/' . $rel, $uploads . '/' . $rel);
            }
            $files++;
        }
        $done = self::refreshCaches();
        return ['tables' => $restored, 'files' => $files, 'refreshed' => $done];
    }

    /** Old/new body of one content item for the diff view. */
    public function bodies(int $id): ?array
    {
        foreach ($this->packRows('content') as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                $cur = Database::fetch('SELECT title, slug, type, body FROM content WHERE id = :id', ['id' => $id]);
                return ['title' => (string) ($row['title'] ?? ''), 'slug' => (string) ($row['slug'] ?? ''), 'type' => (string) ($row['type'] ?? ''),
                    'old' => (string) ($cur['body'] ?? ''), 'new' => (string) ($row['body'] ?? ''), 'exists' => (bool) $cur];
            }
        }
        return null;
    }
}
