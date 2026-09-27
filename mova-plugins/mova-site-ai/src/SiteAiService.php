<?php
namespace MovaSiteAi;

use Mova\AI\AiAssistService;
use Mova\Content\ContentRepository;
use Mova\Core\Database;
use Mova\Theme\DesignConfig;

class SiteAiService
{
    public static function config(): array
    {
        KnowledgeBank::ensureSchema();
        $defaults = [
            'enabled' => false,
            'name' => 'Site Assistant',
            'welcome' => '',
            'primary' => '',
            'accent' => '',
            'bg_light' => '',
            'bg_dark' => '',
        ];
        try {
            $row = Database::fetch(
                "SELECT setting_value FROM site_ai_settings WHERE setting_key = 'config'"
            );
            if ($row && $row['setting_value']) {
                $decoded = json_decode((string) $row['setting_value'], true);
                if (is_array($decoded)) {
                    return array_merge($defaults, $decoded);
                }
            }
        } catch (\Throwable $e) {
        }
        return $defaults;
    }

    public static function saveConfig(array $cfg): void
    {
        KnowledgeBank::ensureSchema();
        $current = self::config();
        $merged = array_merge($current, $cfg);
        Database::query(
            "INSERT INTO site_ai_settings (setting_key, setting_value, updated_at) VALUES ('config', :v, :t)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v2, updated_at = :t2",
            [
                'v' => json_encode($merged),
                't' => date('c'),
                'v2' => json_encode($merged),
                't2' => date('c'),
            ]
        );
    }

    /**
     * @return array{reply:string,links:list<array{label:string,path:string}>}
     */
    public static function chat(string $message, string $pagePath = '/', string $pageTitle = ''): array
    {
        $cfg = self::config();
        $name = (string) ($cfg['name'] ?? 'Assistant');
        $message = trim($message);
        if ($message === '') {
            return ['reply' => '', 'links' => []];
        }

        $kb = KnowledgeBank::search($message, 6);
        $kbBlock = '';
        foreach ($kb as $hit) {
            $kbBlock .= "Source: {$hit['title']}\n{$hit['content']}\n\n";
        }

        // Site pages (public content only) — not HQ
        $repo = new ContentRepository();
        $pages = $repo->all(['status' => 'published'], 40, 0);
        $pageLines = [];
        $links = [];
        $q = mb_strtolower($message);
        foreach ($pages as $p) {
            $title = (string) ($p['title'] ?? '');
            $slug = (string) ($p['slug'] ?? '');
            $path = '/' . ltrim($slug, '/');
            $pageLines[] = "- {$title} → {$path}";
            $hay = mb_strtolower($title . ' ' . $slug);
            if ($title !== '' && (str_contains($q, mb_strtolower($title)) || str_contains($hay, $q))) {
                $links[] = ['label' => $title, 'path' => $path];
            }
        }

        // Current page content for summarize / explain intents
        $currentPageBlock = self::currentPageContext($pagePath, $pageTitle, $message);

        $system = "You are {$name}, the on-site assistant for this website. "
            . "You help visitors only — never mention HQ, admin, CMS, or internal tools. "
            . "Be warm, concise, and useful. Use knowledge bank facts when present. "
            . "When suggesting a page, put it in the links array with a human label and path starting with /. "
            . "In the reply text you may use markdown links like [Label](/path). Prefer that over bare /slug. "
            . "If the visitor asks to summarize or explain the current page, use the CURRENT PAGE CONTENT below. "
            . "Respond with ONLY JSON: {\"reply\":\"...\",\"links\":[{\"label\":\"...\",\"path\":\"/slug\"}]}\n\n"
            . "Published pages:\n" . implode("\n", array_slice($pageLines, 0, 30)) . "\n\n"
            . "Knowledge bank excerpts:\n" . ($kbBlock !== '' ? $kbBlock : "(none)\n")
            . "Visitor is currently on: {$pagePath}"
            . ($pageTitle !== '' ? " ({$pageTitle})" : '') . "\n"
            . $currentPageBlock;

        $assist = new AiAssistService();
        $reply = '';
        $outLinks = $links;
        try {
            if ($assist->isConfigured()) {
                $raw = $assist->chatRaw([
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $message],
                ], 1200);
                $raw = trim($raw);
                if (preg_match('/\{.*\}/s', $raw, $m)) {
                    $data = json_decode($m[0], true);
                    if (is_array($data)) {
                        $reply = trim((string) ($data['reply'] ?? ''));
                        foreach ($data['links'] ?? [] as $l) {
                            if (!is_array($l)) {
                                continue;
                            }
                            $path = (string) ($l['path'] ?? '');
                            if (str_starts_with($path, '/') && !str_starts_with($path, '/hq')) {
                                $outLinks[] = [
                                    'label' => (string) ($l['label'] ?? $path),
                                    'path' => $path,
                                ];
                            }
                        }
                    }
                }
                if ($reply === '') {
                    $reply = preg_replace('/```json[\s\S]*?```/i', '', $raw) ?? $raw;
                    if (str_starts_with(trim($reply), '{')) {
                        $reply = 'Happy to help — ask me about our pages or products.';
                    }
                }
            }
        } catch (\Throwable $e) {
            $reply = '';
        }

