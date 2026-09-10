<?php
use Mova\Security\Csrf;
$accounts = $accounts ?? []; $account = $account ?? null; $aid = (int)($account['id'] ?? 0);
$folders = $folders ?? []; $messages = $messages ?? []; $folder = $folder ?? 'INBOX';
$page = $page ?? 1; $q = $q ?? ''; $tab = $tab ?? 'inbox'; $total = $total ?? 0;
$message = $message ?? null; $compose = !empty($compose); $reply = $reply ?? null;
$contacts = $contacts ?? []; $filters = $filters ?? [];
$error = $error ?? ($_GET['error'] ?? null); $imap_ok = $imap_ok ?? false;
$editAccount = null;
if ($tab === 'accounts' && $aid && empty($_GET['new'])) {
    foreach ($accounts as $a) { if ((int)$a['id'] === $aid) { $editAccount = $a; break; } }
}
if (!empty($_GET['new'])) {
    $editAccount = null;
}
?>
<?php if (!empty($_GET['sent'])): ?><div class="alert alert-success">Message sent.</div><?php endif; ?>
<?php if (!empty($_GET['draft_saved'])): ?><div class="alert alert-success">Draft saved.</div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars((string)$error) ?></div><?php endif; ?>

<div class="toolbar mailbox-top">
  <div class="mailbox-account-switcher" id="mailbox-account-switcher">
    <?php if ($accounts && $account):
      $curName = $account['display_name'] ?: ($account['label'] ?? $account['email']);
      $curEmail = $account['email'] ?? '';
      $curInitial = strtoupper(substr($curName ?: $curEmail, 0, 1));
    ?>
      <button type="button" class="mailbox-switcher-btn" id="mailbox-switcher-btn" aria-haspopup="listbox" aria-expanded="false">
        <?php if (!empty($account['avatar'])): ?>
          <img class="mailbox-avatar" src="<?= htmlspecialchars($account['avatar']) ?>" alt="">
        <?php else: ?>
          <span class="mailbox-avatar mailbox-avatar-fallback"><?= htmlspecialchars($curInitial) ?></span>
        <?php endif; ?>
        <span class="mailbox-switcher-text">
          <span class="name"><?= htmlspecialchars($curName) ?></span>
          <span class="email"><?= htmlspecialchars($curEmail) ?></span>
        </span>
        <i class="fa-solid fa-chevron-down chev" aria-hidden="true"></i>
      </button>
      <div class="mailbox-switcher-menu" id="mailbox-switcher-menu" hidden role="listbox">
        <?php foreach ($accounts as $a):
          $an = $a['display_name'] ?: ($a['label'] ?? $a['email']);
          $ae = $a['email'] ?? '';
          $ai = strtoupper(substr($an ?: $ae, 0, 1));
          $active = $aid === (int)$a['id'];
        ?>
          <a class="mailbox-switcher-item<?= $active ? ' is-active' : '' ?>"
             href="/hq/mailbox?account=<?= (int)$a['id'] ?>&tab=<?= urlencode($tab) ?>"
             role="option" <?= $active ? 'aria-selected="true"' : '' ?>>
            <?php if (!empty($a['avatar'])): ?>
              <img class="mailbox-avatar" src="<?= htmlspecialchars($a['avatar']) ?>" alt="">
            <?php else: ?>
              <span class="mailbox-avatar mailbox-avatar-fallback"><?= htmlspecialchars($ai) ?></span>
            <?php endif; ?>
            <span class="mailbox-switcher-text">
              <span class="name"><?= htmlspecialchars($an) ?><?= !empty($a['is_default']) ? ' · default' : '' ?></span>
              <span class="email"><?= htmlspecialchars($ae) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php elseif ($accounts): ?>
      <span style="color:var(--hq-muted);font-size:0.85rem">Select an account</span>
    <?php else: ?>
      <span style="color:var(--hq-muted);font-size:0.85rem">No accounts</span>
    <?php endif; ?>
  </div>
  <!-- Tabs moved to Line 2 dynamic nav -->
