<?php
/**
 * Mova section library renderer — single markup source for:
 *  - HQ template library (resources/templates/sections/*)
 *  - setup-wizard kits (kit.json section specs)
 *  - AI template prompts
 *
 * Conventions:
 *  - <section class="mx mx-{type} ..."> full-bleed; <div class="mx-in"> ≈1200px inner.
 *  - Colours, radii and fonts come from theme CSS variables (see /assets/css/sections.css).
 *  - [data-reveal] elements animate in on scroll (sections.js); static if reduced motion / no JS.
 *  - Art is inline SVG (SectionArt) — swap any <figure class="mx-media"> for an <img>.
 *
 * Text values are escaped; {{tokens}} pass through untouched so kits can replace them later.
 */

namespace Mova\Theme;

final class Sections
{
    public const TYPES = ['hero', 'stats', 'features', 'split', 'testimonials', 'team', 'faq', 'gallery', 'timeline', 'logos', 'cta', 'contact', 'news', 'newsletter', 'pricing', 'prose', 'pagehead'];

    /** Render a list of specs wrapped in a page container. */
    public static function page(array $specs, string $style = 'modern'): string
    {
        $style = preg_replace('/[^a-z0-9\-]/', '', strtolower($style)) ?: 'modern';
        $out = '<div class="mx-page mx-style--' . $style . '">' . "\n";
        foreach ($specs as $spec) {
            if (is_array($spec)) {
                $out .= self::render($spec) . "\n";
            }
        }
        return $out . '</div>';
    }

    public static function render(array $s): string
    {
        $type = strtolower((string) ($s['type'] ?? ''));
        if (!in_array($type, self::TYPES, true)) {
            return '';
        }
        return self::$type($s);
    }

    // ───────────────────────── helpers ─────────────────────────

    private static function e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    /** Escape text but allow a tiny inline subset (<strong>, <em>, <br>) for richer copy. */
    private static function t($v): string
    {
        $s = self::e($v);
        return preg_replace(['~&lt;(/?)(strong|em|b|i)&gt;~', '~&lt;br\s*/?&gt;~'], ['<$1$2>', '<br>'], $s) ?? $s;
    }

    private static function cls(array $s, string $type, string $variant, string $tone = 'plain'): string
    {
        $tone = (string) ($s['tone'] ?? $tone);
        $c = 'mx mx-' . $type . ' mx-' . $type . '--' . $variant . ' mx-tone-' . preg_replace('/[^a-z]/', '', $tone);
        if (!empty($s['class'])) {
            $c .= ' ' . preg_replace('/[^a-zA-Z0-9_\- ]/', '', (string) $s['class']);
        }
        return $c;
    }

    private static function open(array $s, string $type, string $variant, string $tone = 'plain'): string
    {
        $id = !empty($s['anchor']) ? ' id="' . self::e($s['anchor']) . '"' : '';
        return '<section class="' . self::cls($s, $type, $variant, $tone) . '" data-mx="' . $type . '"' . $id . '>';
    }

    private static function btn(?array $b, string $kind = 'primary', bool $arrow = false): string
    {
        if (!$b || trim((string) ($b['label'] ?? '')) === '') {
            return '';
        }
        $href = (string) ($b['href'] ?? '#');
        return '<a class="mx-btn mx-btn--' . $kind . '" href="' . self::e($href) . '">'
            . '<span>' . self::e($b['label']) . '</span>'
            . ($arrow ? SectionIcons::svg('arrow', 'mx-btn__ico') : '')
            . '</a>';
    }

    private static function actions(array $s, bool $center = false): string
    {
        $p = self::btn($s['primary'] ?? null, 'primary', true);
        $q = self::btn($s['secondary'] ?? null, (string) ($s['secondary_kind'] ?? 'ghost'));
        if ($p === '' && $q === '') {
            return '';
        }
        return '<div class="mx-actions' . ($center ? ' mx-actions--center' : '') . '">' . $p . $q . '</div>';
    }

