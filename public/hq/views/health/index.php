<?php
$report = $report ?? ['overall' => 'ok', 'checks' => []];
$statusColor = [
    'ok' => 'var(--hq-success)',
    'warn' => 'var(--hq-warning)',
    'error' => 'var(--hq-danger)',
    'info' => 'var(--hq-muted)',
];
?>


<?php if (!empty($requirements)): ?>
<div class="panel" style="margin-bottom:1.25rem;">
    <h3 style="margin-top:0;">PHP &amp; environment</h3>
    <ul style="list-style:none;padding:0;margin:0;font-size:0.85rem;">
    <?php foreach ($requirements['items'] as $item): ?>
        <li style="padding:0.3rem 0;border-bottom:1px solid var(--hq-border);display:flex;gap:0.5rem;">
            <span style="color:<?= $item['ok'] ? '#15803d' : ($item['required'] ? '#b91c1c' : '#b45309') ?>"><?= $item['ok'] ? 'OK' : 'NO' ?></span>
            <span><?= htmlspecialchars($item['label']) ?> — <span style="color:var(--hq-muted)"><?= htmlspecialchars($item['detail']) ?></span></span>
        </li>
    <?php endforeach; ?>
    </ul>
    <p style="font-size:0.8rem;color:var(--hq-muted);margin:0.75rem 0 0;">
        On a VPS: <code>sudo bash bin/install-php-deps.sh</code>
    </p>
</div>
<?php endif; ?>

<div class="panel" style="margin-bottom:1.25rem;">
    <h3 style="margin-top:0;">Overall: <span style="color:<?= $statusColor[$report['overall']] ?? 'inherit' ?>"><?= htmlspecialchars(strtoupper($report['overall'])) ?></span></h3>
    <p style="font-size:0.85rem;color:var(--hq-muted);margin:0;">
        Mova <?= htmlspecialchars($report['version'] ?? '') ?> · <?= htmlspecialchars($report['time'] ?? '') ?>
    </p>
</div>

<div class="ops-health-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <?php foreach ($report['checks'] as $name => $check): ?>
        <div class="panel" style="margin:0;">
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;">
                <span style="width:10px;height:10px;border-radius:50%;background:<?= $statusColor[$check['status']] ?? '#999' ?>;"></span>
                <strong style="text-transform:capitalize;"><?= htmlspecialchars($name) ?></strong>
            </div>
            <div style="font-size:0.85rem;color:var(--hq-muted);"><?= htmlspecialchars($check['detail']) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="form-layout">
    <div class="form-main">
        <div class="panel">
            <h3>Recent audit log</h3>
            <?php if (empty($recentAudit)): ?>
                <p class="empty" style="padding:1rem;">No audit entries.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>When</th><th>Action</th><th>Entity</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentAudit as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($row['created_at']))) ?></td>
                            <td><code><?= htmlspecialchars($row['action']) ?></code></td>
                            <td><?= htmlspecialchars(($row['entity_type'] ?? '') . ' ' . ($row['entity_id'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <aside class="form-sidebar">
        <div class="panel">
            <h3>Login history</h3>
            <?php if (empty($recentLogins)): ?>
                <p style="font-size:0.85rem;color:var(--hq-muted);">No logins recorded yet.</p>
            <?php else: ?>
                <div style="font-size:0.8rem;max-height:360px;overflow:auto;">
                    <?php foreach ($recentLogins as $l): ?>
                        <div style="padding:0.45rem 0;border-bottom:1px solid var(--hq-border);">
                            <div><?= htmlspecialchars($l['username'] ?? '') ?>
                                <span style="color:<?= !empty($l['success']) ? 'var(--hq-success)' : 'var(--hq-danger)' ?>">
                                    <?= !empty($l['success']) ? 'OK' : 'FAIL' ?>
                                </span>
                            </div>
                            <div style="color:var(--hq-muted);"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($l['created_at']))) ?>
                                · <?= htmlspecialchars($l['ip_address'] ?? '') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<style>
.ops-health-grid .panel{border-radius:12px}
.form-layout .panel{border-radius:12px;overflow:hidden}
.form-layout .data-table{width:100%;border-collapse:collapse}
.form-layout .data-table th{text-align:left;font-size:0.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--hq-muted);padding:.65rem .85rem;border-bottom:1px solid var(--hq-border)}
.form-layout .data-table td{padding:.7rem .85rem;border-bottom:1px solid var(--hq-border);font-size:.9rem}
</style>
