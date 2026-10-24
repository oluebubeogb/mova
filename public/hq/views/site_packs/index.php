<?php
use Mova\Backup\SitePack;
use Mova\Security\Csrf;

/** @var array $inventory @var array $groups @var array $exports @var array $imports @var array $jobs @var ?array $active @var ?array $undoable @var array $limits */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tip = static fn (string $t): string => '<span class="hq-help" tabindex="0" role="note" data-tip="' . $e($t) . '"><i class="fa-regular fa-circle-question" aria-hidden="true"></i><span class="hq-sr-only">' . $e($t) . '</span></span>';
$tab = in_array($_GET['tab'] ?? '', ['export', 'import', 'history'], true) ? $_GET['tab'] : 'export';
$exported = basename((string) ($_GET['exported'] ?? ''));
$exportNames = array_column($exports, 'name');
$statusPill = static function (array $j): string {
    if (!empty($j['undone_at'])) {
        return '<span class="sp-pill is-muted">Undone</span>';
    }
    $map = ['done' => ['is-ok', 'Imported'], 'failed' => ['is-bad', 'Failed – rolled back'], 'running' => ['is-run', 'Running'], 'queued' => ['is-run', 'Queued']];
    [$c, $l] = $map[$j['status']] ?? ['is-muted', ucfirst((string) $j['status'])];
    return '<span class="sp-pill ' . $c . '">' . $l . '</span>';
};
$spTabActive = 'packs';
include __DIR__ . '/_tabs.php';
?>
<?php if (!empty($_GET['error'])): ?><div class="alert alert-error" role="alert"><?= $e($_GET['error']) ?></div><?php endif; ?>
<?php if ($exported !== '' && in_array($exported, $exportNames, true)): ?>
    <div class="alert alert-success" role="status" data-sp-autodl="/hq/backups/packs/download?file=<?= $e(rawurlencode($exported)) ?>">
        Pack ready: <strong><?= $e($exported) ?></strong>. Your download starts automatically —
        <a href="/hq/backups/packs/download?file=<?= $e(rawurlencode($exported)) ?>">download again</a>. A copy is kept in <code>storage/exports/</code>.
    </div>
<?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?><div class="alert alert-success" role="status">Deleted <?= $e(basename((string) $_GET['deleted'])) ?>.</div><?php endif; ?>
<?php if (!empty($_GET['undone'])): ?><div class="alert alert-success" role="status">Import undone. Content, settings and media are back to how they were before it ran.</div><?php endif; ?>
<?php if ($active): ?>
    <div class="alert sp-alert-info sp-active" role="status"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
        An import of <strong><?= $e($active['pack']) ?></strong> is <?= $e($active['status']) ?> (<?= (int) $active['progress'] ?>%). <a href="/hq/backups/packs/job?id=<?= $e(rawurlencode($active['id'])) ?>">Watch progress</a></div>
<?php endif; ?>