    private static function head(array $s, string $align = 'center', string $tag = 'h2'): string
    {
        $eyebrow = trim((string) ($s['eyebrow'] ?? ''));
        $title = trim((string) ($s['title'] ?? ''));
        $lead = trim((string) ($s['lead'] ?? ''));
        if ($eyebrow === '' && $title === '' && $lead === '') {
            return '';
        }
        $h = '<header class="mx-head mx-head--' . $align . '" data-reveal>';
        if ($eyebrow !== '') {
            $h .= '<span class="mx-eyebrow">' . self::e($eyebrow) . '</span>';
        }
        if ($title !== '') {
            $h .= '<' . $tag . ' class="mx-title">' . self::t($title) . '</' . $tag . '>';
        }
        if ($lead !== '') {
            $h .= '<p class="mx-lead">' . self::t($lead) . '</p>';
        }
        return $h . '</header>';
    }

    private static function media(array $s, string $ratio = '4:3', string $extra = ''): string
    {
        $img = trim((string) ($s['image'] ?? ''));
        $alt = (string) ($s['image_alt'] ?? ($s['title'] ?? ''));
        $inner = $img !== ''
            ? '<img src="' . self::e($img) . '" alt="' . self::e($alt) . '" loading="lazy" decoding="async">'
            : SectionArt::svg((string) ($s['art'] ?? 'abstract'), (int) ($s['art_variant'] ?? 0), $ratio);
        $r = str_replace(':', ' / ', $ratio);
        return '<figure class="mx-media' . ($extra ? ' ' . $extra : '') . '" style="--mx-ratio:' . $r . '">' . $inner . '</figure>';
    }

    private static function initials(string $name): string
    {
        $name = trim(preg_replace('/\{\{[^}]+\}\}/', '', $name) ?? $name);
        $parts = preg_split('/\s+/', $name) ?: [];
        $ini = '';
        foreach ($parts as $p) {
            if ($p === '' || in_array(strtolower(rtrim($p, '.')), ['dr', 'mr', 'mrs', 'ms', 'prof', 'rev'], true)) {
                continue;
            }
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
            if (mb_strlen($ini) >= 2) {
                break;
            }
        }
        return $ini !== '' ? $ini : '•';
    }

    private static function avatar(string $name, int $i, ?string $img = null): string
    {
        if ($img) {
            return '<span class="mx-avatar mx-avatar--img"><img src="' . self::e($img) . '" alt="" loading="lazy"></span>';
        }
        return '<span class="mx-avatar mx-avatar--' . ($i % 4) . '" aria-hidden="true">' . self::e(self::initials($name)) . '</span>';
    }

    private static function items(array $s): array
    {
        $it = $s['items'] ?? [];
        return is_array($it) ? array_values(array_filter($it, 'is_array')) : [];
    }

    // ───────────────────────── sections ─────────────────────────

    private static function hero(array $s): string
    {
        $v = (string) ($s['variant'] ?? 'gradient');
        $v = in_array($v, ['gradient', 'split', 'centered'], true) ? $v : 'gradient';
        $tone = ['gradient' => 'brand', 'split' => 'soft', 'centered' => 'plain'][$v];
        $o = self::open($s, 'hero', $v, $tone);
        $o .= '<div class="mx-hero__bg" aria-hidden="true"><span class="mx-blob mx-blob--a"></span><span class="mx-blob mx-blob--b"></span><span class="mx-hero__grid"></span></div>';
        $copy = '<div class="mx-hero__copy" data-reveal>';
        if (!empty($s['eyebrow'])) {
            $copy .= '<span class="mx-pill"><span class="mx-pill__dot"></span>' . self::e($s['eyebrow']) . '</span>';
        }
        $copy .= '<h1 class="mx-display">' . self::t($s['title'] ?? '') . '</h1>';
        if (!empty($s['lead'])) {
            $copy .= '<p class="mx-hero__lead">' . self::t($s['lead']) . '</p>';
        }
        $copy .= self::actions($s, $v === 'centered');
        $trust = array_filter((array) ($s['points'] ?? []), 'is_string');
        if ($trust) {
            $copy .= '<ul class="mx-hero__points">';
            foreach ($trust as $p) {
                $copy .= '<li>' . SectionIcons::svg('check', 'mx-ico mx-ico--sm') . '<span>' . self::e($p) . '</span></li>';
            }
            $copy .= '</ul>';
        }
        $copy .= '</div>';

        $o .= '<div class="mx-in mx-hero__in">';
        if ($v === 'split') {
            $o .= $copy;
            $o .= '<div class="mx-hero__visual" data-reveal="right">' . self::media($s, '4:3', 'mx-media--hero');
            if (!empty($s['badge'])) {
                $b = (array) $s['badge'];
                $o .= '<div class="mx-float-card"><span class="mx-float-card__ico">' . SectionIcons::svg((string) ($b['icon'] ?? 'star')) . '</span><span><strong>' . self::e($b['title'] ?? '') . '</strong><small>' . self::e($b['text'] ?? '') . '</small></span></div>';
            }
            $o .= '</div>';
        } elseif ($v === 'centered') {
            $o .= $copy;
            $o .= '<div class="mx-hero__wide" data-reveal="zoom">' . self::media($s, '21:9', 'mx-media--wide') . '</div>';
        } else {
            $o .= $copy;
            $stats = self::items(['items' => $s['stats'] ?? []]);
            if ($stats) {
                $o .= '<dl class="mx-hero__stats" data-reveal>';
                foreach ($stats as $st) {
                    $o .= '<div><dt>' . self::e($st['label'] ?? '') . '</dt><dd>' . self::e($st['value'] ?? '') . '</dd></div>';
                }
                $o .= '</dl>';
            }
        }
        $o .= '</div></section>';
        return $o;
    }

