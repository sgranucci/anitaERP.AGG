<?php

use App\Support\Caja\AnitaSync\RendicionGastronomiaRendgastroEsquema;

return [

    /**
     * Réplica rendgastro / rendvalor en Informix vía bridge HTTP.
     */
    'sincronizar' => filter_var(env('RENDICION_GASTRONOMIA_SINCRONIZAR_ANITA', true), FILTER_VALIDATE_BOOLEAN),

    /** Base Informix del bridge (tablas rendgastro / rendvalor). */
    'sistema' => env('RENDICION_GASTRONOMIA_ANITA_SISTEMA', 'caja'),

    'tabla_cabecera' => 'rendgastro',

    'tabla_valor' => 'rendvalor',

    /** Clave compuesta Informix: rendg_nro_oper + rendg_tipo_oper. */
    'tipo_oper' => env('RENDICION_GASTRONOMIA_ANITA_TIPO_OPER', 'F'),

    /**
     * INSERT: columnas numéricas extra del DDL (además de RendicionGastronomiaRendgastroEsquema::COLUMNAS_NUMERICAS_SIN_MAPEO).
     * La lista canónica ccig/ccignc está en código según docs/rendgastro.sql; aquí solo ampliaciones futuras.
     */
    'cabecera_campos_numericos_insert_cero' => RendicionGastronomiaRendgastroEsquema::COLUMNAS_NUMERICAS_SIN_MAPEO,

    /**
     * UPDATE: opt-in. Solo estas columnas (además del mapeo ctx) se incluyen en el SET.
     */
    'cabecera_campos_numericos_cero_en_update' => [
    ],

    /**
     * Código rendv_codigo por empresa y familia de medio de cobro.
     * Familias: efectivo, mercadopago, fiserv, totalcoin, canje_tarjeta.
     */
    'codigos_rendvalor' => [
        1 => [
            'efectivo' => 1,
            'mercadopago' => 6,
            'fiserv' => 26,
            'totalcoin' => 22,
            'canje_tarjeta' => 17,
        ],
        2 => [
            'efectivo' => 51,
            'mercadopago' => 86,
            'fiserv' => 75,
            'totalcoin' => 74,
            'canje_tarjeta' => 17,
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
