<?php
use App\Http\Middleware\CambiarConexionBaseDatos;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EsAdministrador::class,
            'cambiar_conexion' => CambiarConexionBaseDatos::class,
            'es.admin' => \App\Http\Middleware\EsAdministrador::class,
        ]);

        // Esto hace que se ejecute automáticamente en cada petición
        $middleware->append(CambiarConexionBaseDatos::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
