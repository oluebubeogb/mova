<?php
/**
 * Mova CMS - Basic SMTP mail + subscribers
 */

namespace Mova\Mail;

use Mova\Core\Bootstrap;
use Mova\Core\Database;

class MailService
{
    public function allSubscribers(int $limit = 200): array
    {
        return Database::fetchAll(
            "SELECT * FROM subscribers ORDER BY subscribed_at DESC LIMIT :limit",
            ['limit' => $limit]
        );
    }

    public function addSubscriber(string $email, ?string $name = null): int
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        $existing = Database::fetch("SELECT id, status FROM subscribers WHERE email = :e", ['e' => $email]);
        if ($existing) {
            if ($existing['status'] !== 'active') {
                Database::update('subscribers', [
                    'status'         => 'active',
                    'name'           => $name,
                    'unsubscribed_at'=> null,
                    'subscribed_at'  => date('c'),
                    'token'          => bin2hex(random_bytes(16)),
                ], 'id = :id', ['id' => $existing['id']]);
            }
            return (int) $existing['id'];
        }

        return Database::insert('subscribers', [
            'email'          => $email,
            'name'           => $name,
            'status'         => 'active',
            'subscribed_at'  => date('c'),
            'token'          => bin2hex(random_bytes(16)),
        ]);
    }

    public function unsubscribe(string $token): bool
    {
        $row = Database::fetch("SELECT id FROM subscribers WHERE token = :t", ['t' => $token]);
        if (!$row) {
            return false;
        }
        return Database::update('subscribers', [
            'status'          => 'unsubscribed',
            'unsubscribed_at' => date('c'),
        ], 'id = :id', ['id' => $row['id']]) > 0;
    }

    public function deleteSubscriber(int $id): bool
    {
        return Database::delete('subscribers', 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * Send a simple campaign to active subscribers.
     * @return array{sent:int, failed:int, errors:string[]}
     */
    public function sendCampaign(string $subject, string $htmlBody, ?string $textBody = null): array
    {
        $subs = Database::fetchAll(
            "SELECT email, name, token FROM subscribers WHERE status = 'active'"
        );
        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($subs as $sub) {
            $body = $htmlBody;
            $unsub = Bootstrap::baseUrl() . '/unsubscribe?token=' . urlencode($sub['token'] ?? '');
            $body .= '<p style="font-size:12px;color:#888;margin-top:2em;">'
                . '<a href="' . htmlspecialchars($unsub) . '">Unsubscribe</a></p>';

            try {
                $this->send($sub['email'], $subject, $body, $textBody);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = $sub['email'] . ': ' . $e->getMessage();
            }
        }

        return compact('sent', 'failed', 'errors');
    }

    /**
     * Resolve outbound SMTP from Audience mailbox accounts (default account first).
     * Legacy Settings SMTP is no longer used.
     *
     * @return array{from_email:string,from_name:string,host:string,port:int,user:string,pass:string,enc:string}
     */
    public function resolveTransport(?int $accountId = null): array
    {
        $repo = new MailAccountRepository();
        $account = null;
        if ($accountId) {
            $account = $repo->find($accountId);
        }
        if (!$account) {
            $account = $repo->defaultAccount();
        }
        if (!$account) {
            $all = $repo->all();
            $account = $all[0] ?? null;
        }
        if (!$account) {
            throw new \RuntimeException(
                'No mailbox account configured. Add an account under Audience → Mailbox → Accounts and set SMTP.'
            );
        }

        $fromEmail = trim((string) ($account['email'] ?: $account['smtp_username'] ?: $account['imap_username'] ?? ''));
        $fromName = trim((string) ($account['display_name'] ?: $account['label'] ?: $fromEmail));
        $host = trim((string) ($account['smtp_host'] ?? ''));
        $port = (int) ($account['smtp_port'] ?: 587);
        $user = trim((string) ($account['smtp_username'] ?: $account['imap_username'] ?? ''));
        $pass = (string) (($account['smtp_password'] !== '' && $account['smtp_password'] !== null)
            ? $account['smtp_password']
            : ($account['imap_password'] ?? ''));
        $enc = (string) ($account['smtp_encryption'] ?? 'tls');

        if ($fromEmail === '') {
            throw new \RuntimeException('Mailbox account has no From email. Edit the account and set Email.');
        }
        if ($host === '') {
            throw new \RuntimeException(
                'Mailbox account has no SMTP host. Edit Accounts and fill SMTP settings for outbound mail.'
            );
        }

        return [
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'pass' => $pass,
            'enc' => $enc,
            'account_id' => (int) ($account['id'] ?? 0),
        ];
    }

    public function send(string $to, string $subject, string $html, ?string $text = null, ?int $accountId = null): void
    {
        $t = $this->resolveTransport($accountId);
        $this->sendSmtp(
            $t['host'],
            $t['port'],
            $t['user'],
            $t['pass'],
            $t['enc'],
            $t['from_email'],
            $t['from_name'],
            $to,
            $subject,
            $html,
            $text
        );
    }

    private function sendSmtp(
        string $host, int $port, string $user, string $pass, string $enc,
        string $fromEmail, string $fromName, string $to, string $subject, string $html, ?string $text
    ): void {
        $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 20);
        if (!$fp) {
            throw new \RuntimeException("SMTP connect failed: $errstr ($errno)");
        }
        stream_set_timeout($fp, 20);

        $this->smtpExpect($fp, 220);
        $this->smtpCmd($fp, 'EHLO mova.local', 250);

        if ($enc === 'tls') {
            $this->smtpCmd($fp, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('STARTTLS failed');
            }
            $this->smtpCmd($fp, 'EHLO mova.local', 250);
        }

        if ($user !== '') {
            $this->smtpCmd($fp, 'AUTH LOGIN', 334);
            $this->smtpCmd($fp, base64_encode($user), 334);
            $this->smtpCmd($fp, base64_encode($pass), 235);
        }

        $this->smtpCmd($fp, 'MAIL FROM:<' . $fromEmail . '>', 250);
        $this->smtpCmd($fp, 'RCPT TO:<' . $to . '>', 250);
        $this->smtpCmd($fp, 'DATA', 354);

        $boundary = 'mova_' . bin2hex(random_bytes(8));
        $msg = 'From: ' . $this->formatAddress($fromName, $fromEmail) . "\r\n";
        $msg .= 'To: <' . $to . ">\r\n";
        $msg .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $msg .= "\r\n" . $html . "\r\n.";
        fwrite($fp, $msg . "\r\n");
        $this->smtpExpect($fp, 250);
        $this->smtpCmd($fp, 'QUIT', 221);
        fclose($fp);
    }

    private function smtpCmd($fp, string $cmd, int $expect): void
    {
        fwrite($fp, $cmd . "\r\n");
        $this->smtpExpect($fp, $expect);
    }

    private function smtpExpect($fp, int $code): void
    {
        $line = '';
        while ($str = fgets($fp, 512)) {
            $line .= $str;
            if (isset($str[3]) && $str[3] === ' ') {
                break;
            }
        }
        if ((int) substr($line, 0, 3) !== $code) {
            throw new \RuntimeException('SMTP unexpected response: ' . trim($line));
        }
    }

    private function formatAddress(string $name, string $email): string
    {
        if ($name === '') {
            return '<' . $email . '>';
        }
        return '"' . addcslashes($name, '"\\') . '" <' . $email . '>';
    }

    private function setting(string $key, $default = null)
    {
        static $cache = [];
        if (!array_key_exists($key, $cache)) {
            try {
                $row = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = :k", ['k' => $key]);
                $cache[$key] = $row['setting_value'] ?? $default;
            } catch (\Throwable $e) {
                $cache[$key] = $default;
            }
        }
        return $cache[$key] ?? $default;
    }
}
