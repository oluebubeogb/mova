<?php
use Mova\Security\Csrf;
$findings = $findings ?? [];
$filterId = (int) ($filterId ?? 0);
$total = 0;
foreach ($findings as $f) {
    $total += count($f['hits']);
}
$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
/** Highlight "[u2019→’]" markers produced by AiText::repairCorrupted(). */
$ctx = static function (string $c) use ($e): string {
    return preg_replace_callback('/\[(u[0-9a-f]{4})→(.*?)\]/iu', static fn ($m) =>
        '<del class="tr-old">' . $m[1] . '</del><ins class="tr-new">' . ($m[2] === ' ' ? '␣' : $m[2]) . '</ins>', $e($c)) ?? $e($c);
};
?>
<?php if (isset($_GET['fixed'])): ?>
    <div class="alert alert-success" role="status">
        Repaired <?= (int) $_GET['fixed'] ?> match(es) in <?= (int) ($_GET['fields'] ?? 0) ?> field(s).
        <?php if (!empty($_GET['backup'])): ?>Backup saved first: <code><?= $e(basename((string) $_GET['backup'])) ?></code> in storage/backups.<?php endif; ?>
    </div>
<?php endif; ?>
<?php if (!empty($_GET['error']) || !empty($error)): ?>
    <div class="alert alert-error"><?= $e($_GET['error'] ?? $error) ?></div>
<?php endif; ?>

<div class="panel tr-panel">
    <h2 class="tr-h">Text repair · AI punctuation</h2>
    <p class="tr-lead">
        An older AI version could save punctuation as code: <code>Nigeriau2019s</code>, <code>fastu2011changing</code>,
        <code>20u202f%</code>, <code>futureu2014whether</code>. This tool finds that exact pattern in titles, excerpts,
        bodies, SEO/meta fields and revisions and turns it back into real characters
        (<code>Nigeria’s</code>, <code>fast-changing</code>, <code>20 %</code>, <code>future—whether</code>).
        Ordinary text such as <code>menu2019</code> or years is left alone.
    </p>
    <form method="get" action="/hq/text-repair" class="tr-filter">
        <label>Content ID <input type="number" name="id" min="0" value="<?= $filterId > 0 ? $filterId : '' ?>" placeholder="all"></label>
        <button type="submit" class="btn-secondary">Scan</button>
        <?php if ($filterId > 0): ?><a href="/hq/text-repair">Scan everything</a><?php endif; ?>
    </form>

    <?php if ($findings === []): ?>
        <p class="empty tr-empty"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Nothing to repair<?= $filterId > 0 ? ' in content #' . $filterId : '' ?>.</p>
    <?php else: ?>
        <p class="tr-sum"><strong><?= $total ?></strong> match(es) in <strong><?= count($findings) ?></strong> field(s). Review them, then repair. A full database backup is taken first.</p>
        <form method="post" action="/hq/text-repair/apply" class="tr-apply" onsubmit="return confirm('Repair <?= $total ?> match(es)? A database backup is saved first.');">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= $filterId ?>">
            <button type="submit" class="btn-primary">Repair <?= $total ?> match(es)</button>
        </form>
        <div class="backup-table-wrap">
        <table class="data-table tr-table">
            <thead><tr><th>Where</th><th>Field</th><th>Matches (before → after)</th></tr></thead>
            <tbody>
            <?php foreach ($findings as $f): ?>
                <tr>
                    <td>
                        <?php if ($f['table'] === 'content'): ?>
                            <a href="/hq/content/edit/<?= (int) $f['id'] ?>">Content #<?= (int) $f['id'] ?></a>
                        <?php else: ?>
                            <?= $e($f['table'] === 'revisions' ? 'Revision' : 'Meta') ?> #<?= (int) $f['id'] ?>
                            · <a href="/hq/content/edit/<?= (int) $f['content_id'] ?>">content #<?= (int) $f['content_id'] ?></a>
                        <?php endif; ?>
                    </td>
                    <td><code><?= $e($f['column']) ?></code></td>
                    <td><ul class="tr-hits">
                        <?php foreach ($f['hits'] as $h): ?><li>…<?= $ctx($h['context']) ?>…</li><?php endforeach; ?>
                    </ul></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
    <p class="tr-cli">Command line: <code>php bin/fix-unicode-escapes.php</code> (dry run) · <code>php bin/fix-unicode-escapes.php --apply</code></p>
</div>
<style>
.tr-panel{max-width:1100px}
.tr-h{margin:0 0 .4rem;font-size:1.05rem}
.tr-lead,.tr-cli{color:var(--hq-muted);font-size:.9rem;line-height:1.5}
.tr-filter{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin:.8rem 0}
.tr-filter input{width:8rem;margin-left:.3rem}
.tr-apply{margin:.6rem 0 1rem}
.tr-hits{margin:0;padding-left:1rem;font-size:.85rem;line-height:1.55}
.tr-old{color:#b91c1c;text-decoration:line-through}
.tr-new{color:#15803d;text-decoration:none;font-weight:600;margin-left:.15rem}
.tr-empty{display:flex;gap:.4rem;align-items:center}
</style>
