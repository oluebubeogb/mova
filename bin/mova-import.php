<?php
/**
 * Import a Mova site pack.
 *
 *   php bin/mova-import.php path/to/site.movapack --dry-run        # validate + preview, changes nothing
 *   php bin/mova-import.php path/to/site.movapack --apply          # backup, then import
 *   php bin/mova-import.php --undo                                  # undo the most recent import
 *   php bin/mova-import.php --history
 *
 * Options:
 *   --groups=content,design     only these groups (default: all groups in the pack)
 *   --no-add                    don't add items that are new in the pack
 *   --remove-missing            DELETE items of the imported groups that are not in the pack
 *   --override=content:12,...   import these conflicting items anyway (when the pack slug is free)
 *   --json                      machine-readable output
 *   -v                          list every item in the dry run
 * A pack name without a path is looked up in storage/imports/.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
\Mova\Core\Bootstrap::init();

/** getopt() stops at the first positional argument, so parse "--x", "--x=v" and "-v" anywhere on the line. */
function mova_cli_opts(array $args): array
{
    $o = [];
    foreach ($args as $a) {
        if (strncmp($a, '--', 2) === 0) {
            $p = strpos($a, '=');
            if ($p === false) {
                $o[substr($a, 2)] = false;
            } else {
                $o[substr($a, 2, $p - 2)] = substr($a, $p + 1);
            }
        } elseif ($a !== '-' && $a !== '' && $a[0] === '-') {
            foreach (str_split(substr($a, 1)) as $ch) {
                $o[$ch] = false;
            }
        }
    }
    return $o;
}

use Mova\Backup\SitePack;
use Mova\Backup\SitePackImporter;
use Mova\Backup\SitePackJobs;
use Mova\Backup\SitePackReader;

$args = $argv;
array_shift($args);
$pack = null;
foreach ($args as $a) {
    if ($a !== '' && $a[0] !== '-') {
        $pack = $a;
    }
}
$o = mova_cli_opts(array_slice($argv, 1));
$json = isset($o['json']);
$say = static function (string $s) use ($json): void {
    if (!$json) {
        fwrite(STDOUT, $s . "\n");
    }
};
if (isset($o['h']) || isset($o['help'])) {
    fwrite(STDOUT, preg_replace('/^.*?\/\*\*|\*\/.*$/s', '', file_get_contents(__FILE__)) . "\n");
    exit(0);
}
if (isset($o['history'])) {
    foreach (SitePackJobs::all() as $j) {
        $say(sprintf('%s  %-8s %-24s by %-12s %s%s', $j['id'], $j['status'], mb_substr($j['pack'], 0, 24), $j['user'], \Mova\Core\Time::format((int) $j['created']) . ' ' . \Mova\Core\Time::abbr(), !empty($j['undone_at']) ? '  (undone)' : ''));
    }
    exit(0);
}
if (isset($o['undo'])) {
    $u = SitePackJobs::undoable();
    if (!$u) {
        fwrite(STDERR, "Nothing to undo.\n");
        exit(1);
    }
    try {
        $j = SitePackJobs::undo($u['id'], null);
        $say('Undone ' . $j['id'] . ': ' . $j['message']);
        exit(0);
    } catch (\Throwable $e) {
        fwrite(STDERR, 'Undo failed: ' . $e->getMessage() . "\n");
        exit(1);
    }
}
if ($pack === null) {
    fwrite(STDERR, "Usage: php bin/mova-import.php <pack.movapack> --dry-run|--apply  (see --help)\n");
    exit(2);
}
$path = is_file($pack) ? (realpath($pack) ?: $pack) : SitePackReader::resolveImport($pack);
if ($path === null || !is_file($path)) {
    fwrite(STDERR, "Pack not found.\n");
    exit(2);
}
$opts = [
    'add_new' => !isset($o['no-add']),
    'remove_missing' => isset($o['remove-missing']),
    'overrides' => isset($o['override']) ? array_filter(array_map('trim', explode(',', (string) $o['override']))) : [],
    'fail_after' => (int) ($o['fail-after'] ?? 0),
];
$r = new SitePackReader($path);
$say('Validating ' . basename($path) . ' …');
if (!$r->validate()) {
    if ($json) {
        fwrite(STDOUT, json_encode(['ok' => false, 'errors' => $r->errors, 'version_blocked' => $r->versionBlocked], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
    foreach ($r->errors as $e) {
        fwrite(STDERR, 'error: ' . $e . "\n");
    }
    exit($r->versionBlocked ? 3 : 1);
}
$m = $r->manifest();
$opts['groups'] = isset($o['groups']) ? array_filter(array_map('trim', explode(',', (string) $o['groups']))) : (array) $m['groups'];
$say(sprintf('Pack from "%s" (%s), Mova %s, exported %s; groups: %s', $m['site']['name'] ?? '?', $m['site']['host'] ?? '?', $m['mova_version'], $m['exported_at'], implode(', ', $m['groups'])));

if (!isset($o['apply'])) {
    $plan = (new SitePackImporter($r))->plan($opts);
    if ($json) {
        $slim = $plan;
        unset($slim['files'], $slim['remove_files'], $slim['actions'], $slim['children'], $slim['settings'], $slim['kv']);
        $slim['file_count'] = count($plan['files']);
        fwrite(STDOUT, json_encode(['ok' => true, 'plan' => $slim], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        exit(0);
    }
    foreach ($plan['groups'] as $gid => $g) {
        $c = $g['counts'];
        $say(sprintf('  %-22s update %-4d add %-4d unchanged %-4d conflict %-4d remove %-4d%s', $g['label'], $c['update'], $c['add'], $c['unchanged'], $c['conflict'], $c['remove'], $c['skip'] ? ' skipped ' . $c['skip'] : ''));
        foreach ($g['items'] as $it) {
            if (isset($o['v']) || in_array($it['status'], ['conflict', 'remove', 'blocked'], true)) {
                $say(sprintf('      %-9s %s #%s %s %s %s', $it['status'], $it['table'], $it['id'], $it['key'], $it['fields'] ? '[' . implode(', ', array_slice($it['fields'], 0, 8)) . ']' : '', $it['reason']));
            }
        }
    }
    $say(sprintf('Media files to write: %d (%s). Dry run — nothing changed. Use --apply to import.', count($plan['files']), SitePack::fmtBytes((int) $plan['file_bytes'])));
    exit(0);
}
$r->close();
try {
    $job = SitePackJobs::create($path, $opts, null, 'cli');
} catch (\Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
$job = SitePackJobs::runToEnd($job['id'], static function (array $j) use ($say): void {
    $say(sprintf('[%3d%%] %s', $j['progress'], $j['message']));
});
if ($json) {
    unset($job['plan']);
    fwrite(STDOUT, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
}
exit($job['status'] === 'done' ? 0 : 1);
