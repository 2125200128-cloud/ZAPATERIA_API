<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{

    protected $fillable = [
        'proveedor_id',
        'nombre',
        'imagen'
    ];

    public $timestamps = false;

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class)->withDefault([
            'id' => null,
            'nombre' => 'Sin proveedor',
        ]);
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

}
