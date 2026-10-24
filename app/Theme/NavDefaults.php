<?php

declare(strict_types=1);

namespace Mova\Theme;

use Mova\Core\Database;

/**
 * Default site navigation used until the owner saves their own (Design → Layout).
 * Only links to pages that actually exist, so a fresh install never shows dead links.
 */
final class NavDefaults
{
    private static ?string $cache = null;

    public static function raw(): string
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $lines = ['Home|/'];
        try {
            $rows = Database::fetchAll(
                "SELECT slug, title FROM content WHERE status = 'published' AND slug IN ('about','about-us','services','pricing','contact','contact-us')"
            );
            $bySlug = [];
            foreach ($rows as $r) {
                $bySlug[(string) $r['slug']] = (string) $r['title'];
            }
            foreach ([['about', 'about-us', 'About'], ['services', null, 'Services'], ['pricing', null, 'Pricing'], ['contact', 'contact-us', 'Contact']] as [$a, $b, $label]) {
                $slug = isset($bySlug[$a]) ? $a : (($b !== null && isset($bySlug[$b])) ? $b : null);
                if ($slug !== null) {
                    $lines[] = $label . '|/' . $slug;
                }
            }
            $blog = Database::fetch("SELECT id FROM blogs WHERE slug = 'blog' AND status = 'published' LIMIT 1");
            if ($blog) {
                $lines[] = 'Blog|/blog';
            }
        } catch (\Throwable $e) {
            // not installed / table missing → minimal nav
        }
        $lines[] = 'Search|/search';
        return self::$cache = implode("\n", $lines);
    }
}