</div>

<?php if ($tab === 'accounts'): ?>
<div class="form-layout">
  <div class="panel">
    <h3 style="margin-top:0"><?= $editAccount ? 'Edit account' : 'Add mail account' ?></h3>
    <p style="font-size:0.85rem;color:var(--hq-muted)">Switch accounts from the top dropdown (like Gmail). Leave password blank on edit to keep current.</p>
    <form method="post" action="/hq/mailbox/account/save">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="<?= (int)($editAccount['id'] ?? 0) ?>">

      <div class="form-section">
        <h4 class="form-section-title">Identity</h4>
        <div class="form-grid">
          <div class="form-group"><label>Label</label><input name="label" required value="<?= htmlspecialchars($editAccount['label'] ?? 'Work') ?>"></div>
          <div class="form-group"><label>Email</label><input type="email" name="email" required value="<?= htmlspecialchars($editAccount['email'] ?? '') ?>"></div>
          <div class="form-group"><label>Display name</label><input name="display_name" value="<?= htmlspecialchars($editAccount['display_name'] ?? '') ?>"></div>
          <div class="form-group"><label>Profile image URL</label><input name="avatar" value="<?= htmlspecialchars($editAccount['avatar'] ?? '') ?>" placeholder="/mova-uploads/… or https://…"></div>
        </div>
      </div>

      <div class="form-section">
        <h4 class="form-section-title">IMAP (read)</h4>
        <div class="form-grid">
          <div class="form-group"><label>Host</label><input name="imap_host" required value="<?= htmlspecialchars($editAccount['imap_host'] ?? '') ?>"></div>
          <div class="form-group"><label>Port</label><input type="number" name="imap_port" value="<?= (int)($editAccount['imap_port'] ?? 993) ?>"></div>
          <div class="form-group"><label>Encryption</label><select name="imap_encryption"><?php foreach (['ssl'=>'SSL','tls'=>'TLS','none'=>'None'] as $k=>$l): ?><option value="<?= $k ?>" <?= ($editAccount['imap_encryption'] ?? 'ssl')===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label>Username</label><input name="imap_username" required value="<?= htmlspecialchars($editAccount['imap_username'] ?? '') ?>" autocomplete="off"></div>
          <div class="form-group span-2">
            <label>Password</label>
            <div class="password-field">
              <input type="password" name="imap_password" value="" autocomplete="new-password" placeholder="<?= $editAccount ? '••••••••' : '' ?>">
              <button type="button" class="password-toggle" aria-label="Show password"><i class="fa-solid fa-eye"></i><i class="fa-solid fa-eye-slash"></i></button>
            </div>
          </div>
        </div>
      </div>

      <div class="form-section">
        <h4 class="form-section-title">SMTP (send)</h4>
        <div class="form-grid">
          <div class="form-group"><label>Host</label><input name="smtp_host" value="<?= htmlspecialchars($editAccount['smtp_host'] ?? '') ?>"></div>
          <div class="form-group"><label>Port</label><input type="number" name="smtp_port" value="<?= (int)($editAccount['smtp_port'] ?? 587) ?>"></div>
          <div class="form-group"><label>Encryption</label><select name="smtp_encryption"><?php foreach (['tls'=>'TLS','ssl'=>'SSL','none'=>'None'] as $k=>$l): ?><option value="<?= $k ?>" <?= ($editAccount['smtp_encryption'] ?? 'tls')===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label>SMTP username</label><input name="smtp_username" value="<?= htmlspecialchars($editAccount['smtp_username'] ?? '') ?>" autocomplete="off"></div>
          <div class="form-group span-2">
            <label>SMTP password</label>
            <div class="password-field">
              <input type="password" name="smtp_password" value="" autocomplete="new-password" placeholder="Blank = IMAP password">
              <button type="button" class="password-toggle" aria-label="Show password"><i class="fa-solid fa-eye"></i><i class="fa-solid fa-eye-slash"></i></button>
            </div>
          </div>
        </div>
      </div>

      <div class="form-group"><label><input type="checkbox" name="is_default" value="1" <?= !empty($editAccount['is_default'])?'checked':'' ?>> Default account</label></div>
      <button class="btn-primary" type="submit">Save account</button>
    </form>
  </div>
  <div class="panel">
    <div class="mailbox-accounts-head">
      <h3 style="margin:0">Your accounts</h3>
      <a class="btn-primary btn-sm" href="/hq/mailbox?tab=accounts&new=1">+ Add account</a>
    </div>
    <?php foreach ($accounts as $a):
      $an = $a['display_name'] ?: ($a['label'] ?? $a['email']);
      $ai = strtoupper(substr($an ?: $a['email'], 0, 1));
    ?>
      <div class="mailbox-account-card">
        <div class="mailbox-account-main">
          <?php if (!empty($a['avatar'])): ?>
            <img class="mailbox-avatar" src="<?= htmlspecialchars($a['avatar']) ?>" alt="">
          <?php else: ?>
            <span class="mailbox-avatar mailbox-avatar-fallback"><?= htmlspecialchars($ai) ?></span>
          <?php endif; ?>
          <div class="mailbox-account-meta">
            <strong><?= htmlspecialchars($an) ?><?= !empty($a['is_default']) ? ' · default' : '' ?></strong>
            <div class="email"><?= htmlspecialchars($a['email']) ?></div>
          </div>
        </div>
        <div class="mailbox-account-actions">
          <a href="/hq/mailbox?account=<?= (int)$a['id'] ?>">open</a>
          <span class="sep">|</span>
          <a href="/hq/mailbox?account=<?= (int)$a['id'] ?>&tab=accounts">edit</a>
          <span class="sep">|</span>
          <form method="post" action="/hq/mailbox/account/delete" onsubmit="return confirm('Remove account from Mova?');"><?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <button type="submit" class="linkish">remove</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$accounts): ?><p class="empty">No accounts yet.</p><?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'contacts'): ?>