    private static function stats(array $s): string
    {
        $v = (string) ($s['variant'] ?? 'band');
        $v = in_array($v, ['band', 'plain', 'cards'], true) ? $v : 'band';
        $o = self::open($s, 'stats', $v, $v === 'band' ? 'dark' : 'plain');
        $o .= '<div class="mx-in">' . self::head($s);
        $o .= '<div class="mx-stats__grid">';
        foreach (self::items($s) as $i => $it) {
            $val = (string) ($it['value'] ?? '');
            $num = preg_replace('/[^0-9.]/', '', $val);
            $attr = $num !== '' && is_numeric($num)
                ? ' data-count="' . self::e($num) . '" data-prefix="' . self::e(preg_replace('/[0-9.,].*$/', '', $val)) . '" data-suffix="' . self::e(preg_replace('/^[^0-9]*[0-9.,]+/', '', $val)) . '"'
                : '';
            $o .= '<div class="mx-stat" data-reveal style="--mx-d:' . ($i * 80) . 'ms">'
                . '<span class="mx-stat__num"' . $attr . '>' . self::e($val) . '</span>'
                . '<span class="mx-stat__label">' . self::e($it['label'] ?? '') . '</span>'
                . (!empty($it['text']) ? '<span class="mx-stat__text">' . self::e($it['text']) . '</span>' : '')
                . '</div>';
        }
        return $o . '</div></div></section>';
    }

    private static function features(array $s): string
    {
        $v = (string) ($s['variant'] ?? 'cards');
        $v = in_array($v, ['cards', 'minimal', 'bento'], true) ? $v : 'cards';
        $o = self::open($s, 'features', $v);
        $cols = max(2, min(4, (int) ($s['columns'] ?? 3)));
        $o .= '<div class="mx-in">' . self::head($s);
        $o .= '<div class="mx-grid mx-grid--' . $cols . '">';
        foreach (self::items($s) as $i => $it) {
            $href = trim((string) ($it['href'] ?? ''));
            $tag = $href !== '' ? 'a' : 'div';
            $o .= '<' . $tag . ($href !== '' ? ' href="' . self::e($href) . '"' : '') . ' class="mx-card mx-feature' . ($v === 'bento' && $i === 0 ? ' mx-feature--wide' : '') . '" data-reveal style="--mx-d:' . (($i % $cols) * 70) . 'ms">'
                . '<span class="mx-feature__ico mx-ico-chip--' . ($i % 3) . '">' . SectionIcons::svg((string) ($it['icon'] ?? 'sparkles')) . '</span>'
                . '<h3 class="mx-feature__title">' . self::t($it['title'] ?? '') . '</h3>'
                . '<p class="mx-feature__text">' . self::t($it['text'] ?? '') . '</p>'
                . ($href !== '' ? '<span class="mx-link">' . self::e($it['link_label'] ?? 'Learn more') . SectionIcons::svg('arrow', 'mx-ico mx-ico--sm') . '</span>' : '')
                . '</' . $tag . '>';
        }
        return $o . '</div></div></section>';
    }

