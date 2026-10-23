<?php
/**
 * Generate real screenshots with headless Chromium (no Node, no npm):
 *   - section thumbnails  → public/assets/templates/previews/<id>.png
 *   - kit previews        → public/assets/kits/<kit>/preview.png, preview-mobile.png
 *   - theme mockups       → public/assets/themes/<slug>/desktop.png, mobile.png, card.png (device frames via GD)
 *
 * Usage:
 *   php bin/capture.php [all|sections|kits|themes] [--base=http://127.0.0.1:8080] [--only=id,id] [--chrome=/path/to/chrome]
 * Without --base a temporary PHP built-in server is started on a free port (the site must be installed).
 * Theme mockups use a kit demo page (or --theme-page=/some/path for your own content).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$what = 'all';
$opt = [];
foreach ($args as $a) {
    if (preg_match('/^--([a-z-]+)=(.*)$/', $a, $m)) {
        $opt[$m[1]] = $m[2];
    } elseif ($a[0] !== '-') {
        $what = $a;
    }
}
require $root . '/app/Core/Bootstrap.php';
\Mova\Core\Bootstrap::init();
require_once $root . '/mova-plugins/mova-setup-wizard/src/load.php';
if (!\Mova\Core\Schema::isInstalled()) {
    fwrite(STDERR, "Install Mova first (previews render through the site).\n");
    exit(1);
}
if (!function_exists('imagecreatefrompng')) {
    fwrite(STDERR, "PHP GD with PNG support is required.\n");
    exit(1);
}

$chrome = $opt['chrome'] ?? getenv('CHROME_BIN') ?: '';
if ($chrome === '') {
    foreach (['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser', 'chrome'] as $c) {
        $p = trim((string) shell_exec('command -v ' . escapeshellarg($c) . ' 2>/dev/null'));
        if ($p !== '') {
            $chrome = $p;
            break;
        }
    }
}
if ($chrome === '') {
    fwrite(STDERR, "Headless Chrome/Chromium not found. Install it or pass --chrome=/path.\n");
    exit(1);
}

// ---- server ----
$server = null;
$base = rtrim((string) ($opt['base'] ?? ''), '/');
if ($base === '') {
    $sock = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr((string) stream_socket_get_name($sock, false), ':'), 1);
    fclose($sock);
    $env = getenv();
    $env['PHP_CLI_SERVER_WORKERS'] = '4';
    $server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/bin/dev-router.php'],
        [['file', '/dev/null', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes, $root, $env);
    $base = 'http://127.0.0.1:' . $port;
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    for ($i = 0; $i < 50; $i++) {
        usleep(100000);
        if (@file_get_contents($base . '/robots.txt', false, $ctx) !== false) {
            break;
        }
    }
    register_shutdown_function(static function () use ($server) {
        $st = proc_get_status($server);
        if (!empty($st['pid'])) {
            exec('pkill -P ' . (int) $st['pid'] . ' 2>/dev/null');
        }
        proc_terminate($server);
    });
}
echo "Capturing from $base with $chrome\n";

$tmp = sys_get_temp_dir() . '/mova-capture-' . getmypid();
@mkdir($tmp, 0775, true);

/** Viewport screenshot via Chrome CLI. Returns a GD image. */
function shoot(string $url, int $w, int $h, float $scale = 1.0): ?\GdImage
{
    global $chrome, $tmp;
    $out = $tmp . '/s' . md5($url . $w . $h . $scale) . '.png';
    @unlink($out);
    $sep = str_contains($url, '?') ? '&' : '?';
    $cmd = sprintf(
        '%s --headless=new --no-sandbox --disable-gpu --hide-scrollbars --mute-audio --no-first-run --user-data-dir=%s --force-device-scale-factor=%s --window-size=%d,%d --virtual-time-budget=4000 --screenshot=%s %s 2>/dev/null',
        escapeshellarg($chrome), escapeshellarg($tmp . '/profile'), $scale, $w, $h, escapeshellarg($out), escapeshellarg($url . $sep . 'mx_static=1')
    );
    exec($cmd);
    if (!is_file($out)) {
        fwrite(STDERR, "  ! failed: $url\n");
        return null;
    }
    $im = imagecreatefrompng($out);
    return $im ?: null;
}

function savePng(\GdImage $im, string $file): void
{
    @mkdir(dirname($file), 0775, true);
    imagesavealpha($im, true);
    imagepng($im, $file, 8);
    echo '  → ' . str_replace(dirname(__DIR__) . '/', '', $file) . "\n";
}

