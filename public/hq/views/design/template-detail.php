<?php
/** @var array $template */
/** @var string $id */
/** @var string $sharedCss */
$meta = $template['meta'] ?? [];
$name = (string) ($meta['name'] ?? $id);
$desc = (string) ($meta['description'] ?? '');
$preview = (string) ($meta['preview'] ?? '');
$body = (string) ($template['body'] ?? '');
$styles = (string) ($template['styles'] ?? '');
$source = (string) ($template['source'] ?? 'seed');
$constraints = $meta['constraints'] ?? [];
$slots = $meta['slots'] ?? [];
?>
<link rel="stylesheet" href="/assets/css/hq-templates.css?v=20260929">
<link rel="stylesheet" href="/assets/css/hq-section-picker.css?v=20261023p5f">
<style>
.hq-tpl-live { border: 1px solid var(--hq-border, #e2e8f0); border-radius: 14px; overflow: hidden; background: #fff; }
.hq-tpl-live__bar { display: flex; gap: .5rem; align-items: center; justify-content: space-between; padding: .5rem .7rem; border-bottom: 1px solid var(--hq-border, #e2e8f0); background: var(--hq-bg, #f8fafc); }
.hq-tpl-live iframe { display: block; width: 100%; height: 640px; border: 0; margin: 0 auto; transition: width .3s; }
.hq-tpl-live[data-w="mobile"] iframe { width: 390px; max-width: 100%; }
.hq-tpl-live[data-w="tablet"] iframe { width: 820px; max-width: 100%; }
.hq-tpl-live .msp-seg { background: var(--hq-surface, #fff); border-color: var(--hq-border, #e2e8f0); }
.hq-tpl-live .msp-seg button { color: var(--hq-muted, #64748b); }
.hq-tpl-live .msp-seg button.is-on { background: var(--hq-text, #0f172a); color: var(--hq-surface, #fff); }
</style>

<div class="hq-templates hq-templates--detail">
  <p class="hq-templates__crumb">
    <a href="/hq/templates">Sections</a>
    <span>/</span>
    <span><?= htmlspecialchars($name) ?></span>
  </p>

  <header class="hq-templates__head">
    <div>
      <h1 class="hq-page-title"><?= htmlspecialchars($name) ?></h1>
      <?php if ($desc !== ''): ?>
        <p class="hq-templates__intro"><?= htmlspecialchars($desc) ?></p>
      <?php endif; ?>
      <p class="hq-templates__meta-line">
        <span class="hq-tpl-card__cat"><?= htmlspecialchars((string) ($meta['category'] ?? '')) ?></span>
        <span class="hq-tpl-card__source"><?= htmlspecialchars($source) ?></span>
        <code><?= htmlspecialchars($id) ?></code>
      </p>
    </div>
    <div class="hq-templates__head-actions">
      <button type="button" class="btn btn-primary" data-mova-ai-use-template="<?= htmlspecialchars($id) ?>">
        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Use in AI
      </button>
      <a class="btn btn-ghost" href="/hq/templates">Back to catalog</a>
    </div>
  </header>

  <div class="hq-templates__detail-grid">
    <section class="hq-templates__live">
      <h2 class="hq-templates__section-title">Live preview</h2>
      <div class="hq-tpl-live" data-w="desktop">
        <div class="hq-tpl-live__bar">
          <div class="msp-seg" role="group" aria-label="Width">
            <button type="button" class="is-on" data-w="desktop" aria-pressed="true">Desktop</button>
            <button type="button" data-w="tablet" aria-pressed="false">Tablet</button>
            <button type="button" data-w="mobile" aria-pressed="false">Mobile</button>
          </div>
          <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <a class="msp-btn msp-btn--ghost" href="/hq/assembly/new?section=<?= rawurlencode($id) ?>">Use in Assembly</a>
            <a class="msp-btn msp-btn--primary" href="/hq/templates?category=<?= rawurlencode((string) ($meta['category'] ?? '')) ?>">Insert into page…</a>
          </div>
        </div>
        <iframe title="<?= htmlspecialchars($name) ?> preview" src="/_preview/section/<?= rawurlencode($id) ?>" loading="lazy"></iframe>
      </div>
      <script>
      document.querySelectorAll('.hq-tpl-live [data-w]').forEach(function (b) {
        if (b.tagName !== 'BUTTON') return;
        b.addEventListener('click', function () {
          var box = b.closest('.hq-tpl-live'); box.setAttribute('data-w', b.dataset.w);
          box.querySelectorAll('button[data-w]').forEach(function (x) { x.classList.toggle('is-on', x === b); x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        });
      });
      </script>
    </section>

    <aside class="hq-templates__aside">
      <h2 class="hq-templates__section-title">Slots</h2>
      <?php if (is_array($slots) && $slots): ?>
        <ul class="hq-templates__slots">
          <?php foreach ($slots as $slot): ?>
            <li><code><?= htmlspecialchars((string) $slot) ?></code></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="hint">No slots listed.</p>
      <?php endif; ?>

      <h2 class="hq-templates__section-title">Constraints</h2>
      <?php if (is_array($constraints) && $constraints): ?>
        <ul class="hq-templates__constraints">
          <?php foreach ($constraints as $c): ?>
            <li><?= htmlspecialchars((string) $c) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <h2 class="hq-templates__section-title">Prompt hint</h2>
      <pre class="hq-templates__prompt-hint">template:<?= htmlspecialchars($id) ?>

Describe your content (e.g. gym plans, food menu).
Optional: featured tier, greenish feel, CTA labels.</pre>
    </aside>
  </div>
</div>

<script>
(function () {
  document.querySelectorAll('[data-mova-ai-use-template]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-mova-ai-use-template');
      if (!id) return;
      try {
        sessionStorage.setItem('mova_ai_template_id', id);
        sessionStorage.setItem('mova_ai_open_guide', '1');
      } catch (err) {}
      var fab = document.getElementById('mova-ai-fab');
      if (fab) fab.click();
      if (window.MovaAiPanel && typeof window.MovaAiPanel.useTemplate === 'function') {
        window.MovaAiPanel.useTemplate(id);
      }
    });
  });
})();
</script>
