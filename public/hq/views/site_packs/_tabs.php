<?php
/** Tabs shared by HQ → Backups and Export & import. @var string $spTabActive 'backups'|'packs' */
$spTabActive = $spTabActive ?? 'packs';
?>
<nav class="sp-tabs" aria-label="Backups sections">
    <a href="/hq/backups" class="sp-tab<?= $spTabActive === 'backups' ? ' is-active' : '' ?>"<?= $spTabActive === 'backups' ? ' aria-current="page"' : '' ?>><i class="fa-solid fa-box-archive" aria-hidden="true"></i> Backups</a>
    <a href="/hq/backups/packs" class="sp-tab<?= $spTabActive === 'packs' ? ' is-active' : '' ?>"<?= $spTabActive === 'packs' ? ' aria-current="page"' : '' ?>><i class="fa-solid fa-right-left" aria-hidden="true"></i> Export &amp; import</a>
</nav>
<link rel="stylesheet" href="/assets/css/site-packs.css?v=20261024sp">
