<?php

declare(strict_types=1);
use Mova\Auth\Auth;
use Mova\Core\Bootstrap;
use Mova\Core\Database;
use Mova\Content\ContentRepository;
use Mova\Hq\RouteRegistry;

/** @var \Mova\Core\Router $router */

/** Read one row from the settings table (empty string when missing). */
function mova_dash_setting(string $key): string
{
    try {
        $row = Database::fetch('SELECT setting_value FROM settings WHERE setting_key = :k', ['k' => $key]);
        return $row ? (string) $row['setting_value'] : '';
    } catch (\Throwable $e) {
        return '';
    }
}

/** Disk usage of a directory, capped so huge upload folders never stall the dashboard. */
function mova_dash_dir_size(string $dir, int $maxFiles = 20000): array
{
    $bytes = 0;
    $files = 0;
    if (!is_dir($dir)) {
        return ['bytes' => 0, 'files' => 0, 'capped' => false];
    }
    try {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $bytes += $f->getSize();
                if (++$files >= $maxFiles) {
                    return ['bytes' => $bytes, 'files' => $files, 'capped' => true];
                }
            }
        }
    } catch (\Throwable $e) {
    }
    return ['bytes' => $bytes, 'files' => $files, 'capped' => false];
}

/** Storage totals, cached for 15 minutes in storage/cache (shared hosting friendly). */
function mova_dash_storage(): array
{
    $cacheFile = Bootstrap::path('cache') . '/hq-dashboard-storage.json';
    if (is_file($cacheFile) && filemtime($cacheFile) > time() - 900) {
        $j = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($j)) {
            return $j;
        }
    }
    $dbPath = (string) Bootstrap::config('database.path');
    $out = [
        'uploads' => mova_dash_dir_size(Bootstrap::path('uploads'))['bytes'],
        'database' => is_file($dbPath) ? (int) filesize($dbPath) : 0,
        'backups' => mova_dash_dir_size(Bootstrap::path('backups'), 500)['bytes'],
        'cache' => mova_dash_dir_size(Bootstrap::path('cache'), 5000)['bytes'],
    ];
    $free = @disk_free_space(Bootstrap::path('storage'));
    $out['free'] = $free !== false ? (int) $free : null;
    $out['at'] = time();
    @file_put_contents($cacheFile, json_encode($out), LOCK_EX);
    return $out;
}

/**
 * Setup checklist — each step is derived from real data, never from a manual tick.
 * @return list<array{id:string,label:string,hint:string,href:string,done:bool}>
 */
function mova_dash_checklist(int $published, int $users): array
{
    $mailOk = false;
    try {
        $mailOk = (bool) Database::fetch("SELECT id FROM mail_accounts WHERE smtp_host IS NOT NULL AND smtp_host != '' LIMIT 1");
    } catch (\Throwable $e) {
    }
    $aiOn = false;
    try {
        // Only presence is checked; the secret value itself never leaves AiConfig.
        $aiOn = \Mova\AI\AiConfig::get('ai_api_key') !== '' && \Mova\AI\AiConfig::get('ai_api_url') !== '';
    } catch (\Throwable $e) {
    }
    $siteName = trim(mova_dash_setting('site_name'));
    $navRaw = trim(mova_dash_setting('nav_links'));
    $navJson = json_decode($navRaw, true);
    // Stored either as "Label|/url" lines or as JSON.
    $navOk = is_array($navJson) ? $navJson !== [] : str_contains($navRaw, '|');
    return [
        ['id' => 'name', 'label' => 'Name your site', 'hint' => 'Site name and tagline appear in titles and search results.', 'href' => '/hq/settings', 'done' => $siteName !== '' && strcasecmp($siteName, 'Mova') !== 0],
        ['id' => 'brand', 'label' => 'Pick a brand colour', 'hint' => 'One colour drives buttons, links and accents across the theme.', 'href' => '/hq/design', 'done' => mova_dash_setting('brand_color') !== ''],
        ['id' => 'publish', 'label' => 'Publish your first page', 'hint' => 'Write something and hit Publish — it goes live instantly.', 'href' => '/hq/content/new', 'done' => $published > 0],
        ['id' => 'home', 'label' => 'Choose a homepage', 'hint' => 'Pick which page visitors land on.', 'href' => '/hq/settings', 'done' => (int) mova_dash_setting('homepage_content_id') > 0],
        ['id' => 'nav', 'label' => 'Set up navigation', 'hint' => 'Add the links that show in your site header.', 'href' => '/hq/design', 'done' => $navOk],
        ['id' => 'mail', 'label' => 'Connect email', 'hint' => 'Needed for password resets, forms and newsletters.', 'href' => '/hq/mailbox', 'done' => $mailOk],
        ['id' => 'ai', 'label' => 'Turn on Mova AI', 'hint' => 'Drafts, rewrites, alt text and SEO suggestions.', 'href' => '/hq/settings?layer=ai', 'done' => $aiOn],
        ['id' => 'team', 'label' => 'Invite a teammate', 'hint' => 'Give editors or viewers their own login.', 'href' => '/hq/people', 'done' => $users > 1],
    ];
}

