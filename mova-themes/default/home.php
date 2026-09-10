<?php
/** Homepage */
$siteName = mova_setting('site_name', 'Mova');
$siteDesc = mova_setting('site_description', 'Content that moves.');
$posts = $posts ?? [];
?>
<section class="hero">
    <h1><?= htmlspecialchars($siteName) ?></h1>
    <p class="lead"><?= htmlspecialchars($siteDesc) ?></p>
</section>

<section class="content-list">
    <?php if (empty($posts)): ?>
        <div class="empty-state">
            <p>No published content yet.</p>
            <p><a href="/hq">Open Mova HQ</a> to create your first piece.</p>
        </div>
    <?php else: ?>
        <h2>Latest</h2>
        <?php foreach ($posts as $item): ?>
            <article class="content-card">
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
