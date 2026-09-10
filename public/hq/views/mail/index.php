<?php use Mova\Security\Csrf; $subscribers = $subscribers ?? []; ?>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="form-layout sidebar-closed" id="campaigns-layout">
    <div class="form-main">
        <div class="toolbar">
            <h2 style="margin:0;font-size:1rem;">Subscribers (<?= count($subscribers) ?>)</h2>
        </div>
        <div class="panel">
            <?php if (empty($subscribers)): ?>
                <p class="empty">No subscribers yet.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr><th>Email</th><th>Name</th><th>Status</th><th>Since</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($subscribers as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['email']) ?></td>
                            <td><?= htmlspecialchars($s['name'] ?? '') ?></td>
                            <td><span class="badge"><?= htmlspecialchars($s['status']) ?></span></td>
                            <td><?= date('M j, Y', strtotime($s['subscribed_at'])) ?></td>
                            <td>
                                <form method="post" action="/hq/mail/subscriber/delete" style="display:inline;" onsubmit="return confirm('Remove subscriber?');">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <button type="submit" class="btn-danger btn-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <aside class="form-sidebar" id="campaigns-sidebar" hidden>
        <div class="form-sidebar-toolbar">
            <span class="form-sidebar-title" id="campaigns-sidebar-title">Tool</span>
            <button type="button" class="form-sidebar-close" id="btn-close-campaigns-sidebar" title="Close sidebar" aria-label="Close sidebar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="panel collapsible" data-panel="add-subscriber" hidden>
            <button type="button" class="panel-head" aria-expanded="true">
                <h3>Add subscriber</h3>
            </button>
            <div class="panel-body">
                <form method="post" action="/hq/mail/subscriber">
                    <?= Csrf::field() ?>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name">
                    </div>
                    <button type="submit" class="btn-primary">Add</button>
                </form>
            </div>
        </div>

        <div class="panel collapsible" data-panel="send-campaign" hidden>
            <button type="button" class="panel-head" aria-expanded="true">
                <h3>Send campaign</h3>
            </button>
            <div class="panel-body">
                <p style="font-size:0.85rem;color:var(--hq-muted);">Sends to all active subscribers. Configure SMTP under Settings.</p>
                <form method="post" action="/hq/mail/send">
                    <?= Csrf::field() ?>
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="subject" required>
                    </div>
                    <div class="form-group">
                        <label>HTML body</label>
                        <textarea name="body" rows="8" required placeholder="<p>Hello…</p>"></textarea>
                    </div>
                    <button type="submit" class="btn-primary" onclick="return confirm('Send to all active subscribers?');">Send</button>
                </form>
            </div>
        </div>
    </aside>
</div>

<script>
(function () {
  var layout = document.getElementById('campaigns-layout');
  var sidebar = document.getElementById('campaigns-sidebar');
  var titleEl = document.getElementById('campaigns-sidebar-title');
  var btnClose = document.getElementById('btn-close-campaigns-sidebar');
  var labels = { 'add-subscriber': 'Add subscriber', 'send-campaign': 'Send campaign' };

  function closeSidebar() {
    if (layout) layout.classList.add('sidebar-closed');
    if (sidebar) sidebar.hidden = true;
    document.querySelectorAll('#campaigns-sidebar .panel.collapsible').forEach(function (p) {
      p.hidden = true;
      p.classList.remove('is-open', 'is-active-tool');
    });
    delete document.body.dataset.activeTool;
  }

  function openTool(name) {
    if (!layout || !sidebar || !labels[name]) return;
    layout.classList.remove('sidebar-closed');
    sidebar.hidden = false;
    document.querySelectorAll('#campaigns-sidebar .panel.collapsible').forEach(function (p) {
      var match = p.getAttribute('data-panel') === name;
      p.hidden = !match;
      p.classList.toggle('is-open', match);
      p.classList.toggle('is-active-tool', match);
    });
    if (titleEl) titleEl.textContent = labels[name];
    document.body.dataset.activeTool = name;
    var panel = document.querySelector('#campaigns-sidebar .panel.collapsible[data-panel="' + name + '"]');
    var focusEl = panel && panel.querySelector('input, textarea, select');
    if (focusEl) {
      try { focusEl.focus({ preventScroll: true }); } catch (e) { focusEl.focus(); }
    }
  }

  closeSidebar();
  if (btnClose) btnClose.addEventListener('click', closeSidebar);
  document.addEventListener('hq:tool', function (e) {
    var action = (e.detail && e.detail.action) || '';
    if (action === 'add-subscriber' || action === 'send-campaign') openTool(action);
  });
})();
</script>
