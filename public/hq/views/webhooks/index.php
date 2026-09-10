<?php use Mova\Security\Csrf; ?>

<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;">Webhooks</h2>
</div>

<div class="panel" style="margin-bottom:1.5rem;">
    <h3>Add webhook</h3>
    <form method="post" action="/hq/webhooks">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" required placeholder="Zapier, Slack, custom…">
        </div>
        <div class="form-group">
            <label>URL</label>
            <input type="url" name="url" required placeholder="https://example.com/hook">
        </div>
        <div class="form-group">
            <label>Events</label>
            <?php foreach ($events as $key => $label): ?>
                <label style="display:flex;align-items:center;gap:0.4rem;font-weight:400;margin-bottom:0.25rem;">
                    <input type="checkbox" name="events[]" value="<?= htmlspecialchars($key) ?>">
                    <?= htmlspecialchars($label) ?> <code style="font-size:0.75rem;"><?= htmlspecialchars($key) ?></code>
                </label>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn-primary">Create webhook</button>
    </form>
</div>

<?php if (empty($webhooks)): ?>
    <p class="empty">No webhooks configured.</p>
<?php else: ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>URL</th>
            <th>Events</th>
            <th>Status</th>
            <th>Last</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($webhooks as $w): ?>
        <tr>
            <td><?= htmlspecialchars($w['name']) ?></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($w['url']) ?></td>
            <td style="font-size:0.8rem;"><?= htmlspecialchars(implode(', ', $w['events'] ?? [])) ?></td>
            <td><?= htmlspecialchars($w['status']) ?></td>
            <td>
                <?php if ($w['last_triggered_at']): ?>
                    <?= htmlspecialchars(date('Y-m-d H:i', strtotime($w['last_triggered_at']))) ?>
                    (<?= (int) $w['last_status'] ?>)
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            <td style="white-space:nowrap;">
                <form method="post" action="/hq/webhooks" style="display:inline;">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                    <button type="submit" class="btn-ghost"><?= $w['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
                </form>
                <form method="post" action="/hq/webhooks" style="display:inline;" onsubmit="return confirm('Delete webhook?');">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                    <button type="submit" class="btn-danger">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
