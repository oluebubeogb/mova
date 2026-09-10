<?php
/** Single content view */
$content = $content ?? null;
if (!$content) {
    echo '<p>Content not found.</p>';
    return;
}

$isDev = (($content['meta']['editor_mode'] ?? '') === 'dev');
$useChrome = !$isDev || (($content['meta']['use_site_chrome'] ?? '0') === '1');
$bare = $isDev && !$useChrome;

$bodyHtml = $content['body'] ?? '';
if (class_exists(\Mova\Plugin\PluginManager::class)) {
    $bodyHtml = \Mova\Plugin\PluginManager::applyFilters('content.render.body', $bodyHtml, $content);
}

// Bare Dev Mode (no site header/footer): show only the content island — no theme article chrome
if ($bare):
    echo $bodyHtml;
    return;
endif;
?>
<article class="article">
    <header class="article-header">
        <h1><?= htmlspecialchars($content['title']) ?></h1>
        <div class="article-meta">
            <?php if (!empty($content['published_at'])): ?>
                <time datetime="<?= htmlspecialchars($content['published_at']) ?>">
                    <?= date('F j, Y', strtotime($content['published_at'])) ?>
                </time>
            <?php endif; ?>
            <?php if (!empty($content['updated_at']) && ($content['updated_at'] !== ($content['published_at'] ?? ''))): ?>
                <span>Updated <?= date('M j, Y', strtotime($content['updated_at'])) ?></span>
            <?php endif; ?>
            <?php if (!empty($content['type'])): ?>
                <span><?= htmlspecialchars(ucfirst($content['type'])) ?></span>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!empty($content['featured_image'])): ?>
        <figure class="article-featured">
            <?= function_exists('mova_img') ? mova_img((string)($content['featured_image'] ?? ''), (string)($content['title'] ?? '')) : '' ?>
                 alt="<?= htmlspecialchars($content['title']) ?>"
                 loading="lazy">
        </figure>
    <?php endif; ?>

    <div class="article-body">
        <?= $bodyHtml ?>
    </div>
</article>
