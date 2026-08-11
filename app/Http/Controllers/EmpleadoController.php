<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Empleado;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class EmpleadoController extends Controller
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
        $empleados = Empleado::all();
        return response()->json(['empleados' => $empleados], 200);
    }

    public function mostrar(Request $request)
    {
        $id = $request->route('id');
        $empleado = Empleado::find($id);

        if (!$empleado) {
            return response()->json(['message' => 'Empleado no encontrado'], 404);
        }

        return response()->json(['empleado' => $empleado], 200);
    }


    public function editar(Request $request)
    {
        $id = $request->route('id');
        $empleado = Empleado::find($id);

        if (!$empleado) {
            return response()->json(['message' => 'Empleado no encontrado'], 404);
        }

        return response()->json(['empleado' => $empleado], 200);
    }
    
    public function guardar(Request $request)
    {
        $empleado = new Empleado();
        $empleado->nombre = $request->input('nombre');
        $empleado->apellido_paterno = $request->input('apellido_paterno');
        $empleado->apellido_materno = $request->input('apellido_materno');
        $empleado->telefono = $request->input('telefono');
        $empleado->correo = $request->input('correo');
        $empleado->usuario = $request->input('usuario');
        $empleado->contrasena = Hash::make($request->input('contrasena'));
        $empleado->rol = $request->input('rol');
        $empleado->estatus = $request->input('estatus');
        $empleado->calle = $request->input('calle');
        $empleado->numero = $request->input('numero');
        $empleado->municipio = $request->input('municipio');
        $empleado->codigo_postal = $request->input('codigo_postal');
        $empleado->imagen = 'sin-imagen.jpg';
        $empleado->save();

        if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
            $url = $this->subirImagen($request->file('imagen'), 'empleados');
            if ($url) {
                $empleado->imagen = $url;
                $empleado->save();
            }
        }

        return response()->json(['message' => 'Empleado guardado exitosamente.'], 201);
    }

    public function actualizar(Request $request)
    {
        $id = $request->route('id');
        $empleado = Empleado::find($id);

        if (!$empleado) {
            return response()->json(['message' => 'Empleado no encontrado'], 404);
        }

        $empleado->nombre = $request->input('nombre');
        $empleado->apellido_paterno = $request->input('apellido_paterno');
        $empleado->apellido_materno = $request->input('apellido_materno');
        $empleado->telefono = $request->input('telefono');
        $empleado->correo = $request->input('correo');
        $empleado->usuario = $request->input('usuario');
        
        if ($request->filled('contrasena')) {
            $empleado->contrasena = Hash::make($request->input('contrasena'));
        }
        
        $empleado->rol = $request->input('rol');
        $empleado->estatus = $request->input('estatus');
        $empleado->calle = $request->input('calle');
        $empleado->numero = $request->input('numero');
        $empleado->municipio = $request->input('municipio');
        $empleado->codigo_postal = $request->input('codigo_postal');
        $empleado->save();

        if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
            $url = $this->subirImagen($request->file('imagen'), 'empleados');
            if ($url) {
                $empleado->imagen = $url;
                $empleado->save();
            }
        }

        return response()->json(['message' => 'Empleado actualizado'], 200);
    }

    public function eliminar(Request $request)
    {
        $id = $request->route('id');
        $empleado = Empleado::find($id);

        if (!$empleado) {
            return response()->json(['message' => 'Empleado no encontrado'], 404);
        }

        $empleado->estatus = 'Inactivo';
        $empleado->save();

        return response()->json(['message' => 'Empleado eliminado'], 200);
    }
}