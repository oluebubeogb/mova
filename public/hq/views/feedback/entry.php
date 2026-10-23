<?php
use Mova\Security\Csrf;
$entry = $entry ?? null;
if (!$entry) { echo '<p>Not found</p>'; return; }
?>
<div class="toolbar">
  <a href="/hq/feedback/inbox" class="btn-ghost btn-sm">← Inbox</a>
</div>
<article style="max-width:40rem;margin-top:1rem;background:var(--hq-surface,#fff);border:1px solid var(--hq-border,#e5e7eb);border-radius:10px;padding:1.25rem;">
  <p class="text-muted" style="margin:0 0 0.5rem;font-size:0.85rem">
    <?= htmlspecialchars($entry['form_title'] ?? '') ?>
    · <?= hqTimeTag($entry['created_at'] ?? null, 'both') ?>
    · <?= htmlspecialchars($entry['status'] ?? '') ?>
  </p>
  <?php if (!empty($entry['page_url'])): ?>
    <p style="font-size:0.85rem"><a href="<?= htmlspecialchars($entry['page_url']) ?>" target="_blank" rel="noopener">Page URL</a></p>
  <?php endif; ?>
  <dl style="margin:1rem 0 0">
    <?php foreach (($entry['payload'] ?? []) as $k => $v): ?>
      <dt style="font-size:0.75rem;font-weight:600;color:var(--hq-muted,#64748b);margin-top:0.75rem"><?= htmlspecialchars((string)$k) ?></dt>
      <dd style="margin:0.2rem 0 0;white-space:pre-wrap"><?= htmlspecialchars(is_array($v) ? implode(', ', $v) : (string)$v) ?></dd>
    <?php endforeach; ?>
  </dl>
  <form method="post" action="/hq/feedback/entry/delete/<?= (int)$entry['id'] ?>" style="margin-top:1.5rem" onsubmit="return confirm('Delete this entry?');">
    <?= Csrf::field() ?>
    <button type="submit" class="btn-danger btn-sm">Delete</button>
  </form>
</article>
