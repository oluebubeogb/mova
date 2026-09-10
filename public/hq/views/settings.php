<?php use Mova\Security\Csrf; $settings = $settings ?? []; ?>

<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Settings saved.</div>
<?php endif; ?>

<p class="design-intro">General site options and AI assist. Outbound mail uses Audience → Mailbox accounts. Sites are under Settings → Sites.</p>

<form method="post" action="/hq/settings" class="settings-form">
    <?= Csrf::field() ?>

    <div class="hq-layers" data-layer-key="mova_hq_layer_settings">
        <script class="layer-boot-fix">
        (function () {
          try {
            var k = 'mova_hq_layer_settings';
            var q = new URLSearchParams(location.search).get('layer');
            if (q === 'ai' || q === 'general') {
              localStorage.setItem(k, q);
            } else {
              // Default Settings landing = General (site name, comments, etc.)
              localStorage.setItem(k, 'general');
            }
          } catch (e) {}
        })();
        </script>
        <div class="hq-layer-tabs" role="tablist">
            <button type="button" class="hq-layer-tab is-active" data-layer="general" role="tab">
                <i class="fa-solid fa-sliders" aria-hidden="true"></i><span>General</span>
            </button>
            <button type="button" class="hq-layer-tab" data-layer="ai" role="tab">
                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><span>AI assist</span>
            </button>
        </div>
        <hr class="hq-layer-rule">
        <div class="hq-layer-panels">
            <div class="hq-layer-panel is-active" data-layer-panel="general">
                <h3>General</h3>
                <div class="form-section">
                  <div class="form-grid">
                    <div class="form-group"><label>Site name</label>
                      <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>"></div>
                    <div class="form-group span-2"><label>Site description</label>
                      <textarea name="site_description" rows="2"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea></div>
                    <div class="form-group span-2">
                      <label class="checkbox-label">
                        <input type="checkbox" name="comments_enabled" value="1" <?= ($settings['comments_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                        Enable comments
                      </label>
                    </div>
                  </div>
                </div>
            </div>

            <div class="hq-layer-panel" data-layer-panel="ai" hidden>
                <h3>AI assist</h3>
                <p class="field-hint">Leave API key empty for local heuristics. AI never auto-publishes.</p>
                <div class="form-section">
                  <div class="form-grid">
                    <div class="form-group span-2"><label>API key</label>
                      <div class="password-field">
                        <input type="password" name="ai_api_key" value="<?= htmlspecialchars($settings['ai_api_key'] ?? '') ?>" autocomplete="new-password" placeholder="sk-…">
                        <button type="button" class="password-toggle" aria-label="Show password"><i class="fa-solid fa-eye"></i><i class="fa-solid fa-eye-slash"></i></button>
                      </div></div>
                    <div class="form-group span-2"><label>API base URL</label>
                      <input type="url" name="ai_api_url" value="<?= htmlspecialchars($settings['ai_api_url'] ?? 'https://api.openai.com/v1') ?>" placeholder="https://api.openai.com/v1"></div>
                    <div class="form-group"><label>Model</label>
                      <input type="text" name="ai_model" value="<?= htmlspecialchars($settings['ai_model'] ?? 'gpt-4o-mini') ?>"></div>
                    <div class="form-group"><label>Provider label</label>
                      <input type="text" name="ai_provider" value="<?= htmlspecialchars($settings['ai_provider'] ?? 'openai') ?>"></div>
                  </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <div class="hq-layer-actions">
        <button type="submit" class="btn-primary">Save settings</button>
    </div>
</form>
<style>
.design-intro { color: var(--hq-muted); margin: 0 0 1.15rem; font-size: 0.95rem; }
.field-hint { font-size: 0.8rem; color: var(--hq-muted); margin: 0 0 0.85rem; }
.checkbox-label { display: inline-flex; align-items: center; gap: 0.45rem; cursor: pointer; font-weight: 500; }
</style>
<script>
document.querySelectorAll('.password-toggle').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = btn.parentElement.querySelector('input');
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.classList.toggle('is-visible', show);
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
});
</script>
