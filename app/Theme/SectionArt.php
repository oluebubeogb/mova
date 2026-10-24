<?php
/**
 * Theme-aware SVG artwork placeholders for sections (no stock photos, no CDN).
 * Every colour comes from CSS variables so art follows the brand palette and dark mode.
 * Replace any <figure class="mx-media"> contents with an <img> to use a real photo.
 */

namespace Mova\Theme;

final class SectionArt
{
    private static int $n = 0;

    public const KINDS = ['abstract', 'campus', 'people', 'nature', 'event', 'map', 'study', 'stage', 'sport', 'city', 'product', 'community'];

    public static function svg(string $kind = 'abstract', int $variant = 0, string $ratio = '4:3'): string
    {
        self::$n++;
        $id = 'mxa' . substr(md5($kind . $variant . self::$n . $ratio), 0, 6);
        [$w, $h] = self::dims($ratio);
        $kind = in_array($kind, self::KINDS, true) ? $kind : 'abstract';
        $angle = [135, 160, 115, 200, 90, 145][$variant % 6];
        $defs = '<defs>'
            . '<linearGradient id="' . $id . 'g" gradientTransform="rotate(' . ($angle - 90) . ' .5 .5)">'
            . '<stop offset="0" style="stop-color:var(--color-primary,#2563eb)"/>'
            . '<stop offset="1" style="stop-color:var(--color-brand-accent,#7c3aed)"/></linearGradient>'
            . '<linearGradient id="' . $id . 's" x1="0" y1="0" x2="0" y2="1">'
            . '<stop offset="0" style="stop-color:#fff;stop-opacity:.55"/><stop offset="1" style="stop-color:#fff;stop-opacity:0"/></linearGradient>'
            . '<radialGradient id="' . $id . 'r"><stop offset="0" style="stop-color:#fff;stop-opacity:.5"/><stop offset="1" style="stop-color:#fff;stop-opacity:0"/></radialGradient>'
            . '</defs>';
        $bg = '<rect width="' . $w . '" height="' . $h . '" fill="url(#' . $id . 'g)"/>';
        $glow = '<circle cx="' . round($w * (0.2 + 0.12 * ($variant % 4))) . '" cy="' . round($h * 0.18) . '" r="' . round($w * 0.45) . '" fill="url(#' . $id . 'r)"/>';
        $body = self::$kind($w, $h, $variant, $id);
        return '<svg class="mx-art mx-art--' . $kind . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="xMidYMid slice" role="img" aria-label="' . ucfirst($kind) . ' illustration">'
            . $defs . $bg . $glow . $body . '</svg>';
    }

    /** @return array{0:int,1:int} */
    private static function dims(string $ratio): array
    {
        return match ($ratio) {
            '1:1' => [600, 600],
            '3:4' => [600, 800],
            '16:9' => [800, 450],
            '21:9' => [1050, 450],
            '3:2' => [750, 500],
            default => [800, 600],
        };
    }

    private static function w(float $o): string
    {
        return 'style="fill:#fff;fill-opacity:' . $o . '"';
    }

