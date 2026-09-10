<?php use Mova\Security\Csrf; $settings = $settings ?? []; $mobileIconPresets = $mobileIconPresets ?? []; ?>

<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Appearance saved. Public cache cleared.</div>
<?php endif; ?>

<form method="post" action="/hq/appearance" class="panel appearance-form">
    <?= Csrf::field() ?>

    <h2 class="form-section-title">Brand</h2>
    <div class="form-group">
        <label>Site name</label>
        <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? 'Mova') ?>">
    </div>
    <div class="form-group">
        <label>Tagline / description</label>
        <textarea name="site_description" rows="2"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label>Logo URL</label>
        <input type="text" name="logo_url" placeholder="/mova-uploads/..." value="<?= htmlspecialchars($settings['logo_url'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label>Favicon URL</label>
        <input type="text" name="favicon_url" placeholder="/mova-uploads/favicon.ico" value="<?= htmlspecialchars($settings['favicon_url'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label>Brand color</label>
        <input type="color" name="brand_color" value="<?= htmlspecialchars($settings['brand_color'] ?? '#2563eb') ?>" style="height:2.5rem;padding:0.2rem;width:4rem;">
        <span style="margin-left:0.5rem;color:var(--hq-muted);font-size:0.85rem;"><?= htmlspecialchars($settings['brand_color'] ?? '#2563eb') ?></span>
    </div>
    <div class="form-group">
        <label>Footer text</label>
        <input type="text" name="footer_text" value="<?= htmlspecialchars($settings['footer_text'] ?? '') ?>" placeholder="Optional custom footer line">
    </div>

    <h2 class="form-section-title">Navigation</h2>
    <div class="form-group">
        <label>Primary navigation (one label|url per line)</label>
        <textarea name="nav_links" rows="5" placeholder="Home|/&#10;About|/about"><?= htmlspecialchars($settings['nav_links'] ?? "Home|/\nSearch|/search") ?></textarea>
    </div>

    <div class="form-group mobile-icon-field">
        <label>Mobile menu icon</label>
        <p class="field-hint">Shown on phones and tablets when the main nav collapses. Pick a preset, use any Font Awesome class, or an uploaded/remote image.</p>

        <?php
        $mode = $settings['mobile_menu_icon_mode'] ?? 'preset';
        if (!in_array($mode, ['preset', 'custom', 'upload'], true)) {
            $mode = 'preset';
        }
        $preset = $settings['mobile_menu_icon_preset'] ?? 'fa-bars';
        $custom = $settings['mobile_menu_icon_custom'] ?? '';
        $iconUrl = $settings['mobile_menu_icon_url'] ?? '';
        ?>

        <div class="icon-mode-tabs" role="tablist">
            <label class="icon-mode-tab">
                <input type="radio" name="mobile_menu_icon_mode" value="preset" <?= $mode === 'preset' ? 'checked' : '' ?>>
                <span>Presets</span>
            </label>
            <label class="icon-mode-tab">
                <input type="radio" name="mobile_menu_icon_mode" value="custom" <?= $mode === 'custom' ? 'checked' : '' ?>>
                <span>Custom FA</span>
            </label>
            <label class="icon-mode-tab">
                <input type="radio" name="mobile_menu_icon_mode" value="upload" <?= $mode === 'upload' ? 'checked' : '' ?>>
                <span>Upload / URL</span>
            </label>
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
            <input type="text" name="mobile_menu_icon_custom" placeholder="e.g. fa-solid fa-bars or fa-brands fa-github" value="<?= htmlspecialchars($custom) ?>">
            <p class="field-hint">Any Font Awesome 6 class string. Public site will load FA when this mode is used.</p>
        </div>

        <div class="icon-mode-panel" data-mode-panel="upload" style="<?= $mode !== 'upload' ? 'display:none' : '' ?>">
            <input type="text" name="mobile_menu_icon_url" placeholder="/mova-uploads/menu-icon.svg or https://..." value="<?= htmlspecialchars($iconUrl) ?>">
            <p class="field-hint">Image URL (SVG/PNG recommended). Upload via Media first, then paste the path here.</p>
        </div>
    </div>

    <h2 class="form-section-title">Theme</h2>
    <div class="form-group">
        <label>Active theme</label>
        <select name="active_theme">
            <?php foreach (($themes ?? []) as $th): ?>
                <option value="<?= htmlspecialchars($th['slug']) ?>" <?= ($activeTheme ?? 'default') === $th['slug'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($th['name']) ?> (<?= htmlspecialchars($th['slug']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <p class="field-hint">Themes live in <code>/mova-themes</code>. Content stays independent of presentation.</p>
    </div>

    <button type="submit" class="btn-primary">Save appearance</button>
</form>

<style>
.appearance-form { max-width: 640px; }
.form-section-title {
    margin: 1.75rem 0 0.75rem;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hq-muted);
    font-weight: 600;
}
.form-section-title:first-child { margin-top: 0; }
.field-hint {
    font-size: 0.8rem;
    color: var(--hq-muted);
    margin: 0.35rem 0 0;
}
.icon-mode-tabs {
    display: flex;
    gap: 0.35rem;
    margin: 0.5rem 0 0.75rem;
    flex-wrap: wrap;
}
.icon-mode-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.75rem;
    border: 1px solid var(--hq-border);
    border-radius: 6px;
    background: var(--hq-input-bg);
    cursor: pointer;
    font-size: 0.85rem;
    color: var(--hq-muted);
}
.icon-mode-tab:has(input:checked) {
    border-color: var(--hq-accent);
    color: var(--hq-accent);
    background: rgba(59, 130, 246, 0.08);
}
.icon-mode-tab input { margin: 0; }
.icon-preset-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 0.5rem;
}
.icon-preset-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    padding: 0.75rem 0.5rem;
    border: 1px solid var(--hq-border);
    border-radius: 8px;
    background: var(--hq-input-bg);
    cursor: pointer;
    text-align: center;
    position: relative;
}
.icon-preset-option input { position: absolute; opacity: 0; pointer-events: none; }
.icon-preset-option.is-selected,
.icon-preset-option:has(input:checked) {
    border-color: var(--hq-accent);
    box-shadow: 0 0 0 1px var(--hq-accent);
}
.icon-preset-preview {
    font-size: 1.25rem;
    color: var(--hq-text);
    line-height: 1;
}
.icon-preset-label {
    font-size: 0.72rem;
    color: var(--hq-muted);
}
</style>
<script>
(function () {
    var form = document.querySelector('.appearance-form');
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
    form.querySelectorAll('.icon-preset-option').forEach(function (opt) {
        opt.addEventListener('click', function () {
            form.querySelectorAll('.icon-preset-option').forEach(function (o) { o.classList.remove('is-selected'); });
            opt.classList.add('is-selected');
        });
    });
})();
</script>
