<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class CambiarConexionBaseDatos
{
    public function handle(Request $request, Closure $next)
    {
        $conexionPrincipal = 'mariadb';

        try {
            DB::purge($conexionPrincipal);
            DB::connection($conexionPrincipal)->getPdo();

            DB::setDefaultConnection($conexionPrincipal);
            config(['database.default' => $conexionPrincipal]);

            $request->attributes->set('conexion_actual', 'MariaDB');
        } catch (Throwable $e) {
            DB::purge('sqlite_backup');
            DB::connection('sqlite_backup')->getPdo();

            DB::setDefaultConnection('sqlite_backup');
            config(['database.default' => 'sqlite_backup']);

            $request->attributes->set('conexion_actual', 'SQLite');
        }

        return $next($request);
    }
}