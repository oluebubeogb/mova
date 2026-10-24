<?php
/**
 * Mova doctor - environment + upgrade health check (1.2.3).
 *
 *   php bin/mova-doctor.php              # report only (exit 1 when something is broken)
 *   php bin/mova-doctor.php --migrate    # also apply pending database migrations
 *   php bin/mova-doctor.php --reset-opcache  # make the web pool clear its code cache on its next request
 *   php bin/mova-doctor.php --repair-search  # recreate + rebuild the HQ and site full-text indexes
 *   php bin/mova-doctor.php --list-backups   # backups in storage/backups (newest first)
 *   php bin/mova-doctor.php --restore=<file> # restore a backup from storage/backups when HQ is unreachable
 *   php bin/mova-doctor.php --json       # machine-readable output
 *
 * Docker:  docker compose exec -u www-data <service> php bin/mova-doctor.php
 *          (run as www-data so files it creates keep the right owner)
 *
 * Never prints secrets: config values are not shown, only key names where relevant.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

define('MOVA_NO_AUTO_MIGRATE', true);

$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$json = in_array('--json', $args, true);
$doMigrate = in_array('--migrate', $args, true);
$resetOpcache = in_array('--reset-opcache', $args, true);
$repairSearch = in_array('--repair-search', $args, true);
$listBackups = in_array('--list-backups', $args, true);
$restoreFile = null;
$actionFailed = false;
foreach ($args as $a) {
    if (str_starts_with($a, '--restore=')) {
        $restoreFile = basename(substr($a, 10));
    }
}

if (!is_file($root . '/config/config.php')) {
    fwrite(STDERR, "config/config.php not found - is this the Mova root? ($root)\n");
    exit(1);
}

$bootError = null;
try {
    require $root . '/app/Core/Bootstrap.php';
    \Mova\Core\Bootstrap::init();
} catch (\Throwable $e) {
    $bootError = get_class($e) . ': ' . $e->getMessage() . ' in ' . str_replace($root . '/', '', $e->getFile()) . ':' . $e->getLine();
}

use Mova\Core\Runtime;

$out = ['items' => [], 'actions' => []];

if ($bootError !== null) {
    $out['items'][] = ['id' => 'boot', 'label' => 'Bootstrap', 'status' => 'error', 'detail' => $bootError];
}

if (class_exists(Runtime::class)) {
    if ($doMigrate) {
        $ok = Runtime::migrateIfNeeded(true);
        $out['actions'][] = $ok ? 'Migrations applied (schema is current).' : 'Migrations FAILED - see storage/logs/mova-errors.log (mova.migration).';
        $actionFailed = $actionFailed || !$ok;
    }
    if ($repairSearch) {
        try {
            $r = \Mova\Hq\SearchIndex::repair();
            $out['actions'][] = 'Search indexes repaired: HQ ' . $r['rows'] . ' entries (' . $r['engine'] . '), site search ' . $r['content_fts'] . '.';
            // The CLI has no signed-in user/route context: let the next HQ request re-index with the full route list.
            \Mova\Core\Database::query("DELETE FROM settings WHERE setting_key = 'hq_search_sig'");
        } catch (\Throwable $e) {
            $out['actions'][] = 'Search repair FAILED: ' . $e->getMessage();
            $actionFailed = true;
        }
    }
    if ($listBackups || $restoreFile !== null) {
        $svc = new \Mova\Backup\BackupService();
        $list = $svc->listBackups();
        if ($listBackups) {
            foreach ($list as $b) {
                $out['actions'][] = 'backup: ' . $b['name'] . '  ' . round($b['size'] / 1048576, 1) . ' MB  ' . date('Y-m-d H:i', $b['mtime']);
            }
            if (!$list) {
                $out['actions'][] = 'No backups in storage/backups.';
            }
        }
        if ($restoreFile !== null) {
            $hit = array_values(array_filter($list, static fn ($b) => $b['name'] === $restoreFile));
            if (!$hit) {
                $out['actions'][] = 'Restore: ' . $restoreFile . ' not found in storage/backups (see --list-backups).';
                $actionFailed = true;
            } else {
                try {
                    $r = $svc->restore($hit[0]['path']);
                    // No route context in the CLI: the next HQ request re-indexes with the full route list.
                    \Mova\Core\Database::query("DELETE FROM settings WHERE setting_key = 'hq_search_sig'");
                    $out['actions'][] = 'Restored ' . $r['file'] . ' (made by ' . ($r['from_version'] ?: '?') . '); ' . implode(', ', $r['refreshed'])
                        . ($r['safety_backup'] ? '. Undo with --restore=' . $r['safety_backup'] : '') . ' [ref ' . $r['ref'] . ']';
                } catch (\Throwable $e) {
                    $out['actions'][] = 'Restore FAILED: ' . $e->getMessage();
                    $actionFailed = true;
                }
            }
        }
    }
    if ($resetOpcache) {
        $marker = rtrim(\Mova\Core\Bootstrap::path('cache') ?: $root . '/storage/cache', '/') . '/.mova-code-fingerprint';
        if (is_file($marker) && @unlink($marker)) {
            $out['actions'][] = 'Web code-cache marker cleared: the next web request resets OPcache (Mova 1.2.2+ code). If the pool still runs older code, restart PHP-FPM / the container instead.';
        } else {
            $out['actions'][] = 'No marker to clear (or not writable). Restart PHP-FPM / the container to clear OPcache.';
        }
    }
    $out['items'] = array_merge($out['items'], Runtime::diagnostics());
} else {
    $out['items'][] = ['id' => 'runtime', 'label' => 'Runtime', 'status' => 'error', 'detail' => 'app/Core/Runtime.php missing - incomplete upload?'];
}

// Key files present (incomplete uploads are a classic cause of 500s).
$must = ['VERSION', 'app/Core/Bootstrap.php', 'app/Core/Runtime.php', 'app/Core/ErrorHandler.php', 'app/Core/Time.php',
    'public/index.php', 'public/hq/index.php', 'public/hq/helpers.php', 'public/hq/views/content/edit.php'];
$missing = array_values(array_filter($must, static fn ($f) => !is_file($root . '/' . $f)));
$out['items'][] = ['id' => 'files', 'label' => 'Core files', 'status' => $missing ? 'error' : 'ok',
    'detail' => $missing ? 'missing: ' . implode(', ', $missing) : 'present'];

// Helpers the views call must exist in helpers.php (stale/partial upload check).
$helpers = (string) @file_get_contents($root . '/public/hq/helpers.php');
$needFns = ['hqTimeTag', 'hqTime', 'hqDate', 'requireContentAccess', 'requirePerm', 'hqForbidden'];
$absent = array_values(array_filter($needFns, static fn ($f) => !preg_match('/function\s+' . $f . '\s*\(/', $helpers)));
$out['items'][] = ['id' => 'helpers', 'label' => 'HQ helpers', 'status' => $absent ? 'error' : 'ok',
    'detail' => $absent ? 'helpers.php is out of date (missing ' . implode(', ', $absent) . ') - re-upload the full release' : 'up to date'];

// Syntax check of PHP files changed by an upgrade is expensive; do a quick lint of the core.
if (function_exists('exec') && !in_array('--no-lint', $args, true)) {
    $bad = [];
    foreach (['app/Core/Bootstrap.php', 'app/Core/Runtime.php', 'app/Core/ErrorHandler.php', 'public/hq/helpers.php', 'public/hq/routes/content.php', 'public/hq/views/content/edit.php'] as $f) {
        if (!is_file($root . '/' . $f)) {
            continue;
        }
        $o = [];
        $rc = 0;
        @exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $f) . ' 2>&1', $o, $rc);
        if ($rc !== 0) {
            $bad[] = $f;
        }
    }
    $out['items'][] = ['id' => 'lint', 'label' => 'PHP syntax (core)', 'status' => $bad ? 'error' : 'ok',
        'detail' => $bad ? 'syntax errors under PHP ' . PHP_VERSION . ': ' . implode(', ', $bad) : 'OK under PHP ' . PHP_VERSION];
}

if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    $out['items'][] = ['id' => 'user', 'label' => 'Run as', 'status' => 'warn',
        'detail' => 'running as root - prefer `-u www-data` so files created here stay writable by PHP-FPM'];
}

$errors = count(array_filter($out['items'], static fn ($i) => $i['status'] === 'error'));
$warns = count(array_filter($out['items'], static fn ($i) => $i['status'] === 'warn'));

if ($json) {
    echo json_encode($out + ['errors' => $errors, 'warnings' => $warns], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    exit(($errors > 0 || $actionFailed) ? 1 : 0);
}

$tag = ['ok' => ' OK ', 'warn' => 'WARN', 'error' => 'FAIL', 'info' => 'INFO'];
echo "Mova doctor\n===========\n";
foreach ($out['items'] as $i) {
    printf("[%s] %-22s %s\n", $tag[$i['status']] ?? $i['status'], $i['label'], $i['detail']);
}
foreach ($out['actions'] as $a) {
    echo "  -> $a\n";
}
echo "\n", $errors ? "$errors problem(s)" : 'No blocking problems', $warns ? ", $warns warning(s)" : '', ".\n";
if ($errors || $warns) {
    echo "After an in-place upgrade: restart PHP-FPM (docker compose restart <service>), then run with --migrate if migrations are pending.\n";
}
exit(($errors > 0 || $actionFailed) ? 1 : 0);
