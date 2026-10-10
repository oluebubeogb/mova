<?php
/**
 * Mova CMS - Configuration
 * Content that moves.
 *
 * Supports two layouts:
 * 1) Standard: document root → /public  (app, config, storage sit above public)
 * 2) Shared hosting / flat: document root → project root (index.php next to /app)
 *    Used when a subdomain's public_html is the docroot (cPanel, DirectAdmin, etc.)
 */

$movaRoot = dirname(__DIR__);
// Flat mode: index.php lives beside /app (typical public_html upload)
$movaFlat = is_file($movaRoot . '/index.php') && is_dir($movaRoot . '/app');
$movaPublic = $movaFlat ? $movaRoot : ($movaRoot . '/public');

return [
    // Application
    'app_name'       => 'Mova',
    'app_tagline'    => 'Content that moves.',
    'app_version'    => '1.1.4',
    'app_url'        => '', // Auto-detected if empty
    'timezone'       => 'UTC',
    'debug'          => false,
    'install_mode'   => $movaFlat ? 'flat' : 'standard',

    // Paths (relative to root)
    'paths' => [
        'root'     => $movaRoot,
        'public'   => $movaPublic,
        'storage'  => $movaRoot . '/storage',
        'themes'   => $movaRoot . '/mova-themes',
        'plugins'  => $movaRoot . '/mova-plugins',
        'uploads'  => $movaPublic . '/mova-uploads',
        'cache'    => $movaRoot . '/storage/cache',
        'logs'     => $movaRoot . '/storage/logs',
        'backups'  => $movaRoot . '/storage/backups',
    ],

    // Database
    'database' => [
        'driver' => 'sqlite',
        'path'   => $movaRoot . '/storage/database.sqlite',
    ],

    // Security
    'security' => [
        'csrf_token_name'   => '_mova_csrf',
        'session_name'      => 'mova_session',
        'session_lifetime'  => 7200, // 2 hours
        'password_algo'     => PASSWORD_ARGON2ID,
        'login_max_attempts'=> 5,
        'login_lockout'     => 900, // 15 minutes
        'cookie_secure'     => true,
        'cookie_httponly'   => true,
        'cookie_samesite'   => 'Lax',
    ],

    // Content
    'content' => [
        'types' => [
            'article'       => 'Article',
            'page'          => 'Page',
            'guide'         => 'Guide',
            'documentation' => 'Documentation',
            'faq'           => 'FAQ',
            'custom'        => 'Custom',
        ],
        'statuses' => [
            'draft'     => 'Draft',
            'review'    => 'In review',
            'approved'  => 'Approved',
            'scheduled' => 'Scheduled',
            'published' => 'Published',
            'archived'  => 'Archived',
            'trash'     => 'Trash',
        ],
        'default_type'   => 'article',
        'default_status' => 'draft',
        'per_page'       => 20,
    ],

    // Media
    'media' => [
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/svg+xml',
            'application/pdf',
            'video/mp4',
            'video/webm',
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'application/dash+xml',
            'application/mpd',
            'video/mpd',
            'text/xml', // some servers report .mpd as text/xml
        ],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'pdf', 'mp4', 'webm', 'mp3', 'wav', 'mpd'],
        'max_upload_size'    => 10 * 1024 * 1024, // 10 MB (unchanged)
        'variants'           => [480, 768, 1200],
        'quality'            => 85,
        'convert_to_webp'    => true,
    ],

    // SEO defaults
    'seo' => [
        'title_separator' => ' | ',
        'default_robots'  => 'index, follow',
    ],

    // Cache
    'cache' => [
        'enabled' => true,
        'ttl'     => 3600, // 1 hour
    ],

    // Mail (SMTP)
    'mail' => [
        'driver'     => 'smtp',
        'host'       => '',
        'port'       => 587,
        'username'   => '',
        'password'   => '',
        'encryption' => 'tls',
        'from_email' => '',
        'from_name'  => 'Mova',
    ],

    // Mova AI — platform defaults (RunPod public IBM Granite 4.0 H Small).
    // Secret key is NEVER stored here: set RUNPOD_API_KEY in the environment.
    // Leave HQ Settings → Mova AI fields blank to use these defaults.
    // Fill Settings only when overriding with a custom provider.
    'ai' => [
        'ai_api_url'  => 'https://api.runpod.ai/v2/granite-4-0-h-small/openai/v1',
        'ai_api_key'  => '', // always empty in config; use getenv('RUNPOD_API_KEY')
        'ai_model'    => 'ibm-granite/granite-4.0-h-small',
        'ai_provider' => 'RunPod IBM Granite 4.0 H Small',
    ],

    // Reserved routes (cannot be used as content slugs)
    'reserved_routes' => [
        'hq', 'api', 'assets', 'mova-uploads', 'mova-plugins', 'mova-themes', 'search',
        'gallery', 'sitemap.xml', 'robots.txt', 'feed.xml', 'llms.txt',
    ],

    // Roles
    'roles' => [
        'owner'         => 'Owner',
        'administrator' => 'Administrator',
        'editor'        => 'Editor',
        'author'        => 'Author',
        'viewer'        => 'Viewer',
    ],
];