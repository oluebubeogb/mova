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
<link rel="stylesheet" href="<?= htmlspecialchars($sharedCss) ?>?v=20260929">
<?php if ($styles !== ''): ?>
<style><?= $styles /* seed CSS only; admin-authored */ ?></style>
<?php endif; ?>

<div class="hq-templates hq-templates--detail">
  <p class="hq-templates__crumb">
    <a href="/hq/templates">Templates</a>
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
      <div class="hq-templates__stage">
        <?= $body /* seed HTML fragment */ ?>
      </div>
      <?php if ($preview !== ''): ?>
        <p class="hq-templates__static-note">Static thumbnail (for catalog cards):</p>
        <img class="hq-templates__static-img" src="<?= htmlspecialchars($preview) ?>" alt="" width="480" height="280">
      <?php endif; ?>
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