function resized(\GdImage $src, int $w, int $h): \GdImage
{
    $dst = imagecreatetruecolor($w, $h);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
    return $dst;
}

/** Height of the content: trims trailing rows that match the bottom row colour. */
function contentHeight(\GdImage $im): int
{
    $w = imagesx($im);
    $h = imagesy($im);
    $ref = imagecolorat($im, (int) ($w / 2), $h - 1);
    for ($y = $h - 1; $y > 0; $y--) {
        for ($x = 0; $x < $w; $x += 7) {
            if (imagecolorat($im, $x, $y) !== $ref) {
                return min($h, $y + 2);
            }
        }
    }
    return $h;
}

function hexColor(\GdImage $im, string $hex, int $alpha = 0): int
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    return imagecolorallocatealpha($im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $alpha);
}

function roundRect(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $col): void
{
    $r = max(0, min($r, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2)));
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $col);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $col);
    foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
        imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $col);
    }
}

/** Copy $src into a rounded-corner window of $dst (corner pixels masked with $bgCol). */
function pasteRounded(\GdImage $dst, \GdImage $src, int $x, int $y, int $w, int $h, int $r, bool $topOnly = false): void
{
    $tmp = imagecreatetruecolor($w, $h);
    imagealphablending($tmp, false);
    imagecopyresampled($tmp, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
    $clear = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
    $corners = $topOnly ? [[0, 0, 1, 1], [$w - 1, 0, -1, 1]] : [[0, 0, 1, 1], [$w - 1, 0, -1, 1], [0, $h - 1, 1, -1], [$w - 1, $h - 1, -1, -1]];
    foreach ($corners as [$cx, $cy, $dx, $dy]) {
        for ($i = 0; $i < $r; $i++) {
            for ($j = 0; $j < $r; $j++) {
                $ddx = $r - $i - 0.5;
                $ddy = $r - $j - 0.5;
                if ($ddx * $ddx + $ddy * $ddy > $r * $r) {
                    imagesetpixel($tmp, $cx + $dx * $i, $cy + $dy * $j, $clear);
                }
            }
        }
    }
    imagealphablending($dst, true);
    imagecopy($dst, $tmp, $x, $y, 0, 0, $w, $h);
}

function shadow(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $spread = 28, int $dy = 18): void
{
    // Soft drop shadow: draw an opaque mask at 1/4 scale, blur it, then map luminance to alpha.
    $W = imagesx($im);
    $H = imagesy($im);
    $s = 4;
    $w = max(1, intdiv($W, $s));
    $h = max(1, intdiv($H, $s));
    $m = imagecreatetruecolor($w, $h);
    imagefill($m, 0, 0, imagecolorallocate($m, 255, 255, 255));
    roundRect($m, intdiv($x1, $s), intdiv($y1 + $dy, $s), intdiv($x2, $s), intdiv($y2 + $dy, $s), intdiv($r, $s), imagecolorallocate($m, 0, 0, 0));
    $passes = max(2, intdiv($spread, 3));
    for ($i = 0; $i < $passes; $i++) {
        imagefilter($m, IMG_FILTER_GAUSSIAN_BLUR);
    }
    $sh = imagecreatetruecolor($w, $h);
    imagealphablending($sh, false);
    imagesavealpha($sh, true);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $d = 255 - (imagecolorat($m, $x, $y) & 0xFF); // 0..255 darkness
            $alpha = 127 - (int) round($d / 255 * 34);     // max ~27% opacity
            imagesetpixel($sh, $x, $y, imagecolorallocatealpha($sh, 15, 23, 42, $alpha));
        }
    }
    imagealphablending($im, true);
    imagecopyresampled($im, $sh, 0, 0, 0, 0, $W, $H, $w, $h);
}

function canvas(int $w, int $h): \GdImage
{
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    return $im;
}

