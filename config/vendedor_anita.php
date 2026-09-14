<?php

/**
 * Sincronización Anita (Informix) → vendedor del ERP.
 * Tabla origen: vendedor (sistema ventas).
 */
return [
    'tabla' => 'vendedor',

    'sistema' => env('VENDEDOR_SYNC_ANITA_SISTEMA', 'ventas'),

    'empresa_id_default' => (int) env('VENDEDOR_SYNC_EMPRESA_ID', 0) ?: 1,

    /**
     * Columnas del SELECT contra Informix.
     * Si su base no tiene vend_email / vend_estado, defina VENDEDOR_SYNC_ANITA_CAMPOS_LISTADO sin esas columnas.
     * Calzados Ferli: esquema Anita sin vend_email/vend_estado (UNLOAD falla con el default AGG).
     */
    'campos_listado' => env('VENDEDOR_SYNC_ANITA_CAMPOS_LISTADO') ?: (
        str_contains(strtoupper((string) env('EMPRESA', '')), 'FERLI')
            ? 'vend_codigo,vend_nombre,vend_comision_vta,vend_comision_cob,vend_aplicacion,vend_empresa,vend_legajo'
            : 'vend_codigo,vend_nombre,vend_comision_vta,vend_comision_cob,vend_aplicacion,vend_empresa,vend_legajo,vend_email,vend_estado'
    ),
];
