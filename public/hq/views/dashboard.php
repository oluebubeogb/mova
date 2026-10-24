<?php
/* Overview — Phase 5 dashboard */
use Mova\Hq\RouteRegistry;

$h = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$bytes = static function (?int $n): string {
    if ($n === null) {
        return '—';
    }
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $f = (float) $n;
    while ($f >= 1024 && $i < 4) {
        $f /= 1024;
        $i++;
    }
    return ($i ? number_format($f, $f < 10 ? 1 : 0) : (string) (int) $f) . ' ' . $u[$i];
};
$ago = static function (?string $ts): string {
    return $ts ? hqTimeAgo($ts, '') : '';
};
$__now = new \DateTimeImmutable('now', \Mova\Core\Time::zone());
$hour = (int) $__now->format('G');
$greet = $hour < 5 ? 'Working late' : ($hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening'));
$first = trim(explode(' ', (string) ($user['name'] ?? $user['username'] ?? ''))[0] ?? '');
$total = count($checklist);
$pct = $total ? (int) round($doneCount / $total * 100) : 100;
$ring = 2 * M_PI * 26; // r=26
$showQuickSetup = $isAdmin && !$setupComplete && !$wizardDone;
$canContent = RouteRegistry::allowed('manage_content|manage_own_content');
$canMedia = RouteRegistry::allowed('manage_media');
$canAi = RouteRegistry::allowed('use_ai');
$healthIcons = ['database' => 'fa-database', 'cache' => 'fa-bolt', 'storage' => 'fa-hard-drive', 'mail' => 'fa-envelope', 'security' => 'fa-shield-halved', 'ai' => 'fa-wand-magic-sparkles', 'logs' => 'fa-file-lines'];
$statusTone = ['published' => 'ok', 'draft' => 'muted', 'review' => 'warn', 'approved' => 'info', 'scheduled' => 'info', 'archived' => 'muted'];
$jobTone = ['done' => 'ok', 'completed' => 'ok', 'running' => 'info', 'queued' => 'muted', 'failed' => 'err', 'error' => 'err', 'cancelled' => 'muted'];
?>
<section class="dash" aria-labelledby="dash-title">
  <div class="dash-art" aria-hidden="true">
    <svg viewBox="0 0 800 260" preserveAspectRatio="none"><path d="M0 200 C 160 120, 320 260, 480 170 S 720 90, 800 140" /><path d="M0 230 C 200 170, 360 250, 520 200 S 700 150, 800 180" /></svg>
  </div>

  <header class="dash-hero">
    <div class="dash-hero-text">
      <p class="dash-kicker"><span class="dash-dot" aria-hidden="true"></span> <?= $h($__now->format('l, j F')) ?></p>
      <h1 id="dash-title" class="dash-title"><?= $h($greet) ?><?= $first !== '' ? ', ' . $h($first) : '' ?></h1>
      <p class="dash-sub">
        <?php if ($canWrite): ?>
          <strong><?= (int) $stats['published'] ?></strong> published ·
          <strong><?= (int) $stats['drafts'] ?></strong> drafts<?php if ($stats['review'] > 0): ?> ·
          <a href="/hq/content?status=review"><strong><?= (int) $stats['review'] ?></strong> waiting for review</a><?php endif; ?>
        <?php else: ?>
          You have read-only access to <strong><?= (int) $stats['published'] ?></strong> published entries.
        <?php endif; ?>
      </p>
    </div>
    <nav class="dash-actions" aria-label="Quick actions">
      <?php if ($canContent && $canWrite): ?>
        <a class="btn btn-primary dash-act" href="/hq/content/new"><i class="fa-solid fa-plus" aria-hidden="true"></i> New page</a>
      <?php endif; ?>
      <?php if ($canMedia && $canWrite): ?>
        <a class="btn btn-secondary dash-act" href="/hq/media"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i> Upload media</a>
      <?php endif; ?>
      <?php if ($canAi): ?>
        <button type="button" class="btn btn-secondary dash-act" data-dash-ai><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Ask Mova AI</button>
      <?php endif; ?>
      <a class="btn btn-ghost dash-act" href="/" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> View site</a>
    </nav>
  </header>

  <div class="dash-grid<?= $isAdmin ? ' is-admin' : '' ?>">
    <?php if ($isAdmin && $total): ?>
    <article class="dash-card dash-setup<?= $setupComplete ? ' is-complete' : '' ?>" aria-labelledby="dash-setup-h">
      <div class="dash-card-head">
        <div class="dash-ring" role="img" aria-label="Setup <?= $pct ?>% complete">
          <svg viewBox="0 0 64 64" width="64" height="64" aria-hidden="true">
            <circle class="dash-ring-bg" cx="32" cy="32" r="26" />
            <circle class="dash-ring-fg" cx="32" cy="32" r="26" stroke-dasharray="<?= round($ring, 2) ?>" stroke-dashoffset="<?= round($ring * (1 - $pct / 100), 2) ?>" />
          </svg>
          <span><?= $pct ?>%</span>
        </div>
        <div>
          <h2 id="dash-setup-h"><?= $setupComplete ? 'Setup complete' : 'Finish setting up' ?></h2>
          <p class="dash-muted"><?= $doneCount ?> of <?= $total ?> steps done</p>
        </div>
      </div>
      <?php if ($showQuickSetup): ?>
        <a class="dash-quicksetup" href="/hq/setup-wizard">
          <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
          <span><strong>Quick setup</strong><small>Pages, palette, nav and footer in a few minutes</small></span>
          <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </a>
      <?php endif; ?>
      <ol class="dash-steps">
        <?php foreach ($checklist as $s): ?>
          <li class="<?= $s['done'] ? 'is-done' : '' ?>">
            <a href="<?= $h($s['href']) ?>" data-tip="<?= $h($s['hint']) ?>">
              <span class="dash-check" aria-hidden="true"><i class="fa-solid <?= $s['done'] ? 'fa-check' : 'fa-circle' ?>"></i></span>
              <span><?= $h($s['label']) ?></span>
              <span class="hq-sr-only"><?= $s['done'] ? '(done)' : '(to do)' ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ol>
    </article>
    <?php endif; ?>

    <article class="dash-card dash-recent" aria-labelledby="dash-recent-h">
      <div class="dash-card-title">
        <h2 id="dash-recent-h"><i class="fa-regular fa-clock" aria-hidden="true"></i> Recent edits</h2>
        <a class="dash-link" href="/hq/content">All content <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
      </div>
      <?php if (!$recent): ?>
        <div class="dash-empty">
          <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
          <p>Nothing here yet.</p>
          <?php if ($canContent && $canWrite): ?><a class="btn btn-primary btn-sm" href="/hq/content/new">Write your first page</a><?php endif; ?>
        </div>
      <?php else: ?>
        <ul class="dash-list">
          <?php foreach ($recent as $r): ?>
            <li>
              <a href="<?= $canWrite ? '/hq/content/' . (int) $r['id'] . '/edit' : '/' . $h($r['slug']) ?>">
                <span class="dash-list-title"><?= $h($r['title'] !== '' ? $r['title'] : '(untitled)') ?></span>
                <span class="dash-list-meta"><?= $h(ucfirst((string) $r['type'])) ?><?= $r['author'] ? ' · ' . $h($r['author']) : '' ?> · <?= hqTimeTag($r['updated_at'], 'rel') ?></span>
              </a>
              <span class="dash-chip is-<?= $h($statusTone[$r['status']] ?? 'muted') ?>"><?= $h($r['status']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </article>

    <?php if ($isAdmin && $health): ?>
    <article class="dash-card dash-health" aria-labelledby="dash-health-h">
      <div class="dash-card-title">
        <h2 id="dash-health-h"><i class="fa-solid fa-heart-pulse" aria-hidden="true"></i> Health</h2>
        <a class="dash-link" href="/hq/health">Details <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
      </div>
      <ul class="dash-lights">
        <?php foreach ($health as $k => $c):
            $tone = in_array($c['status'], ['ok', 'warn', 'error'], true) ? $c['status'] : 'info'; ?>
          <li class="is-<?= $h($tone) ?>" data-tip="<?= $h($c['detail']) ?>" tabindex="0">
            <span class="dash-light" aria-hidden="true"></span>
            <i class="fa-solid <?= $h($healthIcons[$k] ?? 'fa-circle-info') ?>" aria-hidden="true"></i>
            <span class="dash-light-label"><?= $h(ucfirst($k === 'ai' ? 'AI' : $k)) ?></span>
            <span class="hq-sr-only">: <?= $h($tone) ?> — <?= $h($c['detail']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </article>
    <?php endif; ?>

    <?php if ($canAi): ?>
    <article class="dash-card dash-jobs" aria-labelledby="dash-jobs-h">
      <div class="dash-card-title">
        <h2 id="dash-jobs-h"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> AI jobs</h2>
      </div>
      <?php if (!$jobs): ?>
        <div class="dash-empty is-compact">
          <p>No AI jobs yet. Ask Mova AI to draft a page, write alt text or tidy SEO — it keeps working in the background.</p>
        </div>
      <?php else: ?>
        <ul class="dash-list">
          <?php foreach ($jobs as $j):
              $st = (string) $j['status']; ?>
            <li>
              <span class="dash-list-main">
                <span class="dash-list-title"><?= $h(mb_strimwidth((string) $j['prompt'], 0, 70, '…')) ?></span>
                <span class="dash-list-meta"><?= $h(ucfirst((string) $j['kind'])) ?><?= !empty($j['created_at']) ? ' · ' . hqTimeTag((string) $j['created_at'], 'rel') : '' ?></span>
              </span>
              <span class="dash-chip is-<?= $h($jobTone[$st] ?? 'muted') ?>"><?php if ($st === 'running'): ?><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> <?php endif; ?><?= $h($st) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </article>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <article class="dash-card dash-usage" aria-labelledby="dash-usage-h">
      <div class="dash-card-title">
        <h2 id="dash-usage-h"><i class="fa-solid fa-chart-column" aria-hidden="true"></i> AI usage · 14 days</h2>
        <a class="dash-link" href="/hq/ai-usage">Report <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
      </div>
      <?php
        $max = max(1, ...array_map(fn($d) => $d['calls'], $usage['days'] ?: [['calls' => 0]]));
        $n = max(1, count($usage['days']) - 1);
        $pts = [];
        foreach ($usage['days'] as $i => $d) {
            $pts[] = round($i / $n * 280, 1) . ',' . round(54 - $d['calls'] / $max * 48, 1);
        }
        $line = implode(' ', $pts);
      ?>
      <div class="dash-usage-stats">
        <div><strong><?= number_format($usage['calls']) ?></strong><span>calls</span></div>
        <div><strong><?= $usage['tokens'] >= 10000 ? number_format($usage['tokens'] / 1000, 0) . 'k' : number_format($usage['tokens']) ?></strong><span>tokens</span></div>
        <div class="<?= $usage['errors'] ? 'is-err' : '' ?>"><strong><?= number_format($usage['errors']) ?></strong><span>errors</span></div>
      </div>
      <svg class="dash-spark" viewBox="0 0 280 60" preserveAspectRatio="none" role="img" aria-label="AI calls per day over the last 14 days, peak <?= (int) $max ?>">
        <defs><linearGradient id="dashSparkFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#5B5CE2" stop-opacity=".28"/><stop offset="1" stop-color="#5B5CE2" stop-opacity="0"/></linearGradient></defs>
        <?php if ($line !== ''): ?>
          <polygon points="0,60 <?= $line ?> 280,60" fill="url(#dashSparkFill)" />
          <polyline points="<?= $line ?>" fill="none" stroke="#5B5CE2" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
        <?php endif; ?>
      </svg>

      <h3 class="dash-sub-h"><i class="fa-solid fa-hard-drive" aria-hidden="true"></i> Storage</h3>
      <?php
        $parts = ['uploads' => 'Uploads', 'database' => 'Database', 'backups' => 'Backups', 'cache' => 'Cache'];
        $sum = max(1, array_sum(array_map(fn($k) => (int) ($storage[$k] ?? 0), array_keys($parts))));
      ?>
      <div class="dash-stack" role="img" aria-label="Storage used: <?= $h($bytes($sum)) ?>">
        <?php foreach ($parts as $k => $label): $w = round(((int) ($storage[$k] ?? 0)) / $sum * 100, 2); if ($w <= 0) continue; ?>
          <span class="is-<?= $h($k) ?>" style="width: <?= $w ?>%" data-tip="<?= $h($label . ': ' . $bytes((int) ($storage[$k] ?? 0))) ?>"></span>
        <?php endforeach; ?>
      </div>
      <ul class="dash-legend">
        <?php foreach ($parts as $k => $label): ?>
          <li><span class="dash-swatch is-<?= $h($k) ?>" aria-hidden="true"></span><?= $h($label) ?> <strong><?= $h($bytes((int) ($storage[$k] ?? 0))) ?></strong></li>
        <?php endforeach; ?>
        <?php if (($storage['free'] ?? null) !== null): ?><li class="dash-muted">Free on disk <strong><?= $h($bytes((int) $storage['free'])) ?></strong></li><?php endif; ?>
      </ul>
    </article>
    <?php endif; ?>

    <?php
    // Restored from the pre-P5 landing: one-click entry to every workspace the user may open.
    $wsDesc = ['content' => 'Posts, pages, media, blogs and taxonomy', 'audience' => 'People, mail, sequences, feedback and insights',
        'design' => 'Brand, style, layout, components and themes', 'extend' => 'Plugins, Site AI, integrations, API keys, webhooks',
        'operations' => 'Health, security, backups, updates, AI usage', 'settings' => 'General site options and AI assist'];
    $ws = [];
    foreach (RouteRegistry::sections() as $key => $sec) {
        if ($key === 'overview') continue;
        $hub = RouteRegistry::match($sec['path']);
        if ($hub && !RouteRegistry::allowed($hub['perm'] ?? null)) continue;
        if ($key !== 'settings' && !RouteRegistry::sectionAllowed($key)) continue;
        $ws[$key] = $sec;
    }
    if (RouteRegistry::allowed('manage_appearance')) {
        $ws['templates'] = ['label' => 'Templates', 'icon' => 'fa-solid fa-table-cells-large', 'path' => '/hq/templates'];
        $wsDesc['templates'] = 'Start a page from a ready-made template';
    }
    ?>
    <?php if ($ws): ?>
    <article class="dash-card dash-workspaces" aria-labelledby="dash-ws-h">
      <div class="dash-card-title">
        <h2 id="dash-ws-h"><i class="fa-solid fa-bolt" aria-hidden="true"></i> Jump into a workspace</h2>
      </div>
      <ul class="dash-ws-grid">
        <?php foreach ($ws as $key => $sec): ?>
          <li><a class="dash-ws" href="<?= $h($sec['path']) ?>">
            <span class="dash-ws-ic" aria-hidden="true"><i class="<?= $h($sec['icon']) ?>"></i></span>
            <span class="dash-ws-body"><strong><?= $h($sec['label']) ?></strong><small><?= $h($wsDesc[$key] ?? '') ?></small></span>
            <i class="fa-solid fa-arrow-right dash-ws-go" aria-hidden="true"></i>
          </a></li>
        <?php endforeach; ?>
      </ul>
    </article>
    <?php endif; ?>
  </div>
</section>
<script>
(function () {
  var b = document.querySelector('[data-dash-ai]');
  if (b) b.addEventListener('click', function () {
    if (window.MovaAiPanel && window.MovaAiPanel.open) { window.MovaAiPanel.open(); }
    else { var t = document.querySelector('.mova-ai-btn, [data-ai-toggle]'); if (t) t.click(); }
  });
})();
</script>
