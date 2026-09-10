<?php
use Mova\Security\Csrf;
$isNew = $type === null;
$action = $isNew ? '/hq/types/new' : '/hq/types/edit/' . (int) $type['id'];
?>

<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Saved.</div>
<?php endif; ?>

<form method="post" action="<?= $action ?>" class="content-form">
    <?= Csrf::field() ?>
    <div class="form-layout">
        <div class="form-main">
            <div class="panel">
                <h3>Type</h3>
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($type['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Slug</label>
                    <input type="text" name="slug" value="<?= htmlspecialchars($type['slug'] ?? '') ?>"
                           <?= !empty($type['is_system']) ? 'readonly' : '' ?>
                           placeholder="auto-from-name">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="2"><?= htmlspecialchars($type['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Icon (Font Awesome class)</label>
                    <input type="text" name="icon" value="<?= htmlspecialchars($type['icon'] ?? 'fa-file') ?>" placeholder="fa-cube">
                </div>
                <div class="form-group">
                    <label>Schema.org type</label>
                    <input type="text" name="schema_type" value="<?= htmlspecialchars($type['schema_type'] ?? 'WebPage') ?>">
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400;">
                        <input type="checkbox" name="is_public" value="1" <?= ($type['is_public'] ?? 1) ? 'checked' : '' ?>>
                        Public (appears in type selector)
                    </label>
                </div>
                <button type="submit" class="btn-primary">Save type</button>
            </div>

            <?php if (!$isNew): ?>
            <div class="panel" style="margin-top:1.25rem;">
                <h3>Custom fields</h3>
                <?php if (empty($fields)): ?>
                    <p style="color:var(--hq-muted);font-size:0.9rem;">No custom fields yet.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>Name</th><th>Slug</th><th>Type</th><th>Required</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($fields as $f): ?>
                            <tr>
                                <td><?= htmlspecialchars($f['name']) ?></td>
                                <td><code><?= htmlspecialchars($f['slug']) ?></code></td>
                                <td><?= htmlspecialchars($fieldTypes[$f['field_type']] ?? $f['field_type']) ?></td>
                                <td><?= (int) $f['is_required'] ? 'Yes' : 'No' ?></td>
                                <td>
                                    <button type="submit" name="action" value="delete_field" class="btn-danger"
                                            onclick="this.form.field_id.value=<?= (int) $f['id'] ?>; return confirm('Delete field?');">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <input type="hidden" name="field_id" value="">
                <?php endif; ?>

                <hr style="border:none;border-top:1px solid var(--hq-border);margin:1.25rem 0;">
                <h4 style="margin:0 0 0.75rem;font-size:0.9rem;">Add field</h4>
                <div class="form-group">
                    <label>Field name</label>
                    <input type="text" name="field_name" placeholder="Price">
                </div>
                <div class="form-group">
                    <label>Slug</label>
                    <input type="text" name="field_slug" placeholder="price">
                </div>
                <div class="form-group">
                    <label>Field type</label>
                    <select name="field_type">
                        <?php foreach ($fieldTypes as $k => $label): ?>
                            <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Options (select / multi-select, one per line)</label>
                    <textarea name="field_options" rows="3" placeholder="Option A&#10;Option B"></textarea>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400;">
                        <input type="checkbox" name="field_required" value="1"> Required
                    </label>
                </div>
                <button type="submit" name="action" value="add_field" class="btn-primary">Add field</button>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!$isNew && empty($type['is_system'])): ?>
        <aside class="form-sidebar">
            <div class="panel">
                <h3>Danger</h3>
                <button type="submit" name="action" value="delete_type" class="btn-danger btn-block"
                        onclick="return confirm('Delete this content type? Content using it will keep the slug but the type definition will be removed.');">
                    Delete type
                </button>
            </div>
        </aside>
        <?php endif; ?>
    </div>
</form>
