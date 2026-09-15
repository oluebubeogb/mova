<?php
use Mova\Security\Csrf;
$tokens = $tokens ?? [];
$colors = $tokens['colors'] ?? [];
$dark = $tokens['colors_dark'] ?? [];
$typo = $tokens['typography'] ?? [];
$radius = $tokens['radius'] ?? [];
$shadows = $tokens['shadows'] ?? [];
$spacing = $tokens['spacing'] ?? [];
$customColors = $tokens['custom_colors'] ?? [];
if (!is_array($customColors)) {
    $customColors = [];
}
$lightLabels = [
    'primary' => 'Primary', 'secondary' => 'Secondary', 'accent' => 'Accent',
    'background' => 'Background', 'surface' => 'Surface', 'text' => 'Text',
    'muted' => 'Muted', 'border' => 'Border',
];

$normalizePicker = static function (string $val, string $fallback): string {
    $pickerVal = $val;
    if (preg_match('/^#([0-9a-fA-F]{3})$/', $val, $m)) {
        $pickerVal = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $pickerVal)) {
        return $fallback;
    }
    return $pickerVal;
};

$renderSystemSwatch = static function (string $key, string $label, string $val, string $mode) use ($normalizePicker): void {
    $isDark = $mode === 'dark';
    $fallback = $isDark ? '#ffffff' : '#000000';
    $pickerVal = $normalizePicker($val, $fallback);
    $name = $isDark ? 'color_dark_' . $key : 'color_' . $key;
    $id = $isDark ? 'color_dark_hex_' . $key : 'color_hex_' . $key;
    ?>
    <div class="token-swatch">
        <span class="token-swatch-label"><?= htmlspecialchars($label) ?></span>
        <input type="color" class="token-color-picker" data-hex-target="<?= htmlspecialchars($id) ?>" value="<?= htmlspecialchars($pickerVal) ?>">
        <input type="text" class="token-hex-input" name="<?= htmlspecialchars($name) ?>" id="<?= htmlspecialchars($id) ?>"
               value="<?= htmlspecialchars($val) ?>" pattern="#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})"
               placeholder="#000 or #000000" title="HTML hex (#RGB or #RRGGBB)" autocomplete="off">
    </div>
    <?php
};
?>
<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Style tokens saved. Public cache cleared.</div>
<?php endif; ?>

<p class="design-intro">Design tokens — colors, type, radius, shadows, density. Add custom colors below; double‑click a custom name on Light to rename (syncs to Dark).</p>

