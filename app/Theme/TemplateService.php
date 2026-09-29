<?php
/**
 * Mova design templates — seed files + optional DB overrides.
 *
 * Seeds live in resources/templates/ (shipped with the product).
 * DB table design_templates can override body/css/meta per id.
 * Shared CSS is always available at /assets/css/templates.css
 */

namespace Mova\Theme;

use Mova\Core\Bootstrap;
use Mova\Core\Database;

class TemplateService
{
    public static function seedsRoot(): string
    {
        $root = Bootstrap::path('root');
        if ($root === '.' || $root === '') {
            $root = dirname(__DIR__, 2);
        }
        return rtrim($root, '/\\') . '/resources/templates';
    }

    /**
     * @return list<array{id:string,name:string,category:string,description:string,preview:string,path:string,source:string}>
     */
    public static function listAll(): array
    {
        $items = [];
        $manifest = self::manifest();
        foreach ($manifest['templates'] ?? [] as $entry) {
            $id = (string) ($entry['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $resolved = self::get($id);
            if ($resolved === null) {
                continue;
            }
            $meta = $resolved['meta'];
            $items[] = [
                'id' => $id,
                'name' => (string) ($meta['name'] ?? $id),
                'category' => (string) ($meta['category'] ?? ($entry['category'] ?? 'general')),
                'description' => (string) ($meta['description'] ?? ''),
                'preview' => (string) ($meta['preview'] ?? ''),
                'path' => (string) ($entry['path'] ?? ''),
                'source' => $resolved['source'],
            ];
        }
        return $items;
    }

    /**
     * @return array{meta:array,body:string,styles:string,source:string}|null
     */
    public static function get(string $id): ?array
    {
        $id = self::sanitizeId($id);
        if ($id === '') {
            return null;
        }

        $override = self::dbOverride($id);
        if ($override !== null) {
            return $override;
        }

        return self::seed($id);
    }

    /**
     * Prompt block for HQ AI coding when a template is selected.
     */
    public static function promptBlock(string $id): string
    {
        $tpl = self::get($id);
        if ($tpl === null) {
            return '';
        }
        $meta = $tpl['meta'];
        $name = (string) ($meta['name'] ?? $id);
        $constraints = $meta['constraints'] ?? [];
        $slots = $meta['slots'] ?? [];
        $constraintLines = '';
        if (is_array($constraints)) {
            foreach ($constraints as $c) {
                $constraintLines .= '- ' . $c . "\n";
            }
        }
        $slotLines = is_array($slots) ? implode(', ', $slots) : '';

        return "TEMPLATE MODE — adapt this design template; do not invent a new layout from scratch.\n"
            . "Template id: {$id}\n"
            . "Name: {$name}\n"
            . "Slots you may change: {$slotLines}\n"
            . "Constraints:\n{$constraintLines}"
            . "Shared CSS is already loaded on the site as /assets/css/templates.css — "
            . "prefer keeping class names so shared styles apply. Only emit extra ```css if a small delta is required.\n"
            . "Return adapted ```html (body fragment) using the same root classes.\n\n"
            . "=== TEMPLATE HTML ===\n"
            . $tpl['body'] . "\n"
            . "=== TEMPLATE EXTRA CSS (optional delta base) ===\n"
            . ($tpl['styles'] !== '' ? $tpl['styles'] : "(none — shared templates.css covers base)")
            . "\n=== END TEMPLATE ===\n";
    }

    /**
     * Detect template id from user message or explicit context.
     */
    public static function detectIdFromMessage(string $message, ?string $explicit = null): ?string
    {
        if ($explicit !== null && $explicit !== '') {
            $id = self::sanitizeId($explicit);
            return self::get($id) !== null ? $id : null;
        }
        // Explicit marker: template:pricing-saas-featured
        if (preg_match('/\btemplate\s*[:=]\s*([a-z0-9\-]+)/i', $message, $m)) {
            $id = self::sanitizeId($m[1]);
            return self::get($id) !== null ? $id : null;
        }
        // Fuzzy: mention known template names
        $lower = mb_strtolower($message);
        foreach (self::listAll() as $item) {
            $id = $item['id'];
            if (str_contains($lower, $id) || str_contains($lower, mb_strtolower($item['name']))) {
                return $id;
            }
        }
        // Soft keyword hints for default pricing template
        if (preg_match('/\b(pricelist|price list|pricing|membership plans?)\b/i', $message)
            && preg_match('/\b(template|use template|from template|based on)\b/i', $message)) {
            return 'pricing-saas-featured';
        }
        return null;
    }

    public static function sharedCssUrl(): string
    {
        return '/assets/css/templates.css';
    }

    /** @return array<string,mixed> */
    private static function manifest(): array
    {
        $file = self::seedsRoot() . '/manifest.json';
        if (!is_file($file)) {
            return ['version' => 1, 'templates' => []];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : ['version' => 1, 'templates' => []];
    }

    /** @return array{meta:array,body:string,styles:string,source:string}|null */
    private static function seed(string $id): ?array
    {
        $manifest = self::manifest();
        $pathRel = null;
        foreach ($manifest['templates'] ?? [] as $entry) {
            if (($entry['id'] ?? '') === $id) {
                $pathRel = (string) ($entry['path'] ?? '');
                break;
            }
        }
        if ($pathRel === null || $pathRel === '') {
            return null;
        }
        $dir = self::seedsRoot() . '/' . trim($pathRel, '/');
        if (!is_dir($dir)) {
            return null;
        }
        $meta = [];
        $metaFile = $dir . '/meta.json';
        if (is_file($metaFile)) {
            $decoded = json_decode((string) file_get_contents($metaFile), true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }
        $meta['id'] = $id;
        $body = is_file($dir . '/body.html') ? (string) file_get_contents($dir . '/body.html') : '';
        $styles = is_file($dir . '/styles.css') ? (string) file_get_contents($dir . '/styles.css') : '';
        if ($body === '') {
            return null;
        }
        return [
            'meta' => $meta,
            'body' => $body,
            'styles' => $styles,
            'source' => 'seed',
        ];
    }

    /** @return array{meta:array,body:string,styles:string,source:string}|null */
    private static function dbOverride(string $id): ?array
    {
        try {
            if (!class_exists(Database::class) || !Database::connection()) {
                return null;
            }
            $row = Database::fetch(
                'SELECT id, name, category, description, preview, body_html, styles_css, meta_json, enabled FROM design_templates WHERE template_id = :id LIMIT 1',
                ['id' => $id]
            );
            if (!$row || (string) ($row['enabled'] ?? '1') === '0') {
                return null;
            }
            $meta = [];
            if (!empty($row['meta_json'])) {
                $decoded = json_decode((string) $row['meta_json'], true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }
            $meta['id'] = $id;
            $meta['name'] = (string) ($row['name'] ?? ($meta['name'] ?? $id));
            $meta['category'] = (string) ($row['category'] ?? ($meta['category'] ?? 'general'));
            $meta['description'] = (string) ($row['description'] ?? ($meta['description'] ?? ''));
            if (!empty($row['preview'])) {
                $meta['preview'] = (string) $row['preview'];
            }
            $body = (string) ($row['body_html'] ?? '');
            if ($body === '') {
                return null;
            }
            return [
                'meta' => $meta,
                'body' => $body,
                'styles' => (string) ($row['styles_css'] ?? ''),
                'source' => 'db',
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function sanitizeId(string $id): string
    {
        $id = strtolower(trim($id));
        return preg_replace('/[^a-z0-9\-]/', '', $id) ?? '';
    }
}
