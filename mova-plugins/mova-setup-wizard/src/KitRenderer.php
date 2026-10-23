<?php
/**
 * Kit v2 renderer — kits are lists of section specs (see Mova\Theme\Sections)
 * with {{tokens}}; this turns them into finished page HTML.
 */

declare(strict_types=1);

namespace MovaSetupWizard;

use Mova\Theme\Sections;

final class KitRenderer
{
    /** Text keys the AI copywriter may rewrite (never hrefs, icons, art or variants). */
    public const COPY_KEYS = ['eyebrow', 'title', 'lead', 'text', 'label', 'quote', 'name', 'role', 'bio', 'q', 'a',
        'caption', 'paragraphs', 'points', 'note', 'button', 'tag', 'meta', 'value', 'placeholder'];

    /** Defaults used when the wizard field is blank (per pack). */
    public static function defaults(string $pack): array
    {
        $pack = KitRepository::normalizePack($pack);
        return match ($pack) {
            'school' => [
                'tagline' => 'Curious minds, kind hearts, bright futures',
                'about' => 'We are a caring, ambitious school where every child is known, challenged and supported to flourish — in the classroom and far beyond it.',
            ],
            'organization' => [
                'tagline' => 'Together we build stronger communities',
                'about' => 'We are a community-led organisation working alongside local people to create lasting, positive change — through programmes, partnerships and practical support.',
            ],
            default => [
                'tagline' => 'Thoughtful work, beautifully delivered',
                'about' => 'We are a small, experienced team helping people and organisations do their best work — with clear advice, honest pricing and genuinely great service.',
            ],
        };
    }

