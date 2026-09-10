<?php
/**
 * Hello Mova — sample plugin
 */

use Mova\Plugin\PluginManager;

PluginManager::addAction('mova.boot', function () {
    // Example: log that the plugin booted (no side effects in production paths)
    if (defined('MOVA_CLI') && MOVA_CLI) {
        // silence
    }
});

PluginManager::addAction('content.rendered', function (array &$context) {
    // Example filter point for future HTML transforms
});
