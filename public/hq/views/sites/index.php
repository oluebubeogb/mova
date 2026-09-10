<?php use Mova\Security\Csrf; $themes = $themes ?? []; ?>

<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Site saved.</div>
<?php endif; ?>

<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;">Sites</h2>
</div>

<div class="panel" style="margin-bottom:1.5rem;">
    <h3>Add site</h3>
    <form class="sites-form" method="post" action="/hq/sites">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" required placeholder="Marketing site">
        </div>
        <div class="form-group">
            <label>Slug</label>
            <input type="text" name="slug" placeholder="marketing">
        </div>
        <div class="form-group">
            <label>Domain</label>
            <input type="text" name="domain" placeholder="www.example.com">
        </div>
        <div class="form-group">
            <label>Theme</label>
            <select name="theme">
                <?php foreach ($themes as $th): ?>
                    <option value="<?= htmlspecialchars($th['slug']) ?>"><?= htmlspecialchars($th['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-primary">Create site</button>
    </form>
</div>

<table class="data-table">
    <thead>
        <tr><th>Name</th><th>Slug</th><th>Domain</th><th>Theme</th><th>Primary</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($sites as $s): ?>
        <tr>
            <td>
                <form method="post" action="/hq/sites" style="display:flex;flex-direction:column;gap:0.35rem;">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <input type="text" name="name" value="<?= htmlspecialchars($s['name']) ?>">
                    <input type="text" name="domain" value="<?= htmlspecialchars($s['domain'] ?? '') ?>" placeholder="domain">
                    <select name="theme">
                        <?php foreach ($themes as $th): ?>
                            <option value="<?= htmlspecialchars($th['slug']) ?>" <?= ($s['theme'] ?? '') === $th['slug'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($th['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status">
                        <option value="active" <?= ($s['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($s['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <button type="submit" class="btn-ghost">Save</button>
                </form>
            </td>
            <td><code><?= htmlspecialchars($s['slug']) ?></code></td>
            <td><?= htmlspecialchars($s['domain'] ?: '—') ?></td>
            <td><?= htmlspecialchars($s['theme']) ?></td>
            <td><?= (int) $s['is_primary'] ? 'Yes' : 'No' ?></td>
            <td style="white-space:nowrap;">
                <?php if (!(int) $s['is_primary']): ?>
                <form method="post" action="/hq/sites" style="display:inline;">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="primary">
                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <button type="submit" class="btn-ghost">Make primary</button>
                </form>
                <form method="post" action="/hq/sites" style="display:inline;" onsubmit="return confirm('Delete site?');">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <button type="submit" class="btn-danger">Delete</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
