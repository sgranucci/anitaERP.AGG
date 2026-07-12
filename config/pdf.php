<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DomPDF — tamaño de hoja por defecto
    |--------------------------------------------------------------------------
    |
    | Valores DomPDF habituales: a4, legal (oficio), letter.
    | Override por contexto con PDF_LISTADO_* o PDF_COMPROBANTE_*.
    |
    */
    'tamano' => strtolower((string) env('PDF_TAMANO', 'a4')),

    'orientacion' => strtolower((string) env('PDF_ORIENTACION', 'portrait')),

    /*
    | Listados export (tablas anchas, index/reportes).
    */
    'listado' => [
        'tamano' => env('PDF_LISTADO_TAMANO') !== null && env('PDF_LISTADO_TAMANO') !== ''
            ? strtolower((string) env('PDF_LISTADO_TAMANO'))
            : null,
        'orientacion' => strtolower((string) env('PDF_LISTADO_ORIENTACION', 'landscape')),
    ],

    /*
    | Comprobantes / documentos individuales (COM recepción, mov. stock, cierres, etc.).
    */
    'comprobante' => [
        'tamano' => env('PDF_COMPROBANTE_TAMANO') !== null && env('PDF_COMPROBANTE_TAMANO') !== ''
            ? strtolower((string) env('PDF_COMPROBANTE_TAMANO'))
            : null,
        'orientacion' => strtolower((string) env('PDF_COMPROBANTE_ORIENTACION', 'portrait')),
    ],

];
