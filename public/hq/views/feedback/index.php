<?php
$forms = $forms ?? [];
$newCount = (int) ($newCount ?? 0);
?>
<section class="hq-page fb-forms-page">
  <div class="toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
    <div>
      <h1 style="margin:0;font-size:1.25rem;">Feedback</h1>
      <p class="text-muted" style="margin:0.25rem 0 0;">Forms for the site · inbox for submissions</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <a class="btn-primary btn-sm" href="/hq/feedback/inbox">Inbox<?php if ($newCount): ?> (<?= $newCount ?> new)<?php endif; ?></a>
      <a class="btn-ghost btn-sm" href="/hq/feedback/forms/new">New form</a>
    </div>
  </div>

  <div class="fb-table-wrap">
    <table class="fb-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Slug</th>
          <th>Status</th>
          <th style="width:5.5rem">Embed</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($forms as $f):
        $slug = (string)($f['slug'] ?? '');
        $sc = '[feedback form="' . $slug . '"]';
        $html = '<div class="mova-feedback" data-form="' . htmlspecialchars($slug, ENT_QUOTES) . '"></div>';
      ?>
        <tr>
          <td><strong><?= htmlspecialchars($f['title']) ?></strong></td>
          <td><code class="fb-code"><?= htmlspecialchars($slug) ?></code></td>
          <td><span class="fb-status is-<?= htmlspecialchars($f['status'] ?? '') ?>"><?= htmlspecialchars($f['status'] ?? '') ?></span></td>
          <td>
            <div class="fb-embed-actions">
              <button type="button" class="fb-icon-btn" data-copy="<?= htmlspecialchars($sc, ENT_QUOTES) ?>" title="Copy shortcode" aria-label="Copy shortcode">
                <i class="fa-solid fa-code"></i>
              </button>
              <button type="button" class="fb-icon-btn" data-copy="<?= htmlspecialchars($html, ENT_QUOTES) ?>" title="Copy HTML" aria-label="Copy HTML">
                <i class="fa-solid fa-file-code"></i>
              </button>
            </div>
          </td>
          <td class="fb-row-actions">
            <a href="/hq/feedback/forms/edit/<?= (int)$f['id'] ?>">Edit</a>
            <a href="/hq/feedback/inbox?form=<?= (int)$f['id'] ?>">Entries</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$forms): ?>
        <tr><td colspan="5" class="text-muted">No forms yet. The default Contact form appears after first load.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<style>
.fb-table-wrap{margin-top:1rem;border:1px solid var(--hq-border,#e5e7eb);border-radius:12px;overflow:hidden;background:var(--hq-surface,#fff)}
.fb-table{width:100%;border-collapse:collapse;font-size:0.9rem}
.fb-table th{text-align:left;font-size:0.72rem;font-weight:650;text-transform:uppercase;letter-spacing:0.04em;color:#64748b;padding:0.7rem 1rem;background:#f8fafc;border-bottom:1px solid #eef2f7}
.fb-table td{padding:0.75rem 1rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.fb-table tr:last-child td{border-bottom:none}
.fb-table tbody tr:hover{background:#fafbfc}
.fb-code{font-size:0.8rem;background:#f1f5f9;padding:0.15rem 0.4rem;border-radius:4px}
.fb-status{font-size:0.75rem;font-weight:600;padding:0.15rem 0.5rem;border-radius:999px;background:#f1f5f9;color:#475569}
.fb-status.is-published{background:#ecfdf5;color:#059669}
.fb-status.is-draft{background:#f8fafc;color:#94a3b8}
.fb-embed-actions{display:flex;gap:0.25rem}
.fb-icon-btn{width:2rem;height:2rem;border:1px solid #e2e8f0;border-radius:8px;background:#fff;color:#475569;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
.fb-icon-btn:hover{border-color:#94a3b8;color:#0f172a;background:#f8fafc}
.fb-icon-btn.is-ok{border-color:#6ee7b7;color:#059669}
.fb-row-actions{text-align:right;white-space:nowrap}
.fb-row-actions a{font-size:0.85rem;margin-left:0.75rem;color:var(--hq-accent,#0f766e);text-decoration:none}
.fb-row-actions a:hover{text-decoration:underline}
</style>
<script>
document.querySelectorAll('.fb-icon-btn[data-copy]').forEach(function(btn){
  btn.addEventListener('click', function(){
    navigator.clipboard.writeText(btn.getAttribute('data-copy')||'').then(function(){
      btn.classList.add('is-ok');
      setTimeout(function(){ btn.classList.remove('is-ok'); }, 900);
    });
  });
});
</script>
