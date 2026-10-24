<?php
/**
 * Site packs (.movapack) — shared spec for export and import.
 *
 * A site pack is a zip with manifest.json, one JSON file per table/settings group under data/,
 * and media files under media/ (same relative paths as in mova-uploads). It carries site content and
 * design only: users, API keys, credentials, sessions, logs, analytics and every secret setting are
 * never exported and never imported (there is no option to include them).
 */

declare(strict_types=1);

namespace Mova\Backup;

use Mova\Core\Bootstrap;
use Mova\Core\Database;

final class SitePack
{
    public const FORMAT = 'movapack';
    public const PACK_FORMAT = 1;
    public const EXT = 'movapack';

    /** Tables that must never be exported or imported, whatever a pack says. */
    public const FORBIDDEN_TABLES = [
        'users', 'api_keys', 'api_rate_limits', 'sessions', 'login_history', 'login_attempts', 'trusted_devices',
        'mail_accounts', 'mail_contacts', 'mail_filters', 'mail_sequences', 'mail_sequence_steps', 'mail_suppressions',
        'integrations', 'webhooks', 'webhook_deliveries', 'page_views', 'audit_logs', 'ai_jobs', 'ai_messages',
        'ai_sessions', 'ai_usage', 'hq_search', 'plugins', 'site_ai_sessions', 'site_ai_messages', 'site_ai_feedback',
        'site_ai_index', 'content_fts', 'find_replace_actions', 'find_replace_changes', 'content_assignments',
        'content_notes', 'content_reviews', 'revisions', 'sites',
    ];

    /** Exact setting keys that are secret or credentials (denylist). The pattern below catches the rest. */
    public const SECRET_SETTING_KEYS = [
        'ai_api_key', 'ai_img_api_key', 'ai_key', 'ai_worker_secret', 'openai_api_key', 'anthropic_api_key',
        'gateway_key', 'gateway_token', 'smtp_password', 'smtp_pass', 'smtp_username', 'smtp_user', 'mail_password',
        'imap_password', 'recaptcha_secret', 'recaptcha_site_key', 'turnstile_secret', 'hcaptcha_secret',
        'stripe_secret_key', 'stripe_webhook_secret', 'paystack_secret_key', 'flutterwave_secret_key',
        'webhook_secret', 'api_token', 'github_token', 'update_token', 'license_key', 'cron_secret', 'jwt_secret',
        'app_key', 'encryption_key', 'session_secret', 'totp_secret', 'backup_password',
    ];

    /** Anything whose key matches this is treated as a secret (never exported, never imported). */
    public const SECRET_PATTERN = '/(key|secret|token|passw|pwd|credential|auth|salt|private|signature|cookie|session|dsn|license|bearer)/i';

    /** Internal / environment / volatile settings — not site content, never exported or imported. */
    public const INTERNAL_PREFIXES = [
        'ai_', 'hq_search', 'site_ai_index', 'setup_wizard_draft', 'last_backup', 'installed_at', 'site_uuid', 'site_host',
        'site_url', 'app_url', 'pwa_rev', 'mail_', 'smtp_', 'imap_', 'webhook', 'integration', 'login_', 'security_',
        'totp', 'cron_', 'update_', 'updates_', 'maintenance', 'health_', 'dashboard_', 'debug', 'gateway',
    ];

    public const MENU_KEYS = [
        'nav_links', 'nav_links_desktop', 'nav_cta', 'footer_columns', 'footer_text', 'header_assembly_slug',
        'footer_assembly_slug', 'mobile_menu_icon_mode', 'mobile_menu_icon_preset', 'mobile_menu_icon_url',
        'mobile_menu_icon_custom',
    ];
    public const MENU_PREFIXES = ['nav_', 'menu_', 'footer_', 'header_nav', 'mobile_menu'];

    public const DESIGN_KEYS = [
        'active_theme', 'design_tokens', 'layout_config', 'component_variants', 'motion_config', 'element_styles',
        'design_variables', 'brand_color', 'logo_url', 'logo_url_dark', 'favicon_url', 'header_brand_mode',
        'default_og_image',
    ];
    public const DESIGN_PREFIXES = ['design_', 'theme_', 'brand_', 'logo_', 'style_kit', 'kit_'];

    /** Pseudo setting carrying sites.theme for the primary site (design group). */
    public const SITE_THEME_KEY = '__site_theme';

