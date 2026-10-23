<?php
/** Shared site footer (columns + bar). Expects $F. */
$__e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (!$F['chrome']) {
    return;
}
if ($F['footer_html'] !== '') {
    echo '<div class="site-footer-assembly">' . $F['footer_html'] . '</div>';
    return;
}
$__ft = trim($F['footer_text']);
$__ft = $__ft !== '' ? str_replace(['{{site_name}}', '{{year}}'], [$F['site_name'], date('Y')], $__ft) : '';
?>
<footer class="site-footer mx-footer">
    <div class="mx-footer__in">
        <div class="mx-footer__grid">
            <div class="mx-footer__brand">
                <a href="/" class="mx-brand mx-brand--footer">
<?php if ($F['logo'] !== ''): ?>
                    <img class="logo-img logo-img--light" src="<?= $__e($F['logo']) ?>" alt="" height="30">
<?php if ($F['logo_dark'] !== ''): ?>
                    <img class="logo-img logo-img--dark" src="<?= $__e($F['logo_dark']) ?>" alt="" height="30">
<?php endif; ?>
<?php else: ?>
                    <span class="mx-brand__mark" aria-hidden="true"><?= $__e(\Mova\Theme\SiteFrame::initial($F['site_name'])) ?></span>
<?php endif; ?>
                    <span class="mx-brand__name"><?= $__e($F['site_name']) ?></span>
                </a>
<?php if ($F['site_desc'] !== ''): ?>
                <p class="mx-footer__tag"><?= $__e($F['site_desc']) ?></p>
<?php endif; ?>
            </div>
<?php foreach ($F['footer_cols'] as $__c): ?>
            <div class="mx-footer__col">
<?php if (!empty($__c['title'])): ?>
                <h2 class="mx-footer__title footer-col-title"><?= $__e($__c['title']) ?></h2>
<?php endif; ?>
<?php if (!empty($__c['text'])): ?>
                <p class="mx-footer__text footer-col-text"><?= nl2br($__e(\Mova\Theme\SiteFrame::stripTokens((string) $__c['text']))) ?></p>
<?php endif; ?>
<?php if (!empty($__c['links']) && is_array($__c['links'])): ?>
                <ul class="mx-footer__links footer-col-links">
<?php foreach ($__c['links'] as $__l): if (empty($__l['label']) || empty($__l['url'])) { continue; } ?>
                    <li><a href="<?= $__e($__l['url']) ?>"><?= $__e($__l['label']) ?></a></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>
<?php endforeach; ?>
        </div>
        <div class="mx-footer__bar site-footer-bar">
            <span><?= $__ft !== '' ? $__e($__ft) : '&copy; ' . date('Y') . ' ' . $__e($F['site_name']) . '. All rights reserved.' ?></span>
            <span class="mx-footer__meta"><a href="/feed.xml">RSS</a><a href="/sitemap.xml">Sitemap</a><a href="#main" class="mx-totop" aria-label="Back to top">Top <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg></a></span>
        </div>
    </div>
</footer>
