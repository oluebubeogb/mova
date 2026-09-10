<?php
/** Presence — Search results (always visible, no reveal delay) */
$query = $query ?? '';
$results = $results ?? [];
?>
<section class="search-page">
  <div class="presence-heading-wash" aria-hidden="true"></div>
  <h1>Search</h1>

  <form class="search-form" method="get" action="/search" role="search">
    <label class="sr-only" for="q">Search</label>
    <input type="search" id="q" name="q" value="<?= htmlspecialchars($query) ?>"
           placeholder="Search content…" autofocus>
    <button type="submit">Search</button>
  </form>

  <?php if ($query !== ''): ?>
    <p class="search-results-count">
      <?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?>
      for “<?= htmlspecialchars($query) ?>”
    </p>

    <?php if (empty($results)): ?>
      <div class="empty-state">
        <p>No matches. Try different keywords.</p>
      </div>
    <?php else: ?>
      <div class="content-list">
        <?php foreach ($results as $item): ?>
          <article class="content-card presence-card">
            <h3>
              <a href="/<?= htmlspecialchars($item['slug']) ?>">
                <?= htmlspecialchars($item['title']) ?>
              </a>
            </h3>
            <?php if (!empty($item['search_snippet'])): ?>
              <p class="search-snippet"><?= htmlspecialchars($item['search_snippet']) ?></p>
            <?php elseif (!empty($item['excerpt'])): ?>
              <p><?= htmlspecialchars($item['excerpt']) ?></p>
            <?php endif; ?>
            <div class="content-meta">
              <?php if (!empty($item['published_at'])): ?>
                <time datetime="<?= htmlspecialchars($item['published_at']) ?>">
                  <?= date('M j, Y', strtotime($item['published_at'])) ?>
                </time>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
