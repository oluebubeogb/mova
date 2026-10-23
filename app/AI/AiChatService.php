<?php
/**
 * Mova AI — HQ chat sessions, memory, navigate + write actions (Phases 1–5).
 */

namespace Mova\AI;

use Mova\Auth\Auth;
use Mova\Core\Database;

class AiChatService
{
    private AiAssistService $assist;

    public function __construct(?AiAssistService $assist = null)
    {
        $this->assist = $assist ?? new AiAssistService();
    }

    /** @return list<array<string,mixed>> */
    public function listSessions(int $userId, int $limit = 30): array
    {
        return Database::fetchAll(
            "SELECT id, title, created_at, updated_at FROM ai_sessions
             WHERE user_id = :u ORDER BY updated_at DESC LIMIT {$limit}",
            ['u' => $userId]
        );
    }

    public function getSession(int $sessionId, int $userId): ?array
    {
        return Database::fetch(
            "SELECT * FROM ai_sessions WHERE id = :id AND user_id = :u",
            ['id' => $sessionId, 'u' => $userId]
        );
    }

    public function deleteSession(int $sessionId, int $userId): bool
    {
        $session = $this->getSession($sessionId, $userId);
        if (!$session) {
            return false;
        }
        Database::query("DELETE FROM ai_messages WHERE session_id = :s", ['s' => $sessionId]);
        Database::query("DELETE FROM ai_sessions WHERE id = :id AND user_id = :u", ['id' => $sessionId, 'u' => $userId]);
        return true;
    }

    public function createSession(int $userId, string $title = 'New chat'): array
    {
        $now = date('c');
        $id = Database::insert('ai_sessions', [
            'user_id' => $userId,
            'title' => mb_substr($title, 0, 120) ?: 'New chat',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->getSession($id, $userId) ?? [
            'id' => $id,
            'user_id' => $userId,
            'title' => $title,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function listMessages(int $sessionId, int $userId, int $limit = 100): array
    {
        $session = $this->getSession($sessionId, $userId);
        if (!$session) {
            return [];
        }
        return Database::fetchAll(
            "SELECT id, role, content, meta, created_at FROM ai_messages
             WHERE session_id = :s ORDER BY id ASC LIMIT {$limit}",
            ['s' => $sessionId]
        );
    }

    /**
     * @param array{route?:string,area?:string,entityId?:int|null,layer?:string|null} $pageContext
     * @return array{ok:bool,session_id:int,reply:string,actions:list<array>,provider:string,error?:string}
     */
    public function chat(int $userId, ?int $sessionId, string $message, array $pageContext = [], array $options = []): array
    {
        $message = trim($message);
        if ($message === '') {
            return ['ok' => false, 'session_id' => 0, 'reply' => '', 'actions' => [], 'provider' => 'none', 'error' => 'Empty message'];
        }

        if (!$sessionId) {
            $title = mb_substr($message, 0, 60);
            $session = $this->createSession($userId, $title);
            $sessionId = (int) $session['id'];
        } else {
            $session = $this->getSession($sessionId, $userId);
            if (!$session) {
                $session = $this->createSession($userId, mb_substr($message, 0, 60));
                $sessionId = (int) $session['id'];
            }
        }

        $this->addMessage($sessionId, 'user', $message, null);

        // Local HQ map match (works even if LLM is down)
        $matches = HqMap::search($message, 5);
        $localActions = [];
        foreach ($matches as $m) {
            if (($m['score'] ?? 0) >= 2.0) {
                $localActions[] = [
                    'type' => 'navigate',
                    'label' => 'Open ' . $m['label'],
                    'path' => $m['path'],
                    'id' => $m['id'],
                ];
            }
        }

        $history = $this->listMessages($sessionId, $userId, 24);

        // Raw / original mode: undoctored model output (no Mova JSON schema, no CMS actions).
        $mode = strtolower(trim((string) ($options['mode'] ?? $pageContext['mode'] ?? '')));
        if ($mode === 'raw' || $mode === 'original' || $mode === 'general') {
            $llm = $this->callLlmRaw($message, $history, $options);
            $reply = $llm['reply'] ?? '';
            $actions = [];
            $provider = $llm['provider'] ?? 'heuristic';
            $meta = json_encode(['actions' => [], 'provider' => $provider, 'mode' => 'raw'], JSON_UNESCAPED_UNICODE);
            $this->addMessage($sessionId, 'assistant', $reply, $meta);
            return [
                'ok' => true,
                'session_id' => $sessionId,
                'reply' => $reply,
                'actions' => [],
                'provider' => $provider,
                'mode' => 'raw',
            ];
        }

        $llm = $this->callLlm($message, $history, $pageContext, $matches, $options);

        $reply = $llm['reply'] ?? '';
        $actions = $llm['actions'] ?? [];
        $provider = $llm['provider'] ?? 'heuristic';

        $entityId = (int) ($pageContext['entityId'] ?? 0);

        // Lift real HTML from the model reply into any empty/stub create|update body;
        // also sanitize titles that still include user instruction phrases.
        $replyHtml = $this->extractHtmlFromReply($reply);
        foreach ($actions as $i => $action) {
            $type = (string) ($action['type'] ?? '');
            if ($type !== 'create_content' && $type !== 'update_content') {
                continue;
            }
            $payload = is_array($action['payload'] ?? null) ? $action['payload'] : [];
            if (!empty($payload['title'])) {
                $cleaned = $this->cleanTitleCandidate((string) $payload['title']);
                if ($cleaned !== '' && $cleaned !== (string) $payload['title']) {
                    $payload['title'] = $cleaned;
                }
            }
            $body = (string) ($payload['body'] ?? '');
            if ($this->isStubBody($body) && $replyHtml !== '') {
                $payload['body'] = $replyHtml;
                if (trim((string) ($payload['excerpt'] ?? '')) === '') {
                    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($replyHtml)) ?? '');
                    $payload['excerpt'] = mb_substr($plain, 0, 160);
                }
                $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
                if (trim((string) ($meta['seo_title'] ?? '')) === '' && !empty($payload['title'])) {
                    $meta['seo_title'] = mb_substr((string) $payload['title'], 0, 60);
                }
                if (trim((string) ($meta['meta_description'] ?? '')) === '') {
                    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($replyHtml)) ?? '');
                    $meta['meta_description'] = mb_substr($plain, 0, 155);
                }
                if ($meta !== []) {
                    $payload['meta'] = $meta;
                }
            }
            // If body still stub and we have no reply HTML, try to rebuild title at least
            if ($type === 'create_content' && $this->isStubBody((string) ($payload['body'] ?? ''))) {
                $payload['title'] = $this->extractTitleFromMessage($message);
                if (empty($payload['meta']['seo_title'])) {
                    $payload['meta'] = array_merge(
                        is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
                        ['seo_title' => mb_substr((string) $payload['title'], 0, 60)]
                    );
                }
            }
            $actions[$i]['payload'] = $payload;
        }

        // Prefer update ONLY when the user is clearly revising the open draft.
        // Never strip a model create_content for a new topic just because another draft is open.
        $wantsChatOnly = (bool) preg_match('/(?:^|\n)\s*chat\s*:/i', $message);
        $wantsNewDraft = !$wantsChatOnly
            && $this->looksLikeCreateContent($message)
            && !$this->looksLikeContinueContent($message);
        $wantsContinue = $entityId > 0 && $this->looksLikeContinueContent($message) && !$wantsNewDraft;

        if ($wantsContinue && !$this->hasActionType($actions, 'update_content')) {
            // Keep any good create_content only if title clearly differs; otherwise force update on open id
            $hasCreate = $this->hasActionType($actions, 'create_content');
            if ($hasCreate) {
                // Model asked to create while user is continuing — convert to update on open draft
                $actions = array_values(array_filter($actions, static fn($a) => ($a['type'] ?? '') !== 'create_content'));
            }
            $actions[] = $this->buildUpdateContentAction($message, $entityId);
        } elseif ($wantsNewDraft && !$this->hasActionType($actions, 'create_content')
            && !$this->hasActionType($actions, 'update_content')) {
            // Pass reply HTML so heuristic create is not a dead stub when the model wrote in the reply
            $actions[] = $this->buildCreateContentAction($message, $replyHtml);
        } elseif ($wantsNewDraft && $this->hasActionType($actions, 'update_content') && $entityId > 0) {
            // Model wrongly targeted update while user asked for a brand-new draft — force create
            $actions = array_values(array_filter($actions, static fn($a) => ($a['type'] ?? '') !== 'update_content'));
            if (!$this->hasActionType($actions, 'create_content')) {
                $actions[] = $this->buildCreateContentAction($message, $replyHtml);
            }
        }

        // Heuristic: color change intent
        if (!$this->hasActionType($actions, 'update_design_tokens') && $this->looksLikeColorChange($message)) {
            $colorAction = $this->buildColorActionFromMessage($message);
            if ($colorAction !== null) {
                $actions[] = $colorAction;
            }
        }

        // Auto-run create_content (always draft) so the user gets a real link
        $executor = new AiActionService();
        $finalActions = [];
        foreach ($actions as $action) {
            $type = (string) ($action['type'] ?? '');
            if ($type === 'create_content' || $type === 'update_content') {
                $exec = $executor->execute($action, $userId);
                if (!empty($exec['ok']) && !empty($exec['result']['path'])) {
                    $path = (string) $exec['result']['path'];
                    $title = (string) ($exec['result']['title'] ?? 'Draft');
                    $isUpdate = $type === 'update_content';
                    $finalActions[] = [
                        'type' => 'navigate',
                        'label' => ($isUpdate ? 'View updated draft: ' : 'Open draft: ') . $title,
                        'path' => $path,
                        'soft' => !empty($exec['result']['soft']),
                        'body' => $isUpdate ? (string) ($exec['result']['body'] ?? '') : '',
                        'title_text' => $title,
                        'excerpt' => $isUpdate ? (string) ($exec['result']['excerpt'] ?? '') : '',
                    ];
                    if ($isUpdate) {
                        $reply = ($reply !== '' ? $reply . "\n\n" : '')
                            . "Updated the **current draft** «{$title}». Refresh the editor or open the link if the body looks stale — nothing was published.";
                    } elseif ($reply === '' || str_contains(mb_strtolower($reply), 'create')) {
                        $reply = ($reply !== '' ? $reply . "\n\n" : '')
                            . "Created a **draft** «{$title}». Open it to review — nothing was published.";
                    } else {
                        $reply .= "\n\nCreated draft «{$title}» (not published).";
                    }
                } else {
                    $finalActions[] = $action;
                    if (empty($exec['ok'])) {
                        $reply .= "\n\nCould not " . ($type === 'update_content' ? 'update' : 'create') . " content: " . ($exec['error'] ?? 'unknown error');
                    }
                }
                continue;
            }
            if ($type === 'update_design_tokens') {
                // Require explicit Apply in the UI
                $action['confirm'] = true;
                $action['label'] = $action['label'] ?? 'Apply color changes';
                $finalActions[] = $action;
                continue;
            }
            $finalActions[] = $action;
        }
        $actions = $finalActions;

        // Merge local navigate suggestions if model returned none
        if ($actions === [] && $localActions !== []) {
            $actions = array_slice($localActions, 0, 3);
            if ($reply === '') {
                $reply = 'Here are the best matching HQ screens:';
            }
        }

        if ($reply === '' && $actions !== []) {
            $reply = 'Here’s what I can do:';
        }
        if ($reply === '') {
            $reply = 'I can navigate HQ, create draft pages, and suggest design colors. Try: “Create an About Us page” or “Set primary color to #0ea5e9”.';
        }

        $meta = json_encode(['actions' => $actions, 'provider' => $provider], JSON_UNESCAPED_UNICODE);
        $this->addMessage($sessionId, 'assistant', $reply, $meta);

        Database::query(
            "UPDATE ai_sessions SET updated_at = :t, title = CASE WHEN title = 'New chat' OR title = '' THEN :title ELSE title END WHERE id = :id",
            [
                't' => date('c'),
                'title' => mb_substr($message, 0, 60),
                'id' => $sessionId,
            ]
        );

        return [
            'ok' => true,
            'session_id' => $sessionId,
            'reply' => $reply,
            'actions' => $actions,
            'provider' => $provider,
            'error' => $llm['error'] ?? null,
        ];
    }

