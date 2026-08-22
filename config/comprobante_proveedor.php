<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Archivos PDF de comprobantes (montaje compartido con precarga IA)
    |--------------------------------------------------------------------------
    |
    | Base: PRECARGA_FACTURAS_SCAN_BASE (ej. /data/facturas o /Facturas_scan).
    | Estructura: {base}/comprobantes/{CUIT}/{Y-m}/{TIPO}-{letra}-{sucursal}-{nro}.pdf
    | Ejemplo: /data/facturas/comprobantes/30-65781386-5/2026-02/FGA-A-00003-00946427.pdf
    |
    | - Precarga IA: rutaalmacenamiento → storage:/comprobantes/...
    | - Alta manual sin precarga: mismo montaje vía ComprobanteProveedorArchivoPathSupport
    | - Otros adjuntos (no PDF factura): public/storage/archivos/comprobantes_proveedor/{id}/
    |
    */
    'archivos_subdir' => env('COMPROBANTE_PROVEEDOR_ARCHIVOS_SUBDIR', 'archivos/comprobantes_proveedor'),

    /*
    | Tablas Informix sincronizadas vía bridge (create/update/delete).
    | Contabilidad: solo ctamov en Anita (como facturación); no subdiario.
    */
    'anita_tablas_sync' => [
        'compra',
        'concmov',
        'promov',
        'aplmovp',
        'aplicped',
    ],

    'anita_sistema_compras' => 'compras',
    'anita_sistema_contab' => 'contab',
    'anita_tabla_ctamov' => 'ctamov',
    'anita_tabla_aplmovp' => 'aplmovp',

    /*
    | Nro. interno compra (Anita a-compprov lee_num_comp "INT"):
    | t_comp compras INT → tcomp_refer → numerador ventas (hoy 208).
    */
    'anita_tcomp_clave_interno' => env('COMPROBANTE_PROVEEDOR_ANITA_TCOMP_INTERNO', 'INT'),
    'anita_sistema_tcomp' => env('COMPROBANTE_PROVEEDOR_ANITA_SISTEMA_TCOMP', 'compras'),
    'anita_sistema_numerador' => env('COMPROBANTE_PROVEEDOR_ANITA_SISTEMA_NUMERADOR', 'ventas'),
    'numeracion_lock_segundos' => (int) env('COMPROBANTE_PROVEEDOR_NUMERACION_LOCK_SEGUNDOS', 20),

    'import_anita' => [
        'usuario_id' => (int) env('COMPROBANTE_PROVEEDOR_IMPORT_USUARIO_ID', 1),
        'formapago_id' => (int) env('COMPROBANTE_PROVEEDOR_IMPORT_FORMAPAGO_ID', 1),
    ],

    'tipoasiento_abreviatura' => env('COMPROBANTE_PROVEEDOR_TIPOASIENTO', 'COM'),
];