    private static function abstract(int $w, int $h, int $v, string $id): string
    {
        $s = '';
        // dot grid
        for ($y = 40; $y < $h; $y += 36) {
            for ($x = 40 + ($v % 2) * 18; $x < $w * 0.42; $x += 36) {
                $s .= '<circle cx="' . $x . '" cy="' . $y . '" r="2" ' . self::w(.22) . '/>';
            }
        }
        $cx = $w * 0.56;
        $cy = $h * 0.22;
        // floating UI cards
        $s .= '<g transform="translate(' . round($cx) . ' ' . round($cy) . ') rotate(' . (-6 + $v % 3 * 4) . ')">'
            . '<rect width="' . round($w * 0.34) . '" height="' . round($h * 0.46) . '" rx="18" ' . self::w(.92) . '/>'
            . '<rect x="22" y="24" width="' . round($w * 0.12) . '" height="12" rx="6" style="fill:var(--color-primary,#2563eb);fill-opacity:.85"/>'
            . '<rect x="22" y="50" width="' . round($w * 0.26) . '" height="9" rx="4.5" style="fill:#0f172a;fill-opacity:.12"/>'
            . '<rect x="22" y="68" width="' . round($w * 0.22) . '" height="9" rx="4.5" style="fill:#0f172a;fill-opacity:.12"/>'
            . '<rect x="22" y="' . round($h * 0.46 - 70) . '" width="' . round($w * 0.30 - 22) . '" height="48" rx="12" style="fill:var(--color-brand-accent,#7c3aed);fill-opacity:.18"/>'
            . '<path d="M34 ' . round($h * 0.46 - 36) . ' l26 -12 l22 8 l30 -18 l26 6" fill="none" style="stroke:var(--color-brand-accent,#7c3aed)" stroke-width="3" stroke-linecap="round"/>'
            . '</g>';
        $s .= '<g transform="translate(' . round($w * 0.18) . ' ' . round($h * 0.52) . ') rotate(' . (4 - $v % 3 * 3) . ')">'
            . '<rect width="' . round($w * 0.32) . '" height="' . round($h * 0.26) . '" rx="18" style="fill:#fff;fill-opacity:.18;stroke:#fff;stroke-opacity:.5"/>'
            . '<circle cx="40" cy="44" r="18" ' . self::w(.85) . '/>'
            . '<rect x="70" y="32" width="' . round($w * 0.16) . '" height="10" rx="5" ' . self::w(.85) . '/>'
            . '<rect x="70" y="50" width="' . round($w * 0.1) . '" height="8" rx="4" ' . self::w(.55) . '/>'
            . '</g>';
        $s .= '<circle cx="' . round($w * 0.86) . '" cy="' . round($h * 0.8) . '" r="' . round($h * 0.16) . '" ' . self::w(.12) . '/>';
        $s .= '<circle cx="' . round($w * 0.1) . '" cy="' . round($h * 0.92) . '" r="' . round($h * 0.22) . '" ' . self::w(.08) . '/>';
        return $s;
    }

