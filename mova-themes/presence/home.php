<?php
/** Presence — Homepage */
$siteName = mova_setting('site_name', 'Mova');
$siteDesc = mova_setting('site_description', 'Content that moves.');
$posts = $posts ?? [];
?>
<section class="hero presence-hero">
  <div class="presence-hero-bg" aria-hidden="true">
    <div class="presence-hero-lines"><span></span><span></span><span></span></div>
  </div>
  <p class="presence-kicker presence-reveal" data-presence>
    <span class="presence-kicker-dot"></span>
    Welcome
  </p>
  <h1 class="presence-reveal" data-presence><?= htmlspecialchars($siteName) ?></h1>
  <p class="lead presence-reveal" data-presence><?= htmlspecialchars($siteDesc) ?></p>
</section>

<section class="content-list">
  <?php if (empty($posts)): ?>
    <div class="empty-state presence-reveal" data-presence>
      <p>No published content yet.</p>
      <p><a href="/hq">Open Mova HQ</a> to create your first piece.</p>
    </div>
  <?php else: ?>
    <h2 class="presence-section-title presence-reveal" data-presence>Latest</h2>
    <?php foreach ($posts as $i => $item): ?>
      <article class="content-card presence-card presence-reveal" data-presence style="--presence-i: <?= (int) $i ?>">
        <h3>
          <a href="/<?= htmlspecialchars($item['slug']) ?>">
            <?= htmlspecialchars($item['title']) ?>
          </a>
        </h3>
        <?php if (!empty($item['excerpt'])): ?>
          <p><?= htmlspecialchars($item['excerpt']) ?></p>
        <?php endif; ?>
        <div class="content-meta">
          <?php if (!empty($item['published_at'])): ?>
            <time datetime="<?= htmlspecialchars($item['published_at']) ?>">
              <?= date('M j, Y', strtotime($item['published_at'])) ?>
            </time>
          <?php endif; ?>
          <?php if (!empty($item['type'])): ?>
            <span><?= htmlspecialchars(ucfirst($item['type'])) ?></span>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
