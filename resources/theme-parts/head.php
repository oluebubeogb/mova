<?php
/** Shared <head> for all Mova themes. Expects $F from SiteFrame::build(). */
$__e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php $__pwa = class_exists(\Mova\Site\Pwa::class) && \Mova\Site\Pwa::enabled() ? \Mova\Site\Pwa::config() : null; ?>
    <meta name="theme-color" content="<?= $__e($__pwa ? $__pwa['theme_color'] : $F['brand']) ?>">
    <meta name="generator" content="Mova">
    <script>document.documentElement.setAttribute('data-mova-hq','0');document.documentElement.classList.add('mx-js');if(/[?&]mx_static=1/.test(location.search))document.documentElement.classList.add('mx-static');</script>
    <script src="/assets/js/theme-boot.js"></script>
    <?= $F['head_html'] ?>

    <link rel="icon" href="<?= $__e($F['favicon']) ?>"<?= substr($F['favicon'], -4) === '.svg' ? ' type="image/svg+xml"' : '' ?>>
<?php if (!$__pwa): ?>
    <link rel="apple-touch-icon" href="<?= $__e($F['favicon']) ?>">
<?php endif; ?>
<?php if (class_exists(\Mova\Site\Pwa::class)) { echo \Mova\Site\Pwa::headHtml(); } ?>
    <link rel="preload" href="/assets/fonts/inter-latin-wght.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/fonts/fonts.css?v=<?= $__e($F['ver']) ?>">
<?php if ($F['need_fa']): ?>
    <link rel="preload" as="style" href="/assets/vendor/fontawesome/css/all.min.css?v=6.5.2" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css?v=6.5.2"></noscript>
    <style>@font-face{font-family:"Font Awesome 6 Free";font-display:swap}@font-face{font-family:"Font Awesome 6 Brands";font-display:swap}</style>
<?php endif; ?>
    <link rel="stylesheet" href="/assets/css/mova.css?v=<?= $__e($F['ver']) ?>">
    <link rel="stylesheet" href="/assets/css/templates.css?v=<?= $__e($F['ver']) ?>">
    <link rel="stylesheet" href="/assets/css/site.css?v=<?= $__e($F['ver']) ?>">
    <link rel="stylesheet" href="/assets/css/sections.css?v=<?= $__e($F['ver']) ?>">
<?php foreach ($F['css'] as $__css): ?>
    <?= \Mova\Theme\SiteFrame::inlineFile($__css, 'style', 'theme-' . basename($__css, '.css')) ?>
<?php endforeach; ?>
<?php if (class_exists(\Mova\Theme\VariableService::class)): ?>
    <script>window.MovaVars=<?= \Mova\Theme\VariableService::jsPayload() ?>;</script>
<?php endif; ?>
<?php
if (class_exists(\Mova\Theme\ElementStyles::class)) {
    $__el = \Mova\Theme\ElementStyles::compileCss();
    if ($__el !== '') {
        echo "<style id=\"mova-element-styles\">\n" . $__el . "\n</style>\n";
    }
}
if (class_exists(\Mova\Plugin\PluginManager::class)) {
    \Mova\Plugin\PluginManager::doAction('theme.render.head', [
        'content' => $F['content'],
        'view' => $F['view'],
        'seo' => $F['seo'],
    ]);
}
?>
    <style id="mova-design-tokens">
<?php if (class_exists(\Mova\Theme\DesignConfig::class)) { echo \Mova\Theme\DesignConfig::cssVariables(); } ?>
    </style>
