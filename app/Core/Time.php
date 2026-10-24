<?php
/**
 * Mova\Core\Time — one place to turn stored timestamps into human times.
 *
 * Storage stays UTC (PHP default timezone is UTC via config; SQLite CURRENT_TIMESTAMP is UTC).
 * Display uses the site timezone setting `site_timezone` (default Africa/Lagos, WAT, UTC+1).
 *
 *   Time::format($v)        "23 Oct 2026, 9:00 PM"
 *   Time::date($v)          "23 Oct 2026"
 *   Time::ago($v)           "5 min ago" (falls back to the date after 7 days)
 *   Time::tag($v, $mode)    <time datetime="…Z" title="23 Oct 2026, 9:00 PM WAT">…</time>
 *                           $mode: 'abs' | 'rel' | 'both' | 'date'
 */

declare(strict_types=1);

namespace Mova\Core;

final class Time
{
    public const DEFAULT_ZONE = 'Africa/Lagos';
    private static ?\DateTimeZone $zone = null;

    /** Site display timezone (validated; falls back to Africa/Lagos). */
    public static function zoneName(): string
    {
        return self::zone()->getName();
    }

    public static function zone(): \DateTimeZone
    {
        if (self::$zone === null) {
            $name = '';
            try {
                $row = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = 'site_timezone'");
                $name = trim((string) ($row['setting_value'] ?? ''));
            } catch (\Throwable $e) {
                $name = '';
            }
            self::$zone = new \DateTimeZone(self::isValidZone($name) ? $name : self::DEFAULT_ZONE);
        }
        return self::$zone;
    }

    /** For tests / after the setting changes within the same request. */
    public static function reset(): void
    {
        self::$zone = null;
    }

    public static function isValidZone(string $name): bool
    {
        return $name !== '' && in_array($name, \DateTimeZone::listIdentifiers(), true);
    }

    /** Parse a stored value (ISO string, "Y-m-d H:i:s" UTC, unix int) as an instant. */
    public static function parse($v): ?\DateTimeImmutable
    {
        if ($v instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($v);
        }
        if ($v === null || $v === '' || $v === '0' || $v === 0) {
            return null;
        }
        $utc = new \DateTimeZone('UTC');
        try {
            if (is_int($v) || is_float($v) || (is_string($v) && ctype_digit($v) && strlen($v) >= 9)) {
                return (new \DateTimeImmutable('@' . (int) $v))->setTimezone($utc);
            }
            // Strings without an explicit offset are UTC (that is how Mova stores them).
            return new \DateTimeImmutable((string) $v, $utc);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function local($v): ?\DateTimeImmutable
    {
        $d = self::parse($v);
        return $d ? $d->setTimezone(self::zone()) : null;
    }

    public static function format($v, string $fallback = '—'): string
    {
        $d = self::local($v);
        return $d ? $d->format('j M Y, g:i A') : $fallback;
    }

    public static function date($v, string $fallback = '—'): string
    {
        $d = self::local($v);
        return $d ? $d->format('j M Y') : $fallback;
    }

    /** Short zone label, e.g. "WAT". */
    public static function abbr(): string
    {
        $a = (new \DateTimeImmutable('now', self::zone()))->format('T');
        return preg_match('/^[+-]\d/', $a) ? 'UTC' . $a : $a;
    }

    public static function ago($v, string $fallback = '—'): string
    {
        $d = self::parse($v);
        if (!$d) {
            return $fallback;
        }
        $s = time() - $d->getTimestamp();
        $future = $s < 0;
        $a = abs($s);
        if ($a < 45) {
            return $future ? 'in a moment' : 'just now';
        }
        if ($a < 3600) {
            $n = max(1, (int) round($a / 60));
            $t = $n . ' min';
        } elseif ($a < 86400) {
            $n = (int) round($a / 3600);
            $t = $n . ' h';
        } elseif ($a < 7 * 86400) {
            $n = (int) round($a / 86400);
            if (!$future && $n === 1) {
                return 'yesterday';
            }
            $t = $n . ' day' . ($n === 1 ? '' : 's');
        } else {
            return self::date($v, $fallback);
        }
        return $future ? 'in ' . $t : $t . ' ago';
    }

    /** Value for <input type="datetime-local"> in the site timezone. */
    public static function toLocalInput($v): string
    {
        $d = self::local($v);
        return $d ? $d->format('Y-m-d\TH:i') : '';
    }

    /** A datetime-local value typed in the site timezone → ISO-8601 UTC for storage. */
    public static function fromLocalInput(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($v, self::zone()))->setTimezone(new \DateTimeZone('UTC'))->format('c');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** ISO-8601 UTC for <time datetime>. */
    public static function iso($v): string
    {
        $d = self::parse($v);
        return $d ? $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z') : '';
    }

    public static function tag($v, string $mode = 'both', string $fallback = '—'): string
    {
        $d = self::parse($v);
        if (!$d) {
            return '<span class="hq-time is-empty">' . htmlspecialchars($fallback) . '</span>';
        }
        $abs = self::format($v);
        $text = match ($mode) {
            'rel' => self::ago($v),
            'date' => self::date($v),
            'abs' => $abs,
            default => $abs . ' · ' . self::ago($v),
        };
        return '<time class="hq-time" datetime="' . self::iso($v) . '" data-time-mode="' . htmlspecialchars($mode)
            . '" title="' . htmlspecialchars($abs . ' ' . self::abbr()) . '">' . htmlspecialchars($text) . '</time>';
    }
}
