<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CarroController;
use App\Http\Controllers\ChoferController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\TrayectoController;

Route::post('auth/login', [LoginController::class, 'login']);

Route::post('trayectos/{id}/ubicacion', [TrayectoController::class, 'registrarUbicacion'])
    ->name('trayecto.ubicacion')
    ->middleware('signed');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [LoginController::class, 'logout']);
    Route::get('user', function (Request $request) {
        return response()->json($request->user());
    });

    // Dashboard: antes no tenía ruta, por eso el front tenía que
    // reconstruir las gráficas él solo (con datos inventados).
    Route::get('inicio', [InicioController::class, 'inicio']);

    // Módulos solo-administración
    Route::middleware('es.admin')->group(function () {
        Route::get('empleados', [EmpleadoController::class, 'inicio']);
        Route::post('empleados', [EmpleadoController::class, 'guardar']);
        Route::get('empleados/{id}', [EmpleadoController::class, 'editar']);
        Route::match(['put', 'patch'], 'empleados/{id}', [EmpleadoController::class, 'actualizar']);
        Route::delete('empleados/{id}', [EmpleadoController::class, 'eliminar']);

        Route::get('sucursales', [SucursalController::class, 'inicio']);
        Route::post('sucursales', [SucursalController::class, 'guardar']);
        Route::get('sucursales/{id}', [SucursalController::class, 'editar']);
        Route::match(['put', 'patch'], 'sucursales/{id}', [SucursalController::class, 'actualizar']);
        Route::delete('sucursales/{id}', [SucursalController::class, 'eliminar']);

        Route::get('productos', [ProductoController::class, 'inicio']);
        Route::post('productos', [ProductoController::class, 'guardar']);
        Route::get('productos/{id}', [ProductoController::class, 'editar']);
        Route::match(['put', 'patch'], 'productos/{id}', [ProductoController::class, 'actualizar']);
        Route::delete('productos/{id}', [ProductoController::class, 'eliminar']);

        Route::get('marcas', [MarcaController::class, 'inicio']);
        Route::post('marcas', [MarcaController::class, 'guardar']);
        Route::get('marcas/formulario', [MarcaController::class, 'formulario']);
        Route::post('marcas/quick', [MarcaController::class, 'guardarRapido']);
        Route::get('marcas/{id}', [MarcaController::class, 'editar']);
        Route::match(['put', 'patch'], 'marcas/{id}', [MarcaController::class, 'actualizar']);
        Route::delete('marcas/{id}', [MarcaController::class, 'eliminar']);

        Route::get('proveedores', [ProveedorController::class, 'inicio']);
        Route::post('proveedores', [ProveedorController::class, 'guardar']);
        Route::get('proveedores/{id}', [ProveedorController::class, 'editar']);
        Route::match(['put', 'patch'], 'proveedores/{id}', [ProveedorController::class, 'actualizar']);
        Route::delete('proveedores/{id}', [ProveedorController::class, 'eliminar']);

        Route::get('carros', [CarroController::class, 'inicio']);
        Route::get('carros/formulario', [CarroController::class, 'formulario']);
        Route::post('carros', [CarroController::class, 'guardar']);
        Route::get('carros/{id}', [CarroController::class, 'editar']);
        Route::match(['put', 'patch'], 'carros/{id}', [CarroController::class, 'actualizar']);
        Route::delete('carros/{id}', [CarroController::class, 'eliminar']);

        Route::get('choferes', [ChoferController::class, 'inicio']);
        Route::post('choferes', [ChoferController::class, 'guardar']);
        Route::get('choferes/{id}', [ChoferController::class, 'editar']);
        Route::match(['put', 'patch'], 'choferes/{id}', [ChoferController::class, 'actualizar']);
        Route::delete('choferes/{id}', [ChoferController::class, 'eliminar']);
    });

    // Inventarios: todo empleado autenticado entra a inicio() (la propia
    // API filtra por su sucursal si no es matriz/admin); guardar/editar/
    // eliminar SÍ están bloqueados dentro del controlador para quien no
    // sea matriz/admin (puedeAsignar()).
    Route::get('inventarios', [InventarioController::class, 'inicio']);
    Route::post('inventarios', [InventarioController::class, 'guardar']);
    // ¡orden importa! antes de {id}, si no lo intercepta como si "datos-formulario" fuera un id
    Route::get('inventarios/datos-formulario', [InventarioController::class, 'datosFormulario']);
    // era 'editar' — ese método no existe en InventarioController, tronaba
    Route::get('inventarios/{id}', [InventarioController::class, 'mostrar']);
    Route::match(['put', 'patch'], 'inventarios/{id}', [InventarioController::class, 'actualizar']);
    Route::delete('inventarios/{id}', [InventarioController::class, 'eliminar']);

    // Pedidos (sin cambios)
    Route::get('pedidos', [PedidoController::class, 'inicio']);
    Route::post('pedidos', [PedidoController::class, 'guardar']);
    Route::get('pedidos/formulario', [PedidoController::class, 'formulario']);
    Route::get('pedidos/pending', [PedidoController::class, 'pendientes']);
    Route::get('pedidos/{id}', [PedidoController::class, 'editar']);
    Route::match(['put', 'patch'], 'pedidos/{id}', [PedidoController::class, 'actualizar']);
    Route::delete('pedidos/{id}', [PedidoController::class, 'eliminar']);
    Route::get('pedidos/{id}/assign', [PedidoController::class, 'aceptarFormulario']);
    Route::post('pedidos/{id}/accept', [PedidoController::class, 'aceptar']);
    Route::post('pedidos/{id}/cancel', [PedidoController::class, 'eliminar']);
    Route::get('pedidos/{id}/pdf', [PedidoController::class, 'pdf']);

    Route::get('trayectos', [TrayectoController::class, 'listado']);
    Route::get('trayectos/{id}', [TrayectoController::class, 'editar']);
    Route::match(['put', 'patch'], 'trayectos/{id}', [TrayectoController::class, 'actualizar']);
    Route::delete('trayectos/{id}', [TrayectoController::class, 'eliminar']);
    Route::post('trayectos/{id}/confirm-arrival', [TrayectoController::class, 'confirmarLlegada']);
    Route::get('trayectos/{id}/share', [TrayectoController::class, 'compartirUbicacion']);
    Route::get('trayectos/fleet-locations', [TrayectoController::class, 'ubicacionesFlota']);
});