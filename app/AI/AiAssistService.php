<?php
/**
 * Mova — AI assist (optional). Never auto-publishes.
 * Uses OpenAI-compatible chat completions when configured; otherwise heuristics.
 */

namespace Mova\AI;

use Mova\Core\Bootstrap;
use Mova\Core\Database;

class AiAssistService
{
    public function isConfigured(): bool
    {
        $key = $this->setting('ai_api_key', '');
        return $key !== '';
    }

    public function providerLabel(): string
    {
        return $this->isConfigured()
            ? ($this->setting('ai_provider', 'openai') ?: 'openai')
            : 'heuristic';
    }

    /**
     * @return array{ok:bool,result?:string,error?:string,provider:string}
     */
    public function run(string $action, array $context): array
    {
        $title = trim((string) ($context['title'] ?? ''));
        $body = trim(strip_tags((string) ($context['body'] ?? '')));
        $excerpt = trim((string) ($context['excerpt'] ?? ''));

        if ($this->isConfigured()) {
            try {
                $result = $this->callProvider($action, $title, $body, $excerpt);
                return ['ok' => true, 'result' => $result, 'provider' => $this->providerLabel()];
            } catch (\Throwable $e) {
                // Fall through to heuristic
                $heuristic = $this->heuristic($action, $title, $body, $excerpt);
                return [
                    'ok' => true,
                    'result' => $heuristic,
                    'provider' => 'heuristic',
                    'error' => 'AI provider failed: ' . $e->getMessage() . ' — used local suggestions.',
                ];
            }
        }

        return [
            'ok' => true,
            'result' => $this->heuristic($action, $title, $body, $excerpt),
            'provider' => 'heuristic',
        ];
    }

