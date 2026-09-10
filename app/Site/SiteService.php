<?php
/**
 * Mova — multi-site (Phase 3)
 */

namespace Mova\Site;

use Mova\Core\Database;

class SiteService
{
    private static ?array $current = null;

    public function all(): array
    {
        return Database::fetchAll("SELECT * FROM sites ORDER BY is_primary DESC, name ASC");
    }

    public function find(int $id): ?array
    {
        return Database::fetch("SELECT * FROM sites WHERE id = :id", ['id' => $id]);
    }

    public function findBySlug(string $slug): ?array
    {
        return Database::fetch("SELECT * FROM sites WHERE slug = :s", ['s' => $slug]);
    }

    public function primary(): ?array
    {
        $row = Database::fetch("SELECT * FROM sites WHERE is_primary = 1 LIMIT 1");
        if ($row) {
            return $row;
        }
        return Database::fetch("SELECT * FROM sites ORDER BY id ASC LIMIT 1");
    }

    public function resolveFromHost(?string $host = null): array
    {
        $host = $host ?? ($_SERVER['HTTP_HOST'] ?? '');
        $host = strtolower(preg_replace('/:\d+$/', '', $host));

        if ($host !== '') {
            $row = Database::fetch(
                "SELECT * FROM sites WHERE status = 'active' AND domain != '' AND (domain = :h OR domain = :h2) LIMIT 1",
                ['h' => $host, 'h2' => 'www.' . $host]
            );
            if ($row) {
                self::$current = $row;
                return $row;
            }
        }

        $primary = $this->primary();
        self::$current = $primary;
        return $primary ?: [
            'id' => 0,
            'name' => 'Mova',
            'slug' => 'primary',
            'theme' => 'default',
            'is_primary' => 1,
        ];
    }

    public static function current(): ?array
    {
        return self::$current;
    }

    public function create(array $data): int
    {
        $now = date('c');
        $slug = $this->slugify($data['slug'] ?? $data['name'] ?? 'site');
        $base = $slug;
        $i = 1;
        while ($this->findBySlug($slug)) {
            $slug = $base . '-' . $i++;
        }

        return Database::insert('sites', [
            'name'       => trim((string) ($data['name'] ?? 'Site')),
            'slug'       => $slug,
            'domain'     => trim((string) ($data['domain'] ?? '')),
            'theme'      => trim((string) ($data['theme'] ?? 'default')) ?: 'default',
            'status'     => 'active',
            'is_primary' => 0,
            'settings'   => json_encode($data['settings'] ?? []),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $site = $this->find($id);
        if (!$site) {
            return false;
        }
        return Database::update('sites', [
            'name'       => trim((string) ($data['name'] ?? $site['name'])),
            'domain'     => trim((string) ($data['domain'] ?? $site['domain'])),
            'theme'      => trim((string) ($data['theme'] ?? $site['theme'])) ?: 'default',
            'status'     => ($data['status'] ?? $site['status']) === 'active' ? 'active' : 'inactive',
            'updated_at' => date('c'),
        ], 'id = :id', ['id' => $id]) >= 0;
    }

    public function setPrimary(int $id): void
    {
        Database::query("UPDATE sites SET is_primary = 0");
        Database::update('sites', ['is_primary' => 1, 'updated_at' => date('c')], 'id = :id', ['id' => $id]);
    }

    public function delete(int $id): bool
    {
        $site = $this->find($id);
        if (!$site || (int) $site['is_primary']) {
            return false;
        }
        return Database::delete('sites', 'id = :id', ['id' => $id]) > 0;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-') ?: 'site';
    }
}
