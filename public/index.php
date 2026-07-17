<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// If running on Vercel Serverless environment, redirect bootstrap cache paths to /tmp
if (getenv('VERCEL') || isset($_SERVER['VERCEL']) || isset($_ENV['VERCEL'])) {
    $_ENV['APP_SERVICES_CACHE'] = '/tmp/services.php';
    $_ENV['APP_PACKAGES_CACHE'] = '/tmp/packages.php';
    $_ENV['APP_CONFIG_CACHE'] = '/tmp/config.php';
    $_ENV['APP_ROUTES_CACHE'] = '/tmp/routes-v7.php';
    $_ENV['APP_EVENTS_CACHE'] = '/tmp/events.php';
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
