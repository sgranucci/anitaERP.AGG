<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Archivos del comprobante de proveedor (ERP)
    |--------------------------------------------------------------------------
    |
    | Adjuntos manuales bajo public/storage/archivos/comprobantes_proveedor/{id}/.
    | Los PDF de IA siguen en PRECARGA_FACTURAS_SCAN_BASE/comprobantes (ver precarga_comprobante.php).
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
];
