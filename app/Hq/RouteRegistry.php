<?php

declare(strict_types=1);

namespace Mova\Hq;

use Mova\Auth\Auth;

/**
 * Single source of truth for HQ destinations.
 *
 * Drives: Line-2 contextual nav (hq-nav.js), breadcrumbs, the HQ search index (SearchIndex),
 * the Ctrl/Cmd+K palette, and the AI navigation map (Mova\AI\HqMap).
 *
 * Route fields:
 *  id, label, path, section, icon (Font Awesome class), perm (null|'admin'|permission),
 *  keywords (list), desc, view (HQ view file for text indexing), parent (route id for breadcrumbs),
 *  nav (bool: listed in its section's Line 2), pattern (regex for dynamic paths),
 *  prefix (bool: also own sub-paths), sub (list of contextual items), hidden (not searchable as a page).
 */
final class RouteRegistry
{
    /** @var array<string,array>|null */
    private static ?array $routes = null;

    /** @return array<string,array{label:string,icon:string,path:string}> */
    public static function sections(): array
    {
        return [
            'overview'   => ['label' => 'Overview',   'icon' => 'fa-solid fa-house',            'path' => '/hq'],
            'content'    => ['label' => 'Content',    'icon' => 'fa-solid fa-file-lines',       'path' => '/hq/content-hub'],
            'audience'   => ['label' => 'Audience',   'icon' => 'fa-solid fa-users',            'path' => '/hq/audience'],
            'design'     => ['label' => 'Design',     'icon' => 'fa-solid fa-palette',          'path' => '/hq/design'],
            'extend'     => ['label' => 'Extend',     'icon' => 'fa-solid fa-puzzle-piece',     'path' => '/hq/extend'],
            'operations' => ['label' => 'Operations', 'icon' => 'fa-solid fa-gauge-high',       'path' => '/hq/operations'],
            'settings'   => ['label' => 'Settings',   'icon' => 'fa-solid fa-gear',             'path' => '/hq/settings'],
        ];
    }

