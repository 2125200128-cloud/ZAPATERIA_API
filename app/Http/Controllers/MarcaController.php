<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Marca;
use App\Models\Proveedor;

class MarcaController extends Controller
{
    public function inicio()
    {
        $marcas = Marca::with('proveedor')->get();
        return response()->json(compact('marcas'), 200);
    }

    public function formulario()
    {
        $proveedores = Proveedor::all();
        return response()->json(compact('proveedores'), 200);
    }

    public function editar(Request $request)
    {
        $id = $request->route('id');
        $marca = Marca::find($id);
        
        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }
        
        $proveedores = Proveedor::all();
        return response()->json(compact('marca', 'proveedores'), 200);
    }

    public function actualizar(Request $request)
    {
        $id = $request->route('id');
        $marca = Marca::find($id);
        
        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }
        
        $marca->nombre = $request->input('nombre');
        $marca->proveedor_id = $request->input('proveedor_id');
        $marca->save();

        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $nombre = 'marca_' . $marca->id . '.' . $file->getClientOriginalExtension();
            $ruta = $file->storeAs('imagenes/marcas', $nombre, 'public');
            $marca->imagen = url('storage/' . $ruta);
            $marca->save();
        }

        return response()->json(['message' => 'Marca actualizada'], 200);
    }

    public function guardar(Request $request)
    {
        $marca = new Marca();
        $marca->nombre = $request->input('nombre');
        $marca->proveedor_id = $request->input('proveedor_id');
        $marca->save();

        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $nombre = 'marca_' . $marca->id . '.' . $file->getClientOriginalExtension();
            $ruta = $file->storeAs('imagenes/marcas', $nombre, 'public');
            $marca->imagen = url('storage/' . $ruta);
            $marca->save();
        }

        return response()->json(['message' => 'Marca guardada exitosamente.'], 201);
    }

   public function eliminar(Request $request)
{
    $id = $request->route('id');
    $marca = Marca::find($id);
    
    if (!$marca) {
        return response()->json(['message' => 'Marca no encontrada'], 404);
    }
    
    try {
        $marca->delete();
    } catch (\Illuminate\Database\QueryException $e) {
        return response()->json([
            'message' => 'No se puede eliminar "' . $marca->nombre . '": tiene productos registrados con esta marca.',
        ], 409);
    }

    return response()->json(['message' => 'Marca eliminada'], 200);
}
    // Alta rápida desde el formulario de producto (vía AJAX)
    public function guardarRapido(Request $request)
    {
        $nombre = trim((string) $request->input('nombre'));
        $proveedorId = $request->input('proveedor_id');
        $proveedor = $proveedorId ? Proveedor::find($proveedorId) : null;

        if ($nombre === '' || !$proveedor) {
            return response()->json(['error' => 'Escribe un nombre y elige un proveedor.'], 422);
        }

        $marca = new Marca();
        $marca->nombre = $nombre;
        $marca->proveedor_id = $proveedor->id;
        $marca->save();

        return response()->json(['id' => $marca->id, 'nombre' => $marca->nombre], 201);
    }

    public function mostrar(Request $request)
    {
        $id = $request->route('id');
        $marca = Marca::find($id);
        
        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }
        
        return response()->json(['marca' => $marca], 200);
    }
}