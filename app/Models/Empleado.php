<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class Empleado extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
       'nombre',
       'apellido_paterno',
       'apellido_materno',
       'correo',
       'telefono',
       'contrasena',
       'usuario',
       'rol',
       'estatus',
       'calle',
       'numero',
       'municipio',
       'codigo_postal',
       'imagen',
    ];

    protected $hidden = [
       'contrasena',
    ];

    public $timestamps = false;

    // La columna real es 'contrasena' (sin ñ), no 'password' — Laravel siempre
    // busca el password autenticable a través de este método, así que aquí se
    // hace el mapeo.
    public function getAuthPassword()
    {
       return $this->contrasena;
    }

    public function sucursales()
    {
       return $this->hasMany(Sucursal::class);
    }

    public function pedidos()
    {
       return $this->hasMany(Pedido::class);
    }

    public static function auth(): ?self
    {
       $empleado = Auth::guard('empleado')->user() ?: Auth::user();

       return $empleado instanceof self ? $empleado : null;
    }

    protected function rolNormalizado(): string
    {
       return strtolower(trim((string) ($this->rol ?? '')));
    }

    public function esAdministrador(): bool
    {
       return $this->rolNormalizado() === 'administrador';
    }

    // Si el empleado tiene varias sucursales, se elige la sucursal matriz si
    // existe. De lo contrario, se usa una sucursal estable por id para evitar
    // comportamiento arbitrario.
    public function miSucursal(): ?Sucursal
    {
       $matriz = Sucursal::matriz();
       if ($matriz && $this->sucursales()->whereKey($matriz->id)->exists()) {
           return $matriz;
       }

       return $this->sucursales()
           ->orderBy('id')
           ->first();
    }

    public function esMatriz(): bool
    {
       $sucursal = $this->miSucursal();
       $matriz = Sucursal::matriz();

       if ($sucursal === null || $matriz === null) {
           return false;
       }

       return (int) $sucursal->id === (int) $matriz->id;
    }
}

