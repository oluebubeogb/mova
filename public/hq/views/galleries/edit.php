<?php
use Mova\Security\Csrf;
$gallery = $gallery ?? null;
$items = $items ?? [];
$mediaLibrary = $mediaLibrary ?? [];
$form = $form ?? null;
$error = $error ?? null;

$titleVal = $form['title'] ?? ($gallery['title'] ?? '');
$slugVal = $form['slug'] ?? ($gallery['slug'] ?? '');
$descVal = $form['description'] ?? ($gallery['description'] ?? '');
$statusVal = $form['status'] ?? ($gallery['status'] ?? 'published');
$coverVal = $form['cover_media_id'] ?? ($gallery['cover_media_id'] ?? '');

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
</script>
