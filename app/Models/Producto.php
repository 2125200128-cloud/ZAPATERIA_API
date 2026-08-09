<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $fillable = [
        'marca_id',
        'proveedor_id',
        'nombre',
        'descripcion',
        'precio',
        'modelo',
        'color',
        'sexo',
        'categoria',
        'talla',
        'estatus',
        'imagen1',
        'imagen2',
        'imagen3'
    ];

    public $timestamps = false;


    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function inventarios()
    {
        return $this->hasMany(Inventario::class);
    }

    public function detallePedidos()
    {
        return $this->hasMany(Detalle_pedido::class);
    }
}
