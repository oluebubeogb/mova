<?php
/**
 * Build the HQ section library from Mova\Theme\SectionLibrary.
 *
 *   php bin/build-sections.php
 *
 * Writes resources/templates/sections/<id>/{body.html,meta.json} and
 * resources/templates/manifest.json. Thumbnails: php bin/capture.php sections
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$root = dirname(__DIR__);
require $root . '/app/Core/Bootstrap.php';
\Mova\Core\Bootstrap::init();

use Mova\Theme\SectionLibrary;
use Mova\Theme\Sections;

$base = $root . '/resources/templates';
$secDir = $base . '/sections';
@mkdir($secDir, 0775, true);

$manifest = [
    'version' => 2,
    'shared_css' => '/assets/css/sections.css',
    'shared_js' => '/assets/js/sections.js',
    'categories' => SectionLibrary::CATEGORIES,
    'templates' => [],
];
$keep = [];
foreach (SectionLibrary::all() as $i => $e) {
    $id = $e['id'];
    $dir = $secDir . '/' . $id;
    @mkdir($dir, 0775, true);
    $html = '<div class="mx-page">' . "\n" . Sections::render($e['spec']) . "\n</div>\n";
    file_put_contents($dir . '/body.html', $html);
    $meta = [
        'name' => $e['name'],
        'category' => $e['category'],
        'tags' => $e['tags'],
        'description' => $e['description'],
        'preview' => '/assets/templates/previews/' . $id . '.png',
        'type' => $e['spec']['type'],
        'slots' => ['eyebrow', 'title', 'lead', 'items', 'buttons', 'images'],
        'constraints' => [
            'Keep the root <section class="mx mx-…"> and inner <div class="mx-in"> wrappers.',
            'Colours, fonts and radius come from theme CSS variables — do not hard-code colours.',
            'Keep data-reveal attributes; motion is disabled automatically for reduced-motion users.',
            'Use inline SVG/gradient art or uploaded images only — no external image hosts.',
        ],
        'spec' => $e['spec'],
    ];
    file_put_contents($dir . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    $manifest['templates'][] = ['id' => $id, 'path' => 'sections/' . $id, 'category' => $e['category'], 'order' => $i];
    $keep[$id] = true;
}
// Remove stale section dirs and the Phase 2 pricing-only seeds (ids are kept above).
foreach (glob($secDir . '/*', GLOB_ONLYDIR) ?: [] as $d) {
    if (!isset($keep[basename($d)])) {
        array_map('unlink', glob($d . '/*') ?: []);
        @rmdir($d);
    }
}
if (is_dir($base . '/pricing')) {
    foreach (glob($base . '/pricing/*', GLOB_ONLYDIR) ?: [] as $d) {
        array_map('unlink', glob($d . '/*') ?: []);
        @rmdir($d);
    }
    @rmdir($base . '/pricing');
}
file_put_contents($base . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo count($manifest['templates']) . " sections written to resources/templates/sections\n";
