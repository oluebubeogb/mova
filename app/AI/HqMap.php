<?php
/**
 * Mova AI — structured map of HQ screens for navigation and grounding.
 */

namespace Mova\AI;

class HqMap
{
    /**
     * @return list<array{id:string,label:string,path:string,workspace:string,keywords:list<string>,description:string}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'dashboard',
                'label' => 'Dashboard',
                'path' => '/hq',
                'workspace' => 'overview',
                'keywords' => ['home', 'dashboard', 'overview', 'start'],
                'description' => 'HQ home and overview',
            ],
            [
                'id' => 'content.list',
                'label' => 'Content list',
                'path' => '/hq/content',
                'workspace' => 'content',
                'keywords' => ['content', 'posts', 'articles', 'pages', 'list'],
                'description' => 'Browse and manage all content',
            ],
            [
                'id' => 'content.new',
                'label' => 'New content',
                'path' => '/hq/content/new',
                'workspace' => 'content',
                'keywords' => ['new page', 'new post', 'create content', 'write', 'about us', 'new article'],
                'description' => 'Create a new content item',
            ],
            [
                'id' => 'studio',
                'label' => 'Studio',
                'path' => '/hq/studio',
                'workspace' => 'content',
                'keywords' => ['studio', 'page builder', 'visual editor', 'layout'],
                'description' => 'Multi-column content workspace with live preview',
            ],
            [
                'id' => 'assembly',
                'label' => 'Assembly',
                'path' => '/hq/assembly',
                'workspace' => 'content',
                'keywords' => ['assembly', 'sections', 'blocks', 'components build'],
                'description' => 'Assemble reusable page sections',
            ],
            [
                'id' => 'media',
                'label' => 'Media library',
                'path' => '/hq/media',
                'workspace' => 'content',
                'keywords' => ['media', 'images', 'upload', 'files', 'photos'],
                'description' => 'Upload and manage media files',
            ],
            [
                'id' => 'galleries',
                'label' => 'Galleries',
                'path' => '/hq/galleries',
                'workspace' => 'content',
                'keywords' => ['gallery', 'galleries', 'photo album'],
                'description' => 'Manage image galleries',
            ],
            [
                'id' => 'taxonomy.categories',
                'label' => 'Categories',
                'path' => '/hq/taxonomy/categories',
                'workspace' => 'content',
                'keywords' => ['category', 'categories', 'taxonomy'],
                'description' => 'Content categories',
            ],
            [
                'id' => 'taxonomy.tags',
                'label' => 'Tags',
                'path' => '/hq/taxonomy/tags',
                'workspace' => 'content',
                'keywords' => ['tag', 'tags'],
                'description' => 'Content tags',
            ],
            [
                'id' => 'types',
                'label' => 'Content types',
                'path' => '/hq/types',
                'workspace' => 'content',
                'keywords' => ['content type', 'custom fields', 'types'],
                'description' => 'Define content types and fields',
            ],
            [
                'id' => 'design.brand',
                'label' => 'Design — Brand',
                'path' => '/hq/brand',
                'workspace' => 'design',
                'keywords' => ['brand', 'logo', 'identity'],
                'description' => 'Brand settings',
            ],
            [
                'id' => 'design.style',
                'label' => 'Design — Style',
                'path' => '/hq/style',
                'workspace' => 'design',
                'keywords' => ['style', 'colors', 'colour', 'color', 'theme colors', 'primary color', 'palette'],
                'description' => 'Colors, typography, and style tokens',
            ],
            [
                'id' => 'design.elements',
                'label' => 'Design — Elements',
                'path' => '/hq/elements',
                'workspace' => 'design',
                'keywords' => ['elements', 'element styles', 'buttons', 'cards'],
                'description' => 'Element-level style variants',
            ],
            [
                'id' => 'design.variables',
                'label' => 'Design — Variables',
                'path' => '/hq/variables',
                'workspace' => 'design',
                'keywords' => ['variables', 'css variables', 'tokens', 'design tokens'],
                'description' => 'Design tokens and CSS variables',
            ],
            [
                'id' => 'appearance',
                'label' => 'Appearance',
                'path' => '/hq/appearance',
                'workspace' => 'design',
                'keywords' => ['appearance', 'favicon', 'icon', 'site icon', 'logo upload'],
                'description' => 'Site appearance, favicon, and related options',
            ],
            [
                'id' => 'people',
                'label' => 'People',
                'path' => '/hq/people',
                'workspace' => 'audience',
                'keywords' => ['users', 'people', 'team', 'authors', 'roles'],
                'description' => 'Manage users and roles',
            ],
            [
                'id' => 'mail',
                'label' => 'Mail',
                'path' => '/hq/mail',
                'workspace' => 'audience',
                'keywords' => ['mail', 'email', 'smtp'],
                'description' => 'Outbound mail settings',
            ],
            [
                'id' => 'mailbox',
                'label' => 'Mailbox',
                'path' => '/hq/mailbox',
                'workspace' => 'audience',
                'keywords' => ['mailbox', 'inbox', 'imap'],
                'description' => 'Mailbox accounts',
            ],
            [
                'id' => 'insights',
                'label' => 'Insights',
                'path' => '/hq/insights',
                'workspace' => 'audience',
                'keywords' => ['insights', 'analytics', 'stats', 'views'],
                'description' => 'Site insights and analytics',
            ],
            [
                'id' => 'plugins',
                'label' => 'Plugins',
                'path' => '/hq/plugins',
                'workspace' => 'extend',
                'keywords' => ['plugins', 'extensions', 'add-ons'],
                'description' => 'Install and manage plugins',
            ],
            [
                'id' => 'site_ai',
                'label' => 'Site AI Engine',
                'path' => '/hq/site-ai',
                'workspace' => 'extend',
                'keywords' => ['site ai', 'frontend ai', 'visitor assistant', 'knowledge bank', 'chatbot', 'public ai', 'widget'],
                'description' => 'Public site assistant, knowledge bank, and visitor chat widget',
            ],
            [
                'id' => 'integrations',
                'label' => 'Integrations',
                'path' => '/hq/integrations',
                'workspace' => 'extend',
                'keywords' => ['integrations', 'third party', 'connect'],
                'description' => 'Third-party integrations',
            ],
            [
                'id' => 'api_keys',
                'label' => 'API keys',
                'path' => '/hq/api-keys',
                'workspace' => 'extend',
                'keywords' => ['api', 'api key', 'api keys', 'token'],
                'description' => 'Manage API keys',
            ],
            [
                'id' => 'webhooks',
                'label' => 'Webhooks',
                'path' => '/hq/webhooks',
                'workspace' => 'extend',
                'keywords' => ['webhook', 'webhooks', 'callbacks'],
                'description' => 'Webhook endpoints',
            ],
            [
                'id' => 'health',
                'label' => 'Health',
                'path' => '/hq/health',
                'workspace' => 'operations',
                'keywords' => ['health', 'status', 'diagnostics'],
                'description' => 'System health checks',
            ],
            [
                'id' => 'security',
                'label' => 'Security center',
                'path' => '/hq/security',
                'workspace' => 'operations',
                'keywords' => ['security', 'audit', 'login history'],
                'description' => 'Security and audit',
            ],
            [
                'id' => 'backups',
                'label' => 'Backups',
                'path' => '/hq/backups',
                'workspace' => 'operations',
                'keywords' => ['backup', 'backups', 'restore', 'export'],
                'description' => 'Backup and restore',
            ],
            [
                'id' => 'updates',
                'label' => 'Updates',
                'path' => '/hq/updates',
                'workspace' => 'operations',
                'keywords' => ['update', 'updates', 'version'],
                'description' => 'CMS updates',
            ],
            [
                'id' => 'settings',
                'label' => 'Settings',
                'path' => '/hq/settings',
                'workspace' => 'settings',
                'keywords' => ['settings', 'general', 'site name', 'configuration'],
                'description' => 'General site settings',
            ],
            [
                'id' => 'settings.ai',
                'label' => 'Settings — Mova AI',
                'path' => '/hq/settings?layer=ai',
                'workspace' => 'settings',
                'keywords' => ['mova ai', 'ai settings', 'api key', 'ai model', 'ollama'],
                'description' => 'Configure Mova AI endpoint and model',
            ],
            [
                'id' => 'sites',
                'label' => 'Sites',
                'path' => '/hq/sites',
                'workspace' => 'settings',
                'keywords' => ['sites', 'multi-site', 'domains'],
                'description' => 'Multi-site management',
            ],
            [
                'id' => 'docs',
                'label' => 'Docs',
                'path' => '/hq/docs',
                'workspace' => 'overview',
                'keywords' => ['docs', 'documentation', 'help', 'guide'],
                'description' => 'Built-in documentation',
            ],
        ];
    }

    /**
     * Rank HQ destinations by keyword overlap with the user message.
     *
     * @return list<array{id:string,label:string,path:string,workspace:string,keywords:list<string>,description:string,score:float}>
     */
    public static function search(string $query, int $limit = 5): array
    {
        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return [];
        }

