<?php
/**
 * Re-generate auto excerpts with the Phase 5 spacing fix.
 *
 *   php bin/regenerate-excerpts.php            # dry run: list what would change
 *   php bin/regenerate-excerpts.php --apply    # write the fixed excerpts
 *
 * Only rows whose excerpt is exactly what the old algorithm produced from the body
 * ("HelloWorld" glued text) are touched. Hand-written excerpts are never changed.
 * Safe to run repeatedly; no daemon, works on shared hosting.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
require dirname(__DIR__) . '/app/Core/Bootstrap.php';
\Mova\Core\Bootstrap::init();

use Mova\Content\ContentRepository;
use Mova\Core\Database;

if (!\Mova\Core\Schema::isInstalled()) {
    fwrite(STDERR, "Mova is not installed.\n");
    exit(1);
}

$apply = in_array('--apply', $argv, true);
$rows = Database::fetchAll("SELECT id, title, excerpt, body FROM content WHERE status != 'trash' AND excerpt IS NOT NULL AND excerpt != ''");
$changed = 0;
foreach ($rows as $row) {
    $excerpt = (string) $row['excerpt'];
    $body = (string) $row['body'];
    if (!ContentRepository::isLegacyAutoExcerpt($excerpt, $body)) {
        continue;
    }
    $new = ContentRepository::excerptFromBody($body);
    $changed++;
    printf("#%d %s\n  old: %s\n  new: %s\n", (int) $row['id'], (string) $row['title'], mb_substr($excerpt, 0, 90), mb_substr($new, 0, 90));
    if ($apply) {
        Database::update('content', ['excerpt' => $new, 'updated_at' => date('c')], 'id = :id', ['id' => (int) $row['id']]);
        try {
            \Mova\Hq\SearchIndex::syncContent((int) $row['id']);
        } catch (\Throwable $e) {
        }
    }
}
printf("%d of %d excerpt(s) %s.\n", $changed, count($rows), $apply ? 'regenerated' : 'would be regenerated (dry run; add --apply)');
if ($apply && $changed > 0) {
    try {
        \Mova\Cache\PageCache::flush();
    } catch (\Throwable $e) {
    }
}
