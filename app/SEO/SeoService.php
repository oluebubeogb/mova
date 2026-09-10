<?php
/**
 * Mova CMS - SEO Service
 */

namespace Mova\SEO;

use Mova\Core\Bootstrap;
use Mova\Core\Database;

class SeoService
{
    public function renderHead(?array $content = null, array $overrides = []): string
    {
        $siteName = $this->setting('site_name', Bootstrap::config('app_name', 'Mova'));
        $separator = Bootstrap::config('seo.title_separator', ' — ');

        // Prefer explicit SEO title, then post title, then site name (empty strings ignored)
        $seoTitle = trim((string) ($overrides['title'] ?? ''));
        if ($seoTitle === '' && $content) {
            $seoTitle = trim((string) ($content['meta']['seo_title'] ?? ''));
        }
        $postTitle = $content ? trim((string) ($content['title'] ?? '')) : '';
        if ($seoTitle !== '') {
            $title = $seoTitle;
        } elseif ($postTitle !== '') {
            $title = $postTitle;
        } else {
            $title = $siteName;
        }

        if ($content && $postTitle !== '' && $title !== $siteName) {
            // "Post name — Site name"
            $title = $title . $separator . $siteName;
        }

        $description = $overrides['description'] ?? null;
        if ($description === null && $content) {
            $md = trim((string) ($content['meta']['meta_description'] ?? ''));
            $ex = trim((string) ($content['excerpt'] ?? ''));
            if ($md !== '') {
                $description = $md;
            } elseif ($ex !== '') {
                $description = $ex;
            } elseif (!empty($content['body'])) {
                $description = \Mova\Content\ContentRepository::excerptFromBody((string) $content['body']);
            }
        }
        if ($description === null || $description === '') {
            $description = $this->setting('site_description', '');
        }

        $canonical = $overrides['canonical']
            ?? ($content['canonical_url'] ?? null)
            ?? ($content ? Bootstrap::baseUrl() . '/' . $content['slug'] : Bootstrap::baseUrl() . '/');

        $robots = $overrides['robots']
            ?? ($content['meta']['robots'] ?? null)
            ?? Bootstrap::config('seo.default_robots', 'index, follow');

        $ogTitle = ($content['meta']['og_title'] ?? null) ?: ($content['title'] ?? $title);
        $ogDesc  = ($content['meta']['og_description'] ?? null) ?: $description;
        $ogImage = ($content['meta']['og_image'] ?? null) ?: ($content['featured_image'] ?? $this->setting('default_og_image', ''));

        $html = [];
        $html[] = '<title>' . $this->e($title) . '</title>';
        $html[] = '<meta name="description" content="' . $this->e($description) . '">';
        $html[] = '<link rel="canonical" href="' . $this->e($canonical) . '">';
        $html[] = '<meta name="robots" content="' . $this->e($robots) . '">';

        // Open Graph
        $html[] = '<meta property="og:type" content="' . ($content && $content['type'] === 'article' ? 'article' : 'website') . '">';
        $html[] = '<meta property="og:title" content="' . $this->e($ogTitle) . '">';
        $html[] = '<meta property="og:description" content="' . $this->e($ogDesc) . '">';
        $html[] = '<meta property="og:url" content="' . $this->e($canonical) . '">';
        if ($ogImage) {
            $html[] = '<meta property="og:image" content="' . $this->e($this->absoluteUrl($ogImage)) . '">';
        }
        $html[] = '<meta property="og:site_name" content="' . $this->e($siteName) . '">';

        // Twitter
        $html[] = '<meta name="twitter:card" content="summary_large_image">';
        $html[] = '<meta name="twitter:title" content="' . $this->e($ogTitle) . '">';
        $html[] = '<meta name="twitter:description" content="' . $this->e($ogDesc) . '">';
        if ($ogImage) {
            $html[] = '<meta name="twitter:image" content="' . $this->e($this->absoluteUrl($ogImage)) . '">';
        }

        // JSON-LD
        $schema = $this->buildSchema($content);
        if ($schema) {
            $html[] = '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
        }

        return implode("\n    ", $html);
    }

    public function buildSchema(?array $content): ?array
    {
        if (!$content) {
            return [
                '@context' => 'https://schema.org',
                '@type'    => 'WebSite',
                'name'     => $this->setting('site_name', 'Mova'),
                'url'      => Bootstrap::baseUrl(),
            ];
        }

        $typeMap = [
            'article'       => 'Article',
            'page'          => 'WebPage',
            'guide'         => 'Article',
            'documentation' => 'TechArticle',
            'faq'           => 'FAQPage',
        ];

        $type = $typeMap[$content['type'] ?? 'page'] ?? 'WebPage';

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => $type,
            'headline' => $content['title'],
            'url'      => Bootstrap::baseUrl() . '/' . $content['slug'],
            'datePublished' => $content['published_at'] ?? $content['created_at'],
            'dateModified'  => $content['updated_at'],
        ];

