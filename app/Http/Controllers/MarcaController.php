<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Marca;
use App\Models\Proveedor;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

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
        $marca = Marca::with('proveedor')->find($id);

        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }

        $proveedores = Proveedor::all();
        return response()->json(compact('marca', 'proveedores'), 200);
    }

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

        // 1. Intentar subida con la librería principal de Cloudinary
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

        // 2. Fallback de emergencia: Subida directa mediante API REST HTTP de Cloudinary
        try {
            // Extraer las credenciales de CLOUDINARY_URL usando expresiones regulares
            if (preg_match('/cloudinary:\/\/([^:]+):(.+)@([^@\/]+)$/', trim($cloudinaryUrl), $matches)) {
                $apiKey    = $matches[1];
                $apiSecret = $matches[2];
                $cloudName = $matches[3];

                $timestamp = time();
                // Firma SHA1 requerida por Cloudinary API (parámetros ordenados alfabéticamente)
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

    public function actualizar(Request $request)
    {
        $id = $request->route('id');
        $marca = Marca::find($id);

        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
        ]);

        $marca->nombre = $validated['nombre'];
        $marca->proveedor_id = $validated['proveedor_id'];
        $marca->save();

        $mensaje = 'Marca actualizada';

        if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
            $url = $this->subirImagen($request->file('imagen'), 'marcas');

            if ($url) {
                $marca->imagen = $url;
                $marca->save();
            } else {
                $mensaje = 'Marca actualizada, pero no se pudo subir la imagen (revisa la configuración de Cloudinary).';
            }
        }

        return response()->json(['message' => $mensaje], 200);
    }

    public function guardar(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
        ]);

        $marca = new Marca();
        $marca->nombre = $validated['nombre'];
        $marca->proveedor_id = $validated['proveedor_id'];
        $marca->save();

        $mensaje = 'Marca guardada exitosamente.';

        if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
            $url = $this->subirImagen($request->file('imagen'), 'marcas');

            if ($url) {
                $marca->imagen = $url;
                $marca->save();
            } else {
                $mensaje = 'Marca guardada, pero no se pudo subir la imagen (revisa la configuración de Cloudinary).';
            }
        }

        return response()->json(['message' => $mensaje], 201);
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
        $marca = Marca::with('proveedor')->find($id);

        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }

        return response()->json(['marca' => $marca], 200);
    }
}