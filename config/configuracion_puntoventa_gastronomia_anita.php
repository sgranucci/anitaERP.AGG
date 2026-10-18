<?php

/**
 * Sync Anita confptovta → configuracion_puntoventa_gastronomia (PC / terminal).
 *
 * Fuente C: a-confptovta.c / a-comandera.c (cpvta_*).
 * CROWN Cipolletti: bridge cncadmin + sala CIP (misma pauta que mesa/mozo/caja).
 *
 * Modo uso (cpvta_modo_uso): R=resto S=sala C=comandera O=restosolo
 * Comanda (cpvta_comanda): C=sola F=factura_y_comanda N/vacío=sin_comanda
 */
return [
    'tabla' => 'confptovta',

    'sistema' => env('CONFIG_PV_GASTRONOMIA_SYNC_ANITA_SISTEMA', 'ventas'),

    /** CROWN: cncadmin (Cipolletti). Vacío = bridge global. */
    'ifx_server' => env('CONFIG_PV_GASTRONOMIA_SYNC_ANITA_IFX_SERVER', ''),
    'path_sistema' => env('CONFIG_PV_GASTRONOMIA_SYNC_ANITA_PATH', ''),

    'empresa_default_id' => (int) env('CONFIG_PV_GASTRONOMIA_SYNC_EMPRESA_ID', 0)
        ?: (int) (config('cliente.EMPRESA_DEFAULT_ID') ?? 1),

    /** CROWN: código sala (ej. CIP). */
    'sala_codigo' => env('CONFIG_PV_GASTRONOMIA_SYNC_SALA_CODIGO', ''),

    /**
     * PV CAE de respaldo (código Anita/ERP 5 dígitos) si cpvta_suc_kiosco no es FCEL (C/E).
     * CIP: 00090 Factura Electronica Cipo.
     */
    'puntoventa_cae_fallback_codigo' => env('CONFIG_PV_GASTRONOMIA_SYNC_PV_CAE_FALLBACK', '00090'),

    /**
     * PV CAEA por sala (Anita confptovta no trae suc CAEA en el esquema cncadmin).
     * CIP: 00117 CAEA Cipo.
     */
    'puntoventa_caea_codigo' => env('CONFIG_PV_GASTRONOMIA_SYNC_PV_CAEA', '00117'),

    'campos_listado' => env(
        'CONFIG_PV_GASTRONOMIA_SYNC_CAMPOS',
        'cpvta_host,cpvta_ubicacion,cpvta_modo_uso,cpvta_deposito,cpvta_lista,'
        .'cpvta_tipo_fact,cpvta_conc_hotel,cpvta_suc_kiosco,cpvta_canjea,cpvta_comanda,'
        .'cpvta_salida_a,cpvta_salida_b,cpvta_salida_k,cpvta_salida_fac,'
        .'cpvta_deposito_a,cpvta_deposito_b,cpvta_deposito_k,cpvta_lista_hotel,cpvta_salida_hotel'
    ),

    /**
     * Si true, crea filas en salida con el nombre Anita cuando no existen
     * (impresoras de comanda/factura/hotel). Si false, usa salida_default_id.
     */
    'crear_salidas_faltantes' => filter_var(
        env('CONFIG_PV_GASTRONOMIA_SYNC_CREAR_SALIDAS', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /** Salida ERP de respaldo (comanda / factura) si Anita viene vacío o no se crean. */
    'salida_default_id' => (int) env('CONFIG_PV_GASTRONOMIA_SYNC_SALIDA_DEFAULT_ID', 1),

    /** Tipo transacción factura (abreviatura FAC) y NC (NCD) si no se resuelve por cpvta_tipo_fact. */
    'tipotransaccion_factura_abreviatura' => env('CONFIG_PV_GASTRONOMIA_SYNC_TIPO_FAC', 'FAC'),
    'tipotransaccion_nc_abreviatura' => env('CONFIG_PV_GASTRONOMIA_SYNC_TIPO_NC', 'NCD'),
];