    private static function split(array $s): string
    {
        $v = !empty($s['reverse']) ? 'reverse' : 'default';
        $o = self::open($s, 'split', $v);
        $o .= '<div class="mx-in mx-split__in">';
        $o .= '<div class="mx-split__copy" data-reveal>';
        if (!empty($s['eyebrow'])) {
            $o .= '<span class="mx-eyebrow">' . self::e($s['eyebrow']) . '</span>';
        }
        $o .= '<h2 class="mx-title">' . self::t($s['title'] ?? '') . '</h2>';
        foreach ((array) ($s['paragraphs'] ?? []) as $p) {
            if (is_string($p) && trim($p) !== '') {
                $o .= '<p class="mx-split__p">' . self::t($p) . '</p>';
            }
        }
        $pts = array_filter((array) ($s['points'] ?? []), 'is_string');
        if ($pts) {
            $o .= '<ul class="mx-checks">';
            foreach ($pts as $p) {
                $o .= '<li>' . SectionIcons::svg('check', 'mx-ico mx-ico--sm') . '<span>' . self::t($p) . '</span></li>';
            }
            $o .= '</ul>';
        }
        $o .= self::actions($s) . '</div>';
        $o .= '<div class="mx-split__visual" data-reveal="' . ($v === 'reverse' ? 'left' : 'right') . '">' . self::media($s, (string) ($s['ratio'] ?? '4:3')) . '</div>';
        return $o . '</div></section>';
    }

    private static function testimonials(array $s): string
    {
        $v = (string) ($s['variant'] ?? 'grid');
        $v = in_array($v, ['grid', 'spotlight'], true) ? $v : 'grid';
        $o = self::open($s, 'testimonials', $v, $v === 'spotlight' ? 'soft' : 'plain');
        $o .= '<div class="mx-in">' . self::head($s);
        $items = self::items($s);
        if ($v === 'spotlight' && $items) {
            $it = $items[0];
            $o .= '<figure class="mx-spotlight" data-reveal>' . SectionIcons::svg('quote', 'mx-quote__mark')
                . '<blockquote>' . self::t($it['quote'] ?? '') . '</blockquote>'
                . '<figcaption>' . self::avatar((string) ($it['name'] ?? ''), 0, $it['avatar'] ?? null)
                . '<span><strong>' . self::e($it['name'] ?? '') . '</strong><small>' . self::e($it['role'] ?? '') . '</small></span></figcaption></figure>';
            return $o . '</div></section>';
        }
        $o .= '<div class="mx-grid mx-grid--3 mx-quotes">';
        foreach ($items as $i => $it) {
            $o .= '<figure class="mx-card mx-quote" data-reveal style="--mx-d:' . (($i % 3) * 80) . 'ms">'
                . '<div class="mx-stars" aria-label="5 out of 5">' . str_repeat(SectionIcons::svg('star', 'mx-star'), 5) . '</div>'
                . '<blockquote>' . self::t($it['quote'] ?? '') . '</blockquote>'
                . '<figcaption>' . self::avatar((string) ($it['name'] ?? ''), $i, $it['avatar'] ?? null)
                . '<span><strong>' . self::e($it['name'] ?? '') . '</strong><small>' . self::e($it['role'] ?? '') . '</small></span></figcaption></figure>';
        }
        return $o . '</div></div></section>';
    }

    private static function team(array $s): string
    {
        $o = self::open($s, 'team', 'grid');
        $o .= '<div class="mx-in">' . self::head($s);
        $cols = max(2, min(4, (int) ($s['columns'] ?? 4)));
        $o .= '<div class="mx-grid mx-grid--' . $cols . '">';
        foreach (self::items($s) as $i => $it) {
            $o .= '<article class="mx-card mx-person" data-reveal style="--mx-d:' . (($i % $cols) * 70) . 'ms">'
                . '<div class="mx-person__photo mx-person__photo--' . ($i % 4) . '">'
                . (!empty($it['photo']) ? '<img src="' . self::e($it['photo']) . '" alt="' . self::e($it['name'] ?? '') . '" loading="lazy">' : '<span>' . self::e(self::initials((string) ($it['name'] ?? ''))) . '</span>')
                . '</div>'
                . '<h3 class="mx-person__name">' . self::e($it['name'] ?? '') . '</h3>'
                . '<p class="mx-person__role">' . self::e($it['role'] ?? '') . '</p>'
                . (!empty($it['bio']) ? '<p class="mx-person__bio">' . self::t($it['bio']) . '</p>' : '')
                . '</article>';
        }
        return $o . '</div></div></section>';
    }

