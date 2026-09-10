<?php
/**
 * Mova — Content types & custom fields (V2 Phase 1)
 */

namespace Mova\Content;

use Mova\Core\Database;

class ContentTypeService
{
    public const FIELD_TYPES = [
        'text'      => 'Text',
        'textarea'  => 'Textarea',
        'richtext'  => 'Rich Text',
        'number'    => 'Number',
        'date'      => 'Date',
        'datetime'  => 'Date/Time',
        'boolean'   => 'Boolean',
        'select'    => 'Select',
        'multiselect' => 'Multi-select',
        'image'     => 'Image',
        'gallery'   => 'Gallery',
        'url'       => 'URL',
        'email'     => 'Email',
        'relation'  => 'Relation',
    ];

    public function allTypes(): array
    {
        return Database::fetchAll(
            "SELECT * FROM content_types ORDER BY sort_order ASC, name ASC"
        );
    }

    public function typesMap(): array
    {
        $map = [];
        foreach ($this->allTypes() as $t) {
            $map[$t['slug']] = $t['name'];
        }
        return $map;
    }

    public function findType(int $id): ?array
    {
        return Database::fetch("SELECT * FROM content_types WHERE id = :id", ['id' => $id]);
    }

    public function findTypeBySlug(string $slug): ?array
    {
        return Database::fetch("SELECT * FROM content_types WHERE slug = :s", ['s' => $slug]);
    }

    public function createType(array $data): int
    {
        $now = date('c');
        $slug = $this->slugify($data['slug'] ?? $data['name'] ?? 'type');
        $base = $slug;
        $i = 1;
        while ($this->findTypeBySlug($slug)) {
            $slug = $base . '-' . $i++;
        }

        return Database::insert('content_types', [
            'slug'        => $slug,
            'name'        => trim((string) ($data['name'] ?? 'Untitled')),
            'description' => trim((string) ($data['description'] ?? '')),
            'icon'        => trim((string) ($data['icon'] ?? 'fa-file')) ?: 'fa-file',
            'is_system'   => 0,
            'is_public'   => !empty($data['is_public']) ? 1 : 0,
            'schema_type' => trim((string) ($data['schema_type'] ?? 'WebPage')) ?: 'WebPage',
            'sort_order'  => (int) ($data['sort_order'] ?? 99),
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    public function updateType(int $id, array $data): bool
    {
        $type = $this->findType($id);
        if (!$type) {
            return false;
        }

        $update = [
            'name'        => trim((string) ($data['name'] ?? $type['name'])),
            'description' => trim((string) ($data['description'] ?? $type['description'])),
            'icon'        => trim((string) ($data['icon'] ?? $type['icon'])) ?: 'fa-file',
            'is_public'   => !empty($data['is_public']) ? 1 : 0,
            'schema_type' => trim((string) ($data['schema_type'] ?? $type['schema_type'])) ?: 'WebPage',
            'sort_order'  => (int) ($data['sort_order'] ?? $type['sort_order']),
            'updated_at'  => date('c'),
        ];

        // Non-system types can change slug
        if (!(int) $type['is_system'] && !empty($data['slug'])) {
            $slug = $this->slugify($data['slug']);
            $existing = $this->findTypeBySlug($slug);
            if (!$existing || (int) $existing['id'] === $id) {
                $update['slug'] = $slug;
            }
        }

        return Database::update('content_types', $update, 'id = :id', ['id' => $id]) >= 0;
    }

    public function deleteType(int $id): bool
    {
        $type = $this->findType($id);
        if (!$type || (int) $type['is_system']) {
            return false;
        }
        return Database::delete('content_types', 'id = :id', ['id' => $id]) > 0;
    }

    public function fieldsForType(int $typeId): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM content_fields WHERE type_id = :tid ORDER BY sort_order ASC, id ASC",
            ['tid' => $typeId]
        );
        foreach ($rows as &$row) {
            $row['options'] = $row['options'] ? (json_decode($row['options'], true) ?: []) : [];
        }
        return $rows;
    }

    public function fieldsForTypeSlug(string $slug): array
    {
        $type = $this->findTypeBySlug($slug);
        return $type ? $this->fieldsForType((int) $type['id']) : [];
    }

    public function createField(int $typeId, array $data): int
    {
        $slug = $this->slugify($data['slug'] ?? $data['name'] ?? 'field');
        $options = $data['options'] ?? [];
        if (is_string($options)) {
            // newline or comma separated for select options
            $options = array_values(array_filter(array_map('trim', preg_split('/[\n,]+/', $options))));
        }

        return Database::insert('content_fields', [
            'type_id'     => $typeId,
            'name'        => trim((string) ($data['name'] ?? 'Field')),
            'slug'        => $slug,
            'field_type'  => $data['field_type'] ?? 'text',
            'options'     => json_encode($options),
            'is_required' => !empty($data['is_required']) ? 1 : 0,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'created_at'  => date('c'),
        ]);
    }

    public function updateField(int $id, array $data): bool
    {
        $field = Database::fetch("SELECT * FROM content_fields WHERE id = :id", ['id' => $id]);
        if (!$field) {
            return false;
        }
        $options = $data['options'] ?? json_decode($field['options'] ?? '[]', true);
        if (is_string($options)) {
            $options = array_values(array_filter(array_map('trim', preg_split('/[\n,]+/', $options))));
        }

        return Database::update('content_fields', [
            'name'        => trim((string) ($data['name'] ?? $field['name'])),
            'field_type'  => $data['field_type'] ?? $field['field_type'],
            'options'     => json_encode($options ?: []),
            'is_required' => !empty($data['is_required']) ? 1 : 0,
            'sort_order'  => (int) ($data['sort_order'] ?? $field['sort_order']),
        ], 'id = :id', ['id' => $id]) >= 0;
    }

    public function deleteField(int $id): bool
    {
        return Database::delete('content_fields', 'id = :id', ['id' => $id]) > 0;
    }

    public function saveFieldValues(int $contentId, array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            Database::query(
                "INSERT INTO content_meta (content_id, meta_key, meta_value)
                 VALUES (:cid, :key, :val)
                 ON CONFLICT(content_id, meta_key) DO UPDATE SET meta_value = :val2",
                [
                    'cid'  => $contentId,
                    'key'  => 'field_' . $key,
                    'val'  => (string) $value,
                    'val2' => (string) $value,
                ]
            );
        }
    }

    public function getFieldValues(int $contentId, array $fields = []): array
    {
        $meta = Database::fetchAll(
            "SELECT meta_key, meta_value FROM content_meta WHERE content_id = :id AND meta_key LIKE 'field_%'",
            ['id' => $contentId]
        );
        $out = [];
        foreach ($meta as $row) {
            $slug = substr($row['meta_key'], 6); // strip field_
            $out[$slug] = $row['meta_value'];
        }
        return $out;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-') ?: 'item';
    }
}
