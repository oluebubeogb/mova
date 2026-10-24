<?php
/**
 * Mova AI — execute structured HQ actions (Phase 5).
 * Writes are constrained: content always draft; design requires explicit apply.
 */

namespace Mova\AI;

use Mova\Auth\Auth;
use Mova\Content\ContentRepository;
use Mova\Theme\DesignConfig;

class AiActionService
{
    /**
     * @param array<string,mixed> $action
     * @return array{ok:bool,message?:string,result?:array,error?:string}
     */
    /**
     * Role gate, evaluated from the DB so it also holds in background workers.
     * Returns an error message when the user may not run this action.
     */
    private function authorize(string $type, mixed $payload, int $userId): ?string
    {
        if ($type === 'navigate') {
            return null;
        }
        $role = null;
        try {
            $row = \Mova\Core\Database::fetch("SELECT role FROM users WHERE id = :id", ['id' => $userId]);
            $role = $row['role'] ?? null;
        } catch (\Throwable $e) {
        }
        $can = static fn (string $p): bool => \Mova\Auth\Auth::roleCan($role, $p);
        switch ($type) {
            case 'create_content':
                return ($can('manage_content') || $can('manage_own_content')) ? null : 'Your role cannot create content.';
            case 'update_content':
                if ($can('manage_content')) {
                    return null;
                }
                if ($can('manage_own_content')) {
                    $id = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;
                    $owner = $id > 0 ? \Mova\Core\Database::fetch("SELECT author_id FROM content WHERE id = :id", ['id' => $id]) : null;
                    if ($owner && (int) ($owner['author_id'] ?? 0) === $userId) {
                        return null;
                    }
                }
                return 'Your role cannot edit this content.';
            case 'update_design_tokens':
            case 'switch_theme':
                return $can('manage_appearance') ? null : 'Only owners and administrators can change the design.';
        }
        return null;
    }

    /** Action types that change design, theme or live structure → always need an explicit confirm. */
    public const CONFIRM_TYPES = ['update_design_tokens', 'switch_theme'];

    /**
     * Whether this action must be confirmed by a human before it runs.
     * Design tokens and theme switches always; content updates when they replace a body
     * or touch already-published content (visitors would see it immediately).
     */
    public function needsConfirm(array $action): bool
    {
        $type = (string) ($action['type'] ?? '');
        if (in_array($type, self::CONFIRM_TYPES, true)) {
            return true;
        }
        if ($type === 'update_content') {
            $p = is_array($action['payload'] ?? null) ? $action['payload'] : [];
            if (($p['mode'] ?? 'append') === 'replace') {
                return true;
            }
            $id = (int) ($p['id'] ?? 0);
            if ($id > 0) {
                try {
                    $row = \Mova\Core\Database::fetch('SELECT status FROM content WHERE id = :id', ['id' => $id]);
                    return ($row['status'] ?? '') === 'published';
                } catch (\Throwable $e) {
                }
            }
        }
        return false;
    }

