<?php use Mova\Security\Csrf; $backups = $backups ?? []; ?>

<?php if (!empty($_GET['created'])): ?>
    <div class="alert alert-success">Backup created.</div>
<?php endif; ?>
<?php if (!empty($_GET['restored'])): ?>
    <div class="alert alert-success">Backup restored.</div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars((string) $_GET['error']) ?></div>
<?php endif; ?>
<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- 1) Toolbar: create site backup -->
<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;">Backups</h2>
    <form method="post" action="/hq/backups/create">
        <?= Csrf::field() ?>
        <button type="submit" class="btn-primary">Create backup</button>
    </form>
</div>

<!-- 2) Backup list card (site .mova archives) -->
<div class="panel backups-list-panel">
    <p style="color:var(--hq-muted);font-size:0.9rem;">Backups are <code>.mova</code> archives containing the database, settings, and media uploads.</p>
    <?php if (empty($backups)): ?>
        <p class="empty">No backups yet.</p>
    <?php else: ?>
        <div class="backup-table-wrap">
            <table class="data-table backup-table">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Size</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($backups as $b): ?>
                    <tr>
                        <td class="backup-file"><code><?= htmlspecialchars($b['name']) ?></code></td>
                        <td class="backup-size"><?= number_format($b['size'] / 1024, 1) ?> KB</td>
                        <td class="backup-date"><?= date('M j, Y H:i', $b['mtime']) ?></td>
                        <td class="backup-actions">
                            <div class="backup-actions-inner">
                                <a class="btn-ghost btn-sm" href="/hq/backups/download?file=<?= urlencode($b['name']) ?>">Download</a>
                                <form method="post" action="/hq/backups/restore" onsubmit="return confirm('Restore this backup? Current data will be overwritten.');">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="file" value="<?= htmlspecialchars($b['name']) ?>">
                                    <button type="submit" class="btn-secondary btn-sm">Restore</button>
                                </form>
                                <form method="post" action="/hq/backups/delete" onsubmit="return confirm('Delete this backup file?');">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="file" value="<?= htmlspecialchars($b['name']) ?>">
                                    <button type="submit" class="btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        $page = $page ?? 1;
        $perPage = $perPage ?? 25;
        $total = $total ?? count($backups);
        $totalPages = $totalPages ?? 1;
        $allowedPerPage = $allowedPerPage ?? [10, 25, 50, 100];
        if ($total > 0):
        ?>
        <nav class="pagination" style="display:flex;align-items:center;justify-content:space-between;margin-top:1.25rem;flex-wrap:wrap;gap:0.75rem;">
            <div style="font-size:0.85rem;color:var(--hq-muted);display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                <span>Page <?= (int)$page ?> of <?= (int)$totalPages ?> · <?= (int)$total ?> total</span>
                <label for="backup_per_page" style="margin-left:0.5rem;">Show</label>
                <select id="backup_per_page" onchange="window.location.href=this.value" style="width:auto;padding:0.3rem 0.5rem;">
                    <?php foreach ($allowedPerPage as $n): ?>
                        <option value="/hq/backups?per_page=<?= (int)$n ?>&page=1" <?= $perPage === $n ? 'selected' : '' ?>><?= (int)$n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;">
                <?php if ($page > 1): ?>
                    <a class="btn-ghost btn-sm" href="/hq/backups?page=<?= $page - 1 ?>&per_page=<?= (int)$perPage ?>">← Prev</a>
                <?php endif; ?>
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($p = $start; $p <= $end; $p++):
                ?>
                    <a class="btn-ghost btn-sm" href="/hq/backups?page=<?= $p ?>&per_page=<?= (int)$perPage ?>"
                       style="<?= $p === $page ? 'border-color:var(--hq-accent);color:var(--hq-accent);' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="btn-ghost btn-sm" href="/hq/backups?page=<?= $page + 1 ?>&per_page=<?= (int)$perPage ?>">Next →</a>
                <?php endif; ?>
            </div>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- 3) Get Mova zip — MUST sit below the backups list panel -->
<div class="panel hq-card mova-export-card">
    <h3 style="margin:0 0 0.35rem;font-size:1rem;">Get Mova zip</h3>
    <p style="margin:0 0 1rem;color:var(--hq-muted);font-size:0.9rem;line-height:1.45;">
        Download a full install package (same layout as a fresh Mova zip). Upload and extract on another host for a new install.
        Optionally include this site’s database, config, and media.
    </p>
    <form method="post" action="/hq/backups/export-mova" class="mova-export-form">
        <?= Csrf::field() ?>
        <div class="mova-export-fields">
            <label class="mova-export-check">
                <input type="checkbox" name="include_site_data" value="1">
                Include my site data (database, config, uploads)
            </label>
            <div class="form-group mova-export-password">
                <label for="export_password">Your account password</label>
                <input type="password" name="password" id="export_password" required autocomplete="current-password" placeholder="Confirm password to export">
            </div>
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-file-zipper"></i> Download Mova zip
            </button>
        </div>
    </form>
    <p style="margin:0.75rem 0 0;font-size:0.8rem;color:var(--hq-muted);">
        Password of the logged-in user is required. Without site data → clean core. With site data → portable copy of this site (sensitive).
    </p>
</div>
