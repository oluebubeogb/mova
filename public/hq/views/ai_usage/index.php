<?php
/** @var array $report @var int $days */
$t = $report['totals'] ?? [];
$n = static fn($v) => number_format((int) ($v ?? 0));
$h = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$byDay = $report['by_day'] ?? [];
$maxCalls = 1;
foreach ($byDay as $r) { $maxCalls = max($maxCalls, (int) $r['calls']); }
$featureLabels = [
    'hq.assist' => 'HQ assistant', 'hq.chat' => 'HQ chat', 'site.chat' => 'Site AI (visitors)',
    'image' => 'Image generation', 'site_ai.chat' => 'Site AI (visitors)', 'wizard.copy' => 'Quick setup · copy', 'studio' => 'Studio', 'setup' => 'Quick setup', 'seo' => 'SEO',
];
$featureName = static function (string $f) use ($featureLabels): string {
    if (isset($featureLabels[$f])) { return $featureLabels[$f]; }
    if (str_starts_with($f, 'assist.')) { return 'Assist · ' . str_replace('_', ' ', substr($f, 7)); }
    return ucfirst(str_replace(['.', '_'], [' · ', ' '], $f));
};
?>
<div class="hq-landing-head" id="ai-usage-top">
  <div>
    <h1 class="hq-landing-title">AI usage</h1>
    <p class="hq-landing-desc">Every AI request this site makes, logged locally — by day, feature and model.
      <?= $gateway ? 'Requests go through the Mova AI gateway and carry this site\'s ID.' : 'This site uses a custom AI endpoint.' ?></p>
  </div>
  <div class="ai-usage-range" role="group" aria-label="Date range">
    <?php foreach ([1 => 'Today', 7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '1 year'] as $d => $lbl): ?>
      <a href="/hq/ai-usage?days=<?= $d ?>" class="hq-chip<?= $d === $days ? ' is-active' : '' ?>"<?= $d === $days ? ' aria-current="true"' : '' ?>><?= $lbl ?></a>
    <?php endforeach; ?>
    <a href="/hq/ai-usage?days=<?= $days ?>&amp;format=csv" class="hq-chip">Export CSV</a>
  </div>
</div>

<?php if (!empty($report['error'])): ?>
  <div class="panel" role="alert">Usage table unavailable: <?= $h($report['error']) ?></div>
<?php endif; ?>

<div class="ai-usage-stats">
  <div class="panel ai-stat"><span class="ai-stat-label">Requests</span><strong><?= $n($t['calls'] ?? 0) ?></strong><span class="ai-stat-sub"><?= $n($t['errors'] ?? 0) ?> failed</span></div>
  <div class="panel ai-stat"><span class="ai-stat-label">Total tokens</span><strong><?= $n($t['total_tokens'] ?? 0) ?></strong><span class="ai-stat-sub"><?= $n($t['prompt_tokens'] ?? 0) ?> in · <?= $n($t['completion_tokens'] ?? 0) ?> out</span></div>
  <div class="panel ai-stat"><span class="ai-stat-label">Avg latency</span><strong><?= $n($t['avg_latency_ms'] ?? 0) ?> ms</strong><span class="ai-stat-sub"><?= $n($t['estimated_calls'] ?? 0) ?> estimated counts</span></div>
  <div class="panel ai-stat"><span class="ai-stat-label">Provider</span><strong style="font-size:1rem"><?= $h($model) ?></strong><span class="ai-stat-sub"><?= $h(parse_url((string) $endpoint, PHP_URL_HOST) ?: $endpoint) ?></span></div>
</div>