    /** @return array<string,array> keyed by id, in nav order */
    public static function all(): array
    {
        if (self::$routes !== null) {
            return self::$routes;
        }
        $r = [];
        $add = static function (array $def) use (&$r): void {
            $def += [
                'perm' => null, 'keywords' => [], 'desc' => '', 'view' => null, 'parent' => null,
                'nav' => false, 'group' => null, 'pattern' => null, 'prefix' => false, 'sub' => [], 'hidden' => false, 'plugin' => null,
                'icon' => 'fa-regular fa-file',
            ];
            $r[$def['id']] = $def;
        };

        // ---------- Overview ----------
        $add(['id' => 'dashboard', 'label' => 'Overview', 'path' => '/hq', 'section' => 'overview', 'icon' => 'fa-solid fa-house', 'nav' => true,
            'view' => 'dashboard', 'keywords' => ['home', 'dashboard', 'overview', 'start'], 'desc' => 'HQ home: what needs attention, recent activity and shortcuts.']);
        $add(['id' => 'setup-wizard', 'label' => 'Quick setup', 'path' => '/hq/setup-wizard', 'section' => 'overview', 'icon' => 'fa-solid fa-wand-magic-sparkles', 'nav' => true, 'perm' => 'admin', 'prefix' => true,
            'view' => 'setup_wizard/index', 'keywords' => ['setup', 'wizard', 'kit', 'onboarding', 'starter', 'quick start'], 'desc' => 'Generate a complete starter site from a kit in a few steps.']);
        $add(['id' => 'docs', 'label' => 'Documentation', 'path' => '/hq/docs', 'section' => 'overview', 'icon' => 'fa-solid fa-book', 'nav' => true,
            'view' => 'docs', 'keywords' => ['docs', 'documentation', 'help', 'guide', 'how to'], 'desc' => 'Built-in documentation for every HQ feature.']);
        $add(['id' => 'content-hub', 'label' => 'Content', 'path' => '/hq/content-hub', 'section' => 'content', 'icon' => 'fa-solid fa-file-lines',
            'view' => 'landings/content', 'keywords' => ['content hub', 'content workspace'], 'desc' => 'Content workspace overview.']);
        $add(['id' => 'audience-hub', 'label' => 'Audience', 'path' => '/hq/audience', 'section' => 'audience', 'icon' => 'fa-solid fa-users',
            'view' => 'landings/audience', 'keywords' => ['audience hub'], 'desc' => 'People, mail, feedback and insights.']);
        $add(['id' => 'design-hub', 'label' => 'Design', 'path' => '/hq/design', 'section' => 'design', 'icon' => 'fa-solid fa-palette', 'perm' => 'manage_appearance',
            'view' => 'landings/design', 'keywords' => ['design hub', 'appearance', 'look and feel'], 'desc' => 'Brand, style, layout and sections.']);
        $add(['id' => 'extend-hub', 'label' => 'Extend', 'path' => '/hq/extend', 'section' => 'extend', 'icon' => 'fa-solid fa-puzzle-piece',
            'view' => 'landings/extend', 'keywords' => ['extend hub', 'add-ons'], 'desc' => 'Plugins, Site AI, integrations, API keys and webhooks.']);
        $add(['id' => 'operations-hub', 'label' => 'Operations', 'path' => '/hq/operations', 'section' => 'operations', 'icon' => 'fa-solid fa-gauge-high',
            'view' => 'landings/operations', 'keywords' => ['operations hub', 'maintenance'], 'desc' => 'Health, security, backups, updates and AI usage.']);

        // ---------- Content ----------
        $add(['id' => 'content.list', 'group' => 'library', 'label' => 'All content', 'path' => '/hq/content', 'section' => 'content', 'icon' => 'fa-solid fa-list', 'nav' => true,
            'view' => 'content/list', 'keywords' => ['content', 'posts', 'pages', 'articles', 'list', 'drafts'], 'desc' => 'Browse, filter and bulk-manage all content.',
            'sub' => [
                ['label' => 'All', 'href' => '/hq/content', 'match' => ['status' => null], 'icon' => 'fa-solid fa-layer-group'],
                ['label' => 'Draft', 'href' => '/hq/content?status=draft', 'match' => ['status' => 'draft'], 'icon' => 'fa-regular fa-pen-to-square', 'perm' => 'manage_content'],
                ['label' => 'In review', 'href' => '/hq/content?status=review', 'match' => ['status' => 'review'], 'icon' => 'fa-regular fa-eye', 'perm' => 'manage_content'],
                ['label' => 'Approved', 'href' => '/hq/content?status=approved', 'match' => ['status' => 'approved'], 'icon' => 'fa-regular fa-circle-check', 'perm' => 'manage_content'],
                ['label' => 'Scheduled', 'href' => '/hq/content?status=scheduled', 'match' => ['status' => 'scheduled'], 'icon' => 'fa-regular fa-clock', 'perm' => 'manage_content'],
                ['label' => 'Published', 'href' => '/hq/content?status=published', 'match' => ['status' => 'published'], 'icon' => 'fa-solid fa-globe', 'perm' => 'manage_content'],
                ['label' => 'Archived', 'href' => '/hq/content?status=archived', 'match' => ['status' => 'archived'], 'icon' => 'fa-solid fa-box-archive', 'perm' => 'manage_content'],
                ['label' => 'Trash', 'href' => '/hq/content?status=trash', 'match' => ['status' => 'trash'], 'icon' => 'fa-regular fa-trash-can', 'perm' => 'manage_content'],
                ['label' => 'New content', 'href' => '/hq/content/new', 'primary' => true, 'icon' => 'fa-solid fa-plus', 'perm' => 'manage_content'],
            ]]);
        $add(['id' => 'content.new', 'label' => 'New content', 'path' => '/hq/content/new', 'section' => 'content', 'icon' => 'fa-solid fa-plus', 'parent' => 'content.list', 'perm' => 'manage_content',
            'view' => 'content/edit', 'keywords' => ['new page', 'new post', 'create content', 'write', 'add page', 'new article'], 'desc' => 'Create a page, post or any content type.',
            'sub' => self::editorSub()]);
        $add(['id' => 'content.edit', 'label' => 'Edit content', 'path' => '/hq/content/edit/{id}', 'pattern' => '#^/hq/content/(edit/)?\d+$#', 'section' => 'content', 'icon' => 'fa-regular fa-pen-to-square',
            'parent' => 'content.list', 'hidden' => true, 'perm' => 'manage_content', 'sub' => self::editorSub()]);
        $add(['id' => 'media', 'group' => 'library', 'label' => 'Media', 'path' => '/hq/media', 'section' => 'content', 'icon' => 'fa-regular fa-image', 'nav' => true, 'prefix' => true, 'perm' => 'manage_media',
            'view' => 'media/list', 'keywords' => ['media', 'images', 'upload', 'files', 'photos', 'library'], 'desc' => 'Upload, generate and manage images and files.']);
        $add(['id' => 'galleries', 'group' => 'library', 'label' => 'Galleries', 'path' => '/hq/galleries', 'section' => 'content', 'icon' => 'fa-solid fa-images', 'nav' => true, 'prefix' => true, 'perm' => 'manage_media',
            'view' => 'galleries/list', 'keywords' => ['gallery', 'galleries', 'album', 'carousel', 'collage'], 'desc' => 'Image collections with carousel, grid and collage embeds.']);
        $add(['id' => 'blogs', 'group' => 'library', 'label' => 'Blogs', 'path' => '/hq/blogs', 'section' => 'content', 'icon' => 'fa-solid fa-rss', 'nav' => true, 'prefix' => true, 'perm' => 'manage_content',
            'view' => 'blogs/list', 'keywords' => ['blog', 'feed', 'posts', 'infinite scroll'], 'desc' => 'Content feeds with filters, manual picks and templates.']);
        $add(['id' => 'types', 'group' => 'structure', 'label' => 'Types & fields', 'path' => '/hq/types', 'section' => 'content', 'icon' => 'fa-solid fa-table-list', 'nav' => true, 'prefix' => true, 'perm' => 'admin',
            'view' => 'types/list', 'keywords' => ['content type', 'custom fields', 'types', 'schema'], 'desc' => 'Define content types and their fields.']);
        $add(['id' => 'categories', 'group' => 'structure', 'label' => 'Categories', 'path' => '/hq/categories', 'section' => 'content', 'icon' => 'fa-regular fa-folder', 'nav' => true, 'perm' => 'manage_content',
            'view' => 'taxonomy/categories', 'keywords' => ['category', 'categories', 'taxonomy'], 'desc' => 'Organise content into categories.']);
        $add(['id' => 'tags', 'group' => 'structure', 'label' => 'Tags', 'path' => '/hq/tags', 'section' => 'content', 'icon' => 'fa-solid fa-tag', 'nav' => true, 'perm' => 'manage_content',
            'view' => 'taxonomy/tags', 'keywords' => ['tag', 'tags', 'taxonomy', 'labels'], 'desc' => 'Tag content for discovery.']);
        $add(['id' => 'assembly', 'group' => 'structure', 'label' => 'Assembly', 'path' => '/hq/assembly', 'section' => 'content', 'icon' => 'fa-solid fa-cubes', 'nav' => true, 'prefix' => true, 'perm' => 'manage_content',
            'view' => 'assembly/index', 'keywords' => ['assembly', 'sections', 'blocks', 'reusable', 'components'], 'desc' => 'Build reusable sections and drop them into pages.']);
        $add(['id' => 'studio', 'group' => 'tools', 'label' => 'Studio', 'path' => '/hq/studio', 'section' => 'content', 'icon' => 'fa-solid fa-code', 'nav' => true, 'prefix' => true, 'perm' => 'manage_content',
            'view' => 'studio/index', 'keywords' => ['studio', 'page builder', 'html', 'css', 'js', 'code', 'live preview'], 'desc' => 'Multi-pane HTML/CSS/JS editor with live preview.']);
        $add(['id' => 'seo.media', 'group' => 'tools', 'label' => 'Media SEO', 'path' => '/hq/seo/media', 'section' => 'content', 'icon' => 'fa-solid fa-magnifying-glass-chart', 'nav' => true, 'perm' => 'manage_content',
            'view' => 'seo/media', 'keywords' => ['seo', 'alt text', 'image seo', 'csv'], 'desc' => 'Alt text, titles and filenames in bulk.']);
        $add(['id' => 'seo.content', 'group' => 'tools', 'label' => 'Content SEO', 'path' => '/hq/seo/content', 'section' => 'content', 'icon' => 'fa-solid fa-chart-line', 'nav' => true, 'perm' => 'manage_content',
            'view' => 'seo/content', 'keywords' => ['seo', 'meta description', 'title tag', 'slug', 'csv'], 'desc' => 'Titles, meta descriptions and slugs in bulk.']);
        $add(['id' => 'find-replace', 'group' => 'tools', 'label' => 'Find & replace', 'path' => '/hq/content/find-replace', 'section' => 'content', 'icon' => 'fa-solid fa-arrow-right-arrow-left', 'nav' => true, 'perm' => 'manage_content',
            'view' => 'content/find_replace', 'keywords' => ['find', 'replace', 'bulk edit', 'search and replace'], 'desc' => 'Search and replace text across all content, with preview and undo.']);
        $add(['plugin' => 'mova-dev-editor', 'id' => 'content.upload', 'group' => 'tools', 'label' => 'Upload content', 'path' => '/hq/content/upload', 'section' => 'content', 'icon' => 'fa-solid fa-file-arrow-up', 'nav' => true, 'perm' => 'manage_content',
            'view' => 'content/upload', 'keywords' => ['upload', 'import', 'html', 'bulk'], 'desc' => 'Import HTML/CSS/JS files as content.']);

        // ---------- Audience ----------
        $add(['id' => 'people', 'label' => 'People', 'path' => '/hq/people', 'section' => 'audience', 'icon' => 'fa-solid fa-user-group', 'nav' => true, 'prefix' => true, 'perm' => 'admin',
            'view' => 'people/list', 'keywords' => ['users', 'people', 'team', 'authors', 'roles', 'invite'], 'desc' => 'Team members, roles and permissions.']);
        $add(['id' => 'mailbox', 'label' => 'Mailbox', 'path' => '/hq/mailbox', 'section' => 'audience', 'icon' => 'fa-solid fa-inbox', 'nav' => true, 'perm' => 'manage_mail',
            'view' => 'mailbox/index', 'keywords' => ['mailbox', 'inbox', 'imap', 'email', 'compose'], 'desc' => 'Read and send email from connected accounts.',
            'sub' => [
                ['label' => 'Compose', 'href' => '/hq/mailbox?tab=compose&compose=1', 'icon' => 'fa-solid fa-pen', 'primary' => true],
                ['label' => 'Inbox', 'href' => '/hq/mailbox?tab=inbox&folder=INBOX', 'icon' => 'fa-solid fa-inbox'],
                ['label' => 'Sent', 'href' => '/hq/mailbox?tab=inbox&folder=Sent', 'icon' => 'fa-regular fa-paper-plane'],
                ['label' => 'Drafts', 'href' => '/hq/mailbox?tab=inbox&folder=Drafts', 'icon' => 'fa-regular fa-file'],
                ['label' => 'Trash', 'href' => '/hq/mailbox?tab=inbox&folder=Trash', 'icon' => 'fa-regular fa-trash-can'],
                ['label' => 'Spam', 'href' => '/hq/mailbox?tab=inbox&folder=Spam', 'icon' => 'fa-solid fa-ban'],
                ['label' => 'Address book', 'href' => '/hq/mailbox?tab=contacts', 'icon' => 'fa-regular fa-address-book'],
                ['label' => 'Filters', 'href' => '/hq/mailbox?tab=filters', 'icon' => 'fa-solid fa-filter'],
                ['label' => 'Accounts', 'href' => '/hq/mailbox?tab=accounts', 'icon' => 'fa-solid fa-at'],
            ]]);
        $add(['id' => 'mail', 'label' => 'Campaigns', 'path' => '/hq/mail', 'section' => 'audience', 'icon' => 'fa-solid fa-paper-plane', 'nav' => true, 'perm' => 'manage_mail',
            'view' => 'mail/index', 'keywords' => ['campaign', 'newsletter', 'subscribers', 'email marketing', 'smtp'], 'desc' => 'Subscribers and email campaigns.',
            'sub' => [
                ['label' => 'Add subscriber', 'action' => 'add-subscriber', 'icon' => 'fa-solid fa-user-plus'],
                ['label' => 'Send campaign', 'action' => 'send-campaign', 'icon' => 'fa-regular fa-paper-plane', 'primary' => true],
            ]]);
        $add(['id' => 'sequences', 'label' => 'Sequences', 'path' => '/hq/sequences', 'section' => 'audience', 'icon' => 'fa-solid fa-diagram-project', 'nav' => true, 'perm' => 'manage_mail',
            'view' => 'sequences/index', 'keywords' => ['sequences', 'automation', 'drip', 'follow up'], 'desc' => 'Automated email sequences.']);
        $add(['id' => 'feedback', 'label' => 'Feedback', 'path' => '/hq/feedback', 'section' => 'audience', 'icon' => 'fa-regular fa-comment-dots', 'nav' => true, 'prefix' => true, 'perm' => 'manage_content',
            'view' => 'feedback/index', 'keywords' => ['feedback', 'forms', 'contact form', 'submissions', 'survey'], 'desc' => 'Embeddable forms and their submissions.',
            'sub' => [
                ['label' => 'Forms', 'href' => '/hq/feedback', 'icon' => 'fa-regular fa-rectangle-list'],
                ['label' => 'Inbox', 'href' => '/hq/feedback/inbox', 'icon' => 'fa-solid fa-inbox', 'prefix' => true],
                ['label' => 'Export', 'href' => '/hq/feedback/export', 'icon' => 'fa-solid fa-file-export'],
                ['label' => 'New form', 'href' => '/hq/feedback/forms/new', 'icon' => 'fa-solid fa-plus', 'primary' => true, 'perm' => 'manage_content'],
            ]]);
        $add(['id' => 'feedback.inbox', 'label' => 'Feedback inbox', 'path' => '/hq/feedback/inbox', 'section' => 'audience', 'icon' => 'fa-solid fa-inbox', 'parent' => 'feedback', 'perm' => 'manage_content',
            'view' => 'feedback/inbox', 'keywords' => ['feedback inbox', 'entries', 'unread', 'submissions'], 'desc' => 'Read and export form submissions.']);
        $add(['id' => 'insights', 'label' => 'Insights', 'path' => '/hq/insights', 'section' => 'audience', 'icon' => 'fa-solid fa-chart-simple', 'nav' => true, 'perm' => 'view_insights',
            'view' => 'insights/index', 'keywords' => ['insights', 'analytics', 'stats', 'views', 'traffic', 'popular'], 'desc' => 'Traffic, popular content and recent views.',
            'sub' => [
                ['label' => 'Popular content', 'action' => 'popular', 'icon' => 'fa-solid fa-fire'],
                ['label' => 'Recent views', 'action' => 'recent', 'icon' => 'fa-regular fa-clock'],
            ]]);

        // ---------- Design ----------
        $add(['id' => 'templates', 'label' => 'Sections', 'path' => '/hq/templates', 'section' => 'design', 'icon' => 'fa-solid fa-table-cells-large', 'nav' => true, 'prefix' => true, 'perm' => 'manage_appearance',
            'view' => 'design/templates', 'keywords' => ['templates', 'sections', 'section library', 'hero', 'pricing', 'faq', 'testimonials', 'layouts', 'catalog'], 'desc' => 'Section library: ready-made, animated sections to insert into pages and Assemblies.']);
        $add(['id' => 'themes', 'label' => 'Themes', 'path' => '/hq/themes', 'section' => 'design', 'icon' => 'fa-solid fa-swatchbook', 'nav' => true, 'perm' => 'manage_appearance',
            'view' => 'design/themes', 'keywords' => ['themes', 'theme', 'look', 'skin', 'presence', 'clarity', 'lengthy', 'documentation', 'preview', 'apply theme'], 'desc' => 'Browse themes on desktop and mobile, preview them on your own site and apply in one click.']);
        $add(['id' => 'brand', 'label' => 'Brand', 'path' => '/hq/brand', 'section' => 'design', 'icon' => 'fa-solid fa-copyright', 'nav' => true, 'perm' => 'manage_appearance',
            'view' => 'design/brand', 'keywords' => ['brand', 'logo', 'favicon', 'identity', 'theme', 'site icon'], 'desc' => 'Identity, logo, favicon and theme package.',
            'sub' => self::layers('/hq/brand', ['identity' => ['Identity', 'fa-regular fa-id-card'], 'marks' => ['Logo & favicon', 'fa-regular fa-star'], 'theme' => ['Theme package', 'fa-solid fa-swatchbook']])]);
        $add(['id' => 'style', 'label' => 'Style', 'path' => '/hq/style', 'section' => 'design', 'icon' => 'fa-solid fa-droplet', 'nav' => true, 'perm' => 'manage_appearance',
            'view' => 'design/style', 'keywords' => ['style', 'colors', 'colour', 'typography', 'fonts', 'palette', 'dark mode'], 'desc' => 'Colors, typography, radius and density.',
            'sub' => self::layers('/hq/style', ['light' => ['Light colors', 'fa-regular fa-sun'], 'dark' => ['Dark colors', 'fa-regular fa-moon'], 'type' => ['Typography', 'fa-solid fa-font'], 'shape' => ['Radius & shadow', 'fa-regular fa-square'], 'density' => ['Density', 'fa-solid fa-compress']], true)]);
        $add(['id' => 'layout', 'label' => 'Layout', 'path' => '/hq/layout', 'section' => 'design', 'icon' => 'fa-solid fa-table-columns', 'nav' => true, 'perm' => 'manage_appearance',
            'view' => 'design/layout', 'keywords' => ['layout', 'header', 'footer', 'navigation', 'menu', 'container'], 'desc' => 'Header, navigation, mobile menu and footer.',
            'sub' => self::layers('/hq/layout', ['container' => ['Container', 'fa-solid fa-expand'], 'header' => ['Header', 'fa-solid fa-heading'], 'nav' => ['Navigation', 'fa-solid fa-bars'], 'mobile' => ['Mobile menu', 'fa-solid fa-mobile-screen'], 'footer' => ['Footer', 'fa-solid fa-shoe-prints']], true)]);
        $add(['id' => 'components', 'label' => 'Components', 'path' => '/hq/components', 'section' => 'design', 'icon' => 'fa-solid fa-shapes', 'nav' => true, 'perm' => 'manage_appearance',
            'view' => 'design/components', 'keywords' => ['components', 'buttons', 'cards', 'hero'], 'desc' => 'Buttons, cards and hero styles.',
            'sub' => self::layers('/hq/components', ['button' => ['Buttons', 'fa-solid fa-hand-pointer'], 'card' => ['Cards', 'fa-regular fa-clone'], 'hero' => ['Hero', 'fa-solid fa-panorama']], true)]);
        $add(['id' => 'elements', 'label' => 'Elements', 'path' => '/hq/elements', 'section' => 'design', 'icon' => 'fa-solid fa-cube', 'nav' => true, 'perm' => 'manage_appearance',
            'view' => 'design/elements', 'keywords' => ['elements', 'element styles', 'custom css'], 'desc' => 'Element-level style variants.',
            'sub' => self::layers('/hq/elements', ['editor' => ['Editor', 'fa-solid fa-pen-ruler'], 'applied' => ['Applied', 'fa-solid fa-check-double']])]);
        $add(['id' => 'variables', 'label' => 'Variables', 'path' => '/hq/variables', 'section' => 'design', 'icon' => 'fa-solid fa-sliders', 'nav' => true, 'perm' => 'manage_appearance',
            'view' => 'design/variables', 'keywords' => ['variables', 'css variables', 'tokens', 'design tokens'], 'desc' => 'Design tokens and custom CSS variables.',
            'sub' => [
                ['label' => 'All variables', 'href' => '/hq/variables', 'icon' => 'fa-solid fa-sliders'],
                ['label' => 'Var sheet', 'href' => '/hq/variables/sheet', 'icon' => 'fa-solid fa-table'],
                ['label' => 'Export', 'href' => '/hq/variables/export', 'icon' => 'fa-solid fa-file-export'],
                ['label' => 'Add variable', 'href' => '/hq/variables/new', 'icon' => 'fa-solid fa-plus', 'primary' => true],
            ]]);
        $add(['id' => 'variables.sheet', 'label' => 'Variable sheet', 'path' => '/hq/variables/sheet', 'section' => 'design', 'icon' => 'fa-solid fa-table', 'parent' => 'variables', 'perm' => 'manage_appearance',
            'view' => 'design/variable-sheet', 'keywords' => ['var sheet', 'tokens table']]);
        $add(['id' => 'variables.new', 'label' => 'Add variable', 'path' => '/hq/variables/new', 'section' => 'design', 'icon' => 'fa-solid fa-plus', 'parent' => 'variables', 'perm' => 'manage_appearance',
            'view' => 'design/variable-form', 'keywords' => ['new variable', 'custom variable']]);
        $add(['id' => 'variables.export', 'label' => 'Export variables', 'path' => '/hq/variables/export', 'section' => 'design', 'icon' => 'fa-solid fa-file-export', 'parent' => 'variables', 'perm' => 'manage_appearance',
            'view' => 'design/variable-export', 'keywords' => ['export variables', 'download tokens']]);
        $add(['id' => 'design.reset', 'label' => 'Reset defaults', 'path' => '/hq/design/reset', 'section' => 'design', 'icon' => 'fa-solid fa-rotate-left', 'nav' => true, 'perm' => 'manage_appearance',
            'view' => 'design/reset', 'keywords' => ['reset', 'defaults', 'restore design'], 'desc' => 'Restore style, layout or components to defaults.']);

        // ---------- Extend ----------
        $add(['id' => 'plugins', 'label' => 'Plugins', 'path' => '/hq/plugins', 'section' => 'extend', 'icon' => 'fa-solid fa-plug', 'nav' => true, 'perm' => 'admin',
            'view' => 'plugins/index', 'keywords' => ['plugins', 'extensions', 'add-ons', 'install'], 'desc' => 'Install, activate and configure plugins.']);
        $add(['id' => 'site-ai', 'label' => 'Site AI', 'path' => '/hq/site-ai', 'section' => 'extend', 'icon' => 'fa-solid fa-robot', 'nav' => true, 'perm' => 'admin',
            'view' => 'site_ai/index', 'keywords' => ['site ai', 'chatbot', 'visitor assistant', 'knowledge bank', 'widget'], 'desc' => 'Visitor-facing AI assistant and its knowledge bank.',
            'sub' => [
                ['label' => 'Settings', 'href' => '/hq/site-ai?layer=settings', 'layer' => 'settings', 'icon' => 'fa-solid fa-gear'],
                ['label' => 'Design', 'href' => '/hq/site-ai?layer=design', 'layer' => 'design', 'icon' => 'fa-solid fa-palette'],
                ['label' => 'Knowledge bank', 'href' => '/hq/site-ai?layer=knowledge', 'layer' => 'knowledge', 'icon' => 'fa-solid fa-book-open'],
            ]]);
        $add(['id' => 'integrations', 'label' => 'Integrations', 'path' => '/hq/integrations', 'section' => 'extend', 'icon' => 'fa-solid fa-link', 'nav' => true, 'perm' => 'admin',
            'view' => 'integrations/index', 'keywords' => ['integrations', 'cdn', 'smtp', 'analytics', 'storage', 's3', 'third party'], 'desc' => 'CDN, email API, analytics, storage and AI providers.',
            'sub' => [
                ['label' => 'CDN', 'href' => '/hq/integrations?provider=cdn', 'icon' => 'fa-solid fa-cloud'],
                ['label' => 'Email API', 'href' => '/hq/integrations?provider=smtp', 'icon' => 'fa-regular fa-envelope'],
                ['label' => 'Analytics', 'href' => '/hq/integrations?provider=analytics', 'icon' => 'fa-solid fa-chart-pie'],
                ['label' => 'Object storage', 'href' => '/hq/integrations?provider=storage', 'icon' => 'fa-solid fa-database'],
                ['label' => 'AI provider', 'href' => '/hq/integrations?provider=ai', 'icon' => 'fa-solid fa-microchip'],
            ]]);
        $add(['id' => 'api-keys', 'label' => 'API keys', 'path' => '/hq/api-keys', 'section' => 'extend', 'icon' => 'fa-solid fa-key', 'nav' => true, 'perm' => 'admin',
            'view' => 'api/keys', 'keywords' => ['api', 'api key', 'token', 'headless', 'rest'], 'desc' => 'Keys for the REST API.']);
        $add(['id' => 'webhooks', 'label' => 'Webhooks', 'path' => '/hq/webhooks', 'section' => 'extend', 'icon' => 'fa-solid fa-satellite-dish', 'nav' => true, 'perm' => 'admin',
            'view' => 'webhooks/index', 'keywords' => ['webhook', 'callbacks', 'events', 'notify'], 'desc' => 'Send events to other services.']);
        $add(['id' => 'pwa', 'label' => 'App (PWA)', 'path' => '/hq/pwa', 'section' => 'extend', 'icon' => 'fa-solid fa-mobile-screen-button', 'nav' => true, 'perm' => 'admin',
            'view' => 'pwa/index', 'keywords' => ['pwa', 'app', 'progressive web app', 'install', 'home screen', 'offline', 'manifest', 'service worker', 'install banner', 'add to home screen'],
            'desc' => 'Make the site installable: app name, icon, colours, offline page and install banner.']);

        // ---------- Operations ----------
        $add(['id' => 'health', 'label' => 'Health', 'path' => '/hq/health', 'section' => 'operations', 'icon' => 'fa-solid fa-heart-pulse', 'nav' => true, 'perm' => 'admin',
            'view' => 'health/index', 'keywords' => ['health', 'status', 'diagnostics', 'php', 'requirements'], 'desc' => 'System checks, requirements and recent activity.']);
        $add(['id' => 'security', 'label' => 'Security', 'path' => '/hq/security', 'section' => 'operations', 'icon' => 'fa-solid fa-shield-halved', 'nav' => true, 'perm' => 'admin',
            'view' => 'security/index', 'keywords' => ['security', '2fa', 'audit', 'login history', 'blocked'], 'desc' => 'Audit log, logins and security controls.']);
        $add(['id' => 'backups', 'label' => 'Backups', 'path' => '/hq/backups', 'section' => 'operations', 'icon' => 'fa-solid fa-box-archive', 'nav' => true, 'perm' => 'admin',
            'view' => 'backups/index', 'keywords' => ['backup', 'restore', 'export', 'download'], 'desc' => 'Create, download and restore backups.']);
        $add(['id' => 'updates', 'label' => 'Updates', 'path' => '/hq/updates', 'section' => 'operations', 'icon' => 'fa-solid fa-arrows-rotate', 'nav' => true, 'perm' => 'admin',
            'view' => 'updates/index', 'keywords' => ['update', 'version', 'release', 'identity', 'mova.json'], 'desc' => 'Core updates and public identity files.']);
        $add(['id' => 'ai-usage', 'label' => 'AI usage', 'path' => '/hq/ai-usage', 'section' => 'operations', 'icon' => 'fa-solid fa-chart-column', 'nav' => true, 'perm' => 'admin',
            'view' => 'ai_usage/index', 'keywords' => ['ai usage', 'tokens', 'requests', 'cost', 'gateway', 'quota'], 'desc' => 'AI requests and tokens by day, feature and model.']);

        // ---------- Settings ----------
        $add(['id' => 'settings', 'label' => 'General', 'path' => '/hq/settings', 'section' => 'settings', 'icon' => 'fa-solid fa-sliders', 'nav' => true, 'perm' => 'admin',
            'view' => 'settings', 'keywords' => ['settings', 'general', 'site name', 'timezone', 'configuration'], 'desc' => 'Site name, URLs, mail and AI configuration.',
            'sub' => [
                ['label' => 'General', 'href' => '/hq/settings?layer=general', 'layer' => 'general', 'icon' => 'fa-solid fa-sliders'],
                ['label' => 'AI assist', 'href' => '/hq/settings?layer=ai', 'layer' => 'ai', 'icon' => 'fa-solid fa-wand-magic-sparkles'],
            ]]);
        $add(['id' => 'settings.ai', 'label' => 'AI settings', 'path' => '/hq/settings?layer=ai', 'section' => 'settings', 'icon' => 'fa-solid fa-wand-magic-sparkles', 'perm' => 'admin', 'parent' => 'settings',
            'keywords' => ['mova ai', 'ai settings', 'api key', 'ai model', 'gateway', 'endpoint'], 'desc' => 'Mova AI gateway, model and keys.']);
        $add(['id' => 'sites', 'label' => 'Sites', 'path' => '/hq/sites', 'section' => 'settings', 'icon' => 'fa-solid fa-sitemap', 'nav' => true, 'perm' => 'admin',
            'view' => 'sites/index', 'keywords' => ['sites', 'multi-site', 'domains'], 'desc' => 'Multi-site management.']);

        return self::$routes = $r;
    }