    private static function campus(int $w, int $h, int $v, string $id): string
    {
        $g = $h * 0.78;
        $s = '<circle cx="' . round($w * 0.78) . '" cy="' . round($h * 0.24) . '" r="' . round($h * 0.09) . '" ' . self::w(.75) . '/>';
        $s .= '<path d="M0 ' . round($g - 40) . ' Q ' . round($w * .3) . ' ' . round($g - 90) . ' ' . round($w * .6) . ' ' . round($g - 50) . ' T ' . $w . ' ' . round($g - 60) . ' V ' . $h . ' H 0 Z" ' . self::w(.12) . '/>';
        // main hall with pediment
        $bx = $w * 0.28;
        $bw = $w * 0.44;
        $bh = $h * 0.34;
        $s .= '<rect x="' . round($bx) . '" y="' . round($g - $bh) . '" width="' . round($bw) . '" height="' . round($bh) . '" ' . self::w(.9) . '/>';
        $s .= '<path d="M' . round($bx - 16) . ' ' . round($g - $bh) . ' L' . round($bx + $bw / 2) . ' ' . round($g - $bh - $h * 0.12) . ' L' . round($bx + $bw + 16) . ' ' . round($g - $bh) . ' Z" ' . self::w(.95) . '/>';
        $s .= '<circle cx="' . round($bx + $bw / 2) . '" cy="' . round($g - $bh - $h * 0.045) . '" r="' . round($h * 0.025) . '" style="fill:var(--color-primary,#2563eb);fill-opacity:.6"/>';
        $cols = 6;
        for ($i = 0; $i < $cols; $i++) {
            $x = $bx + 18 + $i * (($bw - 36) / ($cols - 1)) - 7;
            $s .= '<rect x="' . round($x) . '" y="' . round($g - $bh + 22) . '" width="14" height="' . round($bh - 44) . '" rx="3" style="fill:var(--color-primary,#2563eb);fill-opacity:.18"/>';
        }
        $s .= '<rect x="' . round($bx + $bw / 2 - 22) . '" y="' . round($g - 62) . '" width="44" height="62" rx="22" style="fill:var(--color-primary,#2563eb);fill-opacity:.55"/>';
        // side wings
        foreach ([[0.08, 0.18], [0.74, 0.2]] as $k => [$fx, $fw]) {
            $wx = $w * $fx;
            $ww = $w * $fw;
            $wh = $h * (0.22 + 0.03 * (($v + $k) % 2));
            $s .= '<rect x="' . round($wx) . '" y="' . round($g - $wh) . '" width="' . round($ww) . '" height="' . round($wh) . '" ' . self::w(.7) . '/>';
            for ($r = 0; $r < 2; $r++) {
                for ($c = 0; $c < 3; $c++) {
                    $s .= '<rect x="' . round($wx + 14 + $c * ($ww - 28) / 3) . '" y="' . round($g - $wh + 16 + $r * 34) . '" width="' . round(($ww - 28) / 3 - 10) . '" height="20" rx="3" style="fill:var(--color-brand-accent,#7c3aed);fill-opacity:.22"/>';
                }
            }
        }
        // trees
        foreach ([0.05, 0.25, 0.73, 0.95] as $tx) {
            $x = $w * $tx;
            $s .= '<rect x="' . round($x - 3) . '" y="' . round($g - 40) . '" width="6" height="40" ' . self::w(.6) . '/>';
            $s .= '<circle cx="' . round($x) . '" cy="' . round($g - 52) . '" r="26" ' . self::w(.45) . '/>';
        }
        $s .= '<rect x="0" y="' . round($g) . '" width="' . $w . '" height="' . round($h - $g) . '" ' . self::w(.22) . '/>';
        $s .= '<path d="M' . round($w * .44) . ' ' . $h . ' L' . round($w * .48) . ' ' . round($g) . ' H' . round($w * .52) . ' L' . round($w * .56) . ' ' . $h . ' Z" ' . self::w(.35) . '/>';
        return $s;
    }

    private static function people(int $w, int $h, int $v, string $id): string
    {
        $s = '';
        $n = 3 + ($v % 2);
        $base = $h * 0.98;
        for ($i = 0; $i < $n; $i++) {
            $fx = ($i + 0.5) / $n;
            $x = $w * (0.12 + 0.76 * $fx);
            $scale = 1 - abs($fx - 0.5) * 0.35;
            $r = $h * 0.085 * $scale;
            $bodyW = $r * 3.3;
            $top = $base - $h * 0.42 * $scale;
            $op = $i % 2 ? .78 : .95;
            $s .= '<circle cx="' . round($x) . '" cy="' . round($top - $r * 1.25) . '" r="' . round($r) . '" ' . self::w($op) . '/>';
            $s .= '<path d="M' . round($x - $bodyW / 2) . ' ' . round($base) . ' V' . round($top + $r) . ' a' . round($bodyW / 2) . ' ' . round($r * 1.6) . ' 0 0 1 ' . round($bodyW) . ' 0 V' . round($base) . ' Z" ' . self::w($op * .85) . '/>';
        }
        $s .= '<circle cx="' . round($w * .14) . '" cy="' . round($h * .2) . '" r="' . round($h * .06) . '" style="fill:none;stroke:#fff;stroke-opacity:.5" stroke-width="3"/>';
        $s .= '<path d="M' . round($w * .8) . ' ' . round($h * .14) . ' l12 22 24 4 -18 16 4 24 -22 -12 -22 12 4 -24 -18 -16 24 -4z" ' . self::w(.55) . '/>';
        return $s;
    }