        $schemaDesc = trim((string) ($content['excerpt'] ?? ''));
        if ($schemaDesc === '' && !empty($content['body'])) {
            $schemaDesc = \Mova\Content\ContentRepository::excerptFromBody((string) $content['body']);
        }
        if ($schemaDesc !== '') {
            $schema['description'] = $schemaDesc;
        }

        if (!empty($content['featured_image'])) {
            $schema['image'] = $this->absoluteUrl($content['featured_image']);
        }

        // Author
        if (!empty($content['author_id'])) {
            $author = Database::fetch("SELECT name FROM users WHERE id = :id", ['id' => $content['author_id']]);
            if ($author) {
                $schema['author'] = [
                    '@type' => 'Person',
                    'name'  => $author['name'],
                ];
            }
        }

        return $schema;
    }

    public function generateSitemap(): string
    {
        $base = Bootstrap::baseUrl();
        $items = Database::fetchAll(
            "SELECT slug, updated_at, published_at FROM content WHERE status = 'published' ORDER BY published_at DESC"
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= "  <url>\n    <loc>{$base}/</loc>\n    <changefreq>daily</changefreq>\n    <priority>1.0</priority>\n  </url>\n";

        foreach ($items as $item) {
            $loc = $base . '/' . htmlspecialchars($item['slug']);
            $lastmod = date('Y-m-d', strtotime($item['updated_at'] ?? $item['published_at']));
            $xml .= "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.8</priority>\n  </url>\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    public function generateLlmsTxt(): string
    {
        $siteName = $this->setting('site_name', 'Mova');
        $desc = $this->setting('site_description', 'Content that moves.');

        $lines = [
            "# {$siteName}",
            "",
            "> {$desc}",
            "",
            "This site is powered by Mova — a lightweight content & publishing platform.",
            "",
            "## Content",
            "",
        ];

        $items = Database::fetchAll(
            "SELECT title, slug, type, excerpt, body, published_at FROM content WHERE status = 'published' ORDER BY published_at DESC LIMIT 100"
        );

        foreach ($items as $item) {
            $url = Bootstrap::baseUrl() . '/' . $item['slug'];
            $lines[] = "- [{$item['title']}]({$url}) ({$item['type']})";
            $ex = trim((string) ($item['excerpt'] ?? ''));
            if ($ex === '' && !empty($item['body'])) {
                $ex = \Mova\Content\ContentRepository::excerptFromBody((string) $item['body']);
            }
            if ($ex !== '') {
                $lines[] = "  {$ex}";
            }
        }

        $lines[] = "";
        $lines[] = "## Machine-readable";
        $lines[] = "";
        $lines[] = "- Sitemap: " . Bootstrap::baseUrl() . "/sitemap.xml";
        $lines[] = "- Feed: " . Bootstrap::baseUrl() . "/feed.xml";
        $lines[] = "- This file: " . Bootstrap::baseUrl() . "/llms.txt";

        return implode("\n", $lines);
    }

    public function generateFeed(): string
    {
        $siteName = $this->setting('site_name', 'Mova');
        $base = Bootstrap::baseUrl();

        $items = Database::fetchAll(
            "SELECT title, slug, excerpt, body, published_at, updated_at FROM content
             WHERE status = 'published' ORDER BY published_at DESC LIMIT 20"
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= "<channel>\n";
        $xml .= "  <title>" . htmlspecialchars($siteName) . "</title>\n";
        $xml .= "  <link>{$base}/</link>\n";
        $xml .= "  <description>" . htmlspecialchars($this->setting('site_description', '')) . "</description>\n";
        $xml .= "  <atom:link href=\"{$base}/feed.xml\" rel=\"self\" type=\"application/rss+xml\"/>\n";

        foreach ($items as $item) {
            $link = $base . '/' . $item['slug'];
            $xml .= "  <item>\n";
            $xml .= "    <title>" . htmlspecialchars($item['title']) . "</title>\n";
            $xml .= "    <link>{$link}</link>\n";
            $xml .= "    <guid>{$link}</guid>\n";
            $xml .= "    <pubDate>" . date(DATE_RSS, strtotime($item['published_at'])) . "</pubDate>\n";
            $ex = trim((string) ($item['excerpt'] ?? ''));
            if ($ex === '' && !empty($item['body'])) {
                $ex = \Mova\Content\ContentRepository::excerptFromBody((string) $item['body']);
            }
            if ($ex !== '') {
                $xml .= "    <description>" . htmlspecialchars($ex) . "</description>\n";
            }
            $xml .= "  </item>\n";
        }

        $xml .= "</channel>\n</rss>";
        return $xml;
    }

    private function setting(string $key, $default = null)
    {
        static $cache = [];
        if (!isset($cache[$key])) {
            $row = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = :k", ['k' => $key]);
            $cache[$key] = $row['setting_value'] ?? $default;
        }
        return $cache[$key] ?? $default;
    }

    private function absoluteUrl(string $path): string
    {
        if (strpos($path, 'http') === 0) {
            return $path;
        }
        return Bootstrap::baseUrl() . '/' . ltrim($path, '/');
    }

    private function e(string $str): string
    {
        return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
