<?php
use Mova\Security\Csrf;
$entries = $entries ?? [];
$forms = $forms ?? [];
$formId = (int) ($formId ?? 0);
$form = $form ?? null;
$counts = $counts ?? ['new' => 0, 'read' => 0, 'total' => 0];
$newCount = (int) ($counts['new'] ?? $newCount ?? 0);
$readCount = (int) ($counts['read'] ?? 0);
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
$per = (int) ($per ?? 25);

function fb_dt(?string $iso): string {
  if (!$iso) return '';
  $ts = strtotime($iso);
  if ($ts === false) return $iso;
  return date('d/m/y H:i', $ts);
}
$fieldDefs = $form['fields'] ?? [];
$single = $formId > 0;
?>
<section class="hq-page fb-inbox-page">
<div class="fb-inbox-layout" id="fb-inbox-layout">
  <div class="fb-inbox-main" id="fb-inbox-main">
  <div class="fb-inbox-head">
    <div>
      <h1>Feedback inbox</h1>
      <p class="fb-counts">
        <span class="fb-pill is-new"><?= $newCount ?> unread</span>
        <span class="fb-pill"><?= $readCount ?> read</span>
        <span class="text-muted"><?= $total ?> total</span>
      </p>
    </div>
    <div class="fb-inbox-actions">
      <a class="btn-ghost btn-sm" href="/hq/feedback">Forms</a>
      <a class="btn-ghost btn-sm" id="fb-export-link" href="/hq/feedback/export<?= $formId ? ('?form=' . $formId) : '' ?>">Export CSV</a>
    </div>
  </div>

  <div class="fb-inbox-toolbar">
    <form method="get" action="/hq/feedback/inbox" class="fb-form-select">
      <label for="fb-form-filter">Form</label>
      <select name="form" id="fb-form-filter" onchange="this.form.submit()">
        <option value="0">All forms</option>
        <?php foreach ($forms as $f): ?>
          <option value="<?= (int)$f['id'] ?>" <?= $formId === (int)$f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <div class="fb-bulk" id="fb-bulk" hidden>
      <span id="fb-bulk-count">0 selected</span>
      <button type="button" class="btn-ghost btn-sm" data-bulk="read">Mark read</button>
      <button type="button" class="btn-ghost btn-sm" data-bulk="export">Export</button>
      <button type="button" class="btn-ghost btn-sm" data-bulk="delete" style="color:var(--hq-danger-text,#b91c1c)">Delete</button>
    </div>
  </div>

  <form id="fb-bulk-form" method="post" action="/hq/feedback/entries/bulk" style="display:none">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" id="fb-bulk-action" value="">
    <input type="hidden" name="ids" id="fb-bulk-ids" value="">
    <input type="hidden" name="form" value="<?= $formId ?>">
  </form>

  <div class="fb-inbox-table-wrap<?= $single ? ' is-wide' : '' ?>">
    <table class="fb-inbox-table">
      <thead>
        <tr>
          <th class="fb-check"><input type="checkbox" id="fb-check-all" title="Mark all"></th>
          <th>Time/date</th>
          <?php if ($single): ?>
            <th>Form</th>
            <?php foreach ($fieldDefs as $fd): ?>
              <th><?= htmlspecialchars($fd['label'] ?? $fd['key'] ?? '') ?></th>
            <?php endforeach; ?>
          <?php else: ?>
            <th>Form</th>
            <th>Message</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($entries as $e):
        $payload = $e['payload'] ?? [];
        $preview = '';
        foreach ($payload as $v) {
          if (is_array($v)) $v = implode(', ', $v);
          $preview = mb_substr((string)$v, 0, 90);
          if ($preview !== '') break;
        }
        $isNew = ($e['status'] ?? '') === 'new';
      ?>
        <tr class="fb-entry-row<?= $isNew ? ' is-unread' : '' ?>" data-id="<?= (int)$e['id'] ?>" tabindex="0">
          <td class="fb-check" onclick="event.stopPropagation()">
            <input type="checkbox" class="fb-row-check" value="<?= (int)$e['id'] ?>">
          </td>
          <td class="fb-dt"><?= htmlspecialchars(fb_dt($e['created_at'] ?? null)) ?><?= $isNew ? ' <span class="fb-dot" title="Unread"></span>' : '' ?></td>
          <?php if ($single): ?>
            <td><?= htmlspecialchars($e['form_title'] ?? '') ?></td>
            <?php foreach ($fieldDefs as $fd):
              $k = $fd['key'] ?? '';
              $val = $payload[$k] ?? '';
              if (is_array($val)) $val = implode(', ', $val);
            ?>
              <td class="fb-cell"><?= htmlspecialchars(mb_substr((string)$val, 0, 120)) ?></td>
            <?php endforeach; ?>
          <?php else: ?>
            <td><?= htmlspecialchars($e['form_title'] ?? '') ?></td>
            <td class="fb-preview"><?= htmlspecialchars($preview) ?></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$entries): ?>
        <tr><td colspan="20" class="text-muted" style="padding:1.5rem">No submissions yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <div class="fb-pager">
    <?php if ($page > 1): ?>
      <a href="?form=<?= $formId ?>&page=<?= $page - 1 ?>">← Prev</a>
    <?php endif; ?>
    <span>Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?>
      <a href="?form=<?= $formId ?>&page=<?= $page + 1 ?>">Next →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  </div><!-- .fb-inbox-main -->

  <aside class="fb-entry-sidebar" id="fb-sidebar" hidden aria-hidden="true">
    <div class="fb-sidebar-inner">
      <div class="fb-sidebar-head">
        <div class="fb-sidebar-head-text">
          <div class="fb-sidebar-dt" id="fb-side-dt"></div>
          <div class="fb-sidebar-form" id="fb-side-form"></div>
        </div>
        <button type="button" class="fb-sidebar-close" id="fb-side-close" title="Close" aria-label="Close">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
      <div class="fb-sidebar-body" id="fb-side-body"></div>
      <div class="fb-sidebar-foot">
        <form method="post" id="fb-side-del" action="">
          <?= Csrf::field() ?>
          <input type="hidden" name="form" value="<?= $formId ?>">
          <button type="submit" class="btn-ghost btn-sm" style="color:var(--hq-danger-text,#b91c1c)" onclick="return confirm('Delete this entry?')">Delete</button>
        </form>
      </div>
    </div>
  </aside>