<form method="post" action="/hq/style" class="design-form" id="style-form">
    <?= Csrf::field() ?>

    <div class="hq-layers" data-layer-key="mova_hq_layer_style">
        <div class="hq-layer-tabs" role="tablist">
            <button type="button" class="hq-layer-tab is-active" data-layer="light" role="tab">
                <i class="fa-solid fa-sun" aria-hidden="true"></i><span>Light colors</span>
            </button>
            <button type="button" class="hq-layer-tab" data-layer="dark" role="tab">
                <i class="fa-solid fa-moon" aria-hidden="true"></i><span>Dark colors</span>
            </button>
            <button type="button" class="hq-layer-tab" data-layer="type" role="tab">
                <i class="fa-solid fa-font" aria-hidden="true"></i><span>Typography</span>
            </button>
            <button type="button" class="hq-layer-tab" data-layer="shape" role="tab">
                <i class="fa-solid fa-vector-square" aria-hidden="true"></i><span>Radius &amp; shadow</span>
            </button>
            <button type="button" class="hq-layer-tab" data-layer="density" role="tab">
                <i class="fa-solid fa-arrows-up-down" aria-hidden="true"></i><span>Density</span>
            </button>
        </div>
        <hr class="hq-layer-rule">
        <div class="hq-layer-panels">
            <div class="hq-layer-panel is-active" data-layer-panel="light">
                <h3>Light colors</h3>
                <div class="token-swatch-grid" id="light-swatch-grid">
                    <?php foreach ($lightLabels as $key => $label):
                        $renderSystemSwatch($key, $label, (string) ($colors[$key] ?? '#000000'), 'light');
                    endforeach; ?>
                    <?php foreach ($customColors as $i => $cc):
                        $label = (string) ($cc['label'] ?? 'Custom');
                        $slug = (string) ($cc['slug'] ?? ('custom_' . $i));
                        $val = (string) ($cc['light'] ?? '#ffffff');
                        $pickerVal = $normalizePicker($val, '#ffffff');
                        $id = 'color_custom_hex_' . $i;
                        ?>
                        <div class="token-swatch is-custom is-committed" data-custom-index="<?= (int) $i ?>">
                            <span class="token-swatch-label is-editable" data-editable-name title="Double-click to rename"><?= htmlspecialchars($label) ?></span>
                            <input type="hidden" name="custom_slug[]" value="<?= htmlspecialchars($slug) ?>" class="custom-slug-input">
                            <input type="hidden" name="custom_label[]" value="<?= htmlspecialchars($label) ?>" class="custom-label-input">
                            <input type="color" class="token-color-picker" data-hex-target="<?= htmlspecialchars($id) ?>" value="<?= htmlspecialchars($pickerVal) ?>">
                            <input type="text" class="token-hex-input custom-light-hex" name="custom_light[]" id="<?= htmlspecialchars($id) ?>"
                                   value="<?= htmlspecialchars($val) ?>" pattern="#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})"
                                   placeholder="#ffffff" autocomplete="off">
                        </div>
                    <?php endforeach; ?>
                    <!-- Always-present blank Custom slot (light) -->
                    <div class="token-swatch is-custom is-blank" id="light-custom-blank" data-custom-blank="1">
                        <span class="token-swatch-label is-editable" data-editable-name title="Double-click to rename">Custom</span>
                        <input type="hidden" name="custom_slug[]" value="" class="custom-slug-input" disabled>
                        <input type="hidden" name="custom_label[]" value="Custom" class="custom-label-input" disabled>
                        <input type="color" class="token-color-picker" data-hex-target="color_custom_blank_light" value="#ffffff">
                        <input type="text" class="token-hex-input custom-light-hex" name="custom_light[]" id="color_custom_blank_light"
                               value="#ffffff" pattern="#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})" placeholder="#ffffff" autocomplete="off" disabled>
                        <span class="token-swatch-hint">Double-click name to claim</span>
                    </div>
                </div>
            </div>
            <div class="hq-layer-panel" data-layer-panel="dark" hidden>
                <h3>Dark colors</h3>
                <p class="field-hint" style="margin-bottom:0.75rem;">Custom color names are set on Light and shared here. Only values differ per mode.</p>
                <div class="token-swatch-grid" id="dark-swatch-grid">
                    <?php foreach ($lightLabels as $key => $label):
                        $renderSystemSwatch($key, $label, (string) ($dark[$key] ?? '#ffffff'), 'dark');
                    endforeach; ?>
                    <?php foreach ($customColors as $i => $cc):
                        $label = (string) ($cc['label'] ?? 'Custom');
                        $slug = (string) ($cc['slug'] ?? ('custom_' . $i));
                        $val = (string) ($cc['dark'] ?? '#ffffff');
                        $pickerVal = $normalizePicker($val, '#ffffff');
                        $id = 'color_dark_custom_hex_' . $i;
                        ?>
                        <div class="token-swatch is-custom is-committed" data-custom-index="<?= (int) $i ?>">
                            <span class="token-swatch-label" data-synced-label="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($label) ?></span>
                            <input type="hidden" name="custom_dark_slug[]" value="<?= htmlspecialchars($slug) ?>">
                            <input type="color" class="token-color-picker" data-hex-target="<?= htmlspecialchars($id) ?>" value="<?= htmlspecialchars($pickerVal) ?>">
                            <input type="text" class="token-hex-input custom-dark-hex" name="custom_dark[]" id="<?= htmlspecialchars($id) ?>"
                                   value="<?= htmlspecialchars($val) ?>" pattern="#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})"
                                   placeholder="#ffffff" autocomplete="off">
                        </div>
                    <?php endforeach; ?>
                    <div class="token-swatch is-custom is-blank" id="dark-custom-blank" data-custom-blank="1">
                        <span class="token-swatch-label" data-synced-label="">Custom</span>
                        <input type="hidden" name="custom_dark_slug[]" value="" disabled>
                        <input type="color" class="token-color-picker" data-hex-target="color_custom_blank_dark" value="#ffffff">
                        <input type="text" class="token-hex-input custom-dark-hex" name="custom_dark[]" id="color_custom_blank_dark"
                               value="#ffffff" pattern="#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})" placeholder="#ffffff" autocomplete="off" disabled>
                        <span class="token-swatch-hint">Rename on Light colors</span>
                    </div>
                </div>
            </div>
            <div class="hq-layer-panel" data-layer-panel="type" hidden>
                <h3>Typography</h3>
                <div class="form-group">
                    <label>Sans stack</label>
                    <input type="text" name="font_sans" value="<?= htmlspecialchars($typo['font_sans'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Font scale</label>
                    <select name="font_scale">
                        <?php $scale = (string) ($typo['scale'] ?? '1');
                        foreach (['0.9' => 'Compact', '1' => 'Normal', '1.1' => 'Large'] as $v => $lab): ?>
                        <option value="<?= $v ?>" <?= $scale === $v ? 'selected' : '' ?>><?= $lab ?> (<?= $v ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="hq-layer-panel" data-layer-panel="shape" hidden>
                <h3>Radius &amp; shadow</h3>
                <div class="form-group">
                    <label>Radius small</label>
                    <input type="text" name="radius_sm" value="<?= htmlspecialchars($radius['sm'] ?? '6px') ?>">
                </div>
                <div class="form-group">
                    <label>Radius medium</label>
                    <input type="text" name="radius_md" value="<?= htmlspecialchars($radius['md'] ?? '10px') ?>">
                </div>
                <div class="form-group">
                    <label>Radius large</label>
                    <input type="text" name="radius_lg" value="<?= htmlspecialchars($radius['lg'] ?? '16px') ?>">
                </div>
                <div class="form-group">
                    <label>Shadow style</label>
                    <select name="shadow_style">
                        <?php $ss = $shadows['style'] ?? 'soft';
                        foreach (['none' => 'None', 'soft' => 'Soft', 'medium' => 'Medium', 'sharp' => 'Sharp'] as $v => $lab): ?>
                        <option value="<?= $v ?>" <?= $ss === $v ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="hq-layer-panel" data-layer-panel="density" hidden>
                <h3>Density</h3>
                <div class="form-group">
                    <label>Layout density</label>
                    <select name="density">
                        <?php $den = $spacing['density'] ?? 'normal';
                        foreach (['compact' => 'Compact', 'normal' => 'Normal', 'spacious' => 'Spacious'] as $v => $lab): ?>
                        <option value="<?= $v ?>" <?= $den === $v ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="hq-layer-actions">
        <button type="submit" class="btn-primary">Save style</button>
    </div>
</form>
<?php include __DIR__ . '/_design_styles.php'; ?>
<script>
(function () {
  function expandHex(v) {
    v = (v || '').trim();
    var m = v.match(/^#([0-9A-Fa-f]{3})$/);
    if (m) {
      var h = m[1];
      return '#' + h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
    }
    if (/^#([0-9A-Fa-f]{6})$/.test(v)) return v;
    if (/^([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(v)) return expandHex('#' + v);
    return null;
  }

  function bindPicker(root) {
    (root || document).querySelectorAll('.token-color-picker').forEach(function (picker) {
      if (picker.dataset.bound) return;
      picker.dataset.bound = '1';
      var id = picker.getAttribute('data-hex-target');
      var hex = id ? document.getElementById(id) : null;
      if (!hex) return;
      picker.addEventListener('input', function () { hex.value = picker.value; });
      function syncFromHex() {
        var exp = expandHex(hex.value);
        if (exp) { hex.value = exp; picker.value = exp; }
      }
      hex.addEventListener('change', syncFromHex);
      hex.addEventListener('blur', syncFromHex);
    });
  }

  function slugify(label) {
    var s = String(label || '').toLowerCase().trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
    if (!s || s === 'custom') s = 'custom-' + Date.now().toString(36);
    return s.slice(0, 40);
  }

  function commitBlankCustom(blankEl, newLabel) {
    if (!blankEl || !blankEl.classList.contains('is-blank')) return;
    var label = (newLabel || '').trim();
    if (!label || /^custom$/i.test(label)) return;

    var lightGrid = document.getElementById('light-swatch-grid');
    var darkGrid = document.getElementById('dark-swatch-grid');
    if (!lightGrid || !darkGrid) return;

    var slug = slugify(label);
    var lightHex = blankEl.querySelector('.custom-light-hex');
    var lightVal = (lightHex && expandHex(lightHex.value)) || '#ffffff';
    var idx = lightGrid.querySelectorAll('.token-swatch.is-custom.is-committed').length;

    // Light committed card
    var lightCard = document.createElement('div');
    lightCard.className = 'token-swatch is-custom is-committed';
    lightCard.setAttribute('data-custom-index', String(idx));
    var lightId = 'color_custom_hex_' + idx + '_' + Date.now();
    lightCard.innerHTML =
      '<span class="token-swatch-label is-editable" data-editable-name title="Double-click to rename"></span>' +
      '<input type="hidden" name="custom_slug[]" value="" class="custom-slug-input">' +
      '<input type="hidden" name="custom_label[]" value="" class="custom-label-input">' +
      '<input type="color" class="token-color-picker" data-hex-target="' + lightId + '" value="' + lightVal + '">' +
      '<input type="text" class="token-hex-input custom-light-hex" name="custom_light[]" id="' + lightId + '" value="' + lightVal + '" pattern="#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})" placeholder="#ffffff" autocomplete="off">';
    lightCard.querySelector('[data-editable-name]').textContent = label;
    lightCard.querySelector('.custom-slug-input').value = slug;
    lightCard.querySelector('.custom-label-input').value = label;
    lightGrid.insertBefore(lightCard, blankEl);

    // Dark committed card (same name/slug, default #ffffff unless blank was changed — start white for dark)
    var darkBlank = document.getElementById('dark-custom-blank');
    var darkCard = document.createElement('div');
    darkCard.className = 'token-swatch is-custom is-committed';
    darkCard.setAttribute('data-custom-index', String(idx));
    var darkId = 'color_dark_custom_hex_' + idx + '_' + Date.now();
    darkCard.innerHTML =
      '<span class="token-swatch-label" data-synced-label=""></span>' +
      '<input type="hidden" name="custom_dark_slug[]" value="">' +
      '<input type="color" class="token-color-picker" data-hex-target="' + darkId + '" value="#ffffff">' +
      '<input type="text" class="token-hex-input custom-dark-hex" name="custom_dark[]" id="' + darkId + '" value="#ffffff" pattern="#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})" placeholder="#ffffff" autocomplete="off">';
    darkCard.querySelector('[data-synced-label]').textContent = label;
    darkCard.querySelector('[data-synced-label]').setAttribute('data-synced-label', slug);
    darkCard.querySelector('input[name="custom_dark_slug[]"]').value = slug;
    if (darkBlank) darkGrid.insertBefore(darkCard, darkBlank);
    else darkGrid.appendChild(darkCard);

    // Reset blank to Custom / #ffffff
    blankEl.querySelector('[data-editable-name]').textContent = 'Custom';
    if (lightHex) lightHex.value = '#ffffff';
    var blankPicker = blankEl.querySelector('.token-color-picker');
    if (blankPicker) blankPicker.value = '#ffffff';
    // Keep blank inputs disabled so only committed rows submit
    blankEl.querySelectorAll('input[name="custom_slug[]"], input[name="custom_label[]"], input[name="custom_light[]"]').forEach(function (inp) {
      inp.disabled = true;
      if (inp.classList.contains('custom-label-input')) inp.value = 'Custom';
      if (inp.classList.contains('custom-slug-input')) inp.value = '';
      if (inp.classList.contains('custom-light-hex')) inp.value = '#ffffff';
    });

    bindPicker(lightCard);
    bindPicker(darkCard);
    bindRename(lightCard);
  }

  function bindRename(root) {
    (root || document).querySelectorAll('[data-editable-name]').forEach(function (labelEl) {
      if (labelEl.dataset.renameBound) return;
      labelEl.dataset.renameBound = '1';
      labelEl.addEventListener('dblclick', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var card = labelEl.closest('.token-swatch');
        if (!card) return;
        // Only light-mode custom names are editable
        if (!card.closest('#light-swatch-grid')) return;
        if (!card.classList.contains('is-custom')) return;

        var current = labelEl.textContent.trim();
        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'token-swatch-label-input';
        input.value = current;
        input.setAttribute('maxlength', '40');
        labelEl.replaceWith(input);
        input.focus();
        input.select();

        function finish() {
          var next = input.value.trim() || current;
          var span = document.createElement('span');
          span.className = 'token-swatch-label is-editable';
          span.setAttribute('data-editable-name', '');
          span.title = 'Double-click to rename';
          span.textContent = next;
          input.replaceWith(span);
          bindRename(card);

          if (card.classList.contains('is-blank')) {
            if (!/^custom$/i.test(next)) {
              commitBlankCustom(card, next);
            }
            return;
          }

          // Update committed label + slug + dark sync
          var labelInput = card.querySelector('.custom-label-input');
          var slugInput = card.querySelector('.custom-slug-input');
          if (labelInput) labelInput.value = next;
          var newSlug = slugify(next);
          if (slugInput) {
            var oldSlug = slugInput.value;
            slugInput.value = newSlug;
            // Sync dark labels with same index / old slug
            document.querySelectorAll('#dark-swatch-grid .token-swatch.is-committed').forEach(function (darkCard) {
              var ds = darkCard.querySelector('input[name="custom_dark_slug[]"]');
              var dl = darkCard.querySelector('[data-synced-label]');
              if (ds && (ds.value === oldSlug || ds.value === newSlug)) {
                ds.value = newSlug;
                if (dl) {
                  dl.textContent = next;
                  dl.setAttribute('data-synced-label', newSlug);
                }
              }
            });
          }
        }
        input.addEventListener('blur', finish);
        input.addEventListener('keydown', function (ev) {
          if (ev.key === 'Enter') { ev.preventDefault(); input.blur(); }
          if (ev.key === 'Escape') { input.value = current; input.blur(); }
        });
      });
    });
  }

  // Before submit: enable only committed custom fields (blank stays disabled)
  var form = document.getElementById('style-form');
  if (form) {
    form.addEventListener('submit', function () {
      document.querySelectorAll('#light-custom-blank input, #dark-custom-blank input').forEach(function (inp) {
        inp.disabled = true;
      });
    });
  }

  bindPicker(document);
  bindRename(document);
})();
</script>
