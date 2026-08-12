<?php

// Coordenadas fijas de la matriz y las sucursales del caso de estudio
// (Guadalajara y municipios de Jalisco). No dependen de la BD ni de
// geocodificación: son solo 6 ubicaciones conocidas.

return [

    'matriz' => [
        'nombre' => 'Matriz Guadalajara',
        'lat' => 20.6597,
        'lng' => -103.3496,
    ],

    'municipios' => [
        'Guadalajara' => [20.6597, -103.3496],
        'Zapopan' => [20.7236, -103.3848],
        'Ciudad Guzmán' => [19.6971, -103.4630],
        'Puerto Vallarta' => [20.6534, -105.2253],
        'Lagos de Moreno' => [21.3573, -101.9339],
        'San Juan de los Lagos' => [21.2500, -102.3167],
    ],

];
