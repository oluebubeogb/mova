<?php
/**
 * Mova AI background worker.
 *
 * Runs queued AI jobs (chat, coding, image) and running Quick-setup copy jobs outside the web request.
 * Dispatch order (setting ai_worker_mode, default "auto"):
 *   1. worker   — bin/ai-worker.php under supervisord (Docker) or cron (shared hosting) picks jobs up.
 *   2. poke     — no cron? a fire-and-forget loopback request (HMAC-signed) runs the worker in its own request.
 *   3. request  — last resort: process after the response in a shutdown function (the old behaviour).
 * Needs nothing beyond PHP + SQLite: flock() for the single-runner lock, settings rows for the heartbeat.
 */

namespace Mova\AI;

use Mova\Core\Database;

class AiWorker
{
    public const HEARTBEAT_KEY = 'ai_worker_heartbeat';
    public const SECRET_KEY = 'ai_worker_secret';
    public const ALIVE_SECONDS = 45;

    private static int $lastBeat = 0;
    /** @var resource|null */
    private static $lock = null;

    // ── status ────────────────────────────────────────────────────────────

    /** Seconds since the worker last checked in, or null if it never ran. */
    public static function heartbeatAge(): ?int
    {
        try {
            $row = Database::fetch('SELECT setting_value FROM settings WHERE setting_key = :k', ['k' => self::HEARTBEAT_KEY]);
        } catch (\Throwable $e) {
            return null;
        }
        $data = $row ? json_decode((string) $row['setting_value'], true) : null;
        $ts = is_array($data) ? (int) ($data['ts'] ?? 0) : (int) ($row['setting_value'] ?? 0);
        return $ts > 0 ? max(0, time() - $ts) : null;
    }

    /**
     * A cron/supervisord worker (not a one-off poke) checked in recently.
     * Pass 'wizard' to also require that this worker can run Quick-setup steps.
     */
    public static function isAlive(string $for = ''): bool
    {
        try {
            $row = Database::fetch('SELECT setting_value FROM settings WHERE setting_key = :k', ['k' => self::HEARTBEAT_KEY]);
        } catch (\Throwable $e) {
            return false;
        }
        $data = $row ? json_decode((string) $row['setting_value'], true) : null;
        if (!is_array($data) || !empty($data['stopped']) || in_array($data['via'] ?? '', ['poke', 'once'], true)) {
            return false;
        }
        if ($for === 'wizard' && isset($data['wizard']) && $data['wizard'] === false) {
            return false;
        }
        return time() - (int) ($data['ts'] ?? 0) < self::ALIVE_SECONDS;
    }

    public static function ago(int $sec): string
    {
        return match (true) {
            $sec < 90 => $sec . 's ago',
            $sec < 5400 => round($sec / 60) . ' min ago',
            $sec < 172800 => round($sec / 3600) . ' h ago',
            default => round($sec / 86400) . ' days ago',
        };
    }

    public static function mode(): string
    {
        $m = AiConfig::get('ai_worker_mode');
        return in_array($m, ['auto', 'worker', 'poke', 'request'], true) ? $m : 'auto';
    }

    // ── dispatch (web side) ───────────────────────────────────────────────