<div class="form-layout">
  <div class="panel">
    <h3 style="margin-top:0">Address book</h3>
    <?php if (!$contacts): ?><p class="empty">No contacts.</p><?php else: ?>
    <table class="data-table"><thead><tr><th>Name</th><th>Email</th><th></th></tr></thead><tbody>
    <?php foreach ($contacts as $c): ?>
      <tr><td><?= htmlspecialchars($c['name']??'') ?></td>
        <td><a href="/hq/mailbox?account=<?= $aid ?>&compose=1&to=<?= urlencode($c['email']) ?>"><?= htmlspecialchars($c['email']) ?></a></td>
        <td><form method="post" action="/hq/mailbox/contact/delete"><?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="account_id" value="<?= $aid ?>">
          <button class="btn-danger btn-sm" type="submit">Delete</button></form></td></tr>
    <?php endforeach; ?></tbody></table><?php endif; ?>
  </div>
  <div class="panel">
    <h3 style="margin-top:0">Add contact</h3>
    <form method="post" action="/hq/mailbox/contact/save"><?= Csrf::field() ?>
      <input type="hidden" name="account_id" value="<?= $aid ?>">
      <div class="form-group"><label>Name</label><input name="name"></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
      <div class="form-group"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
      <button class="btn-primary" type="submit">Save</button>
    </form>
  </div>
</div>

