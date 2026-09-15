<?php use Mova\Security\Csrf; $sheet = $sheet ?? ''; ?>
<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Var sheet saved. System sources updated where applicable. Cache cleared.</div>
<?php endif; ?>

<p class="design-intro">
    Edit all variables as a simple sheet. Change a system value to sync it back to Brand / Style / Layout.
    Add a new <code>name = value</code> line for a custom var; remove a custom line to delete it.
    Readonly derived vars (e.g. radius-button) are ignored on save.
</p>

<form method="post" action="/hq/variables/sheet" class="design-form">
    <?= Csrf::field() ?>
    <div class="form-group">
        <label>Variable sheet</label>
        <textarea name="sheet" rows="28" style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:0.85rem;line-height:1.45;"><?= htmlspecialchars($sheet) ?></textarea>
    </div>
    <div class="hq-layer-actions">
        <button type="submit" class="btn-primary">Save sheet</button>
        <a class="btn-ghost" href="/hq/variables">All variables</a>
        <a class="btn-ghost" href="/hq/variables/new">Add variable</a>
    </div>
</form>
