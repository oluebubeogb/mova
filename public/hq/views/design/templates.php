<?php
/** @var list<array> $templates */
/** @var array<string,int> $categories */
/** @var array<string,string> $labels */
/** @var string $activeCategory */
$templates = $templates ?? [];
$categories = $categories ?? [];
$labels = $labels ?? [];
$activeCategory = $activeCategory ?? '';
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$orderedCats = [];
foreach ($labels as $k => $l) {
    if (isset($categories[$k])) {
        $orderedCats[$k] = $categories[$k];
    }
}
$orderedCats += $categories;
$total = array_sum($categories);
?>
<link rel="stylesheet" href="/assets/css/hq-templates.css?v=p3">
<link rel="stylesheet" href="/assets/css/hq-section-picker.css?v=20261023p5f">

<div class="hq-templates hq-lib" id="hq-lib">
  <header class="hq-lib__hero">
    <div>
      <p class="hq-lib__eyebrow">Section library</p>
      <h1 class="hq-page-title">Beautiful sections, ready to drop in</h1>
      <p class="hq-templates__intro">
        <?= (int) $total ?> responsive, animated sections that pick up your brand colours, fonts and dark mode automatically.
        Hover a card for a live preview, then insert it into any page or Assembly.
      </p>
    </div>
    <div class="hq-lib__hero-actions">
      <a class="msp-btn msp-btn--ghost" href="/hq/assembly/new">New Assembly</a>
      <a class="msp-btn msp-btn--primary" href="/hq/content/new?type=page">New page</a>
    </div>
  </header>

  <?php if (!empty($_GET['use'])): ?>
    <div class="alert alert-success">
      Section <strong><?= $e($_GET['use']) ?></strong> selected for AI. Open Mova AI (Ctrl+J) and describe what you need.
    </div>
  <?php endif; ?>

  <div class="hq-lib__bar">
    <div class="hq-lib__chips" role="tablist" aria-label="Filter by category">
      <a role="tab" class="hq-lib__chip<?= $activeCategory === '' ? ' is-on' : '' ?>" href="/hq/templates" data-cat="">All <span><?= (int) $total ?></span></a>
      <?php foreach ($orderedCats as $cat => $count): ?>
        <a role="tab" class="hq-lib__chip<?= $activeCategory === $cat ? ' is-on' : '' ?>" href="/hq/templates?category=<?= rawurlencode((string) $cat) ?>" data-cat="<?= $e($cat) ?>">
          <?= $e($labels[$cat] ?? ucfirst((string) $cat)) ?> <span><?= (int) $count ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <input type="search" class="hq-lib__q" id="hq-lib-q" placeholder="Search sections…" aria-label="Search sections">
  </div>

  <?php if (!$templates): ?>
    <div class="hq-templates__empty"><p>No sections found. Seeds live in <code>resources/templates/</code>.</p></div>
  <?php else: ?>
    <div class="hq-lib__grid" id="hq-lib-grid">
      <?php foreach ($templates as $t):
          $id = (string) ($t['id'] ?? '');
          $name = (string) ($t['name'] ?? $id);
          $cat = (string) ($t['category'] ?? '');
          $tags = is_array($t['tags'] ?? null) ? $t['tags'] : [];
          $search = strtolower($name . ' ' . ($t['description'] ?? '') . ' ' . implode(' ', $tags) . ' ' . $cat);
      ?>
        <article class="hq-lib-card" data-id="<?= $e($id) ?>" data-cat="<?= $e($cat) ?>" data-search="<?= $e($search) ?>"
                 data-name="<?= $e($name) ?>"<?= ($activeCategory !== '' && $activeCategory !== $cat) ? ' hidden' : '' ?>>
          <button type="button" class="hq-lib-card__shot" data-act="preview" aria-label="Preview <?= $e($name) ?>">
            <?php if (!empty($t['preview'])): ?>
              <?php $__thumb = (string) ($t['thumb'] ?? ''); ?>
              <img src="<?= $e($__thumb !== '' ? $__thumb : $t['preview']) ?>" alt="" width="800" height="500" decoding="async"<?= $__thumb !== '' ? '' : ' loading="lazy"' ?> onload="this.classList.add('is-loaded')" onerror="this.classList.add('is-loaded')">
            <?php else: ?>
              <span class="hq-lib-card__ph"><?= $e($name) ?></span>
            <?php endif; ?>
            <span class="hq-lib-card__live" aria-hidden="true"></span>
            <span class="hq-lib-card__badge">Live preview</span>
          </button>
          <div class="hq-lib-card__body">
            <div class="hq-lib-card__top">
              <h2 class="hq-lib-card__title"><?= $e($name) ?></h2>
              <span class="hq-lib-card__cat"><?= $e($labels[$cat] ?? $cat) ?></span>
            </div>
            <?php if (!empty($t['description'])): ?><p class="hq-lib-card__desc"><?= $e($t['description']) ?></p><?php endif; ?>
            <div class="hq-lib-card__tags"><?php foreach (array_slice($tags, 0, 4) as $tag): ?><span><?= $e($tag) ?></span><?php endforeach; ?></div>
            <div class="hq-lib-card__actions">
              <button type="button" class="msp-btn msp-btn--primary" data-act="page">Insert into page</button>
              <a class="msp-btn msp-btn--ghost" href="/hq/assembly/new?section=<?= rawurlencode($id) ?>">Assembly</a>
              <details class="hq-lib-card__more">
                <summary class="msp-btn msp-btn--ghost" aria-label="More actions">⋯</summary>
                <div class="hq-lib-card__menu">
                  <button type="button" data-act="preview">Full-screen preview</button>
                  <button type="button" data-act="copy">Copy HTML</button>
                  <button type="button" data-mova-ai-use-template="<?= $e($id) ?>">Adapt with Mova AI</button>
                  <a href="/hq/templates/<?= rawurlencode($id) ?>">Details</a>
                </div>
              </details>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="hq-lib__none" id="hq-lib-none" hidden>No sections match your search.</p>
  <?php endif; ?>
