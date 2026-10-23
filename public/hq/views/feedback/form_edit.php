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
?>
<?php if (!empty($_GET['saved'])): ?><div class="alert alert-success">Saved.</div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<section class="hq-page">
  <div class="toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
    <h1 style="margin:0;font-size:1.15rem"><?= $form ? 'Edit form' : 'New form' ?></h1>
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
    <p class="field-hint" style="margin-top:0">Drag the handle or use ↑ ↓ to reorder.</p>
    <div id="fb-fields" class="fb-fields"></div>
    <button type="button" class="btn-ghost btn-sm" id="fb-add-field" style="margin-top:0.5rem">+ Add field</button>

    <div class="form-actions" style="margin-top:1.25rem">
      <button type="submit" class="btn-primary">Save form</button>
      <a class="btn-ghost" href="/hq/feedback">Cancel</a>
    </div>
  </form>
</section>

<style>
.fb-fields{display:flex;flex-direction:column;gap:0.5rem}
.fb-field-row{
  border:1px solid var(--hq-border,#e5e7eb);border-radius:10px;background:color-mix(in srgb,var(--hq-text,#0f172a) 3%,var(--hq-surface,#fff));
  padding:0.65rem 0.75rem;display:grid;grid-template-columns:1.5rem 2rem 1fr;gap:0.5rem;align-items:start;
}
.fb-field-row.is-dragging{opacity:0.7;border-color:var(--hq-accent,#0f766e)}
.fb-drag{cursor:grab;color:var(--hq-muted,#64748b);padding-top:0.45rem;text-align:center;user-select:none}
.fb-drag:active{cursor:grabbing}
.fb-order-btns{display:flex;flex-direction:column;gap:0.15rem;padding-top:0.15rem}
.fb-order-btns button{
  width:1.6rem;height:1.35rem;border:1px solid #e2e8f0;border-radius:5px;background:#fff;
  color:var(--hq-muted,#64748b);cursor:pointer;font-size:0.65rem;line-height:1;padding:0;
}
.fb-order-btns button:hover{background:#f1f5f9;color:#0f172a}
.fb-field-body{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:0.45rem}
.fb-field-row input,.fb-field-row select{width:100%;box-sizing:border-box;padding:0.35rem 0.45rem;border:1px solid #d1d5db;border-radius:6px;font:inherit;font-size:0.85rem}
.fb-field-row label{display:block;font-size:0.72rem;color:var(--hq-muted,#64748b);font-weight:600}
.fb-field-rm{justify-self:end}
</style>
<script>
(function(){
  var fields = <?= json_encode($fields, JSON_UNESCAPED_UNICODE) ?>;
  var box = document.getElementById('fb-fields');
  var hidden = document.getElementById('fields_json');
  var dragEl = null;

  function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
  }
  function typesOpts(sel) {
    var labels = {
        text:'Text', email:'Email', phone:'Phone', intl_phone:'Intl Phone', country:'Country',
        textarea:'Textarea', select:'Dropdown', checkbox:'Checkbox', radio:'Radio', date:'Date', number:'Number'
      };
      return ['text','email','phone','intl_phone','country','textarea','select','checkbox','radio','date','number']
      .map(function(t){ return '<option value="'+t+'"'+(sel===t?' selected':'')+'>'+(labels[t]||t)+'</option>'; }).join('');
  }
  function row(f) {
    f = f || {};
    var opts = Array.isArray(f.options) ? f.options.join(', ') : (f.options||'');
    var div = document.createElement('div');
    div.className = 'fb-field-row';
    div.draggable = true;
    div.innerHTML =
      '<div class="fb-drag" title="Drag to reorder"><i class="fa-solid fa-grip-vertical"></i></div>' +
      '<div class="fb-order-btns">' +
        '<button type="button" data-act="up" title="Move up">↑</button>' +
        '<button type="button" data-act="down" title="Move down">↓</button>' +
      '</div>' +
      '<div class="fb-field-body">' +
        '<label>Type<select data-k="type">'+typesOpts(f.type||'text')+'</select></label>' +
        '<label>Label<input data-k="label" value="'+esc(f.label||'')+'"></label>' +
        '<label>Key<input data-k="key" value="'+esc(f.key||'')+'" placeholder="auto"></label>' +
        '<label>Placeholder<input data-k="placeholder" value="'+esc(f.placeholder||'')+'"></label>' +
        '<label>Options<input data-k="options" value="'+esc(opts)+'" placeholder="a, b, c"></label>' +
        '<label style="display:flex;align-items:center;gap:0.35rem;margin-top:1.1rem"><input type="checkbox" data-k="required"'+(f.required?' checked':'')+'> Required</label>' +
        '<button type="button" class="btn-ghost btn-sm fb-field-rm" data-act="rm">Remove</button>' +
      '</div>';

    div.querySelector('[data-act="rm"]').onclick = function(){ div.remove(); };
    div.querySelector('[data-act="up"]').onclick = function(){
      if (div.previousElementSibling) box.insertBefore(div, div.previousElementSibling);
    };
    div.querySelector('[data-act="down"]').onclick = function(){
      if (div.nextElementSibling) box.insertBefore(div.nextElementSibling, div);
    };

    div.addEventListener('dragstart', function(e){
      dragEl = div;
      div.classList.add('is-dragging');
      e.dataTransfer.effectAllowed = 'move';
    });
    div.addEventListener('dragend', function(){
      div.classList.remove('is-dragging');
      dragEl = null;
    });
    div.addEventListener('dragover', function(e){
      e.preventDefault();
      if (!dragEl || dragEl === div) return;
      var rect = div.getBoundingClientRect();
      var before = (e.clientY - rect.top) < rect.height / 2;
      box.insertBefore(dragEl, before ? div : div.nextSibling);
    });
    return div;
  }
  function collect() {
    var out = [];
    box.querySelectorAll('.fb-field-row').forEach(function(div){
      var get = function(k){
        var el = div.querySelector('[data-k="'+k+'"]');
        if (!el) return '';
        if (el.type === 'checkbox') return el.checked;
        return el.value;
      };
      var opts = String(get('options')||'').split(',').map(function(s){return s.trim();}).filter(Boolean);
      out.push({
        type: get('type'), label: get('label'), key: get('key'),
        required: !!get('required'), placeholder: get('placeholder'), help: '', options: opts
      });
    });
    return out;
  }
  document.getElementById('fb-add-field').onclick = function(){
    box.appendChild(row({ type:'text', label:'New field', key:'', required:false, options:[] }));
  };
  document.getElementById('fb-form-edit').addEventListener('submit', function(){
    hidden.value = JSON.stringify(collect());
  });
  fields.forEach(function(f){ box.appendChild(row(f)); });
})();
</script>
