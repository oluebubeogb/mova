<?php /** Shared scripts. Expects $F. */ ?>
<script src="/assets/js/theme.js?v=<?= htmlspecialchars($F['ver']) ?>"></script>
<script src="/assets/js/nav.js?v=<?= htmlspecialchars($F['ver']) ?>" defer></script>
<script src="/assets/js/site.js?v=<?= htmlspecialchars($F['ver']) ?>" defer></script>
<script src="/assets/js/sections.js?v=<?= htmlspecialchars($F['ver']) ?>" defer></script>
<?php foreach ($F['js'] as $__js): ?>
<?= \Mova\Theme\SiteFrame::inlineFile($__js, 'script', 'theme-' . basename($__js, '.js')) ?>
<?php endforeach; ?>
<?php
if (class_exists(\Mova\Theme\ElementStyles::class)) {
    $__elJs = \Mova\Theme\ElementStyles::compileJs();
    if ($__elJs !== '') {
        echo "<script id=\"mova-element-js\">\n" . $__elJs . "\n</script>\n";
    }
}
if (class_exists(\Mova\Plugin\PluginManager::class)) {
    \Mova\Plugin\PluginManager::doAction('theme.render.footer', [
        'content' => $F['content'],
        'view' => $F['view'],
        'seo' => $F['seo'],
    ]);
}
?>
