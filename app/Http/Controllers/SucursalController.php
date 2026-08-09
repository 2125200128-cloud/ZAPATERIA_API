<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sucursal;
use App\Models\Empleado;

class SucursalController extends Controller
{
    public function inicio()
    {
        $sucursales = Sucursal::with('empleado')->get();
        return response()->json(compact('sucursales'), 200);
    }

    public function formulario()
    {
        $empleados = Empleado::all();
        return response()->json(compact('empleados'), 200);
    }

    public function editar(Request $request)
    {
        $id = $request->route('id');
        $sucursal = Sucursal::find($id);
        
        if (!$sucursal) {
            return response()->json(['message' => 'Sucursal no encontrada'], 404);
        }
        
        $empleados = Empleado::all();
        return response()->json(compact('sucursal', 'empleados'), 200);
    }

    public function actualizar(Request $request)
    {
        $id = $request->route('id');
        $sucursal = Sucursal::find($id);
        
        if (!$sucursal) {
            return response()->json(['message' => 'Sucursal no encontrada'], 404);
        }
        
        $sucursal->nombre = $request->input('nombre');
        $sucursal->empleado_id = $request->input('empleado_id');
        $sucursal->calle = $request->input('calle');
        $sucursal->numero = $request->input('numero');
        $sucursal->municipio = $request->input('municipio');
        $sucursal->codigo_postal = $request->input('codigo_postal');
        $sucursal->contacto = $request->input('contacto');
        $sucursal->estatus = $request->input('estatus');
        $sucursal->save();

        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $nombre = 'sucursal_' . $sucursal->id . '.' . $file->getClientOriginalExtension();
            $ruta = $file->storeAs('imagenes/sucursales', $nombre, 'public');
            $sucursal->imagen = url('storage/' . $ruta);
            $sucursal->save();
        }

        return response()->json(['message' => 'Sucursal actualizada'], 200);
    }

    public function guardar(Request $request)
    {
        $sucursal = new Sucursal();
        $sucursal->nombre = $request->input('nombre');
        $sucursal->empleado_id = $request->input('empleado_id');
        $sucursal->calle = $request->input('calle');
        $sucursal->numero = $request->input('numero');
        $sucursal->municipio = $request->input('municipio');
        $sucursal->codigo_postal = $request->input('codigo_postal');
        $sucursal->contacto = $request->input('contacto');
        $sucursal->estatus = $request->input('estatus');
        $sucursal->imagen = 'sin-imagen.jpg';
        $sucursal->save();

        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $nombre = 'sucursal_' . $sucursal->id . '.' . $file->getClientOriginalExtension();
            $ruta = $file->storeAs('imagenes/sucursales', $nombre, 'public');
            $sucursal->imagen = url('storage/' . $ruta);
            $sucursal->save();
        }

        return response()->json(['message' => 'Sucursal guardada exitosamente.'], 201);
    }

    public function eliminar(Request $request)
    {
        $id = $request->route('id');
        $sucursal = Sucursal::find($id);
        
        if (!$sucursal) {
            return response()->json(['message' => 'Sucursal no encontrada'], 404);
        }
        
        $sucursal->estatus = 'Inactivo';
        $sucursal->save();
        
        return response()->json(['message' => 'Sucursal eliminada'], 200);
    }

    public function mostrar(Request $request)
    {
        $id = $request->route('id');
        $sucursal = Sucursal::find($id);
        
        if (!$sucursal) {
            return response()->json(['message' => 'Sucursal no encontrada'], 404);
        }
        
        return response()->json(['sucursal' => $sucursal], 200);
    }
}