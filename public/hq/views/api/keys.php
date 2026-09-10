<?php use Mova\Security\Csrf; ?>

<?php if (!empty($newKey)): ?>
<div class="alert alert-success">
    <strong>Copy your API key now</strong> — it won’t be shown again.<br>
    <code style="user-select:all;word-break:break-all;"><?= htmlspecialchars($newKey) ?></code>
</div>
<?php endif; ?>

<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;">API keys</h2>
</div>

<div class="panel" style="margin-bottom:1.5rem;">
    <h3>Create key</h3>
    <form method="post" action="/hq/api-keys">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" required placeholder="Mobile app, Headless site…">
        </div>
        <div class="form-group">
            <label>Scopes</label>
            <label style="display:flex;align-items:center;gap:0.4rem;font-weight:400;margin-bottom:0.25rem;">
                <input type="checkbox" name="scopes[]" value="read" checked> read
            </label>
            <label style="display:flex;align-items:center;gap:0.4rem;font-weight:400;margin-bottom:0.25rem;">
                <input type="checkbox" name="scopes[]" value="write"> write
            </label>
        </div>
        <button type="submit" class="btn-primary">Generate key</button>
    </form>
</div>

<?php if (empty($keys)): ?>
    <p class="empty">No API keys yet.</p>
<?php else: ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Prefix</th>
            <th>Scopes</th>
            <th>Status</th>
            <th>Last used</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($keys as $k): ?>
        <tr>
            <td><?= htmlspecialchars($k['name']) ?></td>
            <td><code><?= htmlspecialchars($k['key_prefix']) ?>…</code></td>
            <td><?= htmlspecialchars(is_string($k['scopes']) ? $k['scopes'] : json_encode($k['scopes'])) ?></td>
            <td><?= htmlspecialchars($k['status']) ?></td>
            <td><?= $k['last_used_at'] ? htmlspecialchars(date('Y-m-d H:i', strtotime($k['last_used_at']))) : '—' ?></td>
            <td style="white-space:nowrap;">
                <?php if ($k['status'] === 'active'): ?>
                <form method="post" action="/hq/api-keys" style="display:inline;">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="revoke">
                    <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                    <button type="submit" class="btn-ghost">Revoke</button>
                </form>
                <?php endif; ?>
                <form method="post" action="/hq/api-keys" style="display:inline;" onsubmit="return confirm('Delete key?');">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                    <button type="submit" class="btn-danger">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="panel" style="margin-top:1.5rem;">
    <h3>Usage</h3>
    <p style="font-size:0.9rem;color:var(--hq-muted);margin:0;">
        Send header <code>Authorization: Bearer YOUR_KEY</code> to
        <code>/api/v1/content</code>, <code>/api/v1/content/{id}</code>,
        <code>/api/v1/search?q=…</code>, <code>/api/v1/categories</code>, <code>/api/v1/media</code>.
    </p>
</div>
