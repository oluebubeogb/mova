<?php
/** Shared site header (scroll state, desktop nav, CTA, mobile drawer). Expects $F. */
$__e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (!$F['chrome']) {
    return;
}
if ($F['header_html'] !== '') {
    echo '<div class="site-header-assembly">' . $F['header_html'] . '</div>';
    return;
}
$__showLogo = $F['logo'] !== '' && $F['brand_mode'] !== 'name_only';
$__showName = $F['brand_mode'] !== 'logo_only' || $F['logo'] === '';
$__sun = '<svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>';
$__moon = '<svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>';
$__search = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
?>
<a class="mx-skip" href="#main">Skip to content</a>
<header class="site-header mx-header" data-mx-header>
    <div class="mx-header__in">
        <a href="/" class="logo mx-brand" aria-label="<?= $__e($F['site_name']) ?> — home">
<?php if ($__showLogo): ?>
            <img class="logo-img logo-img--light" src="<?= $__e($F['logo']) ?>" alt="" height="32">
<?php if ($F['logo_dark'] !== ''): ?>
            <img class="logo-img logo-img--dark" src="<?= $__e($F['logo_dark']) ?>" alt="" height="32">
<?php endif; ?>
<?php else: ?>
            <span class="mx-brand__mark" aria-hidden="true"><?= $__e(\Mova\Theme\SiteFrame::initial($F['site_name'])) ?></span>
<?php endif; ?>
<?php if ($__showName): ?>
            <span class="mx-brand__name"><?= $__e($F['site_name']) ?></span>
<?php endif; ?>
        </a>
<?php if ($F['nav']): ?>
        <nav class="mx-nav" id="site-nav" aria-label="Main">
<?php foreach ($F['nav'] as $__it): ?>
            <a href="<?= $__e($__it['url']) ?>"<?= $__it['active'] ? ' aria-current="page"' : '' ?><?= $__it['external'] ? ' target="_blank" rel="noopener"' : '' ?>><?= $__e($__it['label']) ?></a>
<?php endforeach; ?>
        </nav>
<?php endif; ?>
        <div class="header-actions mx-header__actions">
<?php if ($F['search']): ?>
            <a href="/search" class="mx-icon-btn" aria-label="Search"><?= $__search ?></a>
<?php endif; ?>
            <button type="button" class="mx-icon-btn theme-toggle" data-theme-toggle aria-label="Toggle dark mode"><?= $__sun . $__moon ?></button>
<?php if ($F['cta']): ?>
            <a class="mx-header__cta" href="<?= $__e($F['cta']['url']) ?>"><?= $__e($F['cta']['label']) ?></a>
<?php endif; ?>
            <button type="button" class="mx-icon-btn mx-burger" data-nav-toggle aria-controls="nav-drawer" aria-expanded="false" aria-label="Open menu"><?= $F['mobile_icon'] ?></button>
        </div>
    </div>
    <div class="nav-drawer mx-drawer" id="nav-drawer" hidden>
        <div class="mx-drawer__head">
            <span class="mx-drawer__title"><?= $__e($F['site_name']) ?></span>
            <button type="button" class="mx-icon-btn" data-nav-close aria-label="Close menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
        <nav class="nav-drawer-links" aria-label="Mobile">
            <a href="/">Home</a>
<?php foreach ($F['drawer'] as $__it): ?>
            <a href="<?= $__e($__it['url']) ?>"<?= $__it['active'] ? ' aria-current="page"' : '' ?>><?= $__e($__it['label']) ?></a>
<?php endforeach; ?>
        </nav>
        <div class="mx-drawer__foot">
<?php if ($F['search']): ?>
            <a href="/search" class="nav-drawer-action"><?= $__search ?><span>Search</span></a>
<?php endif; ?>
            <button type="button" class="nav-drawer-action theme-toggle" data-theme-toggle aria-label="Toggle dark mode"><?= $__sun . $__moon ?><span>Appearance</span></button>
<?php if ($F['cta']): ?>
            <a class="mx-header__cta mx-header__cta--block" href="<?= $__e($F['cta']['url']) ?>"><?= $__e($F['cta']['label']) ?></a>
<?php endif; ?>
        </div>
    </div>
</header>
<div class="nav-backdrop" data-nav-backdrop hidden></div>
