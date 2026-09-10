<?php
/**
 * Mova — modular integrations registry (Phase 3)
 */

namespace Mova\Integration;

use Mova\Core\Database;

class IntegrationManager
{
    public const DRIVERS = [
        'cdn' => [
            'name' => 'CDN',
            'fields' => ['provider', 'base_url', 'api_token'],
            'description' => 'Serve media via CDN (Cloudflare, Bunny, custom).',
        ],
        'smtp' => [
            'name' => 'SMTP / Email API',
            'fields' => ['provider', 'api_key', 'from_email'],
            'description' => 'Override mail delivery (SendGrid, Mailgun, Postmark).',
        ],
        'analytics' => [
            'name' => 'Analytics',
            'fields' => ['provider', 'tracking_id'],
            'description' => 'External analytics (Plausible, GA4, Fathom).',
        ],
        'storage' => [
            'name' => 'Object storage',
            'fields' => ['provider', 'bucket', 'region', 'key', 'secret'],
            'description' => 'Offload uploads (S3-compatible).',
        ],
        'ai' => [
            'name' => 'AI provider',
            'fields' => ['provider', 'api_url', 'api_key', 'model'],
            'description' => 'Same as Settings AI — mirrored for integrations UI.',
        ],
    ];

    public function all(): array
    {
        $rows = [];
        try {
            $rows = Database::fetchAll("SELECT * FROM integrations");
        } catch (\Throwable $e) {
        }
        $byDriver = [];
        foreach ($rows as $r) {
            $r['config'] = json_decode($r['config'] ?? '{}', true) ?: [];
            $byDriver[$r['driver']] = $r;
        }

        $out = [];
        foreach (self::DRIVERS as $driver => $meta) {
            $row = $byDriver[$driver] ?? null;
            $out[] = [
                'driver' => $driver,
                'name' => $meta['name'],
                'description' => $meta['description'],
                'fields' => $meta['fields'],
                'status' => $row['status'] ?? 'disabled',
                'config' => $row['config'] ?? [],
            ];
        }
        return $out;
    }

    public function save(string $driver, array $config, string $status = 'enabled'): void
    {
        if (!isset(self::DRIVERS[$driver])) {
            return;
        }
        $existing = Database::fetch("SELECT id FROM integrations WHERE driver = :d", ['d' => $driver]);
        $payload = [
            'driver' => $driver,
            'name' => self::DRIVERS[$driver]['name'],
            'status' => $status === 'enabled' ? 'enabled' : 'disabled',
            'config' => json_encode($config),
            'updated_at' => date('c'),
        ];
        if ($existing) {
            Database::update('integrations', $payload, 'id = :id', ['id' => $existing['id']]);
        } else {
            Database::insert('integrations', $payload);
        }
    }

    public function getConfig(string $driver): array
    {
        $row = Database::fetch("SELECT * FROM integrations WHERE driver = :d AND status = 'enabled'", ['d' => $driver]);
        if (!$row) {
            return [];
        }
        return json_decode($row['config'] ?? '{}', true) ?: [];
    }
}
