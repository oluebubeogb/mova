<?php
/** @var list<array> $templates */
/** @var array<string,int> $categories */
/** @var string $activeCategory */
/** @var string $sharedCss */
$templates = $templates ?? [];
$categories = $categories ?? [];
$activeCategory = $activeCategory ?? '';
?>
<link rel="stylesheet" href="/assets/css/hq-templates.css?v=20260929">

<div class="hq-templates">
  <header class="hq-templates__head">
    <div>
      <h1 class="hq-page-title">Design templates</h1>
      <p class="hq-templates__intro">
        Sleek, var-based layouts shipped with Mova. Open one in AI to adapt copy, prices, and domain —
        structure stays locked. Shared styles load as <code><?= htmlspecialchars($sharedCss ?? '/assets/css/templates.css') ?></code>.
      </p>
    </div>
    <a class="btn btn-primary" href="#" id="hq-templates-open-ai" data-mova-ai-open="1">
      <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Open Mova AI
    </a>
  </header>

  <?php if (!empty($_GET['use'])): ?>
    <div class="alert alert-success">
      Template <strong><?= htmlspecialchars((string) $_GET['use']) ?></strong> selected for AI.
      Open Mova AI (Ctrl+J) and describe what you need — or use the guided steps in the panel.
    </div>
  <?php endif; ?>

  <div class="hq-templates__filters" role="tablist">
    <a class="hq-templates__filter<?= $activeCategory === '' ? ' is-active' : '' ?>" href="/hq/templates">All</a>
    <?php foreach ($categories as $cat => $count): ?>
      <a class="hq-templates__filter<?= $activeCategory === $cat ? ' is-active' : '' ?>"
         href="/hq/templates?category=<?= rawurlencode($cat) ?>">
        <?= htmlspecialchars(ucfirst($cat)) ?>
        <span class="hq-templates__count"><?= (int) $count ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (!$templates): ?>
    <div class="hq-templates__empty">
      <p>No templates found. Seeds live in <code>resources/templates/</code>.</p>
    </div>
  <?php else: ?>
    <div class="hq-templates__grid">
      <?php foreach ($templates as $t): ?>
        <?php
          $id = (string) ($t['id'] ?? '');
          $name = (string) ($t['name'] ?? $id);
          $desc = (string) ($t['description'] ?? '');
          $preview = (string) ($t['preview'] ?? '');
          $cat = (string) ($t['category'] ?? '');
          $source = (string) ($t['source'] ?? 'seed');
        ?>
        <article class="hq-tpl-card">
          <a class="hq-tpl-card__preview" href="/hq/templates/<?= rawurlencode($id) ?>">
            <?php if ($preview !== ''): ?>
              <img src="<?= htmlspecialchars($preview) ?>" alt="" width="480" height="280" loading="lazy">
            <?php else: ?>
              <div class="hq-tpl-card__placeholder"><?= htmlspecialchars($name) ?></div>
            <?php endif; ?>
          </a>
          <div class="hq-tpl-card__body">
            <div class="hq-tpl-card__meta">
              <span class="hq-tpl-card__cat"><?= htmlspecialchars($cat) ?></span>
              <span class="hq-tpl-card__source"><?= htmlspecialchars($source) ?></span>
            </div>
            <h2 class="hq-tpl-card__title">
              <a href="/hq/templates/<?= rawurlencode($id) ?>"><?= htmlspecialchars($name) ?></a>
            </h2>
            <?php if ($desc !== ''): ?>
              <p class="hq-tpl-card__desc"><?= htmlspecialchars($desc) ?></p>
            <?php endif; ?>
            <div class="hq-tpl-card__actions">
              <a class="btn btn-sm btn-primary" href="/hq/templates?use=<?= rawurlencode($id) ?>#mova-ai"
                 data-template-id="<?= htmlspecialchars($id) ?>"
                 data-mova-ai-use-template="<?= htmlspecialchars($id) ?>">
                Use in AI
              </a>
              <a class="btn btn-sm btn-ghost" href="/hq/templates/<?= rawurlencode($id) ?>">Preview</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  document.querySelectorAll('[data-mova-ai-use-template]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      var id = btn.getAttribute('data-mova-ai-use-template');
      if (!id) return;
      e.preventDefault();
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
  var openAi = document.getElementById('hq-templates-open-ai');
  if (openAi) {
    openAi.addEventListener('click', function (e) {
      e.preventDefault();
      var fab = document.getElementById('mova-ai-fab');
      if (fab) fab.click();
    });
  }
  var params = new URLSearchParams(window.location.search);
  var use = params.get('use');
  if (use) {
    try {
      sessionStorage.setItem('mova_ai_template_id', use);
      sessionStorage.setItem('mova_ai_open_guide', '1');
    } catch (err) {}
  }
})();
</script>
