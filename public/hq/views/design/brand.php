<?php use Mova\Security\Csrf; $settings = $settings ?? []; ?>
<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Brand saved. Public cache cleared.</div>
<?php endif; ?>

<p class="design-intro">Site identity — name, homepage, and optional Assembly header/footer.</p>

<form method="post" action="/hq/brand" class="design-form">
    <?= Csrf::field() ?>

    <div class="hq-layers" data-layer-key="mova_hq_layer_brand">
        <div class="hq-layer-tabs" role="tablist">
            <button type="button" class="hq-layer-tab is-active" data-layer="identity" role="tab" aria-selected="true">
                <i class="fa-solid fa-id-card" aria-hidden="true"></i><span>Identity</span>
            </button>
            <button type="button" class="hq-layer-tab" data-layer="marks" role="tab" aria-selected="false">
                <i class="fa-solid fa-image" aria-hidden="true"></i><span>Logo &amp; favicon</span>
            </button>
            <button type="button" class="hq-layer-tab" data-layer="theme" role="tab" aria-selected="false">
                <i class="fa-solid fa-box" aria-hidden="true"></i><span>Theme package</span>
            </button>
        </div>
        <hr class="hq-layer-rule">
        <div class="hq-layer-panels">
            <div class="hq-layer-panel is-active" data-layer-panel="identity" role="tabpanel">
                <h3>Identity</h3>
                <div class="form-group">
                    <label>Site name</label>
                    <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? 'Mova') ?>">
                </div>
                <div class="form-group">
                    <label>Tagline</label>
                    <textarea name="site_description" rows="2"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                </div>
                <div class="form-group mova-suggest-wrap">
                    <label>Homepage</label>
                    <input type="hidden" name="homepage_content_id" id="homepage_content_id" value="<?= htmlspecialchars((string) ($settings['homepage_content_id'] ?? '')) ?>">
                    <input type="text" id="homepage_suggest" autocomplete="off"
                           placeholder="Search published content…"
                           value="<?= htmlspecialchars($settings['homepage_label'] ?? '') ?>">
                    <div class="mova-suggest-list" id="homepage_suggest_list" hidden></div>
                    <p class="field-hint">When set, this content becomes the site landing page at <code>/</code>. Clear the field to use the default post list.</p>
                </div>
                <div class="form-group mova-suggest-wrap">
                    <label>Header (Assembly)</label>
                    <input type="hidden" name="header_assembly_slug" id="header_assembly_slug" value="<?= htmlspecialchars($settings['header_assembly_slug'] ?? '') ?>">
                    <input type="text" id="header_assembly_suggest" autocomplete="off"
                           placeholder="Search assemblies…"
                           value="<?= htmlspecialchars($settings['header_assembly_label'] ?? ($settings['header_assembly_slug'] ?? '')) ?>">
                    <div class="mova-suggest-list" id="header_assembly_list" hidden></div>
                    <p class="field-hint">Overrides the theme header when set. Uses a published Assembly shortcode render.</p>
                </div>
                <div class="form-group mova-suggest-wrap">
                    <label>Footer (Assembly)</label>
                    <input type="hidden" name="footer_assembly_slug" id="footer_assembly_slug" value="<?= htmlspecialchars($settings['footer_assembly_slug'] ?? '') ?>">
                    <input type="text" id="footer_assembly_suggest" autocomplete="off"
                           placeholder="Search assemblies…"
                           value="<?= htmlspecialchars($settings['footer_assembly_label'] ?? ($settings['footer_assembly_slug'] ?? '')) ?>">
                    <div class="mova-suggest-list" id="footer_assembly_list" hidden></div>
                    <p class="field-hint">Overrides the theme footer when set.</p>
                </div>
                <div class="form-group">
                    <label>Footer text</label>
                    <input type="text" name="footer_text" value="<?= htmlspecialchars($settings['footer_text'] ?? '') ?>" placeholder="Optional text inside default footer (ignored if Assembly footer is set)">
                </div>
            </div>

            <div class="hq-layer-panel" data-layer-panel="marks" role="tabpanel" hidden>
                <h3>Logo &amp; site icon</h3>
                <div class="form-group">
                    <label>Header brand display</label>
                    <?php $brandMode = $settings['header_brand_mode'] ?? 'logo_and_name'; ?>
                    <select name="header_brand_mode">
                        <option value="logo_and_name" <?= $brandMode === 'logo_and_name' ? 'selected' : '' ?>>Logo + site name</option>
                        <option value="logo_only" <?= $brandMode === 'logo_only' ? 'selected' : '' ?>>Logo only</option>
                        <option value="name_only" <?= $brandMode === 'name_only' ? 'selected' : '' ?>>Site name only</option>
                    </select>
                    <p class="field-hint">Controls what appears in the frontend header logo area.</p>
                </div>
                <div class="form-group">
                    <label>Logo URL (light)</label>
                    <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                        <input type="text" name="logo_url" id="logo_url" placeholder="/mova-uploads/..." value="<?= htmlspecialchars($settings['logo_url'] ?? '') ?>" style="flex:1;min-width:12rem;">
                        <button type="button" class="btn-ghost btn-sm" id="pick-logo" title="Pick from media library"><i class="fa-solid fa-images"></i> Media</button>
                    </div>
                    <?php if (!empty($settings['logo_url'])): ?>
                        <div style="margin-top:0.5rem;"><img src="<?= htmlspecialchars($settings['logo_url']) ?>" alt="" style="max-height:40px;width:auto;border:none;outline:none;"></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Logo URL (dark mode, optional)</label>
                    <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                        <input type="text" name="logo_url_dark" id="logo_url_dark" placeholder="/mova-uploads/... (optional)" value="<?= htmlspecialchars($settings['logo_url_dark'] ?? '') ?>" style="flex:1;min-width:12rem;">
                        <button type="button" class="btn-ghost btn-sm" id="pick-logo-dark" title="Pick from media library"><i class="fa-solid fa-images"></i> Media</button>
                    </div>
                    <p class="field-hint">Used when the site is in dark mode. Falls back to the light logo if empty.</p>
                    <?php if (!empty($settings['logo_url_dark'])): ?>
                        <div style="margin-top:0.5rem;background:#12151c;padding:0.5rem;border-radius:8px;display:inline-block;"><img src="<?= htmlspecialchars($settings['logo_url_dark']) ?>" alt="" style="max-height:40px;width:auto;border:none;outline:none;"></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Site icon (favicon)</label>
                    <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                        <input type="text" name="favicon_url" id="favicon_url" placeholder="/mova-uploads/favicon.ico" value="<?= htmlspecialchars($settings['favicon_url'] ?? '') ?>" style="flex:1;min-width:12rem;">
                        <button type="button" class="btn-ghost btn-sm" id="pick-favicon" title="Pick from media library"><i class="fa-solid fa-images"></i> Media</button>
                    </div>
                    <p class="field-hint">Shown in the browser tab and bookmarks. Prefer a square PNG or ICO.</p>
                    <?php if (!empty($settings['favicon_url'])): ?>
                        <div style="margin-top:0.5rem;"><img src="<?= htmlspecialchars($settings['favicon_url']) ?>" alt="" style="height:32px;width:32px;object-fit:contain;border:none;outline:none;"></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Brand color (quick)</label>
                    <div class="color-row">
                        <input type="color" name="brand_color" value="<?= htmlspecialchars($settings['brand_color'] ?? '#2563eb') ?>">
                        <span class="muted"><?= htmlspecialchars($settings['brand_color'] ?? '#2563eb') ?></span>
                    </div>
                    <p class="field-hint">Full palette under <a href="/hq/style">Style</a>.</p>
                </div>
            </div>

            <div class="hq-layer-panel" data-layer-panel="theme" role="tabpanel" hidden>
                <h3>Theme package</h3>
                <div class="form-group">
                    <label>Active theme</label>
                    <select name="active_theme">
                        <?php foreach (($themes ?? []) as $th): ?>
                            <option value="<?= htmlspecialchars($th['slug']) ?>" <?= ($activeTheme ?? 'default') === $th['slug'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($th['name']) ?> (<?= htmlspecialchars($th['slug']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field-hint">Themes live in <code>/mova-themes</code>.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="hq-layer-actions">
        <button type="submit" class="btn-primary">Save brand</button>
    </div>
</form>
<style>
.mova-suggest-wrap { position: relative; }
.mova-suggest-list {
  position: absolute; left: 0; right: 0; top: 100%; z-index: 40;
  background: var(--hq-surface); border: 1px solid var(--hq-border);
  border-radius: 8px; max-height: 14rem; overflow: auto;
  box-shadow: 0 8px 24px rgba(0,0,0,.12); margin-top: 2px;
}
.mova-suggest-list button {
  display: block; width: 100%; text-align: left; border: none; background: transparent;
  padding: 0.55rem 0.75rem; font: inherit; color: var(--hq-text); cursor: pointer;
}
.mova-suggest-list button:hover { background: var(--hq-hover, rgba(0,0,0,.04)); }
.mova-suggest-list .muted { color: var(--hq-muted); font-size: 0.8rem; }
</style>
<script>
(function () {
  function bindSuggest(inputId, listId, hiddenId, endpoint, mapItem) {
    var input = document.getElementById(inputId);
    var list = document.getElementById(listId);
    var hidden = document.getElementById(hiddenId);
    if (!input || !list || !hidden) return;
    var t;
    function hide() { list.hidden = true; list.innerHTML = ''; }
    function show(items) {
      if (!items.length) { hide(); return; }
      list.innerHTML = items.map(function (it) {
        return '<button type="button" data-val="' + String(it.value).replace(/"/g, '&quot;') + '" data-label="' + String(it.label).replace(/"/g, '&quot;') + '"><strong>' + it.label.replace(/</g,'&lt;') + '</strong><br><span class="muted">' + (it.sub || '') + '</span></button>';
      }).join('');
      list.hidden = false;
      list.querySelectorAll('button').forEach(function (b) {
        b.addEventListener('click', function () {
          hidden.value = b.getAttribute('data-val') || '';
          input.value = b.getAttribute('data-label') || '';
          hide();
        });
      });
    }
    input.addEventListener('input', function () {
      clearTimeout(t);
      if (!input.value.trim()) { hidden.value = ''; hide(); return; }
      t = setTimeout(function () {
        fetch(endpoint + '?q=' + encodeURIComponent(input.value.trim()))
          .then(function (r) { return r.json(); })
          .then(function (data) {
            var items = (data.items || []).map(mapItem);
            show(items);
          }).catch(hide);
      }, 180);
    });
    input.addEventListener('focus', function () {
      if (input.value.trim()) input.dispatchEvent(new Event('input'));
      else {
        fetch(endpoint + '?q=')
          .then(function (r) { return r.json(); })
          .then(function (data) { show((data.items || []).map(mapItem)); })
          .catch(hide);
      }
    });
    document.addEventListener('click', function (e) {
      if (!list.contains(e.target) && e.target !== input) hide();
    });
  }
  bindSuggest('homepage_suggest', 'homepage_suggest_list', 'homepage_content_id', '/hq/brand/suggest-content', function (it) {
    return { value: String(it.id), label: it.title, sub: '/' + it.slug };
  });
  bindSuggest('header_assembly_suggest', 'header_assembly_list', 'header_assembly_slug', '/hq/brand/suggest-assembly', function (it) {
    return { value: it.slug, label: it.name, sub: it.slug };
  });
  bindSuggest('footer_assembly_suggest', 'footer_assembly_list', 'footer_assembly_slug', '/hq/brand/suggest-assembly', function (it) {
    return { value: it.slug, label: it.name, sub: it.slug };
  });

  // Simple media picker for logo / site icon
  function openMediaPicker(targetInputId) {
    var existing = document.getElementById('mova-media-picker');
    if (existing) existing.remove();

    var overlay = document.createElement('div');
    overlay.id = 'mova-media-picker';
    overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.45);display:flex;align-items:center;justify-content:center;padding:1rem;';
    overlay.innerHTML =
      '<div style="background:var(--hq-surface,#fff);border-radius:12px;max-width:640px;width:100%;max-height:80vh;overflow:auto;box-shadow:0 20px 50px rgba(0,0,0,.2);">' +
        '<div style="display:flex;justify-content:space-between;align-items:center;padding:0.85rem 1rem;border-bottom:1px solid var(--hq-border,#e2e8f0);">' +
          '<strong>Choose media</strong>' +
          '<button type="button" class="btn-ghost btn-sm" id="mpp-close">Close</button>' +
        '</div>' +
        '<div style="padding:0.75rem 1rem;"><input type="search" id="mpp-q" placeholder="Search…" style="width:100%;"></div>' +
        '<div id="mpp-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:0.5rem;padding:0 1rem 1rem;"></div>' +
        '<p id="mpp-empty" class="muted" style="padding:0 1rem 1rem;display:none;">No images found. <a href="/hq/media">Upload in Media</a>.</p>' +
      '</div>';
    document.body.appendChild(overlay);

    function close() { overlay.remove(); }
    overlay.querySelector('#mpp-close').addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });

    function load(q) {
      var url = '/hq/media/json?page=1' + (q ? '&q=' + encodeURIComponent(q) : '');
      fetch(url).then(function (r) { return r.json(); }).then(function (data) {
        var grid = overlay.querySelector('#mpp-grid');
        var empty = overlay.querySelector('#mpp-empty');
        var items = (data.items || []).filter(function (it) {
          return (it.mime_type || '').indexOf('image/') === 0 || /\.(png|jpe?g|gif|webp|svg|ico)$/i.test(it.url || it.path || '');
        });
        grid.innerHTML = '';
        if (!items.length) { empty.style.display = 'block'; return; }
        empty.style.display = 'none';
        items.forEach(function (it) {
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.title = it.original_name || it.url;
          btn.style.cssText = 'border:1px solid var(--hq-border,#e2e8f0);border-radius:8px;padding:0.25rem;background:#fff;cursor:pointer;aspect-ratio:1;overflow:hidden;';
          btn.innerHTML = '<img src="' + (it.url || '') + '" alt="" style="width:100%;height:100%;object-fit:cover;border:none;">';
          btn.addEventListener('click', function () {
            var input = document.getElementById(targetInputId);
            if (input) {
              input.value = it.url || '';
              input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            close();
          });
          grid.appendChild(btn);
        });
      }).catch(function () {
        overlay.querySelector('#mpp-empty').style.display = 'block';
      });
    }
    var t;
    overlay.querySelector('#mpp-q').addEventListener('input', function (e) {
      clearTimeout(t);
      t = setTimeout(function () { load(e.target.value.trim()); }, 200);
    });
    load('');
  }

  var pickLogo = document.getElementById('pick-logo');
  var pickLogoDark = document.getElementById('pick-logo-dark');
  var pickFav = document.getElementById('pick-favicon');
  if (pickLogo) pickLogo.addEventListener('click', function () { openMediaPicker('logo_url'); });
  if (pickLogoDark) pickLogoDark.addEventListener('click', function () { openMediaPicker('logo_url_dark'); });
  if (pickFav) pickFav.addEventListener('click', function () { openMediaPicker('favicon_url'); });
})();
</script>
<?php include __DIR__ . '/_design_styles.php'; ?>
