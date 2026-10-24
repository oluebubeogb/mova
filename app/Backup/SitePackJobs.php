<?php
/**
 * Import jobs for site packs. Each job is a JSON file in storage/imports/jobs (outside the DB, so the
 * history survives an Undo). Phases run in short, resumable steps so large packs work under shared-hosting
 * time and memory limits: from the AI worker (cron/daemon), a web "poke", the browser's polling, or the CLI.
 *
 *   validate → plan → backup → stage → apply → swap → finalize → done
 */

declare(strict_types=1);

namespace Mova\Backup;

use Mova\Auth\Auth;
use Mova\Security\Audit;
use Mova\Core\Database;

final class SitePackJobs
{
    public const PHASES = ['validate' => [0, 20], 'plan' => [20, 25], 'backup' => [25, 40], 'stage' => [40, 70], 'apply' => [70, 85], 'swap' => [85, 92], 'finalize' => [92, 100]];
    private const KEEP_UNDO = 3;

    public static function dir(): string
    {
        return SitePack::importsDir() . '/jobs';
    }

    private static function file(string $id): string
    {
        return self::dir() . '/' . $id . '.json';
    }

    public static function validId(string $id): bool
    {
        return (bool) preg_match('/^imp-\d{8}-\d{6}-[a-f0-9]{6}$/', $id);
    }

    public static function load(string $id): ?array
    {
        if (!self::validId($id) || !is_file(self::file($id))) {
            return null;
        }
        $j = json_decode((string) file_get_contents(self::file($id)), true);
        return is_array($j) ? $j : null;
    }

    public static function save(array $job): void
    {
        $job['updated'] = time();
        $tmp = self::file($job['id']) . '.tmp';
        file_put_contents($tmp, json_encode($job, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX);
        rename($tmp, self::file($job['id']));
    }

    /** @return list<array> newest first (without the heavy plan) */
    public static function all(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/imp-*.json') ?: [] as $f) {
            $j = json_decode((string) file_get_contents($f), true);
            if (is_array($j)) {
                unset($j['plan']);
                $out[] = $j;
            }
        }
        usort($out, static fn ($a, $b) => strcmp((string) $b['id'], (string) $a['id']));
        return $out;
    }

    public static function active(): ?array
    {
        foreach (self::all() as $j) {
            if (in_array($j['status'], ['queued', 'running'], true)) {
                return $j;
            }
        }
        return null;
    }

    /** Latest finished import that has not been undone — the only one Undo is offered for. */
    public static function undoable(): ?array
    {
        foreach (self::all() as $j) {
            if ($j['status'] === 'done' && empty($j['undone_at']) && empty($j['dry_run'])) {
                return is_file(SitePack::importsDir() . '/undo/' . $j['id'] . '/database.sqlite') ? $j : null;
            }
            if ($j['status'] === 'done' && !empty($j['undone_at'])) {
                continue;
            }
        }
        return null;
    }

    public static function create(string $packPath, array $options, ?int $userId, string $via = 'hq'): array
    {
        if (self::active() !== null) {
            throw new \RuntimeException('Another import is still running. Wait for it to finish.');
        }
        $u = $userId ? Database::fetch('SELECT id, username, name FROM users WHERE id = :id', ['id' => $userId]) : null;
        $job = [
            'id' => 'imp-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)),
            'pack' => basename($packPath),
            'pack_path' => $packPath,
            'pack_size' => (int) @filesize($packPath),
            'options' => [
                'groups' => array_values(array_map('strval', (array) ($options['groups'] ?? []))),
                'add_new' => (bool) ($options['add_new'] ?? true),
                'remove_missing' => (bool) ($options['remove_missing'] ?? false),
                'overrides' => array_values(array_map('strval', (array) ($options['overrides'] ?? []))),
                'fail_after' => (int) ($options['fail_after'] ?? 0),
            ],
            'user_id' => $u ? (int) $u['id'] : null,
            'user' => $u ? (string) ($u['name'] ?: $u['username']) : ($via === 'cli' ? 'CLI' : 'unknown'),
            'via' => $via,
            'status' => 'queued',
            'phase' => 'validate',
            'progress' => 0,
            'message' => 'Queued',
            'cursor' => 0,
            'created' => time(),
            'started' => null,
            'finished' => null,
            'log' => [],
        ];
        self::save($job);
        Audit::log('site_pack.import_queued', 'site_pack', null, ['job' => $job['id'], 'pack' => $job['pack'], 'groups' => $job['options']['groups'], 'remove_missing' => $job['options']['remove_missing']]);
        return $job;
    }

