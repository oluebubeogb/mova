<?php use Mova\Security\Csrf; ?>

<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;">Mail sequences</h2>
</div>

<div class="panel" style="margin-bottom:1.5rem;">
    <h3>New sequence</h3>
    <form method="post" action="/hq/sequences">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" required placeholder="Welcome series">
        </div>
        <div class="form-group">
            <label>First step delay (days)</label>
            <input type="number" name="delay_days" value="0" min="0">
        </div>
        <div class="form-group">
            <label>First email subject</label>
            <input type="text" name="subject" required placeholder="Welcome to {{site}}">
        </div>
        <div class="form-group">
            <label>First email body</label>
            <textarea name="body" rows="5" required></textarea>
        </div>
        <button type="submit" class="btn-primary">Create</button>
    </form>
</div>

<?php if (empty($sequences)): ?>
    <p class="empty">No sequences yet. Automation runner can be extended in a later pass.</p>
<?php else: ?>
<table class="data-table">
    <thead><tr><th>Name</th><th>Status</th><th>Created</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($sequences as $s): ?>
        <tr>
            <td><?= htmlspecialchars($s['name']) ?></td>
            <td><?= htmlspecialchars($s['status']) ?></td>
            <td><?= htmlspecialchars(date('Y-m-d', strtotime($s['created_at']))) ?></td>
            <td>
                <form method="post" action="/hq/sequences" onsubmit="return confirm('Delete?');">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <button type="submit" class="btn-danger">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
