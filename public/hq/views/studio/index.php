<?php
/**
 * Studio — content picker / list
 */
use Mova\Security\Csrf;

$items  = $items ?? [];
$q      = $q ?? '';
$status = $status ?? '';
?>

<section class="studio-picker">
  <header class="studio-picker-header">
    <div>
      <p class="studio-kicker"><i class="fa-solid fa-wand-magic-sparkles"></i> Studio</p>
      <h1 class="studio-title">Open in Studio</h1>
      <p class="studio-desc">Structure, style, and code in one resizable workspace. Choose content below or start blank.</p>
    </div>
    <form method="post" action="/hq/studio/new" class="studio-new-form">
      <?= Csrf::field() ?>
      <input type="text" name="title" class="input" placeholder="New page title" value="Untitled" required>
      <button type="submit" class="btn-primary"><i class="fa-solid fa-plus"></i> New blank</button>
    </form>
  </header>

  <form method="get" action="/hq/studio" class="studio-filters">
    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search content…" class="input">
    <select name="status" class="input">
      <option value="all" <?= $status === '' || $status === 'all' ? 'selected' : '' ?>>All statuses</option>
      <?php foreach (['draft', 'review', 'approved', 'scheduled', 'published', 'archived'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-secondary">Filter</button>
  </form>

  <?php if (empty($items)): ?>
    <div class="studio-empty">
      <i class="fa-solid fa-folder-open"></i>
      <p>No content found. Create a blank page or add content from the Content hub first.</p>
      <a href="/hq/content/new" class="btn-secondary">Go to Content</a>
    </div>
  <?php else: ?>
    <div class="studio-list">
      <?php foreach ($items as $it): ?>
        <?php
          $mode = $it['editor_mode'] ?? 'visual';
          $modeLabel = $mode === 'studio' ? 'Studio' : ($mode === 'dev' ? 'Dev' : 'Visual');
        ?>
        <a class="studio-list-item" href="/hq/studio/<?= (int) $it['id'] ?>">
          <div class="studio-list-main">
            <strong><?= htmlspecialchars($it['title'] ?? 'Untitled') ?></strong>
            <span class="studio-list-meta">
              <span class="studio-badge studio-badge-<?= htmlspecialchars($it['status'] ?? 'draft') ?>"><?= htmlspecialchars($it['status'] ?? 'draft') ?></span>
              <span class="studio-badge studio-badge-mode"><?= htmlspecialchars($modeLabel) ?></span>
              <?php if (!empty($it['slug'])): ?>
                <code>/<?= htmlspecialchars($it['slug']) ?></code>
              <?php endif; ?>
            </span>
          </div>
          <i class="fa-solid fa-arrow-right"></i>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<link rel="stylesheet" href="/assets/css/studio.css">
