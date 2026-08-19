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
    ],

    'anita_sistema_compras' => 'compras',
    'anita_sistema_contab' => 'contab',
    'anita_tabla_ctamov' => 'ctamov',
    'anita_tabla_aplmovp' => 'aplmovp',

    'import_anita' => [
        'usuario_id' => (int) env('COMPROBANTE_PROVEEDOR_IMPORT_USUARIO_ID', 1),
        'formapago_id' => (int) env('COMPROBANTE_PROVEEDOR_IMPORT_FORMAPAGO_ID', 1),
    ],

    'tipoasiento_abreviatura' => env('COMPROBANTE_PROVEEDOR_TIPOASIENTO', 'COM'),
];
