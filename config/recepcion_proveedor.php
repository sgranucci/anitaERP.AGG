<?php

return [

    /*
    | Contabilidad de recepción de proveedores (AGG: activa).
    */
    'contabilidad_activa' => filter_var(env('RECEPCION_PROVEEDOR_CONTABILIDAD_ACTIVA', true), FILTER_VALIDATE_BOOLEAN),

    'anita' => [
        'sistema_compras' => env('RECEPCION_PROVEEDOR_ANITA_SISTEMA', 'compras'),
        'sistema_contab' => env('RECEPCION_PROVEEDOR_ANITA_CONTAB', 'contab'),
        'sistema_stk_parte_unica' => env('RECEPCION_PROVEEDOR_STK_PARTE_UNICA_SISTEMA', 'base_admin'),
        'tablas' => [
            'recepcion_cabecera' => 'recepmae',
            'recepcion_linea' => 'recepmov',
            'oc_cabecera' => 'pendmaep',
            'oc_linea' => 'pendmovp',
            'aplicacion_oc' => 'aplicped',
            'subdiario' => 'subdiario',
            'cuenta' => 'ctamae',
            'recepcion_parte_unica' => 'recpunica',
            'articulo_parte_unica' => 'stk_parte_unica',
        ],
        'oc_tipo' => 'PEP',
        'oc_letra' => 'X',
        'oc_sucursal' => 0,
        'recepcion_tipo' => 'COM',
        'recepcion_letra' => 'X',
        'recepcion_estado_confirmada' => env('RECEPCION_PROVEEDOR_ANITA_ESTADO_CONFIRMADA', '2'),
        'recepcion_estado_anulada' => env('RECEPCION_PROVEEDOR_ANITA_ESTADO_ANULADA', '3'),
    ],

    'ocr' => [
        'habilitado' => filter_var(env('RECEPCION_PROVEEDOR_OCR_HABILITADO', false), FILTER_VALIDATE_BOOLEAN),
        'driver' => env('RECEPCION_PROVEEDOR_OCR_DRIVER', 'stub'),
    ],

    'sku_prefijo_laboratorio' => env('RECEPCION_PROVEEDOR_SKU_PREFIJO_LAB', 'LAB'),

    'usoarticulo_laboratorio_ids' => array_map('intval', array_filter(explode(',', env('RECEPCION_PROVEEDOR_USOARTICULO_LAB_IDS', '3')))),

    'tolerancia_default' => [
        'cantidad_pct' => (float) env('RECEPCION_PROVEEDOR_TOL_CANTIDAD_PCT', 0),
        'precio_pct' => (float) env('RECEPCION_PROVEEDOR_TOL_PRECIO_PCT', 0),
        'precio_absoluto' => (float) env('RECEPCION_PROVEEDOR_TOL_PRECIO_ABS', 0),
    ],

];