    private static function nature(int $w, int $h, int $v, string $id): string
    {
        $s = '<circle cx="' . round($w * (0.3 + 0.1 * ($v % 3))) . '" cy="' . round($h * .3) . '" r="' . round($h * .1) . '" ' . self::w(.8) . '/>';
        $s .= '<path d="M0 ' . round($h * .62) . ' L' . round($w * .22) . ' ' . round($h * .36) . ' L' . round($w * .4) . ' ' . round($h * .56) . ' L' . round($w * .62) . ' ' . round($h * .28) . ' L' . $w . ' ' . round($h * .6) . ' V' . $h . ' H0Z" ' . self::w(.28) . '/>';
        $s .= '<path d="M0 ' . round($h * .74) . ' Q' . round($w * .3) . ' ' . round($h * .6) . ' ' . round($w * .55) . ' ' . round($h * .72) . ' T' . $w . ' ' . round($h * .68) . ' V' . $h . ' H0Z" ' . self::w(.45) . '/>';
        $s .= '<path d="M0 ' . round($h * .86) . ' Q' . round($w * .4) . ' ' . round($h * .78) . ' ' . $w . ' ' . round($h * .88) . ' V' . $h . ' H0Z" ' . self::w(.75) . '/>';
        return $s;
    }

    private static function event(int $w, int $h, int $v, string $id): string
    {
        $s = '';
        // stage lights
        for ($i = 0; $i < 4; $i++) {
            $x = $w * (0.15 + $i * 0.23);
            $s .= '<path d="M' . round($x) . ' 0 L' . round($x - 90) . ' ' . round($h * .8) . ' H' . round($x + 90) . ' Z" ' . self::w(.08 + .03 * ($i % 2)) . '/>';
        }
        $cw = $w * .42;
        $ch = $h * .5;
        $x = ($w - $cw) / 2;
        $y = $h * .22;
        $s .= '<rect x="' . round($x) . '" y="' . round($y) . '" width="' . round($cw) . '" height="' . round($ch) . '" rx="22" ' . self::w(.95) . '/>';
        $s .= '<rect x="' . round($x) . '" y="' . round($y) . '" width="' . round($cw) . '" height="' . round($ch * .28) . '" rx="22" style="fill:var(--color-primary,#2563eb);fill-opacity:.9"/>';
        $s .= '<rect x="' . round($x) . '" y="' . round($y + $ch * .18) . '" width="' . round($cw) . '" height="' . round($ch * .1) . '" style="fill:var(--color-primary,#2563eb);fill-opacity:.9"/>';
        for ($r = 0; $r < 3; $r++) {
            for ($c = 0; $c < 5; $c++) {
                $hl = ($r === 1 && $c === 2 + ($v % 2));
                $s .= '<rect x="' . round($x + 22 + $c * ($cw - 44) / 5) . '" y="' . round($y + $ch * .36 + $r * $ch * .2) . '" width="' . round(($cw - 44) / 5 - 10) . '" height="' . round($ch * .13) . '" rx="6" style="fill:' . ($hl ? 'var(--color-brand-accent,#7c3aed);fill-opacity:.85' : '#0f172a;fill-opacity:.08') . '"/>';
            }
        }
        return $s;
    }

