<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{

    protected $fillable = [
        'empleado_id',
        'fecha',
        'estatus'
    ];

    public $timestamps = false;

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function trayectos()
    {
        return $this->hasMany(Trayecto::class);
    }

    public function detallePedidos()
    {
        return $this->hasMany(Detalle_pedido::class);
    }
}