    /**
     * Group definitions. tables: identity tables (have id + natural key); children: rows replaced per parent.
     * private: off by default (personal data).
     */
    public static function groups(): array
    {
        return [
            'content' => ['label' => 'Pages & content', 'icon' => 'fa-solid fa-file-lines',
                'desc' => 'Pages, posts and other content with SEO/meta fields, relations and custom content types.',
                'tables' => ['content_types', 'content'], 'children' => ['content_fields', 'content_meta', 'content_relations']],
            'blogs' => ['label' => 'Blogs', 'icon' => 'fa-solid fa-newspaper',
                'desc' => 'Blog feeds and their hand-picked items.', 'tables' => ['blogs'], 'children' => ['blog_items']],
            'galleries' => ['label' => 'Galleries', 'icon' => 'fa-solid fa-images',
                'desc' => 'Galleries and their items (pair with Media so the images come along).', 'tables' => ['galleries'], 'children' => ['gallery_items']],
            'media' => ['label' => 'Media library', 'icon' => 'fa-solid fa-photo-film',
                'desc' => 'Media rows plus the files (originals and generated sizes).', 'tables' => ['media'], 'children' => []],
            'menus' => ['label' => 'Menus & navigation', 'icon' => 'fa-solid fa-bars',
                'desc' => 'Header links, call-to-action, footer columns and header/footer assembly choice.', 'tables' => [], 'children' => [], 'settings' => true],
            'taxonomy' => ['label' => 'Categories & tags', 'icon' => 'fa-solid fa-tags',
                'desc' => 'Categories, tags and which content they are assigned to.', 'tables' => ['categories', 'tags'], 'children' => ['content_tags', 'content_categories']],
            'design' => ['label' => 'Design', 'icon' => 'fa-solid fa-palette',
                'desc' => 'Theme, design tokens, layout, element styles and custom CSS, brand/logo, design templates and assemblies.',
                'tables' => ['design_templates', 'assemblies'], 'children' => [], 'settings' => true],
            'settings' => ['label' => 'Site settings', 'icon' => 'fa-solid fa-sliders',
                'desc' => 'Non-secret settings such as site name, description, homepage and PWA options. Keys, tokens and passwords are never included.',
                'tables' => [], 'children' => [], 'settings' => true],
            'site_ai' => ['label' => 'Site AI knowledge', 'icon' => 'fa-solid fa-robot',
                'desc' => 'Site assistant appearance plus its knowledge sources and chunks (no chats, no provider keys).',
                'tables' => ['site_ai_sources'], 'children' => ['site_ai_chunks'], 'kv' => 'site_ai_settings'],
            'forms' => ['label' => 'Forms', 'icon' => 'fa-solid fa-clipboard-list',
                'desc' => 'Feedback/contact form definitions (not the entries).', 'tables' => ['feedback_forms'], 'children' => []],
            'subscribers' => ['label' => 'Subscribers', 'icon' => 'fa-solid fa-user-group', 'private' => true,
                'desc' => 'Email subscribers (personal data). Unsubscribe tokens are never included.', 'tables' => ['subscribers'], 'children' => []],
            'form_entries' => ['label' => 'Form entries', 'icon' => 'fa-solid fa-inbox', 'private' => true,
                'desc' => 'Submitted form entries (personal data). IP hashes and browser strings are dropped.', 'tables' => ['feedback_entries'], 'children' => []],
            'comments' => ['label' => 'Comments', 'icon' => 'fa-solid fa-comments', 'private' => true,
                'desc' => 'Visitor comments (personal data). IP addresses and browser strings are dropped.', 'tables' => ['comments'], 'children' => []],
        ];
    }

    /** Default groups ticked in the export form (all non-private). */
    public static function defaultGroups(): array
    {
        return array_keys(array_filter(self::groups(), static fn ($g) => empty($g['private'])));
    }

