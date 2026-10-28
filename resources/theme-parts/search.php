<?php
/**
 * Premium public search — Google-like rich results.
 * Variables: $query, $typeFilter, $pages, $articles, $otherContent, $galleries, $images, $blogs, $results
 */
$query        = $query ?? '';
$typeFilter   = $typeFilter ?? 'all';
$pages        = $pages ?? [];
$articles     = $articles ?? [];
$otherContent = $otherContent ?? [];
$galleries    = $galleries ?? [];
$images       = $images ?? [];
$blogs        = $blogs ?? [];
$results      = $results ?? []; // all content for backward compat

$allContent = array_merge($pages, $articles, $otherContent);
$total = count($allContent) + count($galleries) + count($images) + count($blogs);

$hasQuery = $query !== '';

// Helper: type label
$typeLabel = static function (string $type): string {
    $map = [
        'page' => 'Page', 'article' => 'Article', 'post' => 'Post', 'news' => 'News',
        'faq' => 'FAQ', 'landing' => 'Landing', 'blog' => 'Blog',
    ];
    return $map[strtolower($type)] ?? ucfirst($type);
};

// Build filter tabs with counts
$tabs = [
    'all'      => ['label' => 'All',      'count' => $total],
    'page'     => ['label' => 'Pages',    'count' => count($pages) + count($otherContent)],
    'article'  => ['label' => 'Articles', 'count' => count($articles)],
    'image'    => ['label' => 'Images',   'count' => count($images)],
    'gallery'  => ['label' => 'Galleries','count' => count($galleries)],
    'blog'     => ['label' => 'Blogs',    'count' => count($blogs)],
];
?>
<link rel="stylesheet" href="/assets/css/mova-search.css?v=2">
<section class="mx-search-page">
    <header class="mx-search-hero">
        <p class="mx-tag">Search</p>
        <h1 class="mx-search-title">Find anything on <?= htmlspecialchars((string) mova_setting('site_name', 'this site')) ?></h1>
        <form class="mx-search-form" method="get" action="/search" role="search">
            <label class="sr-only" for="q">Search</label>
            <div class="mx-search-input-wrap">
                <svg class="mx-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" id="q" name="q" value="<?= htmlspecialchars((string) $query) ?>" placeholder="Search pages, articles, images, galleries…" autofocus autocomplete="off">
                <?php if ($typeFilter !== 'all'): ?>
                <input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>">
                <?php endif; ?>
                <button type="submit" class="mx-search-btn">Search</button>
            </div>
        </form>
    </header>

