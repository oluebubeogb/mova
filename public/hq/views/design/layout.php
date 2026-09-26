<?php
use Mova\Security\Csrf;
$layout = $layout ?? [];
$header = $layout['header'] ?? [];
$footer = $layout['footer'] ?? [];
$mobileNav = $layout['mobile_nav'] ?? [];
$mode = $mobile_menu_icon_mode ?? 'preset';
$preset = $mobile_menu_icon_preset ?? 'fa-bars';
$custom = $mobile_menu_icon_custom ?? '';
$iconUrl = $mobile_menu_icon_url ?? '';
$mobileIconPresets = $mobileIconPresets ?? [];
?>
<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Layout saved. Public cache cleared.</div>
<?php endif; ?>

<?php if (!empty($_GET['reset'])): ?>
    <div class="alert alert-success">Restored Mova defaults. Public cache cleared.</div>
<?php endif; ?>
<form method="post" action="/hq/layout/reset" class="design-reset-form" style="margin:0 0 1rem;" onsubmit="return confirm('Reset layout to Mova defaults? This overwrites your current layout settings.');">
    <?= Csrf::field() ?>
    <button type="submit" class="btn btn-secondary" style="font-size:0.85rem;">
        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset layout to defaults
    </button>
</form>
<p class="design-intro">Header, navigation, containers, and mobile menu.</p>