<?php
$gwT = is_array($gw ?? null) ? ($gw['totals'] ?? []) : null;
$gwQuota = is_array($gw ?? null) ? ($gw['quota'] ?? null) : null;
?>
<div class="panel ai-gw-panel" id="ai-usage-gateway">
  <div class="ai-gw-head">
    <h3 style="margin:0">Gateway-reported usage</h3>
    <a class="hq-chip" href="/hq/ai-usage?days=<?= $days ?>&amp;refresh=1#ai-usage-gateway">Refresh</a>
  </div>
  <?php if ($gwT === null): ?>
    <p class="hq-muted" style="margin:.4rem 0 0">
      <?php if (empty($gwCaps['usage'])): ?>
        This AI endpoint does not publish per-site usage (needs Mova AI gateway 1.4+ with the usage ledger on). The numbers below are Mova's own log.
      <?php else: ?>
        Could not load usage from the gateway just now — try Refresh. The numbers below are Mova's own log.
      <?php endif; ?>
    </p>
  <?php else: ?>
    <p class="hq-muted" style="margin:.35rem 0 .8rem">What the gateway billed to site <code><?= $h($gw['site'] ?? $siteId) ?></code> since <?= $h($gw['since'] ?? '') ?> · fetched <?= $h(substr((string) ($gw['fetched_at'] ?? ''), 0, 16)) ?></p>
    <div class="ai-usage-stats ai-usage-stats--gw">
      <div class="ai-stat"><span class="ai-stat-label">Requests</span><strong><?= $n($gwT['requests'] ?? 0) ?></strong><span class="ai-stat-sub"><?= $n($gwT['errors'] ?? 0) ?> failed · Mova logged <?= $n($t['calls'] ?? 0) ?></span></div>
      <div class="ai-stat"><span class="ai-stat-label">Tokens</span><strong><?= $n($gwT['total_tokens'] ?? 0) ?></strong><span class="ai-stat-sub"><?= $n($gwT['prompt_tokens'] ?? 0) ?> in · <?= $n($gwT['completion_tokens'] ?? 0) ?> out</span></div>
      <div class="ai-stat"><span class="ai-stat-label">Images</span><strong><?= $n($gwT['images'] ?? 0) ?></strong><span class="ai-stat-sub"><?= $n($gwT['estimated_requests'] ?? 0) ?> estimated counts</span></div>
      <?php if (is_array($gwQuota)): $lim = $gwQuota; $use = $gw['site_totals'] ?? []; ?>
      <div class="ai-stat"><span class="ai-stat-label">Quota today</span><strong><?= $n($use['day_tokens'] ?? 0) ?><?php if (!empty($lim['daily_tokens'])): ?> / <?= $n($lim['daily_tokens']) ?><?php endif; ?></strong><span class="ai-stat-sub"><?php if (!empty($lim['monthly_tokens'])): ?>Month <?= $n($use['month_tokens'] ?? 0) ?> / <?= $n($lim['monthly_tokens']) ?><?php else: ?>No monthly cap<?php endif; ?></span></div>
      <?php endif; ?>
    </div>
    <?php if (!empty($gw['by_feature'])): ?>
    <div class="hq-table-wrap"><table class="hq-table">
      <thead><tr><th>Feature (as tagged by Mova)</th><th>Requests</th><th>Tokens</th><th>Images</th><th>Failed</th></tr></thead><tbody>
      <?php foreach ($gw['by_feature'] as $r): ?>
        <tr><td><?= $h($featureName((string) ($r['feature'] ?? ''))) ?> <code class="hq-muted" style="font-size:.72rem"><?= $h($r['feature'] ?? '') ?></code></td><td><?= $n($r['requests'] ?? 0) ?></td><td><?= $n($r['total_tokens'] ?? 0) ?></td><td><?= $n($r['images'] ?? 0) ?></td><td><?= $n($r['errors'] ?? 0) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="panel" id="ai-usage-daily">
  <h3 style="margin-top:0">By day</h3>
  <?php if (!$byDay): ?>
    <p class="hq-muted">No AI requests in this period yet. Use the assistant (✦) or Site AI and they will show up here.</p>
  <?php else: ?>
  <div class="ai-usage-bars" role="img" aria-label="Requests per day">
    <?php foreach (array_reverse($byDay) as $r): $pct = max(3, (int) round(((int) $r['calls'] / $maxCalls) * 100)); ?>
      <div class="ai-usage-bar" style="--h:<?= $pct ?>%" title="<?= $h($r['day']) ?>: <?= (int) $r['calls'] ?> requests, <?= (int) $r['total_tokens'] ?> tokens"><span></span></div>
    <?php endforeach; ?>
  </div>
  <div class="hq-table-wrap"><table class="hq-table">
    <thead><tr><th>Day</th><th>Requests</th><th>Failed</th><th>Prompt</th><th>Completion</th><th>Total tokens</th><th>Avg ms</th></tr></thead>
    <tbody>
    <?php foreach ($byDay as $r): ?>
      <tr><td><?= $h($r['day']) ?></td><td><?= $n($r['calls']) ?></td><td><?= $n($r['errors']) ?></td><td><?= $n($r['prompt_tokens']) ?></td><td><?= $n($r['completion_tokens']) ?></td><td><?= $n($r['total_tokens']) ?></td><td><?= $n($r['avg_latency_ms']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<div class="ai-usage-split">
  <div class="panel" id="ai-usage-features">
    <h3 style="margin-top:0">By feature</h3>
    <?php if (empty($report['by_feature'])): ?><p class="hq-muted">Nothing yet.</p><?php else: ?>
    <table class="hq-table"><thead><tr><th>Feature</th><th>Requests</th><th>Tokens</th><th>Failed</th></tr></thead><tbody>
    <?php foreach ($report['by_feature'] as $r): ?>
      <tr><td><?= $h($featureName((string) $r['feature'])) ?> <code class="hq-muted" style="font-size:.72rem"><?= $h($r['feature']) ?></code></td><td><?= $n($r['calls']) ?></td><td><?= $n($r['total_tokens']) ?></td><td><?= $n($r['errors']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table><?php endif; ?>
  </div>
  <div class="panel" id="ai-usage-models">
    <h3 style="margin-top:0">By model</h3>
    <?php if (empty($report['by_model'])): ?><p class="hq-muted">Nothing yet.</p><?php else: ?>
    <table class="hq-table"><thead><tr><th>Model</th><th>Requests</th><th>Tokens</th></tr></thead><tbody>
    <?php foreach ($report['by_model'] as $r): ?>
      <tr><td><?= $h($r['model'] ?: '—') ?></td><td><?= $n($r['calls']) ?></td><td><?= $n($r['total_tokens']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table><?php endif; ?>
  </div>
</div>

<div class="panel" id="ai-usage-recent">
  <h3 style="margin-top:0">Recent requests</h3>
  <?php if (empty($report['recent'])): ?><p class="hq-muted">Nothing yet.</p><?php else: ?>
  <div class="hq-table-wrap"><table class="hq-table">
    <thead><tr><th>When</th><th>Feature</th><th>Kind</th><th>Model</th><th>Tokens</th><th>ms</th><th>Status</th><th>User</th></tr></thead><tbody>
    <?php foreach ($report['recent'] as $r): ?>
      <tr>
        <td><?= $h(substr((string) $r['created_at'], 0, 16)) ?></td>
        <td><?= $h($featureName((string) $r['feature'])) ?></td>
        <td><?= $h($r['kind']) ?></td>
        <td><?= $h($r['model']) ?></td>
        <td><?= $r['total_tokens'] !== null ? $n($r['total_tokens']) . (!empty($r['estimated']) ? ' <span class="hq-muted" title="Gateway sent no usage; estimated">≈</span>' : '') : '—' ?></td>
        <td><?= $n($r['latency_ms']) ?></td>
        <td><?php if ($r['status'] === 'ok'): ?><span class="hq-badge hq-badge-ok">ok</span><?php else: ?><span class="hq-badge hq-badge-err" title="<?= $h($r['error']) ?>">error</span><?php endif; ?></td>
        <td><?= $h($r['user_name'] ?? ($r['user_id'] ? '#' . $r['user_id'] : 'visitor')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<div class="panel" id="ai-usage-site">
  <h3 style="margin-top:0">Site identity sent to the gateway</h3>
  <p style="font-size:.85rem;margin:.25rem 0">Every request carries <code>X-Mova-Site: <?= $h($siteId) ?></code>, <code>X-Mova-Site-Host: <?= $h($siteHost) ?></code> and <code>X-Mova-Feature</code>, so the gateway can attribute usage per site.</p>
  <p class="hq-muted" style="font-size:.8rem;margin:0">Token counts marked ≈ are estimated locally because the endpoint did not return a <code>usage</code> block. <a href="/hq/settings#ai">AI settings →</a></p>
</div>

<style>
.ai-usage-range{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}
.hq-chip{display:inline-flex;align-items:center;padding:.3rem .7rem;border-radius:999px;border:1px solid var(--hq-border);font-size:.78rem;color:var(--hq-text);text-decoration:none}
.hq-chip.is-active{background:var(--hq-accent-fill);border-color:var(--hq-accent);color:#fff}
.ai-usage-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin:1rem 0}
.ai-stat{display:flex;flex-direction:column;gap:.2rem}
.ai-stat strong{font-size:1.6rem;letter-spacing:-.02em}
.ai-stat-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--hq-muted)}
.ai-stat-sub{font-size:.78rem;color:var(--hq-muted)}
.ai-usage-bars{display:flex;align-items:flex-end;gap:3px;height:110px;margin:.5rem 0 1rem;padding:.5rem;border-radius:10px;background:var(--hq-surface-2,rgba(127,127,127,.06))}
.ai-usage-bar{flex:1;height:100%;display:flex;align-items:flex-end}
.ai-usage-bar span{display:block;width:100%;height:var(--h);border-radius:4px 4px 0 0;background:linear-gradient(180deg,var(--hq-accent),color-mix(in srgb,var(--hq-accent) 55%,transparent))}
.ai-usage-split{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1rem;margin:1rem 0}
#ai-usage-daily,#ai-usage-recent,#ai-usage-site{margin-top:1rem}
.ai-gw-panel{border-left:3px solid var(--hq-accent)}
.ai-gw-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem}
.ai-usage-stats--gw{margin:.25rem 0 .75rem}
.ai-usage-stats--gw .ai-stat{padding:.75rem .9rem;border:1px solid var(--hq-border);border-radius:10px}
.hq-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.hq-table{width:100%;border-collapse:collapse;font-size:.84rem;font-variant-numeric:tabular-nums}
.hq-table th{text-align:left;font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--hq-muted);padding:.55rem .75rem;border-bottom:1px solid var(--hq-border);white-space:nowrap}
.hq-table td{padding:.55rem .75rem;border-bottom:1px solid color-mix(in srgb,var(--hq-border) 60%,transparent);white-space:nowrap}
.hq-table tbody tr:last-child td{border-bottom:0}
.hq-table tbody tr:hover td{background:color-mix(in srgb,var(--hq-accent) 6%,transparent)}
.hq-table code{font-size:.72rem;color:var(--hq-muted);margin-left:.35rem}
.hq-badge-ok{color:var(--hq-success-text,#166534)}.hq-badge-err{color:var(--hq-danger-text,#b91c1c)}
</style>