/** Browser window mockup around a desktop screenshot. All sizes at 2x then downsampled for smooth edges. */
function browserFrame(\GdImage $shot, int $outW, string $dark = '#0f172a'): \GdImage
{
    $k = 2;
    $W = $outW * $k;
    $innerW = $W - 2 * 40 * $k / 2;
    $innerH = (int) round($innerW * imagesy($shot) / imagesx($shot));
    $bar = 34 * $k;
    $pad = 20 * $k;
    $H = $innerH + $bar + 2 * $pad + 30 * $k;
    $im = canvas($W, $H);
    $x1 = $pad;
    $y1 = $pad;
    $x2 = $W - $pad;
    $y2 = $pad + $bar + $innerH;
    shadow($im, $x1, $y1, $x2, $y2, 14 * $k, 22 * $k, 10 * $k);
    roundRect($im, $x1, $y1, $x2, $y2, 14 * $k, hexColor($im, '#e7e9ee'));
    roundRect($im, $x1 + 2, $y1 + 2, $x2 - 2, $y1 + $bar + 20, 13 * $k, hexColor($im, '#f6f7f9'));
    foreach (['#ff5f57', '#febc2e', '#28c840'] as $i => $c) {
        imagefilledellipse($im, $x1 + (18 + $i * 16) * $k, $y1 + (int) ($bar / 2), 10 * $k, 10 * $k, hexColor($im, $c));
    }
    $uw = (int) (($x2 - $x1) * 0.42);
    $ux = (int) (($x1 + $x2 - $uw) / 2);
    roundRect($im, $ux, $y1 + 8 * $k, $ux + $uw, $y1 + $bar - 8 * $k, 9 * $k, hexColor($im, '#ffffff'));
    $innerX = $x1 + 2;
    pasteRounded($im, $shot, $innerX, $y1 + $bar, $x2 - $x1 - 3, $innerH, 13 * $k);
    // cover top rounding of the shot (window bar sits above)
    $out = resized($im, $outW, (int) round($H / $k));
    return $out;
}

/** Phone mockup around a mobile screenshot. */
function phoneFrame(\GdImage $shot, int $outW): \GdImage
{
    $k = 2;
    $W = $outW * $k;
    $pad = 26 * $k;
    $bez = 12 * $k;
    $innerW = $W - 2 * $pad - 2 * $bez;
    $innerH = (int) round($innerW * imagesy($shot) / imagesx($shot));
    $H = $innerH + 2 * $bez + 2 * $pad + 10 * $k;
    $im = canvas($W, $H);
    $x1 = $pad;
    $y1 = $pad;
    $x2 = $W - $pad;
    $y2 = $pad + $innerH + 2 * $bez;
    shadow($im, $x1, $y1, $x2, $y2, 46 * $k, 22 * $k, 10 * $k);
    roundRect($im, $x1, $y1, $x2, $y2, 46 * $k, hexColor($im, '#111318'));
    roundRect($im, $x1 + 3 * $k, $y1 + 3 * $k, $x2 - 3 * $k, $y2 - 3 * $k, 43 * $k, hexColor($im, '#23262e'));
    roundRect($im, $x1 + $bez - 2, $y1 + $bez - 2, $x2 - $bez + 2, $y2 - $bez + 2, 36 * $k, hexColor($im, '#000000'));
    pasteRounded($im, $shot, $x1 + $bez, $y1 + $bez, $innerW, $innerH, 34 * $k);
    $iw = 92 * $k;
    roundRect($im, (int) ($W / 2 - $iw / 2), $y1 + $bez + 9 * $k, (int) ($W / 2 + $iw / 2), $y1 + $bez + 35 * $k, 13 * $k, hexColor($im, '#000000'));
    return resized($im, $outW, (int) round($H / $k));
}

/** Card: gradient backdrop + browser + overlapping phone. */
function themeCard(\GdImage $desk, \GdImage $mob, string $p, string $a, int $W = 1200, int $H = 760): \GdImage
{
    $im = imagecreatetruecolor($W, $H);
    [$r1, $g1, $b1] = sscanf($p, '#%02x%02x%02x');
    [$r2, $g2, $b2] = sscanf($a, '#%02x%02x%02x');
    for ($y = 0; $y < $H; $y++) {
        for ($x = 0; $x < $W; $x += 4) {
            $t = min(1, max(0, ($x / $W) * 0.6 + ($y / $H) * 0.4));
            $mix = static fn ($c1, $c2) => (int) round((($c1 + ($c2 - $c1) * $t) * 0.22) + 245 * 0.78);
            $col = imagecolorallocate($im, $mix($r1, $r2), $mix($g1, $g2), $mix($b1, $b2));
            imagefilledrectangle($im, $x, $y, $x + 3, $y, $col);
        }
    }
    imagealphablending($im, true);
    $bf = browserFrame($desk, 980);
    imagecopy($im, $bf, 18, (int) max(10, ($H - imagesy($bf)) / 2 - 6), 0, 0, imagesx($bf), imagesy($bf));
    $pf = phoneFrame($mob, 250);
    $ph = imagesy($pf);
    $scale = min(1, ($H - 40) / $ph);
    $pf2 = resized($pf, (int) (250 * $scale), (int) ($ph * $scale));
    imagecopy($im, $pf2, $W - imagesx($pf2) - 26, (int) (($H - imagesy($pf2)) / 2 + 14), 0, 0, imagesx($pf2), imagesy($pf2));
    return $im;
}

