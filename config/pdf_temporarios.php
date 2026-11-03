<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Purga de PDF temporarios (storage/pdf)
    |--------------------------------------------------------------------------
    |
    | Listados, comprobantes y etiquetas se regeneran al imprimir o exportar.
    | El archivo en disco es la copia de esa descarga, no el documento. La
    | purga diaria borra lo más viejo que PDF_TEMPORARIO_PURGA_DIAS.
    |
    | 3 días: un PDF del viernes sigue el lunes. No incluye adjuntos de
    | storage/app (archivos del comprobante, padrones, conciliación).
    |
    */

    'dias' => max(1, (int) env('PDF_TEMPORARIO_PURGA_DIAS', 3)),

    'directorios' => [
        'listados',
        'pagoproveedor',
        'ingresoegreso',
        'cliente_premio_uif',
        'ordencompra',
        'ventas',
        'caja',
        'impresion_sesion',
        'tmp',
        'recibos_lote',
        'pedido',
        'catalogo',
        'remito',
        'remitos_internos',
        'ordentrabajo',
    ],

];
