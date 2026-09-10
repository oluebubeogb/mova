<?php use Mova\Security\Csrf; ?>
<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;">Content types</h2>
    <a href="/hq/types/new" class="btn-primary">New type</a>
</div>

<?php if (empty($types)): ?>
    <p class="empty">No content types yet.</p>
<?php else: ?>
<table class="data-table">
    <thead>
        <tr>
            <th></th>
            <th>Name</th>
            <th>Slug</th>
            <th>Schema</th>
            <th>System</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($types as $t): ?>
        <tr>
            <td><i class="fa-solid <?= htmlspecialchars($t['icon'] ?: 'fa-file') ?>"></i></td>
            <td><strong><?= htmlspecialchars($t['name']) ?></strong>
                <?php if ($t['description']): ?>
                    <div style="font-size:0.8rem;color:var(--hq-muted);"><?= htmlspecialchars($t['description']) ?></div>
                <?php endif; ?>
            </td>
            <td><code><?= htmlspecialchars($t['slug']) ?></code></td>
            <td><?= htmlspecialchars($t['schema_type'] ?? '') ?></td>
            <td><?= (int) $t['is_system'] ? 'Yes' : 'No' ?></td>
            <td><a href="/hq/types/edit/<?= (int) $t['id'] ?>" class="btn-ghost">Edit</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
