<?php
/** Single content view */
$content = $content ?? null;
if (!$content) {
    echo '<p>Content not found.</p>';
    return;
}

$hideChrome = !empty($content['meta']['hide_article_chrome']) && $content['meta']['hide_article_chrome'] !== '0';
?>
<article class="article">
    <?php if (!$hideChrome): ?>
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
  <?php endif; ?>

        <?php if (!empty($content['featured_image'])): ?>
      <figure class="article-featured">
        <?= function_exists('mova_img')
            ? mova_img((string)($content['featured_image'] ?? ''), (string)($content['title'] ?? ''), [
                'priority' => true,
                'class' => 'article-featured-img',
                'sizes' => '(max-width: 768px) 100vw, 1200px',
              ])
            : '' ?>
      </figure>
      <?php endif; ?>

    <div class="article-body">
        <?php
$__body = $content['body'] ?? '';
if (class_exists(\Mova\Media\ImageTag::class)) {
    $__body = \Mova\Media\ImageTag::upgradeBody($__body);
}
echo $__body;
?>
    </div>
</article>
