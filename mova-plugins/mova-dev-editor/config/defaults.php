<?php
/**
 * Mova Dev Editor — default configuration
 * Validation currently disabled for testing.
 */
return [
    'allowed_roles' => ['owner', 'administrator'],
    'block_publish_on_errors' => false,
    'disable_all_custom_js' => false,
    'disable_all_custom_css' => false,
    // Make @media (prefers-color-scheme) blocks follow the site's light/dark toggle (html[data-theme]).
    'sync_color_scheme' => true,
    // Empty = no pattern blocking
    'dangerous_patterns' => [],
    'max_html_bytes' => 512000,
    'max_css_bytes'  => 128000,
    'max_js_bytes'   => 128000,
    'monaco_cdn' => '/assets/vendor/monaco/vs',
];