    private static function faq(array $s): string
    {
        $o = self::open($s, 'faq', 'accordion');
        $o .= '<div class="mx-in mx-faq__in"><div class="mx-faq__side">' . self::head($s, 'left');
        if (!empty($s['primary'])) {
            $o .= '<div data-reveal>' . self::btn((array) $s['primary'], 'ghost', true) . '</div>';
        }
        $o .= '</div><div class="mx-faq__list">';
        foreach (self::items($s) as $i => $it) {
            $o .= '<details class="mx-faq__item" data-reveal' . ($i === 0 && !empty($s['open_first']) ? ' open' : '') . '>'
                . '<summary><span>' . self::e($it['q'] ?? '') . '</span><span class="mx-faq__icon" aria-hidden="true"></span></summary>'
                . '<div class="mx-faq__a"><p>' . self::t($it['a'] ?? '') . '</p></div></details>';
        }
        return $o . '</div></div></section>';
    }

    private static function gallery(array $s): string
    {
        $o = self::open($s, 'gallery', 'masonry');
        $o .= '<div class="mx-in">' . self::head($s);
        $o .= '<div class="mx-masonry">';
        $ratios = ['4:5', '4:3', '1:1', '3:4', '16:9', '4:3', '3:4', '1:1'];
        $arts = ['campus', 'people', 'study', 'stage', 'sport', 'nature', 'community', 'event'];
        foreach (self::items($s) as $i => $it) {
            $ratio = (string) ($it['ratio'] ?? $ratios[$i % 8]);
            $spec = ['image' => $it['image'] ?? '', 'art' => $it['art'] ?? $arts[$i % 8], 'art_variant' => $i, 'title' => $it['caption'] ?? ''];
            $fig = self::media($spec, $ratio, 'mx-masonry__item');
            if (!empty($it['caption'])) {
                $fig = str_replace('</figure>', '<figcaption>' . self::e($it['caption']) . '</figcaption></figure>', $fig);
            }
            $o .= '<div class="mx-masonry__cell" data-reveal style="--mx-d:' . (($i % 3) * 60) . 'ms">' . $fig . '</div>';
        }
        return $o . '</div></div></section>';
    }

    private static function timeline(array $s): string
    {
        $v = (string) ($s['variant'] ?? 'steps');
        $v = in_array($v, ['steps', 'timeline'], true) ? $v : 'steps';
        $o = self::open($s, 'timeline', $v, $v === 'steps' ? 'soft' : 'plain');
        $o .= '<div class="mx-in">' . self::head($s);
        if ($v === 'steps') {
            $o .= '<ol class="mx-steps">';
            foreach (self::items($s) as $i => $it) {
                $o .= '<li class="mx-step" data-reveal style="--mx-d:' . ($i * 90) . 'ms"><span class="mx-step__n">' . sprintf('%02d', $i + 1) . '</span>'
                    . '<h3>' . self::t($it['title'] ?? '') . '</h3><p>' . self::t($it['text'] ?? '') . '</p></li>';
            }
            $o .= '</ol>';
        } else {
            $o .= '<ol class="mx-tl">';
            foreach (self::items($s) as $i => $it) {
                $o .= '<li class="mx-tl__item" data-reveal><span class="mx-tl__dot" aria-hidden="true"></span>'
                    . '<span class="mx-tl__meta">' . self::e($it['meta'] ?? '') . '</span>'
                    . '<div class="mx-card mx-tl__card"><h3>' . self::t($it['title'] ?? '') . '</h3><p>' . self::t($it['text'] ?? '') . '</p></div></li>';
            }
            $o .= '</ol>';
        }
        return $o . '</div></section>';
    }

