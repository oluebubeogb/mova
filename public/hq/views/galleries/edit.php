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
  <div class="panel">
    <h3>In-page displays</h3>
    <p class="field-hint" style="margin-top:0">One gallery, many embed styles. Each preset has its own layout &amp; options (e.g. autoplay on one, off on another).</p>
    <input type="hidden" name="embed_presets_json" id="embed_presets_json" value="">
    <div id="g-presets"></div>
    <button type="button" class="btn-ghost btn-sm" id="g-add-preset" style="margin-top:0.5rem">+ Add display preset</button>
    <?php if ($gid > 0): ?>
    <hr style="border:none;border-top:1px solid var(--hq-border,#e5e7eb);margin:1rem 0">
    <p style="font-size:.8rem;font-weight:600;margin:0 0 .35rem">Copy embed (active preset)</p>
    <div class="g-copy-row">
      <input type="text" readonly id="g-sc" value="">
      <button type="button" data-copy="g-sc">Copy</button>
    </div>
    <div class="g-copy-row">
      <input type="text" readonly id="g-html" value="">
      <button type="button" data-copy="g-html">Copy</button>
    </div>
    <p class="field-hint">Paste into content. CSS/JS load automatically.</p>
    <?php else: ?>
    <p class="field-hint">Save the gallery to copy shortcode / HTML.</p>
    <?php endif; ?>
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
    // user edited slug manually — stop auto unless empty
    if (slug.value.trim() !== '') {
      auto = false;
      slug.setAttribute('data-auto-slug', '0');
    } else {
      auto = true;
      slug.setAttribute('data-auto-slug', '1');
    }
  });
  // On new gallery with empty slug, seed once from title
  if (auto && !slug.value.trim() && title.value.trim()) {
    slug.value = slugify(title.value);
  }
})();
document.querySelectorAll('[data-copy]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var id = btn.getAttribute('data-copy');
    var input = document.getElementById(id);
    if (!input) return;
    input.select();
    navigator.clipboard.writeText(input.value).then(function () {
      btn.textContent = 'Copied';
      setTimeout(function () { btn.textContent = 'Copy'; }, 1200);
    });
  });
});
</script>