    private static function map(int $w, int $h, int $v, string $id): string
    {
        $s = '<rect width="' . $w . '" height="' . $h . '" style="fill:var(--color-surface,#fff);fill-opacity:.88"/>';
        for ($x = 0; $x < $w; $x += 60) {
            $s .= '<path d="M' . $x . ' 0 V' . $h . '" style="stroke:var(--color-primary,#2563eb);stroke-opacity:.08"/>';
        }
        for ($y = 0; $y < $h; $y += 60) {
            $s .= '<path d="M0 ' . $y . ' H' . $w . '" style="stroke:var(--color-primary,#2563eb);stroke-opacity:.08"/>';
        }
        $s .= '<path d="M-20 ' . round($h * .7) . ' C ' . round($w * .25) . ' ' . round($h * .5) . ', ' . round($w * .45) . ' ' . round($h * .95) . ', ' . ($w + 20) . ' ' . round($h * .62) . '" fill="none" style="stroke:var(--color-brand-accent,#7c3aed);stroke-opacity:.25" stroke-width="28"/>';
        $s .= '<path d="M' . round($w * .1) . ' -10 L' . round($w * .7) . ' ' . ($h + 10) . '" style="stroke:var(--color-text,#0f172a);stroke-opacity:.12" stroke-width="14"/>';
        $s .= '<path d="M-10 ' . round($h * .3) . ' H' . ($w + 10) . '" style="stroke:var(--color-text,#0f172a);stroke-opacity:.1" stroke-width="10"/>';
        $s .= '<rect x="' . round($w * .62) . '" y="' . round($h * .12) . '" width="' . round($w * .16) . '" height="' . round($h * .12) . '" rx="8" style="fill:var(--color-primary,#2563eb);fill-opacity:.1"/>';
        $s .= '<rect x="' . round($w * .14) . '" y="' . round($h * .4) . '" width="' . round($w * .12) . '" height="' . round($h * .16) . '" rx="8" style="fill:var(--color-brand-accent,#7c3aed);fill-opacity:.1"/>';
        $px = $w * .52;
        $py = $h * .5;
        $s .= '<circle cx="' . round($px) . '" cy="' . round($py) . '" r="46" style="fill:var(--color-primary,#2563eb);fill-opacity:.14"/>';
        $s .= '<path d="M' . round($px) . ' ' . round($py + 8) . ' c-16 -22 -28 -34 -28 -52 a28 28 0 0 1 56 0 c0 18 -12 30 -28 52z" style="fill:var(--color-primary,#2563eb)"/>';
        $s .= '<circle cx="' . round($px) . '" cy="' . round($py - 44) . '" r="10" fill="#fff"/>';
        // hide default gradient for map: covered by surface rect above
        return $s;
    }

    private static function study(int $w, int $h, int $v, string $id): string
    {
        $s = '';
        // stacked books
        $bx = $w * .12;
        $by = $h * .82;
        $cols = [.95, .7, .85, .6];
        for ($i = 0; $i < 4; $i++) {
            $bw = $w * (.34 - $i * .03);
            $s .= '<rect x="' . round($bx + $i * 8) . '" y="' . round($by - ($i + 1) * 34) . '" width="' . round($bw) . '" height="30" rx="5" ' . self::w($cols[$i]) . '/>';
            $s .= '<rect x="' . round($bx + $i * 8 + 16) . '" y="' . round($by - ($i + 1) * 34 + 11) . '" width="' . round($bw * .4) . '" height="8" rx="4" style="fill:var(--color-primary,#2563eb);fill-opacity:.35"/>';
        }
        // open laptop / board
        $lx = $w * .52;
        $ly = $h * .26;
        $lw = $w * .38;
        $lh = $h * .38;
        $s .= '<rect x="' . round($lx) . '" y="' . round($ly) . '" width="' . round($lw) . '" height="' . round($lh) . '" rx="14" ' . self::w(.94) . '/>';
        $s .= '<path d="M' . round($lx + 30) . ' ' . round($ly + $lh - 40) . ' l40 -50 l40 30 l50 -70 l50 40" fill="none" style="stroke:var(--color-primary,#2563eb)" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>';
        $s .= '<circle cx="' . round($lx + $lw - 40) . '" cy="' . round($ly + 40) . '" r="16" style="fill:var(--color-brand-accent,#7c3aed);fill-opacity:.6"/>';
        $s .= '<rect x="' . round($lx - 24) . '" y="' . round($ly + $lh) . '" width="' . round($lw + 48) . '" height="16" rx="8" ' . self::w(.8) . '/>';
        // pencil / plant
        $s .= '<rect x="' . round($w * .86) . '" y="' . round($h * .7) . '" width="34" height="' . round($h * .12) . '" rx="6" ' . self::w(.7) . '/>';
        $s .= '<path d="M' . round($w * .86 + 17) . ' ' . round($h * .7) . ' c-24 -30 -10 -60 0 -70 c10 10 24 40 0 70z" ' . self::w(.6) . '/>';
        return $s;
    }

