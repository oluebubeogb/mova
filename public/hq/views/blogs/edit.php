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
$dopt = array_merge(\Mova\Blog\BlogService::DISPLAY_DEFAULTS, (array)($blog['embed_options'] ?? []));
$dChk = fn($k) => !empty($dopt[$k]) ? 'checked' : '';
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
          <label id="b-tpl-label">Post layout</label>
          <p class="field-hint" style="margin-top:0">Pick a preset or design your own. Download samples to edit offline.</p>
          <input type="hidden" name="template_mode" id="b-tpl-mode" value="<?= htmlspecialchars(in_array($tplMode, ['default','modern','minimal','custom'], true) ? $tplMode : 'default') ?>">
          <div class="blog-tpl-picks" id="blog-tpl-picks" role="group" aria-labelledby="b-tpl-label">
            <button type="button" class="blog-tpl-card<?= ($tplMode==='default' || $tplMode==='') ? ' is-active' : '' ?>" data-mode="default" title="Default">
              <div class="blog-tpl-mock blog-tpl-mock-default" aria-hidden="true">
                <div class="btm-row"><span class="btm-cat"></span></div>
                <div class="btm-body"><div class="btm-lines"><i></i><i></i><i class="short"></i><b></b></div><div class="btm-img"></div></div>
              </div>
              <span class="blog-tpl-name">Default</span>
            </button>
            <button type="button" class="blog-tpl-card<?= $tplMode==='modern' ? ' is-active' : '' ?>" data-mode="modern" title="Modern">
              <div class="blog-tpl-mock blog-tpl-mock-modern" aria-hidden="true">
                <div class="btm-img tall"></div>
                <div class="btm-lines pad"><i class="tiny"></i><i></i><i class="short"></i><b></b></div>
              </div>
              <span class="blog-tpl-name">Modern</span>
            </button>
            <button type="button" class="blog-tpl-card<?= $tplMode==='minimal' ? ' is-active' : '' ?>" data-mode="minimal" title="Minimal">
              <div class="blog-tpl-mock blog-tpl-mock-minimal" aria-hidden="true">
                <div class="btm-body"><div class="btm-lines"><i class="tiny"></i><i></i><i class="short"></i></div><div class="btm-img sm"></div></div>
              </div>
              <span class="blog-tpl-name">Minimal</span>
            </button>
            <button type="button" class="blog-tpl-card<?= $tplMode==='custom' ? ' is-active' : '' ?>" data-mode="custom" title="Custom HTML">
              <div class="blog-tpl-mock blog-tpl-mock-code" aria-hidden="true">
                <code>&lt;div&gt;…&lt;/div&gt;</code>
              </div>
              <span class="blog-tpl-name">Custom</span>
            </button>
          </div>
          <div class="blog-tpl-downloads" style="display:flex;flex-wrap:wrap;gap:0.5rem;margin:0.65rem 0 0.35rem">
            <button type="button" class="btn-ghost btn-sm" id="dl-tpl-default"><i class="fa-solid fa-download"></i> Sample: default</button>
            <button type="button" class="btn-ghost btn-sm" id="dl-tpl-rich"><i class="fa-solid fa-download"></i> Sample: all vars</button>
          </div>
          <div id="tpl-custom" style="<?= $tplMode==='custom'?'':'display:none' ?>">
            <p class="field-hint">Vars: {{title}} {{url}} {{excerpt}} {{excerpt_130}} {{featured_image}} {{has_image}} {{category}} {{categories}} {{tags}} {{date}} {{updated}} {{type}}. Loop: {{#items}} … {{/items}}. Condition: {{#has_image}}…{{/has_image}}</p>
            <div class="blog-var-chips" style="display:flex;flex-wrap:wrap;gap:0.3rem;margin-bottom:0.4rem">
              <?php foreach (['title','url','excerpt_130','featured_image','category','categories','tags','date','updated','type','has_image'] as $v): ?>
              <button type="button" class="btn-ghost btn-sm" data-var="<?= $v ?>">{{<?= $v ?>}}</button>
              <?php endforeach; ?>
            </div>
            <textarea name="template_html" id="b-tpl" rows="12" style="font-family:ui-monospace,monospace;font-size:0.8rem;width:100%"><?= htmlspecialchars((string)$tplHtml) ?></textarea>
          </div>
        </div>
        <fieldset class="form-group blog-display-opts" style="margin-top:1.25rem;border:1px solid var(--hq-border,#e5e7eb);border-radius:10px;padding:0.85rem 1rem">
          <legend style="font-weight:600;padding:0 0.35rem">Display</legend>
          <p class="field-hint" style="margin-top:0">Applies wherever this blog shows: its page, and every <code>[blog]</code> embed. A shortcode attribute (e.g. <code>columns="3"</code>, <code>show_date="hide"</code>) overrides it for that one embed.</p>
          <div class="form-row" style="display:flex;gap:1rem;flex-wrap:wrap">
            <div class="form-group" style="flex:1;min-width:140px">
              <label for="b-cols">Columns</label>
              <select id="b-cols" name="opt_columns">
                <?php foreach (['2' => 'Classic (1 → 2)', '1' => '1 column', '3' => 'Up to 3', '4' => 'Up to 4', 'auto' => 'Auto-fit'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= (string)$dopt['columns'] === (string)$v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="flex:1;min-width:140px">
              <label for="b-date">Date</label>
              <select id="b-date" name="opt_show_date">
                <?php foreach (['auto' => 'As the layout shows it', 'show' => 'Show', 'hide' => 'Hide'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= $dopt['show_date'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="flex:1;min-width:140px">
              <label for="b-chip">Category style</label>
              <select id="b-chip" name="opt_chip_style">
                <option value="classic" <?= $dopt['chip_style'] === 'classic' ? 'selected' : '' ?>>Classic (one colour)</option>
                <option value="tones" <?= $dopt['chip_style'] === 'tones' ? 'selected' : '' ?>>Colour per category</option>
              </select>
            </div>
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:0.5rem 1.25rem;font-size:0.88rem">
            <?php foreach (['show_image' => 'Show image', 'show_excerpt' => 'Show excerpt', 'show_category' => 'Show category', 'featured_first' => 'Feature the first post (wide screens)'] as $k => $l): ?>
            <label style="display:flex;align-items:center;gap:0.35rem"><input type="hidden" name="opt_<?= $k ?>" value="0"><input type="checkbox" name="opt_<?= $k ?>" value="1" <?= $dChk($k) ?>> <?= $l ?></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
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
  var tplMode = document.getElementById('b-tpl-mode');
  var tplCustom = document.getElementById('tpl-custom');
  document.querySelectorAll('#blog-tpl-picks .blog-tpl-card').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('#blog-tpl-picks .blog-tpl-card').forEach(function(b){ b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      var mode = btn.getAttribute('data-mode') || 'default';
      tplMode.value = mode;
      tplCustom.style.display = mode === 'custom' ? '' : 'none';
    });
  });
  function downloadText(filename, text) {
    var a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([text], { type: 'text/html;charset=utf-8' }));
    a.download = filename;
    a.click();
    setTimeout(function(){ URL.revokeObjectURL(a.href); }, 500);
  }
  var sampleDefault = <?= json_encode((new \Mova\Blog\BlogService())->defaultTemplateSource()) ?>;
  var sampleRich = <?= json_encode((new \Mova\Blog\BlogService())->richSampleTemplateSource()) ?>;
  document.getElementById('dl-tpl-default').addEventListener('click', function(){
    downloadText('mova-blog-template-default.html', sampleDefault);
  });
  document.getElementById('dl-tpl-rich').addEventListener('click', function(){
    downloadText('mova-blog-template-all-vars.html', sampleRich);
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

<style>
.blog-tpl-picks{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:0.65rem;margin-top:0.5rem}
.blog-tpl-card{
  appearance:none;border:1px solid var(--hq-border,#e5e7eb);border-radius:10px;background:#fff;
  padding:0.5rem;cursor:pointer;text-align:left;font:inherit;color:inherit;
  transition:border-color .15s, box-shadow .15s;
}
.blog-tpl-card:hover{border-color:#94a3b8}
.blog-tpl-card.is-active{border-color:var(--hq-accent,#0f766e);box-shadow:0 0 0 2px color-mix(in srgb, var(--hq-accent,#0f766e) 25%, transparent)}
.blog-tpl-name{display:block;font-size:0.78rem;font-weight:650;margin-top:0.4rem;color:#334155}
.blog-tpl-mock{border-radius:6px;background:#f8fafc;border:1px solid #eef2f7;padding:0.4rem;min-height:4.5rem}
.blog-tpl-mock-modern{padding:0;overflow:hidden}
.blog-tpl-mock-code{display:flex;align-items:center;justify-content:center;min-height:4.5rem}
.blog-tpl-mock-code code{font-size:0.65rem;color:#64748b}
.btm-body{display:flex;gap:0.35rem;align-items:stretch}
.btm-lines{flex:1;display:flex;flex-direction:column;gap:0.22rem;justify-content:center}
.btm-lines i{display:block;height:0.28rem;background:#cbd5e1;border-radius:2px}
.btm-lines i.short{width:55%}
.btm-lines i.tiny{width:30%;height:0.22rem;background:#99f6e4}
.btm-lines b{display:block;height:0.28rem;width:40%;background:#5eead4;border-radius:2px;margin-top:0.1rem}
.btm-img{width:28%;min-height:2.2rem;background:linear-gradient(135deg,#e2e8f0,#cbd5e1);border-radius:4px}
.btm-img.tall{width:100%;min-height:2rem;border-radius:0}
.btm-img.sm{width:1.8rem;min-height:1.8rem;flex-shrink:0}
.btm-lines.pad{padding:0.4rem}
.btm-cat{display:inline-block;height:0.35rem;width:1.6rem;background:#99f6e4;border-radius:99px}
.btm-row{margin-bottom:0.25rem}
</style>