    /**
     * Table rules. key: natural key columns (match "slug+type"). parent: [table, fk column] for children.
     * drop: columns never exported. keep: columns kept from the target row on update (site-local references).
     * nullable_users: user FK columns set to null/current user when the user does not exist here.
     */
    public static function tables(): array
    {
        return [
            'content_types' => ['key' => ['slug']],
            'content' => ['key' => ['slug', 'type'], 'keep' => ['author_id'], 'users' => ['author_id'], 'title' => 'title'],
            'content_fields' => ['parent' => ['content_types', 'type_id'], 'sig' => ['name', 'slug', 'field_type', 'options', 'is_required', 'sort_order']],
            'content_meta' => ['parent' => ['content', 'content_id'], 'sig' => ['meta_key', 'meta_value']],
            'content_relations' => ['parent' => ['content', 'content_id'], 'sig' => ['related_id', 'relation_type', 'sort_order'], 'refs' => ['related_id' => 'content']],
            'blogs' => ['key' => ['slug'], 'title' => 'title'],
            'blog_items' => ['parent' => ['blogs', 'blog_id'], 'sig' => ['content_id', 'sort_order'], 'refs' => ['content_id' => 'content']],
            'galleries' => ['key' => ['slug'], 'title' => 'title', 'refs_null' => ['cover_media_id' => 'media']],
            'gallery_items' => ['parent' => ['galleries', 'gallery_id'], 'sig' => ['media_id', 'caption', 'sort_order'], 'refs' => ['media_id' => 'media']],
            'media' => ['key' => ['path'], 'keep' => ['uploaded_by'], 'users' => ['uploaded_by'], 'title' => 'original_name'],
            'categories' => ['key' => ['slug'], 'title' => 'name', 'refs_null' => ['parent_id' => 'categories']],
            'tags' => ['key' => ['slug'], 'title' => 'name'],
            'content_tags' => ['parent' => ['content', 'content_id'], 'sig' => ['tag_id'], 'refs' => ['tag_id' => 'tags'], 'noid' => true],
            'content_categories' => ['parent' => ['content', 'content_id'], 'sig' => ['category_id'], 'refs' => ['category_id' => 'categories'], 'noid' => true],
            'design_templates' => ['key' => ['template_id'], 'title' => 'name'],
            'assemblies' => ['key' => ['slug'], 'title' => 'name', 'keep' => ['created_by', 'updated_by']],
            'feedback_forms' => ['key' => ['slug'], 'title' => 'title'],
            'site_ai_sources' => ['key' => ['type', 'title'], 'title' => 'title'],
            'site_ai_chunks' => ['parent' => ['site_ai_sources', 'source_id'], 'sig' => ['chunk_index', 'content']],
            'subscribers' => ['key' => ['email'], 'title' => 'email', 'drop' => ['token']],
            'feedback_entries' => ['key' => ['form_id', 'created_at'], 'drop' => ['ip_hash', 'user_agent'], 'refs' => ['form_id' => 'feedback_forms']],
            'comments' => ['key' => ['content_id', 'created_at'], 'title' => 'author_name', 'drop' => ['ip_address', 'user_agent'], 'refs' => ['content_id' => 'content']],
        ];
    }

    /** Columns ignored when deciding whether a row changed (still written on update unless "keep"). */
    public const IGNORE_DIFF = ['updated_at', 'author_id', 'uploaded_by', 'created_by', 'updated_by', 'revision', 'checksum'];

    // ── settings classification ─────────────────────────────────────────

    public static function isSecretKey(string $key): bool
    {
        $k = strtolower($key);
        return in_array($k, self::SECRET_SETTING_KEYS, true) || preg_match(self::SECRET_PATTERN, $k) === 1;
    }

    public static function isInternalKey(string $key): bool
    {
        $k = strtolower($key);
        foreach (self::INTERNAL_PREFIXES as $p) {
            if (str_starts_with($k, $p)) {
                return true;
            }
        }
        return false;
    }

    /** Which settings group a key belongs to, or null when it must never leave/enter the site. */
    public static function settingGroup(string $key): ?string
    {
        if ($key === self::SITE_THEME_KEY) {
            return 'design';
        }
        // Known design/menu keys are allow-listed first ("design_tokens" holds colour tokens, not credentials).
        if (in_array($key, self::MENU_KEYS, true)) {
            return 'menus';
        }
        if (in_array($key, self::DESIGN_KEYS, true)) {
            return 'design';
        }
        if ($key === '' || self::isSecretKey($key) || self::isInternalKey($key) || !preg_match('/^[A-Za-z0-9_.:-]{1,120}$/', $key)) {
            return null;
        }
        $k = strtolower($key);
        if (in_array($k, self::MENU_KEYS, true)) {
            return 'menus';
        }
        if (in_array($k, self::DESIGN_KEYS, true)) {
            return 'design';
        }
        foreach (self::MENU_PREFIXES as $p) {
            if (str_starts_with($k, $p)) {
                return 'menus';
            }
        }
        foreach (self::DESIGN_PREFIXES as $p) {
            if (str_starts_with($k, $p)) {
                return 'design';
            }
        }
        return 'settings';
    }

