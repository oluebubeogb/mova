<?php
use Mova\Security\Csrf;
$c = $components ?? [];
$btn = $c['button'] ?? [];
$card = $c['card'] ?? [];
$hero = $c['hero'] ?? [];
?>
<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Component variants saved. Public cache cleared.</div>
<?php endif; ?>

<p class="design-intro">Visual variants for buttons, cards, and heroes.</p>

<form method="post" action="/hq/components" class="design-form">
    <?= Csrf::field() ?>

    <div class="hq-layers" data-layer-key="mova_hq_layer_components">
        <div class="hq-layer-tabs" role="tablist">
            <button type="button" class="hq-layer-tab is-active" data-layer="button" role="tab">
                <i class="fa-solid fa-toggle-on" aria-hidden="true"></i><span>Buttons</span>
            </button>
            <button type="button" class="hq-layer-tab" data-layer="card" role="tab">
                <i class="fa-solid fa-clone" aria-hidden="true"></i><span>Cards</span>
            </button>
            <button type="button" class="hq-layer-tab" data-layer="hero" role="tab">
                <i class="fa-solid fa-panorama" aria-hidden="true"></i><span>Hero</span>
            </button>
        </div>
        <hr class="hq-layer-rule">
        <div class="hq-layer-panels">
            <div class="hq-layer-panel is-active" data-layer-panel="button">
                <h3>Buttons</h3>
                <div class="variant-grid">
                    <?php $btnVariant = $btn['variant'] ?? 'solid';
                    foreach (['solid' => 'Solid', 'outline' => 'Outline', 'ghost' => 'Ghost', 'soft' => 'Soft'] as $v => $lab): ?>
                    <label class="variant-option <?= $btnVariant === $v ? 'is-selected' : '' ?>">
                        <input type="radio" name="button_variant" value="<?= $v ?>" <?= $btnVariant === $v ? 'checked' : '' ?>>
                        <span class="variant-preview variant-preview--btn variant-preview--btn-<?= $v ?>"><?= $lab ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label>Corner radius</label>
                    <select name="button_radius">
                        <?php $br = $btn['radius'] ?? 'md';
                        foreach (['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'full' => 'Pill'] as $v => $lab): ?>
                        <option value="<?= $v ?>" <?= $br === $v ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="button_uppercase" value="1" <?= !empty($btn['uppercase']) ? 'checked' : '' ?>>
                        Uppercase labels
                    </label>
                </div>
            </div>

            <div class="hq-layer-panel" data-layer-panel="card" hidden>
                <h3>Cards</h3>
                <div class="variant-grid">
                    <?php $cardVariant = $card['variant'] ?? 'elevated';
                    foreach (['flat' => 'Flat', 'elevated' => 'Elevated', 'outlined' => 'Outlined', 'glass' => 'Glass'] as $v => $lab): ?>
                    <label class="variant-option <?= $cardVariant === $v ? 'is-selected' : '' ?>">
                        <input type="radio" name="card_variant" value="<?= $v ?>" <?= $cardVariant === $v ? 'checked' : '' ?>>
                        <span class="variant-preview variant-preview--card variant-preview--card-<?= $v ?>"><?= $lab ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label>Corner radius</label>
                    <select name="card_radius">
                        <?php $cr = $card['radius'] ?? 'md';
                        foreach (['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'] as $v => $lab): ?>
                        <option value="<?= $v ?>" <?= $cr === $v ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Hover effect</label>
                    <select name="card_hover">
                        <?php $ch = $card['hover'] ?? 'lift';
                        foreach (['none' => 'None', 'lift' => 'Lift', 'scale' => 'Scale', 'border' => 'Border'] as $v => $lab): ?>
                        <option value="<?= $v ?>" <?= $ch === $v ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="hq-layer-panel" data-layer-panel="hero" hidden>
                <h3>Hero</h3>
                <div class="variant-grid variant-grid--hero">
                    <?php $heroVariant = $hero['variant'] ?? 'centered';
                    foreach (['centered' => 'Centered', 'split' => 'Split', 'minimal' => 'Minimal', 'fullscreen' => 'Fullscreen'] as $v => $lab): ?>
                    <label class="variant-option <?= $heroVariant === $v ? 'is-selected' : '' ?>">
                        <input type="radio" name="hero_variant" value="<?= $v ?>" <?= $heroVariant === $v ? 'checked' : '' ?>>
                        <span class="variant-preview variant-preview--hero variant-preview--hero-<?= $v ?>"><?= $lab ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="hq-layer-actions">
        <button type="submit" class="btn-primary">Save components</button>
    </div>
</form>
<?php include __DIR__ . '/_design_styles.php'; ?>
