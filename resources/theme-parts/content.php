<?php
/** Shared single-content view (all themes). Canvas pages render full-width sections. */
$content = $content ?? null;
if (!$content) {
    echo '<div class="empty-state"><p>Content not found.</p></div>';
    return;
}
$__body = \Mova\Theme\SiteFrame::body($content);
$__isDev = (($content['meta']['editor_mode'] ?? '') === 'dev');
$__bare = $__isDev && (($content['meta']['use_site_chrome'] ?? '0') !== '1');
if ($__bare) {
    echo $__body;
    return;
}
if (\Mova\Theme\SiteFrame::isCanvas($content)) {
    echo '<div class="mx-canvas">' . $__body . '</div>';
    return;
}
$__hide = !empty($content['meta']['hide_article_chrome']) && $content['meta']['hide_article_chrome'] !== '0';
$__isPost = in_array((string) ($content['type'] ?? ''), ['post', 'article', 'news'], true);
?>
<article class="article<?= $__isPost ? ' article--post' : ' article--page' ?>">
<?php if (!$__hide): ?>
    <header class="article-header">
<?php if ($__isPost): ?>
        <a class="mx-tag" href="/blog"><?= htmlspecialchars(ucfirst((string) $content['type'])) ?></a>
<?php endif; ?>
        <h1><?= htmlspecialchars((string) $content['title']) ?></h1>
<?php if (!empty($content['excerpt']) && !$__isPost && strlen((string) $content['excerpt']) < 240): ?>
        <p class="article-lead"><?= htmlspecialchars((string) $content['excerpt']) ?></p>
<?php endif; ?>
<?php if ($__isPost): ?>
        <div class="article-meta">
<?php if (!empty($content['published_at'])): ?>
            <time datetime="<?= htmlspecialchars((string) $content['published_at']) ?>"><?= date('F j, Y', strtotime((string) $content['published_at'])) ?></time>
<?php endif; ?>
<?php $__words = str_word_count(strip_tags($__body)); if ($__words > 120): ?>
            <span><?= max(1, (int) round($__words / 220)) ?> min read</span>
<?php endif; ?>
        </div>
<?php endif; ?>
    </header>
<?php endif; ?>
<?php if (!empty($content['featured_image'])): ?>
    <figure class="article-featured">
        <?= mova_img((string) $content['featured_image'], (string) ($content['title'] ?? ''), ['priority' => true, 'class' => 'article-featured-img', 'sizes' => '(max-width: 768px) 100vw, 900px']) ?>
    </figure>
<?php endif; ?>
    <div class="article-body">
        <?= $__body ?>
    </div>
</article>
