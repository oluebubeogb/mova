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
        $groupSlug = $this->normalizeGroupSlug($data['group_slug'] ?? null);
        $embedLayout = $this->normalizeEmbedLayout($data['embed_layout'] ?? 'carousel');
        $embedOptions = $this->encodeEmbedStorage($data);
        $id = Database::insert('galleries', [
            'title' => $title,
            'slug' => $slug,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'cover_media_id' => !empty($data['cover_media_id']) ? (int) $data['cover_media_id'] : null,
            'status' => in_array($data['status'] ?? 'published', ['published', 'draft'], true)
                ? ($data['status'] ?? 'published')
                : 'published',
            'group_slug' => $groupSlug,
            'embed_layout' => $embedLayout,
            'embed_options' => $embedOptions,
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

        $groupSlug = array_key_exists('group_slug', $data)
            ? $this->normalizeGroupSlug($data['group_slug'])
            : ($row['group_slug'] ?? null);

        $update = [
            'title' => $title,
            'slug' => $slug,
            'description' => array_key_exists('description', $data)
                ? (trim((string) $data['description']) ?: null)
                : $row['description'],
            'cover_media_id' => $cover,
            'status' => $status,
            'group_slug' => $groupSlug,
            'updated_at' => date('c'),
        ];
        if (array_key_exists('embed_layout', $data) || array_key_exists('embed_options', $data) || array_key_exists('embed_presets', $data)) {
            if (array_key_exists('embed_layout', $data)) {
                $update['embed_layout'] = $this->normalizeEmbedLayout($data['embed_layout']);
            }
            $update['embed_options'] = $this->encodeEmbedStorage($data + [
                'embed_layout' => $update['embed_layout'] ?? ($row['embed_layout'] ?? 'carousel'),
            ]);
        }
        Database::update('galleries', $update, 'id = :id', ['id' => $id]);

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
        $sql = "SELECT * FROM media WHERE mime_type LIKE 'image/%'
                AND COALESCE(exclude_from_gallery, 0) = 0";
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
                    "SELECT 1 AS ok FROM media WHERE mime_type LIKE 'image/%'
                     AND COALESCE(exclude_from_gallery, 0) = 0 AND created_at < :b LIMIT 1",
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
               AND COALESCE(m.exclude_from_gallery, 0) = 0
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


    /**
     * Galleries belonging to a group (group_slug). Used for /gallery/{group} listing pages.
     */
    public function byGroup(string $groupSlug, bool $publishedOnly = true, int $limit = 100, int $offset = 0): array
    {
        $groupSlug = $this->normalizeGroupSlug($groupSlug) ?? '';
        if ($groupSlug === '') {
            return [];
        }
        $sql = 'SELECT * FROM galleries WHERE group_slug = :g';
        if ($publishedOnly) {
            $sql .= " AND status = 'published'";
        }
        $sql .= ' ORDER BY updated_at DESC LIMIT :limit OFFSET :offset';
        $rows = Database::fetchAll($sql, ['g' => $groupSlug, 'limit' => $limit, 'offset' => $offset]);
        return array_map(fn($r) => $this->hydrate($r), $rows);
    }

    public function countByGroup(string $groupSlug, bool $publishedOnly = true): int
    {
        $groupSlug = $this->normalizeGroupSlug($groupSlug) ?? '';
        if ($groupSlug === '') {
            return 0;
        }
        $sql = 'SELECT COUNT(*) AS c FROM galleries WHERE group_slug = :g';
        if ($publishedOnly) {
            $sql .= " AND status = 'published'";
        }
        $row = Database::fetch($sql, ['g' => $groupSlug]);
        return (int) ($row['c'] ?? 0);
    }

    /**
     * Normalize group slug: lowercase, dashes, empty becomes null.
     */
    private function normalizeGroupSlug(mixed $raw): ?string
    {
        $s = strtolower(trim((string) ($raw ?? '')));
        $s = preg_replace('/[^a-z0-9\-]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        return $s === '' ? null : $s;
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
        if (!empty($row['embed_options']) && is_string($row['embed_options'])) {
            $row['embed_options'] = json_decode($row['embed_options'], true) ?: [];
        } elseif (empty($row['embed_options'])) {
            $row['embed_options'] = [];
        }
        if (empty($row['embed_layout'])) {
            $row['embed_layout'] = 'carousel';
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



    /** Persist presets (+ legacy default layout) into embed_options JSON */
    public function encodeEmbedStorage(array $data): string
    {
        $layout = $this->normalizeEmbedLayout($data['embed_layout'] ?? 'carousel');
        if (!empty($data['embed_presets']) && is_array($data['embed_presets'])) {
            $presets = $this->normalizePresets(['presets' => $data['embed_presets']], $layout);
        } elseif (isset($data['embed_options']) && is_array($data['embed_options']) && isset($data['embed_options']['presets'])) {
            $presets = $this->normalizePresets($data['embed_options'], $layout);
        } elseif (isset($data['embed_options'])) {
            $presets = $this->normalizePresets([
                'presets' => [[
                    'key' => 'default',
                    'name' => 'Default',
                    'layout' => $layout,
                ] + (is_array($data['embed_options']) ? $data['embed_options'] : [])],
            ], $layout);
        } else {
            $presets = $this->normalizePresets([], $layout);
        }
        return json_encode(['presets' => $presets, 'default' => $presets[0]['key'] ?? 'default']);
    }

    public function normalizeEmbedLayout($layout): string
    {
        $layout = strtolower(trim((string) $layout));
        $allowed = ['carousel', 'columns', 'grid', 'collage', 'slide'];
        if ($layout === 'slide') {
            $layout = 'carousel';
        }
        return in_array($layout, $allowed, true) ? $layout : 'carousel';
    }

    /** Sanitize CSS size values like 20vh, 180px, 12rem */
    public function normalizeSize($value, string $fallback = '20vh'): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $fallback;
        }
        if (preg_match('/^\d+(\.\d+)?(px|vh|vw|rem|em|%)$/i', $value)) {
            return $value;
        }
        if (preg_match('/^\d+(\.\d+)?$/', $value)) {
            return $value . 'px';
        }
        return $fallback;
    }

    public function normalizeEmbedOptions($opts): array
    {
        if (is_string($opts)) {
            $decoded = json_decode($opts, true);
            $opts = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($opts)) {
            $opts = [];
        }
        $visible = (int) ($opts['slides_visible'] ?? $opts['visible'] ?? 1);
        if ($visible < 1) {
            $visible = 1;
        }
        if ($visible > 6) {
            $visible = 6;
        }
        return [
            'autoplay' => !empty($opts['autoplay']),
            'interval' => max(2000, min(20000, (int) ($opts['interval'] ?? 5000))),
            'arrows' => array_key_exists('arrows', $opts) ? !empty($opts['arrows']) : true,
            'dots' => array_key_exists('dots', $opts) ? !empty($opts['dots']) : true,
            'columns' => max(2, min(4, (int) ($opts['columns'] ?? 3))),
            'min_width' => max(120, min(480, (int) ($opts['min_width'] ?? 200))),
            'gap' => max(0, min(48, (int) ($opts['gap'] ?? 12))),
            'lightbox' => array_key_exists('lightbox', $opts) ? !empty($opts['lightbox']) : true,
            'aspect' => in_array(($opts['aspect'] ?? '16/9'), ['auto', '1/1', '4/3', '16/9', '3/2'], true)
                ? (string) ($opts['aspect'] ?? '16/9') : '16/9',
            'max_height' => $this->normalizeSize($opts['max_height'] ?? '20vh', '20vh'),
            'slides_visible' => $visible,
            'radius' => $this->normalizeSize($opts['radius'] ?? '8px', '8px'),
            'hover_name' => !empty($opts['hover_name']),
        ];
    }

    /**
     * Multiple named embed presets per gallery.
     * @return list<array{key:string,name:string,layout:string,...options}>
     */
    public function normalizePresets($raw, string $fallbackLayout = 'carousel'): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }
        if (!is_array($raw)) {
            $raw = [];
        }
        // Support { presets: [...], default: "x" } or a plain list
        $list = [];
        if (isset($raw['presets']) && is_array($raw['presets'])) {
            $list = $raw['presets'];
        } elseif ($raw !== [] && array_is_list($raw)) {
            $list = $raw;
        } elseif ($raw !== [] && !isset($raw['presets'])) {
            // Legacy flat options → one default preset
            $list = [[
                'key' => 'default',
                'name' => 'Default',
                'layout' => $fallbackLayout,
            ] + $raw];
        }
        $out = [];
        $used = [];
        foreach ($list as $i => $p) {
            if (!is_array($p)) {
                continue;
            }
            $key = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($p['key'] ?? ''))) ?: ('preset-' . ($i + 1));
            if (isset($used[$key])) {
                $key .= '-' . ($i + 1);
            }
            $used[$key] = true;
            $layout = $this->normalizeEmbedLayout($p['layout'] ?? $fallbackLayout);
            $opts = $this->normalizeEmbedOptions($p);
            $out[] = array_merge($opts, [
                'key' => $key,
                'name' => trim((string) ($p['name'] ?? $key)) ?: $key,
                'layout' => $layout,
            ]);
        }
        if ($out === []) {
            $opts = $this->normalizeEmbedOptions([]);
            $out[] = array_merge($opts, [
                'key' => 'default',
                'name' => 'Default',
                'layout' => $this->normalizeEmbedLayout($fallbackLayout),
            ]);
        }
        return $out;
    }

    public function getPresets(array $gallery): array
    {
        $layout = (string) ($gallery['embed_layout'] ?? 'carousel');
        $raw = $gallery['embed_options'] ?? [];
        return $this->normalizePresets($raw, $layout);
    }

    public function findPreset(array $gallery, ?string $key): array
    {
        $presets = $this->getPresets($gallery);
        if ($key) {
            foreach ($presets as $p) {
                if (($p['key'] ?? '') === $key) {
                    return $p;
                }
            }
        }
        return $presets[0];
    }

    /**
     * Payload for frontend embed (mount div / API).
     */
    public function embedPayload(int $id, ?string $layoutOverride = null, array $optsOverride = [], ?string $presetKey = null): ?array
    {
        $g = $this->find($id);
        if (!$g) {
            return null;
        }
        // Public embeds only for published galleries
        if (($g['status'] ?? '') !== 'published') {
            return null;
        }
        $preset = $this->findPreset($g, $presetKey);
        $layout = $this->normalizeEmbedLayout($layoutOverride ?? ($preset['layout'] ?? $g['embed_layout'] ?? 'carousel'));
        $baseOpts = $preset;
        unset($baseOpts['key'], $baseOpts['name'], $baseOpts['layout']);
        $opts = $this->normalizeEmbedOptions(array_merge($baseOpts, $optsOverride));
        $items = [];
        foreach ($this->items($id) as $it) {
            // hydrateItem already sets url, thumb_url, media{}, alt
            $m = is_array($it['media'] ?? null) ? $it['media'] : [];
            $url = (string) ($it['url'] ?? '');
            $thumb = (string) ($it['thumb_url'] ?? '');
            if ($url === '' && $m) {
                try {
                    $url = $this->media->url($m);
                    $thumb = $this->media->url($m, 480);
                } catch (\Throwable $e) {
                    $path = ltrim((string) ($m['path'] ?? ''), '/');
                    $url = $path !== '' ? '/mova-uploads/' . $path : '';
                }
            }
            // Prefer site-relative paths so embeds work on any host
            try {
                if ($url !== '' && method_exists($this->media, 'publicPath') && $m) {
                    $rel = $this->media->publicPath($m);
                    if (is_string($rel) && str_starts_with($rel, '/')) {
                        $url = $rel;
                    }
                    if ($thumb !== '' && $m) {
                        $relT = $this->media->publicPath($m, 480);
                        if (is_string($relT) && str_starts_with($relT, '/')) {
                            $thumb = $relT;
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
            if ($url === '' || rtrim($url, '/') === '/mova-uploads') {
                continue; // skip broken media rows
            }
            $items[] = [
                'id' => (int) ($it['media_id'] ?? $m['id'] ?? 0),
                'url' => $url,
                'thumb' => $thumb !== '' ? $thumb : $url,
                'alt' => (string) ($it['alt'] ?? $m['alt_text'] ?? $m['original_name'] ?? ''),
                'caption' => (string) ($it['caption'] ?? ''),
                'width' => (int) ($m['width'] ?? $it['width'] ?? 0),
                'height' => (int) ($m['height'] ?? $it['height'] ?? 0),
                'mime' => (string) ($m['mime_type'] ?? ''),
            ];
        }
        return [
            'id' => (int) $g['id'],
            'slug' => (string) $g['slug'],
            'title' => (string) $g['title'],
            'layout' => $layout,
            'options' => $opts,
            'items' => $items,
        ];
    }

    public function mountHtml(int $id, ?string $layout = null, array $opts = []): string
    {
        $layout = $this->normalizeEmbedLayout($layout ?? 'carousel');
        $attr = ' class="mova-gallery" data-gallery="' . (int) $id . '" data-layout="' . htmlspecialchars($layout, ENT_QUOTES) . '"';
        if ($opts !== []) {
            $attr .= ' data-opts=\'' . htmlspecialchars(json_encode($this->normalizeEmbedOptions($opts)), ENT_QUOTES) . '\'';
        }
        return '<div' . $attr . '></div>';
    }

    public function shortcode(int $id, ?string $layout = null): string
    {
        $layout = $this->normalizeEmbedLayout($layout ?? 'carousel');
        return '[gallery id="' . (int) $id . '" layout="' . $layout . '"]';
    }

    /** Expand [gallery id="1" layout="carousel"] in content bodies */
    public static function registerShortcodes(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!class_exists(\Mova\Plugin\PluginManager::class)) {
            return;
        }
        \Mova\Plugin\PluginManager::addFilter('content.render.body', function (string $body, array $content = []) {
            if ($body === '' || (stripos($body, '[gallery') === false && stripos($body, 'mova-gallery') === false)) {
                return $body;
            }
            $svc = new self();
            $body = preg_replace_callback(
                '/\[gallery\s+([^\]]*)\]/i',
                function ($m) use ($svc) {
                    $attrs = $m[1];
                    $id = 0;
                    $slug = '';
                    $layout = null;
                    $preset = null;
                    if (preg_match('/\bid=["\']?(\d+)/i', $attrs, $mm)) {
                        $id = (int) $mm[1];
                    }
                    if (preg_match('/\bslug=["\']([^"\']+)["\']/i', $attrs, $mm)) {
                        $slug = $mm[1];
                    }
                    if (preg_match('/\blayout=["\']([^"\']+)["\']/i', $attrs, $mm)) {
                        $layout = $mm[1];
                    }
                    if (preg_match('/\bpreset=["\']([^"\']+)["\']/i', $attrs, $mm)) {
                        $preset = $mm[1];
                    }
                    if ($id <= 0 && $slug !== '') {
                        $g = $svc->findBySlug($slug, false);
                        $id = $g ? (int) $g['id'] : 0;
                    }
                    if ($id <= 0) {
                        return '<!-- gallery: not found -->';
                    }
                    $g = $svc->find($id);
                    if ($preset && $g) {
                        $pr = $svc->findPreset($g, $preset);
                        $layout = $layout ?: ($pr['layout'] ?? null);
                    }
                    if (!$layout && $g) {
                        $layout = $g['embed_layout'] ?? 'carousel';
                    }
                    return $svc->mountHtml($id, $layout, [], $preset);
                },
                $body
            );
            static $assets = false;
            if (!$assets && is_string($body) && strpos($body, 'mova-gallery') !== false) {
                $assets = true;
                $body .= "\n<link rel=\"stylesheet\" href=\"/assets/css/mova-gallery-embed.css?v=2\">"
                    . "\n<script src=\"/assets/js/mova-gallery-embed.js?v=2\" defer></script>";
            }
            return $body;
        }, 25);
    }

}
