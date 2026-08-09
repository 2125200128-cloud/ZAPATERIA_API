<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use App\Models\Empleado;
use App\Models\Trayecto;
use App\Models\TrayectoUbicacion;
use App\Models\Chofer;
use App\Models\Carro;
use App\Models\Pedido;
use App\Models\Inventario;

class TrayectoController extends Controller
{
    // Administrador y el Encargado de la matriz asignan/gestionan cualquier
    // trayecto; un Encargado de sucursal solo ve los suyos.
    private function puedeAsignar(): bool
    {
        $empleado = Empleado::auth();
        return $empleado !== null && ($empleado->esAdministrador() || $empleado->esMatriz());
    }

    public function listado()
    {
        $query = Trayecto::with(['chofer', 'carro', 'pedido.empleado.sucursales']);

        if (!$this->puedeAsignar()) {
            $miSucursal = Empleado::auth()?->miSucursal();
            $miEmpleadoId = $miSucursal?->empleado_id ?? 0;
            $query->whereHas('pedido', function ($q) use ($miEmpleadoId) {
                $q->where('empleado_id', $miEmpleadoId);
            });
        }

        $trayectos = $query->get();

        return response()->json(compact('trayectos'));
    }

    // Un chofer/carro/pedido está "ocupado" si tiene un trayecto cuyo
    // estatus no sea Entregado ni Cancelado. $excluirTrayectoId se usa al
    // editar, para que el propio trayecto no se cuente como "ocupándose a
    // sí mismo" y desaparezca de su propio formulario. El criterio de
    // chofer/carro vive en Chofer::scopeDisponible()/Carro::scopeDisponible()
    // para poder reusarlo también desde PedidoController.
    private function disponibles($excluirTrayectoId = null)
    {
        return [
            'choferes' => Chofer::disponible($excluirTrayectoId)->get(),
            'carros' => Carro::disponible($excluirTrayectoId)->get(),
            'pedidos' => Pedido::whereDoesntHave('trayectos', function ($query) use ($excluirTrayectoId) {
                $query->whereNotIn('estatus', ['Entregado', 'Cancelado']);
                if ($excluirTrayectoId) {
                    $query->where('id', '!=', $excluirTrayectoId);
                }
            })->with('empleado.sucursales')->get(),
        ];
    }

    public function editar(Request $request)
    {
        if (!$this->puedeAsignar()) {
            abort(403, 'Solo la matriz puede editar trayectos.');
        }

        $id = $request->route('id');
        $trayecto = Trayecto::find($id);
        if (!$trayecto) {
            return response()->json(['message' => 'Trayecto no encontrado'], 404);
        }

        $disponibles = $this->disponibles($trayecto->id);
        // Aseguramos que el chofer/carro/pedido actuales del trayecto sigan
        // apareciendo en su propio formulario aunque ya "estén ocupados"
        // (por él mismo).
        if (!$disponibles['choferes']->contains('id', $trayecto->chofer_id) && $trayecto->chofer) {
            $disponibles['choferes']->push($trayecto->chofer);
        }
        if (!$disponibles['carros']->contains('id', $trayecto->carro_id) && $trayecto->carro) {
            $disponibles['carros']->push($trayecto->carro);
        }
        if (!$disponibles['pedidos']->contains('id', $trayecto->pedido_id) && $trayecto->pedido) {
            $disponibles['pedidos']->push($trayecto->pedido);
        }

        return response()->json(array_merge($disponibles, ['trayecto' => $trayecto]));
    }

