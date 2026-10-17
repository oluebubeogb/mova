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
$eo = function ($k, $d) use ($embedOpts) { return array_key_exists($k, $embedOpts) ? $embedOpts[$k] : $d; };
$gid = (int)($gallery['id'] ?? 0);


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
    <h3>In-page display</h3>
    <p class="field-hint" style="margin-top:0">Defaults for embeds on other pages. Default layout: carousel.</p>
    <div class="form-group">
      <label for="embed_layout">Layout</label>
      <select name="embed_layout" id="embed_layout">
        <?php foreach (['carousel'=>'Carousel / slides','columns'=>'Columns','grid'=>'Responsive grid','collage'=>'Collage'] as $k=>$lab): ?>
          <option value="<?= $k ?>" <?= ($embedLayout ?? 'carousel') === $k ? 'selected' : '' ?>><?= $lab ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label><input type="checkbox" name="embed_autoplay" value="1" <?= !empty($eo('autoplay', false)) ? 'checked' : '' ?>> Autoplay (carousel)</label>
    </div>
    <div class="form-group">
      <label for="embed_interval">Interval (ms)</label>
      <input type="number" name="embed_interval" id="embed_interval" min="2000" max="20000" value="<?= (int)$eo('interval', 5000) ?>">
    </div>
    <div class="form-group">
      <label><input type="checkbox" name="embed_arrows" value="1" <?= $eo('arrows', true) ? 'checked' : '' ?>> Arrows</label>
      <label style="margin-left:.75rem"><input type="checkbox" name="embed_dots" value="1" <?= $eo('dots', true) ? 'checked' : '' ?>> Dots</label>
    </div>
    <div class="form-group">
      <label for="embed_columns">Columns</label>
      <input type="number" name="embed_columns" id="embed_columns" min="2" max="4" value="<?= (int)$eo('columns', 3) ?>">
    </div>
    <div class="form-group">
      <label for="embed_min_width">Grid min width (px)</label>
      <input type="number" name="embed_min_width" id="embed_min_width" min="120" max="480" value="<?= (int)$eo('min_width', 200) ?>">
    </div>
    <div class="form-group">
      <label for="embed_gap">Gap (px)</label>
      <input type="number" name="embed_gap" id="embed_gap" min="0" max="48" value="<?= (int)$eo('gap', 12) ?>">
    </div>
    <div class="form-group">
      <label for="embed_aspect">Carousel aspect</label>
      <select name="embed_aspect" id="embed_aspect">
        <?php foreach (['16/9','4/3','1/1','3/2','auto'] as $a): ?>
          <option value="<?= $a ?>" <?= $eo('aspect','16/9') === $a ? 'selected' : '' ?>><?= $a ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($gid > 0): ?>
    <hr style="border:none;border-top:1px solid var(--hq-border,#e5e7eb);margin:1rem 0">
    <p style="font-size:.8rem;font-weight:600;margin:0 0 .35rem">Copy embed</p>
    <div class="g-copy-row">
      <input type="text" readonly id="g-sc" value='[gallery id="<?= $gid ?>" layout="<?= htmlspecialchars($embedLayout ?? 'carousel') ?>"]'>
      <button type="button" data-copy="g-sc">Copy</button>
    </div>
    <div class="g-copy-row">
      <input type="text" readonly id="g-html" value='<div class="mova-gallery" data-gallery="<?= $gid ?>" data-layout="<?= htmlspecialchars($embedLayout ?? 'carousel') ?>"></div>'>
      <button type="button" data-copy="g-html">Copy</button>
    </div>
    <p class="field-hint">Paste into any content body. Site-wide CSS/JS load automatically.</p>
    <?php else: ?>
    <p class="field-hint">Save the gallery to get shortcode and HTML mount.</p>
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
