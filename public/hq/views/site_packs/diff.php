<?php
/** @var string $pack @var int $id @var array $b @var array $hunks @var array $stats */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$spTabActive = 'packs';
include __DIR__ . '/_tabs.php';
?>
<p class="sp-back"><a href="/hq/backups/packs/preview?pack=<?= $e(rawurlencode($pack)) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to preview</a></p>
<section class="panel sp-panel sp-diff-panel">
    <h2 class="sp-h"><?= $e($b['title']) ?> <small class="sp-muted">#<?= (int) $id ?> · <?= $e($b['type']) ?> · /<?= $e($b['slug']) ?></small></h2>
    <p class="sp-muted"><?= $b['exists'] ? 'Body on this site → body in the pack.' : 'New item: the whole body is added.' ?>
        <span class="sp-pill is-add">+<?= (int) $stats['add'] ?></span> <span class="sp-pill is-remove">−<?= (int) $stats['del'] ?></span></p>
    <?php if ((int) $stats['add'] + (int) $stats['del'] === 0): ?>
        <p class="empty sp-empty">The body text is identical (only formatting changed).</p>
    <?php else: ?>
        <div class="sp-diff">
            <?php foreach ($hunks as $op): [$k, $line] = $op; ?>
                <?php if ($k === '…'): ?><div class="sp-diff-gap">⋯ <?= $e($line) ?></div><?php continue; endif; ?>
                <div class="sp-dl <?= $k === '+' ? 'is-add' : ($k === '-' ? 'is-del' : '') ?>"><span class="sp-dk"><?= $k === ' ' ? '' : $e($k === '-' ? '−' : '+') ?></span><span class="sp-dt"><?= $e($line) ?></span></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
