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
                    <select name="container_width">
                        <?php $cw = $layout['container_width'] ?? '720px';
                        foreach (['640px' => 'Narrow', '720px' => 'Default', '960px' => 'Wide', '1200px' => 'Extra wide', '100%' => 'Full'] as $v => $lab): ?>
                        <option value="<?= $v ?>" <?= $cw === $v ? 'selected' : '' ?>><?= $lab ?> (<?= $v ?>)</option>
                        <?php endforeach; ?>
                    </select>
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
                    <label>Primary nav (label|url per line)</label>
                    <textarea name="nav_links" rows="6" placeholder="Home|/&#10;About|/about"><?= htmlspecialchars($nav_links ?? '') ?></textarea>
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
