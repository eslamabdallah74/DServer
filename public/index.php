<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Normalize /server subpath for cPanel / reverse proxy compatibility
if (isset($_SERVER['REQUEST_URI'])) {
    if ($_SERVER['REQUEST_URI'] === '/server') {
        $_SERVER['REQUEST_URI'] = '/';
    } elseif (str_starts_with($_SERVER['REQUEST_URI'], '/server/')) {
        $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], 7) ?: '/';
    } elseif (str_starts_with($_SERVER['REQUEST_URI'], '/server?')) {
        $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], 7);
    }
}

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
