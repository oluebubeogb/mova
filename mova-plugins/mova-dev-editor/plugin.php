<?php
/**
 * Mova Dev Editor — bootstrap
 * Drop into plugins/mova-dev-editor/ and activate in HQ → Plugins.
 */

declare(strict_types=1);

use Mova\Plugin\PluginManager;

$base = __DIR__;

// Lightweight autoload for this plugin only
spl_autoload_register(static function (string $class) use ($base): void {
    $prefix = 'MovaDevEditor\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = $base . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// Boot after core is ready
PluginManager::addAction('mova.boot', static function () use ($base): void {
    $config = require $base . '/config/defaults.php';

    // Allow site settings to override kill switches
    try {
        $row = \Mova\Core\Database::fetch(
            "SELECT setting_value FROM settings WHERE setting_key = 'dev_editor_disable_js'"
        );
        if ($row && $row['setting_value'] === '1') {
            $config['disable_all_custom_js'] = true;
        }
        $row = \Mova\Core\Database::fetch(
            "SELECT setting_value FROM settings WHERE setting_key = 'dev_editor_disable_css'"
        );
        if ($row && $row['setting_value'] === '1') {
            $config['disable_all_custom_css'] = true;
        }
    } catch (\Throwable $e) {
        // settings table may not exist yet
    }

    $plugin = new \MovaDevEditor\DevEditorPlugin($config, $base);
    $plugin->register();
});
