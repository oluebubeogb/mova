<?php
use Mova\Backup\SitePack;
use Mova\Security\Csrf;

/** @var string $pack @var int $packSize @var array $errors @var bool $versionBlocked @var array $manifest @var ?array $plan @var ?array $active */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tip = static fn (string $t): string => '<span class="hq-help" tabindex="0" role="note" data-tip="' . $e($t) . '"><i class="fa-regular fa-circle-question" aria-hidden="true"></i><span class="hq-sr-only">' . $e($t) . '</span></span>';
$defs = SitePack::groups();
$labels = ['add' => 'Add', 'update' => 'Update', 'unchanged' => 'Unchanged', 'conflict' => 'Conflict', 'remove' => 'Remove', 'skip' => 'Skip', 'blocked' => 'Blocked'];
$pill = static fn (string $s): string => '<span class="sp-pill is-' . $e($s) . '">' . $e($labels[$s] ?? ucfirst($s)) . '</span>';
$opts = $opts ?? ['groups' => [], 'add_new' => true, 'remove_missing' => false];
$packGroups = $packGroups ?? [];
$spTabActive = 'packs';
include __DIR__ . '/_tabs.php';
?>
<p class="sp-back"><a href="/hq/backups/packs?tab=import"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Export &amp; import</a></p>
<?php if (!empty($_GET['error'])): ?><div class="alert alert-error" role="alert"><?= $e($_GET['error']) ?></div><?php endif; ?>

<div class="sp-wrap">
<section class="panel sp-panel sp-pack-head">
    <div class="sp-pack-title">
        <span class="sp-gicon is-lg"><i class="fa-solid fa-file-zipper" aria-hidden="true"></i></span>
        <div>
            <h2 class="sp-h"><?= $e($pack) ?></h2>
            <p class="sp-muted"><?= $e(SitePack::fmtBytes($packSize)) ?>
            <?php if ($manifest): ?>
                · from <strong><?= $e($manifest['site']['name'] ?? '') ?></strong> (<?= $e($manifest['site']['host'] ?? '') ?>)
                · exported <?= hqTimeTag($manifest['exported_at'] ?? '', 'both') ?>
                · Mova <?= $e($manifest['mova_version'] ?? '?') ?> (needs ≥ <?= $e($manifest['min_mova_version'] ?? '?') ?>)
                <?php if (!empty($manifest['repacked_at'])): ?>· edited <?= hqTimeTag($manifest['repacked_at'], 'both') ?><?php endif; ?>
                <?php if (!empty($manifest['label'])): ?>· “<?= $e($manifest['label']) ?>”<?php endif; ?>
            <?php endif; ?></p>
        </div>
    </div>
</section>

<?php if ($errors): ?>
    <section class="panel sp-panel">
        <?php if ($versionBlocked): ?>
            <div class="alert alert-error" role="alert"><strong><i class="fa-solid fa-circle-up" aria-hidden="true"></i> Upgrade Mova first.</strong>
                <?= $e(implode(' ', $errors)) ?> Go to <a href="/hq/updates">Updates</a>, then preview the pack again.</div>
        <?php else: ?>
            <div class="alert alert-error" role="alert"><strong>This pack can't be imported.</strong>
                <ul class="sp-errs"><?php foreach (array_slice($errors, 0, 12) as $er): ?><li><?= $e($er) ?></li><?php endforeach; ?></ul>
                Nothing was changed.</div>
        <?php endif; ?>
    </section>
