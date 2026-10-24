<?php
/**
 * Autosaved Quick-setup draft (one per HQ user), stored in settings.
 */

declare(strict_types=1);

namespace MovaSetupWizard;

use Mova\Core\Database;

final class WizardDraft
{
    public const FONT_PAIRS = [
        'theme' => ['label' => 'Theme default', 'heading' => '', 'body' => "'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif", 'sample' => 'Inter'],
        'modern' => ['label' => 'Modern', 'heading' => "'Plus Jakarta Sans', 'Inter', system-ui, sans-serif", 'body' => "'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif", 'sample' => 'Plus Jakarta Sans'],
        'editorial' => ['label' => 'Editorial', 'heading' => "'Fraunces', Georgia, 'Times New Roman', serif", 'body' => "'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif", 'sample' => 'Fraunces'],
        'tech' => ['label' => 'Tech', 'heading' => "'Space Grotesk', 'Inter', system-ui, sans-serif", 'body' => "'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif", 'sample' => 'Space Grotesk'],
        'friendly' => ['label' => 'Friendly', 'heading' => "'Plus Jakarta Sans', system-ui, sans-serif", 'body' => "'Plus Jakarta Sans', system-ui, -apple-system, sans-serif", 'sample' => 'Plus Jakarta Sans'],
        'classic' => ['label' => 'Classic', 'heading' => "'Fraunces', Georgia, serif", 'body' => "'Plus Jakarta Sans', system-ui, sans-serif", 'sample' => 'Fraunces'],
    ];

    public const FIELDS = ['step', 'pack', 'kit', 'site_name', 'tagline', 'about', 'city', 'email', 'phone', 'address', 'logo_url',
        'primary', 'accent', 'secondary', 'font_pair', 'theme', 'use_ai', 'status', 'mode'];

    public static function defaults(): array
    {
        $ai = false;
        try {
            $ai = class_exists(\Mova\AI\AiAssistService::class) && (new \Mova\AI\AiAssistService())->isConfigured();
        } catch (\Throwable $e) {
        }
        $name = '';
        try {
            $name = (string) (\Mova\Theme\DesignConfig::setting('site_name', '') ?? '');
        } catch (\Throwable $e) {
        }
        if (in_array(strtolower($name), ['mova', 'my site', 'mova cms'], true)) {
            $name = '';
        }
        return ['step' => 1, 'pack' => '', 'kit' => '', 'site_name' => $name, 'tagline' => '', 'about' => '', 'city' => '', 'email' => '',
            'phone' => '', 'address' => '', 'logo_url' => '', 'primary' => '', 'accent' => '', 'secondary' => '', 'font_pair' => 'theme',
            'theme' => 'presence', 'use_ai' => $ai, 'status' => 'published', 'copy' => [], 'ai' => []];
    }

    /** Stored draft merged over defaults. */
    public static function load(int $uid): array
    {
        return array_merge(self::defaults(), self::get($uid));
    }

    private static function key(int $uid): string
    {
        return 'setup_wizard_draft_' . $uid;
    }

    public static function get(int $uid): array
    {
        $row = Database::fetch('SELECT setting_value FROM settings WHERE setting_key = :k', ['k' => self::key($uid)]);
        $d = $row ? json_decode((string) $row['setting_value'], true) : null;
        return is_array($d) ? $d : [];
    }

    public static function save(int $uid, array $draft): array
    {
        $draft['updated_at'] = date('c');
        $json = json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $k = self::key($uid);
        $exists = Database::fetch('SELECT setting_key FROM settings WHERE setting_key = :k', ['k' => $k]);
        if ($exists) {
            Database::update('settings', ['setting_value' => $json], 'setting_key = :k', ['k' => $k]);
        } else {
            Database::insert('settings', ['setting_key' => $k, 'setting_value' => $json]);
        }
        return $draft;
    }

    /** Merge whitelisted, sanitised fields from user input. */
    public static function merge(array $draft, array $in): array
    {
        foreach (self::FIELDS as $f) {
            if (!array_key_exists($f, $in)) {
                continue;
            }
            $v = $in[$f];
            if ($f === 'use_ai') {
                $draft[$f] = in_array((string) $v, ['1', 'true', 'on'], true);
                continue;
            }
            if ($f === 'step') {
                $draft[$f] = max(1, min(7, (int) $v));
                continue;
            }
            $v = trim((string) $v);
            if (in_array($f, ['primary', 'accent', 'secondary'], true)) {
                $v = SetupWizardService::normalizeHex($v) ?? ($draft[$f] ?? '');
            } elseif ($f === 'font_pair') {
                $v = isset(self::FONT_PAIRS[$v]) ? $v : 'theme';
            } elseif ($f === 'logo_url') {
                $v = preg_match('~^(/[A-Za-z0-9._/\-]+|https?://[^\s"<>]+)$~', $v) ? $v : '';
            } elseif ($f === 'theme' || $f === 'kit' || $f === 'pack') {
                $v = preg_replace('/[^a-z0-9_\-]/', '', strtolower($v)) ?? '';
            } else {
                $v = mb_substr(strip_tags($v), 0, $f === 'about' ? 1200 : 200);
            }
            $draft[$f] = $v;
        }
        return $draft;
    }

    /** Token vars for KitRenderer. */
    public static function vars(array $d): array
    {
        return KitRenderer::vars($d, (string) ($d['pack'] ?? 'generic'));
    }

    /** Design token overrides for the draft (colours light+dark, fonts). */
    public static function tokens(array $d, ?array $kit = null): array
    {
        $pal = (array) ($kit['palette'] ?? []);
        $p = (string) (($d['primary'] ?? '') ?: ($pal['primary'] ?? '#2563eb'));
        $a = (string) (($d['accent'] ?? '') ?: ($pal['accent'] ?? '#7c3aed'));
        $s = (string) (($d['secondary'] ?? '') ?: ($pal['secondary'] ?? '#64748b'));
        $t = ['colors' => SetupWizardService::lightColors($p, $a, $s), 'colors_dark' => SetupWizardService::darkColors($p, $a, $s)];
        $fp = self::FONT_PAIRS[(string) ($d['font_pair'] ?? 'theme')] ?? self::FONT_PAIRS['theme'];
        $t['typography'] = ['font_sans' => $fp['body'], 'font_heading' => $fp['heading']];
        return $t;
    }
}
