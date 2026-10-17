<?php
$forms = $forms ?? [];
$newCount = (int) ($newCount ?? 0);
?>
<div class="toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
  <div>
    <h2 style="margin:0;font-size:1.15rem;">Feedback</h2>
    <p class="text-muted" style="margin:0.25rem 0 0;">Forms for the site · inbox for submissions</p>
  </div>
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
    <a class="btn-primary btn-sm" href="/hq/feedback/inbox">Inbox<?php if ($newCount): ?> (<?= $newCount ?> new)<?php endif; ?></a>
    <a class="btn-ghost btn-sm" href="/hq/feedback/forms/new">New form</a>
  </div>
</div>

<div class="table-wrap" style="margin-top:1rem;">
  <table class="table">
    <thead>
      <tr><th>Title</th><th>Slug</th><th>Status</th><th>Embed</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($forms as $f): ?>
      <tr>
        <td><strong><?= htmlspecialchars($f['title']) ?></strong></td>
        <td><code><?= htmlspecialchars($f['slug']) ?></code></td>
        <td><?= htmlspecialchars($f['status']) ?></td>
        <td><code style="font-size:0.75rem">[feedback form="<?= htmlspecialchars($f['slug']) ?>"]</code></td>
        <td style="white-space:nowrap">
          <a href="/hq/feedback/forms/edit/<?= (int)$f['id'] ?>">Edit</a>
          ·
          <a href="/hq/feedback/inbox?form=<?= (int)$f['id'] ?>">Entries</a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$forms): ?>
      <tr><td colspan="5" class="text-muted">No forms yet. The default Contact form will appear after first load.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