    /**
     * Human-readable preview of what an action would change (for the confirm dialog).
     *
     * @return array{title:string,lines:list<array{label:string,from?:string,to?:string,swatch?:bool}>,warning:string}
     */
    public function preview(array $action): array
    {
        $type = (string) ($action['type'] ?? '');
        $p = is_array($action['payload'] ?? null) ? $action['payload'] : [];
        $lines = [];
        $warning = '';
        $title = (string) ($action['label'] ?? 'Apply change');
        if ($type === 'update_design_tokens') {
            $tokens = DesignConfig::tokens();
            foreach (['colors' => '', 'colors_dark' => ' (dark)'] as $set => $suffix) {
                foreach ((array) ($p[$set] ?? []) as $k => $v) {
                    if (!is_string($k)) {
                        continue;
                    }
                    $from = (string) ($tokens[$set][$k] ?? '');
                    $to = $this->normalizeHex((string) $v, $from ?: '#000000');
                    $lines[] = ['label' => ucfirst($k) . $suffix, 'from' => $from, 'to' => $to, 'swatch' => true];
                }
            }
            $title = 'Change site colours';
            $warning = 'Applies to the live site immediately. You can change it back in Design → Style.';
        } elseif ($type === 'switch_theme') {
            $tm = new \Mova\Theme\ThemeManager();
            $lines[] = ['label' => 'Theme', 'from' => $tm->activeSlug(), 'to' => (string) ($p['theme'] ?? '')];
            $title = 'Switch the site theme';
            $warning = 'Visitors see the new theme immediately. Your content is not changed.';
        } elseif ($type === 'update_content') {
            $id = (int) ($p['id'] ?? 0);
            $row = $id > 0 ? (new ContentRepository())->find($id) : null;
            $words = static fn (string $h): int => str_word_count(strip_tags($h));
            $mode = (string) ($p['mode'] ?? 'append');
            $title = ($mode === 'replace' ? 'Replace the body of ' : 'Add to ') . '«' . (string) ($row['title'] ?? ('#' . $id)) . '»';
            $lines[] = ['label' => 'Status', 'to' => (string) ($row['status'] ?? 'unknown')];
            $lines[] = ['label' => 'Body', 'from' => $words((string) ($row['body'] ?? '')) . ' words', 'to' => ($mode === 'replace' ? $words((string) ($p['body'] ?? '')) : $words((string) ($row['body'] ?? '')) + $words((string) ($p['body'] ?? ''))) . ' words'];
            if (!empty($p['title'])) {
                $lines[] = ['label' => 'Title', 'from' => (string) ($row['title'] ?? ''), 'to' => (string) $p['title']];
            }
            $warning = ($row['status'] ?? '') === 'published'
                ? 'This page is published — visitors will see the change right away. A revision is kept.'
                : 'The current body will be overwritten (a revision is kept).';
        }
        return ['title' => $title, 'lines' => $lines, 'warning' => $warning];
    }

    /**
     * @param bool $confirmed true only when a human confirmed this exact action in the UI.
     */
    public function execute(array $action, int $userId, bool $confirmed = false): array
    {
        $type = (string) ($action['type'] ?? '');
        $deny = $this->authorize($type, $action['payload'] ?? [], $userId);
        if ($deny !== null) {
            return ['ok' => false, 'error' => $deny, 'forbidden' => true];
        }
        if (!$confirmed && $this->needsConfirm($action)) {
            return ['ok' => false, 'needs_confirm' => true, 'preview' => $this->preview($action), 'error' => 'Please confirm this change first.'];
        }
        return match ($type) {
            'create_content' => $this->createContent($action['payload'] ?? [], $userId),
            'update_content' => $this->updateContent($action['payload'] ?? [], $userId),
            'update_design_tokens' => $this->updateDesignTokens($action['payload'] ?? []),
            'switch_theme' => $this->switchTheme($action['payload'] ?? []),
            'navigate' => [
                'ok' => true,
                'message' => 'Navigate',
                'result' => ['path' => (string) ($action['path'] ?? '/hq')],
            ],
            default => ['ok' => false, 'error' => 'Unknown or unsupported action type'],
        };
    }

