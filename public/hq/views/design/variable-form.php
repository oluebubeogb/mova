<?php
use Mova\Security\Csrf;
$variable = $variable ?? ['name' => '', 'value' => '', 'type' => 'text', 'description' => ''];
$isNew = !empty($isNew);
?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<p class="design-intro">Custom variables appear as CSS custom properties (<code>--mova-name</code>) and as <code>{{var:name}}</code> in HTML.</p>

<form method="post" action="/hq/variables/new" class="design-form">
    <?= Csrf::field() ?>
    <div class="form-group">
        <label>Name</label>
        <input type="text" name="name" required pattern="[A-Za-z0-9_\-]+" placeholder="my-gap" value="<?= htmlspecialchars($variable['name'] ?? '') ?>">
        <p class="field-hint">Letters, numbers, hyphen, underscore. Becomes <code>--mova-…</code> in CSS.</p>
    </div>
    <div class="form-group">
        <label>Value</label>
        <input type="text" name="value" placeholder="1.5rem or #2563eb" value="<?= htmlspecialchars($variable['value'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label>Type</label>
        <select name="type">
            <?php foreach (['text' => 'Text', 'length' => 'Length', 'color' => 'Color', 'url' => 'URL'] as $v => $lab): ?>
                <option value="<?= $v ?>" <?= ($variable['type'] ?? '') === $v ? 'selected' : '' ?>><?= $lab ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Description</label>
        <input type="text" name="description" placeholder="Optional note" value="<?= htmlspecialchars($variable['description'] ?? '') ?>">
    </div>
    <div class="hq-layer-actions">
        <button type="submit" class="btn-primary">Save variable</button>
        <a class="btn-ghost" href="/hq/variables">Cancel</a>
    </div>
</form>
