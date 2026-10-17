<?php
$entries = $entries ?? [];
$forms = $forms ?? [];
$formId = (int) ($formId ?? 0);
$newCount = (int) ($newCount ?? 0);
?>
<div class="toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
  <div>
    <h2 style="margin:0;font-size:1.15rem;">Feedback inbox</h2>
    <p class="text-muted" style="margin:0.25rem 0 0;"><?= $newCount ?> unread</p>
  </div>
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
    <a class="btn-ghost btn-sm" href="/hq/feedback">Forms</a>
    <a class="btn-ghost btn-sm" href="/hq/feedback/export<?= $formId ? ('?form=' . $formId) : '' ?>">Export CSV</a>
  </div>
</div>

<form method="get" action="/hq/feedback/inbox" style="margin:1rem 0;display:flex;gap:0.5rem;align-items:center;">
  <label>Form
    <select name="form" onchange="this.form.submit()">
      <option value="0">All forms</option>
      <?php foreach ($forms as $f): ?>
        <option value="<?= (int)$f['id'] ?>" <?= $formId === (int)$f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['title']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
</form>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr><th></th><th>When</th><th>Form</th><th>Preview</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($entries as $e): ?>
      <?php
        $preview = '';
        foreach (($e['payload'] ?? []) as $k => $v) {
          if (is_array($v)) $v = implode(', ', $v);
          $preview = mb_substr((string)$v, 0, 80);
          if ($preview !== '') break;
        }
      ?>
      <tr style="<?= ($e['status'] ?? '') === 'new' ? 'font-weight:600' : '' ?>">
        <td><?= ($e['status'] ?? '') === 'new' ? '●' : '' ?></td>
        <td style="white-space:nowrap;font-size:0.85rem"><?= htmlspecialchars($e['created_at'] ?? '') ?></td>
        <td><?= htmlspecialchars($e['form_title'] ?? '') ?></td>
        <td class="text-muted"><?= htmlspecialchars($preview) ?></td>
        <td><a href="/hq/feedback/entry/<?= (int)$e['id'] ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$entries): ?>
      <tr><td colspan="5" class="text-muted">No submissions yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
