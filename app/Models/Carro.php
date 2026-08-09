<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Chofer;
use Illuminate\Hashing\AbstractHasher\hasMany;

class Carro extends Model
{
      protected $fillable =[
        'placas',
        'marca',
        'color',
        'capacidad',
        'imagen',
        'dimensiones',
        'estatus'
    ];
public $timestamps = false;

    public function trayectos()
    {
        return $this->hasMany(Trayecto::class);
    }

    // Carros sin ningún trayecto activo (estatus fuera de Entregado/
    // Cancelado). $excluirTrayectoId deja pasar al carro que ya está en
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
