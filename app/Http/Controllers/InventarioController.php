<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Empleado;
use App\Models\Inventario;
use App\Models\Sucursal;
use App\Models\Producto;

class InventarioController extends Controller
{
    private function puedeAsignar(): bool
    {
        $empleado = Empleado::auth();
        return $empleado !== null && ($empleado->esAdministrador() || $empleado->esMatriz());
    }

    // Cada quien ve solo el inventario de su propia sucursal — incluida la
    // matriz, que ya no ve de un jalón el de las 6 sucursales, solo el suyo
    // (Matriz Guadalajara).
    public function inicio()
    {
        $query = Inventario::with(['sucursal', 'producto.marca']);

        if ($this->puedeAsignar()) {
            $matriz = Sucursal::matriz();
            $query->where('sucursal_id', $matriz?->id ?? 0);
        } else {
            $miSucursal = Empleado::auth()?->miSucursal();
            $query->where('sucursal_id', $miSucursal?->id ?? 0);
        }

        $inventarios = $query->get();
        return response()->json(['inventarios' => $inventarios], 200);
    }

    // Este método te será súper útil para que el Admin pida las sucursales y productos
    public function datosFormulario()
    {
        if (!$this->puedeAsignar()) {
            return response()->json(['message' => 'El inventario solo lo gestiona la matriz.'], 403);
        }

        $sucursales = Sucursal::all();
        $productos = Producto::with('marca')->get();

        return response()->json([
            'sucursales' => $sucursales, 
            'productos' => $productos
        ], 200);
    }

    public function mostrar(Request $request)
    {
        if (!$this->puedeAsignar()) {
            return response()->json(['message' => 'El inventario solo lo gestiona la matriz.'], 403);
        }

        $id = $request->route('id');
        $inventario = Inventario::find($id);

        if (!$inventario) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }
        
        return response()->json(['inventario' => $inventario], 200);
    }

    public function guardar(Request $request)
    {
        if (!$this->puedeAsignar()) {
            return response()->json(['message' => 'El inventario solo lo gestiona la matriz.'], 403);
        }

        // El inventario que se captura a mano es siempre de la matriz — las
        // sucursales solo reciben stock por pedidos entregados (ver
        // TrayectoController::reabastecerSucursalDestino), nunca se les
        // agrega manualmente desde aquí.
        $matriz = Sucursal::matriz();
        if (!$matriz) {
            return response()->json(['message' => 'No se encontró la sucursal matriz.'], 422);
        }

        $inventario = new Inventario();
        $inventario->sucursal_id = $matriz->id;
        $inventario->producto_id = $request->input('producto_id');
        $inventario->stock = $request->input('stock');
        $inventario->estatus = $request->input('estatus');
        $inventario->save();

        return response()->json(['message' => 'Registro guardado exitosamente.'], 201);
    }

    // Suma stock a varias tallas de la matriz de un jalón (ej. llegó un
    // envío con varias tallas del mismo producto) — a diferencia de
    // actualizar(), esto SUMA sobre el stock que ya había, no lo reemplaza,
    // y crea el registro si esa talla todavía no tenía inventario.
    public function reabastecer(Request $request)
    {
        if (!$this->puedeAsignar()) {
            return response()->json(['message' => 'El inventario solo lo gestiona la matriz.'], 403);
        }

        $matriz = Sucursal::matriz();
        if (!$matriz) {
            return response()->json(['message' => 'No se encontró la sucursal matriz.'], 422);
        }

        $productoIds = $request->input('producto_id', []);
        $cantidades = $request->input('cantidad', []);

        $actualizados = 0;
        DB::transaction(function () use ($matriz, $productoIds, $cantidades, &$actualizados) {
            foreach ($productoIds as $i => $productoId) {
                $cantidad = (int) ($cantidades[$i] ?? 0);
                if (!$productoId || $cantidad <= 0) {
                    continue;
                }

                $inventario = Inventario::firstOrCreate(
                    ['sucursal_id' => $matriz->id, 'producto_id' => $productoId],
                    ['stock' => 0, 'estatus' => 'Activo']
                );
                $inventario->increment('stock', $cantidad);
                $actualizados++;
            }
        });

        if ($actualizados === 0) {
            return response()->json(['message' => 'No se capturó ninguna talla con cantidad válida.'], 422);
        }

        return response()->json(['message' => "Stock actualizado en {$actualizados} talla(s)."]);
    }

    public function actualizar(Request $request)
    {
        if (!$this->puedeAsignar()) {
            return response()->json(['message' => 'El inventario solo lo gestiona la matriz.'], 403);
        }

        $id = $request->route('id');
        $inventario = Inventario::find($id);

        if (!$inventario) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }

        // Sin sucursal_id aquí a propósito: no se permite reasignar de
        // sucursal un registro existente (evita que uno creado por una
        // entrega real termine apareciendo como si fuera de la matriz).
        $inventario->producto_id = $request->input('producto_id');
        $inventario->stock = $request->input('stock');
        $inventario->estatus = $request->input('estatus');
        $inventario->save();

        return response()->json(['message' => 'Registro actualizado'], 200);
    }

    public function eliminar(Request $request)
    {
        if (!$this->puedeAsignar()) {
            return response()->json(['message' => 'El inventario solo lo gestiona la matriz.'], 403);
        }

        $id = $request->route('id');
        $inventario = Inventario::find($id);

        if (!$inventario) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }

        $inventario->delete();
        return response()->json(['message' => 'Registro eliminado'], 200);
    }
}