$router->get('/', function () {
    requireAuth();
    try {
        (new \Mova\Backup\BackupService())->ensureWeeklyBackup();
    } catch (\Throwable $e) {
    }
    $repo = new ContentRepository();
    $isAdmin = RouteRegistry::allowed('admin');
    $canWrite = Auth::canMutate();
    $user = Auth::user() ?? [];

    $stats = [
        'published' => $repo->count(['status' => 'published']),
        'drafts'    => $canWrite ? $repo->count(['status' => 'draft']) : 0,
        'review'    => $canWrite ? $repo->count(['status' => 'review']) : 0,
        'media'     => Database::count('media'),
        'users'     => Database::count('users'),
    ];

    // Recent edits (viewers only ever see published entries).
    $where = $canWrite ? "c.status != 'trash'" : "c.status = 'published'";
    $recent = [];
    try {
        $recent = Database::fetchAll(
            "SELECT c.id, c.title, c.slug, c.type, c.status, c.updated_at, u.name AS author
               FROM content c LEFT JOIN users u ON u.id = c.author_id
              WHERE {$where}
              ORDER BY COALESCE(c.updated_at, c.created_at) DESC LIMIT 6"
        );
    } catch (\Throwable $e) {
    }

    // Setup checklist + Quick setup visibility (admins only).
    $checklist = [];
    $wizardDone = false;
    if ($isAdmin) {
        $checklist = mova_dash_checklist((int) $stats['published'], (int) $stats['users']);
        $draft = json_decode(mova_dash_setting('setup_wizard_draft_' . (int) Auth::id()), true);
        $wizardDone = is_array($draft) && !empty($draft['published_at']);
    }
    $doneCount = count(array_filter($checklist, fn($s) => $s['done']));
    $setupComplete = $checklist === [] || $doneCount === count($checklist);

    // Health traffic lights (admins only).
    $health = [];
    if ($isAdmin) {
        try {
            $report = (new \Mova\Core\HealthService())->report();
            foreach (($report['checks'] ?? $report) as $k => $c) {
                if (is_array($c) && isset($c['status'])) {
                    $health[$k] = ['status' => (string) $c['status'], 'detail' => (string) ($c['detail'] ?? '')];
                }
            }
        } catch (\Throwable $e) {
        }
    }

    // AI jobs for this user + 14-day usage sparkline.
    $jobs = [];
    $usage = ['days' => [], 'calls' => 0, 'tokens' => 0, 'errors' => 0];
    if (RouteRegistry::allowed('use_ai')) {
        try {
            $jobs = (new \Mova\AI\AiJobService())->listJobs((int) Auth::id(), 5);
        } catch (\Throwable $e) {
        }
    }
    if ($isAdmin) {
        try {
            $rep = \Mova\AI\AiUsage::report(14);
            $byDay = [];
            foreach ($rep['by_day'] ?? [] as $d) {
                $byDay[$d['day']] = (int) $d['calls'];
            }
            for ($i = 13; $i >= 0; $i--) {
                $day = date('Y-m-d', time() - $i * 86400);
                $usage['days'][] = ['day' => $day, 'calls' => $byDay[$day] ?? 0];
            }
            $usage['calls'] = (int) ($rep['totals']['calls'] ?? 0);
            $usage['tokens'] = (int) ($rep['totals']['total_tokens'] ?? 0);
            $usage['errors'] = (int) ($rep['totals']['errors'] ?? 0);
        } catch (\Throwable $e) {
        }
    }
    $storage = $isAdmin ? mova_dash_storage() : [];

    return renderHq('dashboard', compact(
        'stats', 'recent', 'user', 'isAdmin', 'canWrite', 'checklist', 'doneCount',
        'setupComplete', 'wizardDone', 'health', 'jobs', 'usage', 'storage'
    ));
});
