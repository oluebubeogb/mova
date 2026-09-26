<?php use Mova\Security\Csrf; $cfg = $cfg ?? []; $sources = $sources ?? []; $palette = $palette ?? []; ?>
<div class="hq-page">
  <h1>Site AI Engine</h1>
  <p class="muted">Public assistant for visitors on your live site — separate from HQ Mova AI. Enable the <strong>Mova Site AI</strong> plugin, then configure below.</p>

  <form method="post" class="card" style="padding:1.25rem;margin-bottom:1.5rem;">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save">
    <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
      <input type="checkbox" name="enabled" value="1" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>>
      Enable on public site
    </label>
    <div class="form-group">
      <label>AI display name</label>
      <input type="text" name="name" class="input" value="<?= htmlspecialchars($cfg['name'] ?? 'Site Assistant') ?>" placeholder="e.g. Shop Helper">
    </div>
    <div class="form-group">
      <label>Optional welcome (shown under greeting)</label>
      <input type="text" name="welcome" class="input" value="<?= htmlspecialchars($cfg['welcome'] ?? '') ?>">
    </div>
    <div class="form-row" style="display:flex;gap:1rem;flex-wrap:wrap;">
      <div class="form-group">
        <label>Button primary (blank = site palette)</label>
        <input type="text" name="primary" class="input" placeholder="<?= htmlspecialchars($palette['primary'] ?? '#2563eb') ?>" value="<?= htmlspecialchars($cfg['primary'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Accent</label>
        <input type="text" name="accent" class="input" placeholder="<?= htmlspecialchars($palette['accent'] ?? '#7c3aed') ?>" value="<?= htmlspecialchars($cfg['accent'] ?? '') ?>">
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Save</button>
  </form>

  <h2>Knowledge bank</h2>
  <p class="muted">Index text, URLs, and files (txt, md, html, csv, json, docx, pdf best-effort). The assistant uses this on the public site only.</p>

  <form method="post" class="card" style="padding:1rem;margin-bottom:1rem;">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="add_text">
    <div class="form-group">
      <label>Title</label>
      <input type="text" name="source_title" class="input" placeholder="Store hours">
    </div>
    <div class="form-group">
      <label>Text</label>
      <textarea name="source_text" class="input" rows="4" placeholder="Paste policies, FAQs, stock notes…"></textarea>
    </div>
    <button type="submit" class="btn">Add text</button>
  </form>

  <form method="post" class="card" style="padding:1rem;margin-bottom:1rem;">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="add_url">
    <div class="form-group">
      <label>Live URL</label>
      <input type="url" name="source_url" class="input" placeholder="https://…">
    </div>
    <button type="submit" class="btn">Fetch &amp; index URL</button>
  </form>

  <form method="post" enctype="multipart/form-data" class="card" style="padding:1rem;margin-bottom:1.5rem;">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="upload">
    <div class="form-group">
      <label>Upload file</label>
      <input type="file" name="source_file" accept=".txt,.md,.html,.htm,.csv,.json,.xml,.docx,.pdf">
    </div>
    <button type="submit" class="btn">Upload &amp; index</button>
  </form>

  <table class="table">
    <thead><tr><th>Title</th><th>Type</th><th>Updated</th><th></th></tr></thead>
    <tbody>
    <?php if (!$sources): ?>
      <tr><td colspan="4" class="muted">No sources yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($sources as $s): ?>
      <tr>
        <td><?= htmlspecialchars($s['title'] ?? '') ?></td>
        <td><?= htmlspecialchars($s['type'] ?? '') ?></td>
        <td><?= htmlspecialchars(substr((string)($s['updated_at'] ?? ''), 0, 16)) ?></td>
        <td>
          <form method="post" style="display:inline" onsubmit="return confirm('Remove this source?');">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="source_id" value="<?= (int)$s['id'] ?>">
            <button type="submit" class="btn btn-sm">Clear</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