    /** @param array<string,mixed> $payload */
    private function switchTheme(array $payload): array
    {
        $slug = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($payload['theme'] ?? '')) ?? '';
        $tm = new \Mova\Theme\ThemeManager();
        $known = array_column($tm->all(), 'slug');
        if ($slug === '' || !in_array($slug, $known, true)) {
            return ['ok' => false, 'error' => 'Unknown theme. Available: ' . implode(', ', $known)];
        }
        if (!$tm->setActive($slug)) {
            return ['ok' => false, 'error' => 'Could not activate theme'];
        }
        return ['ok' => true, 'message' => 'Theme switched', 'result' => ['theme' => $slug, 'path' => '/hq/themes', 'reload' => true]];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,message?:string,result?:array,error?:string}
     */
    private function createContent(array $payload, int $userId): array
    {
        // Final safety net before anything reaches the editor / database: literal "\u2019"-style
        // escapes (incl. surrogate pairs) → real characters, U+2011 → "-", U+202F → " ".
        // Bare "u2019" without a backslash is never touched here.
        $payload = AiText::cleanDeep($payload);
        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'error' => 'Title is required'];
        }

        $type = trim((string) ($payload['type'] ?? 'page'));
        $allowedTypes = ['article', 'page', 'guide', 'documentation', 'faq', 'custom'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'page';
        }

        $body = (string) ($payload['body'] ?? '');
        $excerpt = trim((string) ($payload['excerpt'] ?? ''));
        $meta = $this->normalizeContentMeta($payload['meta'] ?? null);
        $repo = new ContentRepository();
        $slug = trim((string) ($payload['slug'] ?? ''));
        if ($slug === '') {
            $slug = $repo->generateSlug($title);
        } else {
            $slug = $repo->generateSlug($slug);
        }

        $createData = [
            'type' => $type,
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt,
            'body' => $body,
            'status' => 'draft',
            'author_id' => $userId,
        ];
        if ($meta !== []) {
            $createData['meta'] = $meta;
        }

        $id = $repo->create($createData);

        return [
            'ok' => true,
            'message' => 'Draft created',
            'result' => [
                'id' => $id,
                'title' => $title,
                'slug' => $slug,
                'path' => '/hq/content/edit/' . $id,
                'excerpt' => $excerpt,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,message?:string,result?:array,error?:string}
     */
    private function updateDesignTokens(array $payload): array
    {
        $tokens = DesignConfig::tokens();
        $colorKeys = ['primary', 'secondary', 'accent', 'background', 'surface', 'text', 'muted', 'border'];
        $changed = [];

        if (isset($payload['colors']) && is_array($payload['colors'])) {
            foreach ($colorKeys as $ck) {
                if (!isset($payload['colors'][$ck])) {
                    continue;
                }
                $hex = $this->normalizeHex((string) $payload['colors'][$ck], $tokens['colors'][$ck] ?? '#000000');
                $tokens['colors'][$ck] = $hex;
                $changed['colors.' . $ck] = $hex;
            }
        }

        if (isset($payload['colors_dark']) && is_array($payload['colors_dark'])) {
            foreach ($colorKeys as $ck) {
                if (!isset($payload['colors_dark'][$ck])) {
                    continue;
                }
                $hex = $this->normalizeHex((string) $payload['colors_dark'][$ck], $tokens['colors_dark'][$ck] ?? '#ffffff');
                $tokens['colors_dark'][$ck] = $hex;
                $changed['colors_dark.' . $ck] = $hex;
            }
        }

        if ($changed === []) {
            return ['ok' => false, 'error' => 'No valid color tokens to update'];
        }

        DesignConfig::saveTokens($tokens);

        // CSS vars for seamless client-side apply (no full reload)
        $cssVars = [];
        foreach ($changed as $path => $hex) {
            // path like colors.primary or colors_dark.primary
            if (str_starts_with($path, 'colors_dark.')) {
                $key = substr($path, strlen('colors_dark.'));
                $cssKey = $key === 'background' ? 'background' : $key;
                $cssVars['--color-' . ($cssKey === 'background' ? 'bg' : $cssKey) . '-dark'] = $hex;
                if ($cssKey === 'background') {
                    $cssVars['--color-background-dark'] = $hex;
                }
            } elseif (str_starts_with($path, 'colors.')) {
                $key = substr($path, strlen('colors.'));
                $cssKey = $key === 'background' ? 'bg' : $key;
                $cssVars['--color-' . $cssKey . '-light'] = $hex;
                if ($key === 'background') {
                    $cssVars['--color-background-light'] = $hex;
                }
            }
        }

        return [
            'ok' => true,
            'message' => 'Design tokens updated',
            'result' => [
                'changed' => $changed,
                'path' => '/hq/style',
                'css_vars' => $cssVars,
                'soft' => true,
            ],
        ];
    }

    /**
     * Expand / rewrite an existing draft (preferred when user is on content edit).
     *
     * @param array<string,mixed> $payload
     * @return array{ok:bool,message?:string,result?:array,error?:string}
     */
    private function updateContent(array $payload, int $userId): array
    {
        // Final safety net before anything reaches the editor / database: literal "\u2019"-style
        // escapes (incl. surrogate pairs) → real characters, U+2011 → "-", U+202F → " ".
        // Bare "u2019" without a backslash is never touched here.
        $payload = AiText::cleanDeep($payload);
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            return ['ok' => false, 'error' => 'Content id is required'];
        }

        $repo = new ContentRepository();
        $existing = $repo->find($id);
        if (!$existing) {
            return ['ok' => false, 'error' => 'Content not found'];
        }

        $mode = (string) ($payload['mode'] ?? 'append'); // append | replace
        $newBody = (string) ($payload['body'] ?? '');
        $title = trim((string) ($payload['title'] ?? ''));
        $excerpt = trim((string) ($payload['excerpt'] ?? ''));
        $meta = $this->normalizeContentMeta($payload['meta'] ?? null);

        $data = [];
        if ($newBody !== '') {
            if ($mode === 'replace') {
                $data['body'] = $newBody;
            } else {
                $old = (string) ($existing['body'] ?? '');
                $data['body'] = rtrim($old) . "\n\n" . ltrim($newBody);
            }
        }
        if ($title !== '') {
            $data['title'] = $title;
        }
        if ($excerpt !== '') {
            $data['excerpt'] = $excerpt;
        }
        if ($meta !== []) {
            $data['meta'] = $meta;
        }

        // Keep as draft unless already published — never auto-publish
        if (($existing['status'] ?? '') !== 'published') {
            $data['status'] = 'draft';
        }

        if ($data === []) {
            return ['ok' => false, 'error' => 'Nothing to update'];
        }

        try {
            // Snapshot first so an AI edit can always be rolled back from Revisions.
            $repo->createRevision($id, [
                'title' => $existing['title'] ?? null, 'body' => $existing['body'] ?? null,
                'excerpt' => $existing['excerpt'] ?? null, 'meta' => $existing['meta'] ?? [],
            ], $userId ?: null);
        } catch (\Throwable $e) {
        }
        $repo->update($id, $data);
        $fresh = $repo->find($id) ?: $existing;

        return [
            'ok' => true,
            'message' => 'Draft updated',
            'result' => [
                'id' => $id,
                'title' => (string) ($fresh['title'] ?? $title),
                'body' => (string) ($fresh['body'] ?? ''),
                'excerpt' => (string) ($fresh['excerpt'] ?? ''),
                'path' => '/hq/content/edit/' . $id,
                'soft' => true,
            ],
        ];
    }


    /**
     * Accept SEO / content meta from AI payloads.
     * @param mixed $meta
     * @return array<string,string>
     */
    private function normalizeContentMeta($meta): array
    {
        if (!is_array($meta)) {
            return [];
        }
        $allowed = [
            'seo_title',
            'meta_description',
            'og_title',
            'og_description',
            'robots',
            'canonical',
        ];
        $out = [];
        foreach ($allowed as $key) {
            if (!isset($meta[$key])) {
                continue;
            }
            $val = trim((string) $meta[$key]);
            if ($val !== '') {
                $out[$key] = $val;
            }
        }
        return $out;
    }

    private function normalizeHex(string $v, string $fallback): string
    {
        $v = trim($v);
        if (preg_match('/^#([0-9A-Fa-f]{3})$/', $v, $m)) {
            $h = $m[1];
            return '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        if (preg_match('/^#([0-9A-Fa-f]{6})$/', $v)) {
            return strtolower($v);
        }
        if (preg_match('/^([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $v, $m)) {
            $h = $m[1];
            if (strlen($h) === 3) {
                return '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
            }
            return '#' . strtolower($h);
        }
        return $fallback;
    }
}
