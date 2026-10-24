<?php use Mova\Security\Csrf; $settings = $settings ?? []; ?>

<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Settings saved.</div>
<?php endif; ?>

<p class="design-intro">General site options and Mova AI. Outbound mail uses Audience → Mailbox accounts. Sites are under Settings → Sites.</p>

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
                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><span>Mova AI</span>
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
                    <div class="form-group"><label for="set-tz">Timezone</label>
                      <?php $tzCur = \Mova\Core\Time::zoneName(); ?>
                      <select id="set-tz" name="site_timezone">
                        <?php foreach (\DateTimeZone::listIdentifiers() as $tzId): ?>
                          <option value="<?= htmlspecialchars($tzId) ?>"<?= $tzId === $tzCur ? ' selected' : '' ?>><?= htmlspecialchars(str_replace('_', ' ', $tzId)) ?><?= $tzId === 'Africa/Lagos' ? ' (WAT, default)' : '' ?></option>
                        <?php endforeach; ?>
                      </select>
                      <p class="field-hint">Shown as e.g. <?= htmlspecialchars(\Mova\Core\Time::format(time())) ?> (<?= htmlspecialchars(\Mova\Core\Time::abbr()) ?>). Stored in UTC.</p></div>
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
                <?php
                $aiSrcLabel = static function (string $k): string {
                    return match (\Mova\AI\AiConfig::source($k)) {
                        'settings' => 'set here',
                        'env' => 'from environment',
                        'config' => 'from config.php',
                        default => 'Mova default',
                    };
                };
                $aiSecret = static function (string $k): string {
                    // Never echo the stored secret — only a masked hint of the effective value.
                    $eff = \Mova\AI\AiConfig::get($k);
                    return $eff === '' ? 'Not set' : \Mova\AI\AiConfig::mask($eff);
                };
                $aiVal = static fn (string $k): string => htmlspecialchars((string) ($settings[$k] ?? ''));
                $aiPh = static fn (string $k): string => htmlspecialchars(\Mova\AI\AiConfig::get($k));
                ?>
                <?php
                $advAi = false; foreach (['ai_api_url','ai_model','ai_provider'] as $__k) { if (($settings[$__k] ?? '') !== '') { $advAi = true; } }
                if (($settings['ai_tool_mode'] ?? 'auto') !== 'auto' || ($settings['ai_worker_mode'] ?? 'auto') !== 'auto') { $advAi = true; }
                $advImg = false; foreach (['ai_img_api_url','ai_img_model','ai_img_provider'] as $__k) { if (($settings[$__k] ?? '') !== '') { $advImg = true; } }
                ?>
                <h3 class="set-ai-title">Mova AI
                    <span class="hq-help" tabindex="0" role="note" data-tip="Built-in AI for content, SEO, design and the site assistant. Uses the Mova AI gateway by default (<?= htmlspecialchars(\Mova\AI\AiConfig::DEFAULT_API_URL) ?>, model <?= htmlspecialchars(\Mova\AI\AiConfig::DEFAULT_MODEL) ?>). Empty fields use the default. Mova AI never auto-publishes."><i class="fa-regular fa-circle-question" aria-hidden="true"></i><span class="hq-sr-only">About Mova AI</span></span>
                    <a class="set-ai-usage" href="/hq/ai-usage">Usage <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </h3>
                <div class="form-section">
                  <div class="form-grid">
                    <div class="form-group span-2"><label for="set-ai-key">API key</label>
                      <input id="set-ai-key" type="password" name="ai_api_key" value="" autocomplete="new-password"
                             placeholder="<?= htmlspecialchars($aiSecret('ai_api_key')) ?> — leave blank to keep">
                      <small class="field-hint">Current: <code><?= htmlspecialchars($aiSecret('ai_api_key')) ?></code> (<?= $aiSrcLabel('ai_api_key') ?>).
                        <?php if (($settings['ai_api_key'] ?? '') !== ''): ?>
                          <label class="checkbox-label"><input type="checkbox" name="ai_api_key_clear" value="1"> Remove saved key (use default)</label>
                        <?php endif; ?></small></div>
                  </div>
                  <details class="hq-advanced"<?= $advAi ? ' open' : '' ?>>
                    <summary><i class="fa-solid fa-sliders" aria-hidden="true"></i> Advanced <span class="hq-advanced-sub">endpoint, model, tool calling, background jobs</span></summary>
                    <div class="form-grid">
                    <div class="form-group span-2"><label for="set-ai-url">API base URL</label>
                      <input id="set-ai-url" type="url" name="ai_api_url" value="<?= $aiVal('ai_api_url') ?>" placeholder="<?= $aiPh('ai_api_url') ?>">
                      <small class="field-hint"><?= $aiSrcLabel('ai_api_url') ?></small></div>
                    <div class="form-group"><label for="set-ai-model">Model</label>
                      <input id="set-ai-model" type="text" name="ai_model" value="<?= $aiVal('ai_model') ?>" placeholder="<?= $aiPh('ai_model') ?>">
                      <small class="field-hint"><?= $aiSrcLabel('ai_model') ?></small></div>
                    <div class="form-group"><label for="set-ai-provider">Provider label</label>
                      <input id="set-ai-provider" type="text" name="ai_provider" value="<?= $aiVal('ai_provider') ?>" placeholder="<?= $aiPh('ai_provider') ?>"></div>
                    <?php $toolMode = (string) ($settings['ai_tool_mode'] ?? 'auto'); $workerMode = (string) ($settings['ai_worker_mode'] ?? 'auto'); ?>
                    <div class="form-group"><label for="set-ai-tools">Tool calling</label>
                      <select id="set-ai-tools" name="ai_tool_mode">
                        <option value="auto" <?= $toolMode === 'auto' ? 'selected' : '' ?>>Auto — native when the gateway supports it</option>
                        <option value="native" <?= $toolMode === 'native' ? 'selected' : '' ?>>Always native (OpenAI tools)</option>
                        <option value="text" <?= $toolMode === 'text' ? 'selected' : '' ?>>Text protocol only (JSON in reply)</option>
                      </select>
                      <small class="field-hint">Falls back to the text protocol automatically if native calls fail.</small></div>
                    <div class="form-group"><label for="set-ai-worker">Background jobs</label>
                      <select id="set-ai-worker" name="ai_worker_mode">
                        <option value="auto" <?= $workerMode === 'auto' ? 'selected' : '' ?>>Auto — worker if running, else best fallback</option>
                        <option value="worker" <?= $workerMode === 'worker' ? 'selected' : '' ?>>Worker only (cron / supervisord)</option>
                        <option value="poke" <?= $workerMode === 'poke' ? 'selected' : '' ?>>Loopback poke (shared hosting, no cron)</option>
                        <option value="request" <?= $workerMode === 'request' ? 'selected' : '' ?>>After the request (legacy)</option>
                      </select>
                      <?php $hb = \Mova\AI\AiWorker::heartbeatAge(); ?>
                      <small class="field-hint">Worker: <?= $hb === null ? 'not seen yet' : ($hb < 120 ? '<span style="color:#16a34a">running</span> (seen ' . $hb . 's ago)' : 'last seen ' . htmlspecialchars(\Mova\AI\AiWorker::ago($hb))) ?>.
                        Cron: <code>* * * * * php <?= htmlspecialchars(dirname(__DIR__, 3)) ?>/bin/ai-worker.php</code></small></div>
                    </div>
                  </details>
                  <div class="ai-test-row">
                    <button type="button" class="btn-secondary" id="ai-test-btn"><i class="fa-solid fa-plug-circle-check" aria-hidden="true"></i> Test connection</button>
                    <label class="checkbox-label"><input type="checkbox" id="ai-test-chat" value="1"> include a tiny chat request</label>
                  </div>
                  <div id="ai-test-out" class="ai-test-out" hidden aria-live="polite"></div>
                </div>
                <h3 class="set-ai-title" style="margin-top:1.5rem;">Mova Image
                    <span class="hq-help" tabindex="0" role="note" data-tip="AI image generation for the media library. Empty fields inherit the Mova AI gateway and key (model <?= htmlspecialchars(\Mova\AI\AiConfig::DEFAULT_IMG_MODEL) ?>). Real provider keys stay on the gateway."><i class="fa-regular fa-circle-question" aria-hidden="true"></i><span class="hq-sr-only">About Mova Image</span></span>
                </h3>
                <div class="form-section">
                  <div class="form-grid">
                    <div class="form-group span-2"><label for="set-img-key">Image API key</label>
                      <input id="set-img-key" type="password" name="ai_img_api_key" value="" autocomplete="new-password"
                             placeholder="<?= htmlspecialchars($aiSecret('ai_img_api_key')) ?> — leave blank to keep">
                      <small class="field-hint">Current: <code><?= htmlspecialchars($aiSecret('ai_img_api_key')) ?></code> (<?= ($settings['ai_img_api_key'] ?? '') !== '' ? 'set here' : 'inherits Mova AI key' ?>).
                        <?php if (($settings['ai_img_api_key'] ?? '') !== ''): ?>
                          <label class="checkbox-label"><input type="checkbox" name="ai_img_api_key_clear" value="1"> Remove saved key</label>
                        <?php endif; ?></small></div>
                  </div>
                  <details class="hq-advanced"<?= $advImg ? ' open' : '' ?>>
                    <summary><i class="fa-solid fa-sliders" aria-hidden="true"></i> Advanced <span class="hq-advanced-sub">image endpoint and model</span></summary>
                    <div class="form-grid">
                    <div class="form-group span-2"><label for="set-img-url">Image API base URL</label>
                      <input id="set-img-url" type="url" name="ai_img_api_url" value="<?= $aiVal('ai_img_api_url') ?>" placeholder="<?= $aiPh('ai_img_api_url') ?>"></div>
                    <div class="form-group"><label for="set-img-model">Image model</label>
                      <input id="set-img-model" type="text" name="ai_img_model" value="<?= $aiVal('ai_img_model') ?>" placeholder="<?= $aiPh('ai_img_model') ?>"></div>
                    <div class="form-group"><label for="set-img-provider">Image provider label</label>
                      <input id="set-img-provider" type="text" name="ai_img_provider" value="<?= $aiVal('ai_img_provider') ?>" placeholder="<?= $aiPh('ai_img_provider') ?>"></div>
                    </div>
                  </details>
                </div>
                <p class="field-hint">Site ID sent to the gateway for usage reports: <code><?= htmlspecialchars(\Mova\AI\AiConfig::siteId()) ?></code></p>
            </div>
            </div>
        </div>
    </div>

    <div class="hq-layer-actions">
        <button type="submit" class="btn-primary">Save settings</button>
    </div>
