<?php
/**
 * Mova AI — text hygiene for model output.
 *
 * Models sometimes return almost-JSON (raw newlines, smart-quote delimiters, trailing commas) and
 * mix literal UTF-8 with JSON escapes such as \u2019. Everything here keeps those escapes intact
 * until a real JSON decoder (or the strict decoder below) turns them into characters. Never use
 * stripslashes()/stripcslashes() on model output: they silently drop the backslash of "\u2019"
 * and leave "u2019" in the content.
 */

declare(strict_types=1);

namespace Mova\AI;

final class AiText
{
    /** Flags for storing / returning AI text as JSON. */
    public const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

    /**
     * Decode the inside of a JSON string literal (the text between the quotes), tolerating the
     * mistakes models make: raw newlines/tabs, unknown escapes, lone surrogates.
     */
    public static function jsonUnescape(string $inner): string
    {
        $try = json_decode('"' . self::escapeRawControls($inner) . '"');
        if (is_string($try)) {
            return $try;
        }
        // Manual, strict-enough decoder (used when the literal also has e.g. an unescaped quote).
        $out = '';
        $len = strlen($inner);
        for ($i = 0; $i < $len; $i++) {
            $c = $inner[$i];
            if ($c !== '\\' || $i + 1 >= $len) {
                $out .= $c;
                continue;
            }
            $n = $inner[++$i];
            switch ($n) {
                case 'n': $out .= "\n"; break;
                case 'r': $out .= "\r"; break;
                case 't': $out .= "\t"; break;
                case 'b': $out .= "\x08"; break;
                case 'f': $out .= "\f"; break;
                case '"': case '\\': case '/': $out .= $n; break;
                case 'u':
                    if (preg_match('/\G([0-9a-fA-F]{4})/', $inner, $m, 0, $i + 1)) {
                        $cp = hexdec($m[1]);
                        $i += 4;
                        if ($cp >= 0xD800 && $cp <= 0xDBFF && preg_match('/\G\\\\u(d[c-f][0-9a-f]{2})/i', $inner, $m2, 0, $i + 1)) {
                            $lo = hexdec($m2[1]);
                            $cp = 0x10000 + (($cp - 0xD800) << 10) + ($lo - 0xDC00);
                            $i += 6;
                        }
                        $out .= ($cp >= 0xD800 && $cp <= 0xDFFF) ? "\u{FFFD}" : mb_chr($cp, 'UTF-8');
                    } else {
                        $out .= '\\u'; // keep a malformed escape visible rather than eating the backslash
                    }
                    break;
                default:
                    // Unknown escape (e.g. \' or \x): keep the character, drop nothing else.
                    $out .= ($n === "'") ? "'" : '\\' . $n;
            }
        }
        return $out;
    }

