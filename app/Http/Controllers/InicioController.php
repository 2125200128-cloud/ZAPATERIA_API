<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Sucursal;
use App\Models\Producto;
use App\Models\Pedido;
use App\Models\Chofer;
use App\Models\Detalle_pedido;
use App\Models\Inventario;
use App\Models\Trayecto;

class InicioController extends Controller
{
    public function inicio()
    {
        $empleado = Empleado::auth();
        if (!$empleado) {
            return response()->json(['message' => 'Redirected']);
        }

        $esMatrizOAdmin = $empleado->esAdministrador() || $empleado->esMatriz();

        if ($esMatrizOAdmin) {
            return $this->inicioMatriz();
        }

        return $this->inicioSucursal($empleado);
    }

    private function inicioMatriz()
    {
        $esMatrizOAdmin = true;

        $kpis = [
            'sucursales' => Sucursal::where('estatus', 'Activo')->count(),
            'pedidosPendientes' => Pedido::where('estatus', 'Pendiente')->count(),
            'productos' => Producto::where('estatus', 'Activo')->count(),
            'choferes' => Chofer::where('estatus', 'Activo')->count(),
        ];

        $grafico1Labels = Sucursal::all()->map(fn ($sucursal) => preg_replace('/^Sucursal\s+/i', '', $sucursal->nombre));
        $grafico1Datos = Sucursal::all()->map(fn ($sucursal) => Pedido::where('empleado_id', $sucursal->empleado_id)->count());

        $topProductos = Detalle_pedido::selectRaw('producto_id, SUM(cantidad_solicitada) as total')
            ->groupBy('producto_id')
            ->orderByDesc('total')
            ->take(5)
            ->with('producto')
            ->get()
            ->filter(fn ($fila) => $fila->producto)
            ->map(fn ($fila) => ['nombre' => $fila->producto->nombre, 'total' => (int) $fila->total]);

        $pedidosPorEstatus = collect(['Pendiente', 'Realizado', 'Cancelado'])->map(function ($estatus) {
            return [
                'estatus' => $estatus,
                'total' => Pedido::where('estatus', $estatus)->count(),
            ];
        });

        $trayectoActivoResumen = null;

        return response()->json(compact('esMatrizOAdmin', 'kpis', 'grafico1Labels', 'grafico1Datos', 'topProductos', 'pedidosPorEstatus', 'trayectoActivoResumen'));
    }

    private function inicioSucursal(Empleado $empleado)
    {
        $esMatrizOAdmin = false;

        $miSucursal = $empleado->miSucursal();
        $miEmpleadoId = optional($miSucursal)->empleado_id ?? 0;

        $kpis = [
            'pendientes' => Pedido::where('empleado_id', $miEmpleadoId)->where('estatus', 'Pendiente')->count(),
            'enCamino' => Pedido::where('empleado_id', $miEmpleadoId)
                ->whereHas('trayectos', fn ($q) => $q->whereIn('estatus', ['Aceptado', 'En ruta']))
                ->count(),
            'entregados' => Pedido::where('empleado_id', $miEmpleadoId)->where('estatus', 'Realizado')->count(),
            // Antes era Producto::where('estatus','Activo')->count() — el
            // tamaño del catálogo global, no el inventario real de esta
            // sucursal. Ahora sí es "mi inventario".
            'inventarioTotal' => Inventario::where('sucursal_id', $miSucursal?->id ?? 0)->sum('stock'),
        ];

        $pedidosPorMes = Pedido::where('empleado_id', $miEmpleadoId)
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as mes, COUNT(*) as total")
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();
        $grafico1Labels = $pedidosPorMes->pluck('mes');
        $grafico1Datos = $pedidosPorMes->pluck('total');

        $topProductos = Detalle_pedido::selectRaw('producto_id, SUM(cantidad_solicitada) as total')
            ->whereHas('pedido', fn ($q) => $q->where('empleado_id', $miEmpleadoId))
            ->groupBy('producto_id')
            ->orderByDesc('total')
            ->take(5)
            ->with('producto')
            ->get()
            ->filter(fn ($fila) => $fila->producto)
            ->map(fn ($fila) => ['nombre' => $fila->producto->nombre, 'total' => (int) $fila->total]);

        $pedidosPorEstatus = collect(['Pendiente', 'Realizado', 'Cancelado'])->map(function ($estatus) use ($miEmpleadoId) {
            return [
                'estatus' => $estatus,
                'total' => Pedido::where('empleado_id', $miEmpleadoId)->where('estatus', $estatus)->count(),
            ];
        });

        // El trayecto en curso más reciente de MI sucursal, para verlo de
        // un vistazo sin entrar al mapa de flota.
        $trayectoActivo = Trayecto::whereHas('pedido', fn ($q) => $q->where('empleado_id', $miEmpleadoId))
            ->whereIn('estatus', ['Aceptado', 'En ruta'])
            ->with('chofer')
            ->latest('fecha_envio')
            ->first();

        $trayectoActivoResumen = $trayectoActivo ? [
            'id' => $trayectoActivo->id,
            'pedido_id' => $trayectoActivo->pedido_id,
            'estatus' => $trayectoActivo->estatus,
            'chofer' => trim(($trayectoActivo->chofer->nombre ?? '') . ' ' . ($trayectoActivo->chofer->apellido ?? '')),
            'descripcion_ruta' => $trayectoActivo->descripcion_ruta,
        ] : null;

        return response()->json(compact('esMatrizOAdmin', 'kpis', 'grafico1Labels', 'grafico1Datos', 'topProductos', 'pedidosPorEstatus', 'trayectoActivoResumen'));
    }

    public function login()
    {
        return response()->json(['message' => 'This endpoint is API only']);
    }
}