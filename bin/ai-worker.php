<?php
/**
 * Mova AI background worker — runs queued AI jobs (chat, coding, images) and Quick-setup AI copy.
 *
 *   Cron (shared hosting), every minute:
 *     * * * * * php /path/to/mova/bin/ai-worker.php >/dev/null 2>&1
 *     (runs ~55 s, then exits; the next cron run takes over — no daemon needed)
 *
 *   Docker / VPS (supervisord, systemd, …):
 *     php bin/ai-worker.php --daemon
 *
 * Options:
 *   --max-time=N   seconds to keep polling before exiting (default 55; ignored with --daemon)
 *   --daemon       run forever (restart every hour to keep memory flat)
 *   --once         process what is queued now, then exit
 *   --status       print worker / queue status and exit
 *   -v             verbose log to stdout
 *
 * Only one worker runs at a time (flock on storage/ai-worker.lock); extra copies exit at once.
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
    exit(0);
}

$opts = getopt('v', ['max-time:', 'daemon', 'once', 'status']);
$verbose = isset($opts['v']);
$log = static function (string $m) use ($verbose): void {
    if ($verbose) {
        fwrite(STDOUT, '[' . date('H:i:s') . '] ' . $m . "\n");
    }
};

// The Quick-setup plugin only requires part of its classes on boot (the rest are loaded by its HQ route),
// so load the full set here or wizard steps fail with "class not found" in the CLI worker.
$wizardLoad = dirname(__DIR__) . '/mova-plugins/mova-setup-wizard/src/load.php';
if (is_file($wizardLoad)) {
    require_once $wizardLoad;
}

if (isset($opts['status'])) {
    $age = \Mova\AI\AiWorker::heartbeatAge();
    $q = \Mova\Core\Database::fetchAll("SELECT status, COUNT(*) AS n FROM ai_jobs GROUP BY status");
    printf("worker: %s, %s (mode %s)\n", $age === null ? 'never ran' : ('last seen ' . \Mova\AI\AiWorker::ago($age)), \Mova\AI\AiWorker::isAlive() ? 'active' : 'not running', \Mova\AI\AiWorker::mode());
    foreach ($q as $r) {
        printf("  %-10s %d\n", $r['status'], $r['n']);
    }
    exit(0);
}

if (!\Mova\AI\AiWorker::acquireLock()) {
    $log('another worker holds the lock — exiting');
    exit(0);
}
@set_time_limit(0);

$daemon = isset($opts['daemon']);
$via = $daemon ? 'daemon' : 'cron';
if (isset($opts['once'])) {
    $stats = \Mova\AI\AiWorker::run(600, 'once', 1, $log);
} elseif ($daemon) {
    $log('daemon started');
    $stats = \Mova\AI\AiWorker::run(3600, $via, 0, $log); // supervisord restarts us
} else {
    $max = max(5, min(600, (int) ($opts['max-time'] ?? 55)));
    $stats = \Mova\AI\AiWorker::run($max, $via, 0, $log);
}
\Mova\AI\AiWorker::releaseLock();
$log(sprintf('exit: %d job(s), %d wizard step(s), %d recovered', $stats['jobs'], $stats['wizard'], $stats['recovered']));
exit(0);
