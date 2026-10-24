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

/**
 * 403 response that suits the caller (JSON for fetch/XHR, text otherwise).
 */
function hqForbidden(string $message = 'Your role does not allow this action.'): Response
{
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    if ($xhr || str_contains($accept, 'application/json') || str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'json')) {
        return (new Response())->json(['ok' => false, 'error' => $message, 'forbidden' => true], 403);
    }
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET' && Auth::check()) {
        $html = '<div class="hq-empty-state hq-forbidden"><div class="hq-empty-icon"><i class="fa-solid fa-lock" aria-hidden="true"></i></div>'
            . '<h1>You don’t have access to this page</h1><p>' . htmlspecialchars($message) . ' Ask an administrator if you need it.</p>'
            . '<p><a class="btn btn-primary" href="/hq"><i class="fa-solid fa-house" aria-hidden="true"></i> Back to dashboard</a></p></div>';
        return (new Response())->status(403)->body(hqLayout('forbidden', $html, ['title' => 'No access']));
    }
    return (new Response())->status(403)->body('Forbidden — ' . htmlspecialchars($message));
}

/**
 * Role gate: returns a 403 Response unless the signed-in user has at least one permission.
 * Usage: if ($r = requirePerm('manage_content')) { return $r; }
 */
function requirePerm(string ...$perms): ?Response
{
    requireAuth();
    foreach ($perms as $p) {
        if (Auth::can($p)) {
            return null;
        }
    }
    return hqForbidden();
}

/**
 * Content gate: manage_content, or manage_own_content when the item belongs to the user.
 */
