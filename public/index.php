<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Este proyecto y zapateriagarcias corren bajo el mismo Apache/XAMPP y
// comparten workers de mod_php. Sin esto, un .env "se pega" al proceso
// (putenv) y se filtra al otro proyecto en la siguiente petición que le
// toque ese mismo worker — rompía la firma de los links de ubicación
// porque terminaba usando el APP_KEY equivocado.
\Illuminate\Support\Env::disablePutenv();

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
