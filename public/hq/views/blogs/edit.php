<?php
/** @var ?array $blog */
/** @var array $types */
/** @var array $categories */
/** @var array $tags */
/** @var array $posts */
/** @var array $itemIds */
$blog = $blog ?? null;
$form = $form ?? [];
$id = (int)($blog['id'] ?? 0);
$title = $form['title'] ?? ($blog['title'] ?? '');
$slug = $form['slug'] ?? ($blog['slug'] ?? '');
$desc = $form['description'] ?? ($blog['description'] ?? '');
$status = $form['status'] ?? ($blog['status'] ?? 'published');
$mode = $form['mode'] ?? ($blog['mode'] ?? 'filter');
$allowed = $form['allowed_types'] ?? ($blog['allowed_types'] ?? ['article']);
$catIds = $form['category_ids'] ?? ($blog['category_ids'] ?? []);
$tagIds = $form['tag_ids'] ?? ($blog['tag_ids'] ?? []);
$sort = $form['sort'] ?? ($blog['sort'] ?? 'date_desc');
$perPage = (int)($form['per_page'] ?? ($blog['per_page'] ?? 12));
$excerptChars = (int)($form['excerpt_chars'] ?? ($blog['excerpt_chars'] ?? 130));
$tplMode = $form['template_mode'] ?? ($blog['template_mode'] ?? 'default');
$tplHtml = $form['template_html'] ?? ($blog['template_html'] ?? '');
$embedLimit = (int)(($blog['embed_options']['limit'] ?? 6));
$itemIds = $itemIds ?? [];
$isDefault = ($blog['slug'] ?? '') === 'blog';
$publicPath = $isDefault || $slug === 'blog' ? '/blog' : '/blog/' . htmlspecialchars($slug ?: '…');
?>
<section class="hq-page">
  <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
    <div>
      <h1><?= $id ? 'Edit blog' : 'New blog' ?></h1>
      <p class="muted">Public URL: <code><?= $publicPath ?></code></p>
    </div>
    <a class="btn-ghost" href="/hq/blogs">← All blogs</a>
  </div>
  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Saved.</div>
  <?php endif; ?>

  <form method="post" action="/hq/blogs/save" id="blog-form" class="blog-edit-form">
    <?= \Mova\Security\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="blog-edit-layout">
      <div class="blog-edit-main">
        <div class="form-group">
          <label for="b-title">Title</label>
          <input type="text" id="b-title" name="title" value="<?= htmlspecialchars($title) ?>" required>
        </div>
        <div class="form-group">
          <label for="b-slug">Slug</label>
          <input type="text" id="b-slug" name="slug" value="<?= htmlspecialchars($slug) ?>" <?= $isDefault ? 'readonly' : '' ?>
                 data-auto-slug="<?= $id ? '0' : '1' ?>">
          <?php if ($isDefault): ?>
            <p class="field-hint">Default blog is locked to <code>blog</code> → <code>/blog</code></p>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label for="b-desc">Description</label>
          <textarea id="b-desc" name="description" rows="2"><?= htmlspecialchars((string)$desc) ?></textarea>
        </div>
        <div class="form-row" style="display:flex;gap:1rem;flex-wrap:wrap">
          <div class="form-group" style="flex:1;min-width:140px">
            <label for="b-status">Status</label>
            <select id="b-status" name="status">
              <option value="published" <?= $status==='published'?'selected':'' ?>>Published</option>
              <option value="draft" <?= $status==='draft'?'selected':'' ?>>Draft</option>
            </select>
          </div>
          <div class="form-group" style="flex:1;min-width:140px">
            <label for="b-mode">Source mode</label>
            <select id="b-mode" name="mode">
              <option value="filter" <?= $mode==='filter'?'selected':'' ?>>Filter (types / taxonomies)</option>
              <option value="manual" <?= $mode==='manual'?'selected':'' ?>>Manual pick posts</option>
            </select>
          </div>
          <div class="form-group" style="flex:1;min-width:120px">
            <label for="b-per">Per page / batch</label>
            <input type="number" id="b-per" name="per_page" min="1" max="50" value="<?= $perPage ?>">
          </div>
          <div class="form-group" style="flex:1;min-width:140px">
            <label for="b-sort">Sort</label>
            <select id="b-sort" name="sort">
              <option value="date_desc" <?= $sort==='date_desc'?'selected':'' ?>>Date (newest)</option>
              <option value="date_asc" <?= $sort==='date_asc'?'selected':'' ?>>Date (oldest)</option>
              <option value="title" <?= $sort==='title'?'selected':'' ?>>Title</option>
              <option value="category" <?= $sort==='category'?'selected':'' ?>>Category</option>
            </select>
          </div>
          <div class="form-group" style="flex:1;min-width:140px">
            <label for="b-excerpt">Excerpt characters</label>
            <input type="number" id="b-excerpt" name="excerpt_chars" min="20" max="500" value="<?= $excerptChars ?>">
          </div>
        </div>

        <div class="blog-mode-panel" id="panel-filter" style="<?= $mode==='manual'?'display:none':'' ?>">
          <h3 style="font-size:0.95rem;margin:1rem 0 0.5rem">Allowed content types</h3>
          <p class="field-hint">Default is article. Tick more to include them.</p>
          <div style="display:flex;flex-wrap:wrap;gap:0.5rem 1rem">
            <?php foreach ($types as $t):
              $ts = $t['slug'] ?? '';
              $checked = in_array($ts, $allowed, true) || ($allowed === [] && $ts === 'article');
            ?>
            <label style="font-size:0.85rem;display:flex;align-items:center;gap:0.35rem">
              <input type="checkbox" name="allowed_types[]" value="<?= htmlspecialchars($ts) ?>" <?= $checked?'checked':'' ?>>
              <?= htmlspecialchars($t['name'] ?? $ts) ?>
            </label>
            <?php endforeach; ?>
          </div>
          <h3 style="font-size:0.95rem;margin:1rem 0 0.5rem">Categories (optional)</h3>
          <div style="display:flex;flex-wrap:wrap;gap:0.5rem 1rem;max-height:8rem;overflow:auto">
            <?php foreach ($categories as $c): ?>
            <label style="font-size:0.85rem;display:flex;align-items:center;gap:0.35rem">
              <input type="checkbox" name="category_ids[]" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'], array_map('intval',$catIds), true)?'checked':'' ?>>
              <?= htmlspecialchars($c['name']) ?>
            </label>
            <?php endforeach; ?>
            <?php if (!$categories): ?><span class="muted">No categories</span><?php endif; ?>
          </div>
          <h3 style="font-size:0.95rem;margin:1rem 0 0.5rem">Tags (optional)</h3>
          <div style="display:flex;flex-wrap:wrap;gap:0.5rem 1rem;max-height:8rem;overflow:auto">
            <?php foreach ($tags as $tg): ?>
            <label style="font-size:0.85rem;display:flex;align-items:center;gap:0.35rem">
              <input type="checkbox" name="tag_ids[]" value="<?= (int)$tg['id'] ?>" <?= in_array((int)$tg['id'], array_map('intval',$tagIds), true)?'checked':'' ?>>
              <?= htmlspecialchars($tg['name']) ?>
            </label>
            <?php endforeach; ?>
            <?php if (!$tags): ?><span class="muted">No tags</span><?php endif; ?>
          </div>
        </div>

        <div class="blog-mode-panel" id="panel-manual" style="<?= $mode==='manual'?'':'display:none' ?>">
          <h3 style="font-size:0.95rem;margin:1rem 0 0.5rem">Manual posts</h3>
          <p class="field-hint">Order is the order shown. Only published content is listed.</p>
          <div style="max-height:16rem;overflow:auto;border:1px solid var(--hq-border,#e5e7eb);border-radius:8px;padding:0.5rem">
            <?php foreach ($posts as $p):
              $pid = (int)$p['id'];
              $on = in_array($pid, $itemIds, true);
            ?>
            <label style="display:flex;align-items:center;gap:0.5rem;padding:0.3rem 0.25rem;font-size:0.85rem;border-bottom:1px solid #f1f5f9">
              <input type="checkbox" name="content_ids[]" value="<?= $pid ?>" <?= $on?'checked':'' ?>>
              <span style="flex:1"><?= htmlspecialchars($p['title'] ?? '') ?></span>
              <span class="muted" style="font-size:0.75rem"><?= htmlspecialchars($p['type'] ?? '') ?></span>
            </label>
            <?php endforeach; ?>
            <?php if (!$posts): ?><span class="muted">No published content</span><?php endif; ?>
          </div>
        </div>

        <div class="form-group" style="margin-top:1.25rem">
          <label>Template</label>
          <div style="display:flex;gap:1rem;margin-bottom:0.5rem">
            <label style="font-size:0.85rem"><input type="radio" name="template_mode" value="default" <?= $tplMode!=='custom'?'checked':'' ?>> Default grid cards</label>
            <label style="font-size:0.85rem"><input type="radio" name="template_mode" value="custom" <?= $tplMode==='custom'?'checked':'' ?>> Custom HTML</label>
          </div>
          <div id="tpl-custom" style="<?= $tplMode==='custom'?'':'display:none' ?>">
            <p class="field-hint">Vars: {{title}} {{url}} {{excerpt}} {{excerpt_130}} {{featured_image}} {{has_image}} {{category}} {{categories}} {{tags}} {{date}} {{updated}} {{type}}. Loop: {{#items}} … {{/items}}. Condition: {{#has_image}}…{{/has_image}}</p>
            <div class="blog-var-chips" style="display:flex;flex-wrap:wrap;gap:0.3rem;margin-bottom:0.4rem">
              <?php foreach (['title','url','excerpt_130','featured_image','category','date','has_image'] as $v): ?>
              <button type="button" class="btn-ghost btn-sm" data-var="<?= $v ?>">{{<?= $v ?>}}</button>
              <?php endforeach; ?>
            </div>
            <textarea name="template_html" id="b-tpl" rows="12" style="font-family:ui-monospace,monospace;font-size:0.8rem;width:100%"><?= htmlspecialchars((string)$tplHtml) ?></textarea>
          </div>
        </div>
      </div>

      <aside class="blog-edit-side">
        <div class="panel" style="border:1px solid var(--hq-border,#e5e7eb);border-radius:10px;padding:1rem">
          <h3 style="margin:0 0 0.5rem;font-size:0.95rem">Embed</h3>
          <?php if ($id): ?>
          <div class="form-group">
            <label for="b-sc">Shortcode</label>
            <div style="display:flex;gap:0.35rem;align-items:center">
              <input type="text" readonly id="b-sc" value='[blog id="<?= $id ?>"]'>
              <button type="button" class="btn-ghost btn-sm" data-copy="b-sc">Copy</button>
            </div>
          </div>
          <div class="form-group">
            <label for="b-html">HTML</label>
            <div style="display:flex;gap:0.35rem;align-items:center">
              <input type="text" readonly id="b-html" value='<div class="mova-blog" data-blog="<?= $id ?>"></div>'>
              <button type="button" class="btn-ghost btn-sm" data-copy="b-html">Copy</button>
            </div>
          </div>
          <div class="form-group" style="margin-top:0.75rem">
            <label for="embed_limit">Embed batch size</label>
            <input type="number" id="embed_limit" name="embed_limit" min="1" max="50" value="<?= $embedLimit ?>">
          </div>
          <?php else: ?>
          <p class="field-hint">Save once to copy embeds.</p>
          <?php endif; ?>
          <?php if ($id && !$isDefault): ?>
          <hr style="border:none;border-top:1px solid var(--hq-border,#e5e7eb);margin:1rem 0">
          <button type="submit" form="blog-delete-form" class="btn-ghost" style="color:#dc2626" onclick="return confirm('Delete this blog?')">Delete blog</button>
          <?php endif; ?>
        </div>
      </aside>
    </div>

    <div class="form-actions" style="margin-top:1.25rem">
      <button type="submit" class="btn-primary">Save blog</button>
      <a class="btn-ghost" href="/hq/blogs">Cancel</a>
    </div>
  </form>
  <?php if ($id && !$isDefault): ?>
  <form id="blog-delete-form" method="post" action="/hq/blogs/delete/<?= $id ?>" style="display:none">
    <?= \Mova\Security\Csrf::field() ?>
  </form>
  <?php endif; ?>
</section>
<style>
.blog-edit-layout{display:flex;gap:1.25rem;align-items:flex-start;flex-wrap:wrap}
.blog-edit-main{flex:1 1 60%;min-width:280px}
.blog-edit-side{flex:0 0 28%;min-width:240px;max-width:340px}
</style>
<script>
(function(){
  var mode = document.getElementById('b-mode');
  function syncMode(){
    var m = mode.value;
    document.getElementById('panel-filter').style.display = m==='manual'?'none':'';
    document.getElementById('panel-manual').style.display = m==='manual'?'':'none';
  }
  mode.addEventListener('change', syncMode);
  document.querySelectorAll('input[name="template_mode"]').forEach(function(r){
    r.addEventListener('change', function(){
      document.getElementById('tpl-custom').style.display = document.querySelector('input[name="template_mode"]:checked').value==='custom'?'':'none';
    });
  });
  var title = document.getElementById('b-title');
  var slug = document.getElementById('b-slug');
  if (title && slug && slug.getAttribute('data-auto-slug')==='1') {
    title.addEventListener('input', function(){
      if (slug.readOnly) return;
      var s = (title.value||'').toLowerCase().trim().replace(/[^a-z0-9\s-]/g,'').replace(/[\s-]+/g,'-').replace(/^-+|-+$/g,'');
      slug.value = s || 'blog';
    });
  }
  document.querySelectorAll('[data-var]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var ta = document.getElementById('b-tpl');
      if (!ta) return;
      var v = '{{'+btn.getAttribute('data-var')+'}}';
      var start = ta.selectionStart, end = ta.selectionEnd;
      ta.value = ta.value.slice(0,start)+v+ta.value.slice(end);
      ta.focus();
      ta.selectionStart = ta.selectionEnd = start+v.length;
    });
  });
  document.querySelectorAll('[data-copy]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var input = document.getElementById(btn.getAttribute('data-copy'));
      if (!input) return;
      navigator.clipboard.writeText(input.value).then(function(){ btn.textContent='Copied'; setTimeout(function(){ btn.textContent='Copy'; },1000); });
    });
  });
})();
</script>
