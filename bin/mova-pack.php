<?php
/**
 * Work with .movapack files offline (no database needed for unpack/repack).
 *
 *   php bin/mova-pack.php info   site.movapack          # manifest summary + features used
 *   php bin/mova-pack.php verify site.movapack          # full validation (checksums, safety, version)
 *   php bin/mova-pack.php unpack site.movapack ./dir    # safe extract for editing
 *   php bin/mova-pack.php repack ./dir out.movapack     # re-zip and refresh sha256/size in the manifest
 *
 * Edit data/*.json and swap files under media/ keeping ids and slugs; then repack.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
\Mova\Core\Bootstrap::init();

use Mova\Backup\SitePack;
use Mova\Backup\SitePackReader;

[$cmd, $a, $b] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? ''];
$fail = static function (string $m, int $code = 1): never {
    fwrite(STDERR, $m . "\n");
    exit($code);
};
switch ($cmd) {
    case 'info':
    case 'verify':
        $r = new SitePackReader($a);
        $ok = $cmd === 'verify' ? $r->validate(false) : $r->open();
        $m = $r->manifest();
        if ($m) {
            echo 'Site:      ', $m['site']['name'] ?? '?', ' (', $m['site']['host'] ?? '', ")\n";
            echo 'Exported:  ', $m['exported_at'] ?? '?', ' UTC with Mova ', $m['mova_version'] ?? '?', ' (needs ≥ ', $m['min_mova_version'] ?? '?', ")\n";
            echo 'Groups:    ', implode(', ', (array) ($m['groups'] ?? [])), "\n";
            foreach ((array) ($m['counts'] ?? []) as $t => $n) {
                echo '  ', str_pad($t, 22), $n, "\n";
            }
            $f = (array) ($m['features'] ?? []);
            echo 'Theme:     ', $f['theme'] ?? '', '   Kit: ', $f['kit'] ?? '', "\n";
            foreach (['sections', 'section_styles', 'templates', 'components', 'shortcodes', 'assemblies_used'] as $k) {
                if (!empty($f[$k])) {
                    echo str_pad(ucfirst(str_replace('_', ' ', $k)) . ':', 11), implode(', ', array_map(static fn ($n, $c) => "{$n}×{$c}", array_keys($f[$k]), $f[$k])), "\n";
                }
            }
            echo 'Custom CSS: ', SitePack::fmtBytes((int) ($f['custom_css_bytes'] ?? 0)), '   Plugins: ', implode(', ', array_map(static fn ($p) => $p['slug'] . ' ' . $p['version'], (array) ($f['plugins'] ?? []))), "\n";
        }
        foreach ($r->errors as $e) {
            fwrite(STDERR, 'error: ' . $e . "\n");
        }
        echo $ok ? ($cmd === 'verify' ? "OK — pack is valid.\n" : '') : '';
        exit($ok ? 0 : 1);

    case 'unpack':
        if ($b === '') {
            $fail('Usage: unpack <pack> <dir>', 2);
        }
        $r = new SitePackReader($a);
        if (!$r->validate(false)) {
            $fail(implode("\n", $r->errors));
        }
        @mkdir($b . '/data', 0755, true);
        file_put_contents($b . '/manifest.json', json_encode($r->manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $n = 0;
        foreach (array_keys((array) $r->manifest()['files']) as $name) {
            $r->extractTo($name, rtrim($b, '/') . '/' . $name);
            $n++;
        }
        echo "Unpacked $n files to $b\n";
        exit(0);

    case 'repack':
        if ($b === '' || !is_file($a . '/manifest.json')) {
            $fail('Usage: repack <dir with manifest.json> <out.movapack>', 2);
        }
        $m = json_decode((string) file_get_contents($a . '/manifest.json'), true);
        if (!is_array($m) || ($m['format'] ?? '') !== SitePack::FORMAT) {
            $fail('manifest.json is not a site pack manifest.');
        }
        $files = [];
        $root = rtrim(realpath($a) ?: $a, '/');
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile() || $f->isLink()) {
                continue;
            }
            $rel = substr($f->getPathname(), strlen($root) + 1);
            if ($rel === 'manifest.json' || basename($rel) === '.DS_Store') {
                continue;
            }
            if (SitePackReader::badEntryName($rel) !== null || !(str_starts_with($rel, 'data/') || (str_starts_with($rel, 'media/') && SitePack::mediaExtAllowed($rel)))) {
                fwrite(STDERR, "skipped: $rel\n");
                continue;
            }
            if (str_starts_with($rel, 'data/')) {
                $j = json_decode((string) file_get_contents($f->getPathname()), true);
                if ($j === null) {
                    $fail("$rel is not valid JSON — fix it before repacking.");
                }
                // Keep manifest row counts in step with edited data (rows added or removed by hand).
                $tbl = substr($rel, 5, -5);
                if (is_array($j) && isset($j['rows']) && is_array($j['rows']) && isset($m['counts']) && is_array($m['counts']) && array_key_exists($tbl, $m['counts'])) {
                    $m['counts'][$tbl] = count($j['rows']);
                }
            }
            $files[$rel] = ['sha256' => hash_file('sha256', $f->getPathname()), 'size' => filesize($f->getPathname())];
        }
        ksort($files);
        $m['files'] = $files;
        $m['repacked_at'] = gmdate('Y-m-d\TH:i:s\Z');
        $zip = new ZipArchive();
        if ($zip->open($b . '.part', ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $fail('Cannot write ' . $b);
        }
        $zip->addFromString('manifest.json', json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        foreach ($files as $rel => $_) {
            $zip->addFile($root . '/' . $rel, $rel);
        }
        $zip->close();
        rename($b . '.part', $b);
        echo 'Wrote ', $b, ' (', count($files), " files)\n";
        exit(0);
}
$fail("Usage: php bin/mova-pack.php info|verify|unpack|repack …", 2);
