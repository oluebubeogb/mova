<?php
use Mova\Security\Csrf;
$isNew = $user === null;
$action = $isNew ? '/hq/people/new' : '/hq/people/edit/' . (int) $user['id'];
$isOwner = !$isNew && ($user['role'] ?? '') === 'owner';
?>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= $action ?>" class="panel" style="max-width:520px;">
    <?= Csrf::field() ?>
    <div class="form-group">
        <label>Name</label>
        <input type="text" name="name" required value="<?= htmlspecialchars($user['name'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required value="<?= htmlspecialchars($user['username'] ?? '') ?>" autocomplete="username">
    </div>
    <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($user['email'] ?? '') ?>" autocomplete="email">
    </div>
    <div class="form-group">
        <label>Role</label>
        <select name="role" <?= $isOwner ? 'disabled' : '' ?>>
            <?php foreach ($roles as $k => $label): ?>
                <?php if ($k === 'owner' && !$isOwner) continue; ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= ($user['role'] ?? 'author') === $k ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($isOwner): ?>
            <input type="hidden" name="role" value="owner">
            <p style="font-size:0.8rem;color:var(--hq-muted);margin:0.35rem 0 0;">Owner role cannot be changed.</p>
        <?php endif; ?>
    </div>
    <div class="form-group">
        <label>Status</label>
        <select name="status" <?= $isOwner ? 'disabled' : '' ?>>
            <?php foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $k => $label): ?>
                <option value="<?= $k ?>" <?= ($user['status'] ?? 'active') === $k ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($isOwner): ?>
            <input type="hidden" name="status" value="active">
        <?php endif; ?>
    </div>
    <div class="form-group">
        <label><?= $isNew ? 'Password' : 'New password (leave blank to keep)' ?></label>
        <input type="password" name="password" <?= $isNew ? 'required minlength="8"' : 'minlength="8"' ?> autocomplete="new-password">
    </div>
    <div style="display:flex;gap:0.75rem;">
        <button type="submit" class="btn-primary"><?= $isNew ? 'Create' : 'Save' ?></button>
        <a href="/hq/people" class="btn-ghost">Cancel</a>
    </div>
</form>
