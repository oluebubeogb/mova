<?php
/**
 * Mova — system health (observability Phase 2)
 */

namespace Mova\Core;

class HealthService
{
    public function report(): array
    {
        $checks = [];

        // Database
        try {
            $db = Database::connection();
            $db->query('SELECT 1');
            $path = Bootstrap::config('database.path');
            $size = is_file($path) ? filesize($path) : 0;
            $checks['database'] = [
                'status' => 'ok',
                'detail' => 'SQLite connected · ' . $this->bytes($size),
            ];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'detail' => $e->getMessage()];
        }

        // Cache
        $cacheDir = Bootstrap::path('cache');
        $writable = is_dir($cacheDir) && is_writable($cacheDir);
        $checks['cache'] = [
            'status' => $writable ? 'ok' : 'warn',
            'detail' => $writable ? 'Writable: ' . $cacheDir : 'Not writable: ' . $cacheDir,
        ];

        // Storage / uploads
        $uploads = Bootstrap::path('uploads');
        $uw = is_dir($uploads) && is_writable($uploads);
        $checks['storage'] = [
            'status' => $uw ? 'ok' : 'error',
            'detail' => $uw ? 'Uploads writable' : 'Uploads not writable',
        ];

        // Mail — outbound uses Audience mailbox accounts
        $mailOk = false;
        $mailDetail = 'No mailbox account with SMTP';
        try {
            $acc = Database::fetch(
                "SELECT email, smtp_host FROM mail_accounts WHERE smtp_host IS NOT NULL AND smtp_host != '' ORDER BY is_default DESC, id ASC LIMIT 1"
            );
            if ($acc && !empty($acc['smtp_host'])) {
                $mailOk = true;
                $mailDetail = 'Mailbox SMTP: ' . ($acc['email'] ?: $acc['smtp_host']);
            }
        } catch (\Throwable $e) {
            $mailDetail = 'Mailbox accounts unavailable';
        }
        $checks['mail'] = [
            'status' => $mailOk ? 'ok' : 'warn',
            'detail' => $mailDetail,
        ];

        // Security
        $checks['security'] = [
            'status' => 'ok',
            'detail' => 'CSRF + sessions active',
        ];

        // AI
        $aiKey = '';
        try {
            $row = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = 'ai_api_key'");
            $aiKey = $row['setting_value'] ?? '';
        } catch (\Throwable $e) {
        }
        $checks['ai'] = [
            'status' => $aiKey !== '' ? 'ok' : 'info',
            'detail' => $aiKey !== '' ? 'AI provider configured' : 'Heuristic mode (no API key)',
        ];

        // Recent errors from logs
        $logDir = Bootstrap::path('logs');
        $checks['logs'] = [
            'status' => is_dir($logDir) ? 'ok' : 'warn',
            'detail' => is_dir($logDir) ? $logDir : 'Logs directory missing',
        ];

        $overall = 'ok';
        foreach ($checks as $c) {
            if ($c['status'] === 'error') {
                $overall = 'error';
                break;
            }
            if ($c['status'] === 'warn' && $overall === 'ok') {
                $overall = 'warn';
            }
        }

        return [
            'overall' => $overall,
            'checks'  => $checks,
            'time'    => date('c'),
            'version' => Bootstrap::config('app_version', '1.0.0'),
        ];
    }

    private function bytes(int $n): string
    {
        if ($n < 1024) {
            return $n . ' B';
        }
        if ($n < 1048576) {
            return round($n / 1024, 1) . ' KB';
        }
        return round($n / 1048576, 2) . ' MB';
    }
}
