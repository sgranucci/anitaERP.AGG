<?php

use App\Support\Caja\AnitaSync\RendicionEstacionamientoRendgastroEsquema;

return [

    /**
     * Réplica rendgastro / rendvalor en Informix vía bridge HTTP (misma tabla que gastronomía; PV distintos).
     */
    'sincronizar' => filter_var(env('RENDICION_ESTACIONAMIENTO_SINCRONIZAR_ANITA', env('RENDICION_GASTRONOMIA_SINCRONIZAR_ANITA', true)), FILTER_VALIDATE_BOOLEAN),

    'sistema' => env('RENDICION_ESTACIONAMIENTO_ANITA_SISTEMA', env('RENDICION_GASTRONOMIA_ANITA_SISTEMA', 'caja')),

    'tabla_cabecera' => 'rendgastro',

    'tabla_valor' => 'rendvalor',

    'tipo_oper' => env('RENDICION_ESTACIONAMIENTO_ANITA_TIPO_OPER', env('RENDICION_GASTRONOMIA_ANITA_TIPO_OPER', 'F')),

    'cabecera_campos_numericos_insert_cero' => RendicionEstacionamientoRendgastroEsquema::COLUMNAS_NUMERICAS_SIN_MAPEO,

    'cabecera_campos_numericos_cero_en_update' => [
    ],

    'auditoria_diaria' => [
        'habilitada' => filter_var(env('RENDICION_ESTACIONAMIENTO_AUDITORIA_DIARIA', false), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('RENDICION_ESTACIONAMIENTO_AUDITORIA_HORA', '07:30'),
        'empresa_id' => (int) env('RENDICION_ESTACIONAMIENTO_AUDITORIA_EMPRESA_ID', 1),
        'tolerancia' => (float) env('RENDICION_ESTACIONAMIENTO_AUDITORIA_TOLERANCIA', 0.02),
    ],

    'codigos_rendvalor' => [
        1 => [
            'efectivo' => 1,
            'mercadopago' => 6,
            'fiserv' => 26,
            'totalcoin' => 22,
            'canje_tarjeta' => 15,
        ],
        2 => [
            'efectivo' => 51,
            'mercadopago' => 86,
            'fiserv' => 75,
            'totalcoin' => 74,
            'canje_tarjeta' => 67,
        ],
        3 => [
            'efectivo' => 81,
            'mercadopago' => 88,
            'fiserv' => 92,
            'totalcoin' => 85,
            'canje_tarjeta' => 97,
        ],
        4 => [
            'mercadopago' => 88,
            'canje_tarjeta' => 97,
        ],
    ],

];
