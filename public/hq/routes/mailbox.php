<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Mail\MailboxService;
use Mova\Mail\MailAccountRepository;

/** @var \Mova\Core\Router $router */

function mova_mailbox_guard(): ?Response
{
    requireAuth();
    if (!Auth::can('manage_mail') && !Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    return null;
}

$router->get('/mailbox', function (Request $req) {
    if ($r = mova_mailbox_guard()) { return $r; }
    $repo = new MailAccountRepository();
    $accounts = $repo->all();
    $accountId = (int) $req->query('account', 0);
    $account = $accountId ? $repo->find($accountId) : $repo->defaultAccount();
    if (!$account && $accounts) { $account = $accounts[0]; }
    $folder = (string) $req->query('folder', 'INBOX');
    $page = max(1, (int) $req->query('page', 1));
    $uid = (int) $req->query('uid', 0);
    $q = trim((string) $req->query('q', ''));
    $tab = (string) $req->query('tab', 'inbox');
    $compose = $tab === 'compose' || (string) $req->query('compose', '') === '1';
    $replyUid = (int) $req->query('reply', 0);
    $data = [
        'title' => 'Mailbox', 'accounts' => $accounts, 'account' => $account,
        'folder' => $folder, 'page' => $page, 'q' => $q, 'tab' => $tab,
        'imap_ok' => (new MailboxService())->isImapAvailable(),
        'folders' => [], 'messages' => [], 'total' => 0, 'message' => null,
        'compose' => $compose || $replyUid > 0, 'reply' => null,
        'contacts' => $repo->contacts($account['id'] ?? null),
        'filters' => $repo->filters($account['id'] ?? null), 'error' => null,
    ];
    if (!$data['imap_ok']) {
        $data['error'] = 'PHP IMAP (ext-imap) is not available here. Core Mova and Campaigns still work. Mailbox inbox needs IMAP — common on Linux hosts, often missing on Windows PHP builds.';
        return renderHq('mailbox/index', $data);
    }
    if (!$account) {
        $data['tab'] = 'accounts';
        $data['error'] = 'Add a mail account to get started (switch profiles like Gmail).';
        return renderHq('mailbox/index', $data);
    }
    $box = (new MailboxService())->useAccount($account);
    try {
        if (in_array($tab, ['inbox', 'compose'], true) || $uid || $compose) {
            $data['folders'] = $box->folders();
            $data['total'] = $box->messageCount($folder);
            $data['messages'] = $box->applyFilters($box->listMessages($folder, 40, $page, $q), $data['filters']);
            if ($uid > 0) { $data['message'] = $box->getMessage($folder, $uid); }
            if ($replyUid > 0) {
                $data['reply'] = $box->getMessage($folder, $replyUid);
                $data['compose'] = true; $data['tab'] = 'compose';
            }
            $editUid = (int) $req->query('edit', 0);
            if ($editUid > 0) {
                $draftMsg = $box->getMessage($folder, $editUid);
                $data['reply'] = [
                    'reply_to' => '',
                    'subject' => $draftMsg['subject'] ?? '',
                    'body_text' => $draftMsg['body_text'] ?? strip_tags($draftMsg['body_html'] ?? ''),
                    'body_html' => $draftMsg['body_html'] ?? '',
                    'to' => $draftMsg['to'] ?? '',
                    'cc' => $draftMsg['cc'] ?? '',
                    'is_draft_edit' => true,
                    'draft_uid' => $editUid,
                    'draft_folder' => $folder,
                ];
                // Prefer To header for draft "to" field
                if (!empty($draftMsg['to'])) {
                    $data['reply']['reply_to'] = $draftMsg['to'];
                }
                $data['compose'] = true;
                $data['tab'] = 'compose';
            }
        }
    } catch (\Throwable $e) { $data['error'] = $e->getMessage(); }
    return renderHq('mailbox/index', $data);
});

$router->get('/mailbox/attachment', function (Request $req) {
    if ($r = mova_mailbox_guard()) { return $r; }
    $repo = new MailAccountRepository();
    $account = $repo->find((int) $req->query('account', 0)) ?: $repo->defaultAccount();
    if (!$account) { return (new Response())->status(404)->body('No account'); }
    try {
        $att = (new MailboxService())->useAccount($account)->getAttachment(
            (string) $req->query('folder', 'INBOX'), (int) $req->query('uid', 0), (string) $req->query('part', '1')
        );
        header('Content-Type: ' . $att['mime']);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $att['filename']) . '"');
        header('Content-Length: ' . strlen($att['data']));
        echo $att['data']; exit;
    } catch (\Throwable $e) {
        return (new Response())->status(500)->body($e->getMessage());
    }
});