    private function addMessage(int $sessionId, string $role, string $content, ?string $meta): void
    {
        Database::insert('ai_messages', [
            'session_id' => $sessionId,
            'role' => $role,
            'content' => $content,
            'meta' => $meta,
            'created_at' => date('c'),
        ]);
    }

    /**
     * Original / undoctored model call — no Mova JSON schema, no CMS action forcing.
     * Returns plain assistant text (markdown/code allowed) so the model behaves like a normal LLM.
     *
     * @param list<array<string,mixed>> $history
     * @return array{reply:string,actions:list<array>,provider:string,error?:string}
     */
    private function callLlmRaw(string $message, array $history, array $options = []): array
    {
        $system = "You are a helpful, capable assistant. Answer the user's request fully and directly.\n"
            . "Do NOT wrap your entire response in a JSON object. Do NOT invent CMS actions, navigate paths, or Mova-specific schemas.\n"
            . "When the user asks for code (HTML, CSS, JS, etc.), provide complete, runnable source in markdown fenced code blocks.\n"
            . "Be clear, accurate, and production-quality. Prefer complete solutions over stubs.";

        $messages = [['role' => 'system', 'content' => $system]];
        foreach (array_slice($history, -16) as $row) {
            $role = ($row['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => (string) ($row['content'] ?? '')];
        }
        if (!$history || (string) (end($history)['content'] ?? '') !== $message) {
            $messages[] = ['role' => 'user', 'content' => $message];
        }

        if (!$this->assist->isConfigured()) {
            return [
                'reply' => 'AI provider is not configured. Enable a model in Settings to use Raw AI mode.',
                'actions' => [],
                'provider' => 'none',
            ];
        }

        try {
            $maxTokens = 5000;
            $timeout = 180;
            $raw = $this->assist->chatRaw($messages, $maxTokens, $timeout);
            $reply = trim((string) $raw);
            // Defensive: if the model still emitted a JSON envelope, extract the reply field
            if ($reply !== '' && ($reply[0] === '{' || str_starts_with($reply, '```json'))) {
                $parsed = $this->parseJsonReply($reply);
                if ($parsed !== null && isset($parsed['reply']) && is_string($parsed['reply']) && $parsed['reply'] !== '') {
                    $reply = self::stripLeakedJson($parsed['reply']);
                } else {
                    $reply = self::stripLeakedJson($reply);
                }
            }
            if ($reply === '') {
                $reply = 'The model returned an empty response. Try again or shorten the request.';
            }
            return [
                'reply' => $reply,
                'actions' => [],
                'provider' => $this->assist->providerLabel(),
            ];
        } catch (\Throwable $e) {
            return [
                'reply' => 'Model error in Raw AI mode: ' . $e->getMessage(),
                'actions' => [],
                'provider' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param list<array<string,mixed>> $history
     * @param list<array<string,mixed>> $mapMatches
     * @return array{reply:string,actions:list<array>,provider:string,error?:string}
     */
    private function callLlm(string $message, array $history, array $pageContext, array $mapMatches, array $options = []): array
    {
        $route = (string) ($pageContext['route'] ?? '');
        $area = (string) ($pageContext['area'] ?? '');
        $layer = (string) ($pageContext['layer'] ?? '');
        $kind = (string) ($options['kind'] ?? 'chat');
        // Explicit directives (prefix-style — not topics like "Code of conduct")
        $wantsCodeDirective = (bool) preg_match('/(?:^|\n)\s*code\s*:/i', $message)
            || (bool) preg_match('/\bcode\s*:\s*(html|css|js|javascript|php|sql|json)?/i', $message);
        $wantsChatDirective = (bool) preg_match('/(?:^|\n)\s*chat\s*:/i', $message);
        // Coding only when explicit — NOT "code of conduct" / "code of ethics"
        // Also treat paste-and-edit requests as coding (add/fix/modify this code/html/css/js)
        $hasPastedCode = (bool) preg_match('/<!DOCTYPE\s+html|<html[\s>]|<style[\s>]|<script[\s>]|function\s*\(|const\s+\w+\s*=|document\.getElementById/i', $message)
            || (mb_strlen($message) > 400 && (bool) preg_match('/\{[\s\S]{80,}\}/', $message));
        $isCodeEditRequest = (bool) preg_match(
            '/\b(add|insert|put|include|attach|append|prepend)\b.{0,60}\b(button|start|stop|reset|input|element|div|span|class|id|style|script)\b/i',
            $message
        ) || (bool) preg_match(
            '/\b(fix|modify|update|change|edit|improve|rewrite|refactor)\b.{0,40}\b(this\s+)?(code|html|css|js|javascript|snippet|script)\b/i',
            $message
        ) || (bool) preg_match(
            '/\b(this\s+)?(code|html|css|js|javascript|snippet)\b.{0,40}\b(add|insert|fix|modify|update|change|edit)\b/i',
            $message
        );
        $isCoding = $kind === 'coding'
            || $wantsCodeDirective
            || $isCodeEditRequest
            || ($hasPastedCode && (bool) preg_match('/\b(add|insert|fix|modify|update|change|edit|button|start|make|create)\b/i', $message))
            || (bool) preg_match('/\b(html|css|javascript|snippet|pricelist|price\s*list)\b/i', $message)
            || (bool) preg_match('/\b(write|show|give|generate)\s+(me\s+)?(some\s+)?(html|css|js|javascript)\b/i', $message)
            || (bool) preg_match('/\b(source\s+code|code\s+snippet|code\s+block)\b/i', $message);
        $isContentWrite = !$wantsChatDirective && (
            (bool) preg_match(
                '/\b(write|draft|create|compose|author)\b.{0,40}\b(article|page|post|content|essay|blog)\b/i',
                $message
            ) || (bool) preg_match('/\b(write|draft)\s+(me\s+)?(a\s+)?(draft|article|page|post)?\s*(on|about)\b/i', $message)
              || (bool) preg_match('/\b(insightful|seo[- ]?friendly|in[- ]depth|long[- ]form)\b/i', $message)
        );
        $longRunning = !empty($options['long_running']) || $kind === 'coding' || $isContentWrite || $isCoding;

        $matchLines = [];
        foreach (array_slice($mapMatches, 0, 5) as $m) {
            $matchLines[] = "{$m['label']} → {$m['path']}";
        }

        $entityId = (int) ($pageContext['entityId'] ?? 0);
        $templateId = null;
        $templateHelp = '';
        if (class_exists(\Mova\Theme\TemplateService::class)) {
            $explicit = (string) ($pageContext['templateId'] ?? $options['template_id'] ?? '');
            $templateId = \Mova\Theme\TemplateService::detectIdFromMessage($message, $explicit !== '' ? $explicit : null);
            if ($templateId) {
                $templateHelp = \Mova\Theme\TemplateService::promptBlock($templateId) . "\n";
                $isCoding = true;
            }
        }
        $paletteHelp = "Mova Style palette keys (ONLY these for design-token actions — do not invent 'link' or other keys): "
            . "primary, secondary, accent, background, surface, text, muted, border. "
            . "Links on the public site typically use primary (or accent for emphasis). "
            . "If the user says 'link color', map it to primary and say so in the reply.";

        $contentHelp = $entityId > 0
            ? "The user is editing content id={$entityId}. If they ask to add, expand, continue, revise, or write the article, use update_content with payload id={$entityId}, mode=append (or replace when they say rewrite/replace), and a FULL body HTML — do NOT create_content."
            : "When creating new pages/posts use create_content (draft only). Always fill a complete body, excerpt, and SEO fields — never create an empty draft.";

        $qualityHelp = "CONTENT QUALITY (mandatory for articles/pages):\n"
            . "- MODE DIRECTIVES:\n"
            . "  • Default: CMS draft via create_content/update_content (semantic HTML). Do not wrap the whole article in ``` fences.\n"
            . "  • CODE MODE (code: / HTML/CSS/JS / paste-and-edit): the COMPLETE source MUST appear in the chat reply inside ```html / ```css / ```js fences. Prefer insert_code when an editor is open. Topics like 'Code of conduct' are NOT code mode.\n"
            . "  • chat: … — answer only in the chat reply with clean markdown (headings, lists, tables). Do NOT create/update drafts unless the user also asks to save a draft.\n"
            . "  • Enter code mode for explicit 'code:', clear HTML/CSS/JS/snippet/template/dev/studio requests, OR when the user pastes code and asks to add/fix/modify it.\n"
            . "- RICH DRAFTS: create_content/update_content MUST include title, type, full body HTML, excerpt, meta.seo_title, meta.meta_description.\n"
            . "- BODY HTML: semantic tags — <article>, <section>, <h2>/<h3>, <p>, <ul>/<ol>, <table> when useful. No <html>/<head>/<body> wrappers (unless the user asked for a full document).\n"
            . "- DEPTH & COMPLETENESS (be hardworking — complete on the first try):\n"
            . "  • If asked for a 12-row table, return exactly 12 data rows plus header — not 3.\n"
            . "  • If asked for N examples, sections, FAQs, or items, produce all N — never a short sample with 'and so on'.\n"
            . "  • Write real paragraphs with explanation and examples.\n"
            . "  • For CODE MODE: the full source in a fence is mandatory — a description alone is a failure.\n"
            . "- Prefer full article in the action body; keep chat reply short unless user used chat: or is in CODE MODE.\n"
            . "- CRITICAL: finished body only. FORBIDDEN placeholders like 'This draft was started by Mova AI' or 'Replace this section'.\n"
            . "- Title = topic only (strip 'insightful', 'SEO friendly', etc.). Never invent HQ URLs. Never publish. Never leave body empty.\n";

        // CSS / design-token usage for generated HTML+CSS (matches VariableService + public theme)
        $cssVarHelp = "CSS VARIABLE RULES (mandatory when writing CSS):\n"
            . "- NEVER write bare token names as property values (wrong: background-color: surface; color: text; border: 1px solid border).\n"
            . "- ALWAYS use CSS custom properties with the var() function and a sensible fallback hex/rgb.\n"
            . "- Built-in light palette CSS names (use these exact --names):\n"
            . "  --color-primary, --color-secondary, --color-accent, --color-bg, --color-surface, --color-text, --color-muted, --color-border\n"
            . "  Note: Style key 'background' maps to --color-bg (not --color-background).\n"
            . "- Dark counterparts exist as --color-primary-dark, --color-bg-dark, --color-surface-dark, --color-text-dark, etc.\n"
            . "- Other system vars: --max-width, --radius-sm/--radius-md/--radius-lg, --radius-button, --radius-card, --space-section, --space-element, --font-sans, --shadow-sm, --shadow-md.\n"
            . "- Custom site vars (from Design → Variables) are available as --{name} or --mova-{slug}; prefer var(--name, fallback) when known.\n"
            . "- Preferred syntax examples (match modern Mova page CSS):\n"
            . "  background: var(--color-bg, #f8faf9);\n"
            . "  color: var(--color-text, #111816);\n"
            . "  background-color: var(--color-surface, #fff);\n"
            . "  color: var(--color-primary, #075b3a);\n"
            . "  color: var(--color-muted, #66736d);\n"
            . "  border: 1px solid var(--color-border, #dce5e0);\n"
            . "  box-shadow: var(--shadow-md, 0 4px 12px rgba(0,0,0,0.08));\n"
            . "  border-radius: var(--radius-md, 10px);\n"
            . "  max-width: var(--max-width, 1200px);\n"
            . "- For polished UI also use color-mix() with vars when helpful, e.g. color-mix(in srgb, var(--color-primary, #075b3a) 12%, transparent).\n"
            . "- Scope styles under a clear root class (e.g. .pricing-page) so they do not leak globally; do not redefine :root tokens.\n"
            . "- HTML: semantic, accessible (aria labels, headings). No <html>/<head>/<body> unless explicitly requested. Prefer paste-ready body fragments.";

        $designHelp = "DESIGN QUALITY (mandatory for UI/layout/pricing/cards):\n"
            . "- Aim for production-grade, editorial UI — not a bare white box with plain text.\n"
            . "- Hierarchy: clear section title, plan name, large price with currency, short period label (e.g. /mo), feature list, primary CTA button.\n"
            . "- Cards: equal-height grid (CSS grid or flex), generous padding (1.5–2rem), rounded corners (var(--radius-lg) or ~1rem), soft multi-layer shadow, subtle border with var(--color-border).\n"
            . "- Highlight one recommended tier (middle or highest): scale slightly, primary-tinted border or top accent bar, 'Most popular' badge, stronger CTA (solid primary button).\n"
            . "- Features: checklist style with ✓ or icon; included items normal; higher tiers show everything from lower tiers plus extras.\n"
            . "- Buttons: solid primary for main CTA, outline/ghost for secondary; min-height ~2.75–3.25rem; hover lift (translateY) + stronger shadow.\n"
            . "- Spacing rhythm: consistent gaps (1rem–1.5rem between cards, 0.65–0.85rem between list items).\n"
            . "- Typography: plan name bold 1.1–1.25rem; price 2–2.75rem weight 700–800; muted labels for period and features.\n"
            . "- Responsive: 3 columns → 1 column under ~720px; cards stack cleanly; no horizontal overflow.\n"
            . "- Polish: use color-mix with primary/accent for soft fills; avoid flat grey-only UIs; never leave empty whitespace that looks unfinished.\n"
            . "- Scope under one root class (e.g. .pricing-page). HTML + CSS both required unless user asked for one only.\n";

        $codingHelp = $isCoding
            ? "CODE MODE (code: / HTML/CSS/JS / paste-and-edit request). CRITICAL RULES:\n"
              . "1. ALWAYS put the COMPLETE, runnable, paste-ready source code in the reply inside markdown fences (```html, ```css, or ```js).\n"
              . "2. NEVER reply with only a description of the change. NEVER say 'Here is the updated…' without the full code block.\n"
              . "3. When the user pastes existing code and asks to add/fix/modify something (e.g. 'add a start button to this code'), return the ENTIRE updated document — not a diff, not a fragment, not a summary.\n"
              . "4. Keep the short prose summary to 1–2 sentences max; the code fence is the main deliverable.\n"
              . "5. Do NOT invent design-token apply instructions, 'Apply background …', or color-change actions unless the user explicitly asked to change a color/theme.\n"
              . "6. Prefer a full paste-ready snippet. Use classes/ids and site CSS variables (var(--color-*, …) with fallbacks).\n"
              . "7. You may include navigate or insert_code actions when useful; otherwise actions can be empty.\n"
              . $designHelp
              . $cssVarHelp
            : ($wantsChatDirective
                ? "CHAT MODE (chat:). Answer fully in the reply with clean markdown (## headings, lists, tables) that pastes well into editors. "
                  . "Do not call create_content/update_content unless they also ask to save a draft. "
                  . "If you emit CSS, still follow: " . $cssVarHelp
                : "Default is normal editor content (not code). Only emit long code fences for code: or clear HTML/CSS/JS/dev/studio/template requests. "
                  . "Do NOT treat topics like 'Code of conduct' as code mode. "
                  . "If you emit CSS, still follow: " . $cssVarHelp);

        $modesHelp = "MOVA MODES (awareness):\n"
            . "- Normal editor: classic body field — use semantic HTML in create/update_content. Target this unless user asks for code.\n"
            . "- Dev mode (mova-dev-editor): Monaco HTML/CSS/JS panels; body + meta raw_css / raw_js. Prefer insert_code or full code fences with comments, classes, ids, and site vars.\n"
            . "- Studio: multi-column workspace (structure + elements + Monaco + live preview). Same fields as Dev Mode (body, raw_css, raw_js, editor_mode=studio).\n"
            . "- Assembly: reusable page sections/blocks. Prefer structured section markup.\n"
            . "- Elements: styling panels for components. Prefer clean class-based HTML that Elements can style.\n";

        $system = "You are Mova AI, the assistant inside Mova CMS HQ. "
            . "Help users navigate HQ, create/update draft content, adjust design colors, and write HTML/CSS/JS when asked. "
            . ($isCoding
                ? "CODE requests: the full source code in a markdown fence IS the answer — never replace it with a summary. "
                : "Be concise in chat replies; put substance into draft actions. ")
            . "Never publish content. Never invent HQ URLs — use the map. "
            . "{$paletteHelp} {$contentHelp} {$qualityHelp} {$modesHelp} {$codingHelp} "
            . "When the user should open a screen, include navigate actions.\n\n"
            . $templateHelp
            . HqMap::asPromptBlock(35) . "\n\n"
            . "Current page: route={$route} area={$area} layer={$layer} entityId={$entityId}"
            . ($templateId ? " templateId={$templateId}" : '') . "\n"
            . "Top map matches for this message:\n" . ($matchLines ? implode("\n", $matchLines) : "(none)") . "\n\n"
            . "Respond with ONLY valid JSON (no markdown fences around the JSON itself):\n"
            . '{"reply":"string — for CODE MODE: 1-2 sentence summary + the COMPLETE source inside ```html / ```css / ```js fences (mandatory). For other modes: short summary; may contain markdown and code fences","actions":[ '
            . '{"type":"navigate","label":"Open …","path":"/hq/..."}, '
            . '{"type":"create_content","label":"Create draft","payload":{"title":"…","type":"page","body":"<article>…</article>","excerpt":"…","meta":{"seo_title":"…","meta_description":"…"}}}, '
            . '{"type":"update_content","label":"Update draft","payload":{"id":' . max($entityId, 0) . ',"mode":"append|replace","body":"…","excerpt":"…","meta":{"seo_title":"…","meta_description":"…"}}}, '
            . '{"type":"update_design_tokens","label":"Apply colors","payload":{"colors":{"primary":"#2563eb","accent":"#7c3aed"}}}, '
            . '{"type":"insert_code","label":"Insert into body","payload":{"target":"body","mode":"append","language":"html","code":"…"}} '
            . "]}\n"
            . "Use 0–4 actions. navigate paths must start with /hq. Only use palette keys listed above. "
            . "For normal writing put the full article into create_content/update_content body (with excerpt + meta). "
            . "For coding requests prefer insert_code when the user is already editing content, otherwise show code fences. "
            . "CSS in replies MUST use var(--color-*) / var(--radius-*) etc. with fallbacks — never bare names like surface or text as values.";

        $messages = [['role' => 'system', 'content' => $system]];
        foreach (array_slice($history, -12) as $row) {
            $role = $row['role'] === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => (string) $row['content']];
        }
        // Last user message already in history; ensure latest is present
        if (!$history || end($history)['content'] !== $message) {
            $messages[] = ['role' => 'user', 'content' => $message];
        }

        if (!$this->assist->isConfigured()) {
            return $this->heuristicReply($message, $mapMatches);
        }

        try {
            // Template + coding: full seed HTML is already in the system prompt.
            // High max_tokens (e.g. 6000) often causes small Ollama/vLLM hosts to return
            // empty content → "Invalid AI response". Prefer a moderate completion budget.
            if ($templateId) {
                $maxTokens = 2800;
                $timeout = 240;
                $systemMsg = $messages[0] ?? null;
                $tail = [];
                foreach (array_reverse($messages) as $m) {
                    if (($m['role'] ?? '') === 'system') {
                        continue;
                    }
                    array_unshift($tail, $m);
                    if (count($tail) >= 4) {
                        break;
                    }
                }
                $messages = $systemMsg ? array_merge([$systemMsg], $tail) : $tail;
            } else {
                $maxTokens = ($longRunning || $isCoding) ? 5000 : 2400;
                $timeout = ($longRunning || $isCoding) ? 300 : 90;
            }
            // Long / coding jobs: async submit+poll so proxy timeouts cannot kill a 1–5 min RunPod delay.
            if ($longRunning || $isCoding) {
                $raw = $this->assist->chatRawAsync($messages, $maxTokens, max(600, $timeout * 3), null);
            } else {
                $raw = $this->assist->chatRaw($messages, $maxTokens, $timeout);
            }
            $parsed = $this->parseJsonReply($raw);
            if ($parsed !== null) {
                return [
                    'reply' => self::stripLeakedJson($parsed['reply']),
                    'actions' => $parsed['actions'],
                    'provider' => $this->assist->providerLabel(),
                ];
            }
            // Model returned prose or broken JSON — try recovery before giving up
            $recovered = $this->recoverActionsFromBrokenJson($raw);
            if ($recovered !== null) {
                return [
                    'reply' => self::stripLeakedJson($recovered['reply'] !== '' ? $recovered['reply'] : 'Draft recovered from model output.'),
                    'actions' => $recovered['actions'],
                    'provider' => $this->assist->providerLabel(),
                ];
            }
            return [
                'reply' => self::stripLeakedJson(trim($raw)),
                'actions' => $this->actionsFromMatches($mapMatches),
                'provider' => $this->assist->providerLabel(),
            ];
        } catch (\Throwable $e) {
            $isCodingFail = !empty($options['long_running'])
                || (($options['kind'] ?? '') === 'coding')
                || (bool) preg_match('/\b(html|css|code|snippet)\b/i', $message);
            if ($isCodingFail) {
                return [
                    'reply' => 'The model timed out or failed while generating code. Try again, or shorten the request. (' . $e->getMessage() . ')',
                    'actions' => [],
                    'provider' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
            $fallback = $this->heuristicReply($message, $mapMatches);
            $fallback['error'] = $e->getMessage();
            return $fallback;
        }
    }

    /**
     * @param list<array<string,mixed>> $mapMatches
     * @return array{reply:string,actions:list<array>,provider:string}
     */
    private function heuristicReply(string $message, array $mapMatches): array
    {
        $actions = $this->actionsFromMatches($mapMatches);
        if ($actions !== []) {
            $names = array_map(static fn($a) => $a['label'], $actions);
            return [
                'reply' => 'Based on your question, try: ' . implode(', ', $names) . '.',
                'actions' => $actions,
                'provider' => 'heuristic',
            ];
        }
        return [
            'reply' => 'Ask me things like “Where is the site icon?”, “Open design colors”, or “Take me to new content”.',
            'actions' => [],
            'provider' => 'heuristic',
        ];
    }

    /**
     * @param list<array<string,mixed>> $mapMatches
     * @return list<array{type:string,label:string,path:string,id?:string}>
     */
    private function actionsFromMatches(array $mapMatches): array
    {
        $out = [];
        foreach (array_slice($mapMatches, 0, 3) as $m) {
            if (($m['score'] ?? 0) < 1.5) {
                continue;
            }
            $out[] = [
                'type' => 'navigate',
                'label' => 'Open ' . $m['label'],
                'path' => $m['path'],
                'id' => $m['id'],
            ];
        }
        return $out;
    }

    /** @return array{reply:string,actions:list<array>}|null */
    private function parseJsonReply(string $raw): ?array
    {
        $raw = trim($raw);
        // Strip markdown fences around the whole JSON payload
        if (preg_match('/^```(?:json)?\s*([\s\S]*?)```\s*$/i', $raw, $fm)) {
            $raw = trim($fm[1]);
        }
        // Prefer outermost JSON object
        if (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
            $raw = $m[0];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            // Common model failures: trailing commas, smart quotes, unescaped control chars
            $fixed = $raw;
            $fixed = str_replace(["\u201c", "\u201d", "\u2018", "\u2019", "“", "”", "‘", "’"], ['"', '"', "'", "'", '"', '"', "'", "'"], $fixed);
            $fixed = preg_replace('/,\s*([}\]])/', '$1', $fixed) ?? $fixed;
            $data = json_decode($fixed, true);
        }
        if (!is_array($data)) {
            // Last resort: recover create_content from a broken JSON blob
            $recovered = $this->recoverActionsFromBrokenJson($raw);
            if ($recovered !== null) {
                return $recovered;
            }
            return null;
        }
        $reply = trim((string) ($data['reply'] ?? ''));
        $actions = [];
        foreach ($data['actions'] ?? [] as $a) {
            if (!is_array($a)) {
                continue;
            }
            $type = (string) ($a['type'] ?? '');
            $label = (string) ($a['label'] ?? '');
            if ($type === 'navigate') {
                $path = (string) ($a['path'] ?? '');
                if (str_starts_with($path, '/hq')) {
                    $actions[] = [
                        'type' => 'navigate',
                        'label' => $label !== '' ? $label : 'Open',
                        'path' => $path,
                    ];
                }
            } elseif ($type === 'create_content') {
                $payload = is_array($a['payload'] ?? null) ? $a['payload'] : [];
                if (trim((string) ($payload['title'] ?? '')) !== '') {
                    $actions[] = [
                        'type' => 'create_content',
                        'label' => $label !== '' ? $label : 'Create draft',
                        'payload' => $payload,
                    ];
                }
            } elseif ($type === 'update_content') {
                $payload = is_array($a['payload'] ?? null) ? $a['payload'] : [];
                if ((int) ($payload['id'] ?? 0) > 0 && trim((string) ($payload['body'] ?? '')) !== '') {
                    $actions[] = [
                        'type' => 'update_content',
                        'label' => $label !== '' ? $label : 'Update draft',
                        'payload' => $payload,
                    ];
                }
            } elseif ($type === 'update_design_tokens') {
                $payload = is_array($a['payload'] ?? null) ? $a['payload'] : [];
                $payload = self::normalizePalettePayload($payload);
                if ($payload !== []) {
                    $actions[] = [
                        'type' => 'update_design_tokens',
                        'label' => $label !== '' ? $label : 'Apply colors',
                        'payload' => $payload,
                        'confirm' => true,
                    ];
                }
            }
        }
        return ['reply' => $reply, 'actions' => array_slice($actions, 0, 6)];
    }


    /**
     * When the model returns almost-JSON with a huge HTML body that breaks json_decode,
     * recover title/body/excerpt/meta with targeted regex so the draft is not lost.
     *
     * @return array{reply:string,actions:list<array>}|null
     */
    private function recoverActionsFromBrokenJson(string $raw): ?array
    {
        $reply = '';
        if (preg_match('/"reply"\\s*:\\s*"((?:[^"\\\\]|\\\\.)*)"/s', $raw, $m)) {
            $reply = stripcslashes($m[1]);
        } elseif (preg_match('/"reply"\\s*:\\s*"(.*?)"\\s*,\\s*"actions"/s', $raw, $m)) {
            $reply = stripcslashes($m[1]);
        }

        $title = '';
        if (preg_match('/"title"\\s*:\\s*"((?:[^"\\\\]|\\\\.)*)"/', $raw, $m)) {
            $title = stripcslashes($m[1]);
        }
        $title = $this->cleanTitleCandidate($title);

        $body = '';
        // Greedy body capture between "body":" and the next ","excerpt" or ","meta"
        if (preg_match('/"body"\\s*:\\s*"(.*?)"\\s*,\\s*"(?:excerpt|meta|type)"/s', $raw, $m)) {
            $body = stripcslashes($m[1]);
        } elseif (preg_match('/"body"\\s*:\\s*"(.*?)"/s', $raw, $m)) {
            $body = stripcslashes($m[1]);
        }
        // Prefer fenced HTML in the raw stream if body still empty
        if ($this->isStubBody($body)) {
            $fromFence = $this->extractHtmlFromReply($raw);
            if ($fromFence !== '') {
                $body = $fromFence;
            }
        }

        $excerpt = '';
        if (preg_match('/"excerpt"\\s*:\\s*"((?:[^"\\\\]|\\\\.)*)"/', $raw, $m)) {
            $excerpt = stripcslashes($m[1]);
        }
        $seoTitle = '';
        if (preg_match('/"seo_title"\\s*:\\s*"((?:[^"\\\\]|\\\\.)*)"/', $raw, $m)) {
            $seoTitle = stripcslashes($m[1]);
        }
        $metaDesc = '';
        if (preg_match('/"meta_description"\\s*:\\s*"((?:[^"\\\\]|\\\\.)*)"/', $raw, $m)) {
            $metaDesc = stripcslashes($m[1]);
        }

        $typeHint = 'page';
        if (preg_match('/"type"\\s*:\\s*"(article|page|guide|documentation|faq|custom)"/', $raw, $m)) {
            $typeHint = $m[1];
        }

        $isCreate = (bool) preg_match('/"type"\\s*:\\s*"create_content"/', $raw);
        $isUpdate = (bool) preg_match('/"type"\\s*:\\s*"update_content"/', $raw);
        $updateId = 0;
        if (preg_match('/"id"\\s*:\\s*(\\d+)/', $raw, $m)) {
            $updateId = (int) $m[1];
        }

        if ($title === '' && $body === '') {
            return null;
        }
        if ($title === '') {
            $title = 'Recovered draft';
        }
        if ($excerpt === '' && $body !== '') {
            $excerpt = mb_substr(trim(preg_replace('/\\s+/', ' ', strip_tags($body)) ?? ''), 0, 160);
        }
        $meta = [];
        if ($seoTitle !== '') {
            $meta['seo_title'] = mb_substr($seoTitle, 0, 60);
        } else {
            $meta['seo_title'] = mb_substr($title, 0, 60);
        }
        if ($metaDesc !== '') {
            $meta['meta_description'] = mb_substr($metaDesc, 0, 160);
        } elseif ($excerpt !== '') {
            $meta['meta_description'] = mb_substr($excerpt, 0, 160);
        }

        $actions = [];
        if ($isUpdate && $updateId > 0 && $body !== '') {
            $actions[] = [
                'type' => 'update_content',
                'label' => 'Update draft',
                'payload' => [
                    'id' => $updateId,
                    'mode' => 'replace',
                    'body' => $body,
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'meta' => $meta,
                ],
            ];
        } elseif ($body !== '' || $title !== '') {
            $actions[] = [
                'type' => 'create_content',
                'label' => 'Create draft: ' . $title,
                'payload' => [
                    'title' => $title,
                    'type' => $typeHint,
                    'body' => $body !== '' ? $body : '<p></p>',
                    'excerpt' => $excerpt,
                    'meta' => $meta,
                ],
            ];
        }

        if ($actions === []) {
            return null;
        }
        if ($reply === '') {
            $reply = 'Recovered draft «' . $title . '» from a partial model response.';
        }
        return ['reply' => $reply, 'actions' => $actions];
    }

    /** @param list<array<string,mixed>> $actions */
    private function hasActionType(array $actions, string $type): bool
    {
        foreach ($actions as $a) {
            if (($a['type'] ?? '') === $type) {
                return true;
            }
        }
        return false;
    }

    private function looksLikeCreateContent(string $message): bool
    {
        $m = mb_strtolower($message);
        return (bool) preg_match('/\b(create|new|write|draft|compose)\b.*\b(page|post|article|about\s*us|content|essay|blog)\b/i', $m)
            || ((bool) preg_match('/\babout\s*us\b/i', $m) && (bool) preg_match('/\b(create|new|write|make)\b/i', $m))
            || (bool) preg_match('/\b(write|create|draft)\s+(?:me\s+)?(?:a\s+)?(?:draft|article|page|post)?\s*(?:on|about)\b/i', $m)
            || (bool) preg_match('/\bwrite\s+me\s+a\s+draft\b/i', $m);
    }

    /** Strip instructional clauses so titles stay topical. */
    private function cleanTitleCandidate(string $t): string
    {
        $t = trim($t);
        // Drop trailing instruction phrases
        $t = preg_replace(
            '/\s*[,.]?\s*(the\s+)?content\s+should\s+be\b.*$/iu',
            '',
            $t
        ) ?? $t;
        $t = preg_replace(
            '/\s*[,.]?\s*(make\s+it|keep\s+it|please\s+make\s+it|it\s+should\s+be|should\s+be)\s+(insightful|seo[- ]?friendly|detailed|long|short|comprehensive).*$/iu',
            '',
            $t
        ) ?? $t;
        $t = preg_replace(
            '/\s*[,.]?\s*(seo[- ]?friendly|insightful|in[- ]depth|well[- ]researched|with\s+examples).*$/iu',
            '',
            $t
        ) ?? $t;
        $t = preg_replace('/\s*[,.]?\s*(as\s+a\s+new\s+draft|please|now|thanks).*$/iu', '', $t) ?? $t;
        $t = preg_replace('/\s*,\s*i\s+want\b.*$/iu', '', $t) ?? $t;
        // Leading filler from "write me a draft on X"
        $t = preg_replace('/^(me\s+)?(a\s+)?(draft|article|page|post|content)\s+(on|about)\s+/iu', '', $t) ?? $t;
        $t = preg_replace('/^(me\s+a\s+draft\s+on\s+)/iu', '', $t) ?? $t;
        $t = preg_replace('/^(on|about)\s+/iu', '', $t) ?? $t;
        $t = trim(preg_replace('/\s+/', ' ', $t) ?? $t);
        $t = trim($t, " \t.,;:-\"'");
        // Title-case lightly if all lower / all upper
        if ($t !== '' && (mb_strtolower($t) === $t || mb_strtoupper($t) === $t)) {
            $t = mb_convert_case($t, MB_CASE_TITLE, 'UTF-8');
        }
        return mb_substr($t, 0, 100);
    }

    /** Extract a sensible title from a free-form create request. */
    private function extractTitleFromMessage(string $message): string
    {
        if (preg_match('/about\s*us/i', $message) && !preg_match('/\b(create|new|write|draft)\b.+\b(page|post|article)\b.+/i', $message)) {
            return 'About Us';
        }
        // Quoted title
        if (preg_match('/["\x{201C}\x{201D}]([^"\x{201C}\x{201D}]{3,100})["\x{201C}\x{201D}]/u', $message, $m)) {
            $t = $this->cleanTitleCandidate($m[1]);
            if (mb_strlen($t) >= 3) {
                return $t;
            }
        }
        // "write me a draft on TOPIC" / "write a draft about TOPIC"
        if (preg_match('/\b(?:write|create|draft|compose)\s+(?:me\s+)?(?:a\s+)?(?:new\s+)?(?:draft|article|page|post|content)?\s*(?:on|about)\s+(.+)$/iu', $message, $m)) {
            $t = $this->cleanTitleCandidate($m[1]);
            if (mb_strlen($t) >= 3) {
                return $t;
            }
        }
        // "create a page/post/article titled/called/on/about X"
        if (preg_match('/(?:create|new|write|draft)\s+(?:a\s+|an\s+)?(?:page|post|article|content)\s+(?:on|about|for|called|titled|named)?\s*["\']?([^"\'.\n]{3,120})/i', $message, $m)) {
            $t = $this->cleanTitleCandidate($m[1]);
            if (mb_strlen($t) >= 3) {
                return $t;
            }
        }
        // "New content The Impacts of..." / "create a new content TOPIC"
        if (preg_match('/\b(?:create\s+)?(?:a\s+)?new\s+content\s+(.+)$/iu', $message, $m)) {
            $t = $this->cleanTitleCandidate($m[1]);
            if (mb_strlen($t) >= 3) {
                return $t;
            }
        }
        // Fallback: text after create/write/draft, cleaned
        if (preg_match('/\b(?:create|write|draft|compose)\b\s+(.+)/iu', $message, $m)) {
            $t = $this->cleanTitleCandidate($m[1]);
            $t = preg_replace('/\b(a\s+)?(new\s+)?(page|post|article|content|draft)\b/i', '', $t) ?? $t;
            $t = $this->cleanTitleCandidate($t);
            if (mb_strlen($t) >= 3) {
                return $t;
            }
        }
        return 'Untitled draft';
    }

    /**
     * True when body is empty or our known heuristic placeholder.
     */
    private function isStubBody(string $body): bool
    {
        $b = trim($body);
        if ($b === '') {
            return true;
        }
        if (mb_strlen(strip_tags($b)) < 80) {
            return true;
        }
        $low = mb_strtolower($b);
        foreach ([
            'this draft was started by mova ai',
            'open the editor and ask the ai to expand',
            'replace this section with the main arguments',
            'point one — expand with your research',
            'edit and publish when ready',
        ] as $needle) {
            if (str_contains($low, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Pull first substantial HTML fence (or raw HTML block) from an assistant reply.
     */
    private function extractHtmlFromReply(string $reply): string
    {
        if (preg_match('/```(?:html|xml|markup)?\s*([\s\S]*?)```/i', $reply, $m)) {
            $html = trim($m[1]);
            if ($html !== '' && (str_contains($html, '<') || mb_strlen($html) > 40)) {
                return $html;
            }
        }
        // Loose: largest chunk that looks like HTML sections
        if (preg_match('/((?:<article[\s\S]*?<\/article>)|(?:<section[\s\S]*?<\/section>){2,})/i', $reply, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /** @return array{type:string,label:string,payload:array} */
    private function buildCreateContentAction(string $message, string $replyHtml = ''): array
    {
        $title = $this->extractTitleFromMessage($message);
        $type = preg_match('/\b(article|post|essay|blog)\b/i', $message) ? 'article' : 'page';

        if ($replyHtml !== '' && !$this->isStubBody($replyHtml)) {
            $body = $replyHtml;
            $plain = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');
            $excerpt = mb_substr($plain, 0, 160);
            $seoTitle = mb_substr($title, 0, 60);
            $metaDesc = mb_substr($plain, 0, 155);
        } elseif (preg_match('/about\s*us/i', $message)) {
            $body = '<article class="mova-article">'
                . '<section><h2>Who we are</h2><p>We are building something meaningful. Replace this paragraph with your story, mission, and the people behind the work.</p></section>'
                . '<section><h2>What we do</h2><p>Describe your products, services, or core activities. Add concrete examples so visitors understand the value you deliver.</p></section>'
                . '<section><h2>Get in touch</h2><p>Add contact details, a short call to action, or a link to your contact page.</p></section>'
                . '</article>';
            $excerpt = 'Learn who we are, what we do, and how to get in touch.';
            $seoTitle = 'About Us';
            $metaDesc = 'Discover our story, mission, and how to reach us.';
        } else {
            // Last-resort skeleton only when LLM produced no body — still structured, never instruction-stuffed title
            $safe = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $body = '<article class="mova-article">'
                . '<section><h2>Introduction</h2><p>' . $safe . ' is a topic that rewards careful, balanced analysis. This section should open with context, define key terms, and state why the subject still matters today.</p></section>'
                . '<section><h2>Historical context</h2><p>Outline the main periods, actors, and forces involved. Prefer specific examples over vague claims so the reader can follow the argument.</p></section>'
                . '<section><h2>Key arguments</h2><p>Develop the central claims in full paragraphs. For each point, explain the mechanism, give an illustration, and note limits or counter-arguments.</p></section>'
                . '<section><h2>Contemporary relevance</h2><p>Connect the historical picture to present-day institutions, debates, or development questions without reducing the past to a single slogan.</p></section>'
                . '<section><h2>Conclusion</h2><p>Summarize the strongest takeaway and leave the reader with a clear, nuanced closing thought.</p></section>'
                . '</article>';
            $excerpt = mb_substr('An insightful overview of ' . $title . '.', 0, 160);
            $seoTitle = mb_substr($title, 0, 60);
            $metaDesc = mb_substr('Explore ' . $title . ' with clear arguments, context, and practical takeaways.', 0, 160);
        }

        return [
            'type' => 'create_content',
            'label' => 'Create draft: ' . $title,
            'payload' => [
                'title' => $title,
                'type' => $type,
                'body' => $body,
                'excerpt' => $excerpt,
                'meta' => [
                    'seo_title' => $seoTitle,
                    'meta_description' => $metaDesc,
                ],
            ],
        ];
    }

    private function looksLikeColorChange(string $message): bool
    {
        $m = mb_strtolower($message);
        return (bool) preg_match('/\b(color|colour|primary|accent|palette|theme)\b/i', $m)
            && (bool) preg_match('/\b(set|change|make|update|use|#|[0-9a-f]{3,6}|blue|green|red|purple|orange)\b/i', $m);
    }

    /** @return array{type:string,label:string,payload:array,confirm:bool}|null */
    private function buildColorActionFromMessage(string $message): ?array
    {
        $hex = null;
        if (preg_match('/#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})\b/', $message, $m)) {
            $hex = '#' . $m[1];
        }
        $named = [
            'blue' => '#2563eb',
            'sky' => '#0ea5e9',
            'green' => '#16a34a',
            'red' => '#dc2626',
            'purple' => '#7c3aed',
            'orange' => '#ea580c',
            'pink' => '#db2777',
            'teal' => '#0d9488',
        ];
        if ($hex === null) {
            $low = mb_strtolower($message);
            foreach ($named as $name => $code) {
                if (str_contains($low, $name)) {
                    $hex = $code;
                    break;
                }
            }
        }
        if ($hex === null) {
            return null;
        }
        $key = 'primary';
        if (preg_match('/\baccent\b/i', $message)) {
            $key = 'accent';
        } elseif (preg_match('/\bsecondary\b/i', $message)) {
            $key = 'secondary';
        } elseif (preg_match('/\b(link|links)\b/i', $message)) {
            $key = 'primary'; // Mova has no separate link token; links use primary
        } elseif (preg_match('/\b(muted|subtle)\b/i', $message)) {
            $key = 'muted';
        } elseif (preg_match('/\b(background|bg)\b/i', $message)) {
            $key = 'background';
        } elseif (preg_match('/\b(surface|card)\b/i', $message)) {
            $key = 'surface';
        } elseif (preg_match('/\b(text|foreground)\b/i', $message)) {
            $key = 'text';
        } elseif (preg_match('/\bborder\b/i', $message)) {
            $key = 'border';
        }

        return [
            'type' => 'update_design_tokens',
            'label' => 'Apply ' . $key . ' ' . $hex,
            'payload' => ['colors' => [$key => $hex]],
            'confirm' => true,
        ];
    }

    private function looksLikeContinueContent(string $message): bool
    {
        $m = mb_strtolower($message);
        // Explicit continuation of the open draft — not a brand-new topic request
        if ($this->looksLikeCreateContent($message) && preg_match('/\b(new|another|different|separate)\b/i', $m)) {
            return false;
        }
        if (preg_match('/\b(write|create|draft)\s+(me\s+)?(a\s+)?(new\s+)?(draft|article|page|post)\s+(on|about)\b/i', $m)) {
            return false;
        }
        return (bool) preg_match(
            '/\b(add\s+to\s+(it|this|the\s+draft)|expand\s+(it|this|the)|continue\s+(writing|it|this)|extend\s+(it|this)|append|improve\s+(it|this|the\s+draft)|rewrite\s+(it|this|the)|update\s+(the\s+)?(draft|body|article|content)|more\s+on\s+this|add\s+(a\s+)?section)\b/i',
            $m
        );
    }

    /** @return array{type:string,label:string,payload:array} */
    private function buildUpdateContentAction(string $message, int $entityId): array
    {
        // Heuristic fallback only — LLM should supply full prose.
        $mode = preg_match('/\b(replace|rewrite|overwrite)\b/i', $message) ? 'replace' : 'append';
        $body = '<section><h2>Further points</h2><p>Expanded content generated by Mova AI. Ask the AI again for a full rewrite of this section if you need deeper paragraphs, examples, and SEO-ready wording.</p></section>';
        if (preg_match('/advantage/i', $message) && preg_match('/nigeria|nigerian/i', $message)) {
            $body = '<section><h2>Advantages for Nigerian education</h2>'
                . '<ul>'
                . '<li><strong>Human capital:</strong> Stronger literacy and skills raise employability and entrepreneurship across regions.</li>'
                . '<li><strong>National development:</strong> Educated citizens support better governance, health outcomes, and local innovation.</li>'
                . '<li><strong>Equity and mobility:</strong> Access to quality schooling helps reduce poverty and open pathways for young people.</li>'
                . '<li><strong>Digital readiness:</strong> STEM and digital skills prepare youth for a modern, connected economy.</li>'
                . '<li><strong>Social cohesion:</strong> Shared learning experiences can strengthen community trust and civic participation.</li>'
                . '</ul></section>';
        } elseif (preg_match('/advantage|disadvantage|pros?\b|cons?\b/i', $message)) {
            $body = '<section><h2>Key considerations</h2>'
                . '<p>Use this section to explore the main advantages and drawbacks in full sentences. Replace the outline below with researched points, short examples, and a balanced conclusion.</p>'
                . '<ul>'
                . '<li><strong>Advantage:</strong> Describe the benefit and why it matters for the reader.</li>'
                . '<li><strong>Advantage:</strong> Add a second benefit with a concrete illustration.</li>'
                . '<li><strong>Drawback:</strong> Acknowledge a real limitation and how couples or teams can mitigate it.</li>'
                . '</ul></section>';
        }

        return [
            'type' => 'update_content',
            'label' => 'Update current draft',
            'payload' => [
                'id' => $entityId,
                'mode' => $mode,
                'body' => $body,
            ],
        ];
    }

    /**
     * Map invented keys (e.g. link) onto real Mova Style palette keys.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private static function normalizePalettePayload(array $payload): array
    {
        $allowed = ['primary', 'secondary', 'accent', 'background', 'surface', 'text', 'muted', 'border'];
        $aliases = [
            'link' => 'primary',
            'links' => 'primary',
            'brand' => 'primary',
            'cta' => 'accent',
            'highlight' => 'accent',
            'bg' => 'background',
            'foreground' => 'text',
            'body' => 'text',
            'card' => 'surface',
        ];
        foreach (['colors', 'colors_dark'] as $bucket) {
            if (!isset($payload[$bucket]) || !is_array($payload[$bucket])) {
                continue;
            }
            $out = [];
            foreach ($payload[$bucket] as $k => $v) {
                $k = strtolower((string) $k);
                if (isset($aliases[$k])) {
                    $k = $aliases[$k];
                }
                if (in_array($k, $allowed, true)) {
                    $out[$k] = $v;
                }
            }
            $payload[$bucket] = $out;
        }
        return $payload;
    }

    /** Never show model JSON envelopes in the chat UI */
    private static function stripLeakedJson(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return $text;
        }
        // Whole payload is JSON
        if (str_starts_with($text, '{') && str_contains($text, '"reply"')) {
            $data = json_decode($text, true);
            if (is_array($data) && isset($data['reply'])) {
                return trim((string) $data['reply']);
            }
        }
        // Trailing / embedded JSON object
        if (preg_match('/^(.*?)\s*\{\s*"reply"\s*:/s', $text, $m)) {
            $before = trim($m[1]);
            if ($before !== '') {
                return $before;
            }
            $data = json_decode(substr($text, strpos($text, '{')), true);
            if (is_array($data) && isset($data['reply'])) {
                return trim((string) $data['reply']);
            }
        }
        // Fence
        $text = preg_replace('/```json\s*[\s\S]*?```/i', '', $text) ?? $text;
        return trim($text);
    }
}
