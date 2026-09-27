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
    public function chat(int $userId, ?int $sessionId, string $message, array $pageContext = []): array
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
        $llm = $this->callLlm($message, $history, $pageContext, $matches);

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
    private function callLlm(string $message, array $history, array $pageContext, array $mapMatches): array
    {
        $route = (string) ($pageContext['route'] ?? '');
        $area = (string) ($pageContext['area'] ?? '');
        $layer = (string) ($pageContext['layer'] ?? '');

        $matchLines = [];
        foreach (array_slice($mapMatches, 0, 5) as $m) {
            $matchLines[] = "{$m['label']} → {$m['path']}";
        }

        $entityId = (int) ($pageContext['entityId'] ?? 0);
        $paletteHelp = "Mova Style palette keys (ONLY these — do not invent 'link' or other keys): "
            . "primary, secondary, accent, background, surface, text, muted, border. "
            . "Links on the public site typically use primary (or accent for emphasis). "
            . "If the user says 'link color', map it to primary and say so in the reply.";

        $contentHelp = $entityId > 0
            ? "The user is editing content id={$entityId}. If they ask to add, expand, continue, or revise the article, use update_content with payload id={$entityId}, mode=append (or replace), and body HTML — do NOT create_content."
            : "When creating new pages use create_content (draft only).";

        $system = "You are Mova AI, the assistant inside Mova CMS HQ. "
            . "Help users navigate HQ, create/update draft content, and adjust design colors. Be concise. "
            . "Never publish content. Never invent HQ URLs — use the map. "
            . "{$paletteHelp} {$contentHelp} "
            . "When the user should open a screen, include navigate actions.\n\n"
            . HqMap::asPromptBlock(35) . "\n\n"
            . "Current page: route={$route} area={$area} layer={$layer} entityId={$entityId}\n"
            . "Top map matches for this message:\n" . ($matchLines ? implode("\n", $matchLines) : "(none)") . "\n\n"
            . "Respond with ONLY valid JSON (no markdown fences):\n"
            . '{"reply":"string","actions":[ '
            . '{"type":"navigate","label":"Open …","path":"/hq/..."}, '
            . '{"type":"create_content","label":"Create draft","payload":{"title":"…","type":"page","body":"…"}}, '
            . '{"type":"update_content","label":"Update draft","payload":{"id":' . max($entityId, 0) . ',"mode":"append","body":"…"}}, '
            . '{"type":"update_design_tokens","label":"Apply colors","payload":{"colors":{"primary":"#2563eb","accent":"#7c3aed"}}} '
            . "]}\n"
            . "Use 0–4 actions. navigate paths must start with /hq. Only use palette keys listed above.";

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
            $raw = $this->assist->chatRaw($messages, 2400);
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
