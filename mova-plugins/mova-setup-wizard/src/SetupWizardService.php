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

        $vars = [
            '{{site_name}}' => htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'),
            '{{tagline}}' => htmlspecialchars($tagline !== '' ? $tagline : 'Content that moves.', ENT_QUOTES, 'UTF-8'),
            '{{about_seed}}' => $about !== ''
                ? nl2br(htmlspecialchars($about, ENT_QUOTES, 'UTF-8'))
                : 'Tell visitors who you are and what you stand for.',
            '{{contact_seed}}' => $contact !== ''
                ? nl2br(htmlspecialchars($contact, ENT_QUOTES, 'UTF-8'))
                : 'Address, phone, and email go here.',
            '{{year}}' => date('Y'),
        ];

        $repo = new ContentRepository();
        $authorId = Auth::id();
        $created = [];
        $navLines = [];

        foreach ($pack['pages'] as $page) {
            $slug = (string) $page['slug'];
            $title = (string) $page['title'];
            $role = (string) ($page['role'] ?? 'generic');
            $body = str_replace(array_keys($vars), array_values($vars), (string) $page['body']);

            // Phase 2: optional AI expansion for about/contact roles
            if ($useAi && in_array($role, ['about', 'contact', 'team', 'programs'], true)) {
                $aiBody = self::expandWithAi($role, $siteName, $tagline, $about, $contact, $seedText);
                if ($aiBody !== null && $aiBody !== '') {
                    $body = $aiBody;
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
                $navLines[] = ($item['label'] ?? 'Page') . '|' . ($item['url'] ?? '/');
            }
        }
        DesignConfig::saveSetting('nav_links', implode("\n", $navLines));

        // Footer columns
        $columns = [];
        foreach ($pack['footer_columns'] ?? [] as $col) {
            $entry = [
                'title' => (string) ($col['title'] ?? ''),
                'text' => isset($col['text'])
                    ? str_replace(array_keys($vars), array_values($vars), (string) $col['text'])
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
                        'label' => (string) ($link['label'] ?? ''),
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
                    $clean = self::sanitizeAiHtml($out);
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
}
