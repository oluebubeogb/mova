<?php
/**
 * Mova HQ Mailbox — multi-account IMAP + SMTP
 */

namespace Mova\Mail;

use Mova\Core\Bootstrap;

class MailboxService
{
    private ?array $account = null;

    public function isImapAvailable(): bool
    {
        return function_exists('imap_open');
    }

    public function useAccount(array $account): self
    {
        $this->account = $account;
        return $this;
    }

    public function account(): ?array
    {
        return $this->account;
    }

    public function isConfigured(): bool
    {
        $a = $this->account;
        return $a && !empty($a['imap_host']) && !empty($a['imap_username']) && !empty($a['imap_password']);
    }

    /**
     * Map a friendly folder key (Sent, Drafts, Spam, …) to a real IMAP mailbox name.
     * Providers differ: "Sent", "INBOX.Sent", "[Gmail]/Sent Mail", etc.
     */
    public function resolveFolder(string $folder): string
    {
        $folder = trim($folder) !== '' ? $folder : 'INBOX';
        if (strcasecmp($folder, 'INBOX') === 0) {
            return 'INBOX';
        }
        try {
            $available = $this->folders();
        } catch (\Throwable $e) {
            return $folder;
        }
        // Exact match first
        foreach ($available as $name) {
            if (strcasecmp($name, $folder) === 0) {
                return $name;
            }
        }
        $aliases = [
            'sent' => ['sent', 'sent items', 'sent messages', 'sent mail', '[gmail]/sent mail', 'inbox.sent', 'inbox.sent items'],
            'drafts' => ['drafts', 'draft', '[gmail]/drafts', 'inbox.drafts', 'inbox.draft'],
            'spam' => ['spam', 'junk', 'junk e-mail', 'junk email', '[gmail]/spam', 'inbox.spam', 'inbox.junk'],
            'junk' => ['junk', 'spam', 'junk e-mail', 'junk email', '[gmail]/spam', 'inbox.junk', 'inbox.spam'],
            'trash' => ['trash', 'deleted', 'deleted items', 'deleted messages', '[gmail]/trash', 'inbox.trash', 'inbox.deleted'],
        ];
        $key = strtolower($folder);
        // normalize keys like INBOX.spam
        $key = preg_replace('/^inbox[\.\/]/', '', $key) ?? $key;
        $want = $aliases[$key] ?? [strtolower($folder)];
        foreach ($available as $name) {
            $n = strtolower($name);
            $base = preg_replace('/^inbox[\.\/]/', '', $n) ?? $n;
            foreach ($want as $alias) {
                if ($n === $alias || $base === $alias || str_ends_with($n, '/' . $alias) || str_ends_with($n, '.' . $alias)) {
                    return $name;
                }
            }
        }
        // Partial contains
        foreach ($available as $name) {
            $n = strtolower($name);
            foreach ($want as $alias) {
                if (str_contains($n, $alias)) {
                    return $name;
                }
            }
        }
        return $folder;
    }