</div><!-- .fb-inbox-layout -->
</section>

<style>
.fb-inbox-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}
.fb-inbox-head h1{margin:0;font-size:1.25rem}
.fb-counts{display:flex;gap:0.5rem;align-items:center;margin:0.4rem 0 0;flex-wrap:wrap}
.fb-pill{font-size:0.75rem;font-weight:600;padding:0.2rem 0.55rem;border-radius:999px;background:#f1f5f9;color:#475569}
.fb-pill.is-new{background:#ecfdf5;color:#059669}
.fb-inbox-toolbar{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:0.75rem}
.fb-form-select{display:flex;align-items:center;gap:0.5rem}
.fb-form-select label{font-size:0.8rem;font-weight:600;color:var(--hq-muted,#64748b)}
.fb-form-select select{
  font:inherit;font-size:0.9rem;padding:0.4rem 0.65rem;border:1px solid var(--hq-border,#e2e8f0);border-radius:8px;
  background:var(--hq-surface,#fff);min-width:12rem;color:inherit;
}
.fb-bulk{display:flex;align-items:center;gap:0.5rem;font-size:0.82rem;color:var(--hq-muted,#64748b)}
.fb-inbox-table-wrap{border:1px solid var(--hq-border,#e5e7eb);border-radius:12px;overflow:auto;background:var(--hq-surface,#fff);max-width:100%}
.fb-inbox-table{width:100%;border-collapse:collapse;font-size:0.88rem;min-width:100%}
.fb-inbox-table-wrap.is-wide .fb-inbox-table{min-width:720px}
.fb-inbox-table th{
  text-align:left;font-size:0.7rem;font-weight:650;text-transform:uppercase;letter-spacing:0.04em;
  color:var(--hq-muted,#64748b);padding:0.65rem 0.85rem;background:color-mix(in srgb,var(--hq-text,#0f172a) 4%,var(--hq-surface,#fff));border-bottom:1px solid var(--hq-border,#eef2f7);white-space:nowrap;
}
.fb-inbox-table td{padding:0.7rem 0.85rem;border-bottom:1px solid var(--hq-border,#f1f5f9);vertical-align:middle}
.fb-inbox-table tbody tr:nth-child(even){background:color-mix(in srgb,var(--hq-text,#0f172a) 2%,transparent)}
.fb-inbox-table tbody tr:hover{background:var(--hq-hover,#f1f5f9);cursor:pointer}
.fb-inbox-table tbody tr.is-unread{font-weight:600}
.fb-check{width:2.2rem}
.fb-dt{white-space:nowrap;font-variant-numeric:tabular-nums;font-size:0.85rem}
.fb-dot{display:inline-block;width:0.45rem;height:0.45rem;border-radius:999px;background:#10b981;margin-left:0.25rem;vertical-align:middle}
.fb-preview,.fb-cell{color:var(--hq-muted,#64748b);max-width:18rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.fb-pager{display:flex;gap:1rem;align-items:center;justify-content:center;margin-top:1rem;font-size:0.85rem}
.fb-pager a{color:var(--hq-accent,#0f766e);text-decoration:none}

/* Flex push layout — under HQ dynamic nav, never overlays top bar */
.fb-inbox-layout{
  display:flex;
  align-items:flex-start;
  gap:1.25rem;
  width:100%;
}
.fb-inbox-main{
  flex:1 1 auto;
  min-width:0;
  transition:max-width 0.15s ease;
}
.fb-inbox-layout.is-sidebar-open .fb-inbox-main{
  flex:1 1 70%;
  max-width:calc(100% - min(30vw, 380px) - 1.25rem);
}
.fb-entry-sidebar{
  display:none;
  flex:0 0 min(30vw, 380px);
  width:min(30vw, 380px);
  max-width:380px;
  min-width:260px;
  align-self:flex-start;
  position:sticky;
  top:0.75rem;
  max-height:none;
  background:var(--hq-surface, #fff);
  border:1px solid var(--hq-border, #e5e7eb);
  border-radius:12px;
  box-shadow:0 4px 20px rgba(15,23,42,0.06);
  z-index:2;
}
.fb-inbox-layout.is-sidebar-open .fb-entry-sidebar,
.fb-entry-sidebar.is-open{
  display:flex;
  flex-direction:column;
}
.fb-entry-sidebar[hidden]{
  display:none !important;
}
.fb-sidebar-inner{display:flex;flex-direction:column;min-height:12rem}
.fb-sidebar-head{
  display:flex;justify-content:space-between;align-items:flex-start;gap:0.75rem;
  padding:0.85rem 0.9rem;border-bottom:1px solid var(--hq-border,#f1f5f9);
}
.fb-sidebar-head-text{min-width:0;flex:1}
.fb-sidebar-dt{font-size:0.95rem;font-weight:650;font-variant-numeric:tabular-nums}
.fb-sidebar-form{font-size:0.8rem;color:var(--hq-muted,#64748b);margin-top:0.15rem}
.fb-sidebar-close{
  flex-shrink:0;
  width:2.25rem;height:2.25rem;
  border:1px solid var(--hq-border,#e2e8f0);
  border-radius:8px;
  background:color-mix(in srgb,var(--hq-text,#0f172a) 4%,var(--hq-surface,#fff));
  color:var(--hq-text,#0f172a);
  font-size:1.1rem;
  line-height:1;
  cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;
  box-shadow:0 1px 2px rgba(15,23,42,0.04);
}
.fb-sidebar-close:hover{
  background:#fee2e2;
  border-color:#fca5a5;
  color:#dc2626;
}
.fb-sidebar-body{flex:1;overflow:auto;padding:1rem 1.1rem}
.fb-side-field{margin-bottom:0.85rem}
.fb-side-field label{display:block;font-size:0.72rem;font-weight:650;color:#94a3b8;margin-bottom:0.2rem;text-transform:uppercase;letter-spacing:0.03em}
.fb-side-field div{font-size:0.92rem;line-height:1.45;white-space:pre-wrap;word-break:break-word}
.fb-sidebar-foot{padding:0.75rem 1.1rem;border-top:1px solid var(--hq-border,#f1f5f9)}
@media (max-width: 800px) {
  .fb-inbox-layout.is-sidebar-open{flex-direction:column}
  .fb-inbox-layout.is-sidebar-open .fb-inbox-main{max-width:100%;flex:1 1 auto}
  .fb-entry-sidebar{width:100%;max-width:100%;flex:1 1 auto;position:relative;top:0}
}
</style>
<script>
(function(){
  var sidebar = document.getElementById('fb-sidebar');
  var layout = document.getElementById('fb-inbox-layout');
  var csrf = document.querySelector('#fb-bulk-form input[name="_mova_csrf"], #fb-bulk-form input[name="_csrf"]');
  var csrfToken = csrf ? csrf.value : '';

  function selectedIds() {
    return Array.prototype.map.call(document.querySelectorAll('.fb-row-check:checked'), function(c){ return c.value; });
  }
  function syncBulk() {
    var ids = selectedIds();
    var bar = document.getElementById('fb-bulk');
    bar.hidden = ids.length === 0;
    document.getElementById('fb-bulk-count').textContent = ids.length + ' selected';
  }
  document.getElementById('fb-check-all').addEventListener('change', function(){
    document.querySelectorAll('.fb-row-check').forEach(function(c){ c.checked = document.getElementById('fb-check-all').checked; });
    syncBulk();
  });
  document.querySelectorAll('.fb-row-check').forEach(function(c){
    c.addEventListener('change', syncBulk);
  });

  document.querySelectorAll('[data-bulk]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var act = btn.getAttribute('data-bulk');
      var ids = selectedIds();
      if (!ids.length) return;
      if (act === 'export') {
        var base = '/hq/feedback/export?ids=' + ids.join(',');
        var form = <?= (int)$formId ?>;
        if (form) base += '&form=' + form;
        window.location = base;
        return;
      }
      if (act === 'delete' && !confirm('Delete ' + ids.length + ' entries?')) return;
      document.getElementById('fb-bulk-action').value = act;
      document.getElementById('fb-bulk-ids').value = ids.join(',');
      document.getElementById('fb-bulk-form').submit();
    });
  });

  function closeSide() {
    if (!sidebar) return;
    sidebar.hidden = true;
    sidebar.classList.remove('is-open');
    sidebar.setAttribute('aria-hidden', 'true');
    if (layout) layout.classList.remove('is-sidebar-open');
  }
  document.getElementById('fb-side-close').addEventListener('click', closeSide);
  // Ensure closed on load (display:flex must not override [hidden])
  closeSide();

  function openEntry(id) {
    fetch('/hq/feedback/entry/' + id + '?format=json', {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    }).then(function(r){ return r.json(); }).then(function(data){
      var e = data.entry || {};
      var dt = e.created_at || '';
      // format client-side similar to PHP d/m/y H:i
      try {
        var d = new Date(dt);
        if (!isNaN(d.getTime())) {
          var p = function(n){ return n < 10 ? '0'+n : ''+n; };
          dt = p(d.getDate())+'/'+p(d.getMonth()+1)+'/'+String(d.getFullYear()).slice(2)+' '+p(d.getHours())+':'+p(d.getMinutes());
        }
      } catch (err) {}
      document.getElementById('fb-side-dt').textContent = dt;
      document.getElementById('fb-side-form').textContent = e.form_title || '';
      var body = document.getElementById('fb-side-body');
      body.innerHTML = '';
      var payload = e.payload || {};
      Object.keys(payload).forEach(function(k){
        var v = payload[k];
        if (Array.isArray(v)) v = v.join(', ');
        var div = document.createElement('div');
        div.className = 'fb-side-field';
        div.innerHTML = '<label></label><div></div>';
        div.querySelector('label').textContent = k;
        div.querySelector('div').textContent = String(v);
        body.appendChild(div);
      });
      if (e.page_url) {
        var div = document.createElement('div');
        div.className = 'fb-side-field';
        div.innerHTML = '<label>Page</label><div></div>';
        div.querySelector('div').textContent = e.page_url;
        body.appendChild(div);
      }
      document.getElementById('fb-side-del').action = '/hq/feedback/entry/delete/' + id;
      sidebar.hidden = false;
      sidebar.classList.add('is-open');
      sidebar.setAttribute('aria-hidden', 'false');
      if (layout) layout.classList.add('is-sidebar-open');
      // mark row as read visually
      var row = document.querySelector('.fb-entry-row[data-id="'+id+'"]');
      if (row) {
        row.classList.remove('is-unread');
        document.querySelectorAll('.fb-entry-row.is-active').forEach(function(r){ r.classList.remove('is-active'); });
        row.classList.add('is-active');
      }
    });
  }

  document.querySelectorAll('.fb-entry-row').forEach(function(row){
    row.addEventListener('click', function(){ openEntry(row.getAttribute('data-id')); });
    row.addEventListener('keydown', function(e){
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openEntry(row.getAttribute('data-id')); }
    });
  });
})();
</script>
