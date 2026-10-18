<?php

/**
 * Mapeo Anita → anitaERP para sincronización de descuentos de gastronomía.
 * Tabla origen (Informix): descuento → descuento_gastronomia
 *
 * Campos Anita (descuento.sql):
 *   dto_codigo, dto_desc, dto_tipo_valor, dto_valor, dto_cliente
 *
 * dto_tipo_valor: P = porcentaje, I = importe, A = aplica
 * dto_cliente: código cliente de consumo interno / invitación (distinto al de facturación)
 *
 * CROWN: bridge cncadmin (misma pauta que mesa/mozo/caja).
 */
return [
    'tabla' => 'descuento',

    /** Sistema Informix en apiERP.php (null = sin cláusula de sistema). */
    'sistema' => env('DESCUENTO_GASTRONOMIA_SYNC_ANITA_SISTEMA', 'ventas'),

    /** CROWN: cncadmin (Cipolletti). Vacío = bridge global (AGG). */
    'ifx_server' => env('DESCUENTO_GASTRONOMIA_SYNC_ANITA_IFX_SERVER', ''),
    'path_sistema' => env('DESCUENTO_GASTRONOMIA_SYNC_ANITA_PATH', ''),

    'empresa_default_id' => (int) env('DESCUENTO_GASTRONOMIA_SYNC_EMPRESA_ID', 0) ?: (int) (config('cliente.EMPRESA_DEFAULT_ID') ?? 1),

    /** CROWN: código sala (ej. CIP). Vacío = sin sala_id. */
    'sala_codigo' => env('DESCUENTO_GASTRONOMIA_SYNC_SALA_CODIGO', ''),

    /**
     * Crown/cncadmin no tiene dto_cliente en la tabla descuento (Informix).
     * Si AGG agrega el campo, se puede sumar vía DESCUENTO_GASTRONOMIA_SYNC_CAMPOS.
     */
    'campos_listado' => env(
        'DESCUENTO_GASTRONOMIA_SYNC_CAMPOS',
        'dto_codigo, dto_desc, dto_tipo_valor, dto_valor'
    ),
];
