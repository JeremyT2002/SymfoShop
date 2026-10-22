<?php

// Router for PHP's built-in server: real files from public/, everything else through Symfony
// (AssetMapper assets, /media/… images and routes with dots such as /sitemap.xml).
$public = dirname(__DIR__) . '/public';
$path = (string) parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);

if ($path !== '/' && is_file($public . $path)) {
    return false;
}

// The built-in server exposes the process environment only via getenv(); Dotenv would let .env
// override it, so hand it over the way php-fpm does (DATABASE_URL, APP_ENV, … from e2e/serve.mjs).
foreach (getenv() as $name => $value) {
    $_SERVER[$name] ??= $value;
    $_ENV[$name] ??= $value;
}

$_SERVER['SCRIPT_FILENAME'] = $public . '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require $public . '/index.php';