    /**
     * Repair almost-JSON from a model without touching string contents: escape raw control
     * characters inside strings, turn smart-quote *delimiters* (outside strings) into ",
     * drop trailing commas, and fix invalid escapes like \' . String bodies keep their
     * \uXXXX escapes so json_decode can decode them.
     */
    public static function repairJson(string $raw): string
    {
        $raw = self::outsideStrings($raw, true);
        $out = '';
        $inStr = false;
        $len = strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            $c = $raw[$i];
            if ($inStr) {
                if ($c === '\\') {
                    $n = $i + 1 < $len ? $raw[$i + 1] : '';
                    if ($n === "'") {
                        $out .= "'";
                        $i++;
                    } elseif ($n !== '' && strpbrk($n, "\"\\/bfnrtu") !== false) {
                        $out .= $c . $n;
                        $i++;
                    } else {
                        $out .= '\\\\';
                    }
                    continue;
                }
                if ($c === '"') {
                    $inStr = false;
                    $out .= $c;
                    continue;
                }
                $o = ord($c);
                if ($o < 0x20) {
                    $out .= $c === "\n" ? '\\n' : ($c === "\r" ? '\\r' : ($c === "\t" ? '\\t' : sprintf('\\u%04x', $o)));
                    continue;
                }
                $out .= $c;
                continue;
            }
            if ($c === '"') {
                $inStr = true;
            }
            $out .= $c;
        }
        // Trailing commas before } or ] (outside strings only).
        return self::outsideStrings($out, false);
    }

    /**
     * Walk JSON-ish text; outside string literals either mark smart quotes for replacement
     * ($smartQuotes) or strip trailing commas. Strings delimited by " are copied verbatim.
     */
    private static function outsideStrings(string $s, bool $smartQuotes): string
    {
        $out = '';
        $buf = '';
        $len = strlen($s);
        $inStr = false;
        $flush = static function (string $b) use ($smartQuotes): string {
            if ($smartQuotes) {
                return $b; // smart quotes are only replaced in non-string runs below
            }
            return preg_replace('/,\s*([}\]])/', '$1', $b) ?? $b;
        };
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ($inStr) {
                $out .= $c;
                if ($c === '\\' && $i + 1 < $len) {
                    $out .= $s[++$i];
                } elseif ($c === '"') {
                    $inStr = false;
                }
                continue;
            }
            if ($smartQuotes && (substr($s, $i, 3) === '“' || substr($s, $i, 3) === '”')) {
                // A smart quote used as a JSON delimiter: emit a real quote and enter string mode.
                $out .= $flush($buf) . '"';
                $buf = '';
                $i += 2;
                $inStr = true;
                // Copy until the matching smart/straight closing quote.
                for ($i++; $i < $len; $i++) {
                    if (substr($s, $i, 3) === '”' || substr($s, $i, 3) === '“') {
                        $out .= '"';
                        $i += 2;
                        break;
                    }
                    if ($s[$i] === '"') {
                        $out .= '\\"';
                        continue;
                    }
                    $out .= $s[$i];
                }
                $inStr = false;
                continue;
            }
            if ($c === '"') {
                $out .= $flush($buf) . '"';
                $buf = '';
                $inStr = true;
                continue;
            }
            $buf .= $c;
        }
        return $out . $flush($buf);
    }

    /** Escape raw control characters so a string literal survives json_decode. */
    private static function escapeRawControls(string $s): string
    {
        return preg_replace_callback('/[\x00-\x1F]/', static fn ($m) => match ($m[0]) {
            "\n" => '\\n', "\r" => '\\r', "\t" => '\\t',
            default => sprintf('\\u%04x', ord($m[0])),
        }, $s) ?? $s;
    }

    /**
     * Safety net: turn literal backslash escapes (\u2019, \ud83d\ude00, also the double-escaped
     * \\u2019) that leaked into prose into real characters. Requires the backslash — a bare
     * "u2019" in normal text is never touched. Code (``` fences, `inline`, <pre>, <code>,
     * <script>, <style>) is left exactly as written.
     */
    public static function decodeLeakedEscapes(string $s): string
    {
        if (!str_contains($s, '\\u') && !str_contains($s, '\\U')) {
            return $s;
        }
        $esc = '\\\\{1,2}u(?<hi>d[89ab][0-9a-f]{2})\\\\{1,2}u(?<lo>d[c-f][0-9a-f]{2})|\\\\{1,2}u(?<one>[0-9a-f]{4})';
        $code = '```[\s\S]*?```|`[^`\n]*`|<(?<tag>pre|code|script|style)\b[\s\S]*?<\/\k<tag>>';
        return preg_replace_callback('/(?<code>' . $code . ')|' . $esc . '/i', static function (array $m): string {
            if (($m['code'] ?? '') !== '') {
                return $m[0]; // code segment, verbatim
            }
            if (($m['hi'] ?? '') !== '') {
                $cp = 0x10000 + ((hexdec($m['hi']) - 0xD800) << 10) + (hexdec($m['lo']) - 0xDC00);
                return mb_chr($cp, 'UTF-8');
            }
            $cp = hexdec($m['one'] ?? '');
            // Never inject control characters, quotes, backslashes, angle brackets or lone surrogates.
            if ($cp < 0x20 || in_array($cp, [0x22, 0x27, 0x3C, 0x3E, 0x5C], true) || ($cp >= 0xD800 && $cp <= 0xDFFF)) {
                return $m[0];
            }
            return mb_chr($cp, 'UTF-8');
        }, $s) ?? $s;
    }

    /** U+2011 non-breaking hyphen → "-", U+202F narrow no-break space → " " (search, SEO, fonts). */
    public static function normalizePunctuation(string $s): string
    {
        return str_replace(["\u{2011}", "\u{202F}"], ['-', ' '], $s);
    }

    /** Full clean for AI prose / content fields: leaked escapes → characters, normalise, valid UTF-8. */
    public static function clean(string $s): string
    {
        if ($s === '') {
            return $s;
        }
        $s = mb_scrub($s, 'UTF-8');
        return self::normalizePunctuation(self::decodeLeakedEscapes($s));
    }

    /** Apply clean() to every string in a nested array (action payloads, section specs). */
    public static function cleanDeep(mixed $v): mixed
    {
        if (is_string($v)) {
            return self::clean($v);
        }
        if (is_array($v)) {
            foreach ($v as $k => $x) {
                $v[$k] = self::cleanDeep($x);
            }
        }
        return $v;
    }

    /**
     * Codepoints that the old stripcslashes() bug turned into "uXXXX" in saved content, with what
     * they should read as. Used by bin/fix-unicode-escapes.php.
     *
     * @return array<string,string> lower-case hex => replacement text
     */
    public static function corruptedPunctuationMap(): array
    {
        $map = [];
        foreach ([0x2010, 0x2011, 0x2012, 0x2013, 0x2014, 0x2015, 0x2018, 0x2019, 0x201A, 0x201B, 0x201C, 0x201D, 0x201E, 0x201F, 0x2026, 0x202F, 0x00A0, 0x2032, 0x2033] as $cp) {
            $map[sprintf('%04x', $cp)] = self::normalizePunctuation(mb_chr($cp, 'UTF-8'));
        }
        return $map;
    }

    /**
     * Find and restore "word + uXXXX" corruption (no backslash) for the codepoints above.
     * All-digit codepoints (2010–2015, 2018, 2019, 2026, 2032, 2033) look like years or numbers, so
     * they are only restored when a letter follows ("Nigeriau2019s", "fastu2011changing",
     * "futureu2014whether"); "menu2019", "su2013 2015" or "item u2014" stay untouched.
     * Codepoints with hex letters (201a–201f, 202f, 00a0) can't be years, so the next character
     * just must not continue the hex run ("20u202f%", "bestu201d,").
     *
     * @param list<array{match:string,context:string,replacement:string}> $hits
     */
    public static function repairCorrupted(string $s, ?array &$hits = null): string
    {
        $hits = [];
        $map = self::corruptedPunctuationMap();
        $digitOnly = '201[0-5]|201[89]|2026|203[23]';
        $hexy = '201[a-f]|202f|00a0';
        $re = '/(?<=[\p{L}\p{N}])u(?:(' . $digitOnly . ')(?=\p{L})|(' . $hexy . ')(?![0-9a-f]))/iu';
        return preg_replace_callback($re, static function (array $m) use ($map, $s, &$hits): string {
            $hex = strtolower(($m[1][0] ?? '') !== '' ? $m[1][0] : $m[2][0]);
            $rep = $map[$hex] ?? $m[0][0];
            $off = (int) $m[0][1];
            // Leave URLs, attribute values and query strings alone (slugs, ids, hashes).
            $before = substr($s, 0, $off);
            $tokStart = preg_match('/[\s<>"\']([^\s<>"\']*)$/', $before, $bm) ? $off - strlen($bm[1]) : 0;
            $after = preg_match('/^[^\s<>"\']*/', substr($s, $off), $am) ? $am[0] : '';
            $token = substr($s, $tokStart, $off - $tokStart) . $after;
            if (preg_match('#://|[=/?&\#@]|^www\.#', $token)) {
                return $m[0][0];
            }
            // Context in characters (not bytes) so multi-byte punctuation is never cut in half.
            $ctx = mb_substr(mb_scrub($before, 'UTF-8'), -30) . '[' . $m[0][0] . '→' . $rep . ']'
                . mb_substr(mb_scrub(substr($s, $off + strlen($m[0][0])), 'UTF-8'), 0, 30);
            $hits[] = ['match' => $m[0][0], 'replacement' => $rep, 'context' => preg_replace('/\s+/u', ' ', $ctx) ?? $ctx];
            return $rep;
        }, $s, -1, $count, PREG_OFFSET_CAPTURE) ?? $s;
    }
}
