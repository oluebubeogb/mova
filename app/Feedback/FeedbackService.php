<?php
/**
 * Mova CMS — Feedback forms + inbox entries
 */

declare(strict_types=1);

namespace Mova\Feedback;

use Mova\Core\Database;

class FeedbackService
{
    public const FIELD_TYPES = [
        'text', 'email', 'phone', 'intl_phone', 'country', 'textarea', 'select', 'radio', 'checkbox', 'date', 'number', 'hidden',
    ];

    public function find(int $id): ?array
    {
        $row = Database::fetch('SELECT * FROM feedback_forms WHERE id = :id', ['id' => $id]);
        return $row ? $this->hydrateForm($row) : null;
    }

    public function findBySlug(string $slug, bool $publishedOnly = true): ?array
    {
        $sql = 'SELECT * FROM feedback_forms WHERE slug = :slug';
        if ($publishedOnly) {
            $sql .= " AND status = 'published'";
        }
        $row = Database::fetch($sql, ['slug' => $slug]);
        return $row ? $this->hydrateForm($row) : null;
    }

    public function all(int $limit = 100): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM feedback_forms ORDER BY updated_at DESC LIMIT :limit',
            ['limit' => $limit]
        );
        return array_map(fn($r) => $this->hydrateForm($r), $rows);
    }

    public function create(array $data): int
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('Title is required.');
        }
        $slug = $this->uniqueSlug($this->slugify((string) ($data['slug'] ?? $title)));
        $now = date('c');
        $fields = $this->normalizeFields($data['fields'] ?? []);
        return (int) Database::insert('feedback_forms', [
            'title' => $title,
            'slug' => $slug,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'status' => in_array(($data['status'] ?? 'published'), ['published', 'draft'], true)
                ? ($data['status'] ?? 'published') : 'published',
            'success_message' => trim((string) ($data['success_message'] ?? 'Thank you — we received your message.'))
                ?: 'Thank you — we received your message.',
            'fields_json' => json_encode($fields),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $row = $this->find($id);
        if (!$row) {
            return false;
        }
        $title = array_key_exists('title', $data) ? trim((string) $data['title']) : $row['title'];
        if ($title === '') {
            throw new \InvalidArgumentException('Title is required.');
        }
        $slug = $row['slug'];
        if (array_key_exists('slug', $data)) {
            $ns = $this->slugify((string) $data['slug']);
            if ($ns !== '' && $ns !== $row['slug']) {
                $slug = $this->uniqueSlug($ns, $id);
            }
        }
        $update = [
            'title' => $title,
            'slug' => $slug,
            'updated_at' => date('c'),
        ];
        if (array_key_exists('description', $data)) {
            $update['description'] = trim((string) $data['description']) ?: null;
        }
        if (array_key_exists('status', $data) && in_array($data['status'], ['published', 'draft'], true)) {
            $update['status'] = $data['status'];
        }
        if (array_key_exists('success_message', $data)) {
            $update['success_message'] = trim((string) $data['success_message'])
                ?: 'Thank you — we received your message.';
        }
        if (array_key_exists('fields', $data)) {
            $update['fields_json'] = json_encode($this->normalizeFields($data['fields']));
        }
        Database::update('feedback_forms', $update, 'id = :id', ['id' => $id]);
        return true;
    }

    public function delete(int $id): bool
    {
        Database::delete('feedback_entries', 'form_id = :id', ['id' => $id]);
        return Database::delete('feedback_forms', 'id = :id', ['id' => $id]) > 0;
    }

    public function submit(int $formId, array $values, array $meta = []): int
    {
        $form = $this->find($formId);
        if (!$form || ($form['status'] ?? '') !== 'published') {
            throw new \InvalidArgumentException('Form not available.');
        }
        $clean = [];
        foreach ($form['fields'] as $field) {
            $key = $field['key'];
            $type = $field['type'];
            $raw = $values[$key] ?? null;
            if ($type === 'checkbox') {
                if (is_array($raw)) {
                    $clean[$key] = array_values(array_map('strval', $raw));
                } elseif ($raw !== null && $raw !== '') {
                    $clean[$key] = [(string) $raw];
                } else {
                    $clean[$key] = [];
                }
                if (!empty($field['required']) && $clean[$key] === []) {
                    throw new \InvalidArgumentException(($field['label'] ?: $key) . ' is required.');
                }
                continue;
            }
            $val = is_array($raw) ? implode(', ', $raw) : trim((string) ($raw ?? ''));
            if (!empty($field['required']) && $val === '') {
                throw new \InvalidArgumentException(($field['label'] ?: $key) . ' is required.');
            }
            if ($type === 'email' && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Please enter a valid email.');
            }
            $clean[$key] = $val;
        }
        // Honeypot
        if (!empty($values['_mova_hp'])) {
            throw new \InvalidArgumentException('Rejected.');
        }
        return (int) Database::insert('feedback_entries', [
            'form_id' => $formId,
            'payload' => json_encode($clean),
            'status' => 'new',
            'page_url' => isset($meta['page_url']) ? substr((string) $meta['page_url'], 0, 500) : null,
            'user_agent' => isset($meta['user_agent']) ? substr((string) $meta['user_agent'], 0, 255) : null,
            'ip_hash' => isset($meta['ip']) ? hash('sha256', (string) $meta['ip'] . date('Y-m-d')) : null,
            'created_at' => date('c'),
        ]);
    }

    public function entries(?int $formId = null, int $limit = 100, int $offset = 0): array
    {
        if ($formId) {
            $rows = Database::fetchAll(
                'SELECT e.*, f.title AS form_title, f.slug AS form_slug
                 FROM feedback_entries e
                 JOIN feedback_forms f ON f.id = e.form_id
                 WHERE e.form_id = :fid
                 ORDER BY e.created_at DESC LIMIT :limit OFFSET :offset',
                ['fid' => $formId, 'limit' => $limit, 'offset' => $offset]
            );
        } else {
            $rows = Database::fetchAll(
                'SELECT e.*, f.title AS form_title, f.slug AS form_slug
                 FROM feedback_entries e
                 JOIN feedback_forms f ON f.id = e.form_id
                 ORDER BY e.created_at DESC LIMIT :limit OFFSET :offset',
                ['limit' => $limit, 'offset' => $offset]
            );
        }
        return array_map(fn($r) => $this->hydrateEntry($r), $rows);
    }

    public function countNew(?int $formId = null): int
    {
        if ($formId) {
            $row = Database::fetch(
                "SELECT COUNT(*) AS c FROM feedback_entries WHERE status = 'new' AND form_id = :f",
                ['f' => $formId]
            );
        } else {
            $row = Database::fetch("SELECT COUNT(*) AS c FROM feedback_entries WHERE status = 'new'");
        }
        return (int) ($row['c'] ?? 0);
    }

    public function findEntry(int $id): ?array
    {
        $row = Database::fetch(
            'SELECT e.*, f.title AS form_title, f.slug AS form_slug, f.fields_json
             FROM feedback_entries e
             JOIN feedback_forms f ON f.id = e.form_id
             WHERE e.id = :id',
            ['id' => $id]
        );
        return $row ? $this->hydrateEntry($row) : null;
    }

    public function markRead(int $id): void
    {
        Database::update('feedback_entries', ['status' => 'read'], 'id = :id', ['id' => $id]);
    }

    public function markReadMany(array $ids): int
    {
        $n = 0;
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $this->markRead($id);
                $n++;
            }
        }
        return $n;
    }

    public function deleteEntries(array $ids): int
    {
        $n = 0;
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $this->deleteEntry($id);
                $n++;
            }
        }
        return $n;
    }

    /** @return array{new:int,read:int,total:int} */
    public function countByStatus(?int $formId = null): array
    {
        $params = [];
        $where = '1=1';
        if ($formId) {
            $where .= ' AND form_id = :f';
            $params['f'] = $formId;
        }
        $new = (int) (Database::fetch(
            "SELECT COUNT(*) AS c FROM feedback_entries WHERE {$where} AND status = 'new'",
            $params
        )['c'] ?? 0);
        $read = (int) (Database::fetch(
            "SELECT COUNT(*) AS c FROM feedback_entries WHERE {$where} AND status = 'read'",
            $params
        )['c'] ?? 0);
        return ['new' => $new, 'read' => $read, 'total' => $new + $read];
    }

    public function countEntries(?int $formId = null): int
    {
        if ($formId) {
            return (int) (Database::fetch(
                'SELECT COUNT(*) AS c FROM feedback_entries WHERE form_id = :f',
                ['f' => $formId]
            )['c'] ?? 0);
        }
        return (int) (Database::fetch('SELECT COUNT(*) AS c FROM feedback_entries')['c'] ?? 0);
    }

    public function deleteEntry(int $id): void
    {
        Database::delete('feedback_entries', 'id = :id', ['id' => $id]);
    }

    public function mountHtml(string $slug): string
    {
        return '<div class="mova-feedback" data-form="' . htmlspecialchars($slug, ENT_QUOTES) . '"></div>';
    }

    public static function ensureDefaultContactForm(): void
    {
        try {
            $row = Database::fetch("SELECT id FROM feedback_forms WHERE slug = 'contact' LIMIT 1");
            if ($row) {
                return;
            }
            $svc = new self();
            $svc->create([
                'title' => 'Contact',
                'slug' => 'contact',
                'description' => 'Default contact form. Edit fields anytime.',
                'status' => 'published',
                'success_message' => 'Thank you — we received your message and will get back to you soon.',
                'fields' => [
                    ['type' => 'text', 'label' => 'Name', 'key' => 'name', 'required' => true],
                    ['type' => 'email', 'label' => 'Email', 'key' => 'email', 'required' => true],
                    ['type' => 'phone', 'label' => 'Phone', 'key' => 'phone', 'required' => false],
                    ['type' => 'textarea', 'label' => 'Message', 'key' => 'message', 'required' => true],
                ],
            ]);
        } catch (\Throwable $e) {
            // schema may not be migrated yet
        }
    }

    public static function registerShortcodes(): void
    {
        static $done = false;
        if ($done || !class_exists(\Mova\Plugin\PluginManager::class)) {
            return;
        }
        $done = true;
        \Mova\Plugin\PluginManager::addFilter('content.render.body', function (string $body) {
            if ($body === '' || (stripos($body, '[feedback') === false && strpos($body, 'mova-feedback') === false)) {
                return $body;
            }
            $svc = new self();
            $body = preg_replace_callback('/\[feedback\s+([^\]]*)\]/i', function ($m) use ($svc) {
                $slug = 'contact';
                if (preg_match('/\b(?:form|slug)=["\']([^"\']+)["\']/i', $m[1], $mm)) {
                    $slug = $mm[1];
                }
                return $svc->mountHtml($slug);
            }, $body);
            static $assets = false;
            if (!$assets && is_string($body) && strpos($body, 'mova-feedback') !== false) {
                $assets = true;
                $body .= "\n<link rel=\"stylesheet\" href=\"/assets/css/mova-feedback.css?v=6\">"
                    . "\n<script src=\"/assets/js/mova-feedback-countries.js?v=1\"></script>"
                    . "\n<script src=\"/assets/js/mova-feedback.js?v=6\" defer></script>";
            }
            return $body;
        }, 26);
    }

    private function hydrateForm(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $fields = [];
        if (!empty($row['fields_json'])) {
            $decoded = is_string($row['fields_json'])
                ? json_decode($row['fields_json'], true)
                : $row['fields_json'];
            $fields = is_array($decoded) ? $decoded : [];
        }
        $row['fields'] = $this->normalizeFields($fields);
        unset($row['fields_json']);
        return $row;
    }

    private function hydrateEntry(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['form_id'] = (int) $row['form_id'];
        $payload = [];
        if (!empty($row['payload'])) {
            $decoded = is_string($row['payload']) ? json_decode($row['payload'], true) : $row['payload'];
            $payload = is_array($decoded) ? $decoded : [];
        }
        $row['payload'] = $payload;
        return $row;
    }

    public function normalizeFields($fields): array
    {
        if (is_string($fields)) {
            $fields = json_decode($fields, true) ?: [];
        }
        if (!is_array($fields)) {
            return [];
        }
        $out = [];
        $i = 0;
        foreach ($fields as $f) {
            if (!is_array($f)) {
                continue;
            }
            $type = strtolower((string) ($f['type'] ?? 'text'));
            if (!in_array($type, self::FIELD_TYPES, true)) {
                $type = 'text';
            }
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($f['key'] ?? '')));
            if ($key === '') {
                $key = 'field_' . $i;
            }
            $opts = $f['options'] ?? [];
            if (is_string($opts)) {
                $opts = array_values(array_filter(array_map('trim', explode(',', $opts))));
            }
            if (!is_array($opts)) {
                $opts = [];
            }
            $out[] = [
                'type' => $type,
                'label' => trim((string) ($f['label'] ?? $key)),
                'key' => $key,
                'required' => !empty($f['required']),
                'placeholder' => (string) ($f['placeholder'] ?? ''),
                'help' => (string) ($f['help'] ?? ''),
                'options' => array_values(array_map('strval', $opts)),
            ];
            $i++;
        }
        return $out;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?: 'form';
        return trim($text, '-') ?: 'form';
    }

    private function uniqueSlug(string $slug, ?int $exclude = null): string
    {
        $base = $slug;
        $n = 1;
        while (true) {
            $params = ['s' => $slug];
            $sql = 'SELECT id FROM feedback_forms WHERE slug = :s';
            if ($exclude) {
                $sql .= ' AND id != :id';
                $params['id'] = $exclude;
            }
            $row = Database::fetch($sql, $params);
            if (!$row) {
                return $slug;
            }
            $slug = $base . '-' . $n++;
        }
    }
}
