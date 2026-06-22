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
    | Estructura relativa: {CUIT}/{Y-m}/{TIPO}-{letra}-{sucursal}-{nro}.pdf
    | Ejemplo absoluto: /data/facturas/comprobantes/30-65781386-5/2026-02/FGA-A-00003-00946427.pdf
    |
    */
    'facturas_scan_base' => env('PRECARGA_FACTURAS_SCAN_BASE', '/Facturas_scan'),

    /*
    | Canal Laravel para trazas de la API de precarga (storage/logs/precarga_proveedor_api.log).
    */
    'log_channel' => env('PRECARGA_PROVEEDOR_API_LOG_CHANNEL', 'precarga_proveedor_api'),
];
