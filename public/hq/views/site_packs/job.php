<?php
use Mova\Backup\SitePackJobs;
use Mova\Security\Csrf;

/** @var array $job @var array $state @var bool $canUndo */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$phaseLabels = ['validate' => 'Verify pack', 'plan' => 'Plan', 'backup' => 'Backup', 'stage' => 'Stage media', 'apply' => 'Write database', 'swap' => 'Swap media', 'finalize' => 'Refresh caches'];
$phases = array_keys(SitePackJobs::PHASES);
$cur = array_search($job['phase'], $phases, true);
$live = in_array($job['status'], ['queued', 'running'], true);
$s = (array) ($job['result']['stats'] ?? []);
$m = (array) ($job['result']['media'] ?? []);
$spTabActive = 'packs';
include __DIR__ . '/_tabs.php';
?>
<p class="sp-back"><a href="/hq/backups/packs?tab=history"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Import history</a></p>
<div class="sp-wrap">
<section class="panel sp-panel sp-job" data-sp-job="<?= $e($job['id']) ?>" data-sp-live="<?= $live ? '1' : '0' ?>">
    <div class="sp-row-head">
        <h2 class="sp-h"><i class="fa-solid fa-file-import" aria-hidden="true"></i> <?= $e(['done' => 'Imported', 'failed' => 'Import failed:', 'undone' => 'Undone:'][$job['status']] ?? 'Importing') ?> <?= $e($job['pack']) ?></h2>
        <span class="sp-muted">by <?= $e($job['user']) ?> · <?= hqTimeTag($job['created'], 'both') ?></span>
    </div>
    <div class="sp-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $job['progress'] ?>" aria-label="Import progress">
        <span data-sp-bar style="width:<?= (int) $job['progress'] ?>%"></span>
    </div>
    <p class="sp-job-msg" data-sp-msg aria-live="polite"><?= $e($job['message']) ?></p>
    <ol class="sp-phases">
        <?php foreach ($phases as $i => $p): $cls = ($job['status'] === 'done' || ($cur !== false && $i < $cur)) ? 'is-done' : ($i === $cur ? ($job['status'] === 'failed' ? 'is-bad' : 'is-cur') : ''); ?>
            <li class="<?= $cls ?>" data-sp-phase="<?= $e($p) ?>"><span></span><?= $e($phaseLabels[$p] ?? $p) ?></li>
        <?php endforeach; ?>
    </ol>

    <div data-sp-final <?= $live ? 'hidden' : '' ?>>
    <?php if ($job['status'] === 'done' && empty($job['undone_at'])): ?>
        <div class="alert alert-success" role="status"><strong>Import complete.</strong>
            <?= (int) ($s['updated'] ?? 0) ?> updated · <?= (int) ($s['added'] ?? 0) ?> added<?= !empty($s['removed']) ? ' · ' . (int) $s['removed'] . ' removed' : '' ?>
            · <?= (int) ($s['settings'] ?? 0) ?> settings · <?= (int) ($s['revisions'] ?? 0) ?> revisions saved
            · media: <?= count($m['replaced'] ?? []) ?> replaced, <?= count($m['added'] ?? []) ?> added<?= !empty($m['removed']) ? ', ' . count($m['removed']) . ' removed' : '' ?>.
            <?php if (!empty($job['result']['refreshed'])): ?><br><span class="sp-muted">Then: <?= $e(implode(', ', $job['result']['refreshed'])) ?>.</span><?php endif; ?>
        </div>
        <div class="sp-run-row">
            <a class="btn-primary" href="/" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> View site</a>
            <?php if ($canUndo): ?>
                <form method="post" action="/hq/backups/packs/undo" onsubmit="return confirm('Undo this import? Content, settings and media go back to how they were before it ran. Edits made since then to the same items are lost.');">
                    <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $e($job['id']) ?>">
                    <button type="submit" class="btn-secondary"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Undo import</button>
                </form>
            <?php endif; ?>
            <?php if (!empty($job['backup'])): ?><span class="sp-muted">Backup taken first: <code><?= $e($job['backup']) ?></code></span><?php endif; ?>
        </div>
    <?php elseif (!empty($job['undone_at'])): ?>
        <div class="alert alert-success" role="status">This import was undone <?= hqTimeTag($job['undone_at'], 'both') ?><?= !empty($job['undone_by']) ? ' by ' . $e($job['undone_by']) : '' ?>.</div>
    <?php elseif ($job['status'] === 'failed'): ?>
        <div class="alert alert-error" role="alert"><strong>Import stopped.</strong> <?= $e($job['message']) ?></div>
        <p class="sp-muted">Your site is unchanged. <a href="/hq/backups/packs/preview?pack=<?= $e(rawurlencode($job['pack'])) ?>">Preview the pack again</a></p>
    <?php endif; ?>
    </div>

    <details class="sp-log" <?= $job['status'] === 'failed' ? 'open' : '' ?>>
        <summary>Activity log</summary>
        <ol data-sp-log>
            <?php foreach ($state['log'] as $l): ?><li><time><?= $e($l['t']) ?></time> <?= $e($l['m']) ?></li><?php endforeach; ?>
        </ol>
    </details>
    <p class="sp-note" data-sp-hint <?= $live ? '' : 'hidden' ?>>You can leave this page: the import continues in the background worker. Keep it open to drive it directly if your host has no worker.</p>
</section>
</div>
<script src="/assets/js/site-packs.js?v=20261024sp" defer></script>
