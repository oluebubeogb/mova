<?php
/**
 * Mova HQ — shared helpers (auth gate, view render, layout)
 */

declare(strict_types=1);

use Mova\Core\Response;
use Mova\Auth\Auth;

function requireAuth(): void
{
    if (!Auth::check()) {
        // Remember where the user was so we can send them back after re-login
        $uri = $_SERVER['REQUEST_URI'] ?? '/hq';
        // Prefer path+query only (no scheme/host)
        $path = parse_url($uri, PHP_URL_PATH) ?: '/hq';
        $query = parse_url($uri, PHP_URL_QUERY);
        $intended = $path . ($query ? '?' . $query : '');
        Auth::setIntendedUrl($intended);

        $qs = Auth::wasTimedOut() ? '?timeout=1' : '';
        header('Location: /hq/login' . $qs);
        exit;
    }
}

function renderHq(string $view, array $data = []): Response
{
    $hqViews = __DIR__ . '/views';
    $file = $hqViews . '/' . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';

    if (!file_exists($file)) {
        $html = hqLayout($view, '<p>View not found: ' . htmlspecialchars($view) . '</p>', $data);
        return Response::make($html);
    }

    extract($data);
    ob_start();
    include $file;
    $content = ob_get_clean();

    $html = hqLayout($view, $content, $data);
    return Response::make($html);
}

