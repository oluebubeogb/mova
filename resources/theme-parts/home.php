<?php
/** Shared fallback homepage (used when no page is set as the homepage). */
$__name = (string) mova_setting('site_name', 'Mova');
$__desc = (string) mova_setting('site_description', '');
$__posts = $posts ?? [];
$__variant = $homeHero ?? 'gradient';
echo \Mova\Theme\Sections::page([[
    'type' => 'hero', 'variant' => $__variant,
    'eyebrow' => 'Welcome',
    'title' => $__name,
    'lead' => $__desc !== '' ? $__desc : 'Stories, updates and ideas — all in one place.',
    'primary' => $__posts ? ['label' => 'Read the latest', 'href' => '#latest'] : null,
    'secondary' => ['label' => 'Search', 'href' => '/search'],
]]);
?>
<section class="mx-home-list" id="latest">
<?php if (!$__posts): ?>
    <div class="empty-state">
        <p><strong>Nothing published yet.</strong></p>
        <p>Open <a href="/hq">Mova HQ</a> to publish your first page — or run Quick setup for a complete site in minutes.</p>
    </div>
<?php else: ?>
    <div class="mx-list-head"><h2>Latest</h2><a href="/search" class="mx-more">Browse everything →</a></div>
    <div class="mx-listing">
<?php foreach ($__posts as $__i => $__p): ?>
        <article class="content-card" data-reveal style="--mx-d:<?= ($__i % 3) * 70 ?>ms">
<?php if (!empty($__p['type'])): ?>
            <span class="mx-tag"><?= htmlspecialchars(ucfirst((string) $__p['type'])) ?></span>
<?php endif; ?>
            <h3><a href="/<?= htmlspecialchars((string) $__p['slug']) ?>"><?= htmlspecialchars((string) $__p['title']) ?></a></h3>
<?php if (!empty($__p['excerpt'])): ?>
            <p><?= htmlspecialchars((string) $__p['excerpt']) ?></p>
<?php endif; ?>
<?php if (!empty($__p['published_at'])): ?>
            <div class="content-meta"><time datetime="<?= htmlspecialchars((string) $__p['published_at']) ?>"><?= date('M j, Y', strtotime((string) $__p['published_at'])) ?></time></div>
<?php endif; ?>
        </article>
<?php endforeach; ?>
    </div>
<?php endif; ?>
</section>
