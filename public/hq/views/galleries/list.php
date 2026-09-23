<?php
use Mova\Security\Csrf;
$items = $items ?? [];
?>
<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;">Galleries</h2>
    <a href="/hq/galleries/new" class="btn-primary"><i class="fa-solid fa-plus"></i> New gallery</a>
</div>

<p style="color:var(--hq-muted);font-size:0.9rem;margin:0 0 1rem;">
    Collections shown on the public <a href="/gallery" target="_blank">/gallery</a> page. The slug <code>gallery</code> is reserved.
</p>

<?php if (empty($items)): ?>
    <p class="empty">No galleries yet. Create one to group images for the public gallery.</p>
<?php else: ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Title</th>
            <th>Slug</th>
            <th>Images</th>
            <th>Status</th>
            <th>Updated</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $g): ?>
        <tr>
            <td>
                <strong><a href="/hq/galleries/edit/<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['title']) ?></a></strong>
            </td>
            <td><code><?= htmlspecialchars($g['slug']) ?></code></td>
            <td><?= (int)($g['item_count'] ?? 0) ?></td>
            <td><span class="badge badge-<?= htmlspecialchars($g['status']) ?>"><?= htmlspecialchars($g['status']) ?></span></td>
            <td><?= date('M j, Y', strtotime($g['updated_at'] ?? 'now')) ?></td>
            <td style="white-space:nowrap;">
                <a href="/hq/galleries/edit/<?= (int)$g['id'] ?>" class="btn-ghost btn-sm">Edit</a>
                <?php if (($g['status'] ?? '') === 'published'): ?>
                    <a href="/gallery/<?= htmlspecialchars($g['slug']) ?>" target="_blank" class="btn-ghost btn-sm" title="View">↗</a>
                <?php endif; ?>
                <form method="post" action="/hq/galleries/delete/<?= (int)$g['id'] ?>" style="display:inline;"
                      onsubmit="return confirm('Delete this gallery? Images stay in Media.');">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn-ghost btn-sm" style="color:var(--hq-danger,#b91c1c);">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
