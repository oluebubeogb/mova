<?php
use Mova\Security\Csrf;
$tokens = $tokens ?? [];
$colors = $tokens['colors'] ?? [];
$dark = $tokens['colors_dark'] ?? [];
$typo = $tokens['typography'] ?? [];
$radius = $tokens['radius'] ?? [];
$shadows = $tokens['shadows'] ?? [];
$spacing = $tokens['spacing'] ?? [];
$lightLabels = [
    'primary' => 'Primary', 'secondary' => 'Secondary', 'accent' => 'Accent',
    'background' => 'Background', 'surface' => 'Surface', 'text' => 'Text',
    'muted' => 'Muted', 'border' => 'Border',
];
?>
<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Style tokens saved. Public cache cleared.</div>
<?php endif; ?>

<p class="design-intro">Design tokens — colors, type, radius, shadows, density.</p>

<form method="post" action="/hq/style" class="design-form">
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
                <div class="token-swatch-grid">
                    <?php foreach ($lightLabels as $key => $label): $val = $colors[$key] ?? '#000000'; ?>
                    <label class="token-swatch">
                        <span class="token-swatch-label"><?= htmlspecialchars($label) ?></span>
                        <input type="color" name="color_<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($val) ?>">
                        <span class="token-swatch-hex"><?= htmlspecialchars($val) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="hq-layer-panel" data-layer-panel="dark" hidden>
                <h3>Dark colors</h3>
                <div class="token-swatch-grid">
                    <?php foreach ($lightLabels as $key => $label): $val = $dark[$key] ?? '#ffffff'; ?>
                    <label class="token-swatch">
                        <span class="token-swatch-label"><?= htmlspecialchars($label) ?></span>
                        <input type="color" name="color_dark_<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($val) ?>">
                        <span class="token-swatch-hex"><?= htmlspecialchars($val) ?></span>
                    </label>
                    <?php endforeach; ?>
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
