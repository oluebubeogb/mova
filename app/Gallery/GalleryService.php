<?php
/**
 * Mova CMS — Gallery collections of media
 */

declare(strict_types=1);

namespace Mova\Gallery;

use Mova\Core\Database;
use Mova\Media\MediaService;

class GalleryService
{
    private MediaService $media;

    public function __construct(?MediaService $media = null)
    {
        $this->media = $media ?? new MediaService();
    }

    public function find(int $id): ?array
    {
        $row = Database::fetch('SELECT * FROM galleries WHERE id = :id', ['id' => $id]);
        return $row ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug, bool $publishedOnly = true): ?array
    {
        $sql = 'SELECT * FROM galleries WHERE slug = :slug';
        if ($publishedOnly) {
            $sql .= " AND status = 'published'";
        }
        $row = Database::fetch($sql, ['slug' => $slug]);
        return $row ? $this->hydrate($row) : null;
    }

    public function all(bool $publishedOnly = false, int $limit = 100, int $offset = 0): array
    {
        $sql = 'SELECT * FROM galleries';
        if ($publishedOnly) {
            $sql .= " WHERE status = 'published'";
        }
        $sql .= ' ORDER BY updated_at DESC LIMIT :limit OFFSET :offset';
        $rows = Database::fetchAll($sql, ['limit' => $limit, 'offset' => $offset]);
        return array_map(fn($r) => $this->hydrate($r), $rows);
    }

    public function recent(int $limit = 4, bool $publishedOnly = true): array
    {
        return $this->all($publishedOnly, $limit, 0);
    }