    public function actualizar(Request $request)
    {
        if (!$this->puedeAsignar()) {
            abort(403, 'Solo la matriz puede editar trayectos.');
        }

        $id = $request->route('id');
        $trayecto = Trayecto::find($id);
        if (!$trayecto) {
            return response()->json(['message' => 'Trayecto no encontrado'], 404);
        }

        $choferId = $request->input('chofer_id');
        $carroId = $request->input('carro_id');
        $pedidoId = $request->input('pedido_id');

        $disponibles = $this->disponibles($trayecto->id);
        if (!$disponibles['choferes']->contains('id', $choferId)
            || !$disponibles['carros']->contains('id', $carroId)
            || !$disponibles['pedidos']->contains('id', $pedidoId)) {
            return response()->json(['message' => 'El chofer, carro o pedido elegido ya no está disponible.'], 422);
        }

        $estatusAnterior = $trayecto->estatus;

        $trayecto->chofer_id = $choferId;
        $trayecto->carro_id = $carroId;
        $trayecto->pedido_id = $pedidoId;
        $trayecto->estatus = $request->input('estatus');
        $trayecto->descripcion_ruta = $request->input('descripcion_ruta');
        $trayecto->save();

        // Recién se confirma la entrega (y no lo estaba ya, para no volver a
        // sumar si se guarda otra vez sin cambiar el estatus): la sucursal
        // que pidió recibe el stock que llevaba el trayecto.
        if ($trayecto->estatus === 'Entregado' && $estatusAnterior !== 'Entregado') {
            $this->reabastecerSucursalDestino($trayecto);
        }

        return response()->json(['message' => 'Trayecto actualizado']);
    }

    // El encargado de la sucursal destino (o Administrador) confirma que el
    // pedido llegó — solo tiene sentido desde 'En ruta'.
    public function confirmarLlegada(Request $request)
    {
        $id = $request->route('id');
        $trayecto = Trayecto::with('pedido.empleado.sucursales')->find($id);
        if (!$trayecto) {
            return response()->json(['message' => 'Trayecto no encontrado'], 404);
        }

        if ($trayecto->estatus !== 'En ruta') {
            return response()->json(['message' => 'Este trayecto todavía no está en ruta.'], 404);
        }

        $empleado = Empleado::auth();
        if (!$empleado || !$empleado->esAdministrador()) {
            $miSucursal = $empleado?->miSucursal();
            $sucursalDestino = optional($trayecto->pedido?->empleado)->sucursales->first();
            if (!$miSucursal || !$sucursalDestino || $miSucursal->id !== $sucursalDestino->id) {
                abort(403, 'Solo el encargado de la sucursal destino puede confirmar la llegada.');
            }
        }

        $trayecto->estatus = 'Entregado';
        $trayecto->save();

        $this->reabastecerSucursalDestino($trayecto);

        if ($trayecto->pedido) {
            $trayecto->pedido->estatus = 'Realizado';
            $trayecto->pedido->save();
        }

        return response()->json(['message' => 'Entrega confirmada. Se actualizó el inventario de tu sucursal.']);
    }

    private function reabastecerSucursalDestino(Trayecto $trayecto)
    {
        $trayecto->load(['pedido.empleado.sucursales', 'pedido.detallePedidos']);
        $pedido = $trayecto->pedido;
        $sucursalDestino = $pedido ? optional($pedido->empleado)->sucursales->first() : null;

        if (!$pedido || !$sucursalDestino) {
            return;
        }

        DB::transaction(function () use ($sucursalDestino, $pedido) {
            foreach ($pedido->detallePedidos as $detalle) {
                $inventario = Inventario::firstOrCreate(
                    ['sucursal_id' => $sucursalDestino->id, 'producto_id' => $detalle->producto_id],
                    ['stock' => 0, 'estatus' => 'Activo']
                );
                $inventario->increment('stock', $detalle->cantidad_solicitada);
            }
        });
    }

    public function eliminar(Request $request)
    {
        if (!$this->puedeAsignar()) {
            abort(403, 'Solo la matriz puede cancelar trayectos.');
        }

        $id = $request->route('id');
        $trayecto = Trayecto::find($id);
        if (!$trayecto) {
            return response()->json(['message' => 'Trayecto no encontrado'], 404);
        }
        $trayecto->estatus = 'Cancelado';
        $trayecto->save();
        return response()->json(['message' => 'Trayecto cancelado']);
    }

