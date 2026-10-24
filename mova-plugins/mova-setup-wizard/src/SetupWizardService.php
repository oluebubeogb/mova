<?php
/**
 * Quick Setup Wizard — orchestration for packs, palette, pages, nav, footer.
 */

declare(strict_types=1);

namespace MovaSetupWizard;

use Mova\Auth\Auth;
use Mova\Content\ContentRepository;
use Mova\Core\Database;
use Mova\Plugin\PluginManager;
use Mova\Theme\DesignConfig;

final class SetupWizardService
{
    public const SLUG = 'mova-setup-wizard';
    public const META_TAG = 'setup_wizard';
    public const FOOTER_COLUMNS_KEY = 'footer_columns';

    /** @return list<array{id:string,label:string,description:string}> */
    public static function packList(): array
    {
        return [
            [
                'id' => 'school',
                'label' => 'School',
                'description' => 'Home, About, Academics, Admissions, Contact — ready for a school site.',
            ],
            [
                'id' => 'organization',
                'label' => 'Organization',
                'description' => 'Home, About, Programs, Team, Contact — for nonprofits and groups.',
            ],
            [
                'id' => 'generic',
                'label' => 'Simple site',
                'description' => 'Home, About, Contact — minimal starter.',
            ],
        ];
    }

    public static function getPack(string $id): array
    {
        return match ($id) {
            'school' => Packs\SchoolPack::definition(),
            'organization' => Packs\OrgPack::definition(),
            default => self::genericPack(),
        };
    }

    /** @return array<string, mixed> */
    private static function genericPack(): array
    {
        return [
            'id' => 'generic',
            'label' => 'Simple site',
            'pages' => [
                [
                    'slug' => 'home',
                    'title' => 'Home',
                    'role' => 'front',
                    'body' => '<section class="wizard-section"><h1>{{site_name}}</h1><p>{{tagline}}</p><p>Welcome. Edit this page in HQ to add your story, highlights, and calls to action.</p></section>',
                ],
                [
                    'slug' => 'about',
                    'title' => 'About us',
                    'role' => 'about',
                    'body' => '<section class="wizard-section"><h1>About us</h1><p>{{about_seed}}</p><p>Add your mission, history, and what makes you unique.</p></section>',
                ],
                [
                    'slug' => 'contact',
                    'title' => 'Contact',
                    'role' => 'contact',
                    'body' => '<section class="wizard-section"><h1>Contact</h1><p>Get in touch.</p><p>{{contact_seed}}</p></section>',
                ],
            ],
            'nav' => [
                ['label' => 'Home', 'url' => '/'],
                ['label' => 'About us', 'url' => '/about'],
                ['label' => 'Contact', 'url' => '/contact'],
            ],
            'footer_columns' => [
                [
                    'title' => 'Explore',
                    'links' => [
                        ['label' => 'About us', 'url' => '/about'],
                        ['label' => 'Contact', 'url' => '/contact'],
                    ],
                ],
                [
                    'title' => 'Contact',
                    'text' => '{{contact_seed}}',
                ],
            ],
        ];
    }

    /**
     * Ensure plugin row exists and is active (new installs + upgrades).
     */
    public static function ensureActivated(): void
    {
        try {
            if (!class_exists(Database::class)) {
                return;
            }
            $row = Database::fetch('SELECT id, status FROM plugins WHERE slug = :s', ['s' => self::SLUG]);
            $now = date('c');
            $meta = [
                'name' => 'Quick Setup Wizard',
                'version' => '1.1.0',
            ];
            if (is_file(dirname(__DIR__) . '/plugin.json')) {
                $json = json_decode((string) file_get_contents(dirname(__DIR__) . '/plugin.json'), true);
                if (is_array($json)) {
                    $meta['name'] = (string) ($json['name'] ?? $meta['name']);
                    $meta['version'] = (string) ($json['version'] ?? $meta['version']);
                }
            }
            if ($row) {
                if (($row['status'] ?? '') !== 'active') {
                    Database::update('plugins', [
                        'status' => 'active',
                        'name' => $meta['name'],
                        'version' => $meta['version'],
                        'activated_at' => $now,
                    ], 'id = :id', ['id' => $row['id']]);
                }
            } else {
                Database::insert('plugins', [
                    'slug' => self::SLUG,
                    'name' => $meta['name'],
                    'version' => $meta['version'],
                    'status' => 'active',
                    'config' => '{}',
                    'installed_at' => $now,
                    'activated_at' => $now,
                ]);
            }
        } catch (\Throwable $e) {
            // DB may not exist during very early boot
        }
    }

    /**
     * Build 3–5 palette options from one or more seed colors.
     *
     * @param list<string> $seedColors Hex colors
     * @return list<array{id:string,label:string,colors:array<string,string>,colors_dark:array<string,string>}>
     */
    public static function generatePalettes(array $seedColors): array
    {
        $primary = self::normalizeHex($seedColors[0] ?? '#2563eb') ?: '#2563eb';
        $accentIn = self::normalizeHex($seedColors[1] ?? '') ?: null;
        $third = self::normalizeHex($seedColors[2] ?? '') ?: null;

        $options = [];

        // 1) User primary + derived accent
        $accent = $accentIn ?: self::shiftHue($primary, 40);
        $options[] = self::paletteOption('brand', 'Brand focus', $primary, $accent);

        // 2) Softer / institutional
        $options[] = self::paletteOption(
            'soft',
            'Soft institutional',
            self::mixToward($primary, '#64748b', 0.25),
            self::mixToward($accent, '#94a3b8', 0.2)
        );

        // 3) High contrast
        $options[] = self::paletteOption(
            'contrast',
            'High contrast',
            $primary,
            $third ?: self::shiftHue($primary, -50)
        );

        // 4) Warm companion
        $options[] = self::paletteOption(
            'warm',
            'Warm companion',
            self::mixToward($primary, '#c2410c', 0.15),
            self::mixToward($accent, '#ea580c', 0.25)
        );

        // 5) Cool modern
        $options[] = self::paletteOption(
            'cool',
            'Cool modern',
            self::mixToward($primary, '#0ea5e9', 0.2),
            self::mixToward($accent, '#6366f1', 0.2)
        );

        return $options;
    }