    public function count(bool $publishedOnly = false): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM galleries';
        if ($publishedOnly) {
            $sql .= " WHERE status = 'published'";
        }
        $row = Database::fetch($sql);
        return (int) ($row['c'] ?? 0);
    }

    public function create(array $data): int
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('Title is required.');
        }
        $slug = $this->uniqueSlug($this->slugify((string) ($data['slug'] ?? $title)));
        $now = date('c');
        $id = Database::insert('galleries', [
            'title' => $title,
            'slug' => $slug,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'cover_media_id' => !empty($data['cover_media_id']) ? (int) $data['cover_media_id'] : null,
            'status' => in_array($data['status'] ?? 'published', ['published', 'draft'], true)
                ? ($data['status'] ?? 'published')
                : 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (!empty($data['media_ids']) && is_array($data['media_ids'])) {
            $this->setItems($id, array_map('intval', $data['media_ids']), $data['captions'] ?? []);
        }

        return $id;
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
            $newSlug = $this->slugify((string) $data['slug']);
            if ($newSlug !== '' && $newSlug !== $row['slug']) {
                $slug = $this->uniqueSlug($newSlug, $id);
            }
        }

        $status = $row['status'];
        if (array_key_exists('status', $data) && in_array($data['status'], ['published', 'draft'], true)) {
            $status = $data['status'];
        }

        $cover = $row['cover_media_id'];
        if (array_key_exists('cover_media_id', $data)) {
            $cover = $data['cover_media_id'] !== null && $data['cover_media_id'] !== ''
                ? (int) $data['cover_media_id']
                : null;
        }

        Database::update('galleries', [
            'title' => $title,
            'slug' => $slug,
            'description' => array_key_exists('description', $data)
                ? (trim((string) $data['description']) ?: null)
                : $row['description'],
            'cover_media_id' => $cover,
            'status' => $status,
            'updated_at' => date('c'),
        ], 'id = :id', ['id' => $id]);

        if (array_key_exists('media_ids', $data) && is_array($data['media_ids'])) {
            $this->setItems($id, array_map('intval', $data['media_ids']), $data['captions'] ?? []);
        }

        return true;
    }

    public function delete(int $id): bool
    {
        Database::delete('gallery_items', 'gallery_id = :id', ['id' => $id]);
        return Database::delete('galleries', 'id = :id', ['id' => $id]) > 0;
    }

    public function setItems(int $galleryId, array $mediaIds, array $captions = []): void
    {
        Database::delete('gallery_items', 'gallery_id = :id', ['id' => $galleryId]);
        $now = date('c');
        $order = 0;
        foreach ($mediaIds as $mid) {
            $mid = (int) $mid;
            if ($mid <= 0) {
                continue;
            }
            $media = $this->media->find($mid);
            if (!$media) {
                continue;
            }
            $caption = null;
            if (isset($captions[$mid])) {
                $caption = trim((string) $captions[$mid]) ?: null;
            } elseif (isset($captions[(string) $mid])) {
                $caption = trim((string) $captions[(string) $mid]) ?: null;
            }
            try {
                Database::insert('gallery_items', [
                    'gallery_id' => $galleryId,
                    'media_id' => $mid,
                    'caption' => $caption,
                    'sort_order' => $order++,
                    'created_at' => $now,
                ]);
            } catch (\Throwable $e) {
                // skip duplicates
            }
        }
        Database::update('galleries', ['updated_at' => $now], 'id = :id', ['id' => $galleryId]);
    }

    public function items(int $galleryId): array
    {
        $rows = Database::fetchAll(
            'SELECT gi.*, m.filename, m.original_name, m.mime_type, m.path, m.variants,
                    m.width, m.height, m.alt_text, m.created_at AS media_created_at
             FROM gallery_items gi
             INNER JOIN media m ON m.id = gi.media_id
             WHERE gi.gallery_id = :gid
             ORDER BY gi.sort_order ASC, gi.id ASC',
            ['gid' => $galleryId]
        );
        return array_map(fn($r) => $this->hydrateItem($r), $rows);
    }

    /**
     * Chronological image stream across all media (images only).
     * Soft target $targetCount, completes the last month (may exceed up to $hardMax).
     *
     * @return array{items: array, has_more: bool, next_before: ?string}
     */
    public function streamImages(int $targetCount = 48, int $hardMax = 72, ?string $before = null): array
    {
        $params = [];
        $sql = "SELECT * FROM media WHERE mime_type LIKE 'image/%'";
        if ($before !== null && $before !== '') {
            $sql .= ' AND created_at < :before';
            $params['before'] = $before;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :lim';
        $params['lim'] = $hardMax;

        $rows = Database::fetchAll($sql, $params);
        if (!$rows) {
            return ['items' => [], 'has_more' => false, 'next_before' => null];
        }

        $out = [];
        $lastMonth = null;
        foreach ($rows as $i => $row) {
            $month = substr((string) ($row['created_at'] ?? ''), 0, 7); // YYYY-MM
            if ($i < $targetCount) {
                $out[] = $this->hydrateMedia($row);
                $lastMonth = $month;
                continue;
            }
            // Complete the last month beyond target
            if ($month === $lastMonth && count($out) < $hardMax) {
                $out[] = $this->hydrateMedia($row);
                continue;
            }
            break;
        }

        $nextBefore = null;
        $hasMore = false;
        if (count($out) > 0) {
            $last = $out[count($out) - 1];
            $nextBefore = $last['created_at'] ?? null;
            // More exist if we stopped early or DB returned hardMax and we used all
            if (count($rows) > count($out) || count($rows) >= $hardMax) {
                $check = Database::fetch(
                    "SELECT 1 AS ok FROM media WHERE mime_type LIKE 'image/%' AND created_at < :b LIMIT 1",
                    ['b' => $nextBefore]
                );
                $hasMore = $check !== null;
            }
        }

        return ['items' => $out, 'has_more' => $hasMore, 'next_before' => $nextBefore];
    }

    /**
     * Search galleries + image captions / alt / filenames / dates.
     */
    public function search(string $q, int $limit = 40): array
    {
        $q = trim($q);
        if ($q === '') {
            return ['galleries' => [], 'images' => []];
        }
        $like = '%' . $q . '%';

        $galleries = Database::fetchAll(
            "SELECT * FROM galleries
             WHERE status = 'published'
               AND (title LIKE :q OR description LIKE :q2 OR slug LIKE :q3)
             ORDER BY updated_at DESC LIMIT :lim",
            ['q' => $like, 'q2' => $like, 'q3' => $like, 'lim' => min(20, $limit)]
        );
        $galleries = array_map(fn($r) => $this->hydrate($r), $galleries);

        $images = Database::fetchAll(
            "SELECT m.*, gi.caption, gi.gallery_id, g.title AS gallery_title, g.slug AS gallery_slug
             FROM media m
             LEFT JOIN gallery_items gi ON gi.media_id = m.id
             LEFT JOIN galleries g ON g.id = gi.gallery_id AND g.status = 'published'
             WHERE m.mime_type LIKE 'image/%'
               AND (
                   m.alt_text LIKE :q OR m.original_name LIKE :q2 OR m.filename LIKE :q3
                   OR gi.caption LIKE :q4 OR m.created_at LIKE :q5
               )
             ORDER BY m.created_at DESC
             LIMIT :lim",
            [
                'q' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like,
                'lim' => $limit,
            ]
        );

        $seen = [];
        $imgOut = [];
        foreach ($images as $row) {
            $id = (int) $row['id'];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $item = $this->hydrateMedia($row);
            $item['caption'] = $row['caption'] ?? null;
            $item['gallery_id'] = isset($row['gallery_id']) ? (int) $row['gallery_id'] : null;
            $item['gallery_title'] = $row['gallery_title'] ?? null;
            $item['gallery_slug'] = $row['gallery_slug'] ?? null;
            $imgOut[] = $item;
        }

        return ['galleries' => $galleries, 'images' => $imgOut];
    }

    public function itemCount(int $galleryId): int
    {
        $row = Database::fetch(
            'SELECT COUNT(*) AS c FROM gallery_items WHERE gallery_id = :id',
            ['id' => $galleryId]
        );
        return (int) ($row['c'] ?? 0);
    }

    private function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['cover_media_id'] = isset($row['cover_media_id']) && $row['cover_media_id'] !== null
            ? (int) $row['cover_media_id']
            : null;
        $row['item_count'] = $this->itemCount($row['id']);
        $row['cover_url'] = null;
        $row['cover_thumb'] = null;

        $coverId = $row['cover_media_id'];
        if (!$coverId) {
            $first = Database::fetch(
                'SELECT media_id FROM gallery_items WHERE gallery_id = :id ORDER BY sort_order ASC, id ASC LIMIT 1',
                ['id' => $row['id']]
            );
            $coverId = $first ? (int) $first['media_id'] : null;
        }
        if ($coverId) {
            $m = $this->media->find($coverId);
            if ($m) {
                $row['cover_url'] = $this->media->url($m);
                $row['cover_thumb'] = $this->media->url($m, 480);
            }
        }
        return $row;
    }

    private function hydrateItem(array $row): array
    {
        if (!empty($row['variants']) && is_string($row['variants'])) {
            $row['variants'] = json_decode($row['variants'], true) ?: [];
        }
        $media = [
            'id' => (int) $row['media_id'],
            'filename' => $row['filename'] ?? '',
            'original_name' => $row['original_name'] ?? '',
            'mime_type' => $row['mime_type'] ?? '',
            'path' => $row['path'] ?? '',
            'variants' => $row['variants'] ?? [],
            'width' => $row['width'] ?? null,
            'height' => $row['height'] ?? null,
            'alt_text' => $row['alt_text'] ?? '',
            'created_at' => $row['media_created_at'] ?? $row['created_at'] ?? null,
        ];
        return [
            'id' => (int) $row['id'],
            'gallery_id' => (int) $row['gallery_id'],
            'media_id' => (int) $row['media_id'],
            'caption' => $row['caption'] ?? null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'created_at' => $row['created_at'] ?? null,
            'media' => $media,
            'url' => $this->media->url($media),
            'thumb_url' => $this->media->url($media, 480),
            'alt' => $media['alt_text'] ?: ($media['original_name'] ?? ''),
            'date' => $media['created_at'],
        ];
    }

    private function hydrateMedia(array $row): array
    {
        if (!empty($row['variants']) && is_string($row['variants'])) {
            $row['variants'] = json_decode($row['variants'], true) ?: [];
        }
        $row['id'] = (int) $row['id'];
        $row['url'] = $this->media->url($row);
        $row['thumb_url'] = $this->media->url($row, 480);
        $row['alt'] = $row['alt_text'] ?? ($row['original_name'] ?? '');
        return $row;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text) ?? '';
        $text = preg_replace('/[\s-]+/', '-', $text) ?? '';
        $slug = trim($text, '-');
        if ($slug === '' || $slug === 'gallery') {
            $slug = 'gallery-collection';
        }
        return $slug;
    }

    private function uniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $base = $slug;
        $i = 2;
        while ($this->slugExists($slug, $excludeId)) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM galleries WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        return Database::fetch($sql, $params) !== null;
    }
}
