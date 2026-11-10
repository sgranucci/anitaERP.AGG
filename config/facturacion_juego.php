<?php

/**
 * Facturación juego Crown (paridad p-resultado.c).
 * Tipos y letra por defecto; la pantalla de configuración los puede cambiar.
 */
return [
    'letra' => env('FACTURACION_JUEGO_LETRA', 'B'),
    'tipoasiento_abreviatura' => env('FACTURACION_JUEGO_TIPOASIENTO', 'VTA'),
    'tipos' => [
        'gan_mesa' => env('FACTURACION_JUEGO_TIPO_GAN_MESA', 'FME'),
        'per_mesa' => env('FACTURACION_JUEGO_TIPO_PER_MESA', 'CME'),
        'gan_slot' => env('FACTURACION_JUEGO_TIPO_GAN_SLOT', 'FSL'),
        'per_slot' => env('FACTURACION_JUEGO_TIPO_PER_SLOT', 'CSL'),
        'gan_ruleta_el' => env('FACTURACION_JUEGO_TIPO_GAN_RULETA', 'FRE'),
        'per_ruleta_el' => env('FACTURACION_JUEGO_TIPO_PER_RULETA', 'CRE'),
    ],
    'nombres_cliente' => [
        'mesa' => 'Mesas de juego',
        'slot' => 'Tragamonedas',
        'ruleta_el' => 'Ruleta El.',
    ],
];
