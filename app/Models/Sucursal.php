<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected $fillable = [
        'empleado_id',
        'nombre',
        'calle',
        'numero',
        'municipio',
        'codigo_postal',
        'contacto',
        'imagen',
        'estatus',
        'es_matriz',
    ];

    public $timestamps = false;

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function inventarios()
    {
        return $this->hasMany(Inventario::class);
    }

    // La matriz se identifica por el campo 'es_matriz' si la BD lo tiene;
    // si no, se usa el nombre configurado como fallback para mantener la
    // compatibilidad con proyectos ya creados.
    public static function matriz(): ?self
    {
        if (Schema::hasTable('sucursales') && Schema::hasColumn('sucursales', 'es_matriz')) {
            $matriz = static::query()->where('es_matriz', true)->first();
            if ($matriz) {
                return $matriz;
            }
        }

        $nombreMatriz = config('ubicaciones.matriz.nombre');
        if ($nombreMatriz) {
            $matriz = static::query()->where('nombre', $nombreMatriz)->first();
            if ($matriz) {
                return $matriz;
            }
        }

        return static::query()
            ->where('estatus', 'Activo')
            ->orderBy('id')
            ->first();
    }
}

