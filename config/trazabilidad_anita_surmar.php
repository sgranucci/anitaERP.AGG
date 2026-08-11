<?php

/**
 * Import trazabilidad Anita Surmar (t_comp, recepaper, stkmov, stkvaper, apcom).
 * Una lectura por tabla (sin particionar por mes). No escribe de vuelta a Anita.
 */
return [
    'path_sistema' => env(
        'TRAZABILIDAD_SURMAR_ANITA_PATH',
        env('ANITA_SURMAR_PATH', env('RECEPCION_SURMAR_ANITA_PATH', '/usr2/surmar'))
    ),
    'sistema_compras' => env('TRAZABILIDAD_SURMAR_ANITA_SISTEMA_COMPRAS', 'compras'),
    'sistema_ventas' => env('TRAZABILIDAD_SURMAR_ANITA_SISTEMA_VENTAS', 'ventas'),

    'fecha_desde' => (int) env('TRAZABILIDAD_SURMAR_SYNC_FECHA_DESDE', 20260101),
    'fecha_hasta' => (int) env('TRAZABILIDAD_SURMAR_SYNC_FECHA_HASTA', 0),

    'empresa_id' => (int) env('TRAZABILIDAD_SURMAR_EMPRESA_ID', 3),

    /** Prefijo leyenda movimientostock para idempotencia. */
    'leyenda_prefix' => 'ANITA_SURMAR',

    /** Tras import stkmov: reconstruir saldos solo en depósitos empresa Surmar. */
    'reconstruir_saldos' => (bool) env('TRAZABILIDAD_SURMAR_RECONSTRUIR_SALDOS', true),
];