<?php if ($hasQuery): ?>
    <div class="mx-search-meta">
        <p class="mx-search-count">
            <?= $total ?> result<?= $total === 1 ? '' : 's' ?> for <strong>“<?= htmlspecialchars((string) $query) ?>”</strong>
        </p>
        <nav class="mx-search-tabs" aria-label="Filter results">
            <?php foreach ($tabs as $key => $tab): ?>
                <?php if ($tab['count'] === 0 && $key !== 'all') continue; ?>
                <a href="/search?q=<?= urlencode($query) ?>&type=<?= urlencode($key) ?>"
                   class="mx-search-tab<?= $typeFilter === $key ? ' is-active' : '' ?>"
                   <?= $typeFilter === $key ? 'aria-current="page"' : '' ?>>
                    <?= htmlspecialchars($tab['label']) ?>
                    <span class="mx-search-tab-count"><?= (int) $tab['count'] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <?php if ($total === 0): ?>
        <div class="mx-search-empty">
            <div class="mx-search-empty-icon" aria-hidden="true">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
            <h2>No matches found</h2>
            <p>Try different keywords or check the spelling.</p>
        </div>
    <?php else: ?>

        <?php
        // Decide what to show based on filter
        $showContent = in_array($typeFilter, ['all', 'page', 'article'], true);
        $showImages  = in_array($typeFilter, ['all', 'image'], true);
        $showGalleries = in_array($typeFilter, ['all', 'gallery'], true);
        $showBlogs   = in_array($typeFilter, ['all', 'blog'], true);

        $contentToShow = $allContent;
        if ($typeFilter === 'page') {
            $contentToShow = array_merge($pages, $otherContent);
        } elseif ($typeFilter === 'article') {
            $contentToShow = $articles;
        }
        ?>

        <?php if ($showContent && $contentToShow): ?>
        <section class="mx-search-section">
            <?php if ($typeFilter === 'all'): ?>
            <h2 class="mx-search-section-title">Pages &amp; Articles</h2>
            <?php endif; ?>
            <div class="mx-search-results">
                <?php foreach ($contentToShow as $item):
                    $hasImage = !empty($item['featured_image']);
                    $fi = (string) ($item['featured_image'] ?? '');
                    if ($hasImage && strpos($fi, 'http') !== 0 && strpos($fi, '/') !== 0) {
                        $fi = '/mova-uploads/' . ltrim($fi, '/');
                    } elseif ($hasImage && strpos($fi, 'http') !== 0 && strpos($fi, '/mova-uploads/') === false) {
                        $fi = '/' . ltrim($fi, '/');
                    }
                    $type = (string) ($item['type'] ?? 'page');
                    $snippet = (string) ($item['search_snippet'] ?? $item['excerpt'] ?? '');
                    $date = !empty($item['published_at']) ? date('M j, Y', strtotime((string) $item['published_at'])) : '';
                ?>
                <article class="mx-search-card<?= $hasImage ? ' mx-search-card--has-image' : '' ?>">
                    <?php if ($hasImage): ?>
                    <a class="mx-search-card-media" href="/<?= htmlspecialchars((string) $item['slug']) ?>" tabindex="-1" aria-hidden="true">
                        <img src="<?= htmlspecialchars($fi) ?>" alt="" loading="lazy" width="160" height="120">
                    </a>
                    <?php endif; ?>
                    <div class="mx-search-card-body">
                        <div class="mx-search-card-meta">
                            <span class="mx-search-badge mx-search-badge--<?= htmlspecialchars(preg_replace('/[^a-z0-9]/', '', strtolower($type))) ?>"><?= htmlspecialchars($typeLabel($type)) ?></span>
                            <?php if ($date): ?>
                            <time datetime="<?= htmlspecialchars((string) $item['published_at']) ?>"><?= htmlspecialchars($date) ?></time>
                            <?php endif; ?>
                        </div>
                        <h3 class="mx-search-card-title">
                            <a href="/<?= htmlspecialchars((string) $item['slug']) ?>"><?= htmlspecialchars((string) $item['title']) ?></a>
                        </h3>
                        <?php if ($snippet !== ''): ?>
                        <p class="mx-search-card-snippet"><?= htmlspecialchars($snippet) ?></p>
                        <?php endif; ?>
                        <p class="mx-search-card-url">/<?= htmlspecialchars((string) $item['slug']) ?></p>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($showImages && $images): ?>
        <section class="mx-search-section">
            <?php if ($typeFilter === 'all'): ?>
            <h2 class="mx-search-section-title">Images</h2>
            <?php endif; ?>
            <div class="mx-search-image-grid">
                <?php foreach ($images as $img):
                    $src = (string) ($img['thumb'] ?? $img['url'] ?? '');
                    $link = (string) ($img['source_url'] ?? '#');
                    $title = (string) ($img['source_title'] ?? $img['caption'] ?? $img['alt'] ?? 'Image');
                    $alt = (string) ($img['alt'] ?? $img['caption'] ?? $title);
                ?>
                <a class="mx-search-image-item" href="<?= htmlspecialchars($link) ?>" title="<?= htmlspecialchars($title) ?>">
                    <img src="<?= htmlspecialchars($src) ?>" alt="<?= htmlspecialchars($alt) ?>" loading="lazy">
                    <span class="mx-search-image-caption">
                        <span class="mx-search-image-source"><?= htmlspecialchars($title) ?></span>
                        <?php if (($img['source'] ?? '') === 'gallery'): ?>
                        <span class="mx-search-image-kind">Gallery</span>
                        <?php else: ?>
                        <span class="mx-search-image-kind">Content</span>
                        <?php endif; ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($showGalleries && $galleries): ?>
        <section class="mx-search-section">
            <?php if ($typeFilter === 'all'): ?>
            <h2 class="mx-search-section-title">Galleries</h2>
            <?php endif; ?>
            <div class="mx-search-results">
                <?php foreach ($galleries as $g):
                    $gSlug = (string) ($g['slug'] ?? '');
                    $gTitle = (string) ($g['title'] ?? 'Gallery');
                    $gDesc = (string) ($g['description'] ?? $g['excerpt'] ?? '');
                    $cover = (string) ($g['cover_url'] ?? $g['cover'] ?? $g['featured_image'] ?? '');
                    if ($cover !== '' && strpos($cover, 'http') !== 0 && strpos($cover, '/') !== 0) {
                        $cover = '/mova-uploads/' . ltrim($cover, '/');
                    }
                ?>
                <article class="mx-search-card<?= $cover !== '' ? ' mx-search-card--has-image' : '' ?>">
                    <?php if ($cover !== ''): ?>
                    <a class="mx-search-card-media" href="/gallery/<?= htmlspecialchars($gSlug) ?>" tabindex="-1" aria-hidden="true">
                        <img src="<?= htmlspecialchars($cover) ?>" alt="" loading="lazy" width="160" height="120">
                    </a>
                    <?php endif; ?>
                    <div class="mx-search-card-body">
                        <div class="mx-search-card-meta">
                            <span class="mx-search-badge mx-search-badge--gallery">Gallery</span>
                        </div>
                        <h3 class="mx-search-card-title">
                            <a href="/gallery/<?= htmlspecialchars($gSlug) ?>"><?= htmlspecialchars($gTitle) ?></a>
                        </h3>
                        <?php if ($gDesc !== ''): ?>
                        <p class="mx-search-card-snippet"><?= htmlspecialchars(mb_substr(strip_tags($gDesc), 0, 160)) ?><?= mb_strlen($gDesc) > 160 ? '…' : '' ?></p>
                        <?php endif; ?>
                        <p class="mx-search-card-url">/gallery/<?= htmlspecialchars($gSlug) ?></p>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($showBlogs && $blogs): ?>
        <section class="mx-search-section">
            <?php if ($typeFilter === 'all'): ?>
            <h2 class="mx-search-section-title">Blogs</h2>
            <?php endif; ?>
            <div class="mx-search-results">
                <?php foreach ($blogs as $b): ?>
                <article class="mx-search-card">
                    <div class="mx-search-card-body">
                        <div class="mx-search-card-meta">
                            <span class="mx-search-badge mx-search-badge--blog">Blog</span>
                        </div>
                        <h3 class="mx-search-card-title">
                            <a href="<?= htmlspecialchars((string) ($b['url'] ?? '/blog/' . ($b['slug'] ?? ''))) ?>"><?= htmlspecialchars((string) ($b['title'] ?? '')) ?></a>
                        </h3>
                        <?php if (!empty($b['description'])): ?>
                        <p class="mx-search-card-snippet"><?= htmlspecialchars(mb_substr((string) $b['description'], 0, 160)) ?><?= mb_strlen((string) $b['description']) > 160 ? '…' : '' ?></p>
                        <?php endif; ?>
                        <p class="mx-search-card-url"><?= htmlspecialchars((string) ($b['url'] ?? '/blog/' . ($b['slug'] ?? ''))) ?></p>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    <?php endif; ?>
<?php endif; ?>
</section>
