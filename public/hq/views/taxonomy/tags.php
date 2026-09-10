<?php use Mova\Security\Csrf; ?>

<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;">Tags</h2>
</div>

<div class="form-layout">
    <div class="panel">
        <?php if (empty($tags)): ?>
            <p class="empty">No tags yet.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tags as $tag): ?>
                        <tr>
                            <td><?= htmlspecialchars($tag['name']) ?></td>
                            <td><code><?= htmlspecialchars($tag['slug']) ?></code></td>
                            <td>
                                <form method="post" action="/hq/tags" style="display:inline;" onsubmit="return confirm('Delete this tag?');">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $tag['id'] ?>">
                                    <button type="submit" class="btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h3 style="margin-top:0;">New tag</h3>
        <form method="post" action="/hq/tags">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Slug (optional)</label>
                <input type="text" name="slug" placeholder="auto-from-name">
            </div>
            <button type="submit" class="btn-primary">Add tag</button>
        </form>
    </div>
</div>
