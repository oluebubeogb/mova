<?php

declare(strict_types=1);
use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Mail\MailService;

/** @var \Mova\Core\Router $router */

$router->get('/mail', function () {
    requireAuth();
    if (!Auth::can('manage_mail') && !Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $mail = new MailService();
    $subscribers = $mail->allSubscribers();
    return renderHq('mail/index', array_merge(compact('subscribers'), ['title' => 'Mail']));
});

$router->post('/mail/subscriber', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $mail = new MailService();
    try {
        $mail->addSubscriber((string) $req->post('email', ''), trim((string) $req->post('name', '')) ?: null);
        Audit::log('subscriber.created', 'subscriber', null, ['email' => $req->post('email')]);
        return (new Response())->redirect('/hq/mail');
    } catch (\Throwable $e) {
        $subscribers = $mail->allSubscribers();
        return renderHq('mail/index', ['subscribers' => $subscribers, 'error' => $e->getMessage(), 'title' => 'Mail']);
    }
});

$router->post('/mail/subscriber/delete', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    (new MailService())->deleteSubscriber((int) $req->post('id', 0));
    Audit::log('subscriber.deleted', 'subscriber', (int) $req->post('id', 0));
    return (new Response())->redirect('/hq/mail');
});

$router->post('/mail/send', function (Request $req) {
    requireAuth();
    if (!Auth::can('manage_mail') && !Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $mail = new MailService();
    $subscribers = $mail->allSubscribers();
    try {
        $result = $mail->sendCampaign(
            (string) $req->post('subject', ''),
            (string) $req->post('body', '')
        );
        Audit::log('mail.campaign_sent', null, null, $result);
        $message = "Sent {$result['sent']}, failed {$result['failed']}.";
        if ($result['errors']) {
            $message .= ' ' . implode('; ', array_slice($result['errors'], 0, 3));
        }
        return renderHq('mail/index', array_merge(compact('subscribers', 'message'), ['title' => 'Mail']));
    } catch (\Throwable $e) {
        return renderHq('mail/index', ['subscribers' => $subscribers, 'error' => $e->getMessage(), 'title' => 'Mail']);
    }
});
