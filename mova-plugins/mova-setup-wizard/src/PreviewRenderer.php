<?php
/**
 * Request-scoped previews of kit pages (kit picker, wizard review, theme mockups).
 * Nothing is written to the database: settings, tokens and theme are overridden in memory.
 */

declare(strict_types=1);

namespace MovaSetupWizard;

use Mova\Theme\DesignConfig;
use Mova\Theme\SiteFrame;
use Mova\Theme\ThemeManager;

final class PreviewRenderer
{
    /**
     * @param array $d      wizard draft (or kit demo values)
     * @param string $base  URL prefix used to keep navigation inside the preview
     * @return array{content:array,title:string,base:string,slugs:list<string>}|null
     */
    public static function prepare(array $kit, string $slug, array $d, string $base, string $query = ''): ?array
    {
        $page = KitRenderer::page($kit, $slug);
        if (!$page) {
            return null;
        }
        $d['pack'] = $kit['pack'] ?? ($d['pack'] ?? 'generic');
        $vars = WizardDraft::vars($d);
        $copy = isset($d['copy'][$slug]) && is_array($d['copy'][$slug]) ? $d['copy'][$slug] : null;
        $body = KitRenderer::render($kit, $page, $vars, $copy);

        if (!empty($d['theme'])) {
            ThemeManager::setPreview((string) $d['theme']);
        }
        DesignConfig::setPreviewTokens(WizardDraft::tokens($d, $kit));
        $set = KitRenderer::siteSettings($kit, $vars);
        $ov = [
            'site_name' => $vars['site_name'],
            'site_description' => $vars['tagline'],
            'brand_color' => WizardDraft::tokens($d, $kit)['colors']['primary'],
            'nav_links' => $set['nav_links'],
            'nav_links_desktop' => '',
            'footer_columns' => $set['footer_columns'],
            'nav_cta' => $set['nav_cta'],
            'header_assembly_slug' => '',
            'footer_assembly_slug' => '',
            'logo_url' => (string) ($d['logo_url'] ?? ''),
            'logo_url_dark' => '',
        ];
        SiteFrame::setOverrides($ov);
        $title = (string) KitRenderer::fill((string) ($page['title'] ?? $slug), $vars);
        return [
            'content' => ['id' => 0, 'title' => $title, 'slug' => $slug, 'type' => 'page', 'status' => 'published', 'body' => $body,
                'excerpt' => '', 'published_at' => date('c'), 'updated_at' => date('c'), 'created_at' => date('c'), 'meta' => ['page_layout' => 'canvas', 'meta_description' => (string) KitRenderer::fill((string) ($page['description'] ?? ''), $vars)]],
            'title' => ($page['role'] ?? '') === 'front' ? $vars['site_name'] . ' | ' . $vars['tagline'] : $title . ' | ' . $vars['site_name'],
            'slugs' => array_column($kit['pages'], 'slug'),
            'base' => $base,
            'query' => $query,
        ];
    }

    /** Point internal links of the rendered page at the preview route. */
    public static function rewriteLinks(string $html, array $slugs, string $base, string $query = ''): string
    {
        $q = $query !== '' ? '?' . $query : '';
        $html = str_replace(['href="/"', "href='/'"], 'href="' . $base . '/home' . $q . '"', $html);
        foreach ($slugs as $s) {
            $html = preg_replace('~href="/' . preg_quote($s, '~') . '(#[^"]*)?"~', 'href="' . $base . '/' . $s . $q . '$1"', $html) ?? $html;
        }
        return $html;
    }
}
