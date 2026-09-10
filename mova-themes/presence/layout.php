<?php
/**
 * Presence theme layout
 * Sleek HQ-inspired dual-tone washes + scroll-triggered presence animations
 * Variables: $view, $themePath, $seo, plus view-specific data
 */
use Mova\Core\Bootstrap;
use Mova\Core\Database;
use Mova\Theme\DesignConfig;

if (!function_exists('mova_setting')) {
    function mova_setting(string $key, $default = null) {
        static $cache = [];
        if (!array_key_exists($key, $cache)) {
            try {
                $row = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = :k", ['k' => $key]);
                $cache[$key] = $row['setting_value'] ?? $default;
            } catch (\Throwable $e) {
                $cache[$key] = $default;
            }
        }
        return $cache[$key] ?? $default;
    }
}

$siteName = mova_setting('site_name', Bootstrap::config('app_name', 'Mova'));
$siteDesc = mova_setting('site_description', Bootstrap::config('app_tagline', 'Content that moves.'));
$logoUrl = mova_setting('logo_url', '');
$faviconUrl = mova_setting('favicon_url', '');
$brandColor = mova_setting('brand_color', '#2563eb');
$footerText = mova_setting('footer_text', '');
$navRaw = mova_setting('nav_links', "Search|/search");
$navItems = [];
foreach (preg_split('/\r\n|\r|\n/', (string) $navRaw) as $line) {
    $line = trim($line);
    if ($line === '' || strpos($line, '|') === false) {
        continue;
    }
    [$label, $url] = array_map('trim', explode('|', $line, 2));
    if ($label !== '' && $url !== '') {
        $navItems[] = ['label' => $label, 'url' => $url];
    }
}
if (!$navItems) {
    $navItems = [['label' => 'Search', 'url' => '/search']];
}

// Mobile menu icon (Design → Layout). Presets = inline SVG so icons always show.
$needFontAwesome = false;
$mobileIconHtml = '';
if (class_exists(\Mova\Theme\DesignConfig::class)) {
    $iconPack = \Mova\Theme\DesignConfig::mobileMenuIcon();
    $mobileIconHtml = $iconPack['html'] ?? '';
    $needFontAwesome = !empty($iconPack['need_fa']);
} else {
    $mobileIconHtml = '<svg class="nav-toggle-svg" xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>';
}