        if ($reply === '') {
            if ($kb !== []) {
                $reply = "Here's what I found related to your question:\n\n" . mb_substr($kb[0]['content'], 0, 400);
            } elseif ($outLinks !== []) {
                $reply = 'I can help you open these pages:';
            } else {
                $reply = "Hi — I'm {$name}. Ask about our pages, products, or policies.";
            }
        }

        // Extract any markdown links from reply into links array
        if (preg_match_all('/\[([^\]]+)\]\((\/[^)\s]+)\)/', $reply, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $path = $m[2];
                if (str_starts_with($path, '/') && !str_starts_with($path, '/hq')) {
                    $outLinks[] = ['label' => $m[1], 'path' => $path];
                }
            }
        }

        // unique links
        $seen = [];
        $unique = [];
        foreach ($outLinks as $l) {
            $k = $l['path'];
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $unique[] = $l;
        }

        return ['reply' => $reply, 'links' => array_slice($unique, 0, 5)];
    }

    /**
     * Load published content for the path when user asks about the current page.
     */
    private static function currentPageContext(string $pagePath, string $pageTitle, string $message): string
    {
        $q = mb_strtolower($message);
        $wantsPage = (bool) preg_match(
            '/\b(summar(y|ize|ise)|explain|what is (this|the) page|tell me (more )?about (this|the) page|current page|this page)\b/i',
            $message
        );
        // Always provide a short context when path is known
        $slug = trim($pagePath, '/');
        if ($slug === '') {
            return $pageTitle !== '' ? "Page title: {$pageTitle}\n" : '';
        }
        // Home or multi-segment: try last segment or full slug
        $candidates = [$slug];
        if (str_contains($slug, '/')) {
            $parts = explode('/', $slug);
            $candidates[] = end($parts);
        }
        try {
            $repo = new ContentRepository();
            $entity = null;
            foreach ($candidates as $c) {
                $entity = $repo->findBySlug($c);
                if ($entity) {
                    break;
                }
            }
            if (!$entity) {
                return $pageTitle !== '' ? "Page title: {$pageTitle}\n" : '';
            }
            $title = (string) ($entity['title'] ?? $pageTitle);
            $excerpt = (string) ($entity['excerpt'] ?? '');
            $body = (string) ($entity['body'] ?? '');
            $bodyPlain = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $bodyPlain = preg_replace('/\s+/', ' ', $bodyPlain) ?? $bodyPlain;
            $bodyPlain = mb_substr($bodyPlain, 0, $wantsPage ? 2500 : 800);
            $block = "CURRENT PAGE CONTENT:\nTitle: {$title}\n";
            if ($excerpt !== '') {
                $block .= "Excerpt: {$excerpt}\n";
            }
            if ($bodyPlain !== '') {
                $block .= "Body:\n{$bodyPlain}\n";
            }
            return $block;
        } catch (\Throwable $e) {
            return $pageTitle !== '' ? "Page title: {$pageTitle}\n" : '';
        }
    }

    public static function paletteDefaults(): array
    {
        try {
            $t = DesignConfig::tokens();
            return [
                'primary' => $t['colors']['primary'] ?? '#2563eb',
                'accent' => $t['colors']['accent'] ?? $t['colors']['brand-accent'] ?? '#7c3aed',
                'surface' => $t['colors']['surface'] ?? $t['colors']['bg'] ?? '#ffffff',
                'surface_dark' => $t['colors_dark']['surface'] ?? $t['colors_dark']['bg'] ?? '#1e293b',
            ];
        } catch (\Throwable $e) {
            return [
                'primary' => '#2563eb',
                'accent' => '#7c3aed',
                'surface' => '#ffffff',
                'surface_dark' => '#1e293b',
            ];
        }
    }
}
