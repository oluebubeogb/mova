<?php
/**
 * Writes a .movapack site pack. Streams rows to temp JSON files and lets ZipArchive read media from disk
 * at close time, so memory stays flat on shared hosting even with hundreds of MB of media.
 */

declare(strict_types=1);

namespace Mova\Backup;

use Mova\Core\Bootstrap;
use Mova\Core\Database;

final class SitePackExporter
{
    private const JSON = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
    /** Already-compressed formats are stored, not deflated (faster, same size). */
    private const STORE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'mp4', 'webm', 'mov', 'm4v', 'mp3', 'm4a', 'ogg', 'ogv', 'woff', 'woff2', 'pdf'];

    private string $tmp = '';
    /** @var array<string,array{sha256:string,size:int}> */
    private array $files = [];
    /** @var array<string,list<array>> */
    private array $items = [];
    private array $counts = [];
    private array $features = [];
    private array $warnings = [];
    /** @var array<string,string> zip name => absolute source path */
    private array $media = [];

    // ── what can be exported (for the HQ form) ───────────────────────────

    /** @return array<string,array{count:int,detail:string,types?:array}> */
    public static function inventory(): array
    {
        $c = static function (string $sql): int {
            try {
                return (int) (Database::fetch($sql)['n'] ?? 0);
            } catch (\Throwable $e) {
                return 0;
            }
        };
        $types = [];
        try {
            foreach (Database::fetchAll("SELECT c.type, COUNT(*) AS n, t.name FROM content c LEFT JOIN content_types t ON t.slug = c.type GROUP BY c.type ORDER BY n DESC") as $r) {
                $types[(string) $r['type']] = ['count' => (int) $r['n'], 'label' => (string) ($r['name'] ?: ucfirst((string) $r['type']))];
            }
        } catch (\Throwable $e) {
        }
        $settingsCount = ['menus' => 0, 'design' => 0, 'settings' => 0];
        try {
            foreach (Database::fetchAll('SELECT setting_key FROM settings') as $r) {
                $g = SitePack::settingGroup((string) $r['setting_key']);
                if ($g !== null) {
                    $settingsCount[$g]++;
                }
            }
        } catch (\Throwable $e) {
        }
        $mediaBytes = 0;
        try {
            $mediaBytes = (int) (Database::fetch('SELECT COALESCE(SUM(size),0) AS s FROM media')['s'] ?? 0);
        } catch (\Throwable $e) {
        }
        return [
            'content' => ['count' => $c('SELECT COUNT(*) AS n FROM content'), 'detail' => 'items', 'types' => $types],
            'blogs' => ['count' => $c('SELECT COUNT(*) AS n FROM blogs'), 'detail' => 'blogs · ' . $c('SELECT COUNT(*) AS n FROM blog_items') . ' picked items'],
            'galleries' => ['count' => $c('SELECT COUNT(*) AS n FROM galleries'), 'detail' => 'galleries · ' . $c('SELECT COUNT(*) AS n FROM gallery_items') . ' items'],
            'media' => ['count' => $c('SELECT COUNT(*) AS n FROM media'), 'detail' => 'files · ~' . SitePack::fmtBytes($mediaBytes)],
            'menus' => ['count' => $settingsCount['menus'], 'detail' => 'menu settings'],
            'taxonomy' => ['count' => $c('SELECT COUNT(*) AS n FROM categories') + $c('SELECT COUNT(*) AS n FROM tags'),
                'detail' => $c('SELECT COUNT(*) AS n FROM categories') . ' categories · ' . $c('SELECT COUNT(*) AS n FROM tags') . ' tags · '
                    . ($c('SELECT COUNT(*) AS n FROM content_tags') + $c('SELECT COUNT(*) AS n FROM content_categories')) . ' assignments'],
            'design' => ['count' => $settingsCount['design'] + $c('SELECT COUNT(*) AS n FROM design_templates') + $c('SELECT COUNT(*) AS n FROM assemblies'),
                'detail' => $settingsCount['design'] . ' design settings · ' . $c('SELECT COUNT(*) AS n FROM design_templates') . ' templates · ' . $c('SELECT COUNT(*) AS n FROM assemblies') . ' assemblies'],
            'settings' => ['count' => $settingsCount['settings'], 'detail' => 'non-secret settings'],
            'site_ai' => ['count' => $c('SELECT COUNT(*) AS n FROM site_ai_sources'), 'detail' => 'sources · ' . $c('SELECT COUNT(*) AS n FROM site_ai_chunks') . ' chunks + assistant settings'],
            'forms' => ['count' => $c('SELECT COUNT(*) AS n FROM feedback_forms'), 'detail' => 'forms'],
            'subscribers' => ['count' => $c('SELECT COUNT(*) AS n FROM subscribers'), 'detail' => 'subscribers'],
            'form_entries' => ['count' => $c('SELECT COUNT(*) AS n FROM feedback_entries'), 'detail' => 'entries'],
            'comments' => ['count' => $c('SELECT COUNT(*) AS n FROM comments'), 'detail' => 'comments'],
        ];
    }

    // ── export ───────────────────────────────────────────────────────────

    /**
     * @param list<string> $groups  group ids (see SitePack::groups())
     * @param list<string> $types   content types to include (empty = all)
     * @param array{min_version?:string,out?:string,label?:string} $opts
     * @return array{path:string,name:string,size:int,manifest:array}
     */
    public function export(array $groups, array $types = [], array $opts = []): array
    {
        @set_time_limit(0);
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('The PHP zip extension is required to build site packs.');
        }
        $all = SitePack::groups();
        $groups = array_values(array_intersect(array_keys($all), $groups));
        if ($groups === []) {
            throw new \InvalidArgumentException('Choose at least one group to export.');
        }
        $types = array_values(array_filter(array_map(static fn ($t) => preg_replace('/[^a-z0-9_-]/i', '', (string) $t), $types)));
        if (in_array('content', $groups, true) && $types === []) {
            $types = array_map(static fn ($r) => (string) $r['type'], Database::fetchAll('SELECT DISTINCT type FROM content ORDER BY type'));
        }

        $this->tmp = SitePack::exportsDir() . '/.tmp-' . bin2hex(random_bytes(5));
        @mkdir($this->tmp . '/data', 0755, true);
        try {
            $contentIds = null;
            if (in_array('content', $groups, true)) {
                $this->exportContent($types);
                $contentIds = $this->idsOf('content');
            }
            if (in_array('blogs', $groups, true)) {
                $this->dumpTable('blogs', 'SELECT * FROM blogs ORDER BY id');
                $this->dumpTable('blog_items', 'SELECT * FROM blog_items ORDER BY blog_id, sort_order, id');
            }
            if (in_array('galleries', $groups, true)) {
                $this->dumpTable('galleries', 'SELECT * FROM galleries ORDER BY id');
                $this->dumpTable('gallery_items', 'SELECT * FROM gallery_items ORDER BY gallery_id, sort_order, id');
            }
            if (in_array('media', $groups, true)) {
                $this->dumpTable('media', 'SELECT * FROM media ORDER BY id', function (array $row): array {
                    foreach (SitePack::mediaFiles($row) as $rel) {
                        $this->addMediaFile($rel);
                    }
                    return $row;
                });
            }
            if (in_array('taxonomy', $groups, true)) {
                $this->dumpTable('categories', 'SELECT * FROM categories ORDER BY id');
                $this->dumpTable('tags', 'SELECT * FROM tags ORDER BY id');
                $filter = static fn (array $r): ?array => ($contentIds === null || isset($contentIds[(int) $r['content_id']])) ? $r : null;
                $this->dumpTable('content_tags', 'SELECT * FROM content_tags ORDER BY content_id, tag_id', $filter);
                $this->dumpTable('content_categories', 'SELECT * FROM content_categories ORDER BY content_id, category_id', $filter);
            }
            foreach (['menus', 'design', 'settings'] as $sg) {
                if (in_array($sg, $groups, true)) {
                    $this->exportSettings($sg);
                }
            }
            if (in_array('design', $groups, true)) {
                $this->dumpTable('design_templates', 'SELECT * FROM design_templates ORDER BY id');
                $this->dumpTable('assemblies', 'SELECT * FROM assemblies ORDER BY id', function (array $r): array {
                    $this->featureScan((string) ($r['html'] ?? ''));
                    $this->features['custom_css_bytes'] = ($this->features['custom_css_bytes'] ?? 0) + strlen((string) ($r['css'] ?? ''));
                    return $r;
                });
            }
            if (in_array('site_ai', $groups, true)) {
                $this->dumpTable('site_ai_settings', 'SELECT * FROM site_ai_settings ORDER BY setting_key', static function (array $r): ?array {
                    if (SitePack::isSecretKey((string) $r['setting_key'])) {
                        return null;
                    }
                    $j = json_decode((string) $r['setting_value'], true);
                    if (is_array($j)) {
                        $r['setting_value'] = json_encode(SitePack::scrubSecrets($j), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }
                    return $r;
                });
                $this->dumpTable('site_ai_sources', 'SELECT * FROM site_ai_sources ORDER BY id');
                $this->dumpTable('site_ai_chunks', 'SELECT * FROM site_ai_chunks ORDER BY source_id, chunk_index, id');
            }
            if (in_array('forms', $groups, true)) {
                $this->dumpTable('feedback_forms', 'SELECT * FROM feedback_forms ORDER BY id');
            }
            if (in_array('subscribers', $groups, true)) {
                $this->dumpTable('subscribers', 'SELECT * FROM subscribers ORDER BY id');
            }
            if (in_array('form_entries', $groups, true)) {
                $this->dumpTable('feedback_entries', 'SELECT * FROM feedback_entries ORDER BY id');
            }
            if (in_array('comments', $groups, true)) {
                $filter = static fn (array $r): ?array => ($contentIds === null || isset($contentIds[(int) $r['content_id']])) ? $r : null;
                $this->dumpTable('comments', 'SELECT * FROM comments ORDER BY id', $filter);
            }

            $manifest = $this->manifest($groups, $types, $opts);
            $name = $this->fileName($groups, $opts);
            $dest = (string) ($opts['out'] ?? '');
            if ($dest === '') {
                $dest = SitePack::exportsDir() . '/' . $name;
            } else {
                $name = basename($dest);
            }
            $this->writeZip($dest, $manifest);
            return ['path' => $dest, 'name' => $name, 'size' => (int) filesize($dest), 'manifest' => $manifest];
        } finally {
            self::rrmdir($this->tmp);
        }
    }

    private function exportContent(array $types): void
    {
        $in = $this->inList($types);
        $this->dumpTable('content_types', "SELECT * FROM content_types WHERE slug IN ($in) ORDER BY id");
        $this->dumpTable('content_fields', "SELECT f.* FROM content_fields f JOIN content_types t ON t.id = f.type_id WHERE t.slug IN ($in) ORDER BY f.type_id, f.sort_order, f.id");
        $this->dumpTable('content', "SELECT * FROM content WHERE type IN ($in) ORDER BY id", function (array $r): array {
            $this->featureScan((string) ($r['body'] ?? ''));
            if (!empty($r['template'])) {
                $this->bump('templates', (string) $r['template']);
            }
            return $r;
        });
        $this->dumpTable('content_meta', "SELECT m.* FROM content_meta m JOIN content c ON c.id = m.content_id WHERE c.type IN ($in) ORDER BY m.content_id, m.meta_key", function (array $r): array {
            $k = (string) $r['meta_key'];
            $v = (string) ($r['meta_value'] ?? '');
            if ($k === 'raw_css' && trim($v) !== '') {
                $this->features['custom_css_bytes'] = ($this->features['custom_css_bytes'] ?? 0) + strlen($v);
                $this->bump('page_custom_css', 'pages');
            } elseif ($k === 'raw_js' && trim($v) !== '') {
                $this->bump('page_custom_js', 'pages');
            } elseif ($k === 'page_layout' && $v !== '') {
                $this->bump('templates', 'layout:' . $v);
            } elseif ($k === 'wizard_kit' && $v !== '') {
                $this->bump('kits', $v);
            } elseif ($k === 'wizard_pack' && $v !== '') {
                $this->bump('kit_packs', $v);
            } elseif (in_array($k, ['header_assembly_slug', 'footer_assembly_slug'], true) && $v !== '') {
                $this->bump('assemblies_used', $v);
            }
            return $r;
        });
        $this->dumpTable('content_relations', "SELECT r.* FROM content_relations r JOIN content a ON a.id = r.content_id JOIN content b ON b.id = r.related_id WHERE a.type IN ($in) AND b.type IN ($in) ORDER BY r.content_id, r.id");
    }

    private function exportSettings(string $group): void
    {
        $file = 'data/settings.' . $group . '.json';
        $fh = $this->openData($file, ['table' => 'settings', 'group' => $group]);
        $n = 0;
        $rows = [];
        foreach (Database::fetchAll('SELECT setting_key, setting_value, updated_at FROM settings ORDER BY setting_key') as $r) {
            if (SitePack::settingGroup((string) $r['setting_key']) === $group) {
                $rows[] = $r;
            }
        }
        if ($group === 'design') {
            try {
                $site = Database::fetch('SELECT theme, updated_at FROM sites WHERE is_primary = 1 ORDER BY id LIMIT 1');
                if ($site && (string) $site['theme'] !== '') {
                    $rows[] = ['setting_key' => SitePack::SITE_THEME_KEY, 'setting_value' => (string) $site['theme'], 'updated_at' => $site['updated_at']];
                }
            } catch (\Throwable $e) {
            }
        }
        foreach ($rows as $r) {
            fwrite($fh, ($n ? ",\n" : '') . json_encode($r, self::JSON));
            $n++;
            $key = (string) $r['setting_key'];
            $val = (string) ($r['setting_value'] ?? '');
            $this->items['settings.' . $group][] = ['id' => $key, 'slug' => $key, 'type' => 'setting', 'updated_at' => $r['updated_at'] ?? null];
            if ($key === 'active_theme' || $key === SitePack::SITE_THEME_KEY) {
                $this->features['theme'] = $val;
            }
            if ($key === 'element_styles' || $key === 'design_variables') {
                $j = json_decode($val, true);
                $css = 0;
                is_array($j) && array_walk_recursive($j, static function ($v, $k) use (&$css) {
                    if ($k === 'custom_css' && is_string($v)) {
                        $css += strlen($v);
                    }
                });
                $this->features['custom_css_bytes'] = ($this->features['custom_css_bytes'] ?? 0) + ($key === 'design_variables' ? strlen($val) : $css);
            }
            if (in_array($key, ['header_assembly_slug', 'footer_assembly_slug'], true) && $val !== '') {
                $this->bump('assemblies_used', $val);
            }
            // Logos, favicons and images referenced by settings travel along as loose media files.
            if (preg_match_all('~/mova-uploads/([A-Za-z0-9._@+()/ -]+\.[A-Za-z0-9]{2,5})~', $val, $m)) {
                foreach ($m[1] as $rel) {
                    if (SitePack::safeRelPath($rel) !== null && SitePack::mediaExtAllowed($rel)) {
                        $this->addMediaFile($rel, true);
                    }
                }
            }
        }
        $this->closeData($fh, $file);
        $this->counts['settings.' . $group] = $n;
    }

    // ── streaming JSON writers ───────────────────────────────────────────

    /** @return resource */
    private function openData(string $file, array $head)
    {
        $fh = fopen($this->tmp . '/' . $file, 'wb');
        if (!$fh) {
            throw new \RuntimeException('Cannot write temp file in storage/exports.');
        }
        $h = json_encode($head, self::JSON);
        fwrite($fh, substr($h, 0, -1) . ',"rows":[' . "\n");
        return $fh;
    }

    /** @param resource $fh */
    private function closeData($fh, string $file): void
    {
        fwrite($fh, "\n]}\n");
        fclose($fh);
        $path = $this->tmp . '/' . $file;
        $this->files[$file] = ['sha256' => (string) hash_file('sha256', $path), 'size' => (int) filesize($path), 'src' => $path];
    }

    private function dumpTable(string $table, string $sql, ?callable $map = null): void
    {
        if (!SitePack::tableExists($table)) {
            return;
        }
        $rules = SitePack::tables()[$table] ?? [];
        $drop = array_flip((array) ($rules['drop'] ?? []));
        $cols = array_values(array_filter(SitePack::tableColumns($table), static fn ($c) => !isset($drop[$c])));
        $file = 'data/' . $table . '.json';
        $fh = $this->openData($file, ['table' => $table, 'columns' => $cols]);
        $n = 0;
        $stmt = Database::query($sql);
        $key = (array) ($rules['key'] ?? []);
        $titleCol = (string) ($rules['title'] ?? '');
        while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
            foreach ($drop as $d => $_) {
                unset($row[$d]);
            }
            if ($map) {
                $row = $map($row);
                if ($row === null) {
                    continue;
                }
            }
            fwrite($fh, ($n ? ",\n" : '') . json_encode($row, self::JSON));
            $n++;
            if ($key !== [] && isset($row['id'])) {
                $item = ['id' => (int) $row['id'], 'slug' => implode('|', array_map(static fn ($k) => (string) ($row[$k] ?? ''), array_diff($key, ['type'])))];
                if (isset($row['type'])) {
                    $item['type'] = (string) $row['type'];
                }
                $item['updated_at'] = $row['updated_at'] ?? ($row['created_at'] ?? null);
                if ($titleCol !== '' && isset($row[$titleCol])) {
                    $item['title'] = mb_substr((string) $row[$titleCol], 0, 120);
                }
                $this->items[$table][] = $item;
            }
        }
        $stmt->closeCursor();
        $this->closeData($fh, $file);
        $this->counts[$table] = $n;
    }

    /** @return array<int,true> */
    private function idsOf(string $table): array
    {
        $out = [];
        foreach ($this->items[$table] ?? [] as $it) {
            $out[(int) $it['id']] = true;
        }
        return $out;
    }

    private function addMediaFile(string $rel, bool $loose = false): void
    {
        $name = 'media/' . $rel;
        if (isset($this->media[$name])) {
            return;
        }
        $abs = Bootstrap::path('uploads') . '/' . $rel;
        if (!is_file($abs) || is_link($abs)) {
            $this->warnings[] = 'Missing media file skipped: ' . $rel;
            return;
        }
        if (!SitePack::mediaExtAllowed($rel)) {
            $this->warnings[] = 'Media file type not allowed in packs, skipped: ' . $rel;
            return;
        }
        $this->media[$name] = $abs;
        $this->files[$name] = ['sha256' => (string) hash_file('sha256', $abs), 'size' => (int) filesize($abs), 'src' => $abs];
        if ($loose) {
            $this->features['loose_files'][] = $rel;
        }
    }

    // ── features inventory ───────────────────────────────────────────────

    private function bump(string $bucket, string $name): void
    {
        $this->features[$bucket][$name] = ($this->features[$bucket][$name] ?? 0) + 1;
    }

    private function featureScan(string $html): void
    {
        if ($html === '') {
            return;
        }
        if (preg_match_all('/\bdata-mx="([a-z0-9_-]+)"/i', $html, $m)) {
            foreach ($m[1] as $s) {
                $this->bump('sections', strtolower($s));
            }
        }
        if (preg_match_all('/\bmx-style--([a-z0-9_-]+)/i', $html, $m)) {
            foreach ($m[1] as $s) {
                $this->bump('styles', strtolower($s));
            }
        }
        if (preg_match_all('/\bdata-(?:template|tpl|mova-template)="([a-z0-9_.:-]+)"/i', $html, $m)) {
            foreach ($m[1] as $s) {
                $this->bump('design_templates_used', $s);
            }
        }
        if (preg_match_all('/\bdata-(?:component|block|mova-block)="([a-z0-9_.:-]+)"/i', $html, $m)) {
            foreach ($m[1] as $s) {
                $this->bump('components', $s);
            }
        }
        if (preg_match_all('/\[(gallery|blog|feedback|form|embed|video|button|columns?)\b/i', $html, $m)) {
            foreach ($m[1] as $s) {
                $this->bump('shortcodes', strtolower($s));
            }
        }
        if (preg_match_all('/<!--\s*wp:([a-z0-9\/_-]+)/i', $html, $m)) {
            foreach ($m[1] as $s) {
                $this->bump('blocks', strtolower($s));
            }
        }
    }

    private function manifest(array $groups, array $types, array $opts): array
    {
        $setting = static function (string $k): string {
            try {
                return (string) (Database::fetch('SELECT setting_value FROM settings WHERE setting_key = :k', ['k' => $k])['setting_value'] ?? '');
            } catch (\Throwable $e) {
                return '';
            }
        };
        $plugins = [];
        try {
            foreach (Database::fetchAll("SELECT slug, version, status FROM plugins ORDER BY slug") as $p) {
                $plugins[] = ['slug' => (string) $p['slug'], 'version' => (string) $p['version'], 'status' => (string) $p['status']];
            }
        } catch (\Throwable $e) {
        }
        $f = $this->features;
        $kit = '';
        $draft = json_decode($setting('setup_wizard_draft_1'), true);
        if (is_array($draft) && !empty($draft['kit']) && is_string($draft['kit'])) {
            $kit = $draft['kit'];
        }
        if ($kit === '' && !empty($f['kits'])) {
            arsort($f['kits']);
            $kit = (string) array_key_first($f['kits']);
        }
        $theme = (string) ($f['theme'] ?? $setting('active_theme'));
        foreach (['sections', 'styles', 'templates', 'components', 'shortcodes', 'blocks', 'assemblies_used', 'design_templates_used', 'kits', 'kit_packs'] as $b) {
            if (isset($f[$b])) {
                ksort($f[$b]);
            }
        }
        $ver = SitePack::version();
        $min = trim((string) ($opts['min_version'] ?? ''));
        $files = [];
        foreach ($this->files as $name => $info) {
            $files[$name] = ['sha256' => $info['sha256'], 'size' => $info['size']];
        }
        ksort($files);
        return [
            'format' => SitePack::FORMAT,
            'pack_format' => SitePack::PACK_FORMAT,
            'mova_version' => $ver,
            'min_mova_version' => preg_match('/^\d+(\.\d+){0,3}([-+][0-9A-Za-z.-]+)?$/', $min) ? $min : $ver,
            'site' => ['name' => $setting('site_name') ?: (string) Bootstrap::config('app_name', 'Mova'), 'host' => $setting('site_host') ?: (string) ($_SERVER['HTTP_HOST'] ?? '')],
            'exported_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'label' => mb_substr(trim((string) ($opts['label'] ?? '')), 0, 120),
            'groups' => $groups,
            'content_types' => in_array('content', $groups, true) ? $types : [],
            'counts' => $this->counts,
            'items' => $this->items,
            'features' => [
                'theme' => $theme,
                'kit' => $kit,
                'plugins' => $plugins,
                'sections' => $f['sections'] ?? [],
                'section_styles' => $f['styles'] ?? [],
                'templates' => $f['templates'] ?? [],
                'design_templates_used' => $f['design_templates_used'] ?? [],
                'components' => $f['components'] ?? [],
                'assemblies_used' => $f['assemblies_used'] ?? [],
                'shortcodes' => $f['shortcodes'] ?? [],
                'blocks' => $f['blocks'] ?? [],
                'custom_css_bytes' => (int) ($f['custom_css_bytes'] ?? 0),
                'pages_with_custom_css' => (int) ($f['page_custom_css']['pages'] ?? 0),
                'pages_with_custom_js' => (int) ($f['page_custom_js']['pages'] ?? 0),
                'kits_used' => $f['kits'] ?? [],
                'loose_files' => array_values(array_unique($f['loose_files'] ?? [])),
            ],
            'warnings' => $this->warnings,
            'files' => $files,
        ];
    }

    private function fileName(array $groups, array $opts): string
    {
        $site = '';
        try {
            $site = (string) (Database::fetch("SELECT setting_value FROM settings WHERE setting_key = 'site_name'")['setting_value'] ?? '');
        } catch (\Throwable $e) {
        }
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($site ?: 'mova-site')), '-') ?: 'site';
        $slug = substr($slug, 0, 40);
        $scope = count($groups) >= count(SitePack::defaultGroups()) ? 'full' : implode('-', array_slice($groups, 0, 3)) . (count($groups) > 3 ? '-more' : '');
        return $slug . '-' . $scope . '-' . date('Ymd-His') . '.' . SitePack::EXT;
    }

    private function writeZip(string $dest, array $manifest): void
    {
        $part = $dest . '.part';
        @unlink($part);
        $zip = new \ZipArchive();
        if ($zip->open($part, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create the pack file in storage/exports.');
        }
        $zip->addFromString('manifest.json', json_encode($manifest, self::JSON | JSON_PRETTY_PRINT));
        $zip->setCompressionName('manifest.json', \ZipArchive::CM_DEFLATE);
        foreach ($this->files as $name => $info) {
            $zip->addFile($info['src'], $name);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $zip->setCompressionName($name, in_array($ext, self::STORE_EXT, true) ? \ZipArchive::CM_STORE : \ZipArchive::CM_DEFLATE);
        }
        $zip->setArchiveComment('Mova site pack — format ' . SitePack::PACK_FORMAT);
        if (!$zip->close()) {
            @unlink($part);
            throw new \RuntimeException('Writing the pack failed (disk full?).');
        }
        if (!@rename($part, $dest)) {
            @unlink($part);
            throw new \RuntimeException('Cannot move the pack into place.');
        }
    }

    private function inList(array $vals): string
    {
        if ($vals === []) {
            return "''";
        }
        $pdo = Database::connection();
        return implode(',', array_map(static fn ($v) => $pdo->quote((string) $v), $vals));
    }

    public static function rrmdir(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    /** Stored exports (storage/exports), newest first. */
    public static function listExports(): array
    {
        $out = [];
        foreach (glob(SitePack::exportsDir() . '/*.' . SitePack::EXT) ?: [] as $p) {
            $out[] = ['name' => basename($p), 'path' => $p, 'size' => (int) filesize($p), 'mtime' => (int) filemtime($p)];
        }
        usort($out, static fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
        return $out;
    }
}
