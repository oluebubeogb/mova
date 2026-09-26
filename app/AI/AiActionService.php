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
    public function execute(array $action, int $userId): array
    {
        $type = (string) ($action['type'] ?? '');
        return match ($type) {
            'create_content' => $this->createContent($action['payload'] ?? [], $userId),
            'update_content' => $this->updateContent($action['payload'] ?? [], $userId),
            'update_design_tokens' => $this->updateDesignTokens($action['payload'] ?? []),
            'navigate' => [
                'ok' => true,
                'message' => 'Navigate',
                'result' => ['path' => (string) ($action['path'] ?? '/hq')],
            ],
            default => ['ok' => false, 'error' => 'Unknown or unsupported action type'],
        };
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,message?:string,result?:array,error?:string}
     */
    private function createContent(array $payload, int $userId): array
    {
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
        $repo = new ContentRepository();
        $slug = trim((string) ($payload['slug'] ?? ''));
        if ($slug === '') {
            $slug = $repo->generateSlug($title);
        } else {
            $slug = $repo->generateSlug($slug);
        }

        $id = $repo->create([
            'type' => $type,
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt,
            'body' => $body,
            'status' => 'draft',
            'author_id' => $userId,
        ]);

        return [
            'ok' => true,
            'message' => 'Draft created',
            'result' => [
                'id' => $id,
                'title' => $title,
                'slug' => $slug,
                'path' => '/hq/content/edit/' . $id,
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

        // Keep as draft unless already published — never auto-publish
        if (($existing['status'] ?? '') !== 'published') {
            $data['status'] = 'draft';
        }

        if ($data === []) {
            return ['ok' => false, 'error' => 'Nothing to update'];
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
