<?php

use App\Support\Caja\AnitaSync\RendicionGastronomiaRendgastroEsquema;

return [

    /**
     * Réplica rendgastro / rendvalor de rendiciones vending (Ventas) vía bridge Anita.
     */
    'sincronizar' => filter_var(env('RENDICION_MAQUINAVENDING_SINCRONIZAR_ANITA', env('RENDICION_GASTRONOMIA_SINCRONIZAR_ANITA', true)), FILTER_VALIDATE_BOOLEAN),

    'sistema' => env('RENDICION_MAQUINAVENDING_ANITA_SISTEMA', env('RENDICION_GASTRONOMIA_ANITA_SISTEMA', 'caja')),

    'tabla_cabecera' => 'rendgastro',

    'tabla_valor' => 'rendvalor',

    'tipo_oper' => env('RENDICION_MAQUINAVENDING_ANITA_TIPO_OPER', env('RENDICION_GASTRONOMIA_ANITA_TIPO_OPER', 'F')),

    /**
     * Piso de rendg_nro_oper por empresa (evita colisión con legacy / otros módulos).
     */
    'nro_oper_piso_por_empresa' => [
        2 => (int) env('RENDICION_MAQUINAVENDING_NRO_OPER_PISO_KANDIKO', 400001),
        3 => (int) env('RENDICION_MAQUINAVENDING_NRO_OPER_PISO_REBISCO', 500001),
    ],

    'nro_oper_techo_por_empresa' => [
        2 => (int) env('RENDICION_MAQUINAVENDING_NRO_OPER_TECHO_KANDIKO', 500000),
        3 => (int) env('RENDICION_MAQUINAVENDING_NRO_OPER_TECHO_REBISCO', 600000),
    ],

    'cabecera_campos_numericos_insert_cero' => RendicionGastronomiaRendgastroEsquema::COLUMNAS_NUMERICAS_SIN_MAPEO,

    'cabecera_campos_numericos_cero_en_update' => [],

    /** Caja Informix cuando la rendición aún no pasó por tesorería. */
    'caja_id_default_por_empresa' => [
        1 => (int) env('RENDICION_MAQUINAVENDING_CAJA_DEFAULT_1', 1),
        2 => (int) env('RENDICION_MAQUINAVENDING_CAJA_DEFAULT_2', 1),
        3 => (int) env('RENDICION_MAQUINAVENDING_CAJA_DEFAULT_3', 1),
        4 => (int) env('RENDICION_MAQUINAVENDING_CAJA_DEFAULT_4', 1),
    ],

    'codigos_rendvalor' => config('rendicion_gastronomia_anita.codigos_rendvalor', []),
];
