<?php
/** Shared search view. */
$query = $query ?? '';
$results = $results ?? [];
?>
<section class="search-page mx-search">
    <p class="mx-tag">Search</p>
    <h1 class="mx-search__title">Find anything on <?= htmlspecialchars((string) mova_setting('site_name', 'this site')) ?></h1>
    <form class="search-form" method="get" action="/search" role="search">
        <label class="sr-only" for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= htmlspecialchars((string) $query) ?>" placeholder="Search pages, posts and events…" autofocus>
        <button type="submit">Search</button>
    </form>
<?php if ($query !== ''): ?>
    <p class="search-results-count"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> for “<?= htmlspecialchars((string) $query) ?>”</p>
<?php if (!$results): ?>
    <div class="empty-state"><p>No matches. Try different keywords.</p></div>
<?php else: ?>
    <div class="mx-listing">
<?php foreach ($results as $item): ?>
        <article class="content-card">
            <h3><a href="/<?= htmlspecialchars((string) $item['slug']) ?>"><?= htmlspecialchars((string) $item['title']) ?></a></h3>
<?php if (!empty($item['search_snippet'])): ?>
            <p class="search-snippet"><?= htmlspecialchars((string) $item['search_snippet']) ?></p>
<?php elseif (!empty($item['excerpt'])): ?>
            <p><?= htmlspecialchars((string) $item['excerpt']) ?></p>
<?php endif; ?>
<?php if (!empty($item['published_at'])): ?>
            <div class="content-meta"><time datetime="<?= htmlspecialchars((string) $item['published_at']) ?>"><?= date('M j, Y', strtotime((string) $item['published_at'])) ?></time></div>
<?php endif; ?>
        </article>
<?php endforeach; ?>
    </div>
<?php endif; ?>
<?php endif; ?>
</section>