    /**
     * Called right after a job is queued. Returns how it will be processed: worker|poke|poll|request.
     * $fallback runs the job in this request after the response (shutdown function).
     */
    public static function dispatch(callable $fallback): string
    {
        $mode = self::mode();
        if ($mode === 'worker' || ($mode === 'auto' && self::isAlive())) {
            return 'worker';
        }
        if (($mode === 'auto' || $mode === 'poke') && self::poke()) {
            return 'poke';
        }
        if (!function_exists('fastcgi_finish_request') && $mode !== 'request') {
            // mod_php / php -S cannot end the response early; running here would make the
            // browser wait for the whole AI call. The open page polls /ai/jobs/process instead.
            return 'poll';
        }
        register_shutdown_function(static function () use ($fallback) {
            try {
                @ignore_user_abort(true);
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }
                $fallback();
            } catch (\Throwable $e) {
            }
        });
        return 'request';
    }

    /** Re-poke when the browser polls and nothing seems to be moving (throttled to 1 / 20 s). */
    public static function nudge(): void
    {
        if (self::isAlive() || self::mode() === 'worker' || self::mode() === 'request') {
            return;
        }
        $last = (int) AiConfig::get('ai_worker_poked_at');
        if (time() - $last < 20) {
            return;
        }
        self::poke();
    }

    /** Fire-and-forget loopback request to /hq/ai/worker/poke. True when the request was sent. */
    public static function poke(): bool
    {
        // php -S is single-threaded: a loopback request would just wait for us. Use the fallback.
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server' || empty($_SERVER['HTTP_HOST'])) {
            return false;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $hostHeader = (string) $_SERVER['HTTP_HOST'];
        $hostName = preg_replace('/:\d+$/', '', $hostHeader) ?? $hostHeader;
        $port = (int) ($_SERVER['SERVER_PORT'] ?? ($https ? 443 : 80));
        $ts = (string) time();
        $body = 'ts=' . $ts . '&sig=' . self::sign($ts);
        $req = "POST /hq/ai/worker/poke HTTP/1.1\r\nHost: {$hostHeader}\r\n"
            . "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\n"
            . "User-Agent: Mova-AI-Worker\r\nConnection: close\r\n\r\n" . $body;
        // Prefer the loopback interface (works behind most proxies); fall back to the public host name.
        $targets = [['127.0.0.1', $port, $https], [$hostName, $port, $https]];
        foreach ($targets as [$h, $p, $tls]) {
            $ctx = stream_context_create(['ssl' => [
                'peer_name' => $hostName, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true,
            ]]);
            $errno = 0;
            $errstr = '';
            $fp = @stream_socket_client(($tls ? 'ssl://' : 'tcp://') . $h . ':' . $p, $errno, $errstr, 2, STREAM_CLIENT_CONNECT, $ctx);
            if (!$fp) {
                continue;
            }
            stream_set_timeout($fp, 2);
            $ok = @fwrite($fp, $req) === strlen($req);
            // Read the status line so the server has accepted the request, then hang up.
            $line = $ok ? (string) @fgets($fp, 64) : '';
            @fclose($fp);
            if ($ok && str_contains($line, ' 202')) {
                AiConfig::put('ai_worker_poked_at', (string) time());
                return true;
            }
        }
        return false;
    }

    public static function sign(string $ts): string
    {
        return hash_hmac('sha256', 'mova-ai-poke|' . $ts, self::secret());
    }

    public static function verify(string $ts, string $sig): bool
    {
        if (!ctype_digit($ts) || abs(time() - (int) $ts) > 60) {
            return false;
        }
        return hash_equals(self::sign($ts), $sig);
    }

    private static function secret(): string
    {
        $s = AiConfig::get(self::SECRET_KEY);
        if ($s === '') {
            $s = bin2hex(random_bytes(24));
            AiConfig::put(self::SECRET_KEY, $s);
        }
        return $s;
    }

    // ── worker loop (CLI or poke request) ─────────────────────────────────

    public static function lockPath(): string
    {
        return dirname(__DIR__, 2) . '/storage/ai-worker.lock';
    }

    /** Single-runner lock (flock — works on shared hosting). */
    public static function acquireLock(): bool
    {
        $fp = @fopen(self::lockPath(), 'c');
        if (!$fp) {
            return true; // read-only storage: run anyway, the atomic job claim still prevents double work
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return false;
        }
        self::$lock = $fp;
        return true;
    }

    public static function releaseLock(): void
    {
        if (self::$lock) {
            flock(self::$lock, LOCK_UN);
            fclose(self::$lock);
            self::$lock = null;
        }
    }

    private static bool $wizardOk = true;

    /** Make sure every Quick-setup class is loaded (its plugin only requires some on boot). */
    private static function loadWizard(): bool
    {
        if (!class_exists(\MovaSetupWizard\SetupWizardService::class, false) || !class_exists(\MovaSetupWizard\WizardDraft::class, false)) {
            $f = dirname(__DIR__, 2) . '/mova-plugins/mova-setup-wizard/src/load.php';
            if (!is_file($f)) {
                return false;
            }
            require_once $f;
        }
        return class_exists(\MovaSetupWizard\SetupWizardService::class, false);
    }

    public static function beat(string $via, bool $force = false, bool $stopped = false): void
    {
        if (!$force && time() - self::$lastBeat < 15) {
            return;
        }
        self::$lastBeat = time();
        $data = ['ts' => time(), 'via' => $via, 'pid' => getmypid()];
        if (!self::$wizardOk) {
            $data['wizard'] = false; // web requests advance Quick setup themselves
        }
        if ($stopped) {
            $data['stopped'] = true; // clean exit: web requests take over at once instead of waiting out ALIVE_SECONDS
        }
        AiConfig::put(self::HEARTBEAT_KEY, (string) json_encode($data));
    }

    /**
     * Process work until $maxTime seconds pass. $idleExit: stop after that many idle seconds (0 = keep polling).
     * @param callable(string):void|null $log
     * @return array{jobs:int,wizard:int,recovered:int}
     */
    public static function run(int $maxTime, string $via, int $idleExit = 0, ?callable $log = null): array
    {
        $log ??= static function (string $m): void {
        };
        $t0 = time();
        $idleSince = time();
        $stats = ['jobs' => 0, 'wizard' => 0, 'recovered' => 0];
        $svc = new AiJobService();
        $stats['recovered'] = $svc->recoverStale();
        if ($stats['recovered']) {
            $log('re-queued ' . $stats['recovered'] . ' stale job(s)');
        }
        while (true) {
            AiConfig::flush(); // long-running: pick up Settings changes
            self::beat($via);
            $did = false;
            // 1) queued AI jobs (any user, oldest first)
            $job = $svc->processNext(null);
            if ($job !== null) {
                $did = true;
                $stats['jobs']++;
                $log('job #' . ($job['id'] ?? '?') . ' ' . ($job['kind'] ?? '') . ' → ' . ($job['status'] ?? '?'));
            }
            // 2) Quick-setup AI copy (one page per pass, all running drafts)
            if (!$did && self::$wizardOk && self::loadWizard()) {
                try {
                    \MovaSetupWizard\SetupWizardService::$preferAsync = true;
                    foreach (\MovaSetupWizard\SetupWizardService::runningUsers() as $uid) {
                        $d = \MovaSetupWizard\SetupWizardService::advance($uid);
                        if (empty($d['_busy'])) {
                            $did = true;
                            $stats['wizard']++;
                            $log('wizard user ' . $uid . ': ' . count((array) ($d['ai']['done'] ?? [])) . '/' . (int) ($d['ai']['total'] ?? 0) . ' ' . ($d['ai']['status'] ?? ''));
                        }
                        self::beat($via);
                    }
                } catch (\Throwable $e) {
                    // Don't spin on a broken step: hand Quick setup back to the browser for the rest of this run.
                    $log('wizard error: ' . $e->getMessage() . ' — leaving Quick setup to web requests');
                    self::$wizardOk = false;
                    self::beat($via, true);
                }
            }
            // 3) site-pack imports queued from HQ → Backups → Export / Import (time-boxed steps)
            if (!$did && class_exists(\Mova\Backup\SitePackJobs::class)) {
                try {
                    if (\Mova\Backup\SitePackJobs::processNext(min(20.0, max(5.0, (float) ($maxTime - (time() - $t0)))))) {
                        $did = true;
                        $stats['packs'] = ($stats['packs'] ?? 0) + 1;
                        $log('site pack import step');
                    }
                } catch (\Throwable $e) {
                    $log('site pack error: ' . $e->getMessage());
                }
                self::beat($via);
            }
            if ($did) {
                $idleSince = time();
            }
            if (time() - $t0 >= $maxTime) {
                break;
            }
            if (!$did) {
                if ($idleExit > 0 && time() - $idleSince >= $idleExit) {
                    break;
                }
                sleep(1);
            }
        }
        self::beat($via, true, true);
        return $stats;
    }
}
