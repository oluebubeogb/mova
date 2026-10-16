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
     * Replace image bytes while keeping the same public path / variants URLs.
     * Compresses and converts to WebP variants in place (safe overwrite — never deletes the new files).
     */
    public function replaceImage(int $id, array $file): ?array
    {
        $media = $this->find($id);
        if (!$media) {
            throw new \InvalidArgumentException('Media not found.');
        }
        $this->validate($file);

        $mime = mime_content_type($file['tmp_name']) ?: ($file['type'] ?? '');
        if (strpos($mime, 'image/') !== 0) {
            throw new \InvalidArgumentException('Replace only supports image files.');
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext === 'svg' || $mime === 'image/svg+xml') {
            throw new \InvalidArgumentException('SVG replace is not supported for URL-stable replace.');
        }

        $uploadsRoot = Bootstrap::path('uploads');
        if ($uploadsRoot === '') {
            throw new \RuntimeException('Upload path is not configured.');
        }

        $currentPath = (string) ($media['path'] ?? '');
        $subdir = dirname(str_replace('\\', '/', $currentPath));
        if ($subdir === '.' || $subdir === '') {
            $subdir = date('Y/m');
        }
        $uploadDir = $uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException('Cannot create upload folder for replace.');
        }

        $tmpName = 'replace-src-' . bin2hex(random_bytes(6)) . '.' . ($ext ?: 'bin');
        $tmpDest = $uploadDir . DIRECTORY_SEPARATOR . $tmpName;
        if (!move_uploaded_file($file['tmp_name'], $tmpDest)) {
            if (!@copy($file['tmp_name'], $tmpDest)) {
                throw new \RuntimeException('Failed to stage replacement file.');
            }
            @unlink($file['tmp_name']);
        }

        try {
            $meta = [];
            if (!empty($file['name'])) {
                $meta['original_name'] = (string) $file['name'];
            }
            return $this->commitReplaceFromSourceFile($id, $media, $tmpDest, $subdir, $uploadDir, $uploadsRoot, $meta);
        } finally {
            if (is_file($tmpDest)) {
                @unlink($tmpDest);
            }
        }
    }

    /**
     * Replace image from in-memory bytes (AI regenerate) while keeping public path / variants stable.
     * Does not change original_name / alt / description unless $meta overrides them.
     */
    public function replaceImageFromBinary(int $id, string $binary, string $mime = 'image/png', array $meta = []): ?array
    {
        if ($binary === '') {
            throw new \InvalidArgumentException('Empty image data');
        }
        $media = $this->find($id);
        if (!$media) {
            throw new \InvalidArgumentException('Media not found.');
        }
        $mime = strtolower(trim($mime));
        if ($mime === '' || strpos($mime, 'image/') !== 0) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->buffer($binary);
            $mime = (is_string($detected) && strpos($detected, 'image/') === 0) ? $detected : 'image/png';
        }
        if ($mime === 'image/svg+xml') {
            throw new \InvalidArgumentException('SVG replace is not supported for URL-stable replace.');
        }

        $extMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $ext = $extMap[$mime] ?? 'png';

        $uploadsRoot = Bootstrap::path('uploads');
        if ($uploadsRoot === '') {
            throw new \RuntimeException('Upload path is not configured.');
        }

        $currentPath = (string) ($media['path'] ?? '');
        $subdir = dirname(str_replace('\\', '/', $currentPath));
        if ($subdir === '.' || $subdir === '') {
            $subdir = date('Y/m');
        }
        $uploadDir = $uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException('Cannot create upload folder for replace.');
        }

        $tmpName = 'replace-src-' . bin2hex(random_bytes(6)) . '.' . $ext;
        $tmpDest = $uploadDir . DIRECTORY_SEPARATOR . $tmpName;
        if (@file_put_contents($tmpDest, $binary) === false) {
            throw new \RuntimeException('Failed to stage replacement bytes.');
        }

        try {
            return $this->commitReplaceFromSourceFile($id, $media, $tmpDest, $subdir, $uploadDir, $uploadsRoot, $meta);
        } finally {
            if (is_file($tmpDest)) {
                @unlink($tmpDest);
            }
        }
    }

    /**
     * Core URL-stable replace — overwrite bytes at the SAME public paths.
     * Paths in DB (path + variants[].path) stay unchanged so all site URLs keep working.
     * Writes via temp file + rename so a failed write never leaves a half-deleted asset.
     */
    private function commitReplaceFromSourceFile(
        int $id,
        array $media,
        string $sourceAbs,
        string $subdir,
        string $uploadDir,
        string $uploadsRoot,
        array $meta = []
    ): array {
        if (!is_file($sourceAbs)) {
            throw new \RuntimeException('Replacement source missing.');
        }
        if (!function_exists('imagewebp')) {
            throw new \RuntimeException('WebP conversion is required for image replace (enable GD imagewebp).');
        }

        $src = $this->loadImage($sourceAbs);
        if (!$src) {
            throw new \RuntimeException('Could not read replacement image (unsupported or corrupt).');
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($src);
            throw new \RuntimeException('Replacement image has invalid dimensions.');
        }

        $quality = (int) Bootstrap::config('media.quality', 85);
        $currentPath = ltrim(str_replace('\\', '/', (string) ($media['path'] ?? '')), '/');

        // Collect existing public targets: keep exact relative paths
        $oldVariants = is_array($media['variants'] ?? null) ? $media['variants'] : [];
        if (is_string($media['variants'] ?? null)) {
            $oldVariants = json_decode((string) $media['variants'], true) ?: [];
        }

        /** @var array<int, string> $targetsByWidth width => relative path */
        $targetsByWidth = [];
        foreach ($oldVariants as $v) {
            if (!is_array($v)) {
                continue;
            }
            $w = (int) ($v['width'] ?? 0);
            $p = ltrim(str_replace('\\', '/', (string) ($v['path'] ?? '')), '/');
            if ($w > 0 && $p !== '') {
                $targetsByWidth[$w] = $p;
            }
        }

        // Ensure primary path is included (match its width or treat as largest)
        if ($currentPath !== '') {
            $primaryMatched = false;
            foreach ($targetsByWidth as $w => $p) {
                if ($p === $currentPath) {
                    $primaryMatched = true;
                    break;
                }
            }
            if (!$primaryMatched) {
                // Use source width or max configured as key for primary-only assets
                $targetsByWidth[$srcW] = $currentPath;
            }
        }

        // If media had no variants yet, build the standard set using existing basename
        if ($targetsByWidth === []) {
            $baseName = pathinfo((string) ($media['filename'] ?? 'media'), PATHINFO_FILENAME);
            $baseName = preg_replace('/-(320|480|768|1200|\d+)$/i', '', (string) $baseName) ?: 'media';
            $sizes = Bootstrap::config('media.variants', [320, 480, 768, 1200]);
            if (!is_array($sizes)) {
                $sizes = [320, 480, 768, 1200];
            }
            foreach ($sizes as $targetW) {
                $targetW = (int) $targetW;
                if ($targetW < 1) {
                    continue;
                }
                $outW = $srcW <= $targetW ? $srcW : $targetW;
                $rel = $subdir . '/' . $baseName . '-' . $outW . '.webp';
                $targetsByWidth[$outW] = $rel;
            }
            if ($targetsByWidth === []) {
                $rel = $subdir . '/' . $baseName . '-' . $srcW . '.webp';
                $targetsByWidth[$srcW] = $rel;
            }
            if ($currentPath === '') {
                // pick largest as primary later
            }
        }

        // Sort widths ascending for processing; we'll pick largest as primary
        ksort($targetsByWidth, SORT_NUMERIC);

        $finalVariants = [];
        $writtenAbs = [];
        $errors = [];

        foreach ($targetsByWidth as $targetW => $relPath) {
            $relPath = ltrim(str_replace('\\', '/', $relPath), '/');
            $outW = $srcW <= $targetW ? $srcW : (int) $targetW;
            $outH = (int) max(1, (int) round($srcH * ($outW / $srcW)));

            $abs = $uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
            $dir = dirname($abs);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                $errors[] = 'Cannot create folder for ' . $relPath;
                continue;
            }

            // Render to a sibling temp file, then rename over the public path
            $tmpAbs = $abs . '.movatmp-' . bin2hex(random_bytes(4));

            $ok = false;
            if ($outW === $srcW && $outH === $srcH) {
                $ok = @imagewebp($src, $tmpAbs, $quality);
            } else {
                $resized = imagecreatetruecolor($outW, $outH);
                if ($resized) {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    imagecopyresampled($resized, $src, 0, 0, 0, 0, $outW, $outH, $srcW, $srcH);
                    $ok = @imagewebp($resized, $tmpAbs, $quality);
                    imagedestroy($resized);
                }
            }

            if (!$ok || !is_file($tmpAbs) || filesize($tmpAbs) < 32) {
                if (is_file($tmpAbs)) {
                    @unlink($tmpAbs);
                }
                $errors[] = 'Failed to encode ' . $outW . 'px variant';
                continue;
            }

            // Atomic-ish replace of the public file
            $replaced = false;
            if (is_file($abs)) {
                // Prefer rename over existing (works on Linux)
                if (@rename($tmpAbs, $abs)) {
                    $replaced = true;
                } elseif (@copy($tmpAbs, $abs)) {
                    @unlink($tmpAbs);
                    $replaced = true;
                }
            } else {
                if (@rename($tmpAbs, $abs)) {
                    $replaced = true;
                } elseif (@copy($tmpAbs, $abs)) {
                    @unlink($tmpAbs);
                    $replaced = true;
                }
            }
            if (!$replaced) {
                if (is_file($tmpAbs)) {
                    @unlink($tmpAbs);
                }
                $errors[] = 'Failed to overwrite ' . $relPath . ' (check permissions on mova-uploads)';
                continue;
            }

            // Verify the public file is non-empty webp
            clearstatcache(true, $abs);
            $sz = (int) (@filesize($abs) ?: 0);
            if ($sz < 32) {
                $errors[] = 'Overwrite produced empty file: ' . $relPath;
                continue;
            }

            $finalVariants[] = [
                'width'  => $outW,
                'height' => $outH,
                'path'   => $relPath,
                'size'   => $sz,
            ];
            $writtenAbs[$relPath] = $abs;
        }

        imagedestroy($src);

        if ($finalVariants === []) {
            $msg = 'Could not write any replacement variants.';
            if ($errors) {
                $msg .= ' ' . implode('; ', $errors);
            }
            throw new \RuntimeException($msg);
        }

        // De-dupe by path (same file may be targeted twice via width keys)
        $byPath = [];
        foreach ($finalVariants as $fv) {
            $byPath[$fv['path']] = $fv;
        }
        $finalVariants = array_values($byPath);
        usort($finalVariants, static function ($a, $b) {
            return ((int) ($b['width'] ?? 0)) <=> ((int) ($a['width'] ?? 0));
        });

        // Keep the original primary path whenever we wrote to it; else largest variant
        $storePath = $currentPath;
        if ($storePath === '' || !isset($writtenAbs[$storePath])) {
            $storePath = (string) ($finalVariants[0]['path'] ?? $currentPath);
        }
        $storeAbs = $uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storePath);
        $primaryMeta = $finalVariants[0];
        foreach ($finalVariants as $fv) {
            if (($fv['path'] ?? '') === $storePath) {
                $primaryMeta = $fv;
                break;
            }
        }

        $storeFilename = basename($storePath);
        $storeSize = (int) ($primaryMeta['size'] ?? (@filesize($storeAbs) ?: 0));
        $storeW = (int) ($primaryMeta['width'] ?? $srcW);
        $storeH = (int) ($primaryMeta['height'] ?? $srcH);
        $fileHash = is_file($storeAbs) ? (@hash_file('sha256', $storeAbs) ?: null) : null;

        // Path + variant paths intentionally unchanged (same public URLs)
        $update = [
            'filename'  => $storeFilename,
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'size'      => $storeSize,
            'width'     => $storeW,
            'height'    => $storeH,
            'path'      => $storePath,
            'variants'  => json_encode(array_values($finalVariants)),
        ];
        if ($fileHash) {
            $update['file_hash'] = $fileHash;
        }
        // Only update display name after a successful file write
        if (isset($meta['original_name']) && trim((string) $meta['original_name']) !== '') {
            $update['original_name'] = trim((string) $meta['original_name']);
        }
        if (array_key_exists('alt_text', $meta)) {
            $update['alt_text'] = (string) $meta['alt_text'];
        }

        Database::update('media', $update, 'id = :id', ['id' => $id]);
        $row = $this->find($id);
        if (!$row) {
            throw new \RuntimeException('Media row missing after replace.');
        }
        // Surface partial failures to the client (some sizes may have failed)
        if ($errors) {
            $row['_replace_warnings'] = $errors;
        }
        return $row;
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
