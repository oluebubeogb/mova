<?php
/** Assembly editor — HTML / CSS + live preview (no JS) */
use Mova\Security\Csrf;

$item = $item ?? null;
$isNew = !$item;
$id = $item ? (int) $item['id'] : 0;
$name = $item['name'] ?? '';
$slug = $item['slug'] ?? '';
$description = $item['description'] ?? '';
$html = $item['html'] ?? '';
$css = $item['css'] ?? '';
$status = $item['status'] ?? 'draft';
$cssGlobal = !empty($item['css_global']);
$revision = (int) ($item['revision'] ?? 1);
$embed = $embed ?? '';
$usage = $usage ?? 0;
?>
<style>
.asm-editor { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; align-items: stretch; min-height: 28rem; }
@media (max-width: 960px) { .asm-editor { grid-template-columns: 1fr; } }
.asm-panel { border: 1px solid var(--hq-border); border-radius: 12px; background: var(--hq-surface); display: flex; flex-direction: column; overflow: hidden; }
.asm-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--hq-border); background: color-mix(in srgb, var(--hq-surface) 90%, var(--hq-border)); }
.asm-tabs button {
  flex: 1; border: none; background: transparent; padding: 0.65rem 0.75rem;
  font: inherit; font-weight: 600; font-size: 0.85rem; color: var(--hq-muted); cursor: pointer;
}
.asm-tabs button.is-active { color: var(--hq-text); box-shadow: inset 0 -2px 0 var(--hq-accent); }
.asm-code {
  flex: 1; min-height: 18rem; border: none; resize: vertical; padding: 0.85rem 1rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.85rem; line-height: 1.5;
  background: var(--hq-input-bg, var(--hq-surface)); color: var(--hq-text); width: 100%; box-sizing: border-box;
}
.asm-preview-frame { flex: 1; min-height: 18rem; border: none; background: #fff; width: 100%; }
.asm-toolbar { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; margin-bottom: 1rem; }
.asm-toolbar .btn-primary { font-weight: 700; text-decoration: none; }
.asm-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem; }
@media (max-width: 640px) { .asm-meta { grid-template-columns: 1fr; } }
.asm-meta .form-group { margin-bottom: 0; }
.asm-meta .form-group input {
  width: 100%;
  border: 1px solid var(--hq-border);
  border-radius: var(--hq-radius, 8px);
  background: var(--hq-input-bg);
  color: var(--hq-text);
  padding: 0.5rem 0.75rem;
  font: inherit;
  box-sizing: border-box;
}
.asm-meta .form-group input:focus {
  outline: none;
  border-color: var(--hq-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--hq-accent) 22%, transparent);
  background: var(--hq-surface, var(--hq-input-bg));
}
.asm-name-input {
  width: 100%;
  border: 1px solid var(--hq-border);
  border-radius: var(--hq-radius, 8px);
  background: var(--hq-input-bg);
  color: var(--hq-text);
  padding: 0.55rem 0.85rem;
  font: inherit;
  font-weight: 600;
  font-size: 1.05rem;
  box-sizing: border-box;
}
.asm-name-input:focus {
  outline: none;
  border-color: var(--hq-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--hq-accent) 22%, transparent);
}
.asm-side { font-size: 0.85rem; color: var(--hq-muted); }
.asm-side code { font-size: 0.8rem; }
.asm-embed-box {
  margin-top: 1rem; padding: 0.85rem 1rem; border: 1px solid var(--hq-border);
  border-radius: 10px; background: var(--hq-surface);
}
</style>