        $scored = [];
        foreach (self::all() as $item) {
            $score = 0.0;
            $hay = mb_strtolower($item['label'] . ' ' . $item['description'] . ' ' . implode(' ', $item['keywords']));
            if (str_contains($hay, $q)) {
                $score += 3.0;
            }
            foreach ($item['keywords'] as $kw) {
                $kw = mb_strtolower($kw);
                if ($kw === $q) {
                    $score += 5.0;
                } elseif (str_contains($q, $kw) || str_contains($kw, $q)) {
                    $score += 2.0;
                }
            }
            $words = preg_split('/\s+/', $q) ?: [];
            foreach ($words as $w) {
                if (mb_strlen($w) < 3) {
                    continue;
                }
                if (str_contains($hay, $w)) {
                    $score += 0.5;
                }
            }
            if ($score > 0) {
                $item['score'] = $score;
                $scored[] = $item;
            }
        }

        usort($scored, static fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $limit);
    }

    /** Compact text block for LLM system context */
    public static function asPromptBlock(int $max = 40): string
    {
        $lines = [];
        foreach (array_slice(self::all(), 0, $max) as $item) {
            $lines[] = "- {$item['label']}: {$item['path']} ({$item['description']})";
        }
        return "HQ navigation map:\n" . implode("\n", $lines);
    }
}