<?php elseif ($plan): $t = $plan['totals']; ?>

    <form method="get" action="/hq/backups/packs/preview" class="panel sp-panel sp-options" data-sp-options>
        <input type="hidden" name="pack" value="<?= $e($pack) ?>"><input type="hidden" name="applied" value="1"><input type="hidden" name="g[]" value="">
        <div class="sp-row-head"><h3>Import options</h3><span class="sp-muted">Dry run · nothing has changed yet</span></div>
        <div class="sp-opt-groups">
            <?php foreach ($packGroups as $gid): $c = $plan['groups'][$gid]['counts'] ?? null; ?>
                <label class="sp-chip"><input type="checkbox" name="g[]" value="<?= $e($gid) ?>"<?= in_array($gid, $opts['groups'], true) ? ' checked' : '' ?> data-sp-autosubmit>
                    <i class="<?= $e($defs[$gid]['icon']) ?>" aria-hidden="true"></i> <?= $e($defs[$gid]['label']) ?></label>
            <?php endforeach; ?>
        </div>
        <div class="sp-opt-flags">
            <label class="sp-switch"><input type="checkbox" name="add_new" value="1"<?= $opts['add_new'] ? ' checked' : '' ?> data-sp-autosubmit>
                <span>Add new items</span> <?= $tip('Items in the pack that this site doesn\'t have yet (new pages, media, templates…) are created with the same ID. Untick to only update what already exists.') ?></label>
            <label class="sp-switch is-danger"><input type="checkbox" name="remove_missing" value="1"<?= $opts['remove_missing'] ? ' checked' : '' ?> data-sp-autosubmit>
                <span>Remove items not in the pack</span> <?= $tip('Deletes items of the selected groups that exist on this site but are missing from the pack — for example pages the designer deleted. Only use this with a pack exported from this site. It can be undone, but you must confirm it.') ?></label>
            <noscript><button type="submit" class="btn-secondary btn-sm">Update preview</button></noscript>
        </div>
    </form>

    <section class="panel sp-panel">
        <div class="sp-totals" role="list">
            <?php foreach (['update', 'add', 'unchanged', 'conflict', 'remove', 'skip'] as $k): if ($k === 'remove' && empty($t[$k])) { continue; } ?>
                <div class="sp-total is-<?= $k ?>" role="listitem"><strong><?= (int) ($t[$k] ?? 0) ?></strong><span><?= $e($k === 'skip' ? 'Skipped' : $labels[$k]) ?></span></div>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($plan['blocked'])): ?>
            <p class="sp-blocked"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> <?= count($plan['blocked']) ?> protected setting(s) in the pack will be ignored
                <?= $tip('Secret or internal settings (keys, tokens, passwords, mail and AI provider settings) are never imported, even if someone added them to the pack: ' . implode(', ', array_map(static fn ($b) => preg_replace('/^settings\.[a-z_]+: /', '', $b), array_slice($plan['blocked'], 0, 20)))) ?></p>
        <?php endif; ?>
        <?php if (!empty($t['conflict'])): ?>
            <p class="sp-conflict-note"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Conflicts are skipped. Where it's safe, tick <em>Overwrite</em> to import that item anyway.</p>
        <?php endif; ?>

        <label class="sp-showall"><input type="checkbox" data-sp-show-unchanged> Show unchanged items</label>

        <?php foreach ($plan['groups'] as $gid => $g): $c = $g['counts']; $changed = $c['add'] + $c['update'] + $c['conflict'] + $c['remove'] + $c['skip']; ?>
            <details class="sp-pgroup"<?= $changed > 0 ? ' open' : '' ?>>
                <summary>
                    <span class="sp-gicon"><i class="<?= $e($g['icon']) ?>" aria-hidden="true"></i></span>
                    <strong><?= $e($g['label']) ?></strong>
                    <span class="sp-pcounts">
                        <?php foreach (['update', 'add', 'conflict', 'remove', 'skip', 'unchanged'] as $k): if (empty($c[$k])) { continue; } ?>
                            <span class="sp-pill is-<?= $k ?>"><?= (int) $c[$k] ?> <?= $e(strtolower($k === 'skip' ? 'skipped' : $labels[$k])) ?></span>
                        <?php endforeach; ?>
                        <?php if (!array_filter($c)): ?><span class="sp-muted">nothing in pack</span><?php endif; ?>
                    </span>
                </summary>
                <?php if ($g['items']): ?>
                <div class="backup-table-wrap"><table class="data-table sp-table sp-items">
                    <thead><tr><th>Status</th><th>Item</th><th>Changes</th><th><span class="hq-sr-only">Details</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($g['items'] as $it): $st = (string) $it['status']; $ov = $it['table'] . ':' . $it['id']; ?>
                        <tr class="sp-st-<?= $e($st) ?>"<?= $st === 'unchanged' ? ' data-sp-unchanged hidden' : '' ?>>
                            <td><?= $pill(!empty($it['override']) ? 'update' : $st) ?></td>
                            <td class="sp-item">
                                <strong><?= $e($it['title'] !== '' ? $it['title'] : $it['key']) ?></strong>
                                <small><?= $e($it['type']) ?><?= is_numeric($it['id']) ? ' · #' . (int) $it['id'] : '' ?><?= ($it['key'] !== '' && $it['key'] !== (string) $it['id']) ? ' · ' . $e($it['key']) : '' ?></small>
                            </td>
                            <td class="sp-fields">
                                <?php if ($it['reason'] !== ''): ?><span class="sp-reason"><?= $e($it['reason']) ?></span><?php endif; ?>
                                <?php if ($it['fields']): ?>
                                    <span class="sp-fcount"><?= count($it['fields']) ?> field<?= count($it['fields']) === 1 ? '' : 's' ?>:</span>
                                    <?php foreach (array_slice($it['fields'], 0, 8) as $f): ?><code><?= $e($f) ?></code> <?php endforeach; ?>
                                    <?php if (count($it['fields']) > 8): ?><span class="sp-muted">+<?= count($it['fields']) - 8 ?> more</span><?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="sp-actions">
                                <?php if (!empty($it['diff'])): ?>
                                    <a class="btn-ghost btn-sm" href="/hq/backups/packs/diff?pack=<?= $e(rawurlencode($pack)) ?>&amp;id=<?= (int) $it['id'] ?>" target="_blank" rel="noopener"><i class="fa-solid fa-code-compare" aria-hidden="true"></i> Body diff</a>
                                <?php endif; ?>
                                <?php if ($st === 'conflict' && !empty($it['overridable'])): ?>
                                    <label class="sp-override"><input type="checkbox" name="overrides[]" value="<?= $e($ov) ?>" form="sp-import-form"> Overwrite</label>
                                <?php elseif (!empty($it['override'])): ?>
                                    <span class="sp-muted">overridden</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php endif; ?>
            </details>
        <?php endforeach; ?>
    </section>

    <form method="post" action="/hq/backups/packs/import" class="panel sp-panel sp-run" id="sp-import-form" data-sp-run
          data-remove="<?= $opts['remove_missing'] ? '1' : '0' ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="pack" value="<?= $e($pack) ?>">
        <?php foreach ($opts['groups'] as $gid): ?><input type="hidden" name="groups[]" value="<?= $e($gid) ?>"><?php endforeach; ?>
        <input type="hidden" name="add_new" value="<?= $opts['add_new'] ? '1' : '0' ?>">
        <input type="hidden" name="remove_missing" value="<?= $opts['remove_missing'] ? '1' : '0' ?>">
        <ul class="sp-safety">
            <li><i class="fa-solid fa-box-archive" aria-hidden="true"></i> A full backup is made first.</li>
            <li><i class="fa-solid fa-database" aria-hidden="true"></i> Database changes run in one transaction: if anything fails, nothing changes.</li>
            <li><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Overwritten pages get a revision, and the whole import can be undone in one click.</li>
            <li><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Users, passwords, API keys and other secrets are never touched.</li>
        </ul>
        <?php if ($opts['remove_missing'] && !empty($t['remove'])): ?>
            <div class="sp-danger-confirm">
                <label><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> This deletes <strong><?= (int) $t['remove'] ?></strong> item(s) that are not in the pack. Type <code>REMOVE</code> to confirm:
                    <input type="text" name="confirm_remove" autocomplete="off" required pattern="[Rr][Ee][Mm][Oo][Vv][Ee]" data-sp-confirm-remove></label>
            </div>
        <?php endif; ?>
        <?php $todo = (int) $t['update'] + (int) $t['add'] + (int) ($t['remove'] ?? 0); ?>
        <div class="sp-run-row">
            <?php if ($active): ?>
                <p class="alert alert-error">Another import is running. <a href="/hq/backups/packs/job?id=<?= $e(rawurlencode($active['id'])) ?>">Watch it</a> and try again when it finishes.</p>
            <?php else: ?>
                <button type="submit" class="<?= $opts['remove_missing'] && !empty($t['remove']) ? 'btn-danger' : 'btn-primary' ?>" data-sp-start>
                    <i class="fa-solid fa-file-import" aria-hidden="true"></i> Start import<?= $todo > 0 ? ' (' . $todo . ' change' . ($todo === 1 ? '' : 's') . ')' : '' ?></button>
                <?php if ($todo === 0): ?><span class="sp-muted">Nothing to change with these options (overrides still apply).</span><?php endif; ?>
            <?php endif; ?>
        </div>
    </form>
<?php endif; ?>
</div>
<script src="/assets/js/site-packs.js?v=20261024sp" defer></script>
