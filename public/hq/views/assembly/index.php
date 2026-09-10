<?php
/** Assembly library */
use Mova\Security\Csrf;
$items = $items ?? [];
$status = $status ?? 'all';
$q = $q ?? '';
?>
<style>
.asm-lib-actions .btn-primary {
  font-weight: 700;
  text-decoration: none !important;
  white-space: nowrap;
}
.asm-lib-filter input,
.asm-lib-filter select {
  border: 1px solid var(--hq-border);
  border-radius: var(--hq-radius, 8px);
  background: var(--hq-input-bg);
  color: var(--hq-text);
  padding: 0.5rem 0.75rem;
  font: inherit;
}
.asm-lib-filter input:focus,
.asm-lib-filter select:focus {
  outline: none;
  border-color: var(--hq-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--hq-accent) 22%, transparent);
}
</style>
<section class="hq-landing">
  <div class="hq-landing-bg" aria-hidden="true">
    <div class="hq-landing-lines"><span></span><span></span><span></span></div>
    <span class="hq-landing-glyph">{ }</span>
    <span class="hq-landing-glyph">&lt;/&gt;</span>
  </div>

  <header class="hq-landing-header asm-lib-actions" style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:1rem;">
    <div>
      <p class="hq-landing-kicker"><i class="fa-solid fa-cubes"></i> Assembly</p>
      <h1 class="hq-landing-title">Assembly</h1>
      <p class="hq-landing-desc">Build HTML/CSS components once, embed them anywhere with a shortcode, edit once to update every page.</p>
    </div>
    <a class="btn-primary" href="/hq/assembly/new"><i class="fa-solid fa-plus"></i> New assembly</a>
  </header>

  <form method="get" action="/hq/assembly" class="asm-lib-filter" style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.25rem;position:relative;z-index:1;">
    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search name or slug…" style="flex:1;min-width:12rem;">
    <select name="status">
      <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
      <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
      <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
      <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
    </select>
    <button type="submit" class="btn-ghost">Filter</button>
  </form>

  <?php if (empty($items)): ?>
    <div class="empty-state" style="position:relative;z-index:1;padding:2rem;border:1px dashed var(--hq-border);border-radius:12px;text-align:center;color:var(--hq-muted);">
      <p style="margin:0 0 0.5rem;"><strong>No assemblies yet</strong></p>
      <p style="margin:0 0 1rem;">Create a table, callout, or section — then embed it with <code>[assembly slug="…"]</code>.</p>
      <a class="btn-primary" href="/hq/assembly/new" style="font-weight:700;text-decoration:none;">Create your first assembly</a>
    </div>
  <?php else: ?>
    <div class="hq-landing-grid" style="position:relative;z-index:1;">
      <?php foreach ($items as $it): ?>
        <a class="hq-landing-card" href="/hq/assembly/edit/<?= (int) $it['id'] ?>">
          <div class="hq-landing-card-inner">
            <div class="hq-landing-icon"><i class="fa-solid fa-cube"></i></div>
            <div class="hq-landing-card-body">
              <strong><?= htmlspecialchars($it['name']) ?></strong>
              <span>
                <code style="font-size:0.78rem;"><?= htmlspecialchars($it['slug']) ?></code>
                · <?= htmlspecialchars($it['status']) ?>
                · used <?= (int) ($it['usage'] ?? 0) ?>
              </span>
            </div>
          </div>
          <i class="fa-solid fa-arrow-right hq-landing-arrow" aria-hidden="true"></i>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