<?php elseif ($tab === 'filters'): ?>
<div class="form-layout">
  <div class="panel">
    <h3 style="margin-top:0">Filters</h3>
    <?php foreach ($filters as $f): ?>
      <div style="padding:0.5rem 0;border-bottom:1px solid var(--hq-border);display:flex;justify-content:space-between">
        <span><strong><?= htmlspecialchars($f['name']) ?></strong> — <?= htmlspecialchars($f['match_field'].' '.$f['match_op'].' '.$f['match_value']) ?> → <?= htmlspecialchars($f['action']) ?></span>
        <form method="post" action="/hq/mailbox/filter/delete"><?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><input type="hidden" name="account_id" value="<?= $aid ?>">
          <button class="btn-danger btn-sm" type="submit">Delete</button></form>
      </div>
    <?php endforeach; ?>
    <?php if (!$filters): ?><p class="empty">No filters.</p><?php endif; ?>
  </div>
  <div class="panel">
    <h3 style="margin-top:0">New filter</h3>
    <form method="post" action="/hq/mailbox/filter/save"><?= Csrf::field() ?>
      <input type="hidden" name="account_id" value="<?= $aid ?>">
      <div class="form-group"><label>Name</label><input name="name" required></div>
      <div class="form-group"><label>Field</label><select name="match_field"><option value="from">From</option><option value="subject">Subject</option></select></div>
      <div class="form-group"><label>Match</label><select name="match_op"><option value="contains">Contains</option><option value="equals">Equals</option><option value="starts">Starts with</option></select></div>
      <div class="form-group"><label>Value</label><input name="match_value" required></div>
      <div class="form-group"><label>Action</label><select name="action"><option value="flag">Flag</option><option value="label">Label</option></select></div>
      <div class="form-group"><label>Label text</label><input name="action_value"></div>
      <button class="btn-primary" type="submit">Save filter</button>
    </form>
  </div>
</div>

