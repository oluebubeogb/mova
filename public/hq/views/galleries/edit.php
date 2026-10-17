<?php
use Mova\Security\Csrf;
$gallery = $gallery ?? null;
$items = $items ?? [];
$mediaLibrary = $mediaLibrary ?? [];
$form = $form ?? null;
$error = $error ?? null;

$titleVal = $form['title'] ?? ($gallery['title'] ?? '');
$slugVal = $form['slug'] ?? ($gallery['slug'] ?? '');
$groupSlugVal = $form['group_slug'] ?? ($gallery['group_slug'] ?? '');
$descVal = $form['description'] ?? ($gallery['description'] ?? '');
$statusVal = $form['status'] ?? ($gallery['status'] ?? 'published');
$coverVal = $form['cover_media_id'] ?? ($gallery['cover_media_id'] ?? '');
$embedLayout = $form['embed_layout'] ?? ($gallery['embed_layout'] ?? 'carousel');
$embedOpts = $form['embed_options'] ?? ($gallery['embed_options'] ?? []);
if (is_string($embedOpts)) { $embedOpts = json_decode($embedOpts, true) ?: []; }
$gid = (int)($gallery['id'] ?? 0);
$gSvc = new \Mova\Gallery\GalleryService();
$embedPresets = $gSvc->normalizePresets($embedOpts, (string)$embedLayout);



$selectedIds = $form['media_ids'] ?? [];
if (empty($selectedIds) && $items) {
    foreach ($items as $it) {
        $selectedIds[] = (int) $it['media_id'];
    }
}
$selectedIds = array_map('intval', $selectedIds);
$selectedMap = array_flip($selectedIds);
?>
<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Gallery saved. Public cache cleared.</div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;"><?= $gallery ? 'Edit gallery' : 'New gallery' ?></h2>
    <a href="/hq/galleries" class="btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> All galleries</a>
</div>

<form method="post" action="/hq/galleries/save" class="design-form" id="gallery-edit-form">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)($gallery['id'] ?? 0) ?>">

