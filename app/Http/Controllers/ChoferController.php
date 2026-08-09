<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chofer;

class ChoferController extends Controller
{
    public function inicio()
    {
        $choferes = Chofer::with([
            'trayectos' => function ($query) {
                $query->whereNotIn('estatus', ['Entregado', 'Cancelado'])->with('carro');
            }
        ])->get();

        return response()->json(['choferes' => $choferes], 200);
    }

    public function mostrar(Request $request)
    {
        $id = $request->route('id');
        $chofer = Chofer::find($id);

        if (!$chofer) {
            return response()->json(['message' => 'Chofer no encontrado'], 404);
        }

        return response()->json(['chofer' => $chofer], 200);
    }

    public function editar(Request $request)
    {
        $id = $request->route('id');
        $chofer = Chofer::find($id);

        if (!$chofer) {
            return response()->json(['message' => 'Chofer no encontrado'], 404);
        }

        return response()->json(['chofer' => $chofer], 200);
    }

    public function guardar(Request $request)
    {
        $chofer = new Chofer();
        $chofer->nombre = $request->input('nombre');
        $chofer->apellido = $request->input('apellido');
        $chofer->contacto = $request->input('contacto');
        $chofer->estatus = $request->input('estatus');
        $chofer->save();

        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $nombre = 'chofer_' . $chofer->id . '.' . $file->getClientOriginalExtension();
            $ruta = $file->storeAs('imagenes/choferes', $nombre, 'public');
            $chofer->imagen = url('storage/' . $ruta);
            $chofer->save();
        }

        return response()->json(['message' => 'Chofer guardado exitosamente.'], 201);
    }

    public function actualizar(Request $request)
    {
        $id = $request->route('id');
        $chofer = Chofer::find($id);

        if (!$chofer) {
            return response()->json(['message' => 'Chofer no encontrado'], 404);
        }

        $chofer->nombre = $request->input('nombre');
        $chofer->apellido = $request->input('apellido');
        $chofer->contacto = $request->input('contacto');
        $chofer->estatus = $request->input('estatus');
        $chofer->save();

        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $nombre = 'chofer_' . $chofer->id . '.' . $file->getClientOriginalExtension();
            $ruta = $file->storeAs('imagenes/choferes', $nombre, 'public');
            $chofer->imagen = url('storage/' . $ruta);
            $chofer->save();
        }

        return response()->json(['message' => 'Chofer actualizado'], 200);
    }

    public function eliminar(Request $request)
    {
        $id = $request->route('id');
        $chofer = Chofer::find($id);

        if (!$chofer) {
            return response()->json(['message' => 'Chofer no encontrado'], 404);
        }

        $chofer->estatus = 'Inactivo';
        $chofer->save();

        return response()->json(['message' => 'Chofer eliminado'], 200);
    }
}