<?php elseif (!$account): ?>
<div class="panel"><p>Add an account under <a href="/hq/mailbox?tab=accounts">Accounts</a>.</p></div>
<?php else: ?>
<div class="mailbox-layout<?= (!$compose && empty($message)) ? ' is-list-only' : '' ?>">
  <section class="mailbox-list panel">
    <form class="mailbox-search" method="get" action="/hq/mailbox">
      <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
      <input type="hidden" name="account" value="<?= $aid ?>"><input type="hidden" name="folder" value="<?= htmlspecialchars($folder) ?>">
      <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search…">
    </form>
    <?php foreach ($messages as $m):
      $rawFrom = (string)($m['from'] ?? '');
      $fromName = $rawFrom;
      if (preg_match('/^\s*"?([^"<]+)"?\s*<[^>]+>/', $rawFrom, $fm)) {
        $fromName = trim($fm[1]);
      } elseif (preg_match('/<([^>]+)>/', $rawFrom, $fm)) {
        $fromName = trim($fm[1]);
      }
      $excerpt = trim((string)($m['excerpt'] ?? $m['snippet'] ?? ''));
      if ($excerpt === '' && !empty($m['size'])) {
        $excerpt = '';
      }
    ?>
      <a class="mailbox-row <?= empty($m['seen'])?'is-unread':'' ?>" href="/hq/mailbox?account=<?= $aid ?>&folder=<?= urlencode($folder) ?>&uid=<?= (int)$m['uid'] ?>&q=<?= urlencode($q) ?>">
        <span class="mailbox-line">
          <span class="mailbox-from"><?= htmlspecialchars($fromName) ?></span>
          <span class="mailbox-sep">·</span>
          <span class="mailbox-subject"><?= htmlspecialchars($m['subject'] ?? '(no subject)') ?></span>
          <?php if ($excerpt !== ''): ?>
            <span class="mailbox-sep">—</span>
            <span class="mailbox-excerpt"><?= htmlspecialchars($excerpt) ?></span>
          <?php endif; ?>
          <?php if (!empty($m['label'])): ?> <span class="badge"><?= htmlspecialchars($m['label']) ?></span><?php endif; ?>
        </span>
        <span class="mailbox-date"><?= htmlspecialchars($m['date'] ?? '') ?></span>
      </a>
    <?php endforeach; ?>
    <?php if (!$messages): ?><p class="empty">No messages.</p><?php endif; ?>
  </section>
  <section class="mailbox-detail panel">
    <?php if ($compose):
      $isDraftEdit = !empty($reply['is_draft_edit']);
      $composeTo = $reply['reply_to'] ?? ($_GET['to'] ?? '');
      if ($isDraftEdit && !empty($reply['to'])) {
        $composeTo = $reply['to'];
      }
      $composeCc = $reply['cc'] ?? '';
      if ($isDraftEdit) {
        $composeSubject = $reply['subject'] ?? '';
        $composeBody = $reply['body_text'] ?? strip_tags($reply['body_html'] ?? '');
      } elseif (!empty($reply) && empty($reply['is_draft_edit'])) {
        $sub = $reply['subject'] ?? '';
        $composeSubject = (str_starts_with(strtolower($sub), 're:') ? $sub : 'Re: ' . $sub);
        $composeBody = \Mova\Mail\MailboxService::formatReplyQuote($reply);
      } else {
        $composeSubject = '';
        $composeBody = '';
      }
    ?>
      <h3 style="margin-top:0"><?= $isDraftEdit ? 'Edit draft' : 'Compose' ?></h3>
      <form method="post" action="/hq/mailbox/send" enctype="multipart/form-data" id="mailbox-compose-form"><?= Csrf::field() ?>
        <input type="hidden" name="account_id" value="<?= $aid ?>">
        <?php if ($isDraftEdit): ?>
          <input type="hidden" name="draft_uid" value="<?= (int)($reply['draft_uid'] ?? 0) ?>">
          <input type="hidden" name="draft_folder" value="<?= htmlspecialchars($reply['draft_folder'] ?? 'Drafts') ?>">
        <?php endif; ?>
        <div class="form-group"><label>To</label><input name="to" list="mc" value="<?= htmlspecialchars($composeTo) ?>"<?= $isDraftEdit ? '' : ' required' ?>></div>
        <div class="form-group"><label>Cc</label><input name="cc" list="mc" value="<?= htmlspecialchars($composeCc) ?>"></div>
        <datalist id="mc"><?php foreach ($contacts as $c): ?><option value="<?= htmlspecialchars($c['email']) ?>"><?= htmlspecialchars($c['name']?:$c['email']) ?></option><?php endforeach; ?></datalist>
        <div class="form-group"><label>Subject</label><input name="subject" value="<?= htmlspecialchars($composeSubject) ?>"<?= $isDraftEdit ? '' : ' required' ?>></div>
        <div class="form-group"><label>Message</label><textarea name="body" rows="14" class="mailbox-compose-body"<?= $isDraftEdit ? '' : ' required' ?>><?= htmlspecialchars($composeBody) ?></textarea></div>
        <div class="form-group"><label>Attachments</label><input type="file" name="attachments[]" multiple></div>
        <div class="mailbox-compose-actions">
          <button class="btn-primary" type="submit">Send</button>
          <button class="btn-ghost" type="submit" formaction="/hq/mailbox/draft/save" formnovalidate>Save draft</button>
        </div>
      </form>
    <?php elseif ($message): ?>

      <h3 style="margin-top:0"><?= htmlspecialchars($message['subject']) ?></h3>
      <?php
        $folderLower = strtolower((string)$folder);
        $isDraftFolder = str_contains($folderLower, 'draft');
      ?>
      <div style="margin-bottom:0.75rem;display:flex;gap:0.4rem;flex-wrap:wrap;">
        <?php if ($isDraftFolder): ?>
          <a class="btn-primary btn-sm" href="/hq/mailbox?account=<?= $aid ?>&folder=<?= urlencode($folder) ?>&edit=<?= (int)$message['uid'] ?>">Edit</a>
          <form style="display:inline" method="post" action="/hq/mailbox/delete" onsubmit="return confirm('Delete this draft?')"><?= Csrf::field() ?>
            <input type="hidden" name="account_id" value="<?= $aid ?>"><input type="hidden" name="folder" value="<?= htmlspecialchars($folder) ?>">
            <input type="hidden" name="uid" value="<?= (int)$message['uid'] ?>"><button class="btn-danger btn-sm" type="submit">Delete</button></form>
        <?php else: ?>
          <a class="btn-ghost btn-sm" href="/hq/mailbox?account=<?= $aid ?>&folder=<?= urlencode($folder) ?>&reply=<?= (int)$message['uid'] ?>">Reply</a>
          <form style="display:inline" method="post" action="/hq/mailbox/delete" onsubmit="return confirm('Delete?')"><?= Csrf::field() ?>
            <input type="hidden" name="account_id" value="<?= $aid ?>"><input type="hidden" name="folder" value="<?= htmlspecialchars($folder) ?>">
            <input type="hidden" name="uid" value="<?= (int)$message['uid'] ?>"><button class="btn-danger btn-sm" type="submit">Delete</button></form>
        <?php endif; ?>
      </div>
      <div style="font-size:0.85rem;color:var(--hq-muted);margin-bottom:1rem">
        <div>From: <?= htmlspecialchars($message['from']) ?></div>
        <div>To: <?= htmlspecialchars($message['to']) ?></div>
        <div>Date: <?= htmlspecialchars($message['date']) ?></div>
      </div>
      <?php if (!empty($message['attachments'])): ?>
        <ul><?php foreach ($message['attachments'] as $att): ?>
          <li><a href="/hq/mailbox/attachment?account=<?= $aid ?>&folder=<?= urlencode($folder) ?>&uid=<?= (int)$message['uid'] ?>&part=<?= urlencode($att['part']) ?>"><?= htmlspecialchars($att['filename']) ?></a></li>
        <?php endforeach; ?></ul>
      <?php endif; ?>
      <?php if (!empty($message['body_html'])): ?>
        <iframe class="mailbox-html-frame" sandbox="" srcdoc="<?= htmlspecialchars($message['body_html']) ?>"></iframe>
      <?php else: ?>
        <pre style="white-space:pre-wrap;font-family:inherit"><?= htmlspecialchars($message['body_text']??'') ?></pre>
      <?php endif; ?>
    <?php else: ?>
      <p style="color:var(--hq-muted)">Select a message or compose.</p>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>

