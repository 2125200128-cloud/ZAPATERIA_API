<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = [
        'nombre',
        'contacto',
        'correo',
        'calle',
        'numero',
        'estatus',
        'municipio',
        'codigo_postal',
        'imagen'
    ];

    public $timestamps = false;

        public function marcas()
    {
        return $this->hasMany(Marca::class);
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}