    private function callProvider(string $action, string $title, string $body, string $excerpt): string
    {
        $endpoint = rtrim($this->setting('ai_api_url', 'https://api.openai.com/v1'), '/') . '/chat/completions';
        $model = $this->setting('ai_model', 'gpt-4o-mini') ?: 'gpt-4o-mini';
        $key = $this->setting('ai_api_key', '');

        $prompts = [
            'outline' => "Create a clear content outline (markdown bullet headings) for an article titled \"{$title}\". Body context:\n" . mb_substr($body, 0, 3000),
            'title' => "Suggest 5 improved SEO-friendly titles (one per line) for content currently titled \"{$title}\". Context:\n" . mb_substr($body, 0, 2000),
            'excerpt' => "Write a compelling 1–2 sentence excerpt (max 160 chars preference) for \"{$title}\".\n" . mb_substr($body, 0, 2500),
            'meta' => "Write an SEO meta description (150–160 characters) for \"{$title}\".\n" . mb_substr($body, 0, 2500),
            'keywords' => "Suggest 8 relevant keywords/phrases (comma-separated) for \"{$title}\".\n" . mb_substr($body, 0, 2000),
            'faq' => "Generate 4 FAQ items (Q: / A:) based on \"{$title}\" and this content:\n" . mb_substr($body, 0, 3000),
            'summarize' => "Summarize this content in 3 short bullets:\nTitle: {$title}\n" . mb_substr($body, 0, 4000),
            'improve' => "Suggest concrete improvements for clarity, structure, and SEO (bullet list) for \"{$title}\":\n" . mb_substr($body, 0, 3500),
        ];

        $userPrompt = $prompts[$action] ?? $prompts['summarize'];

        $payload = json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful editorial assistant for the Mova CMS. Be concise. Never invent publishing actions.'],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.6,
            'max_tokens' => 800,
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $key,
                ]),
                'content' => $payload,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($endpoint, false, $ctx);
        if ($raw === false) {
            throw new \RuntimeException('No response from AI provider');
        }
        $data = json_decode($raw, true);
        $text = $data['choices'][0]['message']['content'] ?? null;
        if (!$text) {
            throw new \RuntimeException($data['error']['message'] ?? 'Invalid AI response');
        }
        return trim($text);
    }

    private function heuristic(string $action, string $title, string $body, string $excerpt): string
    {
        $words = preg_split('/\s+/', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);

        switch ($action) {
            case 'outline':
                $lines = ["# " . ($title ?: 'Untitled')];
                $lines[] = "## Introduction";
                $lines[] = "## Key points";
                if ($wordCount > 200) {
                    $lines[] = "## Details";
                }
                $lines[] = "## Conclusion";
                return implode("\n", $lines);

            case 'title':
                $base = $title ?: 'Untitled';
                return implode("\n", [
                    $base,
                    $base . ': A Practical Guide',
                    'How to ' . $base,
                    $base . ' Explained',
                    'Understanding ' . $base,
                ]);

            case 'excerpt':
                if ($excerpt) {
                    return mb_substr($excerpt, 0, 160);
                }
                $plain = preg_replace('/\s+/', ' ', $body);
                return mb_substr($plain, 0, 157) . (mb_strlen($plain) > 157 ? '…' : '');

            case 'meta':
                $src = $excerpt ?: $body;
                $plain = preg_replace('/\s+/', ' ', strip_tags($src));
                $meta = mb_substr($plain, 0, 155);
                if (mb_strlen($plain) > 155) {
                    $meta .= '…';
                }
                return $meta ?: ($title . ' — published on Mova.');

            case 'keywords':
                $text = strtolower($title . ' ' . $body);
                $text = preg_replace('/[^a-z0-9\s-]/', ' ', $text);
                $parts = array_filter(preg_split('/\s+/', $text) ?: [], static fn ($w) => strlen($w) > 3);
                $freq = array_count_values($parts);
                arsort($freq);
                $stop = ['this', 'that', 'with', 'from', 'your', 'have', 'will', 'been', 'about', 'into', 'more', 'when', 'what'];
                $keys = [];
                foreach ($freq as $w => $c) {
                    if (in_array($w, $stop, true)) {
                        continue;
                    }
                    $keys[] = $w;
                    if (count($keys) >= 8) {
                        break;
                    }
                }
                return implode(', ', $keys) ?: 'content, guide, article';

            case 'faq':
                $t = $title ?: 'this topic';
                return "Q: What is {$t}?\nA: A brief explanation based on your draft.\n\nQ: Who is {$t} for?\nA: Readers looking for practical guidance.\n\nQ: How do I get started?\nA: Follow the steps outlined in the article.\n\nQ: What should I avoid?\nA: Common pitfalls covered in the main sections.";

            case 'summarize':
                return "- Topic: " . ($title ?: 'Untitled') . "\n- Length: {$wordCount} words\n- Focus: " . mb_substr(preg_replace('/\s+/', ' ', $body), 0, 120) . '…';

            case 'improve':
            default:
                $tips = [];
                if ($title === '') {
                    $tips[] = 'Add a clear, specific title.';
                }
                if ($wordCount < 100) {
                    $tips[] = 'Expand the body — under 100 words may rank poorly.';
                }
                if ($excerpt === '') {
                    $tips[] = 'Add an excerpt for listings and SEO.';
                }
                if (!preg_match('/<h2/i', $contextBody = (string) ($GLOBALS['__unused'] ?? '')) && $wordCount > 150) {
                    $tips[] = 'Add H2 subheadings to improve structure.';
                }
                if (!$tips) {
                    $tips[] = 'Structure looks okay. Consider internal links and a stronger opening.';
                }
                return '• ' . implode("\n• ", $tips);
        }
    }

    private function setting(string $key, string $default = ''): string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                $rows = Database::fetchAll("SELECT setting_key, setting_value FROM settings");
                foreach ($rows as $r) {
                    $cache[$r['setting_key']] = (string) $r['setting_value'];
                }
            } catch (\Throwable $e) {
                $cache = [];
            }
        }
        if (isset($cache[$key]) && $cache[$key] !== '') {
            return $cache[$key];
        }
        return (string) Bootstrap::config('ai.' . $key, $default);
    }
}
