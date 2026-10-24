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
        $forcedTitle = $seoTitle !== ''; // caller passed an explicit full title (e.g. homepage)
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

        // Append site name for content pages only when the title was not fully forced by the caller
        if (!$forcedTitle && $content && $postTitle !== '' && $title !== $siteName) {
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
            'dateModified'  => $content['updated_at'] ?? ($content['published_at'] ?? null),
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
        $lines[] = "- Media: " . Bootstrap::baseUrl() . "/media.xml";
        $lines[] = "- This file: " . Bootstrap::baseUrl() . "/llms.txt";

        return implode("\n", $lines);
    }

    public function generateFeed(): string
    {
        $siteName = $this->setting('site_name', 'Mova');
        $base = Bootstrap::baseUrl();

        $items = Database::fetchAll(
            "SELECT title, slug, excerpt, body, published_at, updated_at, featured_image FROM content
             WHERE status = 'published' ORDER BY published_at DESC LIMIT 20"
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">' . "\n";
        $xml .= "<channel>\n";
        $xml .= "  <title>" . htmlspecialchars($siteName) . "</title>\n";
        $xml .= "  <link>{$base}/</link>\n";
        $xml .= "  <description>" . htmlspecialchars($this->setting('site_description', '')) . "</description>\n";
        $xml .= "  <atom:link href=\"{$base}/feed.xml\" rel=\"self\" type=\"application/rss+xml\"/>\n";
        $xml .= "  <atom:link href=\"{$base}/media.xml\" rel=\"related\" type=\"application/rss+xml\" title=\"Media library\"/>\n";

        foreach ($items as $item) {
            $link = $base . '/' . $item['slug'];
            $xml .= "  <item>\n";
            $xml .= "    <title>" . htmlspecialchars($item['title']) . "</title>\n";
            $xml .= "    <link>{$link}</link>\n";
            $xml .= "    <guid>{$link}</guid>\n";
            $xml .= "    <pubDate>" . date(DATE_RSS, strtotime($item['published_at'] ?: 'now')) . "</pubDate>\n";
            $ex = trim((string) ($item['excerpt'] ?? ''));
            if ($ex === '' && !empty($item['body'])) {
                $ex = \Mova\Content\ContentRepository::excerptFromBody((string) $item['body']);
            }
            if ($ex !== '') {
                $xml .= "    <description>" . htmlspecialchars($ex) . "</description>\n";
            }
            $img = trim((string) ($item['featured_image'] ?? ''));
            if ($img !== '') {
                $abs = $this->absoluteUrl($img);
                $xml .= "    <media:content url=\"" . htmlspecialchars($abs) . "\" medium=\"image\"/>\n";
                $xml .= "    <enclosure url=\"" . htmlspecialchars($abs) . "\" type=\"image/webp\" length=\"0\"/>\n";
            }
            $xml .= "  </item>\n";
        }

        $xml .= "</channel>\n</rss>";
        return $xml;
    }

    /**
     * Media library feed — name, alt, description, variants, and pages where each file appears.
     */
    public function generateMediaFeed(): string
    {
        $siteName = $this->setting('site_name', 'Mova');
        $base = Bootstrap::baseUrl();
        $mediaSvc = new \Mova\Media\MediaService();

        try {
            $rows = Database::fetchAll(
                "SELECT * FROM media ORDER BY created_at DESC LIMIT 200"
            );
        } catch (\Throwable $e) {
            $rows = [];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">' . "\n";
        $xml .= "<channel>\n";
        $xml .= "  <title>" . htmlspecialchars($siteName) . " — Media</title>\n";
        $xml .= "  <link>{$base}/media.xml</link>\n";
        $xml .= "  <description>Media library for " . htmlspecialchars($siteName) . " (images, video, alt text, descriptions, and site occurrences)</description>\n";
        $xml .= "  <atom:link href=\"{$base}/media.xml\" rel=\"self\" type=\"application/rss+xml\"/>\n";
        $xml .= "  <atom:link href=\"{$base}/feed.xml\" rel=\"related\" type=\"application/rss+xml\" title=\"Content feed\"/>\n";

        foreach ($rows as $row) {
            if (!empty($row['variants']) && is_string($row['variants'])) {
                $row['variants'] = json_decode($row['variants'], true) ?: [];
            }
            $url = $mediaSvc->url($row);
            $mime = (string) ($row['mime_type'] ?? 'application/octet-stream');
            $name = (string) ($row['original_name'] ?? $row['filename'] ?? 'media');
            $alt = trim((string) ($row['alt_text'] ?? ''));
            $desc = trim((string) ($row['description'] ?? ''));
            $isVideo = strpos($mime, 'video/') === 0;
            $isImage = strpos($mime, 'image/') === 0;
            $medium = $isVideo ? 'video' : ($isImage ? 'image' : 'document');
            $guid = $base . '/media/' . (int) ($row['id'] ?? 0);

            $xml .= "  <item>\n";
            $xml .= "    <title>" . htmlspecialchars($name) . "</title>\n";
            $xml .= "    <link>" . htmlspecialchars($url) . "</link>\n";
            $xml .= "    <guid isPermaLink=\"false\">" . htmlspecialchars($guid) . "</guid>\n";
            if (!empty($row['created_at'])) {
                $xml .= "    <pubDate>" . date(DATE_RSS, strtotime((string) $row['created_at'])) . "</pubDate>\n";
            }
            $parts = [];
            if ($alt !== '') {
                $parts[] = $alt;
            }
            if ($desc !== '') {
                $parts[] = $desc;
            }
            if ($parts !== []) {
                $xml .= "    <description>" . htmlspecialchars(implode("\n\n", $parts)) . "</description>\n";
            }
            if ($alt !== '') {
                $xml .= "    <media:title type=\"plain\">" . htmlspecialchars($alt) . "</media:title>\n";
                $xml .= "    <media:description type=\"plain\">" . htmlspecialchars($desc !== '' ? $desc : $alt) . "</media:description>\n";
            } elseif ($desc !== '') {
                $xml .= "    <media:description type=\"plain\">" . htmlspecialchars($desc) . "</media:description>\n";
            }
            $w = (int) ($row['width'] ?? 0);
            $h = (int) ($row['height'] ?? 0);
            $size = (int) ($row['size'] ?? 0);
            $xml .= "    <media:content url=\"" . htmlspecialchars($url) . "\" type=\"" . htmlspecialchars($mime) . "\" medium=\"{$medium}\"";
            if ($w > 0) {
                $xml .= " width=\"{$w}\"";
            }
            if ($h > 0) {
                $xml .= " height=\"{$h}\"";
            }
            if ($size > 0) {
                $xml .= " fileSize=\"{$size}\"";
            }
            $xml .= "/>\n";
            if ($isVideo) {
                $poster = $mediaSvc->posterUrl($row);
                if ($poster !== '') {
                    $xml .= "    <media:thumbnail url=\"" . htmlspecialchars($poster) . "\"/>\n";
                }
            }
            if (!empty($row['variants']) && is_array($row['variants'])) {
                foreach ($row['variants'] as $v) {
                    $vw = (int) ($v['width'] ?? 0);
                    if ($vw <= 0 || empty($v['path'])) {
                        continue;
                    }
                    $vurl = $base . '/mova-uploads/' . ltrim((string) $v['path'], '/');
                    if ($vurl === $url) {
                        continue;
                    }
                    $xml .= "    <media:content url=\"" . htmlspecialchars($vurl) . "\" type=\"image/webp\" medium=\"image\" width=\"{$vw}\"";
                    if (!empty($v['height'])) {
                        $xml .= " height=\"" . (int) $v['height'] . "\"";
                    }
                    $xml .= "/>\n";
                }
            }
            try {
                $occ = $mediaSvc->findOccurrences($row);
            } catch (\Throwable $e) {
                $occ = [];
            }
            foreach ($occ as $o) {
                $xml .= "    <media:credit role=\"appearance\" scheme=\"urn:mova:page\">" . htmlspecialchars((string) ($o['url'] ?? '')) . "</media:credit>\n";
                $xml .= "    <category domain=\"mova:page\">" . htmlspecialchars((string) ($o['title'] ?? $o['slug'] ?? '')) . "</category>\n";
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
