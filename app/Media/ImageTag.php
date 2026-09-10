<?php
/**
 * Responsive <img> helpers for Mova media (480 / 768 / 1200 WebP variants).
 */

namespace Mova\Media;

class ImageTag
{
    /**
     * @return array{src:string,srcset:string,sizes:string}
     */
    public static function responsiveAttrs(string $src): array
    {
        $src = trim($src);
        $sizes = '(max-width: 480px) 480px, (max-width: 768px) 768px, 1200px';
        if ($src === '') {
            return ['src' => '', 'srcset' => '', 'sizes' => $sizes];
        }

        $srcset = '';
        if (preg_match('#^(.*?)(?:-(480|768|1200))?(\.(?:webp|jpe?g|png|gif))$#i', $src, $m)) {
            $base = $m[1];
            $ext = $m[3];
            $parts = [];
            foreach ([480, 768, 1200] as $w) {
                $parts[] = $base . '-' . $w . $ext . ' ' . $w . 'w';
            }
            $srcset = implode(', ', $parts);
            // Prefer mid size as default src for LCP balance
            $preferred = $base . '-768' . $ext;
            $src = $preferred;
            if (!empty($m[2])) {
                // keep requested size if provided
                $src = $base . '-' . $m[2] . $ext;
            }
        }

        return ['src' => $src, 'srcset' => $srcset, 'sizes' => $sizes];
    }

    public static function html(string $src, string $alt = '', array $attrs = []): string
    {
        $r = self::responsiveAttrs($src);
        if ($r['src'] === '') {
            return '';
        }
        $defaults = [
            'loading' => 'lazy',
            'decoding' => 'async',
            'alt' => $alt,
            'src' => $r['src'],
        ];
        if ($r['srcset'] !== '') {
            $defaults['srcset'] = $r['srcset'];
            $defaults['sizes'] = $r['sizes'];
        }
        $merged = array_merge($defaults, $attrs);
        $attrStr = '';
        foreach ($merged as $k => $v) {
            if ($v === null || $v === false) {
                continue;
            }
            $attrStr .= ' ' . htmlspecialchars((string) $k) . '="' . htmlspecialchars((string) $v) . '"';
        }
        return '<img' . $attrStr . '>';
    }
}
