<?php
/**
 * Responsive <img> helpers for Mova media.
 * Progressive reveal + srcset that only references widths that actually exist
 * (e.g. a 764px upload yields -320/-480/-764, never a missing -1200).
 */
namespace Mova\Media;

class ImageTag
{
    /** Configured breakpoints (must match MediaService defaults). */
    public const WIDTHS = [320, 480, 768, 1200];

    /**
     * Parse a media URL/path into base, width (if any), and extension.
     *
     * @return array{base:string,width:?int,ext:string}|null
     */
    public static function parseSrc(string $src): ?array
    {
        $src = trim($src);
        if ($src === '') {
            return null;
        }
        // Any numeric size suffix: -320.webp, -764.webp, -1200.webp, etc.
        if (preg_match('#^(.*?)-(\d+)(\.(?:webp|jpe?g|png|gif))(?:\?.*)?$#i', $src, $m)) {
            return [
                'base'  => $m[1],
                'width' => (int) $m[2],
                'ext'   => $m[3],
            ];
        }
        if (preg_match('#^(.*?)(\.(?:webp|jpe?g|png|gif))(?:\?.*)?$#i', $src, $m)) {
            return [
                'base'  => $m[1],
                'width' => null,
                'ext'   => $m[2],
            ];
        }
        return null;
    }

    /**
     * Widths that processImage would have written for a source of maxWidth px.
     * Downscales for each configured breakpoint strictly below maxWidth, plus maxWidth itself.
     *
     * @return list<int>
     */
    public static function availableWidths(int $maxWidth): array
    {
        if ($maxWidth <= 0) {
            return [];
        }
        $out = [];
        foreach (self::WIDTHS as $w) {
            if ($w < $maxWidth) {
                $out[] = $w;
            }
        }
        // Always include the actual stored max (may be 360, 764, etc.)
        if (!in_array($maxWidth, $out, true)) {
            $out[] = $maxWidth;
        }
        sort($out, SORT_NUMERIC);
        return $out;
    }

    /**
     * Build src / srcset / sizes from a media path or full URL.
     * Never invents -1200 (or any other size) that was not produced at upload time.
     *
     * @return array{src:string,srcset:string,sizes:string}
     */
    public static function responsiveAttrs(string $src, ?string $sizes = null): array
    {
        $src = trim($src);
        $sizes = $sizes ?? '(max-width: 360px) 320px, (max-width: 640px) 480px, (max-width: 1024px) 768px, 1200px';
        if ($src === '') {
            return ['src' => '', 'srcset' => '', 'sizes' => $sizes];
        }

        $parsed = self::parseSrc($src);
        if ($parsed === null) {
            return ['src' => $src, 'srcset' => '', 'sizes' => $sizes];
        }

        $base = $parsed['base'];
        $ext = $parsed['ext'];
        $fileW = $parsed['width'];

        // No size suffix → leave as a single src (cannot invent variants safely)
        if ($fileW === null || $fileW <= 0) {
            return ['src' => $src, 'srcset' => '', 'sizes' => $sizes];
        }

        $widths = self::availableWidths($fileW);
        $parts = [];
        foreach ($widths as $w) {
            $parts[] = $base . '-' . $w . $ext . ' ' . $w . 'w';
        }
        $srcset = implode(', ', $parts);

        // Prefer mid-size default src among what actually exists
        $preferred = null;
        foreach ([480, 320, 768] as $try) {
            if (in_array($try, $widths, true)) {
                $preferred = $base . '-' . $try . $ext;
                break;
            }
        }
        if ($preferred === null) {
            // Only natural size exists (tiny upload)
            $preferred = $base . '-' . $fileW . $ext;
        }

        // If caller passed a specific existing size, keep it as src
        if (in_array($fileW, $widths, true) && $fileW >= 480) {
            // large explicit request is fine as src; browser still uses srcset
            $preferred = $base . '-' . $fileW . $ext;
        }

        // Tighten sizes when max is well below 1200 so browser doesn't over-request
        if ($fileW < 1200) {
            $sizes = self::sizesForMax($fileW);
        }

        return ['src' => $preferred, 'srcset' => $srcset, 'sizes' => $sizes];
    }

    /**
     * sizes attribute capped at the real max width of the asset.
     */
    public static function sizesForMax(int $maxWidth): string
    {
        if ($maxWidth <= 320) {
            return $maxWidth . 'px';
        }
        if ($maxWidth <= 480) {
            return '(max-width: 360px) 320px, ' . $maxWidth . 'px';
        }
        if ($maxWidth <= 768) {
            return '(max-width: 360px) 320px, (max-width: 640px) 480px, ' . $maxWidth . 'px';
        }
        return '(max-width: 360px) 320px, (max-width: 640px) 480px, (max-width: 1024px) 768px, ' . $maxWidth . 'px';
    }

