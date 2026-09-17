<?php

/**
 * Sync Anita (climov + aplmov) → cliente_cuentacorriente / aplicaciones.
 * Dirección: Anita es fuente de verdad del saldo; no escribe Anita.
 *
 * Los formatos Informix varían por instalación (EMPRESA): AGG suele tener
 * cliv_empresa; Ferli/El Bierzo pueden no. Overrides vía env.
 */
return [
    'sistema' => env('CLIENTE_CC_ANITA_SISTEMA', 'ventas'),

    'tabla_climov' => 'climov',
    'tabla_aplmov' => 'aplmov',

    /**
     * null = auto según EMPRESA (AGG=true, resto=false).
     * Forzar con CLIENTE_CC_ANITA_CLIMOV_TIENE_EMPRESA=true|false.
     */
    'climov_tiene_empresa' => env('CLIENTE_CC_ANITA_CLIMOV_TIENE_EMPRESA'),

    /**
     * Lista CSV de campos. Vacío = arma FormatoSupport según climov_tiene_empresa.
     */
    'campos_climov' => env('CLIENTE_CC_ANITA_CAMPOS_CLIMOV', ''),

    'campos_aplmov' => env('CLIENTE_CC_ANITA_CAMPOS_APLMOV', ''),

    /**
     * Si *_cob viene vacío, usar *_ref como crédito (algunos Anita viejos).
     */
    'aplmov_fallback_ref_como_cob' => filter_var(
        env('CLIENTE_CC_ANITA_APLMOV_FALLBACK_REF', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /**
     * Tipos Anita que no son deuda / crédito pendiente de CC
     * (cobranzas aplicadas, recibos, PRE). COA sí se importa: resta deuda.
     */
    'tipos_no_deuda' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CLIENTE_CC_ANITA_TIPOS_NO_DEUDA', 'COB,ANT,REC,RBO,AJU,PRE'))
    ))),

    /**
     * Créditos pendientes que viven en climov sin fila en Anita `venta` (ej. COA).
     * Se sintetiza la cabecera ERP desde climov.
     */
    'tipos_credito_sin_venta' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CLIENTE_CC_ANITA_TIPOS_CREDITO_SIN_VENTA', 'COA'))
    ))),

    /**
     * Tolerancia al comparar aplicado ERP vs cliv_t_cobrado.
     */
    'tolerancia_aplicado' => (float) env('CLIENTE_CC_ANITA_TOLERANCIA', 0.02),

    'bridge_list_reintentos' => (int) env(
        'CLIENTE_CC_ANITA_BRIDGE_REINTENTOS',
        (int) env('ANITA_BRIDGE_LIST_REINTENTOS', 6)
    ),
];
