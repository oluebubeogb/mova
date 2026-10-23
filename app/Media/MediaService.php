<?php
/**
 * Mova CMS - Media Service
 */

namespace Mova\Media;

use Mova\Core\Bootstrap;
use Mova\Core\Database;

class MediaService
{
    public function upload(array $file, ?int $userId = null): array
    {
        $this->validate($file);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($file['tmp_name']) ?: $file['type'];

        // SVG special handling
        if ($ext === 'svg' || $mime === 'image/svg+xml') {
            $this->sanitizeSvg($file['tmp_name']);
        }

        $filename = $this->uniqueFilename($ext);
        $subdir = date('Y/m');
        $uploadsRoot = Bootstrap::path('uploads');
        if ($uploadsRoot === '') {
            throw new \RuntimeException('Upload path is not configured.');
        }
        $uploadDir = $uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);

        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                throw new \RuntimeException('Cannot create upload folder: ' . $subdir . ' (check permissions on mova-uploads).');
            }
        }
        if (!is_writable($uploadDir)) {
            throw new \RuntimeException('Upload folder is not writable: ' . $subdir);
        }

        $dest = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            // Fallback copy for some hosts that alter tmp files
            if (!@copy($file['tmp_name'], $dest)) {
                throw new \RuntimeException('Failed to move uploaded file into mova-uploads.');
            }
            @unlink($file['tmp_name']);
        }

        $width = $height = null;
        $variants = [];

        if (strpos($mime, 'image/') === 0 && $ext !== 'svg') {
            $info = getimagesize($dest);
            if ($info) {
                $width = $info[0];
                $height = $info[1];
            }

            if (Bootstrap::config('media.convert_to_webp', true) && function_exists('imagewebp')) {
                $variants = $this->processImage($dest, $subdir, $filename, $width, $height);
            }
        }

        $relativePath = $subdir . '/' . $filename;
        $fileHash = @hash_file('sha256', $dest) ?: null;

        // Duplicate detection (hash of original upload before we discard it)
        if ($fileHash) {
            try {
                $dup = Database::fetch("SELECT * FROM media WHERE file_hash = :h LIMIT 1", ['h' => $fileHash]);
            } catch (\Throwable $e) {
                $dup = null; // older DBs without file_hash column
            }
            if ($dup) {
                @unlink($dest);
                foreach ($variants as $v) {
                    $vf = Bootstrap::path('uploads') . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string) ($v['path'] ?? ''));
                    if (is_file($vf)) {
                        @unlink($vf);
                    }
                }
                return $this->find((int) $dup['id']);
            }
        }

        // Prefer WebP variants only: drop the original upload file to save storage
        $storeFilename = $filename;
        $storeMime = $mime;
        $storeExt = $ext;
        $storeSize = @filesize($dest) ?: ($file['size'] ?? 0);
        $storePath = $relativePath;
        $storeW = $width;
        $storeH = $height;

        if (!empty($variants)) {
            // Use the largest variant as the canonical media path
            usort($variants, static function ($a, $b) {
                return ($b['width'] ?? 0) <=> ($a['width'] ?? 0);
            });
            $primary = $variants[0];
            $storePath = $primary['path'];
            $storeFilename = basename($primary['path']);
            $storeMime = 'image/webp';
            $storeExt = 'webp';
            $storeSize = (int) ($primary['size'] ?? 0);
            $storeW = (int) ($primary['width'] ?? $width);
            $storeH = (int) ($primary['height'] ?? $height);

            // Delete original uploaded file (PNG/JPEG/etc.) — variants are enough
            if (is_file($dest)) {
                @unlink($dest);
            }
        }

        $id = Database::insert('media', [
            'filename'      => $storeFilename,
            'original_name' => $file['name'],
            'mime_type'     => $storeMime,
            'extension'     => $storeExt,
            'size'          => $storeSize,
            'width'         => $storeW,
            'height'        => $storeH,
            'alt_text'      => '',
            'path'          => $storePath,
            'variants'      => json_encode($variants),
            'file_hash'     => $fileHash,
            'focal_x'       => 0.5,
            'focal_y'       => 0.5,
            'uploaded_by'   => $userId,
            'created_at'    => date('c'),
        ]);

        $row = $this->find($id);
        // Auto poster for videos
        if ($row && strpos((string) ($row['mime_type'] ?? ''), 'video/') === 0) {
            try {
                $row = $this->ensureVideoPoster($id) ?? $row;
            } catch (\Throwable $e) {
                // non-fatal
            }
        }
        return $row;
    }

    /**
     * Ingest AI-generated (or remote) image bytes into the media library.
     * Runs the same WebP variant pipeline as uploads when enabled.
     *
     * @param array{alt_text?:string,original_name?:string,width?:int,height?:int} $meta
     */
    public function ingestGenerated(string $binary, string $mime, ?int $userId = null, array $meta = []): array
    {
        if ($binary === '') {
            throw new \InvalidArgumentException('Empty image data');
        }

        $mime = strtolower(trim($mime));
        if ($mime === '' || strpos($mime, 'image/') !== 0) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->buffer($binary);
            $mime = (is_string($detected) && strpos($detected, 'image/') === 0) ? $detected : 'image/png';
        }

        $extMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $ext = $extMap[$mime] ?? 'png';

        $filename = $this->uniqueFilename($ext);
        $subdir = date('Y/m');
        $uploadsRoot = Bootstrap::path('uploads');
        if ($uploadsRoot === '') {
            throw new \RuntimeException('Upload path is not configured.');
        }
        $uploadDir = $uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                throw new \RuntimeException('Cannot create upload folder: ' . $subdir);
            }
        }

        $dest = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (@file_put_contents($dest, $binary) === false) {
            throw new \RuntimeException('Failed to write generated image');
        }

        $width = isset($meta['width']) ? (int) $meta['width'] : null;
        $height = isset($meta['height']) ? (int) $meta['height'] : null;
        $info = @getimagesize($dest);
        if ($info) {
            $width = (int) $info[0];
            $height = (int) $info[1];
        }

        $variants = [];
        if (Bootstrap::config('media.convert_to_webp', true) && function_exists('imagewebp') && $ext !== 'svg') {
            $variants = $this->processImage($dest, $subdir, $filename, $width, $height);
        }

        $relativePath = $subdir . '/' . $filename;
        $fileHash = @hash_file('sha256', $dest) ?: null;

        $storeFilename = $filename;
        $storeMime = $mime;
        $storeExt = $ext;
        $storeSize = @filesize($dest) ?: strlen($binary);
        $storePath = $relativePath;
        $storeW = $width;
        $storeH = $height;

        if ($variants !== []) {
            usort($variants, static function ($a, $b) {
                return ((int) ($b['width'] ?? 0)) <=> ((int) ($a['width'] ?? 0));
            });
            $primary = $variants[0];
            $storePath = $primary['path'];
            $storeFilename = basename($primary['path']);
            $storeMime = 'image/webp';
            $storeExt = 'webp';
            $storeSize = (int) ($primary['size'] ?? 0);
            $storeW = (int) ($primary['width'] ?? $width);
            $storeH = (int) ($primary['height'] ?? $height);
            if (is_file($dest) && $storePath !== $relativePath) {
                @unlink($dest);
            }
        }

        $originalName = (string) ($meta['original_name'] ?? ('ai-generated.' . $storeExt));
        $alt = (string) ($meta['alt_text'] ?? '');

        $id = Database::insert('media', [
            'filename'      => $storeFilename,
            'original_name' => $originalName,
            'mime_type'     => $storeMime,
            'extension'     => $storeExt,
            'size'          => $storeSize,
            'width'         => $storeW,
            'height'        => $storeH,
            'alt_text'      => $alt,
            'path'          => $storePath,
            'variants'      => json_encode($variants),
            'file_hash'     => $fileHash,
            'focal_x'       => 0.5,
            'focal_y'       => 0.5,
            'uploaded_by'   => $userId,
            'created_at'    => date('c'),
        ]);

        $row = $this->find($id);
        if (!$row) {
            throw new \RuntimeException('Media row not found after ingest');
        }
        return $row;
    }

    public function find(int $id): ?array
    {
        $row = Database::fetch("SELECT * FROM media WHERE id = :id", ['id' => $id]);
        if ($row && !empty($row['variants'])) {
            $row['variants'] = json_decode($row['variants'], true) ?: [];
        }
        return $row;
    }

    public function all(int $limit = 40, int $offset = 0): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM media ORDER BY created_at DESC LIMIT :limit OFFSET :offset",
            ['limit' => $limit, 'offset' => $offset]
        );
        foreach ($rows as &$row) {
            if (!empty($row['variants'])) {
                $row['variants'] = json_decode($row['variants'], true) ?: [];
            }
        }
        return $rows;
    }

    public function count(): int
    {
        $row = Database::fetch('SELECT COUNT(*) AS c FROM media');
        return (int) ($row['c'] ?? 0);
    }


    public function setExcludeFromGallery(int $id, bool $exclude): bool
    {
        $media = $this->find($id);
        if (!$media) {
            return false;
        }
        try {
            Database::update(
                'media',
                ['exclude_from_gallery' => $exclude ? 1 : 0],
                'id = :id',
                ['id' => $id]
            );
        } catch (\Throwable $e) {
            return false;
        }
        return true;
    }

    /**
     * Update editable metadata (original_name, alt_text, description). Auto-save from HQ sidebar.
     */
    public function updateMeta(int $id, array $fields): ?array
    {
        $media = $this->find($id);
        if (!$media) {
            return null;
        }
        $allowed = [];
        if (array_key_exists('original_name', $fields)) {
            $name = trim((string) $fields['original_name']);
            if ($name !== '') {
                $allowed['original_name'] = $name;
            }
        }
        if (array_key_exists('alt_text', $fields)) {
            $allowed['alt_text'] = trim((string) $fields['alt_text']);
        }
        if (array_key_exists('description', $fields)) {
            $allowed['description'] = trim((string) $fields['description']);
        }
        if ($allowed === []) {
            return $media;
        }
        try {
            Database::update('media', $allowed, 'id = :id', ['id' => $id]);
        } catch (\Throwable $e) {
            return null;
        }
        return $this->find($id);
    }

    /**
     * Collect path/URL aliases that may appear in content for this media row.
     * Longer strings first so find-replace does not partially match.
     *
     * @return list<string>
     */
    public function urlAliases(array $media): array
    {
        $aliases = [];
        $paths = [];
        $main = ltrim(str_replace('\\', '/', (string) ($media['path'] ?? '')), '/');
        if ($main !== '') {
            $paths[] = $main;
        }
        $variants = $media['variants'] ?? [];
        if (is_string($variants)) {
            $variants = json_decode($variants, true) ?: [];
        }
        if (is_array($variants)) {
            foreach ($variants as $v) {
                if (!is_array($v)) {
                    continue;
                }
                $p = ltrim(str_replace('\\', '/', (string) ($v['path'] ?? '')), '/');
                if ($p !== '') {
                    $paths[] = $p;
                }
            }
        }
        $paths = array_values(array_unique($paths));
        $base = rtrim((string) Bootstrap::baseUrl(), '/');
        foreach ($paths as $p) {
            $aliases[] = $p;
            $aliases[] = '/mova-uploads/' . $p;
            $aliases[] = 'mova-uploads/' . $p;
            if ($base !== '') {
                $aliases[] = $base . '/mova-uploads/' . $p;
            }
        }
        // Full helper URLs (may include base)
        try {
            $aliases[] = $this->url($media);
            if (!empty($media['variants']) && is_array($media['variants'])) {
                foreach ($media['variants'] as $v) {
                    $w = (int) ($v['width'] ?? 0);
                    if ($w > 0) {
                        $aliases[] = $this->url($media, $w);
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $aliases = array_values(array_unique(array_filter(array_map('strval', $aliases))));
        usort($aliases, static function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });
        return $aliases;
    }

    /**
     * Upload a new image and retarget all site references from $oldId to the new media,
     * then retarget site URLs. Old media is kept by default.
     *
     * @return array{old:array,new:array,retarget:array,deleted:bool}
     */
    public function replaceByNewUpload(int $oldId, array $file, ?int $userId = null, bool $deleteOld = false): array
    {
        $old = $this->find($oldId);
        if (!$old) {
            throw new \InvalidArgumentException('Media not found.');
        }
        $new = $this->upload($file, $userId);
        if (!$new || empty($new['id'])) {
            throw new \RuntimeException('Upload of replacement media failed.');
        }
        // Copy alt/description if new is empty
        $meta = [];
        if (trim((string) ($new['alt_text'] ?? '')) === '' && trim((string) ($old['alt_text'] ?? '')) !== '') {
            $meta['alt_text'] = (string) $old['alt_text'];
        }
        if (trim((string) ($new['description'] ?? '')) === '' && trim((string) ($old['description'] ?? '')) !== '') {
            $meta['description'] = (string) ($old['description'] ?? '');
        }
        if ($meta !== []) {
            $this->updateMeta((int) $new['id'], $meta);
            $new = $this->find((int) $new['id']) ?? $new;
        }

        $retarget = $this->retargetSiteToNewMedia($old, $new);
        $deleted = false;
        if ($deleteOld && (int) $new['id'] !== $oldId) {
            $deleted = $this->delete($oldId);
        }
        return [
            'old' => $old,
            'new' => $this->find((int) $new['id']) ?? $new,
            'retarget' => $retarget,
            'deleted' => $deleted,
        ];
    }

    /**
     * After AI (or any) created $newId, retarget site refs from $oldId. Old media is kept by default.
     *
     * @return array{old:array,new:array,retarget:array,deleted:bool}
     */
    public function replaceByExistingMedia(int $oldId, int $newId, bool $deleteOld = false): array
    {
        $old = $this->find($oldId);
        $new = $this->find($newId);
        if (!$old || !$new) {
            throw new \InvalidArgumentException('Media not found.');
        }
        if ($oldId === $newId) {
            throw new \InvalidArgumentException('Old and new media must differ.');
        }
        $retarget = $this->retargetSiteToNewMedia($old, $new);
        $deleted = false;
        if ($deleteOld) {
            $deleted = $this->delete($oldId);
        }
        return [
            'old' => $old,
            'new' => $new,
            'retarget' => $retarget,
            'deleted' => $deleted,
        ];
    }

    /**
     * Rewrite content / featured images / gallery rows from old media URLs to new media URLs.
     *
     * @return array{pairs:int,content_occurrences:int,content_rows:int,featured:int,gallery:int,meta:int}
     */
    public function retargetSiteToNewMedia(array $old, array $new): array
    {
        $oldAliases = $this->urlAliases($old);
        $newPrimary = $this->url($new);
        $newRel = '/mova-uploads/' . ltrim(str_replace('\\', '/', (string) ($new['path'] ?? '')), '/');
        $newPath = ltrim(str_replace('\\', '/', (string) ($new['path'] ?? '')), '/');

        // Map each old alias to the best corresponding new form
        $pairs = [];
        $newAliases = $this->urlAliases($new);
        $newBySuffix = [];
        foreach ($newAliases as $na) {
            $newBySuffix[$na] = $na;
        }
        foreach ($oldAliases as $oa) {
            if ($oa === '' || strlen($oa) < 4) {
                continue;
            }
            // Prefer same "shape" of URL
            if (str_starts_with($oa, 'http://') || str_starts_with($oa, 'https://')) {
                $pairs[$oa] = $newPrimary;
            } elseif (str_starts_with($oa, '/mova-uploads/')) {
                $pairs[$oa] = $newRel;
            } elseif (str_starts_with($oa, 'mova-uploads/')) {
                $pairs[$oa] = ltrim($newRel, '/');
            } else {
                $pairs[$oa] = $newPath !== '' ? $newPath : $newRel;
            }
        }
        // Longest find first
        uksort($pairs, static function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        $contentOcc = 0;
        $contentRows = 0;
        $featured = 0;
        $gallery = 0;
        $metaHits = 0;

        // Body/title/excerpt via FindReplaceService (changelog + cache flush)
        if (class_exists(\Mova\Content\FindReplaceService::class) && $pairs !== []) {
            try {
                $fr = new \Mova\Content\FindReplaceService();
                foreach ($pairs as $find => $replace) {
                    if ($find === '' || $find === $replace) {
                        continue;
                    }
                    $result = $fr->apply([
                        'find' => $find,
                        'replace' => $replace,
                        'match_case' => true,
                        'fields' => ['title', 'excerpt', 'body'],
                    ]);
                    $contentOcc += (int) ($result['match_count'] ?? 0);
                    $contentRows += (int) ($result['content_count'] ?? 0);
                }
            } catch (\Throwable $e) {
                // Fall through to direct SQL for featured/meta
            }
        }

        // featured_image column
        foreach ($pairs as $find => $replace) {
            if ($find === '' || $find === $replace) {
                continue;
            }
            try {
                $rows = Database::fetchAll(
                    "SELECT id, featured_image FROM content WHERE featured_image IS NOT NULL AND featured_image != '' AND featured_image LIKE :q",
                    ['q' => '%' . $find . '%']
                );
                foreach ($rows as $row) {
                    $oldVal = (string) ($row['featured_image'] ?? '');
                    $newVal = str_replace($find, $replace, $oldVal);
                    if ($newVal !== $oldVal) {
                        Database::update('content', ['featured_image' => $newVal, 'updated_at' => date('c')], 'id = :id', ['id' => (int) $row['id']]);
                        $featured++;
                    }
                }
            } catch (\Throwable $e) {
                // column may differ
            }
        }

        // content_meta values
        foreach ($pairs as $find => $replace) {
            if ($find === '' || $find === $replace) {
                continue;
            }
            try {
                $rows = Database::fetchAll(
                    "SELECT id, meta_value FROM content_meta WHERE meta_value LIKE :q",
                    ['q' => '%' . $find . '%']
                );
                foreach ($rows as $row) {
                    $oldVal = (string) ($row['meta_value'] ?? '');
                    $newVal = str_replace($find, $replace, $oldVal);
                    if ($newVal !== $oldVal) {
                        Database::update('content_meta', ['meta_value' => $newVal], 'id = :id', ['id' => (int) $row['id']]);
                        $metaHits++;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        // gallery_items: point at new media_id
        $oldId = (int) ($old['id'] ?? 0);
        $newId = (int) ($new['id'] ?? 0);
        if ($oldId > 0 && $newId > 0 && $oldId !== $newId) {
            try {
                $items = Database::fetchAll(
                    'SELECT id, gallery_id, media_id FROM gallery_items WHERE media_id = :m',
                    ['m' => $oldId]
                );
                foreach ($items as $it) {
                    try {
                        Database::update(
                            'gallery_items',
                            ['media_id' => $newId],
                            'id = :id',
                            ['id' => (int) $it['id']]
                        );
                        $gallery++;
                    } catch (\Throwable $e) {
                        // unique constraint — drop old row if new already in gallery
                        try {
                            Database::delete('gallery_items', 'id = :id', ['id' => (int) $it['id']]);
                            $gallery++;
                        } catch (\Throwable $e2) {
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if (class_exists(\Mova\Cache\PageCache::class)) {
            try {
                \Mova\Cache\PageCache::flush();
            } catch (\Throwable $e) {
            }
        }

        return [
            'pairs' => count($pairs),
            'content_occurrences' => $contentOcc,
            'content_rows' => $contentRows,
            'featured' => $featured,
            'gallery' => $gallery,
            'meta' => $metaHits,
        ];
    }

    /**
     * Generate a poster frame for video media (scene thumbnail).
     * Uses ffmpeg when available; otherwise leaves poster_path empty (client may capture).
     */
    public function ensureVideoPoster(int $id): ?array
    {
        $media = $this->find($id);
        if (!$media) {
            return null;
        }
        $mime = (string) ($media['mime_type'] ?? '');
        if (strpos($mime, 'video/') !== 0) {
            return $media;
        }
        if (!empty($media['poster_path'])) {
            $posterFile = Bootstrap::path('uploads') . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string) $media['poster_path']);
            if (is_file($posterFile)) {
                return $media;
            }
        }

        $videoRel = (string) ($media['path'] ?? '');
        $videoAbs = Bootstrap::path('uploads') . '/' . str_replace('/', DIRECTORY_SEPARATOR, $videoRel);
        if (!is_file($videoAbs)) {
            return $media;
        }

        $subdir = dirname(str_replace('\\', '/', $videoRel));
        if ($subdir === '.' || $subdir === '') {
            $subdir = date('Y/m');
        }
        $posterName = pathinfo((string) ($media['filename'] ?? 'video'), PATHINFO_FILENAME) . '-poster.webp';
        $posterRel = $subdir . '/' . $posterName;
        $posterAbs = Bootstrap::path('uploads') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $posterRel);
        $posterDir = dirname($posterAbs);
        if (!is_dir($posterDir)) {
            @mkdir($posterDir, 0755, true);
        }

        $ffmpeg = $this->findFfmpeg();
        if ($ffmpeg) {
            // Grab a frame ~1s in (or at 0 if short)
            $tmpJpg = $posterAbs . '.jpg';
            $cmd = escapeshellarg($ffmpeg)
                . ' -y -ss 00:00:01 -i ' . escapeshellarg($videoAbs)
                . ' -vframes 1 -q:v 2 ' . escapeshellarg($tmpJpg) . ' 2>/dev/null';
            @exec($cmd, $out, $code);
            if (!is_file($tmpJpg) || filesize($tmpJpg) < 32) {
                $cmd = escapeshellarg($ffmpeg)
                    . ' -y -ss 00:00:00 -i ' . escapeshellarg($videoAbs)
                    . ' -vframes 1 -q:v 2 ' . escapeshellarg($tmpJpg) . ' 2>/dev/null';
                @exec($cmd, $out, $code);
            }
            if (is_file($tmpJpg) && filesize($tmpJpg) > 32 && function_exists('imagecreatefromjpeg') && function_exists('imagewebp')) {
                $img = @imagecreatefromjpeg($tmpJpg);
                if ($img) {
                    imagewebp($img, $posterAbs, 82);
                    imagedestroy($img);
                }
                @unlink($tmpJpg);
            } elseif (is_file($tmpJpg)) {
                @rename($tmpJpg, preg_replace('/\.webp$/', '.jpg', $posterAbs));
                $posterRel = preg_replace('/\.webp$/', '.jpg', $posterRel);
                $posterAbs = Bootstrap::path('uploads') . '/' . str_replace('/', DIRECTORY_SEPARATOR, $posterRel);
            }
        }

        if (is_file($posterAbs)) {
            try {
                Database::update('media', ['poster_path' => $posterRel], 'id = :id', ['id' => $id]);
            } catch (\Throwable $e) {
                // column may not exist yet
            }
            return $this->find($id);
        }
        return $media;
    }

    private function findFfmpeg(): ?string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached === '' ? null : $cached;
        }
        $candidates = ['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg'];
        foreach ($candidates as $bin) {
            $check = $bin === 'ffmpeg' ? 'command -v ffmpeg 2>/dev/null' : (is_executable($bin) ? 'echo ' . escapeshellarg($bin) : 'false');
            $path = trim((string) @shell_exec($check));
            if ($path !== '') {
                $cached = $path;
                return $cached;
            }
            if ($bin !== 'ffmpeg' && is_executable($bin)) {
                $cached = $bin;
                return $cached;
            }
        }
        $cached = '';
        return null;
    }

    /**
     * Find content/pages where this media path appears (for media.xml occurrences).
     * @return list<array{title:string,slug:string,url:string}>
     */
    public function findOccurrences(array $media): array
    {
        $path = (string) ($media['path'] ?? '');
        $filename = (string) ($media['filename'] ?? '');
        if ($path === '' && $filename === '') {
            return [];
        }
        $needles = array_filter([$path, $filename]);
        $variants = is_array($media['variants'] ?? null) ? $media['variants'] : [];
        foreach ($variants as $v) {
            if (!empty($v['path'])) {
                $needles[] = (string) $v['path'];
            }
        }
        $needles = array_values(array_unique($needles));
        $found = [];
        try {
            $rows = Database::fetchAll(
                "SELECT id, title, slug, body, featured_image FROM content WHERE status = 'published' LIMIT 500"
            );
        } catch (\Throwable $e) {
            return [];
        }
        $base = Bootstrap::baseUrl();
        foreach ($rows as $row) {
            $hay = (string) ($row['body'] ?? '') . ' ' . (string) ($row['featured_image'] ?? '');
            foreach ($needles as $n) {
                if ($n !== '' && strpos($hay, $n) !== false) {
                    $slug = (string) ($row['slug'] ?? '');
                    $found[] = [
                        'title' => (string) ($row['title'] ?? $slug),
                        'slug'  => $slug,
                        'url'   => $base . '/' . ltrim($slug, '/'),
                    ];
                    break;
                }
            }
        }
        return $found;
    }

    public function posterUrl(array $media): string
    {
        if (!empty($media['poster_path'])) {
            return Bootstrap::baseUrl() . '/mova-uploads/' . ltrim((string) $media['poster_path'], '/');
        }
        return '';
    }

    public function delete(int $id): bool
    {
        $media = $this->find($id);
        if (!$media) {
            return false;
        }

        $base = Bootstrap::path('uploads');
        $file = $base . '/' . $media['path'];
        if (is_file($file)) {
            unlink($file);
        }

        // Remove variants
        if (!empty($media['variants'])) {
            foreach ($media['variants'] as $v) {
                $vf = $base . '/' . ($v['path'] ?? '');
                if (is_file($vf)) {
                    unlink($vf);
                }
            }
        }

        if (!empty($media['poster_path'])) {
            $pf = $base . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string) $media['poster_path']);
            if (is_file($pf)) {
                @unlink($pf);
            }
        }

        return Database::delete('media', 'id = :id', ['id' => $id]) > 0;
    }

    public function url(array $media, ?int $width = null): string
    {
        $base = Bootstrap::baseUrl() . '/mova-uploads/';

        if (!empty($media['variants']) && is_array($media['variants'])) {
            if ($width) {
                // Exact match
                foreach ($media['variants'] as $v) {
                    if (($v['width'] ?? 0) == $width && !empty($v['path'])) {
                        return $base . $v['path'];
                    }
                }
                // Closest available at or below requested width (never invent missing -1200)
                $bestAtOrBelow = null;
                foreach ($media['variants'] as $v) {
                    $vw = (int) ($v['width'] ?? 0);
                    if ($vw > 0 && $vw <= $width && !empty($v['path'])) {
                        if ($bestAtOrBelow === null || $vw > (int) ($bestAtOrBelow['width'] ?? 0)) {
                            $bestAtOrBelow = $v;
                        }
                    }
                }
                if ($bestAtOrBelow) {
                    return $base . $bestAtOrBelow['path'];
                }
            }
            // Prefer largest variant that actually exists in the record
            $best = null;
            foreach ($media['variants'] as $v) {
                if ($best === null || ($v['width'] ?? 0) > ($best['width'] ?? 0)) {
                    $best = $v;
                }
            }
            if ($best && !empty($best['path'])) {
                return $base . $best['path'];
            }
        }

        return $base . ($media['path'] ?? '');
    }

    /** Relative public path for HQ/media grid (starts with /mova-uploads/). */
    public function publicPath(array $media, ?int $width = null): string
    {
        $url = $this->url($media, $width);
        $base = Bootstrap::baseUrl();
        if ($base !== '' && str_starts_with($url, $base)) {
            return substr($url, strlen($base)) ?: '/';
        }
        return $url;
    }

    private function validate(array $file): void
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'Upload blocked by a PHP extension.',
            ];
            throw new \InvalidArgumentException($messages[$err] ?? ('Upload error code ' . $err));
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('Invalid upload (temp file missing).');
        }

        $max = (int) Bootstrap::config('media.max_upload_size', 10 * 1024 * 1024);
        if (($file['size'] ?? 0) > $max) {
            throw new \InvalidArgumentException('File too large (max ' . (int) round($max / 1048576) . ' MB).');
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $defaultExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'pdf', 'mp4', 'webm', 'mp3', 'wav', 'mpd'];
        $allowedExt = Bootstrap::config('media.allowed_extensions', $defaultExt);
        if (!is_array($allowedExt) || $allowedExt === []) {
            $allowedExt = $defaultExt;
        }
        if (!in_array($ext, $allowedExt, true)) {
            throw new \InvalidArgumentException('File type not allowed (.' . $ext . ').');
        }

        // Block PHP execution
        if (in_array($ext, ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'cgi', 'pl'], true)) {
            throw new \InvalidArgumentException('Executable files not allowed.');
        }

        $mime = @mime_content_type($file['tmp_name']) ?: ($file['type'] ?? '');
        $defaultMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon',
            'application/pdf',
            'video/mp4', 'video/webm',
            'audio/mpeg', 'audio/wav', 'audio/mp3',
            'application/dash+xml', 'application/mpd', 'video/mpd', 'text/xml',
        ];
        $allowedMimes = Bootstrap::config('media.allowed_mimes', $defaultMimes);
        if (!is_array($allowedMimes) || $allowedMimes === []) {
            $allowedMimes = $defaultMimes;
        }
        // Soft check: only enforce when we could detect a mime
        if ($mime !== '' && !in_array($mime, $allowedMimes, true)) {
            // Allow common jpeg alias
            if (!($mime === 'image/jpg' && in_array('image/jpeg', $allowedMimes, true))) {
                // .mpd is often detected as application/xml or text/xml
                if (!($ext === 'mpd' && (str_contains($mime, 'xml') || str_contains($mime, 'dash') || str_contains($mime, 'mpd')))) {
                    throw new \InvalidArgumentException('MIME type not allowed (' . $mime . ').');
                }
            }
        }
    }

    private function uniqueFilename(string $ext): string
    {
        return bin2hex(random_bytes(16)) . '.' . $ext;
    }

    private function processImage(string $source, string $subdir, string $originalFilename, ?int $origW, ?int $origH): array
    {
        $variants = [];
        $sizes = Bootstrap::config('media.variants', [320, 480, 768, 1200]);
        $quality = (int) Bootstrap::config('media.quality', 85);
        $uploadDir = Bootstrap::path('uploads') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);

        $img = $this->loadImage($source);
        if (!$img) {
            return [];
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $baseName = pathinfo($originalFilename, PATHINFO_FILENAME);

        // Only create the configured variants (default 480, 768, 1200).
        // Never keep a full-resolution copy — keeps storage and bandwidth light.
        $created = 0;
        foreach ($sizes as $targetW) {
            if ($w < $targetW && $created > 0) {
                // Image smaller than this breakpoint and we already have a variant — skip
                continue;
            }
            // If source is smaller than target, write one WebP at natural size once
            $outW = $w <= $targetW ? $w : $targetW;
            $outH = $w <= $targetW ? $h : (int) round($h * ($targetW / $w));

            // Avoid duplicate identical files when multiple sizes exceed source width
            $already = false;
            foreach ($variants as $v) {
                if (($v['width'] ?? 0) === $outW) {
                    $already = true;
                    break;
                }
            }
            if ($already) {
                continue;
            }

            if ($outW === $w && $outH === $h) {
                $name = $baseName . '-' . $outW . '.webp';
                $path = $uploadDir . '/' . $name;
                imagewebp($img, $path, $quality);
            } else {
                $resized = imagecreatetruecolor($outW, $outH);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $outW, $outH, $w, $h);
                $name = $baseName . "-{$outW}.webp";
                $path = $uploadDir . '/' . $name;
                imagewebp($resized, $path, $quality);
                imagedestroy($resized);
            }

            $variants[] = [
                'width'  => $outW,
                'height' => $outH,
                'path'   => $subdir . '/' . $name,
                'size'   => @filesize($path) ?: 0,
            ];
            $created++;
        }

        // If image was smaller than every breakpoint and nothing was written, force one WebP
        if ($variants === []) {
            $name = $baseName . '-' . $w . '.webp';
            $path = $uploadDir . '/' . $name;
            imagewebp($img, $path, $quality);
            $variants[] = [
                'width'  => $w,
                'height' => $h,
                'path'   => $subdir . '/' . $name,
                'size'   => @filesize($path) ?: 0,
            ];
        }

        imagedestroy($img);
        return $variants;
    }

    private function loadImage(string $path)
    {
        $info = getimagesize($path);
        if (!$info) {
            return false;
        }

        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                $img = imagecreatefrompng($path);
                imagealphablending($img, false);
                imagesavealpha($img, true);
                return $img;
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($path);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($path);
            default:
                return false;
        }
    }

    /**
     * Ensure all configured width variants exist for an existing media row.
     * Used on upgrade so sites that already uploaded images get 320/480/etc.
     * @return array updated variants list (also persisted)
     */
    /**
     * Ensure all configured width variants exist for an existing media row.
     * Used on upgrade so sites that already uploaded images get 320/480/etc.
     * @return array updated variants list (also persisted)
     */
    public function ensureVariants(array $media): array
    {
        if (empty($media['id']) || strpos((string)($media['mime_type'] ?? ''), 'image/') !== 0) {
            return $media['variants'] ?? [];
        }
        if (($media['extension'] ?? '') === 'svg') {
            return $media['variants'] ?? [];
        }

        $base = Bootstrap::path('uploads');
        $existing = is_array($media['variants'] ?? null) ? $media['variants'] : [];
        $have = [];
        foreach ($existing as $v) {
            $have[(int)($v['width'] ?? 0)] = $v;
        }

        $sizes = Bootstrap::config('media.variants', [320, 480, 768, 1200]);
        $need = false;
        foreach ($sizes as $w) {
            if (!isset($have[(int)$w])) {
                $need = true;
                break;
            }
        }
        // Also need if no file exists on disk for listed variants
        if (!$need) {
            foreach ($existing as $v) {
                $p = $base . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string)($v['path'] ?? ''));
                if (!is_file($p)) {
                    $need = true;
                    break;
                }
            }
        }
        if (!$need) {
            return $existing;
        }

        // Find a source file: prefer largest existing variant, else media path
        $sourcePath = null;
        $bestW = 0;
        foreach ($existing as $v) {
            $p = $base . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string)($v['path'] ?? ''));
            if (is_file($p) && (int)($v['width'] ?? 0) >= $bestW) {
                $sourcePath = $p;
                $bestW = (int)($v['width'] ?? 0);
            }
        }
        if (!$sourcePath) {
            $p = $base . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string)($media['path'] ?? ''));
            if (is_file($p)) {
                $sourcePath = $p;
            }
        }
        if (!$sourcePath) {
            return $existing;
        }

        $info = @getimagesize($sourcePath);
        $origW = $info ? $info[0] : (int)($media['width'] ?? 0);
        $origH = $info ? $info[1] : (int)($media['height'] ?? 0);
        $subdir = dirname(str_replace('\\', '/', (string)($media['path'] ?? '')));
        if ($subdir === '.') {
            $subdir = date('Y/m');
        }
        $filename = basename($sourcePath);
        // processImage expects original filename without size suffix ideally
        $filename = preg_replace('/-(320|480|768|1200)\.webp$/i', '.webp', $filename);

        $newVariants = $this->processImage($sourcePath, $subdir, $filename, $origW, $origH);
        if ($newVariants === []) {
            return $existing;
        }

        // Prefer largest as stored width/height
        usort($newVariants, static function ($a, $b) {
            return ($b['width'] ?? 0) <=> ($a['width'] ?? 0);
        });
        $primary = $newVariants[0];

        Database::query(
            'UPDATE media SET variants = :v, width = :w, height = :h WHERE id = :id',
            [
                'v' => json_encode($newVariants),
                'w' => (int)($primary['width'] ?? $origW),
                'h' => (int)($primary['height'] ?? $origH),
                'id' => (int)$media['id'],
            ]
        );

        return $newVariants;
    }

    private function sanitizeSvg(string $path): void
    {
        $content = file_get_contents($path);
        // Remove script tags and event handlers (basic sanitization)
        $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $content);
        $content = preg_replace('/javascript\s*:/i', '', $content);
        file_put_contents($path, $content);
    }
}
