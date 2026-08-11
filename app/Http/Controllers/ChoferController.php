<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Chofer;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class ChoferController extends Controller
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

        if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
            $url = $this->subirImagen($request->file('imagen'), 'choferes');
            if ($url) {
                $chofer->imagen = $url;
                $chofer->save();
            }
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

        if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
            $url = $this->subirImagen($request->file('imagen'), 'choferes');
            if ($url) {
                $chofer->imagen = $url;
                $chofer->save();
            }
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