    public function mostrar(Request $request)
    {
        if (!$this->puedeAsignar()) {
            abort(403, 'Solo la matriz puede cancelar trayectos.');
        }

        $id = $request->route('id');
        $trayecto = Trayecto::find($id);
        if (!$trayecto) {
            return response()->json(['message' => 'Trayecto no encontrado'], 404);
        }
        return response()->json(['trayecto' => $trayecto]);
    }

    public function flota()
    {
        return response()->json(['message' => 'This endpoint is API only']);
    }

    public function ubicacionesFlota()
    {
        $query = Trayecto::with(['chofer', 'pedido.empleado.sucursales', 'ubicacionActual'])
            ->whereNotIn('estatus', ['Entregado', 'Cancelado']);

        // Igual que en listado(): una sucursal solo ve el trayecto de sus
        // propios pedidos en el mapa, no el de las demás.
        if (!$this->puedeAsignar()) {
            $miSucursal = Empleado::auth()?->miSucursal();
            $miEmpleadoId = $miSucursal?->empleado_id ?? 0;
            $query->whereHas('pedido', function ($q) use ($miEmpleadoId) {
                $q->where('empleado_id', $miEmpleadoId);
            });
        }

        $trayectos = $query->get();

        $municipios = config('ubicaciones.municipios');

        $datos = $trayectos->map(function (Trayecto $trayecto) use ($municipios) {
            $sucursal = null;
            if ($trayecto->pedido && $trayecto->pedido->empleado) {
                $sucursal = $trayecto->pedido->empleado->sucursales->first();
            }

            $destino = null;
            if ($sucursal && isset($municipios[$sucursal->municipio])) {
                [$lat, $lng] = $municipios[$sucursal->municipio];
                $destino = [
                    'sucursal' => $sucursal->nombre,
                    'municipio' => $sucursal->municipio,
                    'lat' => $lat,
                    'lng' => $lng,
                ];
            }

            $posicion = null;
            if ($trayecto->ubicacionActual) {
                $posicion = [
                    'lat' => (float) $trayecto->ubicacionActual->latitud,
                    'lng' => (float) $trayecto->ubicacionActual->longitud,
                    'actualizado_en' => $trayecto->ubicacionActual->registrado_en,
                ];
            }

            return [
                'trayecto_id' => $trayecto->id,
                'estatus' => $trayecto->estatus,
                'chofer' => $trayecto->chofer ? trim($trayecto->chofer->nombre . ' ' . $trayecto->chofer->apellido) : null,
                'pedido_id' => $trayecto->pedido_id,
                'destino' => $destino,
                'posicion' => $posicion,
            ];
        });

        return response()->json($datos->values());
    }

    public function compartirUbicacion(Request $request)
    {
        $id = $request->route('id');
        $trayecto = Trayecto::find($id);

        if (!$trayecto) {
            abort(404, 'Trayecto no encontrado');
        }

        // El chofer nunca ve/usa el id "a pelo" — el botón de la vista
        // manda a esta URL ya firmada, generada aquí mismo.
        $urlUbicacion = URL::temporarySignedRoute(
            'trayecto.ubicacion',
            now()->addHours(24),
            ['id' => $trayecto->id]
        );

        return response()->json([
            'trayecto' => $trayecto,
            'urlUbicacion' => $urlUbicacion,
            'yaTermino' => in_array($trayecto->estatus, ['Entregado', 'Cancelado']),
        ]);
    }

    public function registrarUbicacion(Request $request)
    {
        $id = $request->route('id');
        $trayecto = Trayecto::find($id);

        if (!$trayecto) {
            return response()->json(['error' => 'Trayecto no encontrado'], 404);
        }

        $validado = $request->validate([
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
        ]);

        TrayectoUbicacion::create([
            'trayecto_id' => $trayecto->id,
            'latitud' => $validado['latitud'],
            'longitud' => $validado['longitud'],
        ]);

        // El primer ping de ubicación del chofer es lo que marca que ya
        // arrancó la entrega.
        if ($trayecto->estatus === 'Aceptado') {
            $trayecto->estatus = 'En ruta';
            $trayecto->save();
        }

        return response()->json(['ok' => true]);
    }
}
