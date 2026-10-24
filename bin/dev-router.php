<?php
// Router for the PHP built-in server: php -S 127.0.0.1:8080 -t public bin/dev-router.php
// (used by bin/capture.php; also handy for local development).
$root = dirname(__DIR__);
$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = $root . '/public' . $uri;
if ($uri !== '/' && is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}
if (preg_match('#^/hq(/|$)#', $uri)) {
    $_SERVER['SCRIPT_NAME'] = '/hq/index.php';
    chdir($root . '/public/hq');
    require $root . '/public/hq/index.php';
    return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
chdir($root . '/public');
require $root . '/public/index.php';