</form>
<script>
(function () {
  var btn = document.getElementById('ai-test-btn');
  if (!btn) return;
  var out = document.getElementById('ai-test-out');
  function esc(s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
  btn.addEventListener('click', function () {
    var form = btn.closest('form');
    var fd = new URLSearchParams();
    var csrf = form.querySelector('input[name="_mova_csrf"]');
    fd.set('_mova_csrf', csrf ? csrf.value : '');
    ['ai_api_url', 'ai_model', 'ai_api_key'].forEach(function (n) {
      var i = form.querySelector('[name="' + n + '"]');
      fd.set(n, i ? i.value : '');
    });
    fd.set('chat', document.getElementById('ai-test-chat').checked ? '1' : '0');
    btn.disabled = true;
    out.hidden = false;
    out.className = 'ai-test-out';
    out.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testing…';
    fetch('/hq/settings/ai/test', { method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: fd.toString() })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        out.className = 'ai-test-out ' + (d.ok ? 'is-ok' : 'is-bad');
        var rows = (d.steps || []).map(function (s) {
          return '<li class="' + (s.ok ? 'ok' : (s.skipped ? 'skip' : 'bad')) + '"><i class="fa-solid ' + (s.ok ? 'fa-circle-check' : (s.skipped ? 'fa-circle-minus' : 'fa-circle-xmark')) + '"></i> <strong>' + esc(s.name) + '</strong> <span>' + esc(s.detail || '') + '</span>' + (s.ms ? ' <em>' + s.ms + ' ms</em>' : '') + '</li>';
        }).join('');
        out.innerHTML = '<div class="ai-test-sum"><strong>' + esc(d.summary || (d.ok ? 'Connected' : 'Failed')) + '</strong> · ' + esc(d.url || '') + ' · ' + esc(d.model || '') + ' · ' + esc(d.key || '') + '</div><ul>' + rows + '</ul>';
      })
      .catch(function () { out.className = 'ai-test-out is-bad'; out.textContent = 'Network error while testing.'; })
      .finally(function () { btn.disabled = false; });
  });
})();
</script>
<style>
.ai-test-row { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin: 0.25rem 0 0.75rem; }
.ai-test-out { border: 1px solid var(--hq-border, #e5e7eb); border-radius: 10px; padding: 0.75rem 0.9rem; font-size: 0.85rem; margin-bottom: 1rem; }
.ai-test-out.is-ok { border-color: #86efac; background: rgba(34, 197, 94, 0.07); }
.ai-test-out.is-bad { border-color: #fca5a5; background: rgba(239, 68, 68, 0.06); }
.ai-test-out ul { list-style: none; margin: 0.5rem 0 0; padding: 0; display: grid; gap: 0.3rem; }
.ai-test-out li.ok i { color: #16a34a; } .ai-test-out li.bad i { color: #dc2626; } .ai-test-out li.skip i { color: #94a3b8; }
.ai-test-out li em { color: var(--hq-muted); font-style: normal; font-size: 0.75rem; }
.ai-test-sum { word-break: break-all; }
.design-intro { color: var(--hq-muted); margin: 0 0 1.15rem; font-size: 0.95rem; }
.field-hint { font-size: 0.8rem; color: var(--hq-muted); margin: 0 0 0.85rem; }
.checkbox-label { display: inline-flex; align-items: center; gap: 0.45rem; cursor: pointer; font-weight: 500; }
</style>
