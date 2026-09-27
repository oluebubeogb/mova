<?php
use Mova\Security\Csrf;
$cfg = $cfg ?? [];
$sources = $sources ?? [];
$palette = $palette ?? [];
$layer = $_GET['layer'] ?? 'settings';
if (!in_array($layer, ['settings', 'design', 'knowledge'], true)) {
    $layer = 'settings';
}
?>
<div class="hq-page">
  <h1>Site AI Engine</h1>
  <p class="muted">Public assistant for visitors on your live site — separate from HQ Mova AI. Enable the <strong>Mova Site AI</strong> plugin, then configure below.</p>

  <div class="hq-layers" data-layer-key="mova_hq_layer_site_ai">
    <div class="hq-layer-tabs" role="tablist" style="display:none" aria-hidden="true">
      <button type="button" class="hq-layer-tab <?= $layer === 'settings' ? 'is-active' : '' ?>" data-layer="settings" role="tab">Settings</button>
      <button type="button" class="hq-layer-tab <?= $layer === 'design' ? 'is-active' : '' ?>" data-layer="design" role="tab">Design</button>
      <button type="button" class="hq-layer-tab <?= $layer === 'knowledge' ? 'is-active' : '' ?>" data-layer="knowledge" role="tab">Knowledge bank</button>
    </div>

    <!-- Settings -->
    <div class="hq-layer-panel <?= $layer === 'settings' ? 'is-active' : '' ?>" data-layer-panel="settings" style="<?= $layer !== 'settings' ? 'display:none' : '' ?>">
      <form method="post" class="card" style="padding:1.25rem;margin-bottom:1.5rem;">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="layer" value="settings">
        <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
          <input type="checkbox" name="enabled" value="1" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>>
          Enable on public site
        </label>
        <div class="form-group">
          <label>AI display name</label>
          <input type="text" name="name" class="input" value="<?= htmlspecialchars($cfg['name'] ?? 'Site Assistant') ?>" placeholder="e.g. Shop Helper">
        </div>
        <div class="form-group">
          <label>Optional welcome (shown under greeting)</label>
          <input type="text" name="welcome" class="input" value="<?= htmlspecialchars($cfg['welcome'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary">Save settings</button>
      </form>
    </div>

    <!-- Design -->
    <div class="hq-layer-panel <?= $layer === 'design' ? 'is-active' : '' ?>" data-layer-panel="design" style="<?= $layer !== 'design' ? 'display:none' : '' ?>">
      <form method="post" class="card" style="padding:1.25rem;margin-bottom:1.5rem;">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="layer" value="design">
        <p class="muted" style="margin-top:0;">Leave blank to use site design tokens (<code>var(--color-surface)</code> for panel background, theme primary/accent for chrome).</p>
        <div class="form-row" style="display:flex;gap:1rem;flex-wrap:wrap;">
          <div class="form-group">
            <label>Override primary (blank = site palette)</label>
            <div style="display:flex;align-items:center;gap:0.5rem;">
              <input type="color" id="primary_picker" value="<?= htmlspecialchars(($cfg['primary'] ?? '') !== '' ? $cfg['primary'] : ($palette['primary'] ?? '#2563eb')) ?>" title="Pick primary">
              <input type="text" name="primary" id="primary_hex" class="input" placeholder="<?= htmlspecialchars($palette['primary'] ?? '#2563eb') ?>" value="<?= htmlspecialchars($cfg['primary'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label>Override accent (blank = site accent)</label>
            <div style="display:flex;align-items:center;gap:0.5rem;">
              <input type="color" id="accent_picker" value="<?= htmlspecialchars(($cfg['accent'] ?? '') !== '' ? $cfg['accent'] : ($palette['accent'] ?? '#7c3aed')) ?>" title="Pick accent">
              <input type="text" name="accent" id="accent_hex" class="input" placeholder="<?= htmlspecialchars($palette['accent'] ?? '#7c3aed') ?>" value="<?= htmlspecialchars($cfg['accent'] ?? '') ?>">
            </div>
          </div>
        </div>
        <div class="form-row" style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:0.5rem;">
          <div class="form-group">
            <label>Panel background — light mode</label>
            <div style="display:flex;align-items:center;gap:0.5rem;">
              <input type="color" id="bg_light_picker" value="<?= htmlspecialchars(($cfg['bg_light'] ?? '') !== '' ? $cfg['bg_light'] : ($palette['surface'] ?? '#ffffff')) ?>" title="Pick light background">
              <input type="text" name="bg_light" id="bg_light_hex" class="input" placeholder="var(--color-surface)" value="<?= htmlspecialchars($cfg['bg_light'] ?? '') ?>">
            </div>
            <p class="field-hint muted" style="font-size:0.8rem;margin:0.25rem 0 0;">Empty = <code>var(--color-surface)</code></p>
          </div>
          <div class="form-group">
            <label>Panel background — dark mode</label>
            <div style="display:flex;align-items:center;gap:0.5rem;">
              <input type="color" id="bg_dark_picker" value="<?= htmlspecialchars(($cfg['bg_dark'] ?? '') !== '' ? $cfg['bg_dark'] : ($palette['surface_dark'] ?? '#1e293b')) ?>" title="Pick dark background">
              <input type="text" name="bg_dark" id="bg_dark_hex" class="input" placeholder="var(--color-surface)" value="<?= htmlspecialchars($cfg['bg_dark'] ?? '') ?>">
            </div>
            <p class="field-hint muted" style="font-size:0.8rem;margin:0.25rem 0 0;">Empty = <code>var(--color-surface)</code> (dark theme token)</p>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:1rem;">Save design</button>
      </form>
    </div>

    <!-- Knowledge bank -->
    <div class="hq-layer-panel <?= $layer === 'knowledge' ? 'is-active' : '' ?>" data-layer-panel="knowledge" style="<?= $layer !== 'knowledge' ? 'display:none' : '' ?>">
      <p class="muted">Index text, URLs, and files (txt, md, html, csv, json, xlsx, docx, pdf). Indexing runs in the background; progress appears below after upload.</p>

      <form method="post" class="card" style="padding:1rem;margin-bottom:1rem;">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="add_text">
        <input type="hidden" name="layer" value="knowledge">
        <div class="form-group">
          <label>Title</label>
          <input type="text" name="source_title" class="input" placeholder="Store hours">
        </div>
        <div class="form-group">
          <label>Text</label>
          <textarea name="source_text" class="input" rows="4" placeholder="Paste policies, FAQs, stock notes…"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Add text</button>
      </form>

      <form method="post" class="card" style="padding:1rem;margin-bottom:1rem;">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="add_url">
        <input type="hidden" name="layer" value="knowledge">
        <div class="form-group">
          <label>Live URL</label>
          <input type="url" name="source_url" class="input" placeholder="https://…">
        </div>
        <button type="submit" class="btn btn-primary">Fetch &amp; index URL</button>
      </form>

      <form method="post" enctype="multipart/form-data" class="card" id="kb-upload-form" style="padding:1rem;margin-bottom:1rem;">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="layer" value="knowledge">
        <div class="form-group">
          <label>Upload files (multiple allowed)</label>
          <input type="file" name="source_files[]" id="kb-files" multiple
                 accept=".txt,.md,.html,.htm,.csv,.json,.xml,.docx,.doc,.pdf,.xlsx,.xls">
        </div>
        <button type="submit" class="btn btn-primary" id="kb-upload-btn">Upload &amp; index</button>
        <div id="kb-index-progress" style="display:none;margin-top:1rem;" aria-live="polite">
          <p class="muted" style="margin:0 0 0.5rem;"><strong>Indexing…</strong> <span id="kb-progress-label">0%</span></p>
          <div style="height:8px;background:var(--hq-border, #e5e7eb);border-radius:4px;overflow:hidden;">
            <div id="kb-progress-bar" style="height:100%;width:0%;background:var(--hq-primary, #2563eb);transition:width 0.2s;"></div>
          </div>
          <ul id="kb-progress-list" class="muted" style="font-size:0.85rem;margin:0.75rem 0 0;padding-left:1.25rem;"></ul>
        </div>
      </form>

      <hr style="border:none;border-top:1px solid var(--hq-border, #e5e7eb);margin:1.5rem 0;">

      <h2 style="margin-top:0;">Indexed sources</h2>
      <table class="table">
        <thead><tr><th>Title</th><th>Type</th><th>Status</th><th>Updated</th><th></th></tr></thead>
        <tbody id="kb-sources-body">
        <?php if (!$sources): ?>
          <tr><td colspan="5" class="muted">No sources yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($sources as $s): ?>
          <tr data-source-id="<?= (int)$s['id'] ?>">
            <td><?= htmlspecialchars($s['title'] ?? '') ?></td>
            <td><?= htmlspecialchars($s['type'] ?? '') ?></td>
            <td>
              <?php
              $st = $s['status'] ?? 'ready';
              $stClass = $st === 'ready' ? '' : ($st === 'indexing' ? 'muted' : 'muted');
              ?>
              <span class="<?= $stClass ?>"><?= htmlspecialchars($st) ?></span>
            </td>
            <td><?= htmlspecialchars(substr((string)($s['updated_at'] ?? ''), 0, 16)) ?></td>
            <td>
              <form method="post" style="display:inline" onsubmit="return confirm('Remove this source?');">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="layer" value="knowledge">
                <input type="hidden" name="source_id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="btn btn-sm btn-ghost">Clear</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
