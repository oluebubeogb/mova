<?php
/**
 * Mova — global HTML element styles (Design → Elements)
 * Phases 1–3: props, custom CSS, responsive, pseudos, targeting, optional JS
 */

namespace Mova\Theme;

use Mova\Core\Database;

class ElementStyles
{
    public const SETTING_KEY = 'element_styles';

    public const PSEUDOS = ['hover', 'focus', 'focus-visible', 'first-child', 'last-child'];

    public static function topTags(): array
    {
        return [
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'p', 'a', 'button', 'img',
            'table', 'tr', 'td', 'th',
            'ul', 'ol', 'li', 'hr',
            'blockquote', 'code', 'pre',
            'section', 'article', 'header', 'footer', 'nav',
            'div', 'span', 'form', 'input', 'label',
        ];
    }

    public static function allTags(): array
    {
        return array_values(array_unique(array_merge(self::topTags(), [
            'main', 'aside', 'figure', 'figcaption', 'picture', 'video', 'audio',
            'strong', 'em', 'small', 'mark', 'del', 'ins', 'sub', 'sup',
            'dl', 'dt', 'dd', 'thead', 'tbody', 'tfoot', 'caption',
            'textarea', 'select', 'option', 'fieldset', 'legend',
            'iframe', 'canvas', 'svg', 'path',
            'details', 'summary', 'dialog', 'menu',
            'address', 'time', 'abbr', 'cite', 'q', 'kbd', 'samp', 'var',
        ])));
    }