$contentData = $content ?? null;
$useSiteChrome = true;
if (is_array($contentData) && (($contentData['meta']['editor_mode'] ?? '') === 'dev')) {
    $useSiteChrome = (($contentData['meta']['use_site_chrome'] ?? '0') === '1');
}
$headHtml = '';
if (isset($seo) && is_object($seo)) {
    $overrides = [];
    if (!empty($title)) {
        $overrides['title'] = $title;
    }
    $headHtml = $seo->renderHead($contentData, $overrides);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?= htmlspecialchars($brandColor) ?>">
    <script>document.documentElement.setAttribute('data-mova-hq','0');</script>
    <script src="/assets/js/theme-boot.js"></script>
    <?php /* presence-anim class is added by presence-animate.js so content stays visible if JS is blocked */ ?>
    <?= $headHtml ?: '<title>' . htmlspecialchars($siteName) . '</title>' ?>
    <?php if ($faviconUrl): ?>
        <link rel="icon" href="<?= htmlspecialchars($faviconUrl) ?>">
    <?php endif; ?>
<?php if ($needFontAwesome): ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/mova.css">
    <?php
    // Self-contained: load Presence CSS from theme folder (no /assets dependency)
    $presenceCssFile = (isset($themePath) ? $themePath : __DIR__) . '/presence.css';
    if (is_file($presenceCssFile)) {
        echo "<style id=\"presence-theme\">\n" . file_get_contents($presenceCssFile) . "\n</style>\n";
    } elseif (is_file(__DIR__ . '/presence.css')) {
        echo "<style id=\"presence-theme\">\n" . file_get_contents(__DIR__ . '/presence.css') . "\n</style>\n";
    } else {
        echo '<link rel="stylesheet" href="/assets/css/presence.css">' . "\n";
    }
    ?>

    <?php
    if (class_exists(\Mova\Theme\ElementStyles::class)) {
        $elCss = \Mova\Theme\ElementStyles::compileCss();
        if ($elCss !== '') {
            echo "<style id=\"mova-element-styles\">\n" . $elCss . "\n</style>\n";
        }
    }
    ?>

    <?php
    if (class_exists(\Mova\Plugin\PluginManager::class)) {
        \Mova\Plugin\PluginManager::doAction('theme.render.head', [
            'content' => $content ?? null,
            'view' => $view ?? null,
            'seo' => $seo ?? null,
        ]);
    }
    ?>
    <style>
<?php
if (class_exists(\Mova\Theme\DesignConfig::class)) {
    echo \Mova\Theme\DesignConfig::cssVariables();
} else {
?>
        :root, [data-theme="light"] { --color-accent: <?= htmlspecialchars($brandColor) ?>; --color-accent-hover: <?= htmlspecialchars($brandColor) ?>; --color-quote-border: <?= htmlspecialchars($brandColor) ?>; }
        [data-theme="dark"] { --color-accent: <?= htmlspecialchars($brandColor) ?>; --color-accent-hover: <?= htmlspecialchars($brandColor) ?>; --color-quote-border: <?= htmlspecialchars($brandColor) ?>; }
<?php } ?>
    </style>
</head>

<?php
$designLayout = class_exists(\Mova\Theme\DesignConfig::class) ? \Mova\Theme\DesignConfig::layout() : [];
$designComponents = class_exists(\Mova\Theme\DesignConfig::class) ? \Mova\Theme\DesignConfig::components() : [];
$headerCfg = $designLayout['header'] ?? [];
$footerCfg = $designLayout['footer'] ?? [];
$bodyClasses = [];
$headerType = preg_replace('/[^a-z0-9\-]/', '', (string)($headerCfg['type'] ?? 'sticky')) ?: 'sticky';
$logoPos = preg_replace('/[^a-z0-9\-]/', '', (string)($headerCfg['logo_position'] ?? 'left')) ?: 'left';
$navAlign = preg_replace('/[^a-z0-9\-]/', '', (string)($headerCfg['nav_align'] ?? 'right')) ?: 'right';
$footerStyle = preg_replace('/[^a-z0-9\-]/', '', (string)($footerCfg['style'] ?? 'simple')) ?: 'simple';
$mobileNavCfg = $designLayout['mobile_nav'] ?? [];
$mobileNavStyle = preg_replace('/[^a-z0-9\-]/', '', (string)($mobileNavCfg['style'] ?? 'drawer-right')) ?: 'drawer-right';
if ($mobileNavStyle === 'drawer') { $mobileNavStyle = 'drawer-top'; }
$mobileNavAlign = preg_replace('/[^a-z]/', '', (string)($mobileNavCfg['content_align'] ?? 'left')) ?: 'left';
if (!in_array($mobileNavAlign, ['left', 'center', 'right'], true)) { $mobileNavAlign = 'left'; }

$btnVar = preg_replace('/[^a-z0-9\-]/', '', (string)(($designComponents['button']['variant'] ?? 'solid'))) ?: 'solid';
$cardVar = preg_replace('/[^a-z0-9\-]/', '', (string)(($designComponents['card']['variant'] ?? 'elevated'))) ?: 'elevated';
$heroVar = preg_replace('/[^a-z0-9\-]/', '', (string)(($designComponents['hero']['variant'] ?? 'centered'))) ?: 'centered';
$cardHover = preg_replace('/[^a-z0-9\-]/', '', (string)(($designComponents['card']['hover'] ?? 'lift'))) ?: 'lift';
$btnUpper = !empty($designComponents['button']['uppercase']);
$headerTransparent = !empty($headerCfg['transparent']);
?>
<body class="theme-presence header-<?= htmlspecialchars($headerType) ?> logo-<?= htmlspecialchars($logoPos) ?> nav-<?= htmlspecialchars($navAlign) ?> footer-<?= htmlspecialchars($footerStyle) ?> mobile-nav-<?= htmlspecialchars($mobileNavStyle) ?> mobile-nav-align-<?= htmlspecialchars($mobileNavAlign) ?> btn-<?= htmlspecialchars($btnVar) ?> card-<?= htmlspecialchars($cardVar) ?> hero-<?= htmlspecialchars($heroVar) ?> card-hover-<?= htmlspecialchars($cardHover) ?><?= $btnUpper ? ' btn-uppercase' : '' ?><?= $headerTransparent ? ' header-transparent' : '' ?>">

    <?php
    $headerAsmSlug = function_exists('mova_setting') ? trim((string) mova_setting('header_assembly_slug', '')) : '';
    $footerAsmSlug = function_exists('mova_setting') ? trim((string) mova_setting('footer_assembly_slug', '')) : '';
    $headerAsmHtml = '';
    $footerAsmHtml = '';
    if (($headerAsmSlug !== '' || $footerAsmSlug !== '') && class_exists(\Mova\Assembly\AssemblyService::class)) {
        $__asm = new \Mova\Assembly\AssemblyService();
        $siteNm = function_exists('mova_setting') ? (string) mova_setting('site_name', 'Mova') : 'Mova';
        if ($headerAsmSlug !== '') {
            $headerAsmHtml = $__asm->renderBySlug($headerAsmSlug);
            $headerAsmHtml = str_replace(['{{site_name}}', '{{year}}'], [htmlspecialchars($siteNm), date('Y')], $headerAsmHtml);
        }
        if ($footerAsmSlug !== '') {
            $footerAsmHtml = $__asm->renderBySlug($footerAsmSlug);
            $footerAsmHtml = str_replace(['{{site_name}}', '{{year}}'], [htmlspecialchars($siteNm), date('Y')], $footerAsmHtml);
        }
    }
    // Desktop nav: skip Home (logo links home) and Search (icon control)
    $navDesktop = [];
    foreach ($navItems as $item) {
        $lab = strtolower(trim((string) ($item['label'] ?? '')));
        $url = rtrim((string) ($item['url'] ?? ''), '/');
        if ($lab === 'home' || $url === '' || $url === '/') {
            continue;
        }
        if ($lab === 'search' || $url === '/search') {
            continue;
        }
        $navDesktop[] = $item;
    }
    ?>
    <?php if (!empty($useSiteChrome)): ?>
        <?php if ($headerAsmHtml !== ''): ?>
            <div class="site-header-assembly"><?= $headerAsmHtml ?></div>
        <?php else: ?>
            <header class="site-header presence-header">
                <div class="container">
                    <a href="/" class="logo">
                        <?php if ($logoUrl): ?>
                            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($siteName) ?>" style="height:28px;width:auto;vertical-align:middle;">
                        <?php else: ?>
                            <?= htmlspecialchars($siteName) ?>
                        <?php endif; ?>
                    </a>
                    <div class="header-actions">
                        <?php if (!empty($navDesktop)): ?>
                        <nav class="nav" id="site-nav" aria-label="Main">
                            <?php foreach ($navDesktop as $item): ?>
                                <a href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label']) ?></a>
                            <?php endforeach; ?>
                        </nav>
                        <?php endif; ?>
                        <a href="/search" class="header-icon-btn" aria-label="Search" title="Search">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                        </a>
                        <button type="button" class="theme-toggle header-icon-btn header-icon-btn--circle" data-theme-toggle aria-label="Toggle light and dark mode" title="Toggle theme">
                            <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
                            <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 14.5A8.5 8.5 0 1 1 9.5 3a7 7 0 0 0 11.5 11.5z"/></svg>
                        </button>
                        <button type="button" class="nav-toggle header-icon-btn" data-nav-toggle aria-label="Open menu" aria-expanded="false" aria-controls="nav-drawer">
                            <?= $mobileIconHtml ?>
                        </button>
                    </div>
                </div>
                <div class="nav-drawer" id="nav-drawer" hidden>
                    <nav class="nav-drawer-links" aria-label="Mobile">
                        <?php foreach ($navItems as $item):
                            $lab = strtolower(trim((string) ($item['label'] ?? '')));
                            $url = rtrim((string) ($item['url'] ?? ''), '/');
                            if ($lab === 'home' || $url === '' || $url === '/') continue;
                            if ($lab === 'search' || $url === '/search') continue;
                        ?>
                            <a href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label']) ?></a>
                        <?php endforeach; ?>
                        <a href="/search" class="nav-drawer-action">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                            <span>Search</span>
                        </a>
                        <button type="button" class="nav-drawer-action theme-toggle" data-theme-toggle aria-label="Toggle theme">
                            <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
                            <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 14.5A8.5 8.5 0 1 1 9.5 3a7 7 0 0 0 11.5 11.5z"/></svg>
                            <span>Theme</span>
                        </button>
                    </nav>
                </div>

            </header>
            <div class="nav-backdrop" data-nav-backdrop hidden></div>
        <?php endif; ?>
    <?php endif; ?>

    <main class="site-main<?= empty($useSiteChrome) ? ' is-bare' : '' ?>">
        <?php if (!empty($useSiteChrome)): ?>
        <div class="container">
            <?php include $themePath . '/' . $view . '.php'; ?>
        </div>
        <?php else: ?>
            <?php include $themePath . '/' . $view . '.php'; ?>
        <?php endif; ?>
    </main>

    <?php if (!empty($useSiteChrome)): ?>
        <?php if ($footerAsmHtml !== ''): ?>
            <div class="site-footer-assembly"><?= $footerAsmHtml ?></div>
        <?php else: ?>
            <footer class="site-footer presence-footer">
                <div class="container">
                    <span>
                        <?php if ($footerText): ?>
                            <?= htmlspecialchars($footerText) ?>
                        <?php else: ?>
                            &copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?>
                        <?php endif; ?>
                    </span>
                    <span>
                        <a href="/feed.xml">Feed</a>
                        &middot;
                        <a href="/llms.txt">llms.txt</a>
                    </span>
                </div>
            </footer>
        <?php endif; ?>
    <?php endif; ?>

<script src="/assets/js/theme.js" defer></script>
    <?php
    // Self-contained: load Presence JS from theme folder
    $presenceJsFile = (isset($themePath) ? $themePath : __DIR__) . '/presence-animate.js';
    if (!is_file($presenceJsFile) && is_file(__DIR__ . '/presence-animate.js')) {
        $presenceJsFile = __DIR__ . '/presence-animate.js';
    }
    if (is_file($presenceJsFile)) {
        echo "<script id=\"presence-animate\">\n" . file_get_contents($presenceJsFile) . "\n</script>\n";
    } else {
        echo '<script src="/assets/js/presence-animate.js" defer></script>' . "\n";
    }
    ?>

    <?php
    if (class_exists(\Mova\Theme\ElementStyles::class)) {
        $elJs = \Mova\Theme\ElementStyles::compileJs();
        if ($elJs !== '') {
            echo "<script id=\"mova-element-js\">\n" . $elJs . "\n</script>\n";
        }
    }
    ?>

    <script src="/assets/js/nav.js" defer></script>
    <?php
    if (class_exists(\Mova\Plugin\PluginManager::class)) {
        \Mova\Plugin\PluginManager::doAction('theme.render.footer', [
            'content' => $content ?? null,
            'view' => $view ?? null,
            'seo' => $seo ?? null,
        ]);
    }
    ?>
</body>
</html>