    /**
     * @param array<string,string> $input site_name, tagline, about, city, email, phone, address
     * @return array<string,string> token => plain text
     */
    public static function vars(array $input, string $pack): array
    {
        $d = self::defaults($pack);
        $name = trim((string) ($input['site_name'] ?? '')) ?: 'My Site';
        $v = [
            'site_name' => $name,
            'tagline' => trim((string) ($input['tagline'] ?? '')) ?: $d['tagline'],
            'about' => trim((string) ($input['about'] ?? '')) ?: $d['about'],
            'city' => trim((string) ($input['city'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'address' => trim((string) ($input['address'] ?? '')),
            'year' => date('Y'),
        ];
        $v['phone_href'] = $v['phone'] !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $v['phone']) : '';
        $v['email_href'] = $v['email'] !== '' ? 'mailto:' . $v['email'] : '';
        $v['city_or_area'] = $v['city'] !== '' ? $v['city'] : 'the local area';
        return $v;
    }

    /**
     * Replace tokens through a spec tree. Items whose required token resolved
     * empty (e.g. a phone row with no phone) are dropped.
     */
    public static function fill($node, array $vars)
    {
        if (is_string($node)) {
            return self::str($node, $vars);
        }
        if (!is_array($node)) {
            return $node;
        }
        $isList = array_is_list($node);
        $out = [];
        foreach ($node as $k => $val) {
            if ($isList && is_array($val) && !array_is_list($val) && self::dropsEmpty($val, $vars)) {
                continue;
            }
            $out[$k] = self::fill($val, $vars);
        }
        if ($isList) {
            $out = array_values(array_filter($out, static fn ($x) => !(is_string($x) && trim($x) === '')));
        }
        return $out;
    }

    private static function dropsEmpty(array $item, array $vars): bool
    {
        foreach (['value', 'href'] as $k) {
            if (isset($item[$k]) && is_string($item[$k]) && preg_match('/^\s*\{\{\s*([a-z_]+)\s*\}\}\s*$/', $item[$k], $m)) {
                if (trim((string) ($vars[$m[1]] ?? '')) === '') {
                    return true;
                }
            }
        }
        return false;
    }

    private static function str(string $s, array $vars): string
    {
        $s = (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static fn ($m) => (string) ($vars[$m[1]] ?? ''), $s);
        return trim((string) preg_replace('/\s{2,}/', ' ', $s)) === '' ? '' : $s;
    }

    /** Merge AI-rewritten copy into the original specs: only same-path strings under COPY_KEYS. */
    public static function mergeCopy(array $orig, $ai, string $key = '')
    {
        if (is_string($orig)) {
            if (!is_string($ai) || $key === '' || !in_array($key, self::COPY_KEYS, true)) {
                return $orig;
            }
            $ai = trim(strip_tags($ai, '<strong><em>'));
            if ($ai === '' || mb_strlen($ai) > max(400, mb_strlen($orig) * 4)) {
                return $orig;
            }
            return $ai;
        }
        if (!is_array($orig) || !is_array($ai)) {
            return $orig;
        }
        foreach ($orig as $k => $v) {
            if (!array_key_exists($k, $ai)) {
                continue;
            }
            $childKey = is_int($k) ? $key : (string) $k;
            if (is_string($v)) {
                if (is_string($ai[$k]) && in_array($childKey, self::COPY_KEYS, true)) {
                    $t = trim(strip_tags((string) $ai[$k], '<strong><em>'));
                    if ($t !== '' && mb_strlen($t) <= max(400, mb_strlen($v) * 4)) {
                        $orig[$k] = $t;
                    }
                }
            } elseif (is_array($v)) {
                $orig[$k] = self::mergeCopy($v, $ai[$k], $childKey);
            }
        }
        return $orig;
    }

    /** @return array{title:string,slug:string,role:string,description:string,sections:array} */
    public static function page(array $kit, string $slug): ?array
    {
        foreach ($kit['pages'] ?? [] as $p) {
            if (($p['slug'] ?? '') === $slug) {
                return $p;
            }
        }
        return null;
    }

    /** Render a kit page with vars (and optional AI copy specs). */
    public static function render(array $kit, array $page, array $vars, ?array $copySections = null): string
    {
        $specs = $copySections ?? ($page['sections'] ?? []);
        $specs = self::fill($specs, $vars);
        $specs = self::fixLinks($specs, $kit);
        return Sections::page($specs, (string) ($kit['style'] ?? 'modern'));
    }

    /**
     * Point internal links at pages this kit actually creates. A link to a page the kit does not
     * include (e.g. "/news" in a compact kit, or a path invented by AI copy) goes to Contact, else Home.
     */
    public static function fixLinks($node, array $kit)
    {
        static $ok = [];
        $key = (string) ($kit['id'] ?? spl_object_id((object) $kit));
        if (!isset($ok[$key])) {
            $set = ['' => true, 'search' => true, 'blog' => true, 'feed.xml' => true, 'sitemap.xml' => true];
            foreach ($kit['pages'] ?? [] as $p) {
                $sl = (string) ($p['slug'] ?? '');
                $set[$sl === 'home' ? '' : $sl] = true;
            }
            $ok[$key] = $set;
        }
        $set = $ok[$key];
        $fallback = isset($set['contact']) ? '/contact' : '/';
        if (!is_array($node)) {
            return $node;
        }
        foreach ($node as $k => $v) {
            if (is_array($v)) {
                $node[$k] = self::fixLinks($v, $kit);
            } elseif (($k === 'href' || $k === 'url') && is_string($v) && $v !== '' && $v[0] === '/' && (strlen($v) < 2 || $v[1] !== '/')) {
                $path = (string) parse_url($v, PHP_URL_PATH);
                $seg = explode('/', trim($path, '/'))[0] ?? '';
                if (!isset($set[$seg])) {
                    $node[$k] = $fallback;
                }
            }
        }
        return $node;
    }

    /** Navigation, footer and CTA settings for a kit, tokens filled. */
    public static function siteSettings(array $kit, array $vars): array
    {
        $nav = [];
        foreach ($kit['nav'] ?? [] as $n) {
            $nav[] = self::str((string) ($n['label'] ?? ''), $vars) . '|' . ($n['url'] ?? '/');
        }
        $cols = self::fixLinks(self::fill($kit['footer_columns'] ?? [], $vars), $kit);
        foreach ($cols as &$c) {
            $c['text'] = (string) ($c['text'] ?? '');
            $c['links'] = array_values(array_filter((array) ($c['links'] ?? []), static fn ($l) => is_array($l) && trim((string) ($l['label'] ?? '')) !== ''));
        }
        unset($c);
        $cols = array_values(array_filter($cols, static fn ($c) => $c['links'] !== [] || trim($c['text']) !== ''));
        $cta = !empty($kit['nav_cta']) ? self::str((string) $kit['nav_cta']['label'], $vars) . '|' . (string) $kit['nav_cta']['url'] : '';
        return ['nav_links' => implode("\n", $nav), 'footer_columns' => json_encode($cols, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'nav_cta' => $cta];
    }
}
