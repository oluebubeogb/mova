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
                <h3>Logo &amp; favicon</h3>
                <div class="form-group">
                    <label>Logo URL</label>
                    <input type="text" name="logo_url" placeholder="/mova-uploads/..." value="<?= htmlspecialchars($settings['logo_url'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Favicon URL</label>
                    <input type="text" name="favicon_url" placeholder="/mova-uploads/favicon.ico" value="<?= htmlspecialchars($settings['favicon_url'] ?? '') ?>">
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
})();
</script>
<?php include __DIR__ . '/_design_styles.php'; ?>
