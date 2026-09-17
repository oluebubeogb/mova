<?php
/**
 * Responsive <img> helpers for Mova media (320 / 480 / 768 / 1200 WebP variants).
 * Progressive reveal: images start invisible and fade in when loaded so visitors
 * never see a broken icon or blank box on first paint / weak networks.
 */
namespace Mova\Media;

class ImageTag
{
    /** Breakpoints used for srcset (must match MediaService variants). */
    public const WIDTHS = [320, 480, 768, 1200];

    /**
     * Build src / srcset / sizes from a media path or full URL.
     *
     * @return array{src:string,srcset:string,sizes:string}
     */
    public static function responsiveAttrs(string $src, ?string $sizes = null): array
    {
        $src = trim($src);
        // Default sizes: mobile-first, avoid downloading 1200px for a 320px slot
        $sizes = $sizes ?? '(max-width: 360px) 320px, (max-width: 640px) 480px, (max-width: 1024px) 768px, 1200px';
        if ($src === '') {
            return ['src' => '', 'srcset' => '', 'sizes' => $sizes];
        }

        $srcset = '';
        // Match …-320.webp | …-480.webp | plain .webp/.jpg etc.
        if (preg_match('#^(.*?)(?:-(320|480|768|1200))?(\.(?:webp|jpe?g|png|gif))(?:\?.*)?$#i', $src, $m)) {
            $base = $m[1];
            $ext = $m[3];
            $parts = [];
            foreach (self::WIDTHS as $w) {
                $parts[] = $base . '-' . $w . $ext . ' ' . $w . 'w';
            }
            $srcset = implode(', ', $parts);
            // Prefer 480 as default src (good LCP balance; browser still picks from srcset)
            $preferred = $base . '-480' . $ext;
            $src = $preferred;
            if (!empty($m[2])) {
                // Keep explicitly requested size as src when caller passed e.g. -768
                $src = $base . '-' . $m[2] . $ext;
            }
        }

        return ['src' => $src, 'srcset' => $srcset, 'sizes' => $sizes];
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

        // Merge class names
        if (!empty($attrs['class'])) {
            $defaults['class'] = trim($defaults['class'] . ' ' . $attrs['class']);
            unset($attrs['class']);
        }

        $merged = array_merge($defaults, $attrs);

        // Progressive reveal: fade in when the image finishes loading (inline for reliability)
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
     * Smallest available variant path/URL for HQ thumbnails (avoids loading 1200px in grids).
     */
    public static function thumbSrc(string $src): string
    {
        $src = trim($src);
        if ($src === '') {
            return '';
        }
        if (preg_match('#^(.*?)(?:-(320|480|768|1200))?(\.(?:webp|jpe?g|png|gif))(?:\?.*)?$#i', $src, $m)) {
            return $m[1] . '-320' . $m[3];
        }
        return $src;
    }

    /**
     * Upgrade plain media <img> tags in HTML body to responsive srcset + progressive class.
     * Safe for already-responsive markup (skips if srcset present).
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
                    return $m[0]; // already responsive
                }
                if (!preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $attrs, $sm)) {
                    return $m[0];
                }
                $src = $sm[1];
                if (!preg_match('#(?:-(?:320|480|768|1200))?\.(?:webp|jpe?g|png|gif)$#i', $src)) {
                    return $m[0]; // not a sized media file
                }
                $r = self::responsiveAttrs($src);
                if ($r['srcset'] === '') {
                    return $m[0];
                }
                // Strip old src/loading/decoding/class that we will re-add
                $attrs = preg_replace('/\s*(?:src|srcset|sizes|loading|decoding)\s*=\s*["\'][^"\']*["\']/i', '', $attrs);
                // Ensure mova-img class
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

