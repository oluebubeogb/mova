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

        return $this->find($id);
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

        return Database::delete('media', 'id = :id', ['id' => $id]) > 0;
    }

    public function url(array $media, ?int $width = null): string
    {
        $base = Bootstrap::baseUrl() . '/mova-uploads/';

        if (!empty($media['variants']) && is_array($media['variants'])) {
            if ($width) {
                foreach ($media['variants'] as $v) {
                    if (($v['width'] ?? 0) == $width) {
                        return $base . $v['path'];
                    }
                }
            }
            // Prefer largest variant when no width requested
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
        $defaultExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'pdf', 'mp4', 'webm', 'mp3', 'wav'];
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
        ];
        $allowedMimes = Bootstrap::config('media.allowed_mimes', $defaultMimes);
        if (!is_array($allowedMimes) || $allowedMimes === []) {
            $allowedMimes = $defaultMimes;
        }
        // Soft check: only enforce when we could detect a mime
        if ($mime !== '' && !in_array($mime, $allowedMimes, true)) {
            // Allow common jpeg alias
            if (!($mime === 'image/jpg' && in_array('image/jpeg', $allowedMimes, true))) {
                throw new \InvalidArgumentException('MIME type not allowed (' . $mime . ').');
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
        $sizes = Bootstrap::config('media.variants', [480, 768, 1200]);
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