(function () {
  function bindColor(pickerId, hexId) {
    var p = document.getElementById(pickerId);
    var h = document.getElementById(hexId);
    if (!p || !h) return;
    p.addEventListener('input', function () { h.value = p.value; });
    h.addEventListener('input', function () {
      if (/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(h.value)) p.value = h.value;
    });
  }
  bindColor('primary_picker', 'primary_hex');
  bindColor('accent_picker', 'accent_hex');
  bindColor('bg_light_picker', 'bg_light_hex');
  bindColor('bg_dark_picker', 'bg_dark_hex');

  var form = document.getElementById('kb-upload-form');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    var input = document.getElementById('kb-files');
    if (!input || !input.files || input.files.length === 0) return;
    e.preventDefault();
    var files = Array.from(input.files);
    var prog = document.getElementById('kb-index-progress');
    var bar = document.getElementById('kb-progress-bar');
    var label = document.getElementById('kb-progress-label');
    var list = document.getElementById('kb-progress-list');
    var btn = document.getElementById('kb-upload-btn');
    prog.style.display = 'block';
    list.innerHTML = '';
    btn.disabled = true;
    var done = 0;
    var total = files.length;
    function update() {
      var pct = total ? Math.round((done / total) * 100) : 0;
      bar.style.width = pct + '%';
      label.textContent = pct + '% (' + done + '/' + total + ')';
    }
    update();
    var csrf = form.querySelector('input[name="_mova_csrf"], input[name*="csrf"]');
    var csrfName = csrf ? csrf.name : '_mova_csrf';
    var csrfVal = csrf ? csrf.value : '';

    function next(i) {
      if (i >= files.length) {
        btn.disabled = false;
        label.textContent = 'Complete — refreshing…';
        setTimeout(function () {
          window.location.href = '/hq/site-ai?layer=knowledge';
        }, 600);
        return;
      }
      var f = files[i];
      var li = document.createElement('li');
      li.textContent = f.name + ' — indexing…';
      list.appendChild(li);
      var fd = new FormData();
      fd.append('action', 'upload_one');
      fd.append('layer', 'knowledge');
      fd.append(csrfName, csrfVal);
      fd.append('source_file', f);
      fetch('/hq/site-ai', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (data) {
          done++;
          li.textContent = f.name + ' — ' + (data.ok ? 'ready' : (data.error || 'failed'));
          update();
          next(i + 1);
        })
        .catch(function () {
          done++;
          li.textContent = f.name + ' — failed';
          update();
          next(i + 1);
        });
    }
    next(0);
  });
})();
</script>
