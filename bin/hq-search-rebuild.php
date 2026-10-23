<?php
/**
 * Rebuild the HQ search index from the command line.
 *   php bin/hq-search-rebuild.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
\Mova\Core\Bootstrap::init();

if (!\Mova\Core\Schema::isInstalled()) {
    fwrite(STDERR, "Mova is not installed yet.\n");
    exit(1);
}
$t0 = microtime(true);
$n = \Mova\Hq\SearchIndex::rebuild();
printf("HQ search index rebuilt: %d rows, engine=%s, %.1f ms\n", $n, \Mova\Hq\SearchIndex::engine(), (microtime(true) - $t0) * 1000);