    /**
     * Render a progressive, responsive <img>.
     *
     * Options in $attrs:
     *   - priority / fetchpriority: 'high' for LCP (featured) images — disables lazy
     *   - loading: override (eager|lazy)
     *   - sizes: custom sizes attribute
     *   - class: extra classes (mova-img is always added)
     */
    public static function html(string $src, string $alt = '', array $attrs = []): string
    {
        $customSizes = isset($attrs['sizes']) ? (string) $attrs['sizes'] : null;
        unset($attrs['sizes']);

        $r = self::responsiveAttrs($src, $customSizes);
        if ($r['src'] === '') {
            return '';
        }

        $isPriority = false;
        if (isset($attrs['priority'])) {
            $isPriority = (bool) $attrs['priority'] || $attrs['priority'] === 'high';
            unset($attrs['priority']);
        }
        if (isset($attrs['fetchpriority']) && $attrs['fetchpriority'] === 'high') {
            $isPriority = true;
        }

        $defaults = [
            'loading' => $isPriority ? 'eager' : 'lazy',
            'decoding' => 'async',
            'alt' => $alt,
            'src' => $r['src'],
            'class' => 'mova-img',
        ];
        if ($isPriority) {
            $defaults['fetchpriority'] = 'high';
        }
        if ($r['srcset'] !== '') {
            $defaults['srcset'] = $r['srcset'];
            $defaults['sizes'] = $r['sizes'];
        }

        if (!empty($attrs['class'])) {
            $defaults['class'] = trim($defaults['class'] . ' ' . $attrs['class']);
            unset($attrs['class']);
        }

        $merged = array_merge($defaults, $attrs);

        $merged['onload'] = "this.classList.add('is-loaded')" . (isset($merged['onload']) ? ';' . $merged['onload'] : '');

        $attrStr = '';
        foreach ($merged as $k => $v) {
            if ($v === null || $v === false) {
                continue;
            }
            $attrStr .= ' ' . htmlspecialchars((string) $k) . '="' . htmlspecialchars((string) $v) . '"';
        }
        return '<img' . $attrStr . '>';
    }

    /**
     * Smallest safe thumb URL: prefer -320 if the asset is at least that wide,
     * otherwise the real max width from the path (never invent a missing file).
     */
    public static function thumbSrc(string $src): string
    {
        $src = trim($src);
        if ($src === '') {
            return '';
        }
        $parsed = self::parseSrc($src);
        if ($parsed === null || $parsed['width'] === null) {
            return $src;
        }
        $maxW = $parsed['width'];
        $widths = self::availableWidths($maxW);
        $thumbW = $widths[0] ?? $maxW;
        return $parsed['base'] . '-' . $thumbW . $parsed['ext'];
    }

    /**
     * Upgrade plain media <img> tags in HTML body to responsive srcset + progressive class.
     * Only references widths that exist for that asset (no phantom -1200).
     */
    public static function upgradeBody(string $html): string
    {
        if ($html === '' || stripos($html, '<img') === false) {
            return $html;
        }
        return preg_replace_callback(
            '/<img\b([^>]*?)>/i',
            static function (array $m) {
                $attrs = $m[1];
                if (preg_match('/\bsrcset\s*=/i', $attrs)) {
                    return $m[0];
                }
                if (!preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $attrs, $sm)) {
                    return $m[0];
                }
                $src = $sm[1];
                $parsed = self::parseSrc($src);
                if ($parsed === null || $parsed['width'] === null) {
                    return $m[0];
                }
                $r = self::responsiveAttrs($src);
                if ($r['srcset'] === '') {
                    return $m[0];
                }
                $attrs = preg_replace('/\s*(?:src|srcset|sizes|loading|decoding)\s*=\s*["\'][^"\']*["\']/i', '', $attrs);
                if (preg_match('/\bclass\s*=\s*["\']([^"\']*)["\']/i', $attrs, $cm)) {
                    if (strpos($cm[1], 'mova-img') === false) {
                        $attrs = preg_replace(
                            '/\bclass\s*=\s*["\']([^"\']*)["\']/i',
                            'class="' . trim($cm[1] . ' mova-img') . '"',
                            $attrs,
                            1
                        );
                    }
                } else {
                    $attrs .= ' class="mova-img"';
                }
                if (!preg_match('/\bonload\s*=/i', $attrs)) {
                    $attrs .= ' onload="this.classList.add(\'is-loaded\')"';
                }
                $attrs .= ' src="' . htmlspecialchars($r['src']) . '"';
                $attrs .= ' srcset="' . htmlspecialchars($r['srcset']) . '"';
                $attrs .= ' sizes="' . htmlspecialchars($r['sizes']) . '"';
                $attrs .= ' loading="lazy" decoding="async"';
                return '<img' . $attrs . '>';
            },
            $html
        ) ?? $html;
    }
}