    private static function stage(int $w, int $h, int $v, string $id): string
    {
        $s = '';
        for ($i = 0; $i < 5; $i++) {
            $x = $w * (0.1 + $i * 0.2);
            $s .= '<path d="M' . round($x) . ' 0 L' . round($x - 70 + ($i % 2) * 40) . ' ' . round($h * .75) . ' H' . round($x + 70 + ($i % 2) * 40) . ' Z" ' . self::w(.07) . '/>';
        }
        $s .= '<ellipse cx="' . round($w / 2) . '" cy="' . round($h * .78) . '" rx="' . round($w * .42) . '" ry="' . round($h * .08) . '" ' . self::w(.3) . '/>';
        // performer silhouettes
        foreach ([.38, .5, .62] as $k => $fx) {
            $x = $w * $fx;
            $s .= '<circle cx="' . round($x) . '" cy="' . round($h * .42) . '" r="' . round($h * .045) . '" ' . self::w(.9) . '/>';
            $s .= '<path d="M' . round($x - 26) . ' ' . round($h * .76) . ' V' . round($h * .54) . ' a26 30 0 0 1 52 0 V' . round($h * .76) . 'Z" ' . self::w(.85) . '/>';
        }
        // notes
        $s .= '<g ' . self::w(.75) . '><circle cx="' . round($w * .16) . '" cy="' . round($h * .36) . '" r="10"/><rect x="' . round($w * .16 + 6) . '" y="' . round($h * .36 - 46) . '" width="4" height="46"/>'
            . '<circle cx="' . round($w * .84) . '" cy="' . round($h * .3) . '" r="10"/><rect x="' . round($w * .84 + 6) . '" y="' . round($h * .3 - 46) . '" width="4" height="46"/></g>';
        return $s;
    }

    private static function sport(int $w, int $h, int $v, string $id): string
    {
        $s = '<rect x="' . round($w * .06) . '" y="' . round($h * .5) . '" width="' . round($w * .88) . '" height="' . round($h * .44) . '" rx="24" style="fill:#fff;fill-opacity:.2;stroke:#fff;stroke-opacity:.6"/>';
        $s .= '<path d="M' . round($w / 2) . ' ' . round($h * .5) . ' V' . round($h * .94) . '" style="stroke:#fff;stroke-opacity:.6" stroke-width="3"/>';
        $s .= '<circle cx="' . round($w / 2) . '" cy="' . round($h * .72) . '" r="' . round($h * .09) . '" fill="none" style="stroke:#fff;stroke-opacity:.6" stroke-width="3"/>';
        $s .= '<circle cx="' . round($w * .68) . '" cy="' . round($h * .28) . '" r="' . round($h * .12) . '" ' . self::w(.92) . '/>';
        $s .= '<path d="M' . round($w * .68 - $h * .12) . ' ' . round($h * .28) . ' H' . round($w * .68 + $h * .12) . ' M' . round($w * .68) . ' ' . round($h * .16) . ' V' . round($h * .4) . '" style="stroke:var(--color-primary,#2563eb);stroke-opacity:.5" stroke-width="4"/>';
        $s .= '<path d="M' . round($w * .2) . ' ' . round($h * .4) . ' q60 -120 160 -60" fill="none" style="stroke:#fff;stroke-opacity:.55" stroke-width="4" stroke-dasharray="10 12" stroke-linecap="round"/>';
        return $s;
    }