    /** @return list<array> */
    private static function editorSub(): array
    {
        return [
            ['label' => 'AI assist', 'action' => 'ai-assist', 'icon' => 'fa-solid fa-wand-magic-sparkles'],
            ['label' => 'Publish', 'action' => 'publish', 'icon' => 'fa-solid fa-upload'],
            ['label' => 'Categories', 'action' => 'categories', 'icon' => 'fa-regular fa-folder'],
            ['label' => 'Tags', 'action' => 'tags', 'icon' => 'fa-solid fa-tag'],
            ['label' => 'Featured image', 'action' => 'featured-image', 'icon' => 'fa-regular fa-image'],
            ['label' => 'Related', 'action' => 'related-content', 'icon' => 'fa-solid fa-link'],
            ['label' => 'SEO', 'action' => 'seo', 'icon' => 'fa-solid fa-magnifying-glass-chart'],
        ];
    }

    /** @param array<string,array{0:string,1:string}> $layers */
    private static function layers(string $path, array $layers, bool $withReset = false): array
    {
        $out = [];
        foreach ($layers as $id => [$label, $icon]) {
            $out[] = ['label' => $label, 'layer' => $id, 'href' => $path . '?layer=' . $id, 'icon' => $icon];
        }
        if ($withReset) {
            $out[] = ['label' => 'Reset', 'href' => '/hq/design/reset', 'icon' => 'fa-solid fa-rotate-left'];
        }
        return $out;
    }