    private static function logos(array $s): string
    {
        $o = self::open($s, 'logos', 'strip');
        $o .= '<div class="mx-in">';
        if (!empty($s['title'])) {
            $o .= '<p class="mx-logos__title" data-reveal>' . self::e($s['title']) . '</p>';
        }
        $items = self::items($s);
        $row = '';
        $icons = ['layers', 'globe', 'leaf', 'compass', 'zap', 'shield', 'star', 'target'];
        foreach ($items as $i => $it) {
            $row .= '<span class="mx-logo">' . SectionIcons::svg((string) ($it['icon'] ?? $icons[$i % 8]), 'mx-ico mx-logo__mark') . '<span>' . self::e($it['name'] ?? '') . '</span></span>';
        }
        $o .= '<div class="mx-logos__viewport" data-reveal><div class="mx-logos__track">' . $row
            . '<span class="mx-logos__dup" aria-hidden="true">' . $row . '</span></div></div>';
        return $o . '</div></section>';
    }

    private static function cta(array $s): string
    {
        $v = (string) ($s['variant'] ?? 'band');
        $v = in_array($v, ['band', 'card'], true) ? $v : 'band';
        $o = self::open($s, 'cta', $v, $v === 'band' ? 'brand' : 'plain');
        $o .= '<div class="mx-in"><div class="mx-cta__box" data-reveal="zoom">'
            . '<div class="mx-cta__art" aria-hidden="true"><span></span><span></span></div>'
            . '<div class="mx-cta__copy"><h2 class="mx-title">' . self::t($s['title'] ?? '') . '</h2>'
            . (!empty($s['lead']) ? '<p class="mx-lead">' . self::t($s['lead']) . '</p>' : '') . '</div>'
            . self::actions($s) . '</div></div></section>';
        return $o;
    }

    private static function contact(array $s): string
    {
        $o = self::open($s, 'contact', 'map');
        $o .= '<div class="mx-in">' . self::head($s);
        $o .= '<div class="mx-contact__grid"><ul class="mx-contact__list">';
        foreach (self::items($s) as $i => $it) {
            $href = trim((string) ($it['href'] ?? ''));
            $val = self::t($it['value'] ?? '');
            $o .= '<li class="mx-card mx-contact__item" data-reveal style="--mx-d:' . ($i * 70) . 'ms"><span class="mx-feature__ico mx-ico-chip--' . ($i % 3) . '">' . SectionIcons::svg((string) ($it['icon'] ?? 'mail')) . '</span>'
                . '<span><small>' . self::e($it['label'] ?? '') . '</small>'
                . ($href !== '' ? '<a href="' . self::e($href) . '">' . $val . '</a>' : '<strong>' . $val . '</strong>')
                . '</span></li>';
        }
        $o .= '</ul>';
        $o .= '<div class="mx-map" data-reveal="right" data-map-embed>' . SectionArt::svg('map', 0, '4:3');
        if (!empty($s['address'])) {
            $o .= '<div class="mx-map__chip">' . SectionIcons::svg('pin', 'mx-ico mx-ico--sm') . '<span>' . self::t($s['address']) . '</span></div>';
        }
        $o .= '</div></div>';
        return $o . '</div></section>';
    }

