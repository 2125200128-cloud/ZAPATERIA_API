<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Carro;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class CarroController extends Controller
{
    /**
     * Subida de imágenes a Cloudinary con fallback por API HTTP
     */
    private function subirImagen($archivo, string $carpeta): ?string
    {
        $cloudinaryUrl = config('cloudinary.cloud_url') ?? env('CLOUDINARY_URL');

        if (!$cloudinaryUrl) {
            Log::warning("Cloudinary sin configurar (falta CLOUDINARY_URL en .env). No se subió la imagen en '{$carpeta}'.");
            return null;
        }

        try {
            $subida = Cloudinary::upload($archivo->getRealPath(), ['folder' => $carpeta]);

            if (is_object($subida) && method_exists($subida, 'getSecurePath')) {
                return $subida->getSecurePath();
            }
            if (is_array($subida) && isset($subida['secure_url'])) {
                return $subida['secure_url'];
            }
        } catch (\Throwable $e) {
            Log::error("Fallo con paquete Cloudinary (" . $e->getMessage() . "). Iniciando subida directa por HTTP REST API...");
        }

        try {
            if (preg_match('/cloudinary:\/\/([^:]+):(.+)@([^@\/]+)$/', trim($cloudinaryUrl), $matches)) {
                $apiKey    = $matches[1];
                $apiSecret = $matches[2];
                $cloudName = $matches[3];

                $timestamp = time();
                $stringToSign = "folder={$carpeta}&timestamp={$timestamp}" . $apiSecret;
                $signature    = sha1($stringToSign);

                $response = Http::attach(
                    'file',
                    file_get_contents($archivo->getRealPath()),
                    $archivo->getClientOriginalName()
                )->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                    'api_key'   => $apiKey,
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                    'folder'    => $carpeta,
                ]);

                if ($response->successful()) {
                    return $response->json('secure_url');
                }

                Log::error("Error en API REST de Cloudinary: " . $response->body());
            }
        } catch (\Throwable $e) {
            Log::error("Fallo crítico en subida fallback por HTTP: " . $e->getMessage());
        }

        return null;
    }

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

        if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
            $url = $this->subirImagen($request->file('imagen'), 'carros');
            if ($url) {
                $carro->imagen = $url;
                $carro->save();
            }
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

        if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
            $url = $this->subirImagen($request->file('imagen'), 'carros');
            if ($url) {
                $carro->imagen = $url;
                $carro->save();
            }
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