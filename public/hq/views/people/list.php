<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">User saved.</div>
<?php endif; ?>

<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;">Team</h2>
    <a href="/hq/people/new" class="btn-primary">Add person</a>
</div>

<div class="panel">
    <?php if (empty($users)): ?>
        <p class="empty">No users yet.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last login</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($roles[$u['role']] ?? $u['role']) ?></span></td>
                        <td><?= htmlspecialchars($u['status']) ?></td>
                        <td><?= $u['last_login_at'] ? date('M j, Y', strtotime($u['last_login_at'])) : '—' ?></td>
                        <td><a href="/hq/people/edit/<?= (int) $u['id'] ?>">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
