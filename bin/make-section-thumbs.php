<?php
/**
 * Build lightweight JPEG thumbnails for the section library cards.
 *
 *   php bin/make-section-thumbs.php
 *
 * Reads  public/assets/templates/previews/<id>.png  (800×500, from bin/capture.php)
 * Writes public/assets/templates/previews/thumbs/<id>.jpg (640×400) when GD has JPEG,
 *        otherwise thumbs/<id>.png (640×400, 256-colour palette).
 *
 * The HQ section library and pickers use the thumb when present so every card
 * paints immediately (no lazy-load blanks), falling back to the PNG.
 * Requires only GD with PNG (JPEG optional) — available on standard shared hosting.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$src = $root . '/public/assets/templates/previews';
$dst = $src . '/thumbs';
$jpeg = function_exists('imagejpeg') && (imagetypes() & IMG_JPG);
if (!function_exists('imagecreatefrompng')) {
    fwrite(STDERR, "GD with PNG support is required.\n");
    exit(1);
}
if (!is_dir($dst) && !mkdir($dst, 0755, true) && !is_dir($dst)) {
    fwrite(STDERR, "Cannot create {$dst}\n");
    exit(1);
}
$n = 0;
foreach (glob($src . '/*.png') ?: [] as $png) {
    $id = basename($png, '.png');
    $out = $dst . '/' . $id . ($jpeg ? '.jpg' : '.png');
    if (is_file($out) && filemtime($out) >= filemtime($png)) {
        continue;
    }
    $im = @imagecreatefrompng($png);
    if (!$im) {
        fwrite(STDERR, "skip {$id}: unreadable\n");
        continue;
    }
    [$w, $h] = [imagesx($im), imagesy($im)];
    $tw = 640;
    $th = (int) round($h * $tw / max(1, $w));
    $t = imagecreatetruecolor($tw, $th);
    imagefill($t, 0, 0, imagecolorallocate($t, 255, 255, 255));
    imagecopyresampled($t, $im, 0, 0, 0, 0, $tw, $th, $w, $h);
    if ($jpeg) {
        imageinterlace($t, true);
        imagejpeg($t, $out, 80);
    } else {
        imagetruecolortopalette($t, true, 256);
        imagepng($t, $out, 9);
    }
    imagedestroy($im);
    imagedestroy($t);
    $n++;
    echo "thumb " . basename($out) . " (" . round(filesize($out) / 1024) . " KB)\n";
}
echo "Done: {$n} thumbnail(s) written.\n";
