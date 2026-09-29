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
        $llm = $this->callLlm($message, $history, $pageContext, $matches, $options);

        $reply = $llm['reply'] ?? '';
        $actions = $llm['actions'] ?? [];
        $provider = $llm['provider'] ?? 'heuristic';

        $entityId = (int) ($pageContext['entityId'] ?? 0);

        // Prefer updating the open draft over creating a new one
        if ($entityId > 0 && !$this->hasActionType($actions, 'update_content')
            && ($this->looksLikeContinueContent($message) || $this->looksLikeCreateContent($message))) {
            $actions = array_values(array_filter($actions, static fn($a) => ($a['type'] ?? '') !== 'create_content'));
            $actions[] = $this->buildUpdateContentAction($message, $entityId);
        } elseif (!$this->hasActionType($actions, 'create_content') && !$this->hasActionType($actions, 'update_content')
            && $this->looksLikeCreateContent($message)) {
            $actions[] = $this->buildCreateContentAction($message);
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
        $longRunning = !empty($options['long_running']) || $kind === 'coding';
        $isCoding = $kind === 'coding' || (bool) preg_match('/\b(html|css|javascript|code|snippet|pricelist|price list)\b/i', $message);

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
            ? "The user is editing content id={$entityId}. If they ask to add, expand, continue, or revise the article, use update_content with payload id={$entityId}, mode=append (or replace), and body HTML — do NOT create_content."
            : "When creating new pages use create_content (draft only).";

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
            ? "The user wants code. Put complete HTML and/or CSS in the reply using markdown fences (```html and ```css). "
              . "Do not omit code in favor of navigate links. Prefer a full paste-ready snippet. "
              . "You may still include navigate actions if useful, but code in reply is required when asked. "
              . $designHelp
              . $cssVarHelp
            : "When helpful you may include short code in markdown fences. If you emit CSS, still follow: " . $cssVarHelp;

        $system = "You are Mova AI, the assistant inside Mova CMS HQ. "
            . "Help users navigate HQ, create/update draft content, adjust design colors, and write HTML/CSS when asked. Be concise. "
            . "Never publish content. Never invent HQ URLs — use the map. "
            . "{$paletteHelp} {$contentHelp} {$codingHelp} "
            . "When the user should open a screen, include navigate actions.\n\n"
            . $templateHelp
            . HqMap::asPromptBlock(35) . "\n\n"
            . "Current page: route={$route} area={$area} layer={$layer} entityId={$entityId}"
            . ($templateId ? " templateId={$templateId}" : '') . "\n"
            . "Top map matches for this message:\n" . ($matchLines ? implode("\n", $matchLines) : "(none)") . "\n\n"
            . "Respond with ONLY valid JSON (no markdown fences around the JSON itself):\n"
            . '{"reply":"string — may contain markdown and ```html / ```css code fences","actions":[ '
            . '{"type":"navigate","label":"Open …","path":"/hq/..."}, '
            . '{"type":"create_content","label":"Create draft","payload":{"title":"…","type":"page","body":"…"}}, '
            . '{"type":"update_content","label":"Update draft","payload":{"id":' . max($entityId, 0) . ',"mode":"append","body":"…"}}, '
            . '{"type":"update_design_tokens","label":"Apply colors","payload":{"colors":{"primary":"#2563eb","accent":"#7c3aed"}}}, '
            . '{"type":"insert_code","label":"Insert into body","payload":{"target":"body","mode":"append","language":"html","code":"…"}} '
            . "]}\n"
            . "Use 0–4 actions. navigate paths must start with /hq. Only use palette keys listed above. "
            . "For coding requests prefer insert_code when the user is editing content. "
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
                $maxTokens = ($longRunning || $isCoding) ? 4000 : 2400;
                $timeout = ($longRunning || $isCoding) ? 300 : 90;
            }
            $raw = $this->assist->chatRaw($messages, $maxTokens, $timeout);
            $parsed = $this->parseJsonReply($raw);
            if ($parsed !== null) {
                return [
                    'reply' => self::stripLeakedJson($parsed['reply']),
                    'actions' => $parsed['actions'],
                    'provider' => $this->assist->providerLabel(),
                ];
            }
            // Model returned prose or raw JSON — never show JSON blob to user
            $maybe = $this->parseJsonReply($raw);
            if ($maybe !== null) {
                return [
                    'reply' => self::stripLeakedJson($maybe['reply'] !== '' ? $maybe['reply'] : 'Done.'),
                    'actions' => $maybe['actions'] ?: $this->actionsFromMatches($mapMatches),
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
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $raw = $m[0];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
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
        return (bool) preg_match('/\b(create|new|write|draft)\b.*\b(page|post|article|about\s*us|content)\b/i', $m)
            || (bool) preg_match('/\babout\s*us\b/i', $m) && preg_match('/\b(create|new|write|make)\b/i', $m);
    }

    /** @return array{type:string,label:string,payload:array} */
    private function buildCreateContentAction(string $message): array
    {
        $title = 'About Us';
        if (preg_match('/about\s*us/i', $message)) {
            $title = 'About Us';
        } elseif (preg_match('/(?:create|new|write|draft)\s+(?:a\s+|an\s+)?(?:page|post|article)\s+(?:on|about|for|called|titled)?\s*[\"\']?([^\"\'.\n]+)/i', $message, $m)) {
            $title = trim($m[1]);
        } elseif (preg_match('/[\"\']([^\"\']{3,80})[\"\']/', $message, $m)) {
            $title = trim($m[1]);
        }

        $body = '<p>This is a draft created by Mova AI. Edit and publish when ready.</p>';
        if (preg_match('/about\s*us/i', $message)) {
            $body = '<h2>Who we are</h2><p>We are building something meaningful. Update this section with your story.</p>'
                . '<h2>What we do</h2><p>Describe your products, services, or mission here.</p>'
                . '<h2>Get in touch</h2><p>Add contact details or a call to action.</p>';
        }

        $type = preg_match('/\b(article|post)\b/i', $message) ? 'article' : 'page';

        return [
            'type' => 'create_content',
            'label' => 'Create draft: ' . $title,
            'payload' => [
                'title' => $title,
                'type' => $type,
                'body' => $body,
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
        return (bool) preg_match('/\b(add|expand|continue|extend|append|improve|rewrite|update|include|advantages|more\s+on|section)\b/i', $m);
    }

    /** @return array{type:string,label:string,payload:array} */
    private function buildUpdateContentAction(string $message, int $entityId): array
    {
        $body = '<h2>Further points</h2><p>Expanded content generated by Mova AI. Edit as needed.</p>';
        if (preg_match('/advantage/i', $message) && preg_match('/nigeria|nigerian/i', $message)) {
            $body = '<h2>Advantages for Nigerian education</h2>'
                . '<ul>'
                . '<li><strong>Human capital:</strong> Stronger literacy and skills raise employability and entrepreneurship.</li>'
                . '<li><strong>National development:</strong> Educated citizens support better governance, health outcomes, and innovation.</li>'
                . '<li><strong>Equity and mobility:</strong> Access to quality schooling helps reduce poverty across regions.</li>'
                . '<li><strong>Digital readiness:</strong> STEM and digital skills prepare youth for a modern economy.</li>'
                . '<li><strong>Social cohesion:</strong> Shared learning experiences can strengthen community and civic trust.</li>'
                . '</ul>';
        } elseif (preg_match('/advantage/i', $message)) {
            $body = '<h2>Key advantages</h2><ul><li>Point one — expand with your research.</li><li>Point two.</li><li>Point three.</li></ul>';
        }

        return [
            'type' => 'update_content',
            'label' => 'Update current draft',
            'payload' => [
                'id' => $entityId,
                'mode' => 'append',
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