    public static function get(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    /** Sub-items the current user may see (items may carry their own 'perm'). */
    public static function visibleSub(array $sub): array
    {
        return array_values(array_filter($sub, static fn($s) => empty($s['perm']) || self::allowed((string) $s['perm'])));
    }

    /** Route is usable right now: role allows it and any required plugin is active. */
    public static function usable(array $route): bool
    {
        if (!empty($route['plugin'])) {
            try {
                $row = \Mova\Core\Database::fetch('SELECT status FROM plugins WHERE slug = :s', ['s' => $route['plugin']]);
                if (($row['status'] ?? '') !== 'active') {
                    return false;
                }
            } catch (\Throwable $e) {
                return false;
            }
        }
        return self::allowed($route['perm']);
    }

    /** True when the user can open at least one nav page in a workspace (hub pages excluded). */
    public static function sectionAllowed(string $section): bool
    {
        $hubPath = self::sections()[$section]['path'] ?? '';
        foreach (self::all() as $r) {
            if ($r['section'] === $section && $r['nav'] && $r['path'] !== $hubPath && self::usable($r)) {
                return true;
            }
        }
        return false;
    }

    /** Does the current user pass a route/permission gate? */
    public static function allowed(?string $perm): bool
    {
        if ($perm === null || $perm === '') {
            return true;
        }
        try {
            if (Auth::hasRole('owner', 'administrator')) {
                return true;
            }
            if ($perm === 'admin') {
                return false;
            }
            foreach (explode('|', $perm) as $p) {
                if (Auth::can($p)) {
                    return true;
                }
            }
            if ($perm === 'manage_content' && Auth::can('manage_own_content')) {
                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }

    /** Best route for a request path (exact > pattern > longest prefix). */
    public static function match(string $path): ?array
    {
        $path = '/' . trim((string) parse_url($path, PHP_URL_PATH), '/');
        if ($path === '/hq/') {
            $path = '/hq';
        }
        $best = null;
        $bestLen = -1;
        foreach (self::all() as $route) {
            $rp = (string) parse_url($route['path'], PHP_URL_PATH);
            if ($rp === $path && !str_contains($route['path'], '?')) {
                return $route;
            }
            if ($route['pattern'] && preg_match($route['pattern'], $path)) {
                return $route;
            }
            if ($rp !== '/hq' && str_starts_with($path, $rp . '/') && strlen($rp) > $bestLen) {
                $best = $route;
                $bestLen = strlen($rp);
            }
        }
        return $best;
    }

    /** Section key for a path (used for body[data-workspace] and Line 1). */
    public static function sectionFor(string $path): string
    {
        $route = self::match($path);
        return $route['section'] ?? 'overview';
    }

    /**
     * @return list<array{label:string,href:string,icon?:string}>
     */
    public static function breadcrumb(?array $route): array
    {
        $sections = self::sections();
        if (!$route) {
            return [['label' => 'Overview', 'href' => '/hq', 'icon' => $sections['overview']['icon']]];
        }
        $trail = [];
        $guard = 0;
        $cur = $route;
        while ($cur && $guard++ < 5) {
            array_unshift($trail, ['label' => $cur['label'], 'href' => $cur['path'], 'icon' => $cur['icon']]);
            $cur = $cur['parent'] ? self::get($cur['parent']) : null;
        }
        $sec = $sections[$route['section']] ?? $sections['overview'];
        if ($trail[0]['label'] !== $sec['label'] && $route['id'] !== 'dashboard') {
            array_unshift($trail, ['label' => $sec['label'], 'href' => $sec['path'], 'icon' => $sec['icon']]);
        }
        return $trail;
    }

    /** Line-2 nav items for a section, filtered by role. */
    public static function sectionNav(string $section): array
    {
        $items = [];
        if ($section === 'overview') {
            foreach (['dashboard'] as $id) {
                $r = self::get($id);
                if ($r && self::allowed($r['perm'])) {
                    $items[] = ['label' => $r['label'], 'href' => $r['path'], 'icon' => $r['icon'], 'id' => $r['id']];
                }
            }
            foreach (self::sections() as $key => $s) {
                if ($key === 'overview') {
                    continue;
                }
                $hub = self::match($s['path']);
                if ($hub && !self::allowed($hub['perm'])) {
                    continue;
                }
                if (!self::sectionAllowed($key)) {
                    continue; // nothing in this workspace the user may open
                }
                $items[] = ['label' => $s['label'], 'href' => $s['path'], 'icon' => $s['icon'], 'section' => $key];
            }
            return $items;
        }
        foreach (self::all() as $r) {
            if ($r['section'] === $section && $r['nav'] && self::usable($r)) {
                $items[] = ['label' => $r['label'], 'href' => $r['path'], 'icon' => $r['icon'], 'id' => $r['id'], 'prefix' => (bool) $r['prefix'], 'group' => $r['group']];
            }
        }
        return $items;
    }

    /**
     * Everything the client nav needs for the current request (serialised into the page).
     */
    public static function clientPayload(string $requestUri): array
    {
        $route = self::match($requestUri);
        $section = $route['section'] ?? 'overview';
        $sections = [];
        foreach (self::sections() as $k => $s) {
            $sections[$k] = $s;
        }
        return [
            'route'      => $route ? ['id' => $route['id'], 'label' => $route['label'], 'path' => $route['path'], 'icon' => $route['icon']] : null,
            'section'    => $section,
            'sections'   => $sections,
            'breadcrumb' => self::breadcrumb($route),
            'nav'        => self::sectionNav($section),
            'sub'        => $route && $route['sub'] ? self::visibleSub($route['sub']) : [],
        ];
    }

    /** Flat list for search/palette/AI (role-filtered unless $all). */
    public static function destinations(bool $all = false): array
    {
        $out = [];
        $sections = self::sections();
        foreach (self::all() as $r) {
            if ($r['hidden'] || (!$all && !self::usable($r))) {
                continue;
            }
            $out[] = [
                'id' => $r['id'], 'label' => $r['label'], 'path' => $r['path'], 'section' => $r['section'],
                'section_label' => $sections[$r['section']]['label'] ?? '', 'icon' => $r['icon'],
                'keywords' => $r['keywords'], 'desc' => $r['desc'], 'perm' => $r['perm'], 'view' => $r['view'],
                'sub' => self::visibleSub($r['sub']),
            ];
        }
        return $out;
    }
}
