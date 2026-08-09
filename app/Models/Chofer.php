<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chofer extends Model
{
    protected $table = 'choferes';

    protected $fillable = [
        'nombre',
        'apellido',
        'contacto',
        'imagen',
        'estatus'
    ];

    public $timestamps = false;

        public function trayectos()
    {
        return $this->hasMany(Trayecto::class);
    }

    // Choferes sin ningún trayecto activo (estatus fuera de Entregado/
    // Cancelado). $excluirTrayectoId deja pasar al chofer que ya está en
    // ESE trayecto (para no desaparecer de su propio formulario al editar).
    public function scopeDisponible($query, $excluirTrayectoId = null)
    {
        return $query->whereDoesntHave('trayectos', function ($q) use ($excluirTrayectoId) {
            $q->whereNotIn('estatus', ['Entregado', 'Cancelado']);
            if ($excluirTrayectoId) {
                $q->where('id', '!=', $excluirTrayectoId);
            }
        });
    }
}