<form method="post" action="/hq/assembly" id="asm-form">
  <?= Csrf::field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <input type="hidden" name="action" id="asm-action" value="<?= $isNew ? 'create' : 'save' ?>">

  <div class="asm-toolbar">
    <div style="flex:1;min-width:12rem;">
      <input type="text" class="asm-name-input" name="name" value="<?= htmlspecialchars($name) ?>" placeholder="Assembly name" required>
    </div>
    <button type="submit" class="btn-ghost" onclick="document.getElementById('asm-action').value='<?= $isNew ? 'create' : 'save' ?>'">Save draft</button>
    <button type="submit" class="btn-primary" onclick="document.getElementById('asm-action').value='publish'">Publish</button>
    <?php if (!$isNew): ?>
      <button type="submit" class="btn-ghost" onclick="document.getElementById('asm-action').value='duplicate'">Duplicate</button>
      <button type="submit" class="btn-ghost" onclick="document.getElementById('asm-action').value='archive'">Archive</button>
    <?php endif; ?>
    <a class="btn-ghost" href="/hq/assembly" style="text-decoration:none;">← Library</a>
  </div>

  <div class="asm-meta">
    <div class="form-group">
      <label>Slug (embed key)</label>
      <input type="text" name="slug" value="<?= htmlspecialchars($slug) ?>" placeholder="auto-from-name" pattern="[a-z0-9\-]*">
    </div>
    <div class="form-group">
      <label>Description</label>
      <input type="text" name="description" value="<?= htmlspecialchars($description) ?>" placeholder="Optional note">
    </div>
  </div>

  <div class="asm-editor">
    <div class="asm-panel">
      <div class="asm-tabs" role="tablist">
        <button type="button" class="is-active" data-asm-tab="html">HTML</button>
        <button type="button" data-asm-tab="css">CSS</button>
      </div>
      <textarea class="asm-code" name="html" id="asm-html" data-asm-pane="html" spellcheck="false" placeholder="<!-- markup only -->"><?= htmlspecialchars($html) ?></textarea>
      <textarea class="asm-code" name="css" id="asm-css" data-asm-pane="css" spellcheck="false" hidden placeholder="/* scoped to this assembly by default */"><?= htmlspecialchars($css) ?></textarea>
    </div>
    <div class="asm-panel">
      <div class="asm-tabs">
        <button type="button" class="is-active" disabled>Live preview</button>
        <button type="button" class="btn-ghost" id="asm-refresh-preview" style="flex:0 0 auto;border-radius:0;">Refresh</button>
      </div>
      <iframe class="asm-preview-frame" id="asm-preview" title="Assembly preview" sandbox="allow-same-origin"></iframe>
    </div>
  </div>

  <div style="display:flex;flex-wrap:wrap;gap:1.25rem;margin-top:1rem;align-items:flex-start;">
    <label class="asm-side" style="display:flex;align-items:center;gap:0.4rem;">
      <input type="checkbox" name="css_global" value="1" <?= $cssGlobal ? 'checked' : '' ?>>
      Global CSS (not scoped — use carefully)
    </label>
    <div class="asm-side" style="flex:1;">
      Status: <strong><?= htmlspecialchars($status) ?></strong>
      <?php if (!$isNew): ?> · Rev <?= $revision ?> · Used in <?= (int) $usage ?> content<?php endif; ?>
    </div>
  </div>

  <?php if ($embed !== ''): ?>
  <div class="asm-embed-box">
    <div style="font-size:0.8rem;color:var(--hq-muted);margin-bottom:0.35rem;">Embed shortcode</div>
    <code id="asm-embed"><?= htmlspecialchars($embed) ?></code>
    <button type="button" class="btn-ghost btn-sm" id="asm-copy-embed" style="margin-left:0.5rem;">Copy</button>
  </div>
  <?php endif; ?>
</form>

<script>
(function () {
  var csrf = document.querySelector('#asm-form input[name="_mova_csrf"]');
  var csrfVal = csrf ? csrf.value : '';
  var tabs = document.querySelectorAll('[data-asm-tab]');
  var panes = {
    html: document.getElementById('asm-html'),
    css: document.getElementById('asm-css')
  };
  tabs.forEach(function (btn) {
    btn.addEventListener('click', function () {
      tabs.forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      var key = btn.getAttribute('data-asm-tab');
      Object.keys(panes).forEach(function (k) {
        if (panes[k]) panes[k].hidden = (k !== key);
      });
    });
  });

  function refreshPreview() {
    var fd = new FormData();
    fd.append('_mova_csrf', csrfVal);
    fd.append('slug', (document.querySelector('[name=slug]') || {}).value || 'preview');
    fd.append('html', panes.html ? panes.html.value : '');
    fd.append('css', panes.css ? panes.css.value : '');
    fd.append('css_global', document.querySelector('[name=css_global]') && document.querySelector('[name=css_global]').checked ? '1' : '');
    fetch('/hq/assembly/preview', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var frame = document.getElementById('asm-preview');
        if (!frame || !data.ok) return;
        var doc = frame.contentDocument || frame.contentWindow.document;
        doc.open();
        doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{margin:1rem;font-family:system-ui,sans-serif;max-width:100%;overflow-x:auto;} img,table,svg,video,iframe{max-width:100%;height:auto;}</style></head><body>' + (data.html || '') + '</body></html>');
        doc.close();
      })
      .catch(function () {});
  }

  var btn = document.getElementById('asm-refresh-preview');
  if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); refreshPreview(); });
  setTimeout(refreshPreview, 200);

  var copyBtn = document.getElementById('asm-copy-embed');
  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      var el = document.getElementById('asm-embed');
      if (!el) return;
      navigator.clipboard.writeText(el.textContent).then(function () {
        copyBtn.textContent = 'Copied';
        setTimeout(function () { copyBtn.textContent = 'Copy'; }, 1200);
      });
    });
  }
})();
</script>