<style>
.mailbox-top{flex-wrap:wrap;gap:.75rem;align-items:center}
.mailbox-avatar{width:32px;height:32px;border-radius:50%;object-fit:cover}
.mailbox-avatar-fallback{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:var(--hq-accent,#2563eb);color:#fff;font-weight:600;font-size:.85rem}
.mailbox-layout{display:grid;grid-template-columns:minmax(220px,1fr) minmax(280px,1.4fr);gap:.75rem;align-items:start}
.mailbox-layout.is-list-only{grid-template-columns:1fr}
.mailbox-layout.is-list-only .mailbox-detail{display:none}
@media(max-width:960px){.mailbox-layout{grid-template-columns:1fr}}

/* One-line mail rows — normal weight; unread = accent bar + darker text */
.mailbox-row{
  display:flex;
  align-items:center;
  gap:.75rem;
  padding:.55rem .65rem;
  border-bottom:1px solid var(--hq-border);
  border-left:3px solid transparent;
  text-decoration:none;
  color:inherit;
  font-size:.84rem;
  font-weight:400;
  line-height:1.35;
  min-width:0;
}
.mailbox-row:hover{background:rgba(0,0,0,.025)}
.mailbox-row.is-unread{
  border-left-color:var(--hq-accent,#2563eb);
  background:rgba(59,130,246,.04);
}
.mailbox-line{
  flex:1;
  min-width:0;
  overflow:hidden;
  white-space:nowrap;
  text-overflow:ellipsis;
  color:var(--hq-text);
  font-weight:400;
}
.mailbox-from{color:var(--hq-text)}
.mailbox-subject{color:var(--hq-text)}
.mailbox-excerpt{color:var(--hq-muted)}
.mailbox-sep{margin:0 .35rem;color:var(--hq-muted)}
.mailbox-date{
  flex-shrink:0;
  font-size:.75rem;
  color:var(--hq-muted);
  white-space:nowrap;
}
.mailbox-html-frame{width:100%;min-height:320px;border:1px solid var(--hq-border);border-radius:8px;background:#fff}

/* Rich account switcher */
.mailbox-account-switcher{position:relative;display:flex;align-items:center}
.mailbox-switcher-btn{
  display:flex;align-items:center;gap:.55rem;
  padding:.3rem .55rem .3rem .3rem;
  border:1px solid var(--hq-border);
  border-radius:999px;
  background:var(--hq-surface,var(--hq-input-bg));
  color:var(--hq-text);cursor:pointer;font:inherit;
  max-width:min(340px,100%);
}
.mailbox-switcher-btn:hover{border-color:var(--hq-accent)}
.mailbox-switcher-btn .mailbox-avatar,
.mailbox-switcher-btn .mailbox-avatar-fallback{width:28px;height:28px;flex-shrink:0}
.mailbox-switcher-text{min-width:0;text-align:left;line-height:1.2}
.mailbox-switcher-text .name{
  display:block;font-size:.82rem;font-weight:600;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.mailbox-switcher-text .email{
  display:block;font-size:.72rem;color:var(--hq-muted);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.mailbox-switcher-btn > .chev{margin-left:.15rem;color:var(--hq-muted);font-size:.7rem}
.mailbox-switcher-menu{
  position:absolute;top:calc(100% + 6px);left:0;
  min-width:260px;max-width:340px;
  background:var(--hq-surface,#fff);
  border:1px solid var(--hq-border);
  border-radius:12px;
  box-shadow:0 10px 30px rgba(0,0,0,.12);
  padding:.35rem;z-index:40;
}
.mailbox-switcher-menu[hidden]{display:none}
.mailbox-switcher-item{
  display:flex;align-items:center;gap:.6rem;
  padding:.45rem .55rem;border-radius:8px;
  text-decoration:none;color:inherit;
}
.mailbox-switcher-item:hover,
.mailbox-switcher-item.is-active{background:rgba(59,130,246,.08)}
.mailbox-switcher-item .mailbox-avatar,
.mailbox-switcher-item .mailbox-avatar-fallback{width:28px;height:28px;flex-shrink:0}

/* Mailbox search like HQ Line 1 */
.mailbox-search{position:relative;margin-bottom:.65rem}
.mailbox-search > i{
  position:absolute;left:14px;top:50%;transform:translateY(-50%);
  color:var(--hq-muted);font-size:13px;pointer-events:none;
}
.mailbox-search input[type="search"]{
  width:100%;height:36px;padding:0 14px 0 40px;
  border:none;border-radius:9px;
  background:var(--hq-bg,var(--hq-input-bg));
  font-size:13.5px;color:var(--hq-text);font-family:inherit;
  transition:box-shadow .15s,background .15s;box-sizing:border-box;
}
.mailbox-search input[type="search"]::placeholder{color:var(--hq-muted)}
.mailbox-search input[type="search"]:focus{
  outline:none;background:var(--hq-surface,#fff);
  box-shadow:0 0 0 2px var(--hq-accent);
}
</style>
<script>
(function () {
  // Account switcher dropdown
  var btn = document.getElementById('mailbox-switcher-btn');
  var menu = document.getElementById('mailbox-switcher-menu');
  if (btn && menu) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = menu.hidden;
      menu.hidden = !open;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function () {
      menu.hidden = true;
      btn.setAttribute('aria-expanded', 'false');
    });
    menu.addEventListener('click', function (e) { e.stopPropagation(); });
  }
  // Password eye toggles
  document.querySelectorAll('.password-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = btn.parentElement.querySelector('input');
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.classList.toggle('is-visible', show);
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });
})();
</script>