function hqLayout(string $view, string $content, array $data = []): string
{
    $user = Auth::user();
    $isAuth = $user !== null;
    $pageTitle = $data['title'] ?? ucfirst(str_replace(['/', '_'], [' ', ' '], $view));

    // Determine current workspace for Line 1 section name + Line 2 items
    $workspace = 'overview';
    if (strpos($view, 'landings/content') === 0 || strpos($view, 'content') === 0 || strpos($view, 'media') === 0
        || strpos($view, 'galleries') === 0 || strpos($view, 'types') === 0
        || strpos($view, 'assembly') === 0
        || strpos($view, 'studio') === 0
        || $view === 'taxonomy/categories' || $view === 'taxonomy/tags') {
        $workspace = 'content';
    } elseif (strpos($view, 'landings/audience') === 0 || strpos($view, 'people') === 0 || strpos($view, 'mail') === 0
        || strpos($view, 'mailbox') === 0 || strpos($view, 'sequences') === 0
        || strpos($view, 'insights') === 0) {
        $workspace = 'audience';
    } elseif (strpos($view, 'landings/design') === 0 || strpos($view, 'brand') === 0 || strpos($view, 'style') === 0
        || strpos($view, 'layout') === 0 || strpos($view, 'components') === 0
        || strpos($view, 'elements') === 0 || strpos($view, 'variables') === 0 || strpos($view, 'variable-') === 0
        || strpos($view, 'design/') === 0
        || $view === 'appearance') {
        $workspace = 'design';
    } elseif (strpos($view, 'landings/extend') === 0 || strpos($view, 'plugins') === 0 || strpos($view, 'integrations') === 0
        || strpos($view, 'api') === 0 || strpos($view, 'webhooks') === 0) {
        $workspace = 'extend';
    } elseif (strpos($view, 'landings/operations') === 0 || strpos($view, 'health') === 0 || strpos($view, 'security') === 0
        || strpos($view, 'backups') === 0 || strpos($view, 'updates') === 0) {
        $workspace = 'operations';
    } elseif ($view === 'settings' || strpos($view, 'sites') === 0) {
        $workspace = 'settings';
    }

    $sectionNames = [
        'overview'   => 'Overview',
        'content'    => 'Content',
        'audience'   => 'Audience',
        'design'     => 'Design',
        'extend'     => 'Extend',
        'operations' => 'Operations',
        'settings'   => 'Settings',
    ];
    $sectionName = $sectionNames[$workspace] ?? 'Overview';

    $themeToggle = <<<'HTML'
<button type="button" class="hq-icon-btn" data-theme-toggle aria-label="Toggle theme" title="Toggle theme">
    <i class="fa-solid fa-circle-half-stroke"></i>
</button>
HTML;

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en" data-mova-hq="1">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#F5F5F7">
    <title><?= htmlspecialchars($pageTitle) ?> — Mova HQ</title>
    <script src="/assets/js/theme-boot.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="/assets/css/hq.css?v=20260922fullwidth2">
    <link rel="stylesheet" href="/assets/css/hq-vnext.css?v=20260922fullwidth2">
    <link rel="stylesheet" href="/assets/css/hq-landings.css?v=20260922fullwidth2">
    <?php if ($isAuth): ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(\Mova\Security\Csrf::token()) ?>">
    <link rel="stylesheet" href="/assets/css/mova-ai-panel.css?v=20260926ai1">
    <?php endif; ?>
</head>
<body class="hq hq-vnext <?= $isAuth ? 'hq-authenticated' : 'hq-guest' ?>" data-workspace="<?= htmlspecialchars($workspace) ?>">

<?php if ($isAuth): ?>

    <!-- LINE 1 -->
    <header class="hq-line1">
        <div class="hq-line1-left">
            <a href="/hq" class="hq-logo">
                <span class="hq-logo-mark">M</span>
                <span class="hq-section-name" id="hq-section-name">Overview</span>
            </a>
        </div>

        <div class="hq-line1-center">
            <div class="hq-search" id="hq-search-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="hq-global-search" placeholder="Search HQ…" autocomplete="off" spellcheck="false">
                <div class="hq-search-dropdown" id="hq-search-dropdown" hidden></div>
            </div>
        </div>

        <div class="hq-line1-right">
            <button type="button" class="hq-icon-btn" id="mova-ai-header-btn" title="Mova AI (Ctrl+J)" aria-label="Open Mova AI">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </button>
            <a href="/" target="_blank" class="hq-icon-btn hq-desktop-only" title="View site" aria-label="View site">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
            </a>
            <span class="hq-desktop-only"><?= $themeToggle ?></span>
            <div class="hq-user-menu">
                <button type="button" class="hq-user-btn" id="hq-user-btn">
                    <span class="hq-avatar"><?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?></span>
                    <span class="hq-user-name"><?= htmlspecialchars($user['name'] ?? 'Admin') ?></span>
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="hq-user-dropdown" id="hq-user-dropdown" hidden>
                    <a href="/" target="_blank" class="hq-mobile-only"><i class="fa-solid fa-arrow-up-right-from-square"></i> Preview</a>
                    <button type="button" class="hq-mobile-only hq-dropdown-theme" data-theme-toggle aria-label="Toggle theme">
                        <i class="fa-solid fa-circle-half-stroke"></i> Theme
                    </button>
                    <a href="/hq/settings"><i class="fa-solid fa-gear"></i> Settings</a>
                    <a href="/hq/logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- LINE 2 — Workspace switcher -->
    <nav class="hq-line2" id="hq-contextual-nav" aria-label="Workspaces">
        <!-- Filled by hq-nav.js -->
    </nav>

    <!-- Full-width content (no breadcrumbs) -->
    <main class="hq-content">
        <?= $content ?>
    </main>

    <!-- Mova AI panel (global) -->
    <button type="button" class="mova-ai-fab" id="mova-ai-fab" title="Mova AI (Ctrl+J)" aria-label="Open Mova AI">
        <i class="fa-solid fa-wand-magic-sparkles"></i>
    </button>
    <aside class="mova-ai-panel" id="mova-ai-panel" aria-label="Mova AI">
        <div class="mova-ai-panel-header">
            <h2>Mova AI <span class="mova-ai-badge">HQ</span></h2>
            <button type="button" class="mova-ai-icon-btn" id="mova-ai-sessions-toggle" title="Sessions" aria-label="Sessions">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </button>
            <button type="button" class="mova-ai-icon-btn" id="mova-ai-new" title="New chat" aria-label="New chat">
                <i class="fa-solid fa-plus"></i>
            </button>
            <button type="button" class="mova-ai-icon-btn" id="mova-ai-close" title="Close" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="mova-ai-sessions" id="mova-ai-sessions" hidden>
            <div id="mova-ai-sessions-list"></div>
        </div>
        <div class="mova-ai-messages" id="mova-ai-messages"></div>
        <div class="mova-ai-composer">
            <textarea id="mova-ai-input" rows="2" placeholder="Ask Mova AI… e.g. Where is the site icon?"></textarea>
            <div class="mova-ai-composer-row">
                <span class="hint">Ctrl+J · Enter to send</span>
                <button type="button" class="mova-ai-send" id="mova-ai-send">Send</button>
            </div>
        </div>
    </aside>

<?php else: ?>
    <div class="hq-auth-wrap">
        <?= $content ?>
    </div>
<?php endif; ?>

    <script src="/assets/js/theme.js" defer></script>
    <script src="/assets/js/hq-search-index.js?v=20260923gallery"></script>
    <script src="/assets/js/hq-nav.js?v=20260923gallery" defer></script>
    <script src="/assets/js/hq-layers.js" defer></script>
    <?php if ($isAuth): ?>
    <script src="/assets/js/mova-ai-panel.js?v=20260926ai1" defer></script>
    <script>
    (function () {
      var btn = document.getElementById('mova-ai-header-btn');
      if (btn) {
        btn.addEventListener('click', function () {
          var fab = document.getElementById('mova-ai-fab');
          if (fab) fab.click();
          else {
            var panel = document.getElementById('mova-ai-panel');
            if (panel) panel.classList.add('is-open');
          }
        });
      }
    })();
    </script>
    <?php endif; ?>
</body>
</html>
    <?php
    return ob_get_clean();
}
/**
 * Check whether a plugin is currently active.
 */
function isPluginActive(string $slug): bool
{
    try {
        $row = \Mova\Core\Database::fetch(
            "SELECT status FROM plugins WHERE slug = :s",
            ['s' => $slug]
        );
        return ($row['status'] ?? '') === 'active';
    } catch (\Throwable $e) {
        return false;
    }
}
