<?php

/**
 * Mapeo Anita → anitaERP para sincronización de mozos de gastronomía.
 * Tabla origen (Informix): vendedor → mozo_gastronomia
 *
 * Campos Anita (vendedor.sql):
 *   vend_codigo, vend_nombre, vend_empresa
 */
return [
    'tabla' => 'vendedor',

    /** Sistema Informix en apiERP.php (null = sin cláusula de sistema). */
    'sistema' => env('MOZO_GASTRONOMIA_SYNC_ANITA_SISTEMA', 'ventas'),

    'empresa_default_id' => (int) env('MOZO_GASTRONOMIA_SYNC_EMPRESA_ID', 0) ?: (int) (config('cliente.EMPRESA_DEFAULT_ID') ?? 1),

    'campos_listado' => 'vend_codigo, vend_nombre, vend_empresa',

    'tabla_password' => 'mozopasswd',

    'campos_password' => 'mozp_mozo, mozp_password',

    /** Clave ERP cuando Anita no tiene fila en mozopasswd. */
    'clave_default' => env('MOZO_GASTRONOMIA_CLAVE_DEFAULT', '12345'),

    'mapeo' => [
        ['origen' => 'vend_codigo', 'destino' => 'codigo', 'transform' => 'codigo_anita'],
        ['origen' => 'vend_nombre', 'destino' => 'nombre', 'transform' => 'trim'],
        ['origen' => 'vend_empresa', 'destino' => 'empresa_id', 'transform' => 'empresa_por_codigo_anita'],
    ],
];
