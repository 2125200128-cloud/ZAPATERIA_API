<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Empleado;
use Symfony\Component\HttpFoundation\Response;

class EsAdministrador
{
    // Módulos de solo-administración (Empleados, Productos, Proveedores,
    // Sucursales, Carros, Choferes, Marcas): el sidebar ya los oculta para
    // quien no es Administrador, pero eso es cosmético — esto es el
    // bloqueo real, por si alguien escribe la URL directamente.
    public function handle(Request $request, Closure $next): Response
    {
        $empleado = Empleado::auth();

        if (!$empleado || !$empleado->esAdministrador()) {
            abort(403, 'Solo un administrador puede acceder a esta sección.');
        }

        return $next($request);
    }
}
