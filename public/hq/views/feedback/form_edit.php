<?php
use Mova\Security\Csrf;
$form = $form ?? null;
$error = $error ?? null;
$formData = $formData ?? null;
$titleVal = $formData['title'] ?? ($form['title'] ?? '');
$slugVal = $formData['slug'] ?? ($form['slug'] ?? '');
$descVal = $formData['description'] ?? ($form['description'] ?? '');
$statusVal = $formData['status'] ?? ($form['status'] ?? 'published');
$successVal = $formData['success_message'] ?? ($form['success_message'] ?? 'Thank you — we received your message.');
$fields = $formData['fields'] ?? ($form['fields'] ?? []);
if (!$fields) {
  $fields = [
    ['type'=>'text','label'=>'Name','key'=>'name','required'=>true,'placeholder'=>'','help'=>'','options'=>[]],
    ['type'=>'email','label'=>'Email','key'=>'email','required'=>true,'placeholder'=>'','help'=>'','options'=>[]],
    ['type'=>'textarea','label'=>'Message','key'=>'message','required'=>true,'placeholder'=>'','help'=>'','options'=>[]],
  ];
}
$slug = $form['slug'] ?? $slugVal;
?>
<?php if (!empty($_GET['saved'])): ?><div class="alert alert-success">Saved.</div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="toolbar">
  <h2 style="margin:0;font-size:1rem"><?= $form ? 'Edit form' : 'New form' ?></h2>
  <a href="/hq/feedback" class="btn-ghost btn-sm">All forms</a>
</div>

<form method="post" action="/hq/feedback/forms/save" id="fb-form-edit">
  <?= Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int)($form['id'] ?? 0) ?>">
  <input type="hidden" name="fields_json" id="fields_json" value="">

  <div class="form-group">
    <label>Title</label>
    <input type="text" name="title" required value="<?= htmlspecialchars($titleVal) ?>">
  </div>
  <div class="form-group">
    <label>Slug</label>
    <input type="text" name="slug" value="<?= htmlspecialchars($slugVal) ?>" placeholder="auto from title">
  </div>
  <div class="form-group">
    <label>Description</label>
    <textarea name="description" rows="2"><?= htmlspecialchars($descVal) ?></textarea>
  </div>
  <div class="form-group">
    <label>Status</label>
    <select name="status">
      <option value="published" <?= $statusVal==='published'?'selected':'' ?>>Published</option>
      <option value="draft" <?= $statusVal==='draft'?'selected':'' ?>>Draft</option>
    </select>
  </div>
  <div class="form-group">
    <label>Success message</label>
    <input type="text" name="success_message" value="<?= htmlspecialchars($successVal) ?>">
  </div>

  <h3 style="font-size:0.95rem;margin:1.25rem 0 0.5rem">Fields</h3>
  <div id="fb-fields"></div>
  <button type="button" class="btn-ghost btn-sm" id="fb-add-field">+ Add field</button>

  <?php if ($slug): ?>
  <p class="field-hint" style="margin-top:1rem">Embed: <code>[feedback form="<?= htmlspecialchars($slug) ?>"]</code>
  · <code>&lt;div class="mova-feedback" data-form="<?= htmlspecialchars($slug) ?>"&gt;&lt;/div&gt;</code></p>
  <?php endif; ?>

  <div class="form-actions" style="margin-top:1.25rem;display:flex;gap:0.5rem">
    <button type="submit" class="btn-primary">Save form</button>
    <a href="/hq/feedback" class="btn-ghost">Cancel</a>
  </div>
</form>

<script>
(function () {
  var types = <?= json_encode(\Mova\Feedback\FeedbackService::FIELD_TYPES) ?>;
  var fields = <?= json_encode($fields) ?>;
  var box = document.getElementById('fb-fields');
  var hidden = document.getElementById('fields_json');

  function row(f, idx) {
    f = f || { type: 'text', label: '', key: '', required: false, placeholder: '', help: '', options: [] };
    var opts = (f.options || []).join(', ');
    var div = document.createElement('div');
    div.className = 'fb-field-row';
    div.style.cssText = 'border:1px solid var(--hq-border,#e5e7eb);border-radius:8px;padding:0.75rem;margin-bottom:0.5rem;background:#fff';
    div.innerHTML =
      '<div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:0.4rem;align-items:end">' +
      '<label style="font-size:0.75rem">Label<input data-k="label" value="' + esc(f.label) + '"></label>' +
      '<label style="font-size:0.75rem">Key<input data-k="key" value="' + esc(f.key) + '" placeholder="auto"></label>' +
      '<label style="font-size:0.75rem">Type<select data-k="type">' + types.map(function (t) {
        return '<option value="' + t + '"' + (f.type === t ? ' selected' : '') + '>' + t + '</option>';
      }).join('') + '</select></label>' +
      '<button type="button" data-rm style="margin-bottom:2px">✕</button></div>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr auto;gap:0.4rem;margin-top:0.4rem;align-items:end">' +
      '<label style="font-size:0.75rem">Placeholder<input data-k="placeholder" value="' + esc(f.placeholder || '') + '"></label>' +
      '<label style="font-size:0.75rem">Options (comma)<input data-k="options" value="' + esc(opts) + '"></label>' +
      '<label style="font-size:0.75rem;display:flex;gap:0.35rem;align-items:center"><input type="checkbox" data-k="required"' + (f.required ? ' checked' : '') + '> Required</label>' +
      '</div>';
    div.querySelector('[data-rm]').onclick = function () { div.remove(); };
    return div;
  }
  function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
  }
  function render() {
    box.innerHTML = '';
    fields.forEach(function (f) { box.appendChild(row(f)); });
  }
  function collect() {
    var out = [];
    box.querySelectorAll('.fb-field-row').forEach(function (div) {
      var get = function (k) {
        var el = div.querySelector('[data-k="' + k + '"]');
        if (!el) return '';
        if (el.type === 'checkbox') return el.checked;
        return el.value;
      };
      var opts = String(get('options') || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
      out.push({
        type: get('type'),
        label: get('label'),
        key: get('key'),
        required: !!get('required'),
        placeholder: get('placeholder'),
        help: '',
        options: opts
      });
    });
    return out;
  }
  document.getElementById('fb-add-field').onclick = function () {
    box.appendChild(row({ type: 'text', label: 'New field', key: '', required: false, options: [] }));
  };
  document.getElementById('fb-form-edit').addEventListener('submit', function () {
    hidden.value = JSON.stringify(collect());
  });
  render();
})();
</script>
<style>
.fb-field-row input, .fb-field-row select { width: 100%; box-sizing: border-box; padding: 0.35rem 0.45rem; border: 1px solid #d1d5db; border-radius: 6px; font: inherit; }
.fb-field-row label { display: block; }
</style>
