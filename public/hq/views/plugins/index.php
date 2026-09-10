<?php use Mova\Security\Csrf; ?>

<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;">Plugins</h2>
</div>

<p style="color:var(--hq-muted);font-size:0.9rem;">
    Drop plugins into <code>plugins/{slug}/</code> with <code>plugin.php</code> + optional <code>plugin.json</code>.
    Use <code>PluginManager::addAction()</code> / <code>applyFilters()</code> — do not edit core files.
</p>

<?php if (empty($plugins)): ?>
    <p class="empty">No plugins discovered. A sample <code>hello-mova</code> plugin ships with Mova.</p>
<?php else: ?>
<table class="data-table">
    <thead>
        <tr><th>Name</th><th>Slug</th><th>Version</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($plugins as $p): ?>
        <tr>
            <td>
                <strong><?= htmlspecialchars($p['name']) ?></strong>
                <?php if (!empty($p['description'])): ?>
                    <div style="font-size:0.8rem;color:var(--hq-muted);"><?= htmlspecialchars($p['description']) ?></div>
                <?php endif; ?>
            </td>
            <td><code><?= htmlspecialchars($p['slug']) ?></code></td>
            <td><?= htmlspecialchars($p['version'] ?? '') ?></td>
            <td><?= htmlspecialchars($p['status'] ?? 'inactive') ?></td>
            <td>
                <form method="post" action="/hq/plugins">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="slug" value="<?= htmlspecialchars($p['slug']) ?>">
                    <?php if (($p['status'] ?? '') === 'active'): ?>
                        <input type="hidden" name="action" value="deactivate">
                        <button type="submit" class="btn-ghost">Deactivate</button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="activate">
                        <button type="submit" class="btn-primary">Activate</button>
                    <?php endif; ?>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
