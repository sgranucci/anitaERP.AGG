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
 */
return [
    'tabla' => 'descuento',

    /** Sistema Informix en apiERP.php (null = sin cláusula de sistema). */
    'sistema' => env('DESCUENTO_GASTRONOMIA_SYNC_ANITA_SISTEMA', 'ventas'),

    'campos_listado' => 'dto_codigo, dto_desc, dto_tipo_valor, dto_valor, dto_cliente',
];