    private static function city(int $w, int $h, int $v, string $id): string
    {
        $s = '';
        $x = 0;
        $i = 0;
        while ($x < $w) {
            $bw = 50 + (($i * 37 + $v * 13) % 60);
            $bh = $h * (0.25 + ((($i * 53 + $v * 7) % 40) / 100));
            $op = .35 + (($i % 3) * .2);
            $s .= '<rect x="' . round($x) . '" y="' . round($h - $bh) . '" width="' . $bw . '" height="' . round($bh) . '" ' . self::w($op) . '/>';
            for ($wy = $h - $bh + 18; $wy < $h - 20; $wy += 26) {
                for ($wx = $x + 10; $wx < $x + $bw - 16; $wx += 18) {
                    if ((((int) $wx + (int) $wy + $i) % 3) === 0) {
                        $s .= '<rect x="' . round($wx) . '" y="' . round($wy) . '" width="8" height="10" style="fill:var(--color-primary,#2563eb);fill-opacity:.35"/>';
                    }
                }
            }
            $x += $bw + 6;
            $i++;
        }
        return $s;
    }

    private static function product(int $w, int $h, int $v, string $id): string
    {
        $s = '';
        $pw = $w * .62;
        $ph = $h * .62;
        $px = ($w - $pw) / 2;
        $py = $h * .2;
        $s .= '<rect x="' . round($px) . '" y="' . round($py) . '" width="' . round($pw) . '" height="' . round($ph) . '" rx="18" ' . self::w(.96) . '/>';
        $s .= '<rect x="' . round($px) . '" y="' . round($py) . '" width="' . round($pw) . '" height="34" rx="18" style="fill:#0f172a;fill-opacity:.05"/>';
        foreach ([0, 1, 2] as $d) {
            $s .= '<circle cx="' . round($px + 22 + $d * 16) . '" cy="' . round($py + 17) . '" r="5" style="fill:#0f172a;fill-opacity:.15"/>';
        }
        $s .= '<rect x="' . round($px + 24) . '" y="' . round($py + 56) . '" width="' . round($pw * .28) . '" height="' . round($ph - 80) . '" rx="10" style="fill:var(--color-primary,#2563eb);fill-opacity:.08"/>';
        for ($i = 0; $i < 4; $i++) {
            $s .= '<rect x="' . round($px + 38) . '" y="' . round($py + 74 + $i * 28) . '" width="' . round($pw * .18) . '" height="10" rx="5" style="fill:var(--color-primary,#2563eb);fill-opacity:' . ($i === 0 ? '.7' : '.2') . '"/>';
        }
        $gx = $px + $pw * .36;
        $gw = $pw * .58;
        for ($i = 0; $i < 6; $i++) {
            $bh = ($ph - 120) * (0.3 + (($i * 29 + $v * 11) % 70) / 100);
            $s .= '<rect x="' . round($gx + $i * $gw / 6) . '" y="' . round($py + $ph - 24 - $bh) . '" width="' . round($gw / 6 - 12) . '" height="' . round($bh) . '" rx="6" style="fill:url(#' . $id . 'g)"/>';
        }
        return $s;
    }

    private static function community(int $w, int $h, int $v, string $id): string
    {
        $s = '';
        $cx = $w / 2;
        $cy = $h * .52;
        $s .= '<circle cx="' . round($cx) . '" cy="' . round($cy) . '" r="' . round($h * .34) . '" fill="none" style="stroke:#fff;stroke-opacity:.35" stroke-width="2" stroke-dasharray="6 10"/>';
        $s .= '<circle cx="' . round($cx) . '" cy="' . round($cy) . '" r="' . round($h * .2) . '" fill="none" style="stroke:#fff;stroke-opacity:.5" stroke-width="2"/>';
        $s .= '<path d="M' . round($cx) . ' ' . round($cy + 30) . ' c-30 -24 -48 -40 -48 -62 a26 26 0 0 1 48 -14 a26 26 0 0 1 48 14 c0 22 -18 38 -48 62z" ' . self::w(.95) . '/>';
        for ($i = 0; $i < 7; $i++) {
            $a = (M_PI * 2 / 7) * $i + $v;
            $x = $cx + cos($a) * $h * .34;
            $y = $cy + sin($a) * $h * .34;
            $s .= '<circle cx="' . round($x) . '" cy="' . round($y) . '" r="' . (18 + ($i % 3) * 4) . '" ' . self::w(.6 + ($i % 2) * .3) . '/>';
        }
        return $s;
    }
}
