<?php $summary = $summary ?? []; $popular = $popular ?? []; $byDay = $byDay ?? []; $referrers = $referrers ?? []; $recent = $recent ?? []; ?>

<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-value"><?= (int) ($summary['total_views'] ?? 0) ?></span>
        <span class="stat-label">Views (<?= (int) ($summary['days'] ?? 30) ?>d)</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= htmlspecialchars((string) ($summary['avg_per_day'] ?? 0)) ?></span>
        <span class="stat-label">Avg / day</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= (int) ($summary['published'] ?? 0) ?></span>
        <span class="stat-label">Published</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= (int) ($summary['subscribers'] ?? 0) ?></span>
        <span class="stat-label">Subscribers</span>
    </div>
</div>

<div class="form-layout sidebar-closed" id="insights-layout" style="margin-top:1.5rem;">
    <div class="form-main">
        <div class="panel" style="margin-bottom:1.5rem;">
            <h3 style="margin-top:0;">Views by day</h3>
            <?php
            $max = 1;
            foreach ($byDay as $d) { $max = max($max, (int) $d['views']); }
            ?>
            <div style="display:flex;align-items:flex-end;gap:4px;height:120px;margin-top:1rem;">
                <?php foreach ($byDay as $d): ?>
                    <?php $h = $max > 0 ? max(4, (int) round(((int)$d['views'] / $max) * 100)) : 4; ?>
                    <div title="<?= htmlspecialchars($d['day']) ?>: <?= (int)$d['views'] ?>"
                         style="flex:1;background:var(--hq-accent);opacity:0.85;border-radius:3px 3px 0 0;height:<?= $h ?>%;"></div>
                <?php endforeach; ?>
            </div>
            <p style="font-size:0.75rem;color:var(--hq-muted);margin:0.5rem 0 0;">Last <?= count($byDay) ?> days</p>
        </div>

        <div class="panel">
            <h3 style="margin-top:0;">Top referrers</h3>
            <?php if (empty($referrers)): ?>
                <p class="empty">No referrer data yet.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Source</th><th>Hits</th></tr></thead>
                    <tbody>
                    <?php foreach ($referrers as $r): ?>
                        <tr>
                            <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($r['referrer']) ?></td>
                            <td><?= (int) $r['hits'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <aside class="form-sidebar" id="insights-sidebar" hidden>
        <div class="form-sidebar-toolbar">
            <span class="form-sidebar-title" id="insights-sidebar-title">Tool</span>
            <button type="button" class="form-sidebar-close" id="btn-close-insights-sidebar" title="Close sidebar" aria-label="Close sidebar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="panel collapsible" data-panel="popular" hidden>
            <button type="button" class="panel-head" aria-expanded="true">
                <h3>Popular content</h3>
            </button>
            <div class="panel-body">
                <?php if (empty($popular)): ?>
                    <p class="empty">No views recorded yet.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Title</th><th>Views</th></tr></thead>
                        <tbody>
                        <?php foreach ($popular as $row): ?>
                            <tr>
                                <td><a href="/<?= htmlspecialchars($row['slug']) ?>" target="_blank"><?= htmlspecialchars($row['title']) ?></a></td>
                                <td><?= (int) $row['views'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel collapsible" data-panel="recent" hidden>
            <button type="button" class="panel-head" aria-expanded="true">
                <h3>Recent views</h3>
            </button>
            <div class="panel-body">
                <?php if (empty($recent)): ?>
                    <p class="empty">Nothing yet.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Path</th><th>When</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['title'] ?: $r['path']) ?></td>
                                <td style="white-space:nowrap;"><?= date('M j, H:i', strtotime($r['viewed_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>

<script>
(function () {
  var layout = document.getElementById('insights-layout');
  var sidebar = document.getElementById('insights-sidebar');
  var titleEl = document.getElementById('insights-sidebar-title');
  var btnClose = document.getElementById('btn-close-insights-sidebar');
  var labels = { popular: 'Popular content', recent: 'Recent views' };

  function closeSidebar() {
    if (layout) layout.classList.add('sidebar-closed');
    if (sidebar) sidebar.hidden = true;
    document.querySelectorAll('#insights-sidebar .panel.collapsible').forEach(function (p) {
      p.hidden = true;
      p.classList.remove('is-open', 'is-active-tool');
    });
    delete document.body.dataset.activeTool;
  }

  function openTool(name) {
    if (!layout || !sidebar || !labels[name]) return;
    layout.classList.remove('sidebar-closed');
    sidebar.hidden = false;
    document.querySelectorAll('#insights-sidebar .panel.collapsible').forEach(function (p) {
      var match = p.getAttribute('data-panel') === name;
      p.hidden = !match;
      p.classList.toggle('is-open', match);
      p.classList.toggle('is-active-tool', match);
    });
    if (titleEl) titleEl.textContent = labels[name];
    document.body.dataset.activeTool = name;
  }

  closeSidebar();
  if (btnClose) btnClose.addEventListener('click', closeSidebar);
  document.addEventListener('hq:tool', function (e) {
    var action = (e.detail && e.detail.action) || '';
    if (action === 'popular' || action === 'recent') openTool(action);
  });
})();
</script>
