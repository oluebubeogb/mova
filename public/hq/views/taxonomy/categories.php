<?php use Mova\Security\Csrf; ?>

<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;">Categories</h2>
</div>

<div class="form-layout">
    <div class="panel">
        <?php if (empty($categories)): ?>
            <p class="empty">No categories yet. Create one on the right.</p>
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
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?= htmlspecialchars($cat['name']) ?></td>
                            <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                            <td>
                                <form method="post" action="/hq/categories" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
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
        <h3 style="margin-top:0;">New category</h3>
        <form method="post" action="/hq/categories">
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
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="2"></textarea>
            </div>
            <button type="submit" class="btn-primary">Add category</button>
        </form>
    </div>
</div>
