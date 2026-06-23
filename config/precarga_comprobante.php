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

    /*
    | Reintentos al consultar OC en Anita (bridge HTTP puede responder vacío de forma intermitente).
    */
    'anita_list_reintentos' => (int) env('PRECARGA_ANITA_LIST_REINTENTOS', 3),
    'anita_list_espera_ms' => (int) env('PRECARGA_ANITA_LIST_ESPERA_MS', 250),

    /*
    | URL base HTTP para el cliente Facturas_scan (sin barra final).
    | Canónica prod .210: http://10.20.30.210
    | Endpoints: POST {base}/api/comprobantes
    |            GET  {base}/api/empresas/{cuit}/orden-de-compra/{oc}/tipo-comprobante/{tipo}/conceptos
    | Alias sin /api: POST {base}/comprobantes (misma acción).
    | NO usar solo {base}/anitaERP/public — redirige (301) sin endpoint.
    */
    'api_base_url' => rtrim((string) env('PRECARGA_PROVEEDOR_API_BASE_URL', env('APP_URL', 'http://localhost')), '/'),
];