    public static function getAll(): array
    {
        try {
            $row = Database::fetch(
                "SELECT setting_value FROM settings WHERE setting_key = :k",
                ['k' => self::SETTING_KEY]
            );
            if ($row && $row['setting_value']) {
                $decoded = json_decode((string) $row['setting_value'], true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (\Throwable $e) {
        }
        return [];
    }

    public static function emptyChunk(): array
    {
        $chunk = ['props' => [], 'custom_css' => ''];
        foreach (self::PSEUDOS as $p) {
            $chunk[$p] = ['props' => [], 'custom_css' => ''];
        }
        return $chunk;
    }

    public static function emptyEntry(): array
    {
        return [
            'desktop' => self::emptyChunk(),
            'tablet' => self::emptyChunk(),
            'mobile' => self::emptyChunk(),
            'target' => ['class' => '', 'id' => '', 'selector' => ''],
            'custom_js' => '',
            'enable_js' => false,
        ];
    }

    public static function normalizeEntry(array $entry): array
    {
        $out = self::emptyEntry();

        // Legacy top-level props
        if (isset($entry['props']) || (isset($entry['custom_css']) && !isset($entry['desktop']))) {
            $out['desktop']['props'] = is_array($entry['props'] ?? null) ? $entry['props'] : [];
            $out['desktop']['custom_css'] = (string) ($entry['custom_css'] ?? '');
        }

        foreach (['desktop', 'tablet', 'mobile'] as $bp) {
            if (!isset($entry[$bp]) || !is_array($entry[$bp])) {
                continue;
            }
            $src = $entry[$bp];
            $out[$bp]['props'] = is_array($src['props'] ?? null) ? $src['props'] : [];
            $out[$bp]['custom_css'] = (string) ($src['custom_css'] ?? '');
            foreach (self::PSEUDOS as $pseudo) {
                if (isset($src[$pseudo]) && is_array($src[$pseudo])) {
                    $out[$bp][$pseudo] = [
                        'props' => is_array($src[$pseudo]['props'] ?? null) ? $src[$pseudo]['props'] : [],
                        'custom_css' => (string) ($src[$pseudo]['custom_css'] ?? ''),
                    ];
                }
            }
        }

        if (isset($entry['target']) && is_array($entry['target'])) {
            $out['target'] = [
                'class' => self::sanitizeClass((string) ($entry['target']['class'] ?? '')),
                'id' => self::sanitizeId((string) ($entry['target']['id'] ?? '')),
                'selector' => self::sanitizeSelector((string) ($entry['target']['selector'] ?? '')),
            ];
        }
        $out['custom_js'] = self::sanitizeJs((string) ($entry['custom_js'] ?? ''));
        $out['enable_js'] = !empty($entry['enable_js']);

        return $out;
    }

    public static function getTag(string $tag): array
    {
        $tag = self::normalizeTag($tag);
        $all = self::getAll();
        $entry = is_array($all[$tag] ?? null) ? $all[$tag] : [];
        return self::normalizeEntry($entry);
    }

    public static function saveTagEntry(string $tag, array $entry): void
    {
        $tag = self::normalizeTag($tag);
        if ($tag === '') {
            return;
        }
        $norm = self::normalizeEntry($entry);
        $allowed = array_flip(self::allowedProps());

        $clean = self::emptyEntry();
        $hasAny = false;

        foreach (['desktop', 'tablet', 'mobile'] as $bp) {
            $clean[$bp] = self::cleanChunk($norm[$bp], $allowed, $hasAny);
        }

        $clean['target'] = [
            'class' => self::sanitizeClass((string) ($norm['target']['class'] ?? '')),
            'id' => self::sanitizeId((string) ($norm['target']['id'] ?? '')),
            'selector' => self::sanitizeSelector((string) ($norm['target']['selector'] ?? '')),
        ];
        if ($clean['target']['class'] !== '' || $clean['target']['id'] !== '' || $clean['target']['selector'] !== '') {
            $hasAny = true;
        }
        $clean['custom_js'] = self::sanitizeJs((string) ($norm['custom_js'] ?? ''));
        $clean['enable_js'] = !empty($norm['enable_js']) && $clean['custom_js'] !== '';
        if ($clean['enable_js']) {
            $hasAny = true;
        }

        $all = self::getAll();
        if (!$hasAny) {
            unset($all[$tag]);
        } else {
            $clean['updated_at'] = date('c');
            $all[$tag] = $clean;
        }
        self::persist($all);
    }

    private static function cleanChunk(array $chunk, array $allowed, bool &$hasAny): array
    {
        $out = self::emptyChunk();
        $out['props'] = self::cleanProps($chunk['props'] ?? [], $allowed);
        $out['custom_css'] = self::sanitizeCustomCss((string) ($chunk['custom_css'] ?? ''));
        if ($out['props'] !== [] || trim($out['custom_css']) !== '') {
            $hasAny = true;
        }
        foreach (self::PSEUDOS as $pseudo) {
            $p = is_array($chunk[$pseudo] ?? null) ? $chunk[$pseudo] : [];
            $out[$pseudo] = [
                'props' => self::cleanProps($p['props'] ?? [], $allowed),
                'custom_css' => self::sanitizeCustomCss((string) ($p['custom_css'] ?? '')),
            ];
            if ($out[$pseudo]['props'] !== [] || trim($out[$pseudo]['custom_css']) !== '') {
                $hasAny = true;
            }
        }
        return $out;
    }

    private static function cleanProps($props, array $allowed): array
    {
        if (!is_array($props)) {
            return [];
        }
        $clean = [];
        foreach ($props as $k => $v) {
            $k = self::normalizePropKey((string) $k);
            if (!isset($allowed[$k])) {
                continue;
            }
            $v = self::sanitizeCssValue((string) $v);
            if ($k !== '' && $v !== '') {
                $clean[$k] = $v;
            }
        }
        return $clean;
    }

    /** @deprecated */
    public static function saveTagBreakpoints(string $tag, array $breakpoints): void
    {
        $entry = self::emptyEntry();
        foreach (['desktop', 'tablet', 'mobile'] as $bp) {
            if (isset($breakpoints[$bp]) && is_array($breakpoints[$bp])) {
                $entry[$bp]['props'] = $breakpoints[$bp]['props'] ?? [];
                $entry[$bp]['custom_css'] = (string) ($breakpoints[$bp]['custom_css'] ?? '');
                foreach (self::PSEUDOS as $pseudo) {
                    if (isset($breakpoints[$bp][$pseudo])) {
                        $entry[$bp][$pseudo] = $breakpoints[$bp][$pseudo];
                    }
                }
            }
        }
        if (isset($breakpoints['target'])) {
            $entry['target'] = $breakpoints['target'];
        }
        if (isset($breakpoints['custom_js'])) {
            $entry['custom_js'] = $breakpoints['custom_js'];
        }
        if (isset($breakpoints['enable_js'])) {
            $entry['enable_js'] = $breakpoints['enable_js'];
        }
        self::saveTagEntry($tag, $entry);
    }

    public static function persist(array $all): void
    {
        $json = json_encode($all, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        Database::query(
            "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :t)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v2, updated_at = :t2",
            [
                'k' => self::SETTING_KEY,
                'v' => $json,
                't' => date('c'),
                'v2' => $json,
                't2' => date('c'),
            ]
        );
    }

    public static function importAll(array $incoming, bool $merge = true): int
    {
        $all = $merge ? self::getAll() : [];
        $count = 0;
        foreach ($incoming as $tag => $entry) {
            $tag = self::normalizeTag((string) $tag);
            if ($tag === '' || !is_array($entry)) {
                continue;
            }
            $all[$tag] = self::normalizeEntry($entry);
            $all[$tag]['updated_at'] = date('c');
            $count++;
        }
        // Re-save through cleaner
        $cleanAll = [];
        foreach ($all as $tag => $entry) {
            $hasAny = false;
            $allowed = array_flip(self::allowedProps());
            $c = self::emptyEntry();
            $norm = self::normalizeEntry(is_array($entry) ? $entry : []);
            foreach (['desktop', 'tablet', 'mobile'] as $bp) {
                $c[$bp] = self::cleanChunk($norm[$bp], $allowed, $hasAny);
            }
            $c['target'] = [
                'class' => self::sanitizeClass((string) ($norm['target']['class'] ?? '')),
                'id' => self::sanitizeId((string) ($norm['target']['id'] ?? '')),
                'selector' => self::sanitizeSelector((string) ($norm['target']['selector'] ?? '')),
            ];
            if ($c['target']['class'] !== '' || $c['target']['id'] !== '' || $c['target']['selector'] !== '') {
                $hasAny = true;
            }
            $c['custom_js'] = self::sanitizeJs((string) ($norm['custom_js'] ?? ''));
            $c['enable_js'] = !empty($norm['enable_js']) && $c['custom_js'] !== '';
            if ($c['enable_js']) {
                $hasAny = true;
            }
            if ($hasAny) {
                $c['updated_at'] = date('c');
                $cleanAll[$tag] = $c;
            }
        }
        self::persist($cleanAll);
        return $count;
    }

    public static function compileCss(): string
    {
        $all = self::getAll();
        if ($all === []) {
            return '';
        }

        $desktop = [];
        $tablet = [];
        $mobile = [];

        foreach ($all as $tag => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $tag = self::normalizeTag((string) $tag);
            if ($tag === '') {
                continue;
            }
            $norm = self::normalizeEntry($entry);
            $baseSel = self::selectorForTag($tag, $norm['target']);

            self::appendCompiled($desktop, $baseSel, $norm['desktop']);
            self::appendCompiled($tablet, $baseSel, $norm['tablet']);
            self::appendCompiled($mobile, $baseSel, $norm['mobile']);
        }

        $out = [];
        if ($desktop) {
            $out[] = implode("\n\n", $desktop);
        }
        if ($tablet) {
            $out[] = "@media (max-width: 768px) {\n" . self::indentBlocks($tablet) . "\n}";
        }
        if ($mobile) {
            $out[] = "@media (max-width: 480px) {\n" . self::indentBlocks($mobile) . "\n}";
        }
        return implode("\n\n", $out);
    }

    /** Public custom JS snippets (only enabled tags) */
    public static function compileJs(): string
    {
        $all = self::getAll();
        $parts = [];
        foreach ($all as $tag => $entry) {
            if (!is_array($entry) || empty($entry['enable_js'])) {
                continue;
            }
            $js = self::sanitizeJs((string) ($entry['custom_js'] ?? ''));
            if ($js === '') {
                continue;
            }
            $tag = self::normalizeTag((string) $tag);
            $parts[] = "/* element: {$tag} */\n(function(){\ntry {\n{$js}\n} catch(e) { console.warn('Mova element JS', e); }\n})();";
        }
        return implode("\n\n", $parts);
    }

    private static function appendCompiled(array &$blocks, string $baseSel, array $chunk): void
    {
        $rules = self::rulesFromPropsChunk($chunk);
        if ($rules !== '') {
            $blocks[] = $baseSel . " {\n" . $rules . "\n}";
        }
        foreach (self::PSEUDOS as $pseudo) {
            $p = is_array($chunk[$pseudo] ?? null) ? $chunk[$pseudo] : [];
            $pr = self::rulesFromPropsChunk($p);
            if ($pr === '') {
                continue;
            }
            $blocks[] = $baseSel . ':' . $pseudo . " {\n" . $pr . "\n}";
        }
    }

    private static function rulesFromPropsChunk(array $chunk): string
    {
        $props = is_array($chunk['props'] ?? null) ? $chunk['props'] : [];
        $lines = [];
        foreach ($props as $prop => $value) {
            $prop = self::normalizePropKey((string) $prop);
            $value = self::sanitizeCssValue((string) $value);
            if ($prop !== '' && $value !== '') {
                $lines[] = '  ' . $prop . ': ' . $value . ';';
            }
        }
        $custom = self::sanitizeCustomCss((string) ($chunk['custom_css'] ?? ''));
        if ($custom !== '') {
            foreach (preg_split("/\r\n|\n|\r/", $custom) as $cline) {
                $cline = trim($cline);
                if ($cline === '' || str_starts_with($cline, '/*')) {
                    continue;
                }
                if (preg_match('/^([a-zA-Z\-]+)\s*:\s*(.+);?\s*$/', $cline, $m)) {
                    $p = self::normalizePropKey($m[1]);
                    $v = self::sanitizeCssValue(rtrim($m[2], ';'));
                    if ($p !== '' && $v !== '') {
                        $lines[] = '  ' . $p . ': ' . $v . ';';
                    }
                }
            }
        }
        return implode("\n", $lines);
    }

    private static function indentBlocks(array $blocks): string
    {
        $lines = [];
        foreach ($blocks as $b) {
            foreach (explode("\n", $b) as $line) {
                $lines[] = '  ' . $line;
            }
            $lines[] = '';
        }
        return rtrim(implode("\n", $lines));
    }

    public static function selectorForTag(string $tag, array $target = []): string
    {
        // Free-form selector (e.g. "#main .home", ".card h3") — scoped under .site-main when relative
        $free = self::sanitizeSelector((string) ($target['selector'] ?? ''));
        if ($free !== '') {
            if (str_starts_with($free, '.site-main') || str_starts_with($free, 'html') || str_starts_with($free, 'body') || str_starts_with($free, ':root')) {
                return $free;
            }
            return '.site-main ' . $free;
        }

        $tag = self::normalizeTag($tag);
        if ($tag === '') {
            return '.site-main';
        }
        $sel = '.site-main ' . $tag;
        $id = self::sanitizeId((string) ($target['id'] ?? ''));
        $class = self::sanitizeClass((string) ($target['class'] ?? ''));
        if ($id !== '') {
            $sel .= '#' . $id;
        }
        if ($class !== '') {
            foreach (preg_split('/\s+/', $class) as $c) {
                $c = self::sanitizeClass($c);
                if ($c !== '') {
                    $sel .= '.' . $c;
                }
            }
        } else {
            // Bare tag rules (e.g. div, section) must not restyle layout chrome.
            // .container is a div inside .site-main — padding/margin on "div"
            // was collapsing horizontal page gutters site-wide.
            $exclude = self::layoutChromeExclusions($tag);
            if ($exclude !== '') {
                $sel .= $exclude;
            }
        }
        return $sel;
    }

    /**
     * :not(...) chain so generic element styles skip structural wrappers.
     */
    private static function layoutChromeExclusions(string $tag): string
    {
        // Classes used by the default/documentation themes for page chrome
        $classes = [
            'container',
            'site-header',
            'site-footer',
            'site-main',
            'header-actions',
            'nav',
            'nav-drawer',
            'nav-drawer-links',
            'nav-backdrop',
            'nav-toggle',
            'theme-toggle',
            'icon-label',
            'icon-label-text',
            'logo',
        ];
        // Protect header chrome controls so generic "button" styles cannot hide icons
        if ($tag === 'button') {
            $btnChrome = ['nav-toggle', 'theme-toggle', 'header-icon-btn', 'nav-drawer-action'];
            $parts = [];
            foreach ($btnChrome as $c) {
                $parts[] = ':not(.' . $c . ')';
            }
            return implode('', $parts);
        }
        // Only meaningful for elements that can carry those classes
        if (!in_array($tag, ['div', 'section', 'header', 'footer', 'nav', 'main', 'aside'], true)) {
            return '';
        }
        $parts = [];
        foreach ($classes as $c) {
            $parts[] = ':not(.' . $c . ')';
        }
        return implode('', $parts);
    }

    public static function normalizeTag(string $tag): string
    {
        $tag = strtolower(trim($tag));
        return preg_replace('/[^a-z0-9]/', '', $tag) ?? '';
    }

    public static function normalizePropKey(string $key): string
    {
        $key = strtolower(trim($key));
        return preg_replace('/[^a-z\-]/', '', $key) ?? '';
    }

    public static function sanitizeCssValue(string $value): string
    {
        $value = trim($value);
        $value = str_replace(["\0", '<', '>', '{', '}'], '', $value);
        $value = str_replace(';', '', $value);
        if (preg_match('/expression\s*\(|javascript\s*:/i', $value)) {
            return '';
        }
        if (strlen($value) > 500) {
            $value = substr($value, 0, 500);
        }
        return $value;
    }

    public static function sanitizeCustomCss(string $css): string
    {
        $css = str_replace("\0", '', $css);
        if (preg_match('/@import|expression\s*\(|javascript\s*:/i', $css)) {
            $out = [];
            foreach (preg_split("/\r\n|\n|\r/", $css) as $line) {
                if (preg_match('/@import|expression\s*\(|javascript\s*:/i', $line)) {
                    continue;
                }
                $out[] = $line;
            }
            $css = implode("\n", $out);
        }
        if (strlen($css) > 8000) {
            $css = substr($css, 0, 8000);
        }
        return $css;
    }

    public static function sanitizeClass(string $class): string
    {
        $class = trim($class);
        $class = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $class) ?? '';
        return trim(preg_replace('/\s+/', ' ', $class) ?? '');
    }

    public static function sanitizeId(string $id): string
    {
        $id = trim($id);
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $id) ?? '';
    }

    /**
     * Free-form CSS selector fragment (no braces, no @rules).
     * Allows #id, .class, combinators, element names — used for target.selector.
     */
    public static function sanitizeSelector(string $sel): string
    {
        $sel = trim($sel);
        $sel = str_replace(["\0", '{', '}', '<', '>'], '', $sel);
        if (preg_match('/@import|expression\s*\(|javascript\s*:/i', $sel)) {
            return '';
        }
        // Keep common selector characters only
        $sel = preg_replace('/[^a-zA-Z0-9_\-#\.\s:>\+~\*\[\]=\"\'\|\(\),]/', '', $sel) ?? '';
        $sel = trim(preg_replace('/\s+/', ' ', $sel) ?? '');
        if (strlen($sel) > 200) {
            $sel = substr($sel, 0, 200);
        }
        return $sel;
    }

    public static function sanitizeJs(string $js): string
    {
        $js = str_replace("\0", '', $js);
        // Block obvious dangerous patterns
        if (preg_match('/\bdocument\.write\b|\beval\s*\(|\bFunction\s*\(|<script|javascript\s*:/i', $js)) {
            return '';
        }
        if (strlen($js) > 6000) {
            $js = substr($js, 0, 6000);
        }
        return $js;
    }

    public static function allowedProps(): array
    {
        return [
            'font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing',
            'text-align', 'text-decoration', 'text-transform',
            'color', 'background-color', 'border-color',
            'margin', 'padding', 'gap',
            'width', 'height', 'min-width', 'max-width', 'min-height', 'max-height',
            'border-width', 'border-style', 'border-radius', 'border',
            'display', 'position', 'top', 'right', 'bottom', 'left', 'z-index',
            'overflow', 'overflow-x', 'overflow-y',
            'flex-direction', 'flex-wrap', 'justify-content', 'align-items', 'align-content',
            'flex', 'flex-grow', 'flex-shrink', 'flex-basis', 'align-self', 'order',
            'grid-template-columns', 'grid-template-rows', 'grid-template-areas',
            'grid-column', 'grid-row', 'grid-gap', 'row-gap', 'column-gap',
            'justify-items', 'place-items',
            'background-image', 'background-size', 'background-position', 'background-repeat',
            'background-attachment', 'background', 'background-clip', 'background-origin',
            'box-shadow', 'opacity', 'filter', 'backdrop-filter', 'mix-blend-mode',
            'transform', 'transform-origin',
            // Animation (P3)
            'transition', 'transition-property', 'transition-duration', 'transition-timing-function', 'transition-delay',
            'animation', 'animation-name', 'animation-duration', 'animation-timing-function',
            'animation-delay', 'animation-iteration-count', 'animation-direction', 'animation-fill-mode',
            'scroll-behavior', 'scroll-margin', 'scroll-padding',
            'cursor', 'pointer-events', 'user-select', 'will-change',
            'object-fit', 'object-position', 'aspect-ratio',
        ];
    }

    /** Tags that currently have any styles */
    public static function styledTags(): array
    {
        return array_keys(self::getAll());
    }
}
