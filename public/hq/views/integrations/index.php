<?php use Mova\Security\Csrf;
$provider = $provider ?? 'cdn';
$titles = [
    'cdn' => 'CDN',
    'smtp' => 'Email API',
    'analytics' => 'Analytics',
    'storage' => 'Object storage',
    'ai' => 'AI provider',
];
$pageTitle = $titles[$provider] ?? 'Integrations';
?>

<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Integration saved.</div>
<?php endif; ?>

<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;"><?= htmlspecialchars($pageTitle) ?></h2>
</div>

<?php if (empty($integrations)): ?>
    <p class="empty">No configuration available for this integration.</p>
<?php else: ?>
<?php foreach ($integrations as $int): ?>
<div class="panel" style="margin-bottom:1.25rem;">
    <h3 style="margin-top:0;"><?= htmlspecialchars($int['name']) ?>
        <code style="font-size:0.75rem;font-weight:400;"><?= htmlspecialchars($int['driver']) ?></code>
    </h3>
    <p style="font-size:0.85rem;color:var(--hq-muted);"><?= htmlspecialchars($int['description']) ?></p>
    <form method="post" action="/hq/integrations">
        <?= Csrf::field() ?>
        <input type="hidden" name="driver" value="<?= htmlspecialchars($int['driver']) ?>">
        <input type="hidden" name="return_provider" value="<?= htmlspecialchars($provider) ?>">
        <label style="display:flex;align-items:center;gap:0.4rem;margin-bottom:0.75rem;font-weight:400;">
            <input type="checkbox" name="enabled" value="1" <?= ($int['status'] ?? '') === 'enabled' ? 'checked' : '' ?>>
            Enabled
        </label>
        <?php foreach ($int['fields'] as $field): ?>
            <div class="form-group">
                <label><?= htmlspecialchars(ucwords(str_replace('_', ' ', $field))) ?></label>
                <input type="<?= str_contains($field, 'secret') || str_contains($field, 'key') || str_contains($field, 'token') ? 'password' : 'text' ?>"
                       name="config[<?= htmlspecialchars($field) ?>]"
                       value="<?= htmlspecialchars($int['config'][$field] ?? '') ?>"
                       autocomplete="off">
            </div>
        <?php endforeach; ?>
        <button type="submit" class="btn-primary">Save</button>
    </form>
</div>
<?php endforeach; ?>
<?php endif; ?>
