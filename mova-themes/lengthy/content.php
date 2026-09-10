<?php
/** Lengthy — Single content view */
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

  <p class="article-footer-actions">
    <a href="#" class="back-to-top" data-back-to-top>↑ Back to top</a>
  </p>
</article>
<script>
(function(){var a=document.querySelector('[data-back-to-top]');if(!a)return;a.addEventListener('click',function(e){e.preventDefault();window.scrollTo(0,0);});})();
</script>
