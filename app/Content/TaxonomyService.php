<?php
/**
 * Mova CMS - Categories & Tags
 */

namespace Mova\Content;

use Mova\Core\Database;

class TaxonomyService
{
    // ---------- Categories ----------

    public function allCategories(): array
    {
        return Database::fetchAll(
            "SELECT * FROM categories ORDER BY sort_order ASC, name ASC"
        );
    }

    public function findCategory(int $id): ?array
    {
        return Database::fetch("SELECT * FROM categories WHERE id = :id", ['id' => $id]);
    }

    public function findCategoryBySlug(string $slug): ?array
    {
        return Database::fetch("SELECT * FROM categories WHERE slug = :s", ['s' => $slug]);
    }

    public function createCategory(array $data): int
    {
        $name = trim($data['name'] ?? '');
        $slug = trim($data['slug'] ?? '') ?: $this->slugify($name);
        $slug = $this->uniqueCategorySlug($slug);

        return Database::insert('categories', [
            'name'        => $name,
            'slug'        => $slug,
            'description' => trim($data['description'] ?? ''),
            'parent_id'   => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'created_at'  => date('c'),
        ]);
    }

    public function updateCategory(int $id, array $data): bool
    {
        $name = trim($data['name'] ?? '');
        $slug = trim($data['slug'] ?? '') ?: $this->slugify($name);
        $slug = $this->uniqueCategorySlug($slug, $id);

        return Database::update('categories', [
            'name'        => $name,
            'slug'        => $slug,
            'description' => trim($data['description'] ?? ''),
            'parent_id'   => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
        ], 'id = :id', ['id' => $id]) > 0;
    }

    public function deleteCategory(int $id): bool
    {
        Database::delete('content_categories', 'category_id = :id', ['id' => $id]);
        return Database::delete('categories', 'id = :id', ['id' => $id]) > 0;
    }

    public function getContentCategoryIds(int $contentId): array
    {
        $rows = Database::fetchAll(
            "SELECT category_id FROM content_categories WHERE content_id = :id",
            ['id' => $contentId]
        );
        return array_map(fn($r) => (int) $r['category_id'], $rows);
    }

    public function syncContentCategories(int $contentId, array $categoryIds): void
    {
        Database::delete('content_categories', 'content_id = :id', ['id' => $contentId]);
        foreach ($categoryIds as $cid) {
            $cid = (int) $cid;
            if ($cid > 0) {
                Database::insert('content_categories', [
                    'content_id'  => $contentId,
                    'category_id' => $cid,
                ]);
            }
        }
    }

    // ---------- Tags ----------

    public function allTags(): array
    {
        return Database::fetchAll("SELECT * FROM tags ORDER BY name ASC");
    }

    public function findTag(int $id): ?array
    {
        return Database::fetch("SELECT * FROM tags WHERE id = :id", ['id' => $id]);
    }

    public function findTagBySlug(string $slug): ?array
    {
        return Database::fetch("SELECT * FROM tags WHERE slug = :s", ['s' => $slug]);
    }

    public function createTag(string $name, ?string $slug = null): int
    {
        $name = trim($name);
        $slug = trim($slug ?? '') ?: $this->slugify($name);
        $slug = $this->uniqueTagSlug($slug);

        return Database::insert('tags', [
            'name'       => $name,
            'slug'       => $slug,
            'created_at' => date('c'),
        ]);
    }

    public function updateTag(int $id, string $name, ?string $slug = null): bool
    {
        $name = trim($name);
        $slug = trim($slug ?? '') ?: $this->slugify($name);
        $slug = $this->uniqueTagSlug($slug, $id);

        return Database::update('tags', [
            'name' => $name,
            'slug' => $slug,
        ], 'id = :id', ['id' => $id]) > 0;
    }

    public function deleteTag(int $id): bool
    {
        Database::delete('content_tags', 'tag_id = :id', ['id' => $id]);
        return Database::delete('tags', 'id = :id', ['id' => $id]) > 0;
    }

    public function getContentTagIds(int $contentId): array
    {
        $rows = Database::fetchAll(
            "SELECT tag_id FROM content_tags WHERE content_id = :id",
            ['id' => $contentId]
        );
        return array_map(fn($r) => (int) $r['tag_id'], $rows);
    }

    public function getContentTags(int $contentId): array
    {
        return Database::fetchAll(
            "SELECT t.* FROM tags t
             JOIN content_tags ct ON ct.tag_id = t.id
             WHERE ct.content_id = :id
             ORDER BY t.name",
            ['id' => $contentId]
        );
    }

    /**
     * Sync tags from a list of names (creates missing tags).
     * @param string[] $names
     */
    public function syncContentTagsByNames(int $contentId, array $names): void
    {
        $tagIds = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $existing = Database::fetch(
                "SELECT id FROM tags WHERE LOWER(name) = LOWER(:n)",
                ['n' => $name]
            );
            if ($existing) {
                $tagIds[] = (int) $existing['id'];
            } else {
                $tagIds[] = $this->createTag($name);
            }
        }
        $this->syncContentTags($contentId, $tagIds);
    }

    public function syncContentTags(int $contentId, array $tagIds): void
    {
        Database::delete('content_tags', 'content_id = :id', ['id' => $contentId]);
        foreach ($tagIds as $tid) {
            $tid = (int) $tid;
            if ($tid > 0) {
                Database::insert('content_tags', [
                    'content_id' => $contentId,
                    'tag_id'     => $tid,
                ]);
            }
        }
    }

    // ---------- Helpers ----------

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-') ?: 'item';
    }

    private function uniqueCategorySlug(string $slug, ?int $excludeId = null): string
    {
        $base = $slug;
        $i = 1;
        while ($this->categorySlugExists($slug, $excludeId)) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    private function categorySlugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM categories WHERE slug = :s";
        $params = ['s' => $slug];
        if ($excludeId) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }
        return (bool) Database::fetch($sql, $params);
    }

    private function uniqueTagSlug(string $slug, ?int $excludeId = null): string
    {
        $base = $slug;
        $i = 1;
        while ($this->tagSlugExists($slug, $excludeId)) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    private function tagSlugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM tags WHERE slug = :s";
        $params = ['s' => $slug];
        if ($excludeId) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }
        return (bool) Database::fetch($sql, $params);
    }
}