    /**
     * @return array{id:string,label:string,colors:array<string,string>,colors_dark:array<string,string>}
     */
    private static function paletteOption(string $id, string $label, string $primary, string $accent): array
    {
        $colors = [
            'primary' => $primary,
            'secondary' => '#64748b',
            'accent' => $accent,
            'background' => '#f8f9fb',
            'surface' => '#ffffff',
            'text' => '#111827',
            'muted' => '#6b7280',
            'border' => '#e5e7eb',
        ];
        $colorsDark = [
            'primary' => self::lighten($primary, 0.25),
            'secondary' => '#94a3b8',
            'accent' => self::lighten($accent, 0.2),
            'background' => '#0b0d12',
            'surface' => '#12151c',
            'text' => '#f3f4f6',
            'muted' => '#9ca3af',
            'border' => '#1f2430',
        ];
        return [
            'id' => $id,
            'label' => $label,
            'colors' => $colors,
            'colors_dark' => $colorsDark,
        ];
    }

    public static function applyPalette(array $colors, array $colorsDark = []): void
    {
        $tokens = DesignConfig::tokens();
        $tokens['colors'] = array_merge($tokens['colors'] ?? [], $colors);
        if ($colorsDark !== []) {
            $tokens['colors_dark'] = array_merge($tokens['colors_dark'] ?? [], $colorsDark);
        }
        DesignConfig::saveTokens($tokens);
        if (!empty($colors['primary'])) {
            DesignConfig::saveSetting('brand_color', (string) $colors['primary']);
        }
    }