$only = array_filter(explode(',', (string) ($opt['only'] ?? '')));

// ---- sections ----
if ($what === 'all' || $what === 'sections') {
    echo "Sections\n";
    foreach (\Mova\Theme\SectionLibrary::all() as $s) {
        if ($only && !in_array($s['id'], $only, true)) {
            continue;
        }
        echo ' ' . $s['id'] . "\n";
        $im = shoot($base . '/_preview/section/' . rawurlencode($s['id']), 1280, 960, 1);
        if (!$im) {
            continue;
        }
        $ch = max(400, min(contentHeight($im), 960));
        $h = (int) min($ch, 1280 / 1.6 * 1.25); // up to 1280x1000 crop, keeps the headline
        $crop = imagecrop($im, ['x' => 0, 'y' => 0, 'width' => 1280, 'height' => $h]);
        $th = canvas(800, 500);
        $bg = imagecolorat($im, 2, max(0, $h - 2));
        imagefilledrectangle($th, 0, 0, 800, 500, $bg);
        $sw = 800;
        $sh = (int) round($h * 800 / 1280);
        if ($sh > 500) {
            $sh = 500;
            $crop = imagecrop($crop, ['x' => 0, 'y' => 0, 'width' => 1280, 'height' => 800]);
        }
        imagecopyresampled($th, $crop, 0, (int) ((500 - $sh) / 2), 0, 0, $sw, $sh, 1280, imagesy($crop));
        savePng($th, $root . '/public/assets/templates/previews/' . $s['id'] . '.png');
    }
    // Refresh the lightweight card thumbnails used by the HQ section library.
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/make-section-thumbs.php'));
}

// ---- kits ----
if ($what === 'all' || $what === 'kits') {
    echo "Kits\n";
    foreach (\MovaSetupWizard\KitRepository::list() as $k) {
        if ($only && !in_array($k['id'], $only, true)) {
            continue;
        }
        echo ' ' . $k['id'] . "\n";
        $url = $base . '/_preview/kit/' . rawurlencode($k['id']) . '/home?theme=presence';
        $d = shoot($url, 1440, 1100, 1);
        if ($d) {
            savePng(resized($d, 960, 733), $root . '/public/assets/kits/' . $k['id'] . '/preview.png');
        }
        $m = shoot($url, 390, 844, 1);
        if ($m) {
            savePng($m, $root . '/public/assets/kits/' . $k['id'] . '/preview-mobile.png');
        }
    }
}

// ---- themes ----
if ($what === 'all' || $what === 'themes') {
    echo "Themes\n";
    $mgr = new \Mova\Theme\ThemeManager();
    $demoKit = ['presence' => 'basic-modern', 'default' => 'org-classic', 'documentation' => 'school-compact', 'lengthy' => 'school-classic'];
    foreach ($mgr->all() as $t) {
        $slug = (string) ($t['slug'] ?? '');
        if ($slug === '' || ($only && !in_array($slug, $only, true))) {
            continue;
        }
        echo ' ' . $slug . "\n";
        $page = (string) ($opt['theme-page'] ?? '');
        $kitId = $demoKit[$slug] ?? 'basic-modern';
        $url = $page !== ''
            ? $base . $page . (str_contains($page, '?') ? '&' : '?') . \Mova\Theme\ThemeManager::signedQuery(['mova_theme' => $slug])
            : $base . '/_preview/kit/' . $kitId . '/home?font_pair=theme&theme=' . rawurlencode($slug);
        $d = shoot($url, 1440, 900, 1);
        $m = shoot($url, 390, 844, 2);
        if (!$d || !$m) {
            continue;
        }
        $dir = $root . '/public/assets/themes/' . $slug;
        savePng(browserFrame($d, 1200), $dir . '/desktop.png');
        savePng(phoneFrame(resized($m, 390, 844), 420), $dir . '/mobile.png');
        $kit = \MovaSetupWizard\KitRepository::load($kitId);
        $pal = (array) ($kit['palette'] ?? []);
        savePng(themeCard($d, resized($m, 390, 844), (string) ($pal['primary'] ?? '#2563eb'), (string) ($pal['accent'] ?? '#7c3aed')), $dir . '/card.png');
    }
}

exec('rm -rf ' . escapeshellarg($tmp));
echo "Done.\n";
