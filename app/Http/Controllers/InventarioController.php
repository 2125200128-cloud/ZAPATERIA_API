<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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

    public function inicio()
    {
        $query = Inventario::with(['sucursal', 'producto.marca']);

        if (!$this->puedeAsignar()) {
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

        $inventario = new Inventario();
        $inventario->sucursal_id = $request->input('sucursal_id');
        $inventario->producto_id = $request->input('producto_id');
        $inventario->stock = $request->input('stock');
        $inventario->estatus = $request->input('estatus');
        $inventario->save();

        return response()->json(['message' => 'Registro guardado exitosamente.'], 201);
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

        $inventario->sucursal_id = $request->input('sucursal_id');
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