<div class="sp-wrap" data-sp-page data-sp-tab="<?= $e($tab) ?>">
    <div class="sp-intro panel">
        <div>
            <h2 class="sp-h"><i class="fa-solid fa-right-left" aria-hidden="true"></i> Site packs</h2>
            <p class="sp-lead">Export parts of this site as a <code>.movapack</code> file, have it restyled or edited elsewhere, then import it back.
                IDs and slugs are kept, so an import updates the same pages instead of creating copies. Every import makes a full backup first and can be undone in one click.</p>
        </div>
        <div class="sp-never">
            <strong><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Never in a pack</strong>
            <?= $tip('Users and passwords, API keys, AI/provider keys, mail accounts, integration credentials and webhook secrets, any setting whose name looks like a key, token, secret or password, sessions, login history, trusted devices, page views, audit logs and AI jobs. Imports can never overwrite these either.') ?>
            <span>Users · passwords · API &amp; AI keys · mail accounts · secrets · sessions · logs</span>
        </div>
    </div>

    <div class="sp-subtabs" role="tablist" aria-label="Export or import">
        <a role="tab" href="?tab=export" data-sp-tab-link="export" class="sp-subtab"><i class="fa-solid fa-file-export" aria-hidden="true"></i> Export</a>
        <a role="tab" href="?tab=import" data-sp-tab-link="import" class="sp-subtab"><i class="fa-solid fa-file-import" aria-hidden="true"></i> Import</a>
        <a role="tab" href="?tab=history" data-sp-tab-link="history" class="sp-subtab"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> History<?php if ($jobs): ?> <span class="sp-count"><?= count($jobs) ?></span><?php endif; ?></a>
    </div>

    <!-- EXPORT -->
    <section class="panel sp-panel" data-sp-pane="export" id="sp-export" aria-label="Export">
        <form method="post" action="/hq/backups/packs/export" class="sp-export-form" data-sp-export>
            <?= Csrf::field() ?>
            <div class="sp-row-head">
                <h3>What to export</h3>
                <label class="sp-selectall"><input type="checkbox" data-sp-all checked> Select all</label>
            </div>
            <div class="sp-groups">
                <?php foreach ($groups as $gid => $g): if (!empty($g['private'])) { continue; } $inv = $inventory[$gid] ?? ['count' => 0, 'detail' => '']; ?>
                    <div class="sp-group">
                        <label class="sp-group-main">
                            <input type="checkbox" name="groups[]" value="<?= $e($gid) ?>" checked data-sp-group="<?= $e($gid) ?>">
                            <span class="sp-gicon"><i class="<?= $e($g['icon']) ?>" aria-hidden="true"></i></span>
                            <span class="sp-gtext"><strong><?= $e($g['label']) ?></strong>
                                <small><?= preg_match('/^\d/', (string) $inv['detail']) ? $e($inv['detail']) : (int) $inv['count'] . ' ' . $e($inv['detail']) ?></small></span>
                        </label>
                        <?= $tip($g['desc']) ?>
                        <?php if ($gid === 'content' && !empty($inv['types'])): ?>
                            <div class="sp-types" data-sp-types>
                                <?php foreach ($inv['types'] as $type => $t): ?>
                                    <label class="sp-chip"><input type="checkbox" name="types[]" value="<?= $e($type) ?>" checked> <?= $e($t['label']) ?> <span><?= (int) $t['count'] ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <details class="sp-private">
                <summary><i class="fa-solid fa-user-lock" aria-hidden="true"></i> Personal data (off by default)
                    <?= $tip('Subscribers, form entries and comments contain visitors\' personal data. Only include them when the person receiving the pack is allowed to handle it (for example your own staging site). IP addresses, browser strings and unsubscribe tokens are stripped even when included.') ?></summary>
                <div class="sp-groups">
                    <?php foreach ($groups as $gid => $g): if (empty($g['private'])) { continue; } $inv = $inventory[$gid] ?? ['count' => 0, 'detail' => '']; ?>
                        <div class="sp-group is-private">
                            <label class="sp-group-main">
                                <input type="checkbox" name="groups[]" value="<?= $e($gid) ?>">
                                <span class="sp-gicon"><i class="<?= $e($g['icon']) ?>" aria-hidden="true"></i></span>
                                <span class="sp-gtext"><strong><?= $e($g['label']) ?></strong><small><?= preg_match('/^\d/', (string) $inv['detail']) ? $e($inv['detail']) : (int) $inv['count'] . ' ' . $e($inv['detail']) ?></small></span>
                            </label>
                            <?= $tip($g['desc']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>

            <div class="sp-export-opts">
                <label><span class="sp-lbl">Label</span> <input type="text" name="label" maxlength="80" placeholder="e.g. for restyle, October"></label>
                <label><span class="sp-lbl">Requires Mova at least <?= $tip('Importing sites running an older Mova will refuse the pack and ask to upgrade first. Leave empty to use this site\'s version (' . $version . ').') ?></span>
                    <input type="text" name="min_version" inputmode="decimal" pattern="\d+\.\d+(\.\d+)?" placeholder="<?= $e($version) ?>"></label>
                <button type="submit" class="btn-primary" data-sp-export-btn><i class="fa-solid fa-file-export" aria-hidden="true"></i> Export pack</button>
            </div>
            <p class="sp-note">Command line: <code>php bin/mova-export.php --groups=content,design,media</code></p>
        </form>

        <h3 class="sp-sub">Stored exports <span class="sp-muted">storage/exports/</span></h3>
        <?php if ($exports === []): ?>
            <p class="empty sp-empty">No exports yet.</p>
        <?php else: ?>
            <div class="backup-table-wrap"><table class="data-table sp-table">
                <thead><tr><th>Pack</th><th>Size</th><th>Created</th><th><span class="hq-sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($exports as $x): ?>
                    <tr<?= $x['name'] === $exported ? ' class="is-new"' : '' ?>>
                        <td class="sp-name"><i class="fa-solid fa-file-zipper" aria-hidden="true"></i> <?= $e($x['name']) ?></td>
                        <td><?= $e(SitePack::fmtBytes($x['size'])) ?></td>
                        <td><?= hqTimeTag($x['mtime'], 'both') ?></td>
                        <td class="sp-actions">
                            <a class="btn-ghost btn-sm" href="/hq/backups/packs/download?file=<?= $e(rawurlencode($x['name'])) ?>"><i class="fa-solid fa-download" aria-hidden="true"></i> Download</a>
                            <form method="post" action="/hq/backups/packs/delete" onsubmit="return confirm('Delete this export?');">
                                <?= Csrf::field() ?><input type="hidden" name="kind" value="export"><input type="hidden" name="file" value="<?= $e($x['name']) ?>">
                                <button type="submit" class="btn-danger btn-sm" aria-label="Delete <?= $e($x['name']) ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <!-- IMPORT -->
    <section class="panel sp-panel" data-sp-pane="import" id="sp-import" aria-label="Import">
        <div class="sp-import-grid">
            <form method="post" action="/hq/backups/packs/upload" enctype="multipart/form-data" class="sp-upload" data-sp-upload data-max="<?= (int) $limits['effective'] ?>">
                <?= Csrf::field() ?>
                <h3>Upload a pack</h3>
                <label class="sp-drop">
                    <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                    <span data-sp-file-label>Choose a <code>.movapack</code> file</span>
                    <input type="file" name="pack" accept=".movapack,.zip" required>
                </label>
                <p class="sp-limits">Server limits: upload_max_filesize <strong><?= $e($limits['upload_max_filesize']) ?></strong> · post_max_size <strong><?= $e($limits['post_max_size']) ?></strong>
                    <?= $tip('These PHP settings cap browser uploads. For bigger packs (lots of media), upload the file by FTP/SFTP into storage/imports/ and pick it from the list. Large imports run in the background in small steps, so they also work with a low memory_limit (' . $limits['memory_limit'] . ').') ?></p>
                <p class="sp-toobig" data-sp-toobig hidden>This file is larger than the server accepts. Upload it by FTP to <code>storage/imports/</code> instead.</p>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Upload &amp; preview</button>
                <p class="sp-note">Nothing changes until you review the preview and start the import.</p>
            </form>
            <div class="sp-ftp">
                <h3>Packs in <code><?= $e($importsRel) ?></code> <?= $tip('Packs uploaded here (by the form or by FTP/SFTP) are listed below. Mova only reads .movapack/.zip files in this folder; symlinks and other files are ignored.') ?></h3>
                <?php if ($imports === []): ?>
                    <p class="empty sp-empty">No packs yet. Upload one, or copy it by FTP into <code>storage/imports/</code> and reload.</p>
                <?php else: ?>
                    <ul class="sp-list">
                        <?php foreach ($imports as $x): ?>
                            <li>
                                <span class="sp-name"><i class="fa-solid fa-file-zipper" aria-hidden="true"></i> <?= $e($x['name']) ?>
                                    <small><?= $e(SitePack::fmtBytes($x['size'])) ?> · <?= hqTimeTag($x['mtime'], 'both') ?></small></span>
                                <span class="sp-actions">
                                    <a class="btn-secondary btn-sm" href="/hq/backups/packs/preview?pack=<?= $e(rawurlencode($x['name'])) ?>">Preview</a>
                                    <form method="post" action="/hq/backups/packs/delete" onsubmit="return confirm('Delete this pack from storage/imports?');">
                                        <?= Csrf::field() ?><input type="hidden" name="kind" value="import"><input type="hidden" name="file" value="<?= $e($x['name']) ?>">
                                        <button type="submit" class="btn-danger btn-sm" aria-label="Delete <?= $e($x['name']) ?>"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                    </form>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <p class="sp-note">Command line: <code>php bin/mova-import.php pack.movapack --dry-run</code> · <code>--apply</code></p>
    </section>

    <!-- HISTORY -->
    <section class="panel sp-panel" data-sp-pane="history" id="sp-history" aria-label="Import history">
        <h3>Import history</h3>
        <?php if ($jobs === []): ?>
            <p class="empty sp-empty">No imports yet.</p>
        <?php else: ?>
            <div class="backup-table-wrap"><table class="data-table sp-table">
                <thead><tr><th>Pack</th><th>Status</th><th>Changes</th><th>Who</th><th>When</th><th><span class="hq-sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($jobs as $j): $t = (array) ($j['totals'] ?? []); ?>
                    <tr>
                        <td class="sp-name"><a href="/hq/backups/packs/job?id=<?= $e(rawurlencode($j['id'])) ?>"><?= $e($j['pack']) ?></a></td>
                        <td><?= $statusPill($j) ?></td>
                        <td class="sp-muted"><?= $t ? (int) ($t['update'] ?? 0) . ' updated · ' . (int) ($t['add'] ?? 0) . ' added' . (!empty($t['remove']) ? ' · ' . (int) $t['remove'] . ' removed' : '') : '—' ?></td>
                        <td><?= $e($j['user'] ?: '—') ?><?= ($j['via'] ?? '') === 'cli' && $j['user'] !== 'CLI' ? ' <span class="sp-muted">(CLI)</span>' : '' ?></td>
                        <td><?= hqTimeTag($j['created'], 'both') ?>
                            <?php if (!empty($j['undone_at'])): ?><br><small class="sp-muted">Undone <?= hqTimeTag($j['undone_at'], 'both') ?><?= !empty($j['undone_by']) ? ' by ' . $e($j['undone_by']) : '' ?></small><?php endif; ?></td>
                        <td class="sp-actions">
                            <?php if ($undoable && $undoable['id'] === $j['id']): ?>
                                <form method="post" action="/hq/backups/packs/undo" onsubmit="return confirm('Undo this import? Content, settings and media go back to how they were before it ran. Edits made since then to the same items are lost.');">
                                    <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $e($j['id']) ?>">
                                    <button type="submit" class="btn-secondary btn-sm"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Undo</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <p class="sp-note">Only the most recent import can be undone. Older ones can be rolled back from the automatic backup listed on the Backups tab.</p>
        <?php endif; ?>
    </section>
</div>
<script src="/assets/js/site-packs.js?v=20261024sp" defer></script>