function requireContentAccess(?int $contentId = null): ?Response
{
    requireAuth();
    if (Auth::can('manage_content')) {
        return null;
    }
    if (Auth::can('manage_own_content')) {
        if ($contentId === null || $contentId <= 0) {
            return null; // creating new content
        }
        try {
            $row = \Mova\Core\Database::fetch("SELECT author_id FROM content WHERE id = :id", ['id' => $contentId]);
            if ($row && (int) ($row['author_id'] ?? 0) === (int) Auth::id()) {
                return null;
            }
        } catch (\Throwable $e) {
        }
    }
    return hqForbidden('Your role cannot edit this content.');
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

/** Drop landing-page cards that point at pages the current role cannot open. */
function hqFilterLandingCards(string $html): string
{
    $out = preg_replace_callback(
        '#<a class="hq-landing-card[^"]*" href="([^"]+)"[^>]*>.*?</a>\s*#s',
        static function (array $m): string {
            try {
                $path = (string) parse_url(html_entity_decode($m[1]), PHP_URL_PATH);
                $route = \Mova\Hq\RouteRegistry::match($path);
                if ($route && !\Mova\Hq\RouteRegistry::usable($route)) {
                    return '';
                }
            } catch (\Throwable $e) {
            }
            return $m[0];
        },
        $html
    );
    $out = is_string($out) ? $out : $html;
    if (!str_contains($out, 'hq-landing-card') && str_contains($out, 'hq-landing-grid')) {
        $out = preg_replace(
            '#<div class="hq-landing-grid">\s*</div>#',
            '<div class="hq-landing-empty"><i class="fa-solid fa-lock" aria-hidden="true"></i><p>Your role does not include any tools in this workspace. Ask an administrator if you need access.</p><a class="btn btn-secondary" href="/hq">Back to overview</a></div>',
            $out
        ) ?? $out;
    }
    return $out;
}

function hqLayout(string $view, string $content, array $data = []): string
{
    if (str_starts_with($view, 'landings/') && Auth::user() !== null) {
        $content = hqFilterLandingCards($content);
    }
    $user = Auth::user();
    $isAuth = $user !== null;
    $pageTitle = $data['title'] ?? ucfirst(str_replace(['/', '_'], [' ', ' '], $view));

    // Workspace, breadcrumb and Line 2 come from the single HQ route registry
    $navPayload = null;
    $workspace = 'overview';
    if ($isAuth) {
        try {
            $navPayload = \Mova\Hq\RouteRegistry::clientPayload((string) ($_SERVER['REQUEST_URI'] ?? '/hq'));
            $workspace = $navPayload['section'];
        } catch (\Throwable $e) {
            $navPayload = null;
        }
    }
    $sectionName = $navPayload['sections'][$workspace]['label'] ?? 'Overview';

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
    <meta name="theme-color" content="#0F1115" data-theme-sync data-light="#F5F5F7" data-dark="#0F1115">
    <title><?= htmlspecialchars($pageTitle) ?> — Mova HQ</title>
    <link rel="icon" href="/assets/hq/favicon.svg?v=p5x" type="image/svg+xml">
    <link rel="icon" href="/assets/hq/favicon-32.png?v=p5x" type="image/png" sizes="32x32">
    <link rel="alternate icon" href="/assets/hq/favicon.ico?v=p5x" sizes="16x16 32x32 48x48">
    <link rel="apple-touch-icon" href="/assets/hq/apple-touch-icon.png?v=p5x" sizes="180x180">
    <script src="/assets/js/theme-boot.js"></script>
    <script>window.MOVA_TZ = <?= json_encode(\Mova\Core\Time::zoneName()) ?>;</script>
    <script src="/assets/js/hq-time.js?v=20261023uni"></script>
    <link rel="preload" href="/assets/fonts/inter-latin-wght.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css?v=6.5.2">
    <link rel="stylesheet" href="/assets/css/hq.css?v=20261023uni">
    <link rel="stylesheet" href="/assets/css/hq-vnext.css?v=20261023uni">
    <link rel="stylesheet" href="/assets/css/hq-landings.css?v=20261023uni">
    <link rel="stylesheet" href="/assets/css/hq-nav-search.css?v=20261023uni">
    <?php if ($isAuth): ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(\Mova\Security\Csrf::token()) ?>">
    <link rel="stylesheet" href="/assets/css/mova-ai-panel.css?v=20261023uni">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/hq-p5.css?v=20261023uni">
</head>
<body class="hq hq-vnext <?= $isAuth ? 'hq-authenticated' : 'hq-guest' ?>" data-workspace="<?= htmlspecialchars($workspace) ?>" data-hq-tz="<?= htmlspecialchars(\Mova\Core\Time::zoneName()) ?>"<?php if ($isAuth): ?> data-hq-admin="<?= \Mova\Hq\RouteRegistry::allowed('admin') ? '1' : '0' ?>" data-hq-design="<?= \Mova\Hq\RouteRegistry::allowed('manage_appearance') ? '1' : '0' ?>" data-hq-ai="<?= \Mova\Hq\RouteRegistry::allowed('use_ai') ? '1' : '0' ?>"<?php endif; ?>>
<a class="hq-skip-link" href="#hq-main">Skip to content</a>

<?php if ($isAuth): ?>

    <!-- LINE 1 -->
    <header class="hq-line1">
        <div class="hq-line1-left">
            <button type="button" class="hq-icon-btn hq-menu-btn" id="hq-menu-btn" aria-label="Open navigation menu" aria-expanded="false" aria-controls="hq-drawer">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
            <a href="/hq" class="hq-logo" aria-label="Mova HQ home">
                <span class="hq-logo-mark" aria-hidden="true">M</span>
            </a>
            <?= hqBreadcrumbHtml($navPayload) ?>
        </div>

        <div class="hq-line1-center">
            <div class="hq-search" id="hq-search-wrap" role="search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="hq-global-search" placeholder="Search pages, settings, content…" autocomplete="off" spellcheck="false"
                       role="combobox" aria-expanded="false" aria-controls="hq-search-dropdown" aria-autocomplete="list" aria-label="Search HQ">
                <kbd class="hq-search-kbd" aria-hidden="true" title="Command palette">Ctrl K</kbd>
                <div class="hq-search-dropdown" id="hq-search-dropdown" role="listbox" aria-label="Search suggestions" hidden></div>
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
            <a href="/hq/docs" class="hq-icon-btn hq-desktop-only" data-tip="Documentation &amp; help" aria-label="Documentation and help">
                <i class="fa-regular fa-circle-question" aria-hidden="true"></i>
            </a>
            <div class="hq-user-menu">
                <button type="button" class="hq-user-btn" id="hq-user-btn" aria-haspopup="menu" aria-expanded="false" aria-controls="hq-user-dropdown" aria-label="Account menu">
                    <span class="hq-avatar"><?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?></span>
                    <span class="hq-user-name"><?= htmlspecialchars($user['name'] ?? 'Admin') ?></span>
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="hq-user-dropdown" id="hq-user-dropdown" hidden>
                    <a href="/" target="_blank" class="hq-mobile-only"><i class="fa-solid fa-arrow-up-right-from-square"></i> Preview</a>
                    <button type="button" class="hq-mobile-only hq-dropdown-theme" data-theme-toggle aria-label="Toggle theme">
                        <i class="fa-solid fa-circle-half-stroke"></i> Theme
                    </button>
                    <div class="hq-dropdown-head"><strong><?= htmlspecialchars($user['name'] ?? 'Admin') ?></strong><span><?= htmlspecialchars(ucfirst((string) ($user['role'] ?? ''))) ?></span></div>
                    <?php if (\Mova\Hq\RouteRegistry::allowed('admin')): ?>
                    <a href="/hq/settings"><i class="fa-solid fa-gear"></i> Settings</a>
                    <a href="/hq/setup-wizard"><i class="fa-solid fa-wand-magic-sparkles"></i> Quick setup</a>
                    <?php endif; ?>
                    <a href="/hq/docs"><i class="fa-solid fa-book"></i> Documentation</a>
                    <button type="button" data-hq-shortcuts><i class="fa-regular fa-keyboard"></i> Keyboard shortcuts</button>
                    <span class="hq-dropdown-sep" aria-hidden="true"></span>
                    <a href="/hq/logout"><i class="fa-solid fa-right-from-bracket"></i> Log out</a>
                </div>
            </div>
        </div>
    </header>

    <!-- LINE 2 — registry-driven contextual nav (server-rendered, enhanced by hq-nav.js) -->
    <?= hqLine2Html($navPayload) ?>
    <?= hqDrawerHtml($navPayload) ?>

    <!-- Full-width content (no breadcrumbs) -->
    <main class="hq-content" id="hq-main" tabindex="-1">
        <?php if (stripos($content, '<h1') === false): ?><h1 class="hq-sr-only"><?= htmlspecialchars(preg_replace('/\s+[—–|-]\s+Mova.*$/u', '', (string) $pageTitle)) ?></h1><?php endif; ?>
        <?= $content ?>
    </main>

    <!-- Mova AI panel (global) -->
    <button type="button" class="mova-ai-fab" id="mova-ai-fab" title="Mova AI (Ctrl+J)" aria-label="Open Mova AI">
        <i class="fa-solid fa-wand-magic-sparkles"></i>
    </button>
    <aside class="mova-ai-panel" id="mova-ai-panel" aria-label="Mova AI" data-uid="<?= (int) ($user['id'] ?? 0) ?>">
        <div class="mova-ai-panel-header">
            <h2>Mova AI <span class="mova-ai-badge">HQ</span></h2>
            <button type="button" class="mova-ai-icon-btn" id="mova-ai-sessions-toggle" title="Sessions" aria-label="Sessions">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </button>
            <button type="button" class="mova-ai-icon-btn" id="mova-ai-guide-toggle" title="Guided section steps" aria-label="Guided steps">
                <i class="fa-solid fa-table-cells-large"></i>
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
        <div class="mova-ai-guide" id="mova-ai-guide" hidden>
            <div class="mova-ai-guide-head">
                <span>Quick start</span>
                <button type="button" class="mova-ai-text-btn" id="mova-ai-guide-skip">Skip — free chat</button>
            </div>
            <div class="mova-ai-guide-step" data-guide-step="1">
                <p class="mova-ai-guide-label">What do you want?</p>
                <div class="mova-ai-guide-chips">
                    <button type="button" class="mova-ai-chip" data-guide-intent="template">Add a section</button>
                    <button type="button" class="mova-ai-chip" data-guide-intent="chat">Free chat / code</button>
                    <button type="button" class="mova-ai-chip" data-guide-intent="colors">Change site colors</button>
                </div>
            </div>
            <div class="mova-ai-guide-step" data-guide-step="2" hidden>
                <p class="mova-ai-guide-label">Category</p>
                <div class="mova-ai-guide-chips" id="mova-ai-guide-categories"></div>
                <a class="mova-ai-guide-link" href="/hq/templates" target="_self">Browse all sections →</a>
            </div>
            <div class="mova-ai-guide-step" data-guide-step="3" hidden>
                <p class="mova-ai-guide-label">Pick a section</p>
                <div class="mova-ai-guide-templates" id="mova-ai-guide-templates"></div>
            </div>
            <div class="mova-ai-guide-step" data-guide-step="4" hidden>
                <p class="mova-ai-guide-label">Brief <span id="mova-ai-guide-picked"></span></p>
                <textarea id="mova-ai-guide-brief" rows="3" placeholder="e.g. Gym Lite $10 / Pro $75 / Pro Max $350. Feature Pro. Slightly green."></textarea>
                <div class="mova-ai-guide-chips">
                    <button type="button" class="mova-ai-chip is-toggle" data-guide-opt="keep_colors">Keep site colors</button>
                    <button type="button" class="mova-ai-chip is-toggle" data-guide-opt="featured_middle">Featured middle</button>
                </div>
                <button type="button" class="mova-ai-send mova-ai-guide-generate" id="mova-ai-guide-generate">Generate from section</button>
            </div>
        </div>
        <div class="mova-ai-messages" id="mova-ai-messages"></div>
        <div class="mova-ai-suggest" id="mova-ai-suggest" aria-label="Suggestions">
            <button type="button" class="mova-ai-chip" data-ai-suggest="Write a short blog post draft about our latest news">Draft a blog post</button>
            <button type="button" class="mova-ai-chip" data-ai-suggest="Suggest SEO titles and meta descriptions for my top pages">Improve SEO</button>
            <button type="button" class="mova-ai-chip" data-ai-suggest="Where do I change the site colours and fonts?">Change colours</button>
            <button type="button" class="mova-ai-chip" data-ai-suggest="What should I set up next on this site?">What next?</button>
        </div>
        <div class="mova-ai-composer">
            <textarea id="mova-ai-input" rows="2" placeholder="Ask Mova AI… code, navigate, or content help"></textarea>
            <div class="mova-ai-composer-row">
                <div class="mova-ai-foot-tools">
                    <button type="button" class="mova-ai-raw" id="mova-ai-raw-mode" role="switch" aria-checked="false" aria-label="Raw mode" data-tip="Raw: the AI answers directly like any chat AI, with no site tools or HQ context">
                        <span class="mova-ai-raw-track" aria-hidden="true"><span class="mova-ai-raw-knob"></span></span>
                        <span class="mova-ai-raw-label" aria-hidden="true">Raw</span>
                    </button>
                    <span class="mova-ai-hint" id="mova-ai-hint" aria-hidden="true"><span data-kbd-mod>Ctrl+J</span> · Enter</span>
                </div>
                <button type="button" class="mova-ai-send" id="mova-ai-send" aria-label="Send message"><span>Send</span><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button>
            </div>
        </div>
    </aside>

<?php else: ?>
    <div class="hq-auth-wrap">
        <?= $content ?>
    </div>
<?php endif; ?>

    <script src="/assets/js/theme.js" defer></script>
    <script src="/assets/js/hq-eye.js?v=20261023eye" defer></script>
    <?php if ($isAuth && $navPayload): ?>
    <script>window.MOVA_HQ_NAV = <?= json_encode($navPayload, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <?php endif; ?>
    <script src="/assets/js/hq-nav.js?v=20261023uni" defer></script>
    <?php if ($isAuth): ?>
    <script src="/assets/js/hq-search.js?v=20261023uni" defer></script>
    <?php endif; ?>
    <script src="/assets/js/hq-layers.js?v=20261023uni" defer></script>
    <script src="/assets/js/hq-p5.js?v=20261023uni" defer></script>
    <?php if ($isAuth): ?>
    <script>
    window.MovaAiContext = {
      route: <?= json_encode(($_SERVER['REQUEST_URI'] ?? '/hq')) ?>,
      area: <?= json_encode($workspace) ?>,
      layer: <?= json_encode($_GET['layer'] ?? '') ?>,
      userName: <?= json_encode($user['name'] ?? '') ?>,
      entityId: (function () {
        var m = (<?= json_encode(($_SERVER['REQUEST_URI'] ?? '')) ?> || '').match(/\/hq\/content\/edit\/(\d+)/);
        return m ? parseInt(m[1], 10) : null;
      })()
    };
    </script>
    <script src="/assets/js/mova-markdown.js?v=20260930md1"></script>
    <?php if (\Mova\Hq\RouteRegistry::allowed('use_ai')): ?>
    <script src="/assets/js/mova-ai-panel.js?v=20261023uni" defer></script>
    <?php endif; ?>
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
 * Line 1 breadcrumb (section › parent › page).
 */
function hqBreadcrumbHtml(?array $nav): string
{
    $trail = $nav['breadcrumb'] ?? [['label' => 'Overview', 'href' => '/hq', 'icon' => 'fa-solid fa-house']];
    $h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $out = '<nav class="hq-crumbs" id="hq-crumbs" aria-label="Breadcrumb"><ol>';
    $last = count($trail) - 1;
    foreach ($trail as $i => $c) {
        $icon = $i === 0 && !empty($c['icon']) ? '<i class="' . $h($c['icon']) . '" aria-hidden="true"></i>' : '';
        $cls = $i === 0 ? ' class="hq-crumb-root"' : '';
        if ($i === $last) {
            $out .= '<li' . $cls . '><span aria-current="page"' . ($i === 0 ? ' id="hq-section-name"' : '') . '>' . $icon . '<span>' . $h($c['label']) . '</span></span></li>';
        } else {
            $out .= '<li' . $cls . '><a href="' . $h($c['href']) . '"' . ($i === 0 ? ' id="hq-section-name"' : '') . '>' . $icon . '<span>' . $h($c['label']) . '</span></a></li>';
        }
    }
    return $out . '</ol></nav>';
}

/**
 * Line 2: history buttons + section items + (optional) page tools, rendered server-side so the
 * nav is present without JS and never flashes; hq-nav.js adds the live active state,
 * indicator, edge fades and session history.
 */
function hqLine2Html(?array $nav): string
{
    $h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $section = (string) ($nav['section'] ?? 'overview');
    $sectionLabel = (string) ($nav['sections'][$section]['label'] ?? 'Overview');
    $routeId = (string) ($nav['route']['id'] ?? '');
    $parentIds = [];
    try {
        $r = $routeId !== '' ? \Mova\Hq\RouteRegistry::get($routeId) : null;
        while ($r && !empty($r['parent'])) {
            $parentIds[] = $r['parent'];
            $r = \Mova\Hq\RouteRegistry::get($r['parent']);
        }
    } catch (\Throwable $e) {
    }
    $icon = static fn($cls) => $cls ? '<i class="' . $h($cls) . '" aria-hidden="true"></i>' : '';

    $out = '<nav class="hq-line2" id="hq-contextual-nav" aria-label="' . $h($sectionLabel) . ' navigation" data-section="' . $h($section) . '" data-route="' . $h($routeId) . '">';
    $out .= '<div class="hq-nav-hist" role="group" aria-label="History">'
        . '<button type="button" class="hq-hist-btn" id="hq-nav-back" aria-label="Go back" title="Go back" disabled><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>'
        . '<button type="button" class="hq-hist-btn" id="hq-nav-forward" aria-label="Go forward" title="Go forward" disabled><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>'
        . '</div><span class="hq-nav-divider" aria-hidden="true"></span>';
    $out .= '<div class="hq-nav-viewport" id="hq-nav-viewport"><div class="hq-nav-scroller" id="hq-nav-scroller" tabindex="-1"><ul class="hq-nav-track" role="list">';
    // Phase 5: items carrying a registry 'group' (other than the primary "library" group) are
    // folded into a dropdown trigger so long sections (Content) stay scannable. URLs are unchanged.
    $groupMeta = [
        'structure' => ['label' => 'Structure', 'icon' => 'fa-solid fa-sitemap'],
        'tools' => ['label' => 'Tools', 'icon' => 'fa-solid fa-screwdriver-wrench'],
    ];
    // Unique per level (as the original hq-nav contexts): a route with its own children shows ONLY
    // those children on Line 2, replacing its siblings. Going back up is the breadcrumb / Back button.
    $sub = $nav['sub'] ?? [];
    $navItems = $sub ? [] : ($nav['nav'] ?? []);
    $groupCount = [];
    foreach ($navItems as $item) {
        $g = (string) ($item['group'] ?? '');
        if (isset($groupMeta[$g])) {
            $groupCount[$g] = ($groupCount[$g] ?? 0) + 1;
        }
    }
    $isActive = static fn(array $item) => isset($item['id']) && ($item['id'] === $routeId || in_array($item['id'], $parentIds, true));
    $renderedGroups = [];
    foreach ($navItems as $item) {
        $g = (string) ($item['group'] ?? '');
        if (isset($groupMeta[$g]) && ($groupCount[$g] ?? 0) > 1) {
            if (isset($renderedGroups[$g])) {
                continue;
            }
            $renderedGroups[$g] = true;
            $members = array_values(array_filter($navItems, static fn($it) => ($it['group'] ?? '') === $g));
            $cur = null;
            foreach ($members as $m) {
                if ($isActive($m)) {
                    $cur = $m;
                }
            }
            $menuId = 'hq-navgroup-' . $h($g);
            $out .= '<li class="hq-nav-group" data-group="' . $h($g) . '">'
                . '<button type="button" class="hq-nav-item hq-nav-grouptrigger' . ($cur ? ' active' : '') . '" aria-haspopup="menu" aria-expanded="false" aria-controls="' . $menuId . '"'
                . ($cur ? ' aria-current="page"' : '') . '>'
                . $icon($cur['icon'] ?? $groupMeta[$g]['icon']) . '<span>' . $h($groupMeta[$g]['label']) . '</span>'
                . ($cur ? '<em class="hq-nav-groupcur">' . $h($cur['label']) . '</em>' : '')
                . '<i class="fa-solid fa-chevron-down hq-nav-caret" aria-hidden="true"></i></button>'
                . '<div class="hq-nav-menu" id="' . $menuId . '" role="menu" aria-label="' . $h($groupMeta[$g]['label']) . '" hidden>';
            foreach ($members as $m) {
                $on = $isActive($m);
                $out .= '<a role="menuitem" class="hq-nav-menuitem' . ($on ? ' active' : '') . '" href="' . $h($m['href']) . '"'
                    . (isset($m['id']) ? ' data-nav-id="' . $h($m['id']) . '"' : '')
                    . ($on ? ' aria-current="page"' : '') . '>' . $icon($m['icon'] ?? '') . '<span>' . $h($m['label']) . '</span></a>';
            }
            $out .= '</div></li>';
            continue;
        }
        $active = $isActive($item);
        $out .= '<li><a class="hq-nav-item' . ($active ? ' active' : '') . '" href="' . $h($item['href']) . '"'
            . (isset($item['id']) ? ' data-nav-id="' . $h($item['id']) . '"' : '')
            . (!empty($item['prefix']) ? ' data-prefix="1"' : '')
            . ($active ? ' aria-current="page"' : '') . '>' . $icon($item['icon'] ?? '') . '<span>' . $h($item['label']) . '</span></a></li>';
    }
    if ($sub) {
        foreach ($sub as $s) {
            $cls = 'hq-nav-item is-sub' . (!empty($s['primary']) ? ' primary' : '');
            $data = '';
            if (!empty($s['layer'])) {
                $data .= ' data-hq-layer="' . $h($s['layer']) . '"';
            }
            if (!empty($s['match']) && is_array($s['match'])) {
                $data .= " data-match='" . $h(json_encode($s['match'])) . "'";
            }
            if (!empty($s['prefix'])) {
                $data .= ' data-prefix="1"';
            }
            $inner = $icon($s['icon'] ?? '') . '<span>' . $h($s['label']) . '</span>';
            if (!empty($s['action'])) {
                $out .= '<li><button type="button" class="' . $cls . '" data-hq-action="' . $h($s['action']) . '" aria-pressed="false">' . $inner . '</button></li>';
            } elseif (!empty($s['href'])) {
                $out .= '<li><a class="' . $cls . '" href="' . $h($s['href']) . '"' . $data . '>' . $inner . '</a></li>';
            }
        }
    }
    // "More" overflow menu — filled by hq-p5.js when the track is wider than the viewport.
    $out .= '<li class="hq-nav-more" hidden><button type="button" class="hq-nav-item hq-nav-moretrigger" aria-haspopup="menu" aria-expanded="false" aria-controls="hq-navgroup-more">'
        . '<i class="fa-solid fa-ellipsis" aria-hidden="true"></i><span>More</span></button>'
        . '<div class="hq-nav-menu" id="hq-navgroup-more" role="menu" aria-label="More" hidden></div></li>';
    $out .= '</ul><span class="hq-nav-indicator" aria-hidden="true"></span></div></div></nav>';
    return $out;
}

/**
 * Mobile navigation drawer (hamburger): every section the user can use, with its nav items.
 * Built from the same RouteRegistry data as Line 2, so permissions and plugins stay in sync.
 */
function hqDrawerHtml(?array $nav): string
{
    if (!$nav) {
        return '';
    }
    $h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $current = (string) ($nav['section'] ?? 'overview');
    $out = '<div class="hq-drawer-backdrop" id="hq-drawer-backdrop" hidden></div>'
        . '<nav class="hq-drawer" id="hq-drawer" aria-label="All sections" hidden>'
        . '<div class="hq-drawer-head"><span class="hq-logo-mark" aria-hidden="true">M</span><strong>Mova HQ</strong>'
        . '<button type="button" class="hq-icon-btn" id="hq-drawer-close" aria-label="Close menu"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>'
        . '<div class="hq-drawer-body">';
    try {
        foreach (\Mova\Hq\RouteRegistry::sections() as $id => $sec) {
            $items = \Mova\Hq\RouteRegistry::sectionNav($id);
            if ($id === 'overview') {
                $items = array_values(array_filter($items, static fn($it) => isset($it['id'])));
            }
            if (!$items) {
                continue;
            }
            $open = $id === $current;
            $out .= '<details class="hq-drawer-sec"' . ($open ? ' open' : '') . '><summary><i class="' . $h($sec['icon'] ?? 'fa-solid fa-circle') . '" aria-hidden="true"></i><span>' . $h($sec['label'] ?? $id) . '</span></summary><ul class="hq-drawer-links">';
            foreach ($items as $it) {
                $out .= '<li><a href="' . $h($it['href']) . '"><i class="' . $h($it['icon'] ?? '') . '" aria-hidden="true"></i><span>' . $h($it['label']) . '</span></a></li>';
            }
            $out .= '</ul></details>';
        }
    } catch (\Throwable $e) {
    }
    $out .= '</div><div class="hq-drawer-foot"><a class="btn btn-ghost btn-sm" href="/hq/docs"><i class="fa-solid fa-book" aria-hidden="true"></i> Documentation</a><a class="btn btn-ghost btn-sm" href="/hq/logout"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Log out</a></div>';
    return $out . '</nav>';
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

/* ---------- Human times (Africa/Lagos by default; storage stays UTC) ---------- */
function hqTime($v, string $fallback = '—'): string { return \Mova\Core\Time::format($v, $fallback); }
function hqDate($v, string $fallback = '—'): string { return \Mova\Core\Time::date($v, $fallback); }
function hqTimeAgo($v, string $fallback = '—'): string { return \Mova\Core\Time::ago($v, $fallback); }
/** Escaped <time> element; mode = abs|rel|both|date. Safe to echo directly. */
function hqTimeTag($v, string $mode = 'both', string $fallback = '—'): string { return \Mova\Core\Time::tag($v, $mode, $fallback); }
