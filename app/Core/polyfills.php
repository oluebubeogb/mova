<?php
/**
 * Mova CMS - minimal fallbacks for hosts without ext-mbstring (1.2.2).
 * Only the mb_* functions Mova uses; each is defined only when missing. UTF-8 only.
 * Install php-mbstring for full accuracy - HQ -> System health / bin/mova-doctor.php flag it.
 */

if (!function_exists('mb_strlen')) {
    function mb_strlen($s, $enc = null): int
    {
        $s = (string) $s;
        return preg_match_all('/./us', $s) ?: strlen($s);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr($s, $start, $length = null, $enc = null): string
    {
        $chars = preg_split('//u', (string) $s, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return $length === null ? (string) substr((string) $s, $start) : (string) substr((string) $s, $start, $length);
        }
        return implode('', $length === null ? array_slice($chars, (int) $start) : array_slice($chars, (int) $start, (int) $length));
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($s, $enc = null): string
    {
        // ASCII-only lowercasing; other scripts are left as-is (best effort without mbstring).
        return strtolower((string) $s);
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($s, $enc = null): string
    {
        return strtoupper((string) $s);
    }
}
if (!function_exists('mb_convert_case')) {
    if (!defined('MB_CASE_UPPER')) {
        define('MB_CASE_UPPER', 0);
        define('MB_CASE_LOWER', 1);
        define('MB_CASE_TITLE', 2);
    }
    function mb_convert_case($s, $mode, $enc = null): string
    {
        $s = (string) $s;
        if ($mode === MB_CASE_UPPER) {
            return strtoupper($s);
        }
        if ($mode === MB_CASE_LOWER) {
            return strtolower($s);
        }
        return ucwords(strtolower($s));
    }
}
if (!function_exists('mb_strpos')) {
    function mb_strpos($h, $n, $offset = 0, $enc = null)
    {
        $h = (string) $h;
        $byteOff = $offset > 0 ? strlen(mb_substr($h, 0, $offset)) : 0;
        $p = strpos($h, (string) $n, $byteOff);
        return $p === false ? false : mb_strlen(substr($h, 0, $p));
    }
}
if (!function_exists('mb_strrpos')) {
    function mb_strrpos($h, $n, $offset = 0, $enc = null)
    {
        $h = (string) $h;
        $p = strrpos($h, (string) $n);
        return $p === false ? false : mb_strlen(substr($h, 0, $p));
    }
}
if (!function_exists('mb_chr')) {
    function mb_chr($cp, $enc = null)
    {
        $cp = (int) $cp;
        if ($cp < 0 || $cp > 0x10FFFF || ($cp >= 0xD800 && $cp <= 0xDFFF)) {
            return false;
        }
        if ($cp < 0x80) {
            return chr($cp);
        }
        if ($cp < 0x800) {
            return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
        }
        if ($cp < 0x10000) {
            return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
        }
        return chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
    }
}
if (!function_exists('mb_scrub')) {
    function mb_scrub($s, $enc = null): string
    {
        $s = (string) $s;
        if (preg_match('//u', $s)) {
            return $s;
        }
        if (function_exists('iconv')) {
            $r = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
            if ($r !== false) {
                return $r;
            }
        }
        return (string) preg_replace('/[\x80-\xFF]/', '?', $s);
    }
}
if (!function_exists('mb_strimwidth')) {
    function mb_strimwidth($s, $start, $width, $trim = '', $enc = null): string
    {
        $s = mb_substr((string) $s, (int) $start);
        if (mb_strlen($s) <= $width) {
            return $s;
        }
        return mb_substr($s, 0, max(0, $width - mb_strlen((string) $trim))) . $trim;
    }
}
