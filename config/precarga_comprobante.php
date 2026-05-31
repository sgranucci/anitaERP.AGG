<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Carpeta de facturas escaneadas (precarga compras)
    |--------------------------------------------------------------------------
    |
    | Montaje de facturas escaneadas. Los PDF se leen siempre desde
    | {facturas_scan_base}/comprobantes/ (rutaalmacenamiento: storage:/comprobantes/...
    | o storage:/facturas/... — ambos se normalizan a comprobantes).
    |
    */
    'facturas_scan_base' => env('PRECARGA_FACTURAS_SCAN_BASE', '/Facturas_scan'),
];
