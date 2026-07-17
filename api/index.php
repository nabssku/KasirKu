<?php
// If running on Vercel Serverless environment, redirect bootstrap cache paths to /tmp
if (getenv('VERCEL') || isset($_SERVER['VERCEL']) || isset($_ENV['VERCEL'])) {
    $_ENV['APP_SERVICES_CACHE'] = '/tmp/services.php';
    $_ENV['APP_PACKAGES_CACHE'] = '/tmp/packages.php';
    $_ENV['APP_CONFIG_CACHE'] = '/tmp/config.php';
    $_ENV['APP_ROUTES_CACHE'] = '/tmp/routes-v7.php';
    $_ENV['APP_EVENTS_CACHE'] = '/tmp/events.php';

    // Force script name to /index.php to prevent Laravel from stripping /api prefix
    // because the Vercel serverless entrypoint is physically located in /api/index.php
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

// Forward Vercel request to Laravel public index
require __DIR__ . '/../public/index.php';
