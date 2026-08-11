<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Sucursal;
use App\Models\Empleado;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class SucursalController extends Controller
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

        if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
            $url = $this->subirImagen($request->file('imagen'), 'sucursales');
            if ($url) {
                $sucursal->imagen = $url;
                $sucursal->save();
            }
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

        if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
            $url = $this->subirImagen($request->file('imagen'), 'sucursales');
            if ($url) {
                $sucursal->imagen = $url;
                $sucursal->save();
            }
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