<form method="post" action="/hq/layout" class="design-form">
    <?= Csrf::field() ?>

    <div class="hq-layers" data-layer-key="mova_hq_layer_layout">
        <div class="hq-layer-tabs" role="tablist">
            <button type="button" class="hq-layer-tab is-active" data-layer="container" role="tab">
                <i class="fa-solid fa-expand" aria-hidden="true"></i><span>Container</span>
            </button>
            <button type="button" class="hq-layer-tab" data-layer="header" role="tab">
                <i class="fa-solid fa-window-maximize" aria-hidden="true"></i><span>Header</span>
            </button>
            <button type="button" class="hq-layer-tab" data-layer="nav" role="tab">
                <i class="fa-solid fa-bars" aria-hidden="true"></i><span>Navigation</span>
            </button>
            <button type="button" class="hq-layer-tab" data-layer="mobile" role="tab">
                <i class="fa-solid fa-mobile-screen" aria-hidden="true"></i><span>Mobile menu</span>
            </button>
            <button type="button" class="hq-layer-tab" data-layer="footer" role="tab">
                <i class="fa-solid fa-shoe-prints" aria-hidden="true"></i><span>Footer</span>
            </button>
        </div>
        <hr class="hq-layer-rule">
        <div class="hq-layer-panels">
            <div class="hq-layer-panel is-active" data-layer-panel="container">
                <h3>Container</h3>
                <div class="form-group">
                    <label>Max width</label>
                    <?php $cw = $layout['container_width'] ?? '720px'; ?>
                    <input type="text" name="container_width" value="<?= htmlspecialchars($cw) ?>"
                           placeholder="720px, 100%, 90vw, min(960px, 90%)"
                           pattern=".*"
                           list="container-width-suggestions"
                           class="input">
                    <datalist id="container-width-suggestions">
                        <option value="640px">Narrow</option>
                        <option value="720px">Default</option>
                        <option value="960px">Wide</option>
                        <option value="1200px">Extra wide</option>
                        <option value="100%">Full width</option>
                        <option value="90vw">90vw</option>
                        <option value="min(960px, 90%)">Fluid max 960px</option>
                    </datalist>
                    <p class="field-hint">Any CSS length: <code>px</code>, <code>%</code>, <code>vw</code>, <code>rem</code>, <code>ch</code>, or functions like <code>min()</code> / <code>max()</code> / <code>clamp()</code>. Used as <code>--max-width</code> on the public site.</p>
                </div>
            </div>

            <div class="hq-layer-panel" data-layer-panel="header" hidden>
                <h3>Header</h3>
                <div class="form-group">
                    <label>Type</label>
                    <select name="header_type">
                        <?php $ht = $header['type'] ?? 'sticky';
                        foreach (['sticky' => 'Sticky', 'static' => 'Static', 'floating' => 'Floating'] as $v => $lab): ?>
                        <option value="<?= $v ?>" <?= $ht === $v ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Logo position</label>
                    <select name="logo_position">
                        <?php $lp = $header['logo_position'] ?? 'left'; ?>
                        <option value="left" <?= $lp === 'left' ? 'selected' : '' ?>>Left</option>
                        <option value="center" <?= $lp === 'center' ? 'selected' : '' ?>>Center</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nav alignment</label>
                    <select name="nav_align">
                        <?php $na = $header['nav_align'] ?? 'right';
                        foreach (['left' => 'Left', 'center' => 'Center', 'right' => 'Right'] as $v => $lab): ?>
                        <option value="<?= $v ?>" <?= $na === $v ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="header_transparent" value="1" <?= !empty($header['transparent']) ? 'checked' : '' ?>>
                        Transparent header
                    </label>
                </div>
            </div>

            <div class="hq-layer-panel" data-layer-panel="nav" hidden>
                <h3>Navigation links</h3>
                <div class="form-group">
                    <label>Primary nav — mobile &amp; fallback (label|url per line)</label>
                    <textarea name="nav_links" rows="6" placeholder="Home|/&#10;About Us|/about&#10;Contact|/contact&#10;Blog|/blog"><?= htmlspecialchars($nav_links ?? '') ?></textarea>
                    <p class="field-hint">Used in the mobile drawer. Also used on wide screens if “Desktop nav” below is empty.</p>
                </div>
                <div class="form-group">
                    <label>Desktop / tablet nav (optional, label|url per line)</label>
                    <textarea name="nav_links_desktop" rows="6" placeholder="About Us|/about&#10;Contact|/contact&#10;Blog|/blog"><?= htmlspecialchars($nav_links_desktop ?? '') ?></textarea>
                    <p class="field-hint">Shown in the header on wide screens next to Search and Theme toggle. Leave blank to reuse primary nav. Home and Search lines are skipped (logo + search icon cover those).</p>
                </div>
            </div>

            <div class="hq-layer-panel" data-layer-panel="mobile" hidden>
                <h3>Mobile menu</h3>
                <div class="form-group">
                    <label>Mobile nav style</label>
                    <select name="mobile_nav_style">
                        <?php
                        $mns = $mobileNav['style'] ?? 'drawer-right';
                        // Legacy alias
                        if ($mns === 'drawer') $mns = 'drawer-top';
                        $mobileStyles = [
                            'drawer-top' => 'Top panel (60vw × 60vh)',
                            'drawer-right' => 'Right panel (60vw × 60vh)',
                            'drawer-left' => 'Left panel (60vw × 60vh)',
                            'fullscreen' => 'Fullscreen overlay',
                            'bottom-sheet' => 'Bottom sheet',
                        ];
                        foreach ($mobileStyles as $v => $lab):
                        ?>
                        <option value="<?= $v ?>" <?= $mns === $v ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field-hint">How the mobile menu opens. Preview on a narrow viewport after saving.</p>
                </div>
                <div class="form-group">
                    <label>Mobile menu content alignment</label>
                    <select name="mobile_nav_content_align">
                        <?php
                        $mna = $mobileNav['content_align'] ?? 'left';
                        if (!in_array($mna, ['left', 'center', 'right'], true)) $mna = 'left';
                        foreach (['left' => 'Left', 'center' => 'Center', 'right' => 'Right'] as $v => $lab):
                        ?>
                        <option value="<?= $v ?>" <?= $mna === $v ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field-hint">Align links and actions inside the mobile menu panel.</p>
                </div>
                <p class="field-hint">Menu icon — preset, custom Font Awesome, or image URL.</p>
                <div class="icon-mode-tabs">
                    <label class="icon-mode-tab"><input type="radio" name="mobile_menu_icon_mode" value="preset" <?= $mode === 'preset' ? 'checked' : '' ?>> Presets</label>
                    <label class="icon-mode-tab"><input type="radio" name="mobile_menu_icon_mode" value="custom" <?= $mode === 'custom' ? 'checked' : '' ?>> Custom FA</label>
                    <label class="icon-mode-tab"><input type="radio" name="mobile_menu_icon_mode" value="upload" <?= $mode === 'upload' ? 'checked' : '' ?>> Upload / URL</label>
                </div>
                <div class="icon-mode-panel" data-mode-panel="preset" style="<?= $mode !== 'preset' ? 'display:none' : '' ?>">
                    <div class="icon-preset-grid">
                        <?php foreach ($mobileIconPresets as $cls => $label): ?>
                        <label class="icon-preset-option <?= $preset === $cls ? 'is-selected' : '' ?>">
                            <input type="radio" name="mobile_menu_icon_preset" value="<?= htmlspecialchars($cls) ?>" <?= $preset === $cls ? 'checked' : '' ?>>
                            <span class="icon-preset-preview"><i class="fa-solid <?= htmlspecialchars($cls) ?>"></i></span>
                            <span class="icon-preset-label"><?= htmlspecialchars($label) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="icon-mode-panel" data-mode-panel="custom" style="<?= $mode !== 'custom' ? 'display:none' : '' ?>">
                    <input type="text" name="mobile_menu_icon_custom" placeholder="fa-solid fa-bars" value="<?= htmlspecialchars($custom) ?>">
                </div>
                <div class="icon-mode-panel" data-mode-panel="upload" style="<?= $mode !== 'upload' ? 'display:none' : '' ?>">
                    <input type="text" name="mobile_menu_icon_url" placeholder="/mova-uploads/icon.svg" value="<?= htmlspecialchars($iconUrl) ?>">
                </div>
            </div>

            <div class="hq-layer-panel" data-layer-panel="footer" hidden>
                <h3>Footer</h3>
                <div class="form-group">
                    <label>Style</label>
                    <select name="footer_style">
                        <?php $fs = $footer['style'] ?? 'simple';
                        foreach (['simple' => 'Simple', 'centered' => 'Centered', 'columns' => 'Columns'] as $v => $lab): ?>
                        <option value="<?= $v ?>" <?= $fs === $v ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="hq-layer-actions">
        <button type="submit" class="btn-primary">Save layout</button>
    </div>
</form>
<?php include __DIR__ . '/_design_styles.php'; ?>
<script>
(function () {
    var form = document.querySelector('.design-form');
    if (!form) return;
    var tabs = form.querySelectorAll('input[name="mobile_menu_icon_mode"]');
    var panels = form.querySelectorAll('[data-mode-panel]');
    function sync() {
        var mode = 'preset';
        tabs.forEach(function (t) { if (t.checked) mode = t.value; });
        panels.forEach(function (p) {
            p.style.display = p.getAttribute('data-mode-panel') === mode ? '' : 'none';
        });
    }
    tabs.forEach(function (t) { t.addEventListener('change', sync); });
})();
</script>
