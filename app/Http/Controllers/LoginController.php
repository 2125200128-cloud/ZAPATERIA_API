<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Empleado;

class LoginController extends Controller
{
    public function mostrar()
    {
        return response()->json(['message' => 'This endpoint is API only']);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'usuario' => 'required|string',
            'password' => 'required|string',
        ]);

        $empleado = Empleado::where('usuario', $validated['usuario'])->where('estatus', 'Activo')->first();

        if (!$empleado || (!Hash::check($validated['password'], $empleado->contrasena) && $empleado->contrasena !== $validated['password'])) {
            return response()->json(['message' => 'Usuario o contraseña incorrectos.'], 401);
        }

        $token = $empleado->createToken('api-token')->plainTextToken;

        // Antes se devolvía el modelo completo, lo que incluía 'contrasena'
        // (el hash) en el JSON — riesgo de seguridad. Ahora se arma a mano
        // solo lo necesario, con esAdministrador/esMatriz/sucursal ya
        // resueltos, para que el front pueda mostrar/ocultar pestañas y
        // botones sin volver a preguntarle nada a la API.
        $miSucursal = $empleado->miSucursal();

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $empleado->id,
                'nombre' => $empleado->nombre,
                'apellido_paterno' => $empleado->apellido_paterno,
                'apellido_materno' => $empleado->apellido_materno,
                'correo' => $empleado->correo,
                'usuario' => $empleado->usuario,
                'rol' => $empleado->rol,
                'estatus' => $empleado->estatus,
                'imagen' => $empleado->imagen,
                'esAdministrador' => $empleado->esAdministrador(),
                'esMatriz' => $empleado->esMatriz(),
                'sucursal' => $miSucursal ? [
                    'id' => $miSucursal->id,
                    'nombre' => $miSucursal->nombre,
                    'es_matriz' => (bool) $miSucursal->es_matriz,
                ] : null,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }
}