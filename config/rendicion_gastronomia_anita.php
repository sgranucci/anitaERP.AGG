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
     * Piso de rendg_nro_oper por empresa (solo cuenta / asigna números >= piso).
     * Evita colisión con legacy Anita / estacionamiento en secuencia baja.
     */
    'nro_oper_piso_por_empresa' => [
        2 => (int) env('RENDICION_GASTRONOMIA_NRO_OPER_PISO_KANDIKO', 200001),
        3 => (int) env('RENDICION_GASTRONOMIA_NRO_OPER_PISO_REBISCO', 300001),
    ],

    /** Techo exclusivo del rango por empresa (siguiente debe ser < techo). */
    'nro_oper_techo_por_empresa' => [
        2 => (int) env('RENDICION_GASTRONOMIA_NRO_OPER_TECHO_KANDIKO', 300000),
        3 => (int) env('RENDICION_GASTRONOMIA_NRO_OPER_TECHO_REBISCO', 400000),
    ],

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
     * Auditoría diaria rendg_total_z / rendg_tot_nc (rendgastro) vs ERP.
     * Comando: rendicion-gastronomia:auditoria-anita
     */
    'auditoria_diaria' => [
        'habilitada' => filter_var(env('RENDICION_GASTRONOMIA_AUDITORIA_DIARIA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('RENDICION_GASTRONOMIA_AUDITORIA_HORA', '08:00'),
        'empresa_id' => (int) env('RENDICION_GASTRONOMIA_AUDITORIA_EMPRESA_ID', 1),
        'empresas_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('RENDICION_GASTRONOMIA_AUDITORIA_EMPRESAS_IDS', '1,2,3')),
        ))),
        'tolerancia' => (float) env('RENDICION_GASTRONOMIA_AUDITORIA_TOLERANCIA', 0.02),
        'email' => env('RENDICION_GASTRONOMIA_AUDITORIA_EMAIL', env('GASTRONOMIA_AUDITORIA_ANITA_EMAIL', '')),
        'email_si_ok' => filter_var(env('RENDICION_GASTRONOMIA_AUDITORIA_EMAIL_SI_OK', true), FILTER_VALIDATE_BOOLEAN),
        /** PV que facturan solo en Anita (estacionamiento); no alertar si ERP=0 y Anita tiene Z. */
        'puntoventa_codigos_solo_anita' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('RENDICION_GASTRONOMIA_AUDITORIA_PV_SOLO_ANITA', '00013,00072,00073,00074')),
        ))),
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
