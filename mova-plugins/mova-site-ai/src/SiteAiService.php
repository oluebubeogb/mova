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

    /** @return array{reply:string,links:list<array{label:string,path:string}>} */
    public static function chat(string $message, string $pagePath = '/'): array
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

        $system = "You are {$name}, the on-site assistant for this website. "
            . "You help visitors only — never mention HQ, admin, CMS, or internal tools. "
            . "Be warm, concise, and useful. Use knowledge bank facts when present. "
            . "When suggesting a page, include a site path starting with /. "
            . "Respond with ONLY JSON: {\"reply\":\"...\",\"links\":[{\"label\":\"...\",\"path\":\"/slug\"}]}\n\n"
            . "Published pages:\n" . implode("\n", array_slice($pageLines, 0, 30)) . "\n\n"
            . "Knowledge bank excerpts:\n" . ($kbBlock !== '' ? $kbBlock : "(none)\n")
            . "Visitor is currently on: {$pagePath}";

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
                            if (!is_array($l)) continue;
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

        // unique links
        $seen = [];
        $unique = [];
        foreach ($outLinks as $l) {
            $k = $l['path'];
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $unique[] = $l;
        }

        return ['reply' => $reply, 'links' => array_slice($unique, 0, 5)];
    }

    public static function paletteDefaults(): array
    {
        try {
            $t = DesignConfig::tokens();
            return [
                'primary' => $t['colors']['primary'] ?? '#2563eb',
                'accent' => $t['colors']['accent'] ?? '#7c3aed',
            ];
        } catch (\Throwable $e) {
            return ['primary' => '#2563eb', 'accent' => '#7c3aed'];
        }
    }
}
