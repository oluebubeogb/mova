<?php
/**
 * Mova — Assembly: synced code-native components
 * HTML/CSS/(optional JS), shortcode embeds, XSS-tight rendering
 */

declare(strict_types=1);

namespace Mova\Assembly;

use Mova\Core\Database;

class AssemblyService
{
    public const STATUSES = ['draft', 'published', 'archived'];

    public function ensureTable(): void
    {
        // Safety for installs that have not re-run Schema::migrate yet
        try {
            Database::connection()->exec("
                CREATE TABLE IF NOT EXISTS assemblies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    site_id INTEGER,
                    slug TEXT NOT NULL,
                    name TEXT NOT NULL,
                    description TEXT,
                    html TEXT NOT NULL DEFAULT '',
                    css TEXT NOT NULL DEFAULT '',
                    js TEXT NOT NULL DEFAULT '',
                    scripts_enabled INTEGER NOT NULL DEFAULT 0,
                    css_global INTEGER NOT NULL DEFAULT 0,
                    status TEXT NOT NULL DEFAULT 'draft',
                    revision INTEGER NOT NULL DEFAULT 1,
                    checksum TEXT,
                    created_by INTEGER,
                    updated_by INTEGER,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    published_at TEXT
                )
            ");
            Database::connection()->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_assemblies_slug ON assemblies(slug)");
            Database::connection()->exec("CREATE INDEX IF NOT EXISTS idx_assemblies_status ON assemblies(status)");
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function all(?string $status = null, string $q = ''): array
    {
        $this->ensureTable();
        $sql = "SELECT * FROM assemblies WHERE 1=1";
        $params = [];
        if ($status !== null && $status !== '' && $status !== 'all') {
            $sql .= " AND status = :st";
            $params['st'] = $status;
        }
        if ($q !== '') {
            $sql .= " AND (name LIKE :q OR slug LIKE :q2 OR description LIKE :q3)";
            $like = '%' . $q . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        $sql .= " ORDER BY updated_at DESC";
        return Database::fetchAll($sql, $params);
    }

    public function published(string $q = ''): array
    {
        return $this->all('published', $q);
    }

    public function find(int $id): ?array
    {
        $this->ensureTable();
        return Database::fetch("SELECT * FROM assemblies WHERE id = :id", ['id' => $id]);
    }

    public function findBySlug(string $slug): ?array
    {
        $this->ensureTable();
        $slug = $this->normalizeSlug($slug);
        if ($slug === '') {
            return null;
        }
        return Database::fetch("SELECT * FROM assemblies WHERE slug = :s", ['s' => $slug]);
    }

    public function create(array $data, ?int $userId = null): int
    {
        $this->ensureTable();
        $now = date('c');
        $name = trim((string) ($data['name'] ?? 'Untitled assembly'));
        if ($name === '') {
            $name = 'Untitled assembly';
        }
        $slug = $this->normalizeSlug((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->normalizeSlug($name);
        }
        if ($slug === '') {
            $slug = 'assembly-' . substr(bin2hex(random_bytes(4)), 0, 8);
        }
        $slug = $this->uniqueSlug($slug);

        $html = (string) ($data['html'] ?? '');
        $css = (string) ($data['css'] ?? '');
        $js = (string) ($data['js'] ?? '');
        $status = $this->normalizeStatus((string) ($data['status'] ?? 'draft'));

        return Database::insert('assemblies', [
            'site_id' => $data['site_id'] ?? null,
            'slug' => $slug,
            'name' => $name,
            'description' => trim((string) ($data['description'] ?? '')),
            'html' => $html,
            'css' => $css,
            'js' => $js,
            'scripts_enabled' => !empty($data['scripts_enabled']) ? 1 : 0,
            'css_global' => !empty($data['css_global']) ? 1 : 0,
            'status' => $status,
            'revision' => 1,
            'checksum' => $this->checksum($html, $css, $js),
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
            'published_at' => $status === 'published' ? $now : null,
        ]);
    }

    public function update(int $id, array $data, ?int $userId = null, bool $publish = false): bool
    {
        $row = $this->find($id);
        if (!$row) {
            return false;
        }

        $name = array_key_exists('name', $data) ? trim((string) $data['name']) : $row['name'];
        if ($name === '') {
            $name = $row['name'];
        }

        $slug = $row['slug'];
        if (array_key_exists('slug', $data)) {
            $newSlug = $this->normalizeSlug((string) $data['slug']);
            if ($newSlug !== '' && $newSlug !== $row['slug']) {
                $slug = $this->uniqueSlug($newSlug, $id);
            }
        }

        $html = array_key_exists('html', $data) ? (string) $data['html'] : $row['html'];
        $css = array_key_exists('css', $data) ? (string) $data['css'] : $row['css'];
        $js = array_key_exists('js', $data) ? (string) $data['js'] : $row['js'];

        $status = array_key_exists('status', $data)
            ? $this->normalizeStatus((string) $data['status'])
            : $row['status'];

        if ($publish) {
            $status = 'published';
        }

        $revision = (int) ($row['revision'] ?? 1);
        // Bump revision only when publishing a new head
        if ($publish) {
            $revision = $revision + 1;
        }

        $payload = [
            'name' => $name,
            'slug' => $slug,
            'description' => array_key_exists('description', $data)
                ? trim((string) $data['description'])
                : ($row['description'] ?? ''),
            'html' => $html,
            'css' => $css,
            'js' => $js,
            'scripts_enabled' => array_key_exists('scripts_enabled', $data)
                ? (!empty($data['scripts_enabled']) ? 1 : 0)
                : (int) ($row['scripts_enabled'] ?? 0),
            'css_global' => array_key_exists('css_global', $data)
                ? (!empty($data['css_global']) ? 1 : 0)
                : (int) ($row['css_global'] ?? 0),
            'status' => $status,
            'revision' => $revision,
            'checksum' => $this->checksum($html, $css, $js),
            'updated_by' => $userId,
            'updated_at' => date('c'),
        ];

        if ($status === 'published' && empty($row['published_at'])) {
            $payload['published_at'] = date('c');
        }
        if ($publish) {
            $payload['published_at'] = date('c');
        }

        return Database::update('assemblies', $payload, 'id = :id', ['id' => $id]) >= 0;
    }

    public function duplicate(int $id, ?int $userId = null): ?int
    {
        $row = $this->find($id);
        if (!$row) {
            return null;
        }
        return $this->create([
            'name' => $row['name'] . ' (copy)',
            'slug' => $row['slug'] . '-copy',
            'description' => $row['description'] ?? '',
            'html' => $row['html'] ?? '',
            'css' => $row['css'] ?? '',
            'js' => $row['js'] ?? '',
            'scripts_enabled' => 0,
            'css_global' => (int) ($row['css_global'] ?? 0),
            'status' => 'draft',
        ], $userId);
    }

    public function archive(int $id, ?int $userId = null): bool
    {
        return $this->update($id, ['status' => 'archived'], $userId);
    }

    public function delete(int $id): bool
    {
        $this->ensureTable();
        return Database::delete('assemblies', 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * Expand shortcodes in content body.
     * Supports: [assembly slug="my-slug"] and {{assembly:my-slug}}
     */
    public function expandShortcodes(string $body): string
    {
        if ($body === '' || (strpos($body, '[assembly') === false && strpos($body, '{{assembly:') === false)) {
            return $body;
        }

        // Normalize {{assembly:slug}} → [assembly slug="slug"]
        $body = preg_replace_callback(
            '/\{\{\s*assembly\s*:\s*([a-z0-9\-]+)\s*\}\}/i',
            static function (array $m): string {
                return '[assembly slug="' . strtolower($m[1]) . '"]';
            },
            $body
        ) ?? $body;

        // Group consecutive shortcodes on the same "line" (only whitespace between)
        // into a flex row so they sit side-by-side on the front-end.
        $body = preg_replace_callback(
            '/(?:\[assembly\s+[^\]]*\]\s*){2,}/i',
            function (array $m): string {
                $chunk = $m[0];
                if (!preg_match_all('/\[assembly\s+([^\]]*)\]/i', $chunk, $parts)) {
                    return $chunk;
                }
                $html = [];
                foreach ($parts[1] as $attrs) {
                    $html[] = $this->renderFromAttrString($attrs);
                }
                if (count($html) < 2) {
                    return implode('', $html);
                }
                return $this->layoutCssOnce()
                    . '<div class="mova-assembly-row">' . implode('', $html) . '</div>';
            },
            $body
        ) ?? $body;

        // Remaining single shortcodes
        $body = preg_replace_callback(
            '/\[assembly\s+([^\]]*)\]/i',
            function (array $m): string {
                return $this->layoutCssOnce() . $this->renderFromAttrString($m[1]);
            },
            $body
        ) ?? $body;

        return $body;
    }

    public function renderFromAttrString(string $attrString): string
    {
        $slug = '';
        if (preg_match('/\bslug\s*=\s*["\']([a-z0-9\-]+)["\']/i', $attrString, $m)) {
            $slug = strtolower($m[1]);
        } elseif (preg_match('/\bslug\s*=\s*([a-z0-9\-]+)/i', $attrString, $m)) {
            $slug = strtolower($m[1]);
        }
        if ($slug === '') {
            return '<!-- assembly: invalid shortcode -->';
        }
        return $this->renderBySlug($slug);
    }

    public function renderBySlug(string $slug): string
    {
        $row = $this->findBySlug($slug);
        if (!$row || ($row['status'] ?? '') !== 'published') {
            return '<!-- assembly unavailable: ' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . ' -->';
        }
        return $this->renderRow($row);
    }

    public function renderRow(array $row, bool $forPreview = false): string
    {
        $slug = $this->normalizeSlug((string) ($row['slug'] ?? 'item'));
        if ($slug === '') {
            $slug = 'item';
        }
        $safeClass = 'mova-assembly--' . preg_replace('/[^a-z0-9\-]/', '', $slug);

        $html = $this->sanitizeHtml((string) ($row['html'] ?? ''));
        $css = (string) ($row['css'] ?? '');
        $global = !empty($row['css_global']);
        $revision = (int) ($row['revision'] ?? 1);

        $styleBlock = '';
        if (trim($css) !== '') {
            $scoped = $global ? $this->sanitizeCss($css) : $this->scopeCss($this->sanitizeCss($css), $safeClass);
            if ($scoped !== '') {
                $styleBlock = "<style data-assembly-css=\"{$safeClass}\">\n{$scoped}\n</style>\n";
            }
        }

        $rev = htmlspecialchars((string) $revision, ENT_QUOTES, 'UTF-8');
        $slugAttr = htmlspecialchars($slug, ENT_QUOTES, 'UTF-8');

        return $styleBlock
            . '<div class="mova-assembly ' . $safeClass . '" data-assembly="' . $slugAttr . '" data-revision="' . $rev . '">'
            . $html
            . '</div>';
    }

    public function embedCode(string $slug): string
    {
        $slug = $this->normalizeSlug($slug);
        return '[assembly slug="' . $slug . '"]';
    }

    public function usageCount(string $slug): int
    {
        $slug = $this->normalizeSlug($slug);
        if ($slug === '') {
            return 0;
        }
        try {
            $needle = '%[assembly slug="' . $slug . '"%';
            $needle2 = '%{{assembly:' . $slug . '}}%';
            $row = Database::fetch(
                "SELECT COUNT(*) AS c FROM content WHERE body LIKE :a OR body LIKE :b",
                ['a' => $needle, 'b' => $needle2]
            );
            return (int) ($row['c'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function normalizeSlug(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        return substr($s, 0, 80);
    }

    private function uniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $base = $slug;
        $i = 2;
        while (true) {
            $row = Database::fetch("SELECT id FROM assemblies WHERE slug = :s", ['s' => $slug]);
            if (!$row || ($excludeId !== null && (int) $row['id'] === $excludeId)) {
                return $slug;
            }
            $slug = $base . '-' . $i;
            $i++;
            if ($i > 50) {
                return $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            }
        }
    }

    private function normalizeStatus(string $s): string
    {
        $s = strtolower(trim($s));
        return in_array($s, self::STATUSES, true) ? $s : 'draft';
    }

    private function checksum(string $html, string $css, string $js): string
    {
        return hash('sha256', $html . "\n" . $css . "\n" . $js);
    }

    /** Tight HTML allowlist — no scripts, handlers, or dangerous URLs */
    public function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<(script|iframe|object|embed|form|link|meta|base|svg)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(script|iframe|object|embed|form|link|meta|base)\b[^>]*/?>#is', '', $html) ?? $html;
        // Event handlers and javascript: URLs
        $html = preg_replace('/\son[a-z]+\s*=\s*("|\').*?\1/iu', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*[^\s>]+/iu', '', $html) ?? $html;
        $html = preg_replace('/(href|src)\s*=\s*("|\'?)\s*javascript:[^"\'>\s]*/iu', '$1="#"', $html) ?? $html;
        $html = preg_replace('/style\s*=\s*("|\').*?expression\s*\(.*?\1/iu', '', $html) ?? $html;
        return $html;
    }

    public function sanitizeCss(string $css): string
    {
        $css = preg_replace('/@import\b[^;]*;/i', '', $css) ?? $css;
        $css = preg_replace('/expression\s*\(/i', 'blocked(', $css) ?? $css;
        $css = preg_replace('/-moz-binding\s*:/i', 'blocked:', $css) ?? $css;
        $css = preg_replace('/behavior\s*:/i', 'blocked:', $css) ?? $css;
        $css = preg_replace('/javascript\s*:/i', 'blocked:', $css) ?? $css;
        return $css;
    }

    public function sanitizeJs(string $js): string
    {
        // JS field should be pure script — strip HTML tags if pasted
        $js = preg_replace('#</?script\b[^>]*>#i', '', $js) ?? $js;
        return $js;
    }

    /**
     * Prefix selectors so assembly CSS does not bleed into the page.
     */
    public function scopeCss(string $css, string $rootClass): string
    {
        $root = '.' . $rootClass;
        $out = '';
        $len = strlen($css);
        $i = 0;
        $buf = '';
        $inStr = false;
        $strQ = '';
        $depth = 0;

        while ($i < $len) {
            $ch = $css[$i];
            if ($inStr) {
                $buf .= $ch;
                if ($ch === $strQ && ($i === 0 || $css[$i - 1] !== '\\')) {
                    $inStr = false;
                }
                $i++;
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $inStr = true;
                $strQ = $ch;
                $buf .= $ch;
                $i++;
                continue;
            }
            if ($ch === '{') {
                if ($depth === 0) {
                    $selector = trim($buf);
                    $buf = '';
                    if ($selector !== '' && strpos($selector, '@') !== 0) {
                        $parts = array_map('trim', explode(',', $selector));
                        $scoped = [];
                        foreach ($parts as $p) {
                            if ($p === '') {
                                continue;
                            }
                            if (strpos($p, $root) === 0) {
                                $scoped[] = $p;
                            } elseif ($p === ':root' || $p === 'body' || $p === 'html') {
                                $scoped[] = $root;
                            } else {
                                $scoped[] = $root . ' ' . $p;
                            }
                        }
                        $out .= implode(', ', $scoped) . ' {';
                    } else {
                        // @media etc. — pass through, still try to scope inner later (simple pass)
                        $out .= $selector . ' {';
                    }
                } else {
                    $out .= $buf . '{';
                    $buf = '';
                }
                $depth++;
                $i++;
                continue;
            }
            if ($ch === '}') {
                $out .= $buf . '}';
                $buf = '';
                $depth = max(0, $depth - 1);
                $i++;
                continue;
            }
            $buf .= $ch;
            $i++;
        }
        $out .= $buf;
        return $out;
    }

    /**
     * Shared layout CSS once per response: side-by-side rows + no overflow.
     */
    private function layoutCssOnce(): string
    {
        static $done = false;
        if ($done) {
            return '';
        }
        $done = true;
        return '<style data-mova-assembly-layout>'
            . '.mova-assembly-row{display:flex;flex-wrap:wrap;align-items:flex-start;gap:1rem;width:100%;max-width:100%;box-sizing:border-box;}'
            . '.mova-assembly-row>.mova-assembly{flex:1 1 auto;min-width:0;max-width:100%;width:max-content;box-sizing:border-box;overflow-x:auto;}'
            . '.mova-assembly{max-width:100%;box-sizing:border-box;overflow-x:auto;}'
            . '.mova-assembly img,.mova-assembly svg,.mova-assembly video,.mova-assembly iframe,.mova-assembly table{max-width:100%;height:auto;}'
            . '.mova-assembly table{display:block;overflow-x:auto;}'
            . '</style>';
    }

    /** Register content.render.body filter once */
    public static function registerRenderFilter(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!class_exists(\Mova\Plugin\PluginManager::class)) {
            return;
        }
        \Mova\Plugin\PluginManager::addFilter('content.render.body', function (string $body, array $content = []) {
            try {
                $svc = new AssemblyService();
                return $svc->expandShortcodes($body);
            } catch (\Throwable $e) {
                return $body;
            }
        }, 20);
    }
}