    private static function log(array &$job, string $msg): void
    {
        $job['log'][] = ['t' => time(), 'm' => $msg];
        if (count($job['log']) > 60) {
            $job['log'] = array_slice($job['log'], -60);
        }
        $job['message'] = $msg;
    }

    private static function progress(array &$job, string $phase, float $frac): void
    {
        [$a, $b] = self::PHASES[$phase];
        $job['progress'] = (int) floor($a + ($b - $a) * max(0, min(1, $frac)));
    }

    /**
     * Run one time-boxed step. Safe to call concurrently (flock per job). Returns the job.
     */
    public static function step(string $id, float $budget = 8.0): ?array
    {
        $job = self::load($id);
        if ($job === null || !in_array($job['status'], ['queued', 'running'], true)) {
            return $job;
        }
        $lock = fopen(self::file($id) . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                fclose($lock);
            }
            $job['_busy'] = true; // another runner holds it
            return $job;
        }
        @set_time_limit(max(60, (int) ceil($budget) + 60));
        $deadline = microtime(true) + $budget;
        try {
            $job = self::load($id) ?? $job; // re-read under the lock
            if (!in_array($job['status'], ['queued', 'running'], true)) {
                return $job;
            }
            if ($job['status'] === 'queued') {
                $job['status'] = 'running';
                $job['started'] = time();
            }
            while (microtime(true) < $deadline && $job['status'] === 'running') {
                self::runPhase($job, $deadline);
                $job['peak_mb'] = max((float) ($job['peak_mb'] ?? 0), round(memory_get_peak_usage(true) / 1048576, 1));
                self::save($job);
            }
        } catch (\Throwable $e) {
            self::fail($job, $e);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock); // the .lock file is kept: unlinking it would let two runners lock different inodes
        }
        return $job;
    }

    /** Run to completion (CLI and tests). */
    public static function runToEnd(string $id, ?callable $out = null): array
    {
        $last = '';
        while (true) {
            $job = self::step($id, 30.0);
            if ($job === null) {
                throw new \RuntimeException('Job not found.');
            }
            if ($out && $job['message'] !== $last) {
                $out($job);
                $last = $job['message'];
            }
            if (!in_array($job['status'], ['queued', 'running'], true)) {
                return $job;
            }
        }
    }

    /** Hook for the background worker: advance the oldest queued/running job. */
    public static function processNext(float $budget = 20.0): bool
    {
        $a = self::active();
        if ($a === null) {
            return false;
        }
        $job = self::step($a['id'], $budget);
        return $job !== null && empty($job['_busy']);

    }

    private static function reader(array $job): SitePackReader
    {
        $r = new SitePackReader((string) $job['pack_path']);
        if (!$r->open()) {
            throw new \RuntimeException(implode(' ', array_slice($r->errors, 0, 3)));
        }
        return $r;
    }

    private static function runPhase(array &$job, float $deadline): void
    {
        $id = $job['id'];
        switch ($job['phase']) {
            case 'validate':
                $r = new SitePackReader((string) $job['pack_path']);
                if (!$r->open()) {
                    throw new \RuntimeException(implode(' ', array_slice($r->errors, 0, 3)));
                }
                if ($r->isVerified()) {
                    $next = -1;
                } else {
                    $next = $r->verifyChecksums((int) $job['cursor'], $deadline);
                    if ($r->errors) {
                        throw new \RuntimeException(implode(' ', array_slice($r->errors, 0, 3)));
                    }
                }
                if ($next === -1) {
                    $r->markVerified();
                    $job['cursor'] = 0;
                    $job['phase'] = 'plan';
                    self::progress($job, 'validate', 1);
                    self::log($job, 'Pack verified (' . $r->fileCount() . ' files, checksums OK).');
                } else {
                    $job['cursor'] = $next;
                    self::progress($job, 'validate', $next / max(1, $r->fileCount()));
                    self::log($job, 'Verifying checksums… ' . $next . ' / ' . $r->fileCount());
                }
                $r->close();
                return;

            case 'plan':
                $r = self::reader($job);
                $plan = (new SitePackImporter($r))->plan($job['options']);
                $r->close();
                $job['plan'] = $plan;
                $job['totals'] = $plan['totals'];
                $job['phase'] = 'backup';
                self::progress($job, 'plan', 1);
                $t = $plan['totals'];
                self::log($job, sprintf('Plan: %d to update, %d to add, %d unchanged, %d conflicts skipped, %d to remove, %d files.', $t['update'], $t['add'], $t['unchanged'], $t['conflict'], $t['remove'], count($plan['files'])));
                return;

            case 'backup':
                self::log($job, 'Creating a full backup…');
                $job['backup'] = basename((new BackupService())->createManaged());
                $undo = SitePack::importsDir() . '/undo/' . $id;
                @mkdir($undo . '/media', 0755, true);
                $snap = $undo . '/database.sqlite';
                @unlink($snap);
                Database::connection()->exec('VACUUM INTO ' . Database::connection()->quote($snap));
                $job['phase'] = 'stage';
                $job['cursor'] = 0;
                self::progress($job, 'backup', 1);
                self::log($job, 'Backup ' . $job['backup'] . ' created; undo snapshot saved.');
                return;

            case 'stage':
                $files = (array) ($job['plan']['files'] ?? []);
                if ($files === []) {
                    $next = -1;
                } else {
                    $r = self::reader($job);
                    $next = (new SitePackImporter($r))->stage($files, SitePack::importsDir() . '/staging/' . $id, (int) $job['cursor'], $deadline);
                    $r->close();
                }
                if ($next === -1) {
                    $job['phase'] = 'apply';
                    $job['cursor'] = 0;
                    self::progress($job, 'stage', 1);
                    self::log($job, 'Media staged (' . count($files) . ' files, verified).');
                } else {
                    $job['cursor'] = $next;
                    self::progress($job, 'stage', $next / max(1, count($files)));
                    self::log($job, 'Staging media… ' . $next . ' / ' . count($files));
                }
                return;

            case 'apply':
                self::log($job, 'Writing to the database (one transaction)…');
                $r = self::reader($job);
                $res = (new SitePackImporter($r))->applyDatabase($job['plan'], ['user_id' => $job['user_id'], 'fail_after' => $job['options']['fail_after'] ?? 0]);
                $r->close();
                $job['result'] = $res;
                $job['phase'] = 'swap';
                self::progress($job, 'apply', 1);
                $s = $res['stats'];
                self::log($job, sprintf('Database updated: %d updated, %d added, %d removed, %d settings, %d revisions.', $s['updated'], $s['added'], $s['removed'], $s['settings'], $s['revisions']));
                return;

            case 'swap':
                $res = SitePackImporter::swap((array) ($job['plan']['files'] ?? []), (array) ($job['plan']['remove_files'] ?? []),
                    SitePack::importsDir() . '/staging/' . $id, SitePack::importsDir() . '/undo/' . $id . '/media');
                $job['result']['media'] = $res;
                SitePackExporter::rrmdir(SitePack::importsDir() . '/staging/' . $id);
                $job['phase'] = 'finalize';
                self::progress($job, 'swap', 1);
                self::log($job, sprintf('Media swapped in: %d replaced, %d added, %d removed.', count($res['replaced']), count($res['added']), count($res['removed'])));
                return;

            case 'finalize':
                $done = SitePackImporter::refreshCaches();
                $job['result']['refreshed'] = $done;
                $job['status'] = 'done';
                $job['phase'] = 'done';
                $job['progress'] = 100;
                $job['finished'] = time();
                unset($job['plan']['files'], $job['plan']['remove_files']);
                self::log($job, 'Import finished — ' . implode(', ', $done) . '.');
                self::audit($job, 'site_pack.imported', [
                    'job' => $id, 'pack' => $job['pack'], 'backup' => $job['backup'] ?? '', 'stats' => $job['result']['stats'] ?? [],
                    'media' => ['replaced' => count($job['result']['media']['replaced'] ?? []), 'added' => count($job['result']['media']['added'] ?? []), 'removed' => count($job['result']['media']['removed'] ?? [])],
                ]);
                self::pruneUndo();
                return;
        }
        throw new \RuntimeException('Unknown phase.');
    }

    private static function fail(array &$job, \Throwable $e): void
    {
        $job['status'] = 'failed';
        $job['finished'] = time();
        $job['error'] = mb_substr($e->getMessage(), 0, 500);
        $phase = $job['phase'];
        $note = in_array($phase, ['validate', 'plan', 'backup', 'stage', 'apply'], true)
            ? ' Nothing on the site was changed (the database transaction was rolled back).'
            : ' The database changes were committed; use Undo to roll back.';
        self::log($job, 'Failed during ' . $phase . ': ' . $job['error'] . $note);
        SitePackExporter::rrmdir(SitePack::importsDir() . '/staging/' . $job['id']);
        if (in_array($phase, ['validate', 'plan', 'backup', 'stage', 'apply'], true)) {
            SitePackExporter::rrmdir(SitePack::importsDir() . '/undo/' . $job['id']);
        }
        unset($job['plan']['files'], $job['plan']['remove_files']);
        self::save($job);
        self::audit($job, 'site_pack.import_failed', ['job' => $job['id'], 'pack' => $job['pack'], 'phase' => $phase, 'error' => $job['error']]);
    }

    private static function audit(array $job, string $action, array $details): void
    {
        try {
            if (Auth::id() === null && !empty($job['user_id'])) {
                // Background run: record who started it.
                Database::insert('audit_logs', [
                    'user_id' => (int) $job['user_id'], 'action' => $action, 'entity_type' => 'site_pack', 'entity_id' => null,
                    'details' => json_encode($details, JSON_UNESCAPED_SLASHES), 'ip_address' => null, 'created_at' => date('c'),
                ]);
                return;
            }
        } catch (\Throwable $e) {
        }
        Audit::log($action, 'site_pack', null, $details);
    }

    public static function undo(string $id, ?int $userId): array
    {
        $job = self::load($id);
        $u = self::undoable();
        if ($job === null || $u === null || $u['id'] !== $id) {
            throw new \RuntimeException('Only the most recent import can be undone.');
        }
        $lock = fopen(self::file($id) . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('This import is busy.');
        }
        try {
            @set_time_limit(0);
            $res = SitePackImporter::undo($job);
            $usr = $userId ? Database::fetch('SELECT username, name FROM users WHERE id = :id', ['id' => $userId]) : null;
            $job['undone_at'] = time();
            $job['undone_by'] = $usr ? (string) ($usr['name'] ?: $usr['username']) : 'CLI';
            $job['undo_result'] = $res;
            self::log($job, 'Undone: ' . count($res['tables']) . ' tables and ' . $res['files'] . ' files restored.');
            self::save($job);
            Audit::log('site_pack.import_undone', 'site_pack', null, ['job' => $id, 'pack' => $job['pack'], 'tables' => $res['tables'], 'files' => $res['files']]);
            SitePackExporter::rrmdir(SitePack::importsDir() . '/undo/' . $id);
            return $job;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Keep undo data only for the last few imports (disk space on shared hosting). */
    private static function pruneUndo(): void
    {
        $keep = 0;
        foreach (self::all() as $j) {
            $dir = SitePack::importsDir() . '/undo/' . $j['id'];
            if (!is_dir($dir)) {
                continue;
            }
            if ($j['status'] === 'done' && empty($j['undone_at']) && $keep < self::KEEP_UNDO) {
                $keep++;
                continue;
            }
            if (!in_array($j['status'], ['queued', 'running'], true)) {
                SitePackExporter::rrmdir($dir);
            }
        }
    }
}
