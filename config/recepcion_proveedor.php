<?php

return [

    /*
    | Contabilidad de recepción de proveedores (AGG: activa).
    */
    'contabilidad_activa' => filter_var(env('RECEPCION_PROVEEDOR_CONTABILIDAD_ACTIVA', true), FILTER_VALIDATE_BOOLEAN),

    // Al confirmar recepción: actualizar ordencompra_articulo.precio y pendmovp.penvp_precio si difiere de la OC.
    'actualizar_precio_oc_al_confirmar' => filter_var(env('RECEPCION_PROVEEDOR_ACTUALIZAR_PRECIO_OC_AL_CONFIRMAR', true), FILTER_VALIDATE_BOOLEAN),

    'anita' => [
        'sistema_compras' => env('RECEPCION_PROVEEDOR_ANITA_SISTEMA', 'compras'),
        // stkmov vive en Informix ventas (mismo bridge que facturación), no en compras.
        'sistema_ventas' => env('RECEPCION_PROVEEDOR_ANITA_SISTEMA_VENTAS', 'ventas'),
        // false: COM no replica stkmov (stock en ERP); sigue recepmae/recepmov/aplicped/pendmovp.
        'stkmov_habilitado' => filter_var(env('RECEPCION_PROVEEDOR_ANITA_STKMOV_HABILITADO', false), FILTER_VALIDATE_BOOLEAN),
        // Clave t_comp (ventas) para resolver tcomp_refer → numerador COM
        't_comp_clave_numerador' => env('RECEPCION_PROVEEDOR_ANITA_T_COMP_CLAVE', 'COM'),
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
            'stock_movimiento' => 'stkmov',
            'stock_maestro' => 'stkmae',
        ],
        'oc_tipo' => 'PEP',
        'oc_letra' => 'X',
        'oc_sucursal' => 0,
        'recepcion_tipo' => 'COM',
        'recepcion_letra' => 'X',
        // false: opcional reservar vía numerador al calcular; siempre se actualiza num_clave 120 al asignar COM en ERP
        'reservar_numerador_anita' => filter_var(env('RECEPCION_PROVEEDOR_RESERVAR_NUMERADOR_ANITA', false), FILTER_VALIDATE_BOOLEAN),
        'recepcion_estado_confirmada' => env('RECEPCION_PROVEEDOR_ANITA_ESTADO_CONFIRMADA', '2'),
        'recepcion_estado_anulada' => env('RECEPCION_PROVEEDOR_ANITA_ESTADO_ANULADA', '3'),
    ],

    /*
    | Informe COM: períodos anteriores al primer COM ERP se leen de Anita (una consulta).
    | Vacío = MIN(recepcion_proveedor.fecha). Forzar corte: 2025-01-01.
    */
    'reporte' => [
        'anita_corte_erp' => env('RECEPCION_PROVEEDOR_REPORTE_ANITA_CORTE_ERP', ''),
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
        // Completar EAN-13 truncado por OCR (prioriza cantidad; dejar false si el barcode incompleto confunde)
        'completar_ean' => filter_var(env('RECEPCION_PROVEEDOR_OCR_COMPLETAR_EAN', false), FILTER_VALIDATE_BOOLEAN),
        // Prefijos de OC de 6 dígitos separados por coma (ej. 2 → 221067)
        'oc_prefijos' => env('RECEPCION_PROVEEDOR_OCR_OC_PREFIJOS', '2'),
    ],

    'sku_prefijo_laboratorio' => env('RECEPCION_PROVEEDOR_SKU_PREFIJO_LAB', 'LAB'),

    /** Artículo contable del impuesto interno en recepciones de cigarrillos (cuenta COMPRAS del maestro). */
    'sku_articulo_impuesto_interno' => env('RECEPCION_PROVEEDOR_SKU_ARTICULO_IMPUESTO_INTERNO', 'IMPINTERNO'),

    'usoarticulo_laboratorio_ids' => array_map('intval', array_filter(explode(',', env('RECEPCION_PROVEEDOR_USOARTICULO_LAB_IDS', '3')))),

    'encuesta_habilitada' => filter_var(env('RECEPCION_PROVEEDOR_ENCUESTA_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),

    'encuesta_id' => (int) env('RECEPCION_PROVEEDOR_ENCUESTA_ID', 1),

    /*
    | Modal previo al guardar: validar/completar datos que se grabarán en articulo_proveedor.
    | AGG: true. Otros sitios pueden desactivarlo si no usan catálogo por recepción.
    */
    'modal_articulo_proveedor_habilitado' => filter_var(
        env('RECEPCION_PROVEEDOR_MODAL_ARTICULO_PROVEEDOR', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    'tolerancia_default' => [
        'cantidad_pct' => (float) env('RECEPCION_PROVEEDOR_TOL_CANTIDAD_PCT', 0),
        'precio_pct' => (float) env('RECEPCION_PROVEEDOR_TOL_PRECIO_PCT', 0),
        'precio_absoluto' => (float) env('RECEPCION_PROVEEDOR_TOL_PRECIO_ABS', 0),
    ],

    // Diferencia máxima (moneda recepción) entre total ítems y asiento debe/haber al confirmar
    'tolerancia_cuadre_contable' => (float) env('RECEPCION_PROVEEDOR_TOL_CUADRE_CONTABLE', 0.02),

    /*
    | Auditoría diaria asientos COM: ERP ↔ ctamov Anita (recepcion-proveedor:auditoria-asientos-com).
    | Incluye RECEPCION y DEVOLUCION. Control extra: devolución vs II de la recepción origen.
    */
    'auditoria_asientos_com_diaria' => [
        'habilitada' => filter_var(env('RECEPCION_PROVEEDOR_AUDITORIA_ASIENTOS_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('RECEPCION_PROVEEDOR_AUDITORIA_ASIENTOS_HORA', '07:45'),
        'usuario_id' => (int) env('RECEPCION_PROVEEDOR_AUDITORIA_ASIENTOS_USUARIO_ID', 1),
        'email' => env('RECEPCION_PROVEEDOR_AUDITORIA_ASIENTOS_EMAIL', 'sergiogranucci@gmail.com'),
        'tolerancia' => (float) env('RECEPCION_PROVEEDOR_AUDITORIA_ASIENTOS_TOLERANCIA', 0.02),
        'incluir_importadas_anita' => filter_var(env('RECEPCION_PROVEEDOR_AUDITORIA_ASIENTOS_INCLUIR_IMPORTADAS', false), FILTER_VALIDATE_BOOLEAN),
        // Días calendario inclusive hasta hoy que audita el cron diario (--desde / --hasta).
        'ventana_dias' => max(1, (int) env('RECEPCION_PROVEEDOR_AUDITORIA_ASIENTOS_VENTANA_DIAS', 7)),
        // Ante discrepancia: repara Anita (ctamov/recepmae) y, si falta II en devolución, recuadra asiento+II.
        // Si el período está cerrado no repara: avisa en el mail para revisión manual.
        // Si ctamov fue editado en Anita (ctav_fecha_umod/usuario) y los montos coinciden, no se pisa Anita: se copia al ERP.
        'auto_reparar' => filter_var(env('RECEPCION_PROVEEDOR_AUDITORIA_ASIENTOS_AUTO_REPARAR', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    | Tras confirmar COM en ERP: job en cola verifica recepmae + ctamov Anita y repara.
    */
    'anita_tras_confirmacion' => [
        'habilitada' => filter_var(env('RECEPCION_PROVEEDOR_ANITA_TRAS_CONFIRMACION', true), FILTER_VALIDATE_BOOLEAN),
        'usuario_id' => (int) env('RECEPCION_PROVEEDOR_ANITA_TRAS_CONFIRMACION_USUARIO_ID', 1),
        'cola' => env('RECEPCION_PROVEEDOR_ANITA_TRAS_CONFIRMACION_COLA', 'default'),
        'job_tries' => max(1, (int) env('RECEPCION_PROVEEDOR_ANITA_TRAS_CONFIRMACION_JOB_TRIES', 3)),
        'job_backoff_segundos' => array_map('intval', explode(',', env('RECEPCION_PROVEEDOR_ANITA_TRAS_CONFIRMACION_JOB_BACKOFF', '30,120,600'))),
        'job_timeout' => max(60, (int) env('RECEPCION_PROVEEDOR_ANITA_TRAS_CONFIRMACION_JOB_TIMEOUT', 300)),
        // Segundo pase diferido (0 = desactivado) para detectar ctamov que desaparece tras un OK.
        'recheck_delay_segundos' => max(0, (int) env('RECEPCION_PROVEEDOR_ANITA_TRAS_CONFIRMACION_RECHECK_DELAY', 600)),
    ],

];
