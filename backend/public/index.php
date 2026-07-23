<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

header_remove('X-Powered-By');

// Determine whether the application is in maintenance mode.
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader.
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request.
/**
 * Resolve the bootstrapped Laravel application.
 *
 * @var Application $app
 */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
