<?php
/**
 * Repair AI drafts damaged by the old "\u2019 → u2019" bug (e.g. "Nigeriau2019s", "fastu2011changing",
 * "20u202f%", "futureu2014whether").
 *
 *   php bin/fix-unicode-escapes.php              dry run: list every match, change nothing
 *   php bin/fix-unicode-escapes.php --apply      back up the database, then write the fixes
 *   php bin/fix-unicode-escapes.php --id=42      limit to one content item (and its meta/revisions)
 *   php bin/fix-unicode-escapes.php --quiet      summary only
 *
 * Scans content titles, excerpts and bodies, content meta (SEO title/description, …) and revisions.
 * Only a letter/digit directly followed by "u" + a known punctuation codepoint is changed
 * (2010–2015, 2018–201F, 2026, 202F, 00A0, 2032–2033); numbers-only codepoints need a letter after
 * them, so "menu2019" or "in 2019" are never touched. Review the dry run before --apply.
 * Before writing, a full copy of the database is saved to storage/backups/pre-unicode-fix-*.sqlite
 * plus a JSON file with the old value of every changed field.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
\Mova\Core\Bootstrap::init();

if (!\Mova\Core\Schema::isInstalled()) {
    fwrite(STDERR, "Mova is not installed yet.\n");
    exit(1);
}

$apply = in_array('--apply', $argv, true);
$quiet = in_array('--quiet', $argv, true);
$id = null;
foreach ($argv as $a) {
    if (preg_match('/^--id=(\d+)$/', $a, $m)) {
        $id = (int) $m[1];
    }
    if ($a === '--help' || $a === '-h') {
        echo "Usage: php bin/fix-unicode-escapes.php [--apply] [--id=N] [--quiet]\n";
        exit(0);
    }
}

$svc = new \Mova\AI\UnicodeRepairService();
try {
    $res = $apply ? $svc->apply($id) : ['findings' => $svc->scan($id), 'backup' => null];
} catch (\Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}

$findings = $res['findings'];
$total = 0;
foreach ($findings as $f) {
    $total += count($f['hits']);
    if ($quiet) {
        continue;
    }
    printf("%s #%d (content %d) · %s · %d match(es)\n", $f['table'], $f['id'], $f['content_id'], $f['column'], count($f['hits']));
    foreach ($f['hits'] as $h) {
        echo '    …' . $h['context'] . "…\n";
    }
}
echo str_repeat('-', 60) . "\n";
printf("%d field(s), %d match(es)%s.\n", count($findings), $total, $id !== null ? " in content #{$id}" : '');
if ($findings === []) {
    echo "Nothing to repair.\n";
} elseif ($apply) {
    echo "Applied. Backup: " . ($res['backup'] ?? '-') . " (+ .json with the old values)\n";
    echo "Page cache cleared and search indexes rebuilt.\n";
} else {
    echo "Dry run — nothing changed. Review the matches above, then run again with --apply.\n";
}
exit(0);
