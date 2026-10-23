<?php
/** @var array $blog */
/** @var array $feed */
/** @var string $html */
$blog = $blog ?? [];
$feed = $feed ?? ['items' => [], 'has_more' => false, 'next_offset' => 0];
$html = $html ?? '';
$id = (int)($blog['id'] ?? 0);
$limit = (int)($blog['per_page'] ?? 12);
?>
<link rel="stylesheet" href="/assets/css/mova-blog.css?v=6">
<section class="mova-blog-page">
  <h1 class="mova-blog-page-title"><?= htmlspecialchars($blog['title'] ?? 'Blog') ?></h1>
  <?php if (!empty($blog['description'])): ?>
    <p class="mova-blog-page-desc"><?= htmlspecialchars($blog['description']) ?></p>
  <?php endif; ?>

  <div class="mova-blog-feed"
       data-blog-id="<?= $id ?>"
       data-offset="<?= (int)($feed['next_offset'] ?? $limit) ?>"
       data-limit="<?= $limit ?>"
       data-has-more="<?= !empty($feed['has_more']) ? '1' : '0' ?>">
    <?= $html ?>
  </div>
</section>
<script src="/assets/js/mova-blog.js?v=3" defer></script>