    /**
     * Run Phase 1 create: settings, palette, pages, nav, footer columns.
     *
     * @param array{
     *   pack_id: string,
     *   site_name: string,
     *   tagline?: string,
     *   seed_text?: string,
     *   about?: string,
     *   contact?: string,
     *   palette?: array{colors: array, colors_dark?: array},
     *   status?: string,
     *   use_ai?: bool
     * } $input
     * @return array{ok:bool, pages: list<array{id:int,slug:string,title:string}>, message?: string, error?: string}
     */
    public static function run(array $input): array
    {
        $packId = (string) ($input['pack_id'] ?? 'generic');
        $kitId = trim((string) ($input['kit_id'] ?? ''));
        $kit = null;
        if ($kitId !== '' && class_exists(KitRepository::class)) {
            $kit = KitRepository::load($kitId);
        }
        if (is_array($kit) && (int) ($kit['schema'] ?? 1) >= 2) {
            $colors = (array) ($input['palette']['colors'] ?? []);
            $draft = WizardDraft::merge(WizardDraft::defaults(), [
                'pack' => $packId, 'kit' => $kitId,
                'site_name' => (string) ($input['site_name'] ?? ''), 'tagline' => (string) ($input['tagline'] ?? ''),
                'about' => (string) ($input['about'] ?? ($input['seed_text'] ?? '')),
                'primary' => (string) ($colors['primary'] ?? ''), 'accent' => (string) ($colors['accent'] ?? ''),
                'status' => (string) ($input['status'] ?? 'published'),
            ]);
            return self::publishDraft($draft);
        }
        $pack = self::getPack($packId);
        // Prefer kit pages/nav/footer when a kit is selected
        if (is_array($kit) && !empty($kit['pages_resolved'])) {
            $pack['pages'] = [];
            foreach ($kit['pages_resolved'] as $pr) {
                $pack['pages'][] = [
                    'slug' => (string) ($pr['slug'] ?? 'page'),
                    'title' => (string) ($pr['title'] ?? 'Page'),
                    'role' => (string) ($pr['role'] ?? 'generic'),
                    'body' => (string) ($pr['html'] ?? ''),
                ];
            }
            if (!empty($kit['nav']) && is_array($kit['nav'])) {
                $pack['nav'] = $kit['nav'];
            }
            if (!empty($kit['footer_columns']) && is_array($kit['footer_columns'])) {
                $pack['footer_columns'] = $kit['footer_columns'];
            }
        }
        $siteName = trim((string) ($input['site_name'] ?? 'My Site'));
        if ($siteName === '') {
            $siteName = 'My Site';
        }
        $tagline = trim((string) ($input['tagline'] ?? ''));
        $seedText = trim((string) ($input['seed_text'] ?? ''));
        $about = trim((string) ($input['about'] ?? $seedText));
        $contact = trim((string) ($input['contact'] ?? ''));
        $status = (($input['status'] ?? 'draft') === 'published') ? 'published' : 'draft';
        $useAi = !empty($input['use_ai']);
        $aiExpanded = 0;
        $knowledgeSeeded = false;

        // Site name + description
        DesignConfig::saveSetting('site_name', $siteName);
        if ($tagline !== '') {
            DesignConfig::saveSetting('site_description', $tagline);
        }

        // Palette
        if (!empty($input['palette']['colors']) && is_array($input['palette']['colors'])) {
            self::applyPalette(
                $input['palette']['colors'],
                is_array($input['palette']['colors_dark'] ?? null) ? $input['palette']['colors_dark'] : []
            );
        }

        // Footer layout style → columns when we have column data
        $layout = DesignConfig::layout();
        $layout['footer'] = array_merge($layout['footer'] ?? [], ['style' => 'columns']);
        DesignConfig::saveLayout($layout);

        $aboutHtml = $about !== ''
            ? nl2br(htmlspecialchars($about, ENT_QUOTES, 'UTF-8'))
            : 'Tell visitors who you are and what you stand for.';
        $contactHtml = $contact !== ''
            ? nl2br(htmlspecialchars($contact, ENT_QUOTES, 'UTF-8'))
            : 'Address, phone, and email go here.';
        // Kits use {{about}} / {{contact}}; built-in packs use the *_seed variants.
        // Support both so no raw token ever reaches the public site.
        $vars = [
            '{{site_name}}' => htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'),
            '{{tagline}}' => htmlspecialchars($tagline !== '' ? $tagline : 'Content that moves.', ENT_QUOTES, 'UTF-8'),
            '{{about_seed}}' => $aboutHtml,
            '{{about}}' => $aboutHtml,
            '{{contact_seed}}' => $contactHtml,
            '{{contact}}' => $contactHtml,
            '{{year}}' => date('Y'),
        ];

        $repo = new ContentRepository();
        $authorId = Auth::id();
        $created = [];
        $navLines = [];

        foreach ($pack['pages'] as $page) {
            $slug = (string) $page['slug'];
            $title = html_entity_decode(strip_tags(self::replaceTokens((string) $page['title'], $vars)), ENT_QUOTES, 'UTF-8');
            $role = (string) ($page['role'] ?? 'generic');
            $body = self::replaceTokens((string) $page['body'], $vars);

            // Phase 2: optional AI expansion for about/contact roles
            if ($useAi && in_array($role, ['about', 'contact', 'team', 'programs'], true)) {
                $aiBody = self::expandWithAi($role, $siteName, $tagline, $about, $contact, $seedText);
                if ($aiBody !== null && $aiBody !== '') {
                    $body = self::replaceTokens($aiBody, $vars);
                    $aiExpanded++;
                }
            }

            // Avoid clobbering existing published content: update wizard-tagged drafts or create new
            $existing = $repo->findBySlugAnyStatus($slug);
            $meta = [
                'created_by' => self::META_TAG,
                'wizard_pack' => $packId,
                'wizard_role' => $role,
                'wizard_kit' => $kitId,
            ];
            // Kit CSS/JS only render on the public site when editor_mode=dev
            // (mova-dev-editor FrontendInjector). Keep site chrome (header/footer).
            if (is_array($kit) && (!empty($kit['css_combined']) || !empty($kit['js_combined']))) {
                $meta['editor_mode'] = 'dev';
                $meta['use_site_chrome'] = '1';
                $meta['hide_article_chrome'] = '1';
            }
            if (is_array($kit) && !empty($kit['css_combined'])) {
                $meta['raw_css'] = $kit['css_combined'];
            }
            if (is_array($kit) && !empty($kit['js_combined'])) {
                $meta['raw_js'] = $kit['js_combined'];
            }

            if ($existing && (($existing['meta']['created_by'] ?? '') === self::META_TAG || ($existing['status'] ?? '') === 'draft')) {
                $repo->update((int) $existing['id'], [
                    'title' => $title,
                    'body' => $body,
                    'status' => $status,
                    'type' => 'page',
                    'author_id' => $authorId ?? $existing['author_id'] ?? null,
                    'meta' => array_merge($existing['meta'] ?? [], $meta),
                ]);
                $id = (int) $existing['id'];
            } elseif ($existing) {
                // Leave non-wizard published pages alone; use a wizard-prefixed slug only if needed
                $altSlug = $slug . '-setup';
                $id = $repo->create([
                    'title' => $title,
                    'slug' => $altSlug,
                    'body' => $body,
                    'status' => $status,
                    'type' => 'page',
                    'author_id' => $authorId,
                    'meta' => $meta,
                ]);
                $slug = $altSlug;
            } else {
                $id = $repo->create([
                    'title' => $title,
                    'slug' => $slug,
                    'body' => $body,
                    'status' => $status,
                    'type' => 'page',
                    'author_id' => $authorId,
                    'meta' => $meta,
                ]);
            }

            $created[] = ['id' => $id, 'slug' => $slug, 'title' => $title, 'role' => $role];

            if ($role === 'front') {
                // Front page often lives at / via theme home; still list in nav as Home → /
                $navLines[] = $title . '|/';
            } else {
                $navLines[] = $title . '|/' . ltrim($slug, '/');
            }
        }

        // Nav from pack (preferred order) if provided
        if (!empty($pack['nav']) && is_array($pack['nav'])) {
            $navLines = [];
            foreach ($pack['nav'] as $item) {
                $navLines[] = strip_tags(self::replaceTokens((string) ($item['label'] ?? 'Page'), $vars)) . '|' . ($item['url'] ?? '/');
            }
        }
        DesignConfig::saveSetting('nav_links', implode("\n", $navLines));

        // Footer columns
        $columns = [];
        foreach ($pack['footer_columns'] ?? [] as $col) {
            $entry = [
                'title' => (string) ($col['title'] ?? ''),
                'text' => isset($col['text'])
                    ? self::replaceTokens((string) $col['text'], $vars)
                    : '',
                'links' => [],
            ];
            foreach ($col['links'] ?? [] as $link) {
                if (is_string($link)) {
                    // slug reference
                    $found = null;
                    foreach ($created as $c) {
                        if ($c['slug'] === $link || ($c['role'] ?? '') === $link) {
                            $found = $c;
                            break;
                        }
                    }
                    if ($found) {
                        $entry['links'][] = [
                            'label' => $found['title'],
                            'url' => ($found['role'] ?? '') === 'front' ? '/' : '/' . $found['slug'],
                        ];
                    }
                } elseif (is_array($link)) {
                    $entry['links'][] = [
                        'label' => self::replaceTokens((string) ($link['label'] ?? ''), $vars),
                        'url' => (string) ($link['url'] ?? '#'),
                    ];
                }
            }
            $columns[] = $entry;
        }
        DesignConfig::saveSetting(self::FOOTER_COLUMNS_KEY, json_encode($columns, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Phase 2: optional KnowledgeBank seed
        // Bind / to kit Home page so frontend is not the empty theme home.php
        foreach ($created as $c) {
            if (($c['role'] ?? '') === 'front' && !empty($c['id'])) {
                DesignConfig::saveSetting('homepage_content_id', (string) (int) $c['id']);
                break;
            }
        }

        if ($useAi && ($seedText !== '' || $about !== '' || $contact !== '')) {
            $knowledgeSeeded = self::seedKnowledgeBank($siteName, $seedText, $about, $contact);
        }

        $msg = count($created) . ' page(s) created. Palette, nav, and footer columns applied.';
        if ($useAi) {
            $msg .= $aiExpanded > 0
                ? " AI expanded {$aiExpanded} page(s)."
                : ' AI expansion used local copy where the gateway was unavailable.';
            if ($knowledgeSeeded) {
                $msg .= ' Site AI knowledge seeded.';
            }
        }

        // Site name, nav, palette, homepage and footer all changed: drop cached public pages.
        if (class_exists(\Mova\Cache\PageCache::class)) {
            try {
                \Mova\Cache\PageCache::flush();
            } catch (\Throwable $e) {
                // cache dir not writable — pages refresh when the TTL expires
            }
        }

        return [
            'ok' => true,
            'pages' => $created,
            'message' => $msg,
            'ai' => [
                'requested' => $useAi,
                'expanded' => $aiExpanded,
                'knowledge_seeded' => $knowledgeSeeded,
            ],
        ];
    }

    /**
     * Phase 2 — expand page body via AiAssistService::chatRaw (or heuristic fallback).
     */
    public static function expandWithAi(
        string $role,
        string $siteName,
        string $tagline,
        string $about,
        string $contact,
        string $seedText
    ): ?string {
        $facts = trim($seedText);
        if ($role === 'about' && trim($about) !== '') {
            $facts = trim($about);
        }
        if ($role === 'contact' && trim($contact) !== '') {
            $facts = trim($contact);
        }
        if ($facts === '' && trim($about) !== '') {
            $facts = trim($about);
        }

        $system = 'You are a web copywriter for a CMS. Reply with clean semantic HTML only '
            . '(section, h1, h2, p, ul, li, a, strong, em). No markdown fences, no <html>/<body>, '
            . 'no scripts or styles. Keep tone professional and warm. Use the provided facts; '
            . 'do not invent phone numbers, emails, or addresses that were not given.';

        $user = match ($role) {
            'about' => "Write an About Us page for \"{$siteName}\".\nTagline: "
                . ($tagline !== '' ? $tagline : '(none)')
                . "\nFacts:\n" . ($facts !== '' ? $facts : 'General community organization or school.'),
            'contact' => "Write a Contact page for \"{$siteName}\".\nInclude a clear heading and present "
                . "these contact details as readable text (no form unless a simple mailto link):\n"
                . ($facts !== '' ? $facts : 'Add address, phone, and email when available.'),
            'team' => "Write a Team / Who we are page for \"{$siteName}\".\nFacts:\n"
                . ($facts !== '' ? $facts : 'Introduce leadership and key people; leave room for bios.'),
            'programs' => "Write a Programs page for \"{$siteName}\".\nFacts:\n"
                . ($facts !== '' ? $facts : 'Describe programmes and how people can take part.'),
            'generic' => "Write a clear page body for \"{$siteName}\" titled for role generic.\nFacts:\n"
                . ($facts !== '' ? $facts : $tagline),
            default => null,
        };
        if ($user === null) {
            return null;
        }

        if (class_exists(\Mova\AI\AiAssistService::class)) {
            try {
                $assist = new \Mova\AI\AiAssistService();
                if ($assist->isConfigured() && method_exists($assist, 'chatRaw')) {
                    $out = $assist->chatRaw([
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ], 1200, 90, 0.55);
                    // Leaked "\u2019"-style escapes → real characters before sanitising.
                    $clean = self::sanitizeAiHtml(\Mova\AI\AiText::clean((string) $out));
                    if ($clean !== null) {
                        return $clean;
                    }
                }
            } catch (\Throwable $e) {
                // fall through to heuristic
            }
        }

        return self::heuristicPageHtml($role, $siteName, $tagline, $facts);
    }

    /**
     * Local Phase 2 fallback when AI is offline — still richer than static pack shells.
     */
    public static function heuristicPageHtml(string $role, string $siteName, string $tagline, string $facts): string
    {
        $safeName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
        $safeTag = htmlspecialchars($tagline !== '' ? $tagline : '', ENT_QUOTES, 'UTF-8');
        $safeFacts = $facts !== ''
            ? '<p>' . nl2br(htmlspecialchars($facts, ENT_QUOTES, 'UTF-8')) . '</p>'
            : '';

        return match ($role) {
            'about' => '<section class="wizard-section"><h1>About ' . $safeName . '</h1>'
                . ($safeTag !== '' ? '<p class="lead">' . $safeTag . '</p>' : '')
                . ($safeFacts !== '' ? $safeFacts : '<p>We are committed to serving our community with care, clarity, and purpose.</p>')
                . '<p>Edit this page in HQ to add history, values, and highlights.</p></section>',
            'contact' => '<section class="wizard-section"><h1>Contact</h1>'
                . '<p>We would love to hear from you.</p>'
                . ($safeFacts !== '' ? $safeFacts : '<p>Add your address, phone, and email here.</p>')
                . '</section>',
            'team' => '<section class="wizard-section"><h1>Our team</h1>'
                . ($safeFacts !== '' ? $safeFacts : '<p>Meet the people behind ' . $safeName . '. Add names, roles, and short bios.</p>')
                . '</section>',
            'programs' => '<section class="wizard-section"><h1>Programs</h1>'
                . ($safeFacts !== '' ? $safeFacts : '<p>Explore the programmes and initiatives offered by ' . $safeName . '.</p>')
                . '</section>',
            default => '<section class="wizard-section"><h1>' . $safeName . '</h1>'
                . ($safeTag !== '' ? '<p class="lead">' . $safeTag . '</p>' : '')
                . $safeFacts . '</section>',
        };
    }

    /** Strip markdown fences and dangerous tags from model output. */
    public static function sanitizeAiHtml(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }
        $html = trim($html);
        if ($html === '') {
            return null;
        }
        // Remove ```html ... ``` wrappers
        if (preg_match('/^```(?:html|HTML)?\s*([\s\S]*?)```\s*$/', $html, $m)) {
            $html = trim($m[1]);
        }
        $html = preg_replace('#<(script|iframe|object|embed|form)[^>]*>[\s\S]*?</\1>#i', '', $html) ?? $html;
        $html = preg_replace('#</?(script|iframe|object|embed|form)[^>]*>#i', '', $html) ?? $html;
        $html = trim($html);
        return $html !== '' ? $html : null;
    }

    /**
     * Phase 2 — seed Site AI knowledge bank when plugin is present.
     * @return bool True if a source was added
     */
    public static function seedKnowledgeBank(string $siteName, string $seed, string $about, string $contact): bool
    {
        try {
            $kbFile = dirname(__DIR__, 2) . '/mova-site-ai/src/KnowledgeBank.php';
            if (is_file($kbFile) && !class_exists(\MovaSiteAi\KnowledgeBank::class, false)) {
                require_once $kbFile;
            }
            if (!class_exists(\MovaSiteAi\KnowledgeBank::class)) {
                return false;
            }
            \MovaSiteAi\KnowledgeBank::ensureSchema();
            $text = trim(implode("\n\n", array_filter([
                'Site: ' . $siteName,
                $seed !== '' ? $seed : null,
                $about !== '' ? "About:\n" . $about : null,
                $contact !== '' ? "Contact:\n" . $contact : null,
            ], static fn ($v) => $v !== null && $v !== '')));
            if ($text === '') {
                return false;
            }
            \MovaSiteAi\KnowledgeBank::addTextSource(
                'Setup Wizard — ' . $siteName,
                'text',
                $text,
                'setup-wizard'
            );
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function normalizeHex(string $hex): ?string
    {
        $hex = trim($hex);
        if ($hex === '') {
            return null;
        }
        if ($hex[0] !== '#') {
            $hex = '#' . $hex;
        }
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $hex, $m)) {
            $h = $m[1];
            return '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        if (preg_match('/^#([0-9a-fA-F]{6})$/', $hex)) {
            return strtolower($hex);
        }
        return null;
    }

    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function rgbToHex(int $r, int $g, int $b): string
    {
        $r = max(0, min(255, $r));
        $g = max(0, min(255, $g));
        $b = max(0, min(255, $b));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private static function mixToward(string $hex, string $toward, float $t): string
    {
        [$r1, $g1, $b1] = self::hexToRgb($hex);
        [$r2, $g2, $b2] = self::hexToRgb($toward);
        return self::rgbToHex(
            (int) round($r1 + ($r2 - $r1) * $t),
            (int) round($g1 + ($g2 - $g1) * $t),
            (int) round($b1 + ($b2 - $b1) * $t)
        );
    }

    private static function lighten(string $hex, float $t): string
    {
        return self::mixToward($hex, '#ffffff', $t);
    }

    private static function shiftHue(string $hex, float $degrees): string
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        if ($d < 0.00001) {
            $h = 0;
            $s = 0;
        } else {
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            if ($max === $r) {
                $h = (($g - $b) / $d) + ($g < $b ? 6 : 0);
            } elseif ($max === $g) {
                $h = (($b - $r) / $d) + 2;
            } else {
                $h = (($r - $g) / $d) + 4;
            }
            $h /= 6;
        }
        $h = fmod($h + ($degrees / 360.0) + 1.0, 1.0);
        $hue2rgb = static function ($p, $q, $t) {
            if ($t < 0) {
                $t += 1;
            }
            if ($t > 1) {
                $t -= 1;
            }
            if ($t < 1 / 6) {
                return $p + ($q - $p) * 6 * $t;
            }
            if ($t < 1 / 2) {
                return $q;
            }
            if ($t < 2 / 3) {
                return $p + ($q - $p) * (2 / 3 - $t) * 6;
            }
            return $p;
        };
        if ($s < 0.00001) {
            $rr = $gg = $bb = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $rr = $hue2rgb($p, $q, $h + 1 / 3);
            $gg = $hue2rgb($p, $q, $h);
            $bb = $hue2rgb($p, $q, $h - 1 / 3);
        }
        return self::rgbToHex((int) round($rr * 255), (int) round($gg * 255), (int) round($bb * 255));
    }

    /**
     * Replace known {{tokens}}, then strip any unknown leftover {{token}} so raw
     * placeholders never leak to the public site.
     *
     * @param array<string,string> $vars
     */
    public static function replaceTokens(string $text, array $vars): string
    {
        $text = str_replace(array_keys($vars), array_values($vars), $text);
        return (string) preg_replace('/\{\{\s*[a-z0-9_]+\s*\}\}/i', '', $text);
    }

    /** @return list<array{title:string,text:string,links:list<array{label:string,url:string}>}> */
    public static function getFooterColumns(): array
    {
        try {
            $raw = DesignConfig::setting(self::FOOTER_COLUMNS_KEY, '[]');
            $decoded = json_decode((string) $raw, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ───────────────────── Quick setup v2 (kits of section specs) ─────────────────────

    public static function lightColors(string $p, string $a, string $s): array
    {
        return [
            'primary' => $p, 'accent' => $a, 'secondary' => $s,
            'background' => self::mixToward('#f8f9fb', $p, 0.018),
            'surface' => '#ffffff',
            'text' => self::mixToward('#0f172a', $p, 0.06),
            'muted' => self::mixToward('#5b6474', $p, 0.06),
            'border' => self::mixToward('#e5e7eb', $p, 0.05),
        ];
    }

    public static function darkColors(string $p, string $a, string $s): array
    {
        return [
            'primary' => self::lighten($p, 0.28), 'accent' => self::lighten($a, 0.22), 'secondary' => self::lighten($s, 0.25),
            'background' => self::mixToward('#0a0c12', $p, 0.05),
            'surface' => self::mixToward('#121621', $p, 0.06),
            'text' => '#f2f4f8',
            'muted' => '#9aa3b2',
            'border' => self::mixToward('#232a38', $p, 0.06),
        ];
    }

    /** Smart presets from the three brand colours (shown as swatches in the Brand step). */
    public static function presetPalettes(): array
    {
        return [
            ['id' => 'ocean', 'label' => 'Ocean', 'primary' => '#2563eb', 'accent' => '#06b6d4', 'secondary' => '#7c3aed'],
            ['id' => 'royal', 'label' => 'Royal', 'primary' => '#4f46e5', 'accent' => '#ec4899', 'secondary' => '#f59e0b'],
            ['id' => 'forest', 'label' => 'Forest', 'primary' => '#166534', 'accent' => '#ca8a04', 'secondary' => '#0f766e'],
            ['id' => 'navy', 'label' => 'Navy & gold', 'primary' => '#1e3a8a', 'accent' => '#b45309', 'secondary' => '#0f766e'],
            ['id' => 'berry', 'label' => 'Berry', 'primary' => '#be185d', 'accent' => '#7c3aed', 'secondary' => '#f97316'],
            ['id' => 'sunset', 'label' => 'Sunset', 'primary' => '#c2410c', 'accent' => '#db2777', 'secondary' => '#f59e0b'],
            ['id' => 'teal', 'label' => 'Teal', 'primary' => '#0f766e', 'accent' => '#f59e0b', 'secondary' => '#1e3a8a'],
            ['id' => 'mono', 'label' => 'Graphite', 'primary' => '#0f172a', 'accent' => '#10b981', 'secondary' => '#6366f1'],
        ];
    }

    /**
     * AI copy job, one page per call (poll-driven, so it works on shared hosting and
     * Docker without workers or long requests). Returns the updated draft.
     */
    /** Set by bin/ai-worker.php: use the gateway job API (no HTTP request timeouts in the CLI). */
    public static bool $preferAsync = false;

    /**
     * Advance one page of a running AI-copy job for $uid (lock, step, merge with autosaves, save).
     * Shared by the browser poll route and the background worker.
     */
    public static function advance(int $uid): array
    {
        $d = WizardDraft::load($uid);
        $job = (array) ($d['ai'] ?? []);
        if (($job['status'] ?? '') !== 'running') {
            return $d;
        }
        // One step at a time (two tabs, the worker, or a retry while the gateway is still answering).
        if (!empty($job['lock']) && time() - (int) $job['lock'] < 150) {
            $d['_busy'] = true;
            return $d;
        }
        $d['ai']['lock'] = time();
        WizardDraft::save($uid, $d);
        $after = self::aiCopyStep($d);
        // Re-read so autosaves that happened while the AI was answering are not lost.
        $fresh = WizardDraft::load($uid);
        if (($fresh['ai']['status'] ?? '') === 'running' && (int) ($fresh['ai']['started'] ?? 0) === (int) ($after['ai']['started'] ?? 0)) {
            $fresh['copy'] = (array) ($after['copy'] ?? []);
            $fresh['ai'] = (array) ($after['ai'] ?? []);
        }
        unset($fresh['ai']['lock']);
        return WizardDraft::save($uid, $fresh);
    }

    /** @return list<int> user ids with a running AI-copy job */
    public static function runningUsers(): array
    {
        $rows = \Mova\Core\Database::fetchAll(
            "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'setup_wizard_draft_%'"
        );
        $out = [];
        foreach ($rows as $r) {
            $d = json_decode((string) $r['setting_value'], true);
            if (is_array($d) && (($d['ai']['status'] ?? '') === 'running')) {
                $out[] = (int) substr((string) $r['setting_key'], strlen('setup_wizard_draft_'));
            }
        }
        return array_values(array_filter($out));
    }

    public static function aiCopyStep(array $d): array
    {
        $kit = KitRepository::load((string) ($d['kit'] ?? ''));
        $job = (array) ($d['ai'] ?? []);
        if (!$kit || ($job['status'] ?? '') !== 'running') {
            return $d;
        }
        $slugs = array_column($kit['pages'] ?? [], 'slug');
        $done = (array) ($job['done'] ?? []);
        $next = null;
        foreach ($slugs as $sl) {
            if (!in_array($sl, $done, true)) {
                $next = $sl;
                break;
            }
        }
        if ($next === null) {
            $job['status'] = 'done';
            $d['ai'] = $job;
            return $d;
        }
        $page = KitRenderer::page($kit, $next);
        $vars = WizardDraft::vars($d);
        $filled = KitRenderer::fill($page['sections'] ?? [], $vars);
        $copy = null;
        try {
            $copy = self::aiRewriteSections($filled, $d, (string) ($page['title'] ?? $next));
        } catch (\Throwable $e) {
            $job['errors'] = (int) ($job['errors'] ?? 0) + 1;
            $job['last_error'] = 'AI unavailable — kept the default copy for ' . ($page['title'] ?? $next) . '.';
        }
        if (is_array($copy)) {
            $d['copy'][$next] = $copy;
        }
        $done[] = $next;
        $job['done'] = $done;
        $job['total'] = count($slugs);
        $job['current'] = $page['title'] ?? $next;
        if (count($done) >= count($slugs)) {
            $job['status'] = 'done';
        }
        $d['ai'] = $job;
        return $d;
    }

    /**
     * Pull a list of section objects out of a model reply. Accepts a JSON array, an object wrapping
     * one ({"sections": [...]}), or — as smaller models often emit — several top-level objects
     * written one after another without the surrounding array. Markdown fences are ignored.
     *
     * @return list<array<string,mixed>>|null
     */
    public static function parseSectionsJson(string $raw): ?array
    {
        $raw = trim((string) preg_replace('/^\s*```(?:json)?\s*$|^\s*```\s*$/mi', '', trim($raw)));
        $isList = static fn ($v): bool => is_array($v) && $v !== [] && array_is_list($v) && is_array($v[0]);
        $whole = json_decode($raw, true);
        if ($whole === null && class_exists(\Mova\AI\AiText::class)) {
            // Raw newlines / trailing commas: repair structurally (keeps \uXXXX escapes intact).
            $whole = json_decode(\Mova\AI\AiText::repairJson($raw), true);
        }
        if ($isList($whole)) {
            return $whole;
        }
        if (is_array($whole)) {
            foreach (['sections', 'data', 'items', 'page'] as $k) {
                if (isset($whole[$k]) && $isList($whole[$k])) {
                    return $whole[$k];
                }
            }
            if (isset($whole['type'])) {
                return [$whole];
            }
        }
        $start = strpos($raw, '[');
        $end = strrpos($raw, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $arr = json_decode(substr($raw, $start, $end - $start + 1), true);
            if ($isList($arr)) {
                return $arr;
            }
        }
        // Stream of top-level objects: scan balanced braces outside strings.
        $out = [];
        $depth = 0;
        $inStr = false;
        $esc = false;
        $from = -1;
        $len = strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            $c = $raw[$i];
            if ($inStr) {
                if ($esc) {
                    $esc = false;
                } elseif ($c === '\\') {
                    $esc = true;
                } elseif ($c === '"') {
                    $inStr = false;
                }
                continue;
            }
            if ($c === '"') {
                $inStr = true;
            } elseif ($c === '{') {
                if ($depth === 0) {
                    $from = $i;
                }
                $depth++;
            } elseif ($c === '}' && $depth > 0) {
                $depth--;
                if ($depth === 0 && $from >= 0) {
                    $obj = json_decode(substr($raw, $from, $i - $from + 1), true);
                    if (is_array($obj)) {
                        $out[] = $obj;
                    }
                    $from = -1;
                }
            }
        }
        return $out !== [] ? $out : null;
    }

    /** Ask the gateway to rewrite the copy fields of filled specs; returns merged specs or null. */
    private static function aiRewriteSections(array $filled, array $d, string $pageTitle): ?array
    {
        if (!class_exists(\Mova\AI\AiAssistService::class)) {
            return null;
        }
        $assist = new \Mova\AI\AiAssistService();
        if (!$assist->isConfigured()) {
            return null;
        }
        $facts = trim(implode("\n", array_filter([
            'Name: ' . ($d['site_name'] ?? ''),
            !empty($d['tagline']) ? 'Tagline: ' . $d['tagline'] : '',
            !empty($d['about']) ? 'About: ' . $d['about'] : '',
            !empty($d['city']) ? 'Location: ' . $d['city'] : '',
            'Site type: ' . ($d['pack'] ?? 'business'),
        ])));
        $system = 'You are a senior website copywriter. You receive a JSON array of page sections. '
            . 'Return the SAME JSON structure (same keys, same array lengths, same order) with only human-readable text values rewritten '
            . 'to fit the organisation described. Never change keys like type, variant, href, icon, art, anchor, tone, columns, featured, value of prices. '
            . 'Never invent phone numbers, emails, addresses, prices, statistics, awards or named people — keep those values exactly as given. '
            . 'Keep each text roughly the same length. Warm, confident, plain English. Reply with JSON only, no markdown.';
        $user = "Organisation facts:\n" . $facts . "\n\nPage: " . $pageTitle . "\nSections JSON:\n" . json_encode($filled, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $msgs = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
        $assist = $assist->forFeature('wizard.copy');
        if (self::$preferAsync) {
            try {
                $raw = $assist->chatRawAsync($msgs, 3500, 300, 0.6);
            } catch (\Throwable $e) {
                $raw = $assist->chatRaw($msgs, 3500, 120, 0.6);
            }
        } else {
            $raw = $assist->chatRaw($msgs, 3500, 120, 0.6);
        }
        $ai = self::parseSectionsJson((string) $raw);
        if (is_array($ai)) {
            $ai = \Mova\AI\AiText::cleanDeep($ai);
        }
        if (!is_array($ai) || count($ai) !== count($filled)) {
            return null;
        }
        // Protect numbers/people: stats values, prices and names stay as authored.
        $merged = KitRenderer::mergeCopy($filled, $ai);
        foreach ($merged as $i => $sec) {
            foreach (['items', 'plans', 'stats'] as $k) {
                foreach ((array) ($sec[$k] ?? []) as $j => $it) {
                    if (!is_array($it)) {
                        continue;
                    }
                    foreach (['value', 'name', 'price', 'price_b', 'href', 'day', 'month', 'time', 'date'] as $keep) {
                        if (isset($filled[$i][$k][$j][$keep])) {
                            $merged[$i][$k][$j][$keep] = $filled[$i][$k][$j][$keep];
                        }
                    }
                }
            }
        }
        return $merged;
    }

    /**
     * Publish the Quick setup draft: settings, design tokens, theme, pages, nav, footer, home.
     * @return array{ok:bool,pages:list<array>,message:string,home_url:string}
     */
    public static function publishDraft(array $d): array
    {
        $kit = KitRepository::load((string) ($d['kit'] ?? ''));
        if (!$kit || empty($kit['pages'])) {
            return ['ok' => false, 'error' => 'Please choose a kit first.', 'pages' => []];
        }
        $pack = KitRepository::normalizePack((string) ($kit['pack'] ?? ($d['pack'] ?? 'generic')));
        $d['pack'] = $pack;
        $vars = WizardDraft::vars($d);
        $status = (($d['status'] ?? 'published') === 'draft') ? 'draft' : 'published';

        // Brand & settings
        DesignConfig::saveSetting('site_name', $vars['site_name']);
        DesignConfig::saveSetting('site_description', $vars['tagline']);
        if (!empty($d['logo_url'])) {
            DesignConfig::saveSetting('logo_url', (string) $d['logo_url']);
        }
        $tok = WizardDraft::tokens($d, $kit);
        $tokens = DesignConfig::tokens();
        $tokens['colors'] = array_merge($tokens['colors'] ?? [], $tok['colors']);
        $tokens['colors_dark'] = array_merge($tokens['colors_dark'] ?? [], $tok['colors_dark']);
        $tokens['typography'] = array_merge($tokens['typography'] ?? [], $tok['typography']);
        DesignConfig::saveTokens($tokens);
        DesignConfig::saveSetting('brand_color', $tok['colors']['primary']);
        $layout = DesignConfig::layout();
        $layout['footer'] = array_merge($layout['footer'] ?? [], ['style' => 'columns']);
        DesignConfig::saveLayout($layout);
        if (!empty($d['theme'])) {
            try {
                (new \Mova\Theme\ThemeManager())->setActive((string) $d['theme']);
            } catch (\Throwable $e) {
            }
        }

        $repo = new ContentRepository();
        $authorId = Auth::id();
        $created = [];
        $slugMap = [];
        $kitSlugs = [];
        foreach ($kit['pages'] as $page) {
            $slug = (string) $page['slug'];
            $kitSlugs[] = $slug;
            $copy = isset($d['copy'][$slug]) && is_array($d['copy'][$slug]) ? $d['copy'][$slug] : null;
            $body = KitRenderer::render($kit, $page, $vars, $copy);
            $title = KitRenderer::fill((string) ($page['title'] ?? ucfirst($slug)), $vars);
            $meta = [
                'created_by' => self::META_TAG, 'wizard_pack' => $pack, 'wizard_role' => (string) ($page['role'] ?? 'generic'),
                'wizard_kit' => (string) $kit['id'], 'page_layout' => 'canvas',
                'meta_description' => KitRenderer::fill((string) ($page['description'] ?? ''), $vars),
                // v1 kits used dev-mode raw CSS/JS; clear it so sections.css + theme apply.
                'editor_mode' => '', 'raw_css' => '', 'raw_js' => '', 'use_site_chrome' => '', 'hide_article_chrome' => '',
            ];
            $existing = $repo->findBySlugAnyStatus($slug);
            if ($existing && (($existing['meta']['created_by'] ?? '') === self::META_TAG || ($existing['status'] ?? '') === 'draft')) {
                $repo->update((int) $existing['id'], ['title' => $title, 'body' => $body, 'status' => $status, 'type' => 'page',
                    'author_id' => $authorId ?? $existing['author_id'] ?? null, 'meta' => array_merge($existing['meta'] ?? [], $meta)]);
                $id = (int) $existing['id'];
                $finalSlug = $slug;
            } elseif ($existing) {
                $finalSlug = $slug . '-site';
                $alt = $repo->findBySlugAnyStatus($finalSlug);
                if ($alt) {
                    $repo->update((int) $alt['id'], ['title' => $title, 'body' => $body, 'status' => $status, 'type' => 'page', 'meta' => array_merge($alt['meta'] ?? [], $meta)]);
                    $id = (int) $alt['id'];
                } else {
                    $id = $repo->create(['title' => $title, 'slug' => $finalSlug, 'body' => $body, 'status' => $status, 'type' => 'page', 'author_id' => $authorId, 'meta' => $meta]);
                }
            } else {
                $finalSlug = $slug;
                $id = $repo->create(['title' => $title, 'slug' => $slug, 'body' => $body, 'status' => $status, 'type' => 'page', 'author_id' => $authorId, 'meta' => $meta]);
            }
            $slugMap['/' . $slug] = '/' . $finalSlug;
            $created[] = ['id' => (int) $id, 'slug' => $finalSlug, 'title' => $title, 'role' => (string) ($page['role'] ?? 'generic'),
                'url' => ($page['role'] ?? '') === 'front' ? '/' : '/' . $finalSlug];
        }
        // Retire wizard pages from a previously chosen kit (kept as drafts, never deleted).
        try {
            $rows = Database::fetchAll("SELECT c.id, c.slug FROM content c JOIN content_meta m ON m.content_id = c.id WHERE m.meta_key = 'created_by' AND m.meta_value = :t AND c.status = 'published'", ['t' => self::META_TAG]);
            $keep = array_map(static fn ($u) => ltrim($u, '/'), array_values($slugMap));
            foreach ($rows as $r) {
                if (!in_array((string) $r['slug'], $keep, true)) {
                    $repo->update((int) $r['id'], ['status' => 'draft']);
                }
            }
        } catch (\Throwable $e) {
        }

        // Rewrite links if any slug had to change (existing user pages are never overwritten)
        $settings = KitRenderer::siteSettings($kit, $vars);
        $fix = static function (string $s) use ($slugMap): string {
            foreach ($slugMap as $from => $to) {
                if ($from !== $to) {
                    $s = preg_replace('~(\||"url":")' . preg_quote($from, '~') . '(?=["\n]|$)~', '$1' . $to, $s) ?? $s;
                }
            }
            return $s;
        };
        DesignConfig::saveSetting('nav_links', $fix($settings['nav_links']));
        DesignConfig::saveSetting(self::FOOTER_COLUMNS_KEY, $fix($settings['footer_columns']));
        DesignConfig::saveSetting('nav_cta', $fix($settings['nav_cta']));
        DesignConfig::saveSetting('header_assembly_slug', '');
        DesignConfig::saveSetting('footer_assembly_slug', '');
        if ($slugMap && array_filter($slugMap, static fn ($v, $k) => $v !== $k, ARRAY_FILTER_USE_BOTH)) {
            foreach ($created as $c) {
                $content = $repo->find($c['id']);
                if ($content) {
                    $repo->update($c['id'], ['body' => $fix(str_replace('href="', 'href="|', (string) $content['body']))]);
                    $content = $repo->find($c['id']);
                    $repo->update($c['id'], ['body' => str_replace('href="|', 'href="', (string) $content['body'])]);
                }
            }
        }
        foreach ($created as $c) {
            if ($c['role'] === 'front') {
                DesignConfig::saveSetting('homepage_content_id', (string) $c['id']);
                break;
            }
        }
        if (!empty($d['use_ai']) && !empty($d['about'])) {
            self::seedKnowledgeBank($vars['site_name'], (string) $d['about'], (string) $d['about'], trim(($d['address'] ?? '') . "\n" . ($d['phone'] ?? '') . "\n" . ($d['email'] ?? '')));
        }
        if (class_exists(\Mova\Cache\PageCache::class)) {
            try {
                \Mova\Cache\PageCache::flush();
            } catch (\Throwable $e) {
            }
        }
        return ['ok' => true, 'pages' => $created, 'home_url' => '/', 'message' => count($created) . ' pages ' . ($status === 'published' ? 'published' : 'saved as drafts') . '.'];
    }
}