    /** Drop nested keys that look secret from a decoded JSON structure (used for site_ai_settings). */
    public static function scrubSecrets($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $k => $v) {
            if (is_string($k) && self::isSecretKey($k)) {
                continue;
            }
            $out[$k] = self::scrubSecrets($v);
        }
        return $out;
    }

    // ── media rules ─────────────────────────────────────────────────────

    public const MEDIA_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif', 'ico', 'bmp', 'pdf', 'mp4', 'webm',
        'mov', 'm4v', 'ogv', 'mp3', 'wav', 'ogg', 'm4a', 'mpd', 'vtt', 'woff', 'woff2'];

    /** Never allowed anywhere in a pack (also as an inner extension, e.g. x.php.jpg). */
    public const FORBIDDEN_EXT_PATTERN = '/\.(php\d*|phtml|phar|pht|phps|inc|cgi|pl|py|rb|sh|bash|zsh|ksh|exe|dll|so|dylib|bin|bat|cmd|com|msi|jar|jsp|jspx|asp|aspx|ashx|htaccess|htpasswd|user\.ini|ini|shtml|vbs|ps1|scr|apk|elf)(\.|$)/i';

    public static function mediaExtAllowed(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $cfg = (array) Bootstrap::config('media.allowed_extensions', []);
        $allowed = array_unique(array_merge(self::MEDIA_EXT, array_map('strtolower', array_map('strval', $cfg))));
        $allowed = array_values(array_filter($allowed, static fn ($e) => !preg_match(self::FORBIDDEN_EXT_PATTERN, '.' . $e)));
        return $ext !== '' && in_array($ext, $allowed, true);
    }

    /** A safe relative path below mova-uploads ("2026/10/x.webp"), or null. */
    public static function safeRelPath(string $p): ?string
    {
        if ($p === '' || strlen($p) > 400 || str_contains($p, "\0") || str_contains($p, '\\') || $p[0] === '/' || preg_match('/^[A-Za-z]:/', $p)) {
            return null;
        }
        foreach (explode('/', $p) as $seg) {
            if ($seg === '' || $seg === '.' || $seg === '..' || !preg_match('/^[A-Za-z0-9._@+ ()-]+$/', $seg) || $seg[0] === '.') {
                return null;
            }
        }
        if (preg_match(self::FORBIDDEN_EXT_PATTERN, $p)) {
            return null;
        }
        return $p;
    }

    /** Files of one media row (primary + generated sizes + poster), relative to uploads. */
    public static function mediaFiles(array $row): array
    {
        $out = [];
        foreach ([(string) ($row['path'] ?? ''), (string) ($row['poster_path'] ?? '')] as $p) {
            if ($p !== '') {
                $out[] = ltrim($p, '/');
            }
        }
        $vars = is_array($row['variants'] ?? null) ? $row['variants'] : json_decode((string) ($row['variants'] ?? ''), true);
        if (is_array($vars)) {
            foreach ($vars as $v) {
                if (is_array($v) && !empty($v['path'])) {
                    $out[] = ltrim((string) $v['path'], '/');
                }
            }
        }
        return array_values(array_unique(array_filter($out, static fn ($p) => self::safeRelPath($p) !== null)));
    }

    // ── misc helpers ────────────────────────────────────────────────────

    public static function version(): string
    {
        return Bootstrap::version();
    }

    public static function exportsDir(): string
    {
        $d = Bootstrap::path('storage') . '/exports';
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
        return $d;
    }

    public static function importsDir(): string
    {
        $d = Bootstrap::path('storage') . '/imports';
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
        foreach (['/jobs', '/staging', '/undo'] as $sub) {
            if (!is_dir($d . $sub)) {
                @mkdir($d . $sub, 0755, true);
            }
        }
        if (!is_file($d . '/.htaccess')) {
            @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        return $d;
    }

    /** @return array<string,bool> real table name => exists */
    public static function tableColumns(string $table): array
    {
        static $cache = [];
        if (!isset($cache[$table])) {
            $cols = [];
            try {
                foreach (Database::fetchAll('PRAGMA table_info("' . str_replace('"', '', $table) . '")') as $c) {
                    $cols[] = (string) $c['name'];
                }
            } catch (\Throwable $e) {
            }
            $cache[$table] = $cols;
        }
        return $cache[$table];
    }

    public static function tableExists(string $table): bool
    {
        return self::tableColumns($table) !== [];
    }

    /** Every data/ file name a valid pack may contain. */
    public static function allowedDataFiles(): array
    {
        $files = [];
        foreach (self::groups() as $gid => $g) {
            foreach (array_merge($g['tables'], $g['children']) as $t) {
                $files[] = 'data/' . $t . '.json';
            }
            if (!empty($g['settings'])) {
                $files[] = 'data/settings.' . $gid . '.json';
            }
            if (!empty($g['kv'])) {
                $files[] = 'data/' . $g['kv'] . '.json';
            }
        }
        return array_values(array_unique($files));
    }

    public static function fmtBytes(int $b): string
    {
        if ($b < 1024) {
            return $b . ' B';
        }
        $u = ['KB', 'MB', 'GB', 'TB'];
        $i = -1;
        $v = $b;
        do {
            $v /= 1024;
            $i++;
        } while ($v >= 1024 && $i < 3);
        return round($v, $v < 10 ? 1 : 0) . ' ' . $u[$i];
    }

    /** Parse php.ini size (e.g. "64M") to bytes. */
    public static function iniBytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return -1;
        }
        $n = (float) $v;
        return (int) match (strtolower(substr($v, -1))) {
            'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n,
        };
    }
}