    private static function news(array $s): string
    {
        $v = (string) ($s['variant'] ?? 'cards');
        $v = in_array($v, ['cards', 'events'], true) ? $v : 'cards';
        $o = self::open($s, 'news', $v);
        $o .= '<div class="mx-in"><div class="mx-news__head">' . self::head($s, 'left');
        if (!empty($s['more'])) {
            $o .= self::btn((array) $s['more'], 'ghost', true);
        }
        $o .= '</div>';
        if ($v === 'events') {
            $o .= '<ul class="mx-events">';
            foreach (self::items($s) as $i => $it) {
                $o .= '<li class="mx-card mx-event" data-reveal style="--mx-d:' . ($i * 70) . 'ms">'
                    . '<span class="mx-event__date"><b>' . self::e($it['day'] ?? '') . '</b><small>' . self::e($it['month'] ?? '') . '</small></span>'
                    . '<span class="mx-event__body"><h3>' . self::t($it['title'] ?? '') . '</h3><span class="mx-event__meta">' . SectionIcons::svg('clock', 'mx-ico mx-ico--sm') . self::e($it['time'] ?? '') . (!empty($it['place']) ? ' · ' . self::e($it['place']) : '') . '</span></span>'
                    . (!empty($it['href']) ? '<a class="mx-event__go" href="' . self::e($it['href']) . '" aria-label="Details">' . SectionIcons::svg('arrow', 'mx-ico') . '</a>' : '')
                    . '</li>';
            }
            return $o . '</ul></div></section>';
        }
        $o .= '<div class="mx-grid mx-grid--3">';
        $arts = ['event', 'study', 'community', 'campus', 'stage', 'sport'];
        foreach (self::items($s) as $i => $it) {
            $href = (string) ($it['href'] ?? '#');
            $o .= '<article class="mx-card mx-post" data-reveal style="--mx-d:' . (($i % 3) * 80) . 'ms">'
                . '<a class="mx-post__media" href="' . self::e($href) . '" tabindex="-1" aria-hidden="true">' . self::media(['image' => $it['image'] ?? '', 'art' => $it['art'] ?? $arts[$i % 6], 'art_variant' => $i], '16:9')
                . (!empty($it['tag']) ? '<span class="mx-chip">' . self::e($it['tag']) . '</span>' : '') . '</a>'
                . '<div class="mx-post__body"><time class="mx-post__date">' . self::e($it['date'] ?? '') . '</time>'
                . '<h3 class="mx-post__title"><a href="' . self::e($href) . '">' . self::t($it['title'] ?? '') . '</a></h3>'
                . '<p class="mx-post__text">' . self::t($it['text'] ?? '') . '</p>'
                . '<span class="mx-link">Read more' . SectionIcons::svg('arrow', 'mx-ico mx-ico--sm') . '</span></div></article>';
        }
        return $o . '</div></div></section>';
    }

    private static function newsletter(array $s): string
    {
        $o = self::open($s, 'newsletter', 'card');
        $o .= '<div class="mx-in"><div class="mx-newsletter__box" data-reveal>'
            . '<div class="mx-newsletter__copy"><span class="mx-feature__ico mx-ico-chip--1">' . SectionIcons::svg('mail') . '</span>'
            . '<div><h2 class="mx-title">' . self::t($s['title'] ?? '') . '</h2>'
            . (!empty($s['lead']) ? '<p class="mx-lead">' . self::t($s['lead']) . '</p>' : '') . '</div></div>'
            . '<form class="mx-newsletter__form" action="/subscribe" method="post" data-mx-subscribe>'
            . '<label class="mx-sr" for="mx-nl-email">Email address</label>'
            . '<input id="mx-nl-email" type="email" name="email" required placeholder="' . self::e($s['placeholder'] ?? 'you@example.com') . '" autocomplete="email">'
            . '<input type="text" name="website" tabindex="-1" autocomplete="off" class="mx-hp" aria-hidden="true">'
            . '<button class="mx-btn mx-btn--primary" type="submit"><span>' . self::e($s['button'] ?? 'Subscribe') . '</span></button>'
            . '<p class="mx-newsletter__note" data-mx-subscribe-msg>' . self::e($s['note'] ?? 'No spam. Unsubscribe any time.') . '</p>'
            . '</form></div></div></section>';
        return $o;
    }

