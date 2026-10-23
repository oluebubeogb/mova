<?php
/**
 * Global theme helpers (loaded by Mova\Theme\SiteFrame).
 * mova_setting() honours SiteFrame preview overrides (theme gallery / setup wizard review).
 */
if (!function_exists('mova_setting')) {
    function mova_setting(string $key, $default = null)
    {
        $ov = \Mova\Theme\SiteFrame::overrides();
        if (array_key_exists($key, $ov)) {
            return $ov[$key];
        }
        static $cache = [];
        if (!array_key_exists($key, $cache)) {
            try {
                $row = \Mova\Core\Database::fetch("SELECT setting_value FROM settings WHERE setting_key = :k", ['k' => $key]);
                $cache[$key] = $row['setting_value'] ?? null;
            } catch (\Throwable $e) {
                $cache[$key] = null;
            }
        }
        return $cache[$key] ?? $default;
    }
}