</div>

<div class="msp" id="hq-lib-pages" hidden role="dialog" aria-modal="true" aria-labelledby="hq-lib-pages-h">
  <div class="msp__panel hq-lib-pages">
    <header class="msp__head">
      <div><h2 id="hq-lib-pages-h">Insert into which page?</h2><p data-section-name></p></div>
      <button type="button" class="msp-btn msp-btn--ghost msp-x" data-close aria-label="Close">✕</button>
    </header>
    <div class="msp__tools"><input type="search" class="msp__q" placeholder="Search pages…" aria-label="Search pages" data-q></div>
    <div class="hq-lib-pages__list" data-list><p class="msp__loading">Loading pages…</p></div>
    <footer class="hq-lib-pages__foot">
      <a class="msp-btn msp-btn--primary" data-new href="/hq/content/new?type=page">+ Start a new page with this section</a>
    </footer>
  </div>
</div>

<script src="/assets/js/hq-section-picker.js?v=20261023p5f"></script>
<script>
(function () {
  var grid = document.getElementById('hq-lib-grid');
  if (!grid) return;
  var q = document.getElementById('hq-lib-q');
  var none = document.getElementById('hq-lib-none');
  var cat = <?= json_encode($activeCategory) ?>;
  var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };

  function filter() {
    var term = (q.value || '').trim().toLowerCase(), shown = 0;
    grid.querySelectorAll('.hq-lib-card').forEach(function (c) {
      var ok = (!cat || c.dataset.cat === cat) && (!term || c.dataset.search.indexOf(term) !== -1);
      c.hidden = !ok; if (ok) shown++;
    });
    none.hidden = shown > 0;
  }
  document.querySelectorAll('.hq-lib__chip').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      cat = a.dataset.cat || '';
      document.querySelectorAll('.hq-lib__chip').forEach(function (x) { x.classList.toggle('is-on', x === a); x.setAttribute('aria-selected', x === a ? 'true' : 'false'); });
      history.replaceState(null, '', cat ? '/hq/templates?category=' + encodeURIComponent(cat) : '/hq/templates');
      filter();
    });
  });
  q.addEventListener('input', filter);

  /* hover live preview: real section rendered in the site theme, scaled into the card */
  var canHover = window.matchMedia('(hover: hover)').matches;
  grid.querySelectorAll('.hq-lib-card').forEach(function (card) {
    var shot = card.querySelector('.hq-lib-card__shot');
    var holder = card.querySelector('.hq-lib-card__live');
    var t;
    if (!canHover) return;
    shot.addEventListener('mouseenter', function () {
      t = setTimeout(function () {
        if (!holder.firstChild) {
          var f = document.createElement('iframe');
          f.setAttribute('tabindex', '-1'); f.setAttribute('title', '');
          f.src = '/_preview/section/' + encodeURIComponent(card.dataset.id);
          f.onload = function () { card.classList.add('is-live'); };
          holder.appendChild(f);
        } else card.classList.add('is-live');
        holder.style.setProperty('--s', String(shot.clientWidth / 1280));
      }, 320);
    });
    shot.addEventListener('mouseleave', function () { clearTimeout(t); card.classList.remove('is-live'); });
  });

  var lib = null;
  function item(id) {
    return (lib || (lib = MovaSectionPicker.load(true))).then(function (d) {
      return { data: d, it: (d.items || []).filter(function (x) { return x.id === id; })[0] };
    });
  }

  /* insert into page dialog */
  var dlg = document.getElementById('hq-lib-pages');
  var list = dlg.querySelector('[data-list]');
  var pq = dlg.querySelector('[data-q]');
  var current = '', pages = [], lastFocus = null;
  function renderPages() {
    var term = (pq.value || '').toLowerCase();
    var rows = pages.filter(function (p) { return !term || (p.title + ' ' + p.slug).toLowerCase().indexOf(term) !== -1; });
    if (!rows.length) { list.innerHTML = '<p class="msp__loading">No pages yet — start a new one below.</p>'; return; }
    list.innerHTML = rows.map(function (p) {
      return '<a class="hq-lib-pages__row" href="/hq/content/edit/' + p.id + '?insert_section=' + encodeURIComponent(current) + '">' +
        '<strong>' + esc(p.title || '(untitled)') + '</strong><span>/' + esc(p.slug) + '</span>' +
        (p.status !== 'published' ? '<em>' + esc(p.status) + '</em>' : '') + '</a>';
    }).join('');
  }
  function openPages(card) {
    current = card.dataset.id;
    lastFocus = document.activeElement;
    dlg.querySelector('[data-section-name]').textContent = 'Adding “' + card.dataset.name + '”. It is appended to the page — you can move it before saving.';
    dlg.querySelector('[data-new]').href = '/hq/content/new?type=page&insert_section=' + encodeURIComponent(current);
    dlg.hidden = false; document.documentElement.classList.add('msp-lock');
    pq.value = ''; pq.focus();
    item(current).then(function (r) { pages = r.data.pages || []; renderPages(); })
      .catch(function () { list.innerHTML = '<p class="msp__loading">Could not load pages.</p>'; });
  }
  function closePages() { dlg.hidden = true; document.documentElement.classList.remove('msp-lock'); if (lastFocus) lastFocus.focus(); }
  pq.addEventListener('input', renderPages);
  dlg.querySelector('[data-close]').addEventListener('click', closePages);
  dlg.addEventListener('click', function (e) { if (e.target === dlg) closePages(); });
  dlg.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePages(); });

  grid.addEventListener('click', function (e) {
    var b = e.target.closest('[data-act]'); if (!b) return;
    var card = b.closest('.hq-lib-card'); var id = card.dataset.id; var act = b.dataset.act;
    var det = card.querySelector('details'); if (det) det.open = false;
    if (act === 'page') openPages(card);
    if (act === 'preview') {
      MovaSectionPicker.preview({ id: id, name: card.dataset.name, live: '/_preview/section/' + encodeURIComponent(id) }, {
        actionsHtml: '<button type="button" class="msp-btn msp-btn--primary" data-pv-page>Insert into page</button>',
        bind: function (root, close) { root.querySelector('[data-pv-page]').addEventListener('click', function () { close(); openPages(card); }); }
      });
    }
    if (act === 'copy') {
      item(id).then(function (r) {
        if (!r.it) return;
        return navigator.clipboard.writeText(r.it.html).then(function () {
          b.textContent = 'Copied ✓'; setTimeout(function () { b.textContent = 'Copy HTML'; }, 1400);
        });
      }).catch(function () { b.textContent = 'Copy failed'; });
    }
  });

  document.querySelectorAll('[data-mova-ai-use-template]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      var id = btn.getAttribute('data-mova-ai-use-template');
      e.preventDefault();
      try { sessionStorage.setItem('mova_ai_template_id', id); sessionStorage.setItem('mova_ai_open_guide', '1'); } catch (err) {}
      var fab = document.getElementById('mova-ai-fab'); if (fab) fab.click();
      if (window.MovaAiPanel && typeof window.MovaAiPanel.useTemplate === 'function') window.MovaAiPanel.useTemplate(id);
    });
  });
  var use = new URLSearchParams(location.search).get('use');
  if (use) { try { sessionStorage.setItem('mova_ai_template_id', use); sessionStorage.setItem('mova_ai_open_guide', '1'); } catch (err) {} }
})();
</script>