<script>
(function () {
  var gid = <?= (int)$gid ?>;
  var presets = <?= json_encode($embedPresets, JSON_UNESCAPED_UNICODE) ?>;
  var box = document.getElementById('g-presets');
  var hidden = document.getElementById('embed_presets_json');
  var layouts = [
    {v:'carousel', l:'Carousel / slides'},
    {v:'columns', l:'Columns'},
    {v:'grid', l:'Responsive grid'},
    {v:'collage', l:'Collage'}
  ];

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
  }

  function card(p, idx) {
    p = p || {};
    var div = document.createElement('div');
    div.className = 'g-preset-card';
    div.dataset.idx = String(idx);
    div.style.cssText = 'border:1px solid var(--hq-border,#e5e7eb);border-radius:8px;padding:0.65rem;margin-bottom:0.55rem;background:#fafbfc';
    var layoutOpts = layouts.map(function (o) {
      return '<option value="'+o.v+'"'+(p.layout===o.v?' selected':'')+'>'+o.l+'</option>';
    }).join('');
    div.innerHTML =
      '<div style="display:flex;justify-content:space-between;gap:0.35rem;align-items:center;margin-bottom:0.4rem">' +
      '<strong style="font-size:0.8rem">Preset ' + (idx+1) + '</strong>' +
      '<button type="button" data-rm class="btn-ghost btn-sm" style="padding:0.15rem 0.4rem">Remove</button></div>' +
      '<label class="g-pl">Name<input data-k="name" value="'+esc(p.name||('Preset '+(idx+1)))+'"></label>' +
      '<label class="g-pl">Key<input data-k="key" value="'+esc(p.key||('preset-'+(idx+1)))+'" placeholder="homepage"></label>' +
      '<label class="g-pl">Layout<select data-k="layout">'+layoutOpts+'</select></label>' +
      '<label class="g-pl"><input type="checkbox" data-k="autoplay"'+(p.autoplay?' checked':'')+'> Autoplay</label>' +
      '<label class="g-pl">Interval (ms)<input data-k="interval" type="number" min="2000" max="20000" value="'+(p.interval||5000)+'"></label>' +
      '<label class="g-pl"><input type="checkbox" data-k="arrows"'+(p.arrows!==false?' checked':'')+'> Arrows</label>' +
      '<label class="g-pl"><input type="checkbox" data-k="dots"'+(p.dots!==false?' checked':'')+'> Dots</label>' +
      '<label class="g-pl">Slides visible<input data-k="slides_visible" type="number" min="1" max="6" value="'+(p.slides_visible||1)+'"></label>' +
      '<label class="g-pl">Max height (px/vh)<input data-k="max_height" value="'+esc(p.max_height||'20vh')+'" placeholder="20vh or 180px"></label>' +
      '<label class="g-pl">Radius<input data-k="radius" value="'+esc(p.radius||'8px')+'" placeholder="8px"></label>' +
      '<label class="g-pl">Aspect<select data-k="aspect">' +
        ['16/9','4/3','1/1','3/2','auto'].map(function(a){return '<option value="'+a+'"'+( (p.aspect||'16/9')===a?' selected':'')+'>'+a+'</option>';}).join('') +
      '</select></label>' +
      '<label class="g-pl">Columns<input data-k="columns" type="number" min="2" max="4" value="'+(p.columns||3)+'"></label>' +
      '<label class="g-pl">Grid min width<input data-k="min_width" type="number" min="120" max="480" value="'+(p.min_width||200)+'"></label>' +
      '<label class="g-pl">Gap (px)<input data-k="gap" type="number" min="0" max="48" value="'+(p.gap||12)+'"></label>' +
      '<label class="g-pl"><input type="checkbox" data-k="hover_name"'+(p.hover_name?' checked':'')+'> Show name on hover</label>';
    div.querySelector('[data-rm]').onclick = function () {
      if (box.querySelectorAll('.g-preset-card').length <= 1) { alert('Keep at least one preset.'); return; }
      div.remove();
      syncCopy();
    };
    div.querySelectorAll('input,select').forEach(function (el) {
      el.addEventListener('input', syncCopy);
      el.addEventListener('change', syncCopy);
    });
    return div;
  }

  function collect() {
    var out = [];
    box.querySelectorAll('.g-preset-card').forEach(function (div) {
      var get = function (k) {
        var el = div.querySelector('[data-k="'+k+'"]');
        if (!el) return '';
        if (el.type === 'checkbox') return el.checked;
        return el.value;
      };
      out.push({
        key: get('key') || 'default',
        name: get('name') || 'Default',
        layout: get('layout') || 'carousel',
        autoplay: !!get('autoplay'),
        interval: parseInt(get('interval'), 10) || 5000,
        arrows: !!get('arrows'),
        dots: !!get('dots'),
        slides_visible: parseInt(get('slides_visible'), 10) || 1,
        max_height: get('max_height') || '20vh',
        radius: get('radius') || '8px',
        aspect: get('aspect') || '16/9',
        columns: parseInt(get('columns'), 10) || 3,
        min_width: parseInt(get('min_width'), 10) || 200,
        gap: parseInt(get('gap'), 10) || 12,
        hover_name: !!get('hover_name'),
        lightbox: true
      });
    });
    return out;
  }

  function syncCopy() {
    var list = collect();
    hidden.value = JSON.stringify(list);
    var p = list[0] || { key: 'default', layout: 'carousel' };
    var sc = document.getElementById('g-sc');
    var html = document.getElementById('g-html');
    if (sc && gid) sc.value = '[gallery id="'+gid+'" preset="'+p.key+'"]';
    if (html && gid) html.value = '<div class="mova-gallery" data-gallery="'+gid+'" data-preset="'+p.key+'" data-layout="'+p.layout+'"></div>';
  }

  function render() {
    box.innerHTML = '';
    if (!presets.length) presets = [{ key:'default', name:'Default', layout:'carousel', max_height:'20vh', slides_visible:1 }];
    presets.forEach(function (p, i) { box.appendChild(card(p, i)); });
    syncCopy();
  }

  document.getElementById('g-add-preset').addEventListener('click', function () {
    box.appendChild(card({
      key: 'preset-' + (box.querySelectorAll('.g-preset-card').length + 1),
      name: 'New display',
      layout: 'carousel',
      autoplay: false,
      max_height: '20vh',
      slides_visible: 3,
      radius: '8px',
      hover_name: true
    }, box.querySelectorAll('.g-preset-card').length));
    syncCopy();
  });

  var form = document.getElementById('gallery-edit-form');
  if (form) form.addEventListener('submit', function () { hidden.value = JSON.stringify(collect()); });

  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-copy'));
      if (!input) return;
      input.select();
      navigator.clipboard.writeText(input.value).then(function () {
        btn.textContent = 'Copied';
        setTimeout(function () { btn.textContent = 'Copy'; }, 1200);
      });
    });
  });

  render();
})();
</script>
<style>
.g-pl { display:block; font-size:0.72rem; color:#64748b; margin:0.28rem 0; font-weight:600; }
.g-pl input[type="text"], .g-pl input[type="number"], .g-pl select {
  display:block; width:100%; margin-top:0.15rem; font-weight:400; font-size:0.85rem;
  padding:0.3rem 0.4rem; border:1px solid #d1d5db; border-radius:6px; background:#fff; box-sizing:border-box;
}
.g-pl input[type="checkbox"] { margin-right:0.35rem; }
</style>