    /** @return resource|\IMAP\Connection */
    public function connect(string $mailbox = 'INBOX')
    {
        if (!$this->isImapAvailable()) {
            throw new \RuntimeException('PHP IMAP extension is not installed (ext-imap).');
        }
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Select or configure a mail account first.');
        }
        $mailbox = $this->resolveFolder($mailbox);
        $a = $this->account;
        $flags = '/imap';
        $enc = $a['imap_encryption'] ?? 'ssl';
        if ($enc === 'ssl') {
            $flags .= '/ssl';
        } elseif ($enc === 'tls') {
            $flags .= '/tls';
        } else {
            $flags .= '/notls';
        }
        $flags .= '/novalidate-cert';
        $ref = '{' . $a['imap_host'] . ':' . (int) ($a['imap_port'] ?? 993) . $flags . '}' . $mailbox;
        $conn = @imap_open($ref, $a['imap_username'], $a['imap_password']);
        if (!$conn) {
            throw new \RuntimeException('IMAP connection failed (' . $mailbox . '): ' . (imap_last_error() ?: 'unknown error'));
        }
        return $conn;
    }

    private function mailboxRef(): string
    {
        $a = $this->account;
        $flags = '/imap';
        $enc = $a['imap_encryption'] ?? 'ssl';
        if ($enc === 'ssl') {
            $flags .= '/ssl';
        } elseif ($enc === 'tls') {
            $flags .= '/tls';
        } else {
            $flags .= '/notls';
        }
        $flags .= '/novalidate-cert';
        return '{' . $a['imap_host'] . ':' . (int) ($a['imap_port'] ?? 993) . $flags . '}';
    }

    /** @return list<string> */
    public function folders(): array
    {
        $conn = $this->connect('INBOX');
        $ref = $this->mailboxRef();
        $list = imap_list($conn, $ref, '*') ?: [];
        imap_close($conn);
        $out = [];
        foreach ($list as $full) {
            $name = str_replace($ref, '', $full);
            $out[] = $name !== '' ? $name : 'INBOX';
        }
        if (!$out) {
            $out = ['INBOX'];
        }
        sort($out);
        return $out;
    }

    /**
     * @return list<array{uid:int,subject:string,from:string,date:string,seen:bool,size:int}>
     */
    public function listMessages(string $folder = 'INBOX', int $limit = 40, int $page = 1, string $query = ''): array
    {
        $conn = $this->connect($folder);
        $n = imap_num_msg($conn);
        $msgnos = [];

        if ($query !== '') {
            $criteria = 'OR OR SUBJECT "' . addslashes($query) . '" FROM "' . addslashes($query) . '" TO "' . addslashes($query) . '"';
            $found = @imap_search($conn, $criteria, SE_UID);
            if ($found === false) {
                // fallback ALL and filter in PHP
                $found = [];
                for ($i = 1; $i <= $n; $i++) {
                    $found[] = imap_uid($conn, $i);
                }
            }
            rsort($found);
            $page = max(1, $page);
            $slice = array_slice($found, ($page - 1) * $limit, $limit);
            foreach ($slice as $uid) {
                $msgnos[] = ['uid' => (int) $uid, 'msgno' => imap_msgno($conn, (int) $uid)];
            }
        } else {
            if ($n < 1) {
                imap_close($conn);
                return [];
            }
            $page = max(1, $page);
            $end = $n - (($page - 1) * $limit);
            $start = max(1, $end - $limit + 1);
            for ($i = $end; $i >= $start; $i--) {
                $msgnos[] = ['uid' => (int) imap_uid($conn, $i), 'msgno' => $i];
            }
        }

        $out = [];
        $qLower = mb_strtolower($query);
        foreach ($msgnos as $ref) {
            $i = $ref['msgno'];
            if ($i < 1) {
                continue;
            }
            $ov = imap_headerinfo($conn, $i);
            if (!$ov) {
                continue;
            }
            $from = '';
            if (!empty($ov->from)) {
                $f = $ov->from[0];
                $from = trim(($f->personal ?? '') . ' <' . ($f->mailbox ?? '') . '@' . ($f->host ?? '') . '>');
            }
            $subject = isset($ov->subject) ? $this->decodeMime($ov->subject) : '(no subject)';
            if ($qLower !== '') {
                $hay = mb_strtolower($subject . ' ' . $from);
                if (!str_contains($hay, $qLower) && $query !== '') {
                    // if imap_search worked we already filtered; still ok
                }
            }
            $excerpt = '';
            try {
                // Lightweight preview — do not mark seen
                $peek = @imap_fetchbody($conn, $i, '1', FT_PEEK);
                if (is_string($peek) && $peek !== '') {
                    $peek = preg_replace('/\s+/', ' ', strip_tags($this->decodeMime($peek)));
                    $excerpt = mb_substr(trim($peek), 0, 120);
                }
            } catch (\Throwable $e) {
                $excerpt = '';
            }
            $out[] = [
                'uid'     => $ref['uid'],
                'msgno'   => $i,
                'subject' => $subject,
                'from'    => $this->decodeMime($from),
                'date'    => isset($ov->date) ? date('Y-m-d H:i', strtotime($ov->date)) : '',
                'seen'    => empty($ov->Unseen),
                'size'    => (int) ($ov->Size ?? 0),
                'excerpt' => $excerpt,
            ];
        }
        imap_close($conn);
        return $out;
    }

    public function messageCount(string $folder = 'INBOX'): int
    {
        $conn = $this->connect($folder);
        $n = imap_num_msg($conn);
        imap_close($conn);
        return (int) $n;
    }

    public function getMessage(string $folder, int $uid): array
    {
        $conn = $this->connect($folder);
        $msgno = imap_msgno($conn, $uid);
        if ($msgno < 1) {
            imap_close($conn);
            throw new \RuntimeException('Message not found.');
        }
        $ov = imap_headerinfo($conn, $msgno);
        $structure = imap_fetchstructure($conn, $msgno);
        $parsed = $this->parseStructure($conn, $msgno, $structure);

        $from = $this->formatAddressList($ov->from ?? []);
        $to = $this->formatAddressList($ov->to ?? []);
        $cc = $this->formatAddressList($ov->cc ?? []);
        $replyTo = $from;
        if (!empty($ov->reply_to[0])) {
            $r = $ov->reply_to[0];
            $replyTo = ($r->mailbox ?? '') . '@' . ($r->host ?? '');
        }

        @imap_setflag_full($conn, (string) $msgno, '\\Seen');
        imap_close($conn);

        return [
            'uid'         => $uid,
            'folder'      => $folder,
            'subject'     => isset($ov->subject) ? $this->decodeMime($ov->subject) : '(no subject)',
            'from'        => $from,
            'to'          => $to,
            'cc'          => $cc,
            'reply_to'    => $replyTo,
            'date'        => isset($ov->date) ? date('Y-m-d H:i', strtotime($ov->date)) : '',
            'body_html'   => $parsed['html'],
            'body_text'   => $parsed['text'],
            'attachments' => $parsed['attachments'],
        ];
    }

    public function getAttachment(string $folder, int $uid, string $part): array
    {
        $conn = $this->connect($folder);
        $msgno = imap_msgno($conn, $uid);
        if ($msgno < 1) {
            imap_close($conn);
            throw new \RuntimeException('Message not found.');
        }
        $structure = imap_fetchstructure($conn, $msgno);
        $meta = $this->findPartMeta($structure, $part);
        $data = imap_fetchbody($conn, $msgno, $part);
        $data = $this->decodePart($data, $meta['encoding'] ?? 0);
        imap_close($conn);
        return [
            'filename' => $meta['filename'] ?? ('part-' . $part),
            'mime'     => $meta['mime'] ?? 'application/octet-stream',
            'data'     => $data,
        ];
    }

    public function deleteMessage(string $folder, int $uid): void
    {
        $conn = $this->connect($folder);
        $msgno = imap_msgno($conn, $uid);
        if ($msgno > 0) {
            imap_delete($conn, (string) $msgno);
            imap_expunge($conn);
        }
        imap_close($conn);
    }

    /**
     * @param string|list<string> $to
     * @param string|list<string>|null $cc
     * @param list<array{name:string,tmp:string,type:string}> $attachments
     */
    public function send($to, string $subject, string $html, $cc = null, array $attachments = []): void
    {
        $recipients = $this->parseAddressList($to);
        if (!$recipients) {
            throw new \InvalidArgumentException('At least one recipient is required.');
        }
        $ccList = $cc ? $this->parseAddressList($cc) : [];
        $a = $this->account;

        // Prefer account SMTP if set, else global MailService settings
        if ($a && !empty($a['smtp_host'])) {
            $rfc822 = $this->sendSmtpAccount($recipients, $ccList, $subject, $html, $attachments);
            $this->appendToSent($rfc822);
            return;
        }

        $mail = new MailService();
        foreach (array_merge($recipients, $ccList) as $addr) {
            $mail->send($addr, $subject, $html);
        }
        // Best-effort copy into IMAP Sent when account is linked
        if ($a && $this->isConfigured()) {
            $fromEmail = $a['email'] ?: ($a['smtp_username'] ?? $a['imap_username'] ?? '');
            $fromName = $a['display_name'] ?: $fromEmail;
            $headers = [
                'From: ' . $this->formatAddress($fromName, $fromEmail),
                'To: ' . implode(', ', $recipients),
            ];
            if ($ccList) {
                $headers[] = 'Cc: ' . implode(', ', $ccList);
            }
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Date: ' . date('r');
            $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $this->appendToSent(implode("\r\n", $headers) . "\r\n\r\n" . $html);
        }
    }

    /**
     * Save a copy of an outbound message into the account's Sent folder (IMAP APPEND).
     * SMTP alone never does this — webmail clients always append after send.
     */
    /**
     * Save a draft into the account's Drafts folder (IMAP APPEND + \Draft).
     */
    public function saveDraft(string $to, string $subject, string $html, string $cc = ''): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Mail account is not configured for IMAP.');
        }
        $a = $this->account;
        $fromEmail = $a['email'] ?: ($a['smtp_username'] ?? $a['imap_username'] ?? '');
        $fromName = $a['display_name'] ?: $fromEmail;
        $toList = $this->parseAddressList($to);
        $ccList = $cc !== '' ? $this->parseAddressList($cc) : [];

        $headers = [
            'From: ' . $this->formatAddress($fromName, $fromEmail),
            'To: ' . ($toList ? implode(', ', $toList) : ''),
        ];
        if ($ccList) {
            $headers[] = 'Cc: ' . implode(', ', $ccList);
        }
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@mova.local>';
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject !== '' ? $subject : '(no subject)') . '?=';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $rfc822 = implode("\r\n", $headers) . "\r\n\r\n" . $html;
        $rfc822 = str_replace(["\r\n", "\r"], "\n", $rfc822);
        $rfc822 = str_replace("\n", "\r\n", $rfc822);
        if (!str_ends_with($rfc822, "\r\n")) {
            $rfc822 .= "\r\n";
        }

        $drafts = $this->resolveFolder('Drafts');
        $conn = $this->connect($drafts);
        $ok = @imap_append($conn, $this->mailboxRef() . $drafts, $rfc822, '\\Draft \\Seen');
        if (!$ok) {
            $ok = @imap_append($conn, $this->mailboxRef() . $drafts, $rfc822, '\\Draft');
        }
        if (!$ok) {
            $ok = @imap_append($conn, $this->mailboxRef() . $drafts, $rfc822);
        }
        imap_close($conn);
        if (!$ok) {
            throw new \RuntimeException('Could not save draft: ' . (imap_last_error() ?: 'unknown error'));
        }
    }

    /**
     * Build a Gmail-style reply quote from an original message.
     */
    public static function formatReplyQuote(array $message): string
    {
        $from = (string) ($message['from'] ?? '');
        $date = (string) ($message['date'] ?? '');
        // Prefer a readable date label
        $when = $date;
        if ($date !== '') {
            $ts = strtotime($date);
            if ($ts) {
                $when = date('D, M j, Y \a\t g:i A', $ts);
            }
        }
        $text = trim((string) ($message['body_text'] ?? ''));
        if ($text === '' && !empty($message['body_html'])) {
            $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", (string) $message['body_html'])), ENT_QUOTES, 'UTF-8'));
        }
        $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
        $lines = explode("\n", $text);
        $quoted = [];
        foreach ($lines as $line) {
            $quoted[] = '> ' . rtrim($line);
        }
        $block = implode("\n", $quoted);
        return "\n\n\nOn {$when}, {$from} wrote:\n\n{$block}\n";
    }

    public function appendToSent(string $rfc822): void
    {
        if (!$this->isConfigured() || trim($rfc822) === '') {
            return;
        }
        // Ensure Date header exists (required by some servers)
        if (!preg_match('/^Date:/mi', $rfc822)) {
            $rfc822 = 'Date: ' . date('r') . "\r\n" . $rfc822;
        }
        // Normalize line endings for IMAP
        $rfc822 = str_replace(["\r\n", "\r"], "\n", $rfc822);
        $rfc822 = str_replace("\n", "\r\n", $rfc822);
        if (!str_ends_with($rfc822, "\r\n")) {
            $rfc822 .= "\r\n";
        }

        try {
            $sent = $this->resolveFolder('Sent');
            $conn = $this->connect($sent);
            $ok = @imap_append($conn, $this->mailboxRef() . $sent, $rfc822, '\\Seen');
            if (!$ok) {
                // Retry without flags (some hosts reject flag list)
                $ok = @imap_append($conn, $this->mailboxRef() . $sent, $rfc822);
            }
            imap_close($conn);
            if (!$ok) {
                // Non-fatal: mail was already delivered via SMTP
                error_log('Mova mailbox: failed to APPEND to Sent (' . $sent . '): ' . (imap_last_error() ?: 'unknown'));
            }
        } catch (\Throwable $e) {
            error_log('Mova mailbox: appendToSent error: ' . $e->getMessage());
        }
    }

    /**
     * @return string RFC822 message that was sent (for Sent-folder append)
     */
    private function sendSmtpAccount(array $to, array $cc, string $subject, string $html, array $attachments): string
    {
        $a = $this->account;
        $fromEmail = $a['email'] ?: $a['smtp_username'];
        $fromName = $a['display_name'] ?: $fromEmail;
        $boundary = 'mova_' . bin2hex(random_bytes(8));
        $headers = [];
        $headers[] = 'From: ' . $this->formatAddress($fromName, $fromEmail);
        $headers[] = 'To: ' . implode(', ', $to);
        if ($cc) {
            $headers[] = 'Cc: ' . implode(', ', $cc);
        }
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@mova.local>';
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';

        if ($attachments) {
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $body .= $html . "\r\n";
            foreach ($attachments as $att) {
                $raw = is_file($att['tmp']) ? file_get_contents($att['tmp']) : '';
                $body .= "--{$boundary}\r\n";
                $body .= 'Content-Type: ' . ($att['type'] ?: 'application/octet-stream') . '; name="' . addslashes($att['name']) . "\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= 'Content-Disposition: attachment; filename="' . addslashes($att['name']) . "\"\r\n\r\n";
                $body .= chunk_split(base64_encode($raw)) . "\r\n";
            }
            $body .= "--{$boundary}--\r\n";
        } else {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $body = $html;
        }

        $rfc822 = implode("\r\n", $headers) . "\r\n\r\n" . $body;

        $host = $a['smtp_host'];
        $port = (int) ($a['smtp_port'] ?: 587);
        $enc = $a['smtp_encryption'] ?? 'tls';
        $prefix = ($enc === 'ssl') ? 'ssl://' : '';
        $fp = @stream_socket_client($prefix . $host . ':' . $port, $errno, $errstr, 20);
        if (!$fp) {
            throw new \RuntimeException("SMTP connect failed: $errstr");
        }
        $this->smtpExpect($fp, 220);
        $this->smtpCmd($fp, 'EHLO mova.local', 250);
        if ($enc === 'tls') {
            $this->smtpCmd($fp, 'STARTTLS', 220);
            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->smtpCmd($fp, 'EHLO mova.local', 250);
        }
        $user = $a['smtp_username'] ?: $a['imap_username'];
        $pass = $a['smtp_password'] !== '' ? $a['smtp_password'] : $a['imap_password'];
        $this->smtpCmd($fp, 'AUTH LOGIN', 334);
        $this->smtpCmd($fp, base64_encode($user), 334);
        $this->smtpCmd($fp, base64_encode($pass), 235);
        $this->smtpCmd($fp, 'MAIL FROM:<' . $fromEmail . '>', 250);
        foreach (array_merge($to, $cc) as $rcpt) {
            $this->smtpCmd($fp, 'RCPT TO:<' . $rcpt . '>', 250);
        }
        $this->smtpCmd($fp, 'DATA', 354);
        fwrite($fp, $rfc822 . "\r\n.\r\n");
        $this->smtpExpect($fp, 250);
        $this->smtpCmd($fp, 'QUIT', 221);
        fclose($fp);

        return $rfc822;
    }

    private function smtpCmd($fp, string $cmd, int $expect): void
    {
        fwrite($fp, $cmd . "\r\n");
        $this->smtpExpect($fp, $expect);
    }

    private function smtpExpect($fp, int $code): void
    {
        $resp = '';
        while ($line = fgets($fp, 512)) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        if ((int) substr($resp, 0, 3) !== $code) {
            throw new \RuntimeException('SMTP error: ' . trim($resp));
        }
    }

    /** @return list<string> */
    public function parseAddressList($input): array
    {
        if (is_array($input)) {
            $raw = implode(',', $input);
        } else {
            $raw = (string) $input;
        }
        $parts = preg_split('/[,;]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (preg_match('/<([^>]+)>/', $p, $m)) {
                $p = trim($m[1]);
            }
            if (filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $out[] = $p;
            }
        }
        return array_values(array_unique($out));
    }

    public function applyFilters(array $messages, array $filters): array
    {
        foreach ($messages as &$m) {
            $m['flags'] = [];
            foreach ($filters as $f) {
                if (empty($f['is_active'])) {
                    continue;
                }
                $field = $f['match_field'] ?? 'from';
                $hay = mb_strtolower((string) ($m[$field] ?? $m['subject'] ?? ''));
                if ($field === 'subject') {
                    $hay = mb_strtolower($m['subject'] ?? '');
                } elseif ($field === 'from') {
                    $hay = mb_strtolower($m['from'] ?? '');
                }
                $val = mb_strtolower((string) ($f['match_value'] ?? ''));
                $op = $f['match_op'] ?? 'contains';
                $hit = match ($op) {
                    'equals' => $hay === $val,
                    'starts' => str_starts_with($hay, $val),
                    default => $val !== '' && str_contains($hay, $val),
                };
                if ($hit) {
                    $m['flags'][] = $f['action'] ?? 'flag';
                    if (($f['action'] ?? '') === 'label' && !empty($f['action_value'])) {
                        $m['label'] = $f['action_value'];
                    }
                }
            }
        }
        unset($m);
        return $messages;
    }

    private function parseStructure($conn, int $msgno, $structure, string $prefix = ''): array
    {
        $html = '';
        $text = '';
        $attachments = [];
        if (!$structure) {
            return ['html' => '', 'text' => (string) imap_body($conn, $msgno), 'attachments' => []];
        }

        $parts = [];
        if (empty($structure->parts)) {
            $parts[] = ['part' => $prefix ?: '1', 'struct' => $structure];
        } else {
            foreach ($structure->parts as $i => $p) {
                $num = $prefix === '' ? (string) ($i + 1) : $prefix . '.' . ($i + 1);
                if (!empty($p->parts)) {
                    $nested = $this->parseStructure($conn, $msgno, $p, $num);
                    if ($nested['html'] !== '') {
                        $html = $nested['html'];
                    }
                    if ($nested['text'] !== '' && $text === '') {
                        $text = $nested['text'];
                    }
                    $attachments = array_merge($attachments, $nested['attachments']);
                } else {
                    $parts[] = ['part' => $num, 'struct' => $p];
                }
            }
        }

        foreach ($parts as $item) {
            $p = $item['struct'];
            $partNo = $item['part'];
            $data = imap_fetchbody($conn, $msgno, $partNo);
            $data = $this->decodePart($data, $p->encoding ?? 0);
            $disp = $this->getDisposition($p);
            $filename = $this->getFilename($p);
            $subtype = strtoupper($p->subtype ?? 'PLAIN');
            $isAttach = ($disp === 'attachment') || ($filename !== '' && $subtype !== 'HTML' && $subtype !== 'PLAIN');
            if ($isAttach && $filename !== '') {
                $attachments[] = [
                    'part' => $partNo,
                    'filename' => $filename,
                    'mime' => strtolower(($p->type == 0 ? 'text' : 'application') . '/' . ($p->subtype ?? 'octet-stream')),
                    'size' => (int) ($p->bytes ?? strlen($data)),
                ];
                continue;
            }
            if ($subtype === 'HTML') {
                $html = $data;
            } elseif ($subtype === 'PLAIN' && $text === '') {
                $text = $data;
            }
        }
        return compact('html', 'text', 'attachments');
    }

    private function findPartMeta($structure, string $part): array
    {
        $parts = explode('.', $part);
        $current = $structure;
        foreach ($parts as $idx) {
            $i = (int) $idx - 1;
            if (!empty($current->parts[$i])) {
                $current = $current->parts[$i];
            }
        }
        return [
            'filename' => $this->getFilename($current),
            'mime' => strtolower('application/' . ($current->subtype ?? 'octet-stream')),
            'encoding' => (int) ($current->encoding ?? 0),
        ];
    }

    private function getDisposition($part): string
    {
        if (empty($part->dparameters) && empty($part->ifdisposition)) {
            return '';
        }
        return strtolower($part->disposition ?? '');
    }

    private function getFilename($part): string
    {
        foreach (['dparameters', 'parameters'] as $key) {
            if (empty($part->$key)) {
                continue;
            }
            foreach ($part->$key as $param) {
                if (in_array(strtolower($param->attribute ?? ''), ['filename', 'name'], true)) {
                    return $this->decodeMime($param->value ?? '');
                }
            }
        }
        return '';
    }

    private function formatAddressList(array $list): string
    {
        $parts = [];
        foreach ($list as $t) {
            $email = ($t->mailbox ?? '') . '@' . ($t->host ?? '');
            $personal = !empty($t->personal) ? $this->decodeMime($t->personal) : '';
            $parts[] = $personal ? ($personal . ' <' . $email . '>') : $email;
        }
        return implode(', ', $parts);
    }

    private function formatAddress(string $name, string $email): string
    {
        if ($name === '') {
            return $email;
        }
        return sprintf('"%s" <%s>', addslashes($name), $email);
    }

    private function decodePart(string $data, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($data) ?: $data,
            4 => quoted_printable_decode($data),
            default => $data,
        };
    }

    private function decodeMime(string $str): string
    {
        $out = @imap_mime_header_decode($str);
        if (!$out) {
            return $str;
        }
        $s = '';
        foreach ($out as $chunk) {
            $s .= $chunk->text;
        }
        return $s;
    }
}