$router->post('/mailbox/send', function (Request $req) {
    if ($r = mova_mailbox_guard()) { return $r; }
    if (!Csrf::validate()) { return (new Response())->status(403)->body('CSRF'); }
    $repo = new MailAccountRepository();
    $account = $repo->find((int) $req->post('account_id', 0)) ?: $repo->defaultAccount();
    if (!$account) { return (new Response())->redirect('/hq/mailbox?tab=accounts'); }
    try {
        $attachments = [];
        if (!empty($_FILES['attachments']['name']) && is_array($_FILES['attachments']['name'])) {
            foreach ($_FILES['attachments']['name'] as $i => $name) {
                if (($_FILES['attachments']['error'][$i] ?? 4) !== 0) continue;
                $attachments[] = ['name' => $name, 'tmp' => $_FILES['attachments']['tmp_name'][$i], 'type' => $_FILES['attachments']['type'][$i] ?? 'application/octet-stream'];
            }
        }
        $body = (string) $req->post('body', '');
        $html = (str_contains($body, '<') && str_contains($body, '>')) ? $body : nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
        (new MailboxService())->useAccount($account)->send((string)$req->post('to',''), trim((string)$req->post('subject','')), $html, (string)$req->post('cc',''), $attachments);
        Audit::log('mailbox.sent', 'mail_account', (int)$account['id'], ['to' => $req->post('to')]);
        return (new Response())->redirect('/hq/mailbox?account='.(int)$account['id'].'&sent=1');
    } catch (\Throwable $e) {
        return (new Response())->redirect('/hq/mailbox?account='.(int)$account['id'].'&compose=1&error='.urlencode($e->getMessage()));
    }
});


$router->post('/mailbox/draft/save', function (Request $req) {
    if ($r = mova_mailbox_guard()) { return $r; }
    if (!Csrf::validate()) { return (new Response())->status(403)->body('CSRF'); }
    $repo = new MailAccountRepository();
    $account = $repo->find((int) $req->post('account_id', 0)) ?: $repo->defaultAccount();
    if (!$account) { return (new Response())->redirect('/hq/mailbox?tab=accounts'); }
    $aid = (int) $account['id'];
    try {
        $body = (string) $req->post('body', '');
        $html = (str_contains($body, '<') && str_contains($body, '>')) ? $body : nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
        $box = (new MailboxService())->useAccount($account);
        $box->saveDraft(
            (string) $req->post('to', ''),
            trim((string) $req->post('subject', '')),
            $html,
            (string) $req->post('cc', '')
        );
        // If editing an existing draft, remove the old copy
        $oldUid = (int) $req->post('draft_uid', 0);
        $oldFolder = (string) $req->post('draft_folder', 'Drafts');
        if ($oldUid > 0) {
            try { $box->deleteMessage($oldFolder, $oldUid); } catch (\Throwable $e) { /* ignore */ }
        }
        Audit::log('mailbox.draft_saved', 'mail_account', $aid, ['subject' => $req->post('subject')]);
        return (new Response())->redirect('/hq/mailbox?account='.$aid.'&tab=inbox&folder=Drafts&draft_saved=1');
    } catch (\Throwable $e) {
        return (new Response())->redirect('/hq/mailbox?account='.$aid.'&compose=1&error='.urlencode($e->getMessage()));
    }
});

$router->post('/mailbox/delete', function (Request $req) {
    if ($r = mova_mailbox_guard()) { return $r; }
    if (!Csrf::validate()) { return (new Response())->status(403)->body('CSRF'); }
    $repo = new MailAccountRepository();
    $account = $repo->find((int)$req->post('account_id',0)) ?: $repo->defaultAccount();
    $folder = (string)$req->post('folder','INBOX');
    try { (new MailboxService())->useAccount($account)->deleteMessage($folder, (int)$req->post('uid',0)); }
    catch (\Throwable $e) { return (new Response())->redirect('/hq/mailbox?error='.urlencode($e->getMessage())); }
    return (new Response())->redirect('/hq/mailbox?account='.(int)($account['id']??0).'&folder='.urlencode($folder));
});

