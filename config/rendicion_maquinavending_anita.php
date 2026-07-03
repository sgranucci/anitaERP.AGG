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

    /** Detalle por rulo en Informix ventas (rendmva_nro_oper = rendg_nro_oper global). */
    'tabla_articulo' => env('RENDICION_MAQUINAVENDING_ANITA_TABLA_ARTICULO', 'rendmvart'),

    /**
     * rendg_nro_ticket en rendgastro cuando exista la columna en Informix.
     * rendg_ult_ticket apunta a rendmva_nro_oper (= rendg_nro_oper ERP).
     */
    'incluir_rendg_nro_ticket' => filter_var(
        env('RENDICION_MAQUINAVENDING_ANITA_INCLUIR_RENDG_NRO_TICKET', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    'tipo_oper' => env('RENDICION_MAQUINAVENDING_ANITA_TIPO_OPER', env('RENDICION_GASTRONOMIA_ANITA_TIPO_OPER', 'F')),

    /**
     * Bridge Anita central Biyemas (ANITA_IP) para rendgastro, rendvalor y rendmvart.
     * No usar bridge per-empresa (Kandiko/Rebisco) en vending: el cierre lee todo en Biyemas.
     */
    'bridge_biyemas_central' => filter_var(
        env('RENDICION_MAQUINAVENDING_BRIDGE_BIYEMAS_CENTRAL', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /** Sistema Informix para rendmvart (mismo host que rendgastro). */
    'sistema_ventas' => env('RENDICION_MAQUINAVENDING_ANITA_SISTEMA_VENTAS', 'ventas'),

    /**
     * Secuencia única rendg_nro_oper / rendmva_nro_oper para empresas 1–3 (clave Informix: tipo F + nro_oper).
     * Piso por encima de rangos legacy por empresa; techo 0 = sin límite.
     */
    'nro_oper_piso_global' => (int) env('RENDICION_MAQUINAVENDING_NRO_OPER_PISO_GLOBAL', 600001),

    'nro_oper_techo_global' => (int) env('RENDICION_MAQUINAVENDING_NRO_OPER_TECHO_GLOBAL', 0),

    /** Empresas ERP incluidas en max(nro_oper) al numerar. */
    'empresa_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('RENDICION_MAQUINAVENDING_EMPRESA_IDS', '1,2,3'))
    ), static fn (int $id) => $id > 0)),

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
