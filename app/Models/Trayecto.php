<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trayecto extends Model
{
    protected $fillable = [
        'chofer_id',
        'carro_id',
        'pedido_id',
        'fecha_envio',
        'descripcion_ruta',
        'estatus'
    ];

    public $timestamps = false;

    public function chofer()
    {
        return $this->belongsTo(Chofer::class);
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function carro()
    {
        return $this->belongsTo(Carro::class);
    }

    public function ubicaciones()
    {
        return $this->hasMany(TrayectoUbicacion::class);
    }

    public function ubicacionActual()
    {
        return $this->hasOne(TrayectoUbicacion::class)->latestOfMany('registrado_en');
    }
}