$router->post('/mailbox/account/save', function (Request $req) {
    if ($r = mova_mailbox_guard()) { return $r; }
    if (!Csrf::validate()) { return (new Response())->status(403)->body('CSRF'); }
    $repo = new MailAccountRepository();
    $id = (int)$req->post('id',0) ?: null;
    $newId = $repo->save([
        'label'=>$req->post('label'),'email'=>$req->post('email'),'display_name'=>$req->post('display_name'),
        'avatar'=>$req->post('avatar'),'imap_host'=>$req->post('imap_host'),'imap_port'=>$req->post('imap_port'),
        'imap_encryption'=>$req->post('imap_encryption'),'imap_username'=>$req->post('imap_username'),
        'imap_password'=>$req->post('imap_password'),'smtp_host'=>$req->post('smtp_host'),'smtp_port'=>$req->post('smtp_port'),
        'smtp_encryption'=>$req->post('smtp_encryption'),'smtp_username'=>$req->post('smtp_username'),
        'smtp_password'=>$req->post('smtp_password'),'is_default'=>$req->post('is_default'),
    ], $id);
    Audit::log('mailbox.account_saved','mail_account',$newId);
    return (new Response())->redirect('/hq/mailbox?tab=accounts&account='.$newId);
});

$router->post('/mailbox/account/delete', function (Request $req) {
    if ($r = mova_mailbox_guard()) { return $r; }
    if (!Csrf::validate()) { return (new Response())->status(403)->body('CSRF'); }
    (new MailAccountRepository())->delete((int)$req->post('id',0));
    return (new Response())->redirect('/hq/mailbox?tab=accounts');
});

$router->post('/mailbox/account/default', function (Request $req) {
    if ($r = mova_mailbox_guard()) { return $r; }
    if (!Csrf::validate()) { return (new Response())->status(403)->body('CSRF'); }
    (new MailAccountRepository())->setDefault((int)$req->post('id',0));
    return (new Response())->redirect('/hq/mailbox?account='.(int)$req->post('id',0));
});

$router->post('/mailbox/contact/save', function (Request $req) {
    if ($r = mova_mailbox_guard()) { return $r; }
    if (!Csrf::validate()) { return (new Response())->status(403)->body('CSRF'); }
    $id = (int)$req->post('id',0) ?: null;
    (new MailAccountRepository())->saveContact(['account_id'=>$req->post('account_id'),'name'=>$req->post('name'),'email'=>$req->post('email'),'notes'=>$req->post('notes')], $id);
    return (new Response())->redirect('/hq/mailbox?tab=contacts&account='.(int)$req->post('account_id',0));
});

$router->post('/mailbox/contact/delete', function (Request $req) {
    if ($r = mova_mailbox_guard()) { return $r; }
    if (!Csrf::validate()) { return (new Response())->status(403)->body('CSRF'); }
    (new MailAccountRepository())->deleteContact((int)$req->post('id',0));
    return (new Response())->redirect('/hq/mailbox?tab=contacts&account='.(int)$req->post('account_id',0));
});

$router->post('/mailbox/filter/save', function (Request $req) {
    if ($r = mova_mailbox_guard()) { return $r; }
    if (!Csrf::validate()) { return (new Response())->status(403)->body('CSRF'); }
    $id = (int)$req->post('id',0) ?: null;
    (new MailAccountRepository())->saveFilter([
        'account_id'=>$req->post('account_id'),'name'=>$req->post('name'),'match_field'=>$req->post('match_field'),
        'match_op'=>$req->post('match_op'),'match_value'=>$req->post('match_value'),'action'=>$req->post('action'),
        'action_value'=>$req->post('action_value'),'is_active'=>$req->post('is_active','1'),
    ], $id);
    return (new Response())->redirect('/hq/mailbox?tab=filters&account='.(int)$req->post('account_id',0));
});

$router->post('/mailbox/filter/delete', function (Request $req) {
    if ($r = mova_mailbox_guard()) { return $r; }
    if (!Csrf::validate()) { return (new Response())->status(403)->body('CSRF'); }
    (new MailAccountRepository())->deleteFilter((int)$req->post('id',0));
    return (new Response())->redirect('/hq/mailbox?tab=filters&account='.(int)$req->post('account_id',0));
});
