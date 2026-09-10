<?php
/**
 * Mova — Webhooks (V2 Phase 1)
 */

namespace Mova\Webhook;

use Mova\Core\Database;

class WebhookService
{
    public const EVENTS = [
        'content.published' => 'Content published',
        'content.updated'   => 'Content updated',
        'content.deleted'   => 'Content deleted',
        'media.uploaded'    => 'Media uploaded',
        'user.created'      => 'User created',
        'subscriber.created'=> 'Subscriber created',
    ];

    public function all(): array
    {
        $rows = Database::fetchAll("SELECT * FROM webhooks ORDER BY created_at DESC");
        foreach ($rows as &$row) {
            $row['events'] = json_decode($row['events'] ?? '[]', true) ?: [];
        }
        return $rows;
    }

    public function find(int $id): ?array
    {
        $row = Database::fetch("SELECT * FROM webhooks WHERE id = :id", ['id' => $id]);
        if ($row) {
            $row['events'] = json_decode($row['events'] ?? '[]', true) ?: [];
        }
        return $row;
    }

    public function create(array $data): int
    {
        $now = date('c');
        $events = $data['events'] ?? [];
        if (!is_array($events)) {
            $events = [];
        }

        return Database::insert('webhooks', [
            'name'       => trim((string) ($data['name'] ?? 'Webhook')),
            'url'        => trim((string) ($data['url'] ?? '')),
            'events'     => json_encode(array_values($events)),
            'secret'     => $data['secret'] ?? bin2hex(random_bytes(16)),
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $wh = $this->find($id);
        if (!$wh) {
            return false;
        }
        $events = $data['events'] ?? $wh['events'];
        if (!is_array($events)) {
            $events = [];
        }

        return Database::update('webhooks', [
            'name'       => trim((string) ($data['name'] ?? $wh['name'])),
            'url'        => trim((string) ($data['url'] ?? $wh['url'])),
            'events'     => json_encode(array_values($events)),
            'status'     => ($data['status'] ?? $wh['status']) === 'active' ? 'active' : 'disabled',
            'updated_at' => date('c'),
        ], 'id = :id', ['id' => $id]) >= 0;
    }

    public function delete(int $id): bool
    {
        return Database::delete('webhooks', 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * Dispatch event to matching active webhooks (non-blocking best-effort).
     */
    public function dispatch(string $event, array $payload): void
    {
        $hooks = Database::fetchAll("SELECT * FROM webhooks WHERE status = 'active'");
        foreach ($hooks as $hook) {
            $events = json_decode($hook['events'] ?? '[]', true) ?: [];
            if (!in_array($event, $events, true) && !in_array('*', $events, true)) {
                continue;
            }
            $this->deliver($hook, $event, $payload);
        }
    }

    private function deliver(array $hook, string $event, array $payload): void
    {
        $body = json_encode([
            'event'     => $event,
            'timestamp' => date('c'),
            'data'      => $payload,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $signature = hash_hmac('sha256', $body, $hook['secret'] ?? '');

        $code = 0;
        $responseBody = '';
        $success = 0;

        try {
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => implode("\r\n", [
                        'Content-Type: application/json',
                        'User-Agent: Mova-Webhook/1.0',
                        'X-Mova-Event: ' . $event,
                        'X-Mova-Signature: sha256=' . $signature,
                    ]),
                    'content' => $body,
                    'timeout' => 5,
                    'ignore_errors' => true,
                ],
            ]);
            $result = @file_get_contents($hook['url'], false, $ctx);
            $responseBody = is_string($result) ? substr($result, 0, 2000) : '';
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $code = (int) $m[1];
            }
            $success = ($code >= 200 && $code < 300) ? 1 : 0;
        } catch (\Throwable $e) {
            $responseBody = $e->getMessage();
        }

        Database::insert('webhook_deliveries', [
            'webhook_id'    => $hook['id'],
            'event'         => $event,
            'payload'       => $body,
            'response_code' => $code,
            'response_body' => $responseBody,
            'success'       => $success,
            'created_at'    => date('c'),
        ]);

        Database::update('webhooks', [
            'last_status'       => $code,
            'last_triggered_at' => date('c'),
            'updated_at'        => date('c'),
        ], 'id = :id', ['id' => $hook['id']]);
    }

    public function recentDeliveries(int $webhookId, int $limit = 20): array
    {
        return Database::fetchAll(
            "SELECT * FROM webhook_deliveries WHERE webhook_id = :id ORDER BY created_at DESC LIMIT :lim",
            ['id' => $webhookId, 'lim' => $limit]
        );
    }
}
