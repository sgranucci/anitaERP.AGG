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
        'driver' => env('RECEPCION_PROVEEDOR_OCR_DRIVER', 'tesseract'),
        'tesseract_bin' => env('RECEPCION_PROVEEDOR_OCR_TESSERACT_BIN', 'tesseract'),
        'tesseract_lang' => env('RECEPCION_PROVEEDOR_OCR_TESSERACT_LANG', 'spa'),
        'tesseract_psm' => (int) env('RECEPCION_PROVEEDOR_OCR_TESSERACT_PSM', 6),
        'tesseract_psm_extra' => env('RECEPCION_PROVEEDOR_OCR_TESSERACT_PSM_EXTRA', '11'),
        'pdftotext_bin' => env('RECEPCION_PROVEEDOR_OCR_PDFTOTEXT_BIN', 'pdftotext'),
        'pdftoppm_bin' => env('RECEPCION_PROVEEDOR_OCR_PDFTOPPM_BIN', 'pdftoppm'),
        'pdf_min_chars_texto' => (int) env('RECEPCION_PROVEEDOR_OCR_PDF_MIN_CHARS', 40),
        'pdf_max_paginas' => (int) env('RECEPCION_PROVEEDOR_OCR_PDF_MAX_PAGINAS', 3),
        'dpi_pdf' => (int) env('RECEPCION_PROVEEDOR_OCR_DPI_PDF', 300),
        'timeout_segundos' => (int) env('RECEPCION_PROVEEDOR_OCR_TIMEOUT', 120),
        'tmp_dir' => env('RECEPCION_PROVEEDOR_OCR_TMP_DIR', ''),
        'imagen_max_ancho' => (int) env('RECEPCION_PROVEEDOR_OCR_IMAGEN_MAX_ANCHO', 2400),
        'imagen_jpeg_calidad' => (int) env('RECEPCION_PROVEEDOR_OCR_IMAGEN_JPEG_CALIDAD', 88),
        // Prefijos de OC de 6 dígitos separados por coma (ej. 2 → 221067)
        'oc_prefijos' => env('RECEPCION_PROVEEDOR_OCR_OC_PREFIJOS', '2'),
    ],

    'sku_prefijo_laboratorio' => env('RECEPCION_PROVEEDOR_SKU_PREFIJO_LAB', 'LAB'),

    'usoarticulo_laboratorio_ids' => array_map('intval', array_filter(explode(',', env('RECEPCION_PROVEEDOR_USOARTICULO_LAB_IDS', '3')))),

    'tolerancia_default' => [
        'cantidad_pct' => (float) env('RECEPCION_PROVEEDOR_TOL_CANTIDAD_PCT', 0),
        'precio_pct' => (float) env('RECEPCION_PROVEEDOR_TOL_PRECIO_PCT', 0),
        'precio_absoluto' => (float) env('RECEPCION_PROVEEDOR_TOL_PRECIO_ABS', 0),
    ],

];