    private static function pricing(array $s): string
    {
        $v = (string) ($s['variant'] ?? 'cards');
        $v = in_array($v, ['cards', 'compact', 'toggle'], true) ? $v : 'cards';
        $o = self::open($s, 'pricing', $v);
        $o .= '<div class="mx-in">' . self::head($s);
        $toggle = $v === 'toggle';
        if ($toggle) {
            $o .= '<div class="mx-pricing__switch" role="group" aria-label="Billing period" data-reveal>'
                . '<button type="button" class="is-on" data-mx-period="a" aria-pressed="true">' . self::e($s['period_a'] ?? 'Monthly') . '</button>'
                . '<button type="button" data-mx-period="b" aria-pressed="false">' . self::e($s['period_b'] ?? 'Yearly') . (!empty($s['save']) ? ' <em>' . self::e($s['save']) . '</em>' : '') . '</button></div>';
        }
        $plans = self::items(['items' => $s['plans'] ?? ($s['items'] ?? [])]);
        $o .= '<div class="mx-plans mx-plans--' . count($plans) . '"' . ($toggle ? ' data-mx-pricing' : '') . '>';
        foreach ($plans as $i => $p) {
            $feat = !empty($p['featured']);
            $o .= '<article class="mx-card mx-plan' . ($feat ? ' is-featured' : '') . '" data-reveal style="--mx-d:' . ($i * 90) . 'ms">';
            if ($feat) {
                $o .= '<span class="mx-plan__badge">' . self::e($p['badge'] ?? 'Most popular') . '</span>';
            }
            $o .= '<h3 class="mx-plan__name">' . self::e($p['name'] ?? '') . '</h3>';
            if (!empty($p['text'])) {
                $o .= '<p class="mx-plan__text">' . self::t($p['text']) . '</p>';
            }
            $o .= '<p class="mx-plan__price"><span class="mx-plan__amt" data-a="' . self::e($p['price'] ?? '') . '" data-b="' . self::e($p['price_b'] ?? ($p['price'] ?? '')) . '">' . self::e($p['price'] ?? '') . '</span>'
                . '<span class="mx-plan__per" data-a="' . self::e($p['per'] ?? '') . '" data-b="' . self::e($p['per_b'] ?? ($p['per'] ?? '')) . '">' . self::e($p['per'] ?? '') . '</span></p>';
            $o .= '<ul class="mx-checks">';
            foreach ((array) ($p['features'] ?? []) as $f) {
                $o .= '<li>' . SectionIcons::svg('check', 'mx-ico mx-ico--sm') . '<span>' . self::t($f) . '</span></li>';
            }
            $o .= '</ul>' . self::btn((array) ($p['cta'] ?? ['label' => 'Get started', 'href' => '#']), $feat ? 'primary' : 'ghost') . '</article>';
        }
        $o .= '</div>';
        if (!empty($s['note'])) {
            $o .= '<p class="mx-pricing__note">' . self::t($s['note']) . '</p>';
        }
        return $o . '</div></section>';
    }

    private static function prose(array $s): string
    {
        $o = self::open($s, 'prose', 'narrow');
        $o .= '<div class="mx-in mx-prose__in" data-reveal>';
        if (!empty($s['title'])) {
            $o .= '<h2 class="mx-title">' . self::t($s['title']) . '</h2>';
        }
        foreach ((array) ($s['paragraphs'] ?? []) as $p) {
            if (is_string($p) && trim($p) !== '') {
                $o .= '<p>' . self::t($p) . '</p>';
            }
        }
        if (!empty($s['html'])) {
            $o .= (string) $s['html']; // trusted (kit file / HQ)
        }
        return $o . '</div></section>';
    }

    /** Inner-page header: breadcrumb, title, lead on soft brand art. */
    private static function pagehead(array $s): string
    {
        $v = (string) ($s['variant'] ?? 'soft');
        $v = in_array($v, ['soft', 'brand'], true) ? $v : 'soft';
        $o = self::open($s, 'pagehead', $v, $v === 'brand' ? 'brand' : 'soft');
        $o .= '<div class="mx-hero__bg" aria-hidden="true"><span class="mx-blob mx-blob--a"></span><span class="mx-blob mx-blob--b"></span><span class="mx-hero__grid"></span></div>';
        $o .= '<div class="mx-in mx-pagehead__in" data-reveal>';
        $o .= '<nav class="mx-crumbs" aria-label="Breadcrumb"><a href="/">Home</a><span aria-hidden="true">/</span><span aria-current="page">' . self::e(strip_tags((string) ($s['crumb'] ?? $s['title'] ?? ''))) . '</span></nav>';
        if (!empty($s['eyebrow'])) {
            $o .= '<span class="mx-pill"><span class="mx-pill__dot"></span>' . self::e($s['eyebrow']) . '</span>';
        }
        $o .= '<h1 class="mx-display mx-display--page">' . self::t($s['title'] ?? '') . '</h1>';
        if (!empty($s['lead'])) {
            $o .= '<p class="mx-hero__lead">' . self::t($s['lead']) . '</p>';
        }
        $o .= self::actions($s);
        return $o . '</div></section>';
    }
}
