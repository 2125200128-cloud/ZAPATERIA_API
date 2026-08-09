<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrayectoUbicacion extends Model
{
    protected $table = 'trayecto_ubicaciones';

    protected $fillable = [
        'trayecto_id',
        'latitud',
        'longitud',
        'registrado_en',
    ];

    public $timestamps = false;

    public function trayecto()
    {
        return $this->belongsTo(Trayecto::class);
    }
}
