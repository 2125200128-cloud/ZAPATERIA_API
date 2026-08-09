<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Carro;

class CarroController extends Controller
{
    public function inicio()
    {
        $carros = Carro::with(['trayectos' => function ($query) {
            $query->whereNotIn('estatus', ['Entregado', 'Cancelado'])->with('chofer');
        }])->get();

        return response()->json(['carros' => $carros], 200);
    }

    public function guardar(Request $request)
    {
        $carro = new Carro();
        $carro->placas = $request->input('placas');
        $carro->marca = $request->input('marca');
        $carro->color = $request->input('color');
        $carro->capacidad = $request->input('capacidad');
        $carro->dimenciones = $request->input('dimenciones');
        $carro->estatus = $request->input('estatus');
        $carro->save();

        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $nombre = 'carro_' . $carro->id . '.' . $file->getClientOriginalExtension();
            $ruta = $file->storeAs('imagenes/carros', $nombre, 'public');
            $carro->imagen = url('storage/' . $ruta);
            $carro->save();
        }

        return response()->json(['message' => 'Carro guardado exitosamente.'], 201);
    }

    public function mostrar(Request $request)
    {
        $id = $request->route('id');
        $carro = Carro::find($id);

        if (!$carro) {
            return response()->json(['message' => 'Carro no encontrado'], 404);
        }
        
        return response()->json(['carro' => $carro], 200);
    }

    public function editar(Request $request)
    {
        $id = $request->route('id');
        $carro = Carro::find($id);

        if (!$carro) {
            return response()->json(['message' => 'Carro no encontrado'], 404);
        }
        
        return response()->json(['carro' => $carro], 200);
    }

    public function actualizar(Request $request)
    {
        $id = $request->route('id');
        $carro = Carro::find($id);

        if (!$carro) {
            return response()->json(['message' => 'Carro no encontrado'], 404);
        }

        $carro->placas = $request->input('placas');
        $carro->marca = $request->input('marca');
        $carro->color = $request->input('color');
        $carro->capacidad = $request->input('capacidad');
        $carro->dimenciones = $request->input('dimenciones');
        $carro->estatus = $request->input('estatus');
        $carro->save();

        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $nombre = 'carro_' . $carro->id . '.' . $file->getClientOriginalExtension();
            $ruta = $file->storeAs('imagenes/carros', $nombre, 'public');
            $carro->imagen = url('storage/' . $ruta);
            $carro->save();
        }

        return response()->json(['message' => 'Carro actualizado exitosamente'], 200);
    }

    public function eliminar(Request $request)
{
    $id = $request->route('id');
    $carro = Carro::find($id);

    if (!$carro) {
        return response()->json(['message' => 'Carro no encontrado'], 404);
    }

    try {
        $carro->delete();
    } catch (\Illuminate\Database\QueryException $e) {
        return response()->json([
            'message' => 'No se puede eliminar "' . $carro->placas . '": tiene trayectos registrados. Márcalo como Ocupado/Inactivo en vez de borrarlo.',
        ], 409);
    }

    return response()->json(['message' => 'Carro eliminado'], 200);
}
}