<style>
.g-edit-layout{display:flex;gap:1.25rem;align-items:flex-start;flex-wrap:wrap}
.g-edit-main{flex:1 1 62%;min-width:280px}
.g-edit-side{flex:0 0 30%;min-width:260px;max-width:360px;position:sticky;top:.75rem}
.g-edit-side .panel{background:var(--hq-surface,#fff);border:1px solid var(--hq-border,#e5e7eb);border-radius:10px;padding:1rem}
.g-edit-side h3{margin:0 0 .75rem;font-size:.95rem}
.g-copy-row{display:flex;gap:.35rem;margin:.35rem 0}
.g-copy-row input{flex:1;font-size:.8rem;padding:.35rem .5rem;border:1px solid var(--hq-border,#d1d5db);border-radius:6px;background:#f8fafc}
.g-copy-row button{font-size:.75rem;padding:.35rem .55rem;border-radius:6px;border:1px solid var(--hq-border);background:#f1f5f9;cursor:pointer}
</style>
<div class="g-edit-layout">
<div class="g-edit-main">

    <div class="form-group">
        <label for="g-title">Title</label>
        <input type="text" id="g-title" name="title" required value="<?= htmlspecialchars((string)$titleVal) ?>" placeholder="Summer collection">
    </div>

    <div class="form-group">
        <label for="g-slug">Slug</label>
        <input type="text" id="g-slug" name="slug" value="<?= htmlspecialchars((string)$slugVal) ?>" placeholder="auto from title"
               data-auto-slug="<?= $gallery ? '0' : '1' ?>">
        <p class="field-hint">Public URL: /gallery/<em>slug</em>. Auto-filled from the title (cannot be “gallery”). Edit anytime to override.</p>
    </div>

    <div class="form-group">
        <label for="g-group">Gallery group (optional)</label>
        <input type="text" id="g-group" name="group_slug" value="<?= htmlspecialchars((string)$groupSlugVal) ?>" placeholder="e.g. security">
        <p class="field-hint">Manual group slug. Visiting <code>/gallery/security</code> lists all galleries whose group is <code>security</code>. Leave empty for ungrouped. Does not change this gallery’s own URL.</p>
    </div>

    <div class="form-group">
        <label for="g-desc">Description</label>
        <textarea id="g-desc" name="description" rows="3" placeholder="Optional short description"><?= htmlspecialchars((string)$descVal) ?></textarea>
    </div>

    <div class="form-group">
        <label for="g-status">Status</label>
        <select id="g-status" name="status">
            <option value="published" <?= $statusVal === 'published' ? 'selected' : '' ?>>Published</option>
            <option value="draft" <?= $statusVal === 'draft' ? 'selected' : '' ?>>Draft</option>
        </select>
    </div>

    <div class="form-group">
        <label for="g-cover">Cover media ID (optional)</label>
        <input type="number" id="g-cover" name="cover_media_id" min="0" value="<?= $coverVal !== null && $coverVal !== '' ? (int)$coverVal : '' ?>" placeholder="Defaults to first image">
    </div>

    <div class="form-group">
        <label>Images</label>
        <p class="field-hint">Select images from the media library. Order is top-to-bottom (first selected = first in gallery).</p>
        <div id="gallery-selected" style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.75rem;min-height:2rem;"></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(5.5rem,1fr));gap:0.4rem;max-height:20rem;overflow:auto;border:1px solid var(--hq-border,#e5e7eb);border-radius:8px;padding:0.5rem;">
            <?php foreach ($mediaLibrary as $m): ?>
                <?php
                if (strpos((string)($m['mime_type'] ?? ''), 'image/') !== 0) {
                    continue;
                }
                $mid = (int) $m['id'];
                $checked = isset($selectedMap[$mid]);
                $svc = new \Mova\Media\MediaService();
                $thumb = $svc->url($m, 320);
                ?>
                <label style="display:block;cursor:pointer;position:relative;border-radius:6px;overflow:hidden;border:2px solid <?= $checked ? 'var(--hq-accent,#2563eb)' : 'transparent' ?>;aspect-ratio:1;">
                    <input type="checkbox" name="media_ids[]" value="<?= $mid ?>" <?= $checked ? 'checked' : '' ?>
                           style="position:absolute;top:4px;left:4px;z-index:1;"
                           data-gallery-pick>
                    <img src="<?= htmlspecialchars($thumb) ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;" loading="lazy">
                </label>
            <?php endforeach; ?>
        </div>
        <?php if (empty($mediaLibrary)): ?>
            <p class="empty">No media yet. <a href="/hq/media">Upload images</a> first.</p>
        <?php endif; ?>
    </div>

</div><!-- .g-edit-main -->
<aside class="g-edit-side">
  <div class="g-side-panel">
    <div class="g-side-head">
      <h3>In-page displays</h3>
      <span class="g-side-hint">Multiple styles · autosave</span>
    </div>
    <input type="hidden" name="embed_presets_json" id="embed_presets_json" value="">
    <div id="g-presets" class="g-presets"></div>
    <button type="button" class="g-add-preset" id="g-add-preset">
      <i class="fa-solid fa-plus"></i> Add preset
    </button>
    <div class="g-save-status" id="g-preset-status" aria-live="polite"></div>
  </div>
</aside>
</div><!-- .g-edit-layout -->

<div class="form-actions" style="display:flex;gap:0.5rem;margin-top:1.25rem;">
        <button type="submit" class="btn-primary">Save gallery</button>
        <a href="/hq/galleries" class="btn-ghost">Cancel</a>
        <?php if ($gallery && ($gallery['status'] ?? '') === 'published'): ?>
            <a href="/gallery/<?= htmlspecialchars($gallery['slug']) ?>" target="_blank" class="btn-ghost">View ↗</a>
        <?php endif; ?>
    </div>
</form>

<script>
(function () {
  var title = document.getElementById('g-title');
  var slug = document.getElementById('g-slug');
  if (!title || !slug) return;
  var auto = slug.getAttribute('data-auto-slug') === '1';
  function slugify(s) {
    s = (s || '').toLowerCase().trim();
    s = s.replace(/[^a-z0-9\s-]/g, '').replace(/[\s-]+/g, '-').replace(/^-+|-+$/g, '');
    if (!s || s === 'gallery') s = 'gallery-collection';
    return s;
  }
  title.addEventListener('input', function () {
    if (!auto) return;
    slug.value = slugify(title.value);
  });
  slug.addEventListener('input', function () {
    if (slug.value.trim() !== '') {
      auto = false;
      slug.setAttribute('data-auto-slug', '0');
    } else {
      auto = true;
      slug.setAttribute('data-auto-slug', '1');
    }
  });
  if (auto && !slug.value.trim() && title.value.trim()) {
    slug.value = slugify(title.value);
  }
})();
</script>


<style>
.g-edit-side { flex: 0 0 30%; min-width: 280px; max-width: 380px; }
.g-side-panel {
  background: var(--hq-surface, #fff);
  border: 1px solid var(--hq-border, #e5e7eb);
  border-radius: 12px;
  padding: 0.85rem 0.9rem 1rem;
}
.g-side-head { display: flex; align-items: baseline; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.65rem; }
.g-side-head h3 { margin: 0; font-size: 0.95rem; font-weight: 650; }
.g-side-hint { font-size: 0.72rem; color: var(--hq-muted, #94a3b8); }
.g-add-preset {
  width: 100%; margin-top: 0.5rem; padding: 0.45rem 0.6rem;
  border: 1px dashed var(--hq-border, #cbd5e1); border-radius: 8px;
  background: transparent; color: var(--hq-muted, #64748b);
  font: inherit; font-size: 0.82rem; cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center; gap: 0.35rem;
}
.g-add-preset:hover { border-color: var(--hq-accent, #0f766e); color: var(--hq-accent, #0f766e); background: #f8fafc; }
.g-save-status { min-height: 1.1rem; font-size: 0.72rem; color: var(--hq-muted, #94a3b8); margin-top: 0.4rem; text-align: right; }
.g-save-status.is-ok { color: #059669; }
.g-save-status.is-err { color: #dc2626; }

.g-pc {
  border: 1px solid var(--hq-border, #e5e7eb);
  border-radius: 10px;
  margin-bottom: 0.5rem;
  background: #fafbfc;
  overflow: hidden;
}
.g-pc.is-open { background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,0.04); }
.g-pc-head {
  display: flex; align-items: center; gap: 0.35rem;
  padding: 0.5rem 0.55rem; cursor: pointer; user-select: none;
}
.g-pc-head:hover { background: rgba(15,23,42,0.02); }
.g-pc-chevron {
  width: 1.1rem; text-align: center; color: #94a3b8; font-size: 0.7rem;
  transition: transform 0.15s ease;
}
.g-pc.is-open .g-pc-chevron { transform: rotate(90deg); }
.g-pc-title { flex: 1; font-size: 0.82rem; font-weight: 600; color: #334155; }
.g-pc-actions { display: flex; gap: 0.15rem; opacity: 0.35; transition: opacity 0.15s; }
.g-pc-head:hover .g-pc-actions, .g-pc.is-open .g-pc-actions { opacity: 1; }
.g-pc-btn {
  width: 1.7rem; height: 1.7rem; border: none; border-radius: 6px;
  background: transparent; color: #64748b; cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center;
  position: relative; font-size: 0.78rem;
}
.g-pc-btn:hover { background: #f1f5f9; color: #0f172a; }
.g-pc-btn.is-danger:hover { background: #fef2f2; color: #dc2626; }
.g-pc-btn.is-disabled,
.g-pc-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
  color: #94a3b8;
  pointer-events: auto;
}
.g-pc-btn.is-disabled:hover,
.g-pc-btn:disabled:hover {
  background: transparent;
  color: #94a3b8;
}
.g-pc-btn[data-tip]::after {
  content: attr(data-tip); position: absolute; bottom: calc(100% + 4px); left: 50%;
  transform: translateX(-50%); white-space: nowrap; font-size: 0.68rem; font-weight: 500;
  background: #0f172a; color: #fff; padding: 0.2rem 0.4rem; border-radius: 4px;
  opacity: 0; pointer-events: none; transition: opacity 0.12s;
}
.g-pc-btn:hover::after { opacity: 1; }

.g-pc-body { display: none; padding: 0 0.65rem 0.7rem; border-top: 1px solid #f1f5f9; }
.g-pc.is-open .g-pc-body { display: block; }

.g-row {
  display: grid; grid-template-columns: 7.2rem 1fr; gap: 0.4rem; align-items: center;
  margin-top: 0.45rem;
}
.g-row label {
  font-size: 0.72rem; font-weight: 600; color: #64748b; margin: 0;
}
.g-row input[type="text"],
.g-row input[type="number"],
.g-row input[type="color"],
.g-row select {
  width: 100%; box-sizing: border-box; font: inherit; font-size: 0.82rem;
  padding: 0.32rem 0.45rem; border: 1px solid #e2e8f0; border-radius: 6px;
  background: #fff; color: inherit;
}
.g-row input:focus, .g-row select:focus {
  outline: none; border-color: #94a3b8; box-shadow: none;
}
.g-row-check { display: flex; align-items: center; gap: 0.4rem; }
.g-row-check input { margin: 0; }
.g-color-wrap { display: flex; gap: 0.35rem; align-items: center; }
.g-color-wrap input[type="color"] { width: 2rem; height: 1.7rem; padding: 0; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; }
.g-color-wrap input[type="text"] { flex: 1; }
</style>
<script>
(function () {
  var gid = <?= (int)$gid ?>;
  var presets = <?= json_encode($embedPresets, JSON_UNESCAPED_UNICODE) ?>;
  var box = document.getElementById('g-presets');
  var hidden = document.getElementById('embed_presets_json');
  var statusEl = document.getElementById('g-preset-status');
  var csrf = document.querySelector('#gallery-edit-form input[name="_mova_csrf"], #gallery-edit-form input[name="_csrf"]');
  var csrfToken = csrf ? csrf.value : '';
  var saveTimer = null;
  var openIdx = 0;

  var layouts = [
    {v:'carousel', l:'Carousel'},
    {v:'columns', l:'Columns'},
    {v:'grid', l:'Grid'},
    {v:'collage', l:'Collage'}
  ];
  var nameModes = [
    {v:'hover', l:'Show name on hover'},
    {v:'always', l:'Always show name'},
    {v:'caption', l:'Show name as caption'},
    {v:'hide', l:'Hide name'}
  ];

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
  }

  function fixedKey(idx) {
    return idx === 0 ? 'default' : ('preset-' + (idx + 1));
  }

  function iconBtn(action, icon, tip, danger) {
    return '<button type="button" class="g-pc-btn'+(danger?' is-danger':'')+'" data-act="'+action+'" data-tip="'+tip+'"><i class="fa-solid '+icon+'"></i></button>';
  }

  function row(label, html) {
    return '<div class="g-row"><label>'+label+'</label><div>'+html+'</div></div>';
  }

  function card(p, idx) {
    p = p || {};
    var key = fixedKey(idx);
    var nameMode = p.name_mode || (p.hover_name ? 'hover' : 'hide');
    var div = document.createElement('div');
    div.className = 'g-pc' + (idx === openIdx ? ' is-open' : '');
    div.dataset.idx = String(idx);

    var layoutOpts = layouts.map(function (o) {
      return '<option value="'+o.v+'"'+(p.layout===o.v?' selected':'')+'>'+o.l+'</option>';
    }).join('');
    var nameOpts = nameModes.map(function (o) {
      return '<option value="'+o.v+'"'+(nameMode===o.v?' selected':'')+'>'+o.l+'</option>';
    }).join('');
    var overlay = p.overlay_color || 'rgba(15,23,42,0.72)';
    var overlayHex = '#0f172a';
    if (/^#/.test(overlay)) overlayHex = overlay.slice(0, 7);

    div.innerHTML =
      '<div class="g-pc-head" data-toggle>' +
        '<span class="g-pc-chevron"><i class="fa-solid fa-chevron-right"></i></span>' +
        '<span class="g-pc-title">Preset ' + (idx + 1) + '</span>' +
        '<span class="g-pc-actions">' +
          iconBtn('copy-sc', 'fa-code', 'Copy shortcode') +
          iconBtn('copy-html', 'fa-file-code', 'Copy HTML') +
          (idx === 0
            ? '<button type="button" class="g-pc-btn is-disabled" data-tip="Default preset cannot be removed" disabled aria-disabled="true"><i class="fa-solid fa-trash"></i></button>'
            : iconBtn('remove', 'fa-trash', 'Remove', true)) +
        '</span>' +
      '</div>' +
      '<div class="g-pc-body">' +
        row('Name', '<input type="text" data-k="name" value="'+esc(p.name || ('Preset ' + (idx + 1)))+'">') +
        row('Layout', '<select data-k="layout">'+layoutOpts+'</select>') +
        row('Interval (ms)', '<input type="number" data-k="interval" min="2000" max="20000" value="'+(p.interval||5000)+'">') +
        row('Slides visible', '<input type="number" data-k="slides_visible" min="1" max="6" value="'+(p.slides_visible||1)+'">') +
        row('Max height', '<input type="text" data-k="max_height" value="'+esc(p.max_height||'20vh')+'" placeholder="20vh or 180px">') +
        row('Radius', '<input type="text" data-k="radius" value="'+esc(p.radius||'8px')+'" placeholder="8px">') +
        row('Aspect', '<select data-k="aspect">' +
          ['16/9','4/3','1/1','3/2','auto'].map(function(a){
            return '<option value="'+a+'"'+((p.aspect||'16/9')===a?' selected':'')+'>'+a+'</option>';
          }).join('') + '</select>') +
        row('Columns', '<input type="number" data-k="columns" min="2" max="4" value="'+(p.columns||3)+'">') +
        row('Grid min width', '<input type="number" data-k="min_width" min="120" max="480" value="'+(p.min_width||200)+'">') +
        row('Gap', '<input type="number" data-k="gap" min="0" max="48" value="'+(p.gap||12)+'">') +
        row('Autoplay', '<label class="g-row-check"><input type="checkbox" data-k="autoplay"'+(p.autoplay?' checked':'')+'> Enable</label>') +
        row('Arrows', '<label class="g-row-check"><input type="checkbox" data-k="arrows"'+(p.arrows!==false?' checked':'')+'> Show</label>') +
        row('Dots', '<label class="g-row-check"><input type="checkbox" data-k="dots"'+(p.dots!==false?' checked':'')+'> Show</label>') +
        row('Display name', '<select data-k="name_mode">'+nameOpts+'</select>') +
        row('Overlay color', '<div class="g-color-wrap"><input type="color" data-k="overlay_picker" value="'+esc(overlayHex)+'"><input type="text" data-k="overlay_color" value="'+esc(overlay)+'" placeholder="rgba(...) or #hex"></div>') +
      '</div>';

    // toggle
    div.querySelector('[data-toggle]').addEventListener('click', function (e) {
      if (e.target.closest('[data-act]')) return;
      var was = div.classList.contains('is-open');
      box.querySelectorAll('.g-pc').forEach(function (c) { c.classList.remove('is-open'); });
      if (!was) {
        div.classList.add('is-open');
        openIdx = idx;
      }
    });

    // actions
    div.querySelectorAll('[data-act]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var act = btn.getAttribute('data-act');
        var keyNow = fixedKey(idx);
        if (act === 'copy-sc') {
          copyText('[gallery id="'+gid+'" preset="'+keyNow+'"]', btn);
        } else if (act === 'copy-html') {
          copyText('<div class="mova-gallery" data-gallery="'+gid+'" data-preset="'+keyNow+'"></div>', btn);
        } else if (act === 'remove') {
          if (idx === 0 || fixedKey(idx) === 'default') {
            setStatus('Default preset cannot be removed', true);
            return;
          }
          if (box.querySelectorAll('.g-pc').length <= 1) {
            setStatus('Keep at least one preset', true);
            return;
          }
          var label = (div.querySelector('[data-k="name"]') || {}).value
            || (div.querySelector('.g-pc-title') || {}).textContent
            || ('Preset ' + (idx + 1));
          if (!confirm('Delete “' + label + '”? This cannot be undone.')) {
            return;
          }
          div.remove();
          reindexTitles();
          scheduleSave();
        }
      });
    });

    // fields
    div.querySelectorAll('input,select').forEach(function (el) {
      el.addEventListener('input', onField);
      el.addEventListener('change', onField);
    });
    var picker = div.querySelector('[data-k="overlay_picker"]');
    var colorText = div.querySelector('[data-k="overlay_color"]');
    if (picker && colorText) {
      picker.addEventListener('input', function () {
        colorText.value = picker.value;
        scheduleSave();
      });
    }
    return div;
  }

  function onField() { scheduleSave(); }

  function reindexTitles() {
    box.querySelectorAll('.g-pc').forEach(function (c, i) {
      c.dataset.idx = String(i);
      var t = c.querySelector('.g-pc-title');
      if (t) t.textContent = 'Preset ' + (i + 1);
    });
  }

  function collect() {
    var out = [];
    box.querySelectorAll('.g-pc').forEach(function (div, idx) {
      var get = function (k) {
        var el = div.querySelector('[data-k="'+k+'"]');
        if (!el) return '';
        if (el.type === 'checkbox') return el.checked;
        return el.value;
      };
      var nameMode = get('name_mode') || 'hide';
      out.push({
        key: fixedKey(idx),
        name: get('name') || ('Preset ' + (idx + 1)),
        layout: get('layout') || 'carousel',
        interval: parseInt(get('interval'), 10) || 5000,
        slides_visible: parseInt(get('slides_visible'), 10) || 1,
        max_height: get('max_height') || '20vh',
        radius: get('radius') || '8px',
        aspect: get('aspect') || '16/9',
        columns: parseInt(get('columns'), 10) || 3,
        min_width: parseInt(get('min_width'), 10) || 200,
        gap: parseInt(get('gap'), 10) || 12,
        autoplay: !!get('autoplay'),
        arrows: !!get('arrows'),
        dots: !!get('dots'),
        name_mode: nameMode,
        hover_name: nameMode === 'hover',
        overlay_color: get('overlay_color') || 'rgba(15,23,42,0.72)',
        lightbox: true
      });
    });
    return out;
  }

  function setStatus(msg, err) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.className = 'g-save-status' + (err ? ' is-err' : (msg ? ' is-ok' : ''));
  }

  function copyText(text, btn) {
    if (!gid) {
      setStatus('Save the gallery first to copy embeds', true);
      return;
    }
    navigator.clipboard.writeText(text).then(function () {
      var tip = btn.getAttribute('data-tip');
      btn.setAttribute('data-tip', 'Copied');
      setTimeout(function () { btn.setAttribute('data-tip', tip); }, 1000);
    });
  }

  function scheduleSave() {
    hidden.value = JSON.stringify(collect());
    clearTimeout(saveTimer);
    if (!gid) {
      setStatus('Save gallery once to enable preset autosave');
      return;
    }
    setStatus('Saving…');
    saveTimer = setTimeout(savePresets, 450);
  }

  function savePresets() {
    var list = collect();
    hidden.value = JSON.stringify(list);
    fetch('/hq/galleries/presets/' + gid, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify({ presets: list })
    }).then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) {
          setStatus(data.error || 'Save failed', true);
          return;
        }
        setStatus('Saved');
        setTimeout(function () {
          if (statusEl && statusEl.textContent === 'Saved') setStatus('');
        }, 1500);
      })
      .catch(function () { setStatus('Network error', true); });
  }

  function render() {
    box.innerHTML = '';
    if (!presets.length) {
      presets = [{ key: 'default', name: 'Default', layout: 'carousel', max_height: '20vh', slides_visible: 1, name_mode: 'hide' }];
    }
    presets.forEach(function (p, i) { box.appendChild(card(p, i)); });
    hidden.value = JSON.stringify(collect());
  }

  document.getElementById('g-add-preset').addEventListener('click', function () {
    var n = box.querySelectorAll('.g-pc').length;
    openIdx = n;
    box.appendChild(card({
      name: 'Preset ' + (n + 1),
      layout: 'carousel',
      autoplay: false,
      max_height: '20vh',
      slides_visible: 3,
      radius: '8px',
      name_mode: 'hover'
    }, n));
    scheduleSave();
  });

  var form = document.getElementById('gallery-edit-form');
  if (form) form.addEventListener('submit', function () {
    hidden.value = JSON.stringify(collect());
  });

  render();
})();
</script>
