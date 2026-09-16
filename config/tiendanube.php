<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tiendanube — facturación de pedidos (Ferli)
    |--------------------------------------------------------------------------
    | Credenciales y defaults del canal e-commerce. Emisión vía FacturacionService
    | (ARCA + stock + cobranza). Solo pedidos payment_status=paid.
    */

    'store_id' => env('TIENDANUBE_STORE_ID', '3796054'),

    'access_token' => env('TIENDANUBE_ACCESS_TOKEN', ''),

    'user_agent' => env('TIENDANUBE_USER_AGENT', 'anitaERP Integracion Facturacion (sergiogranucci@gmail.com)'),

    'api_base' => env('TIENDANUBE_API_BASE', 'https://api.tiendanube.com/v1'),

    'empresa_id' => (int) env('TIENDANUBE_EMPRESA_ID', 1),

    // PV default Ferli Nube (codigo 00023)
    'puntoventa_codigo_default' => env('TIENDANUBE_PUNTOVENTA_CODIGO', '00023'),

    // Códigos PV online seleccionables en pantalla (Excel locales)
    'puntoventa_codigos_online' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TIENDANUBE_PUNTOVENTA_CODIGOS', '00021,00023,00026,00027'))
    ))),

    'deposito_codigo_default' => env('TIENDANUBE_DEPOSITO_CODIGO', '10'),

    'listaprecio_codigo_default' => env('TIENDANUBE_LISTAPRECIO_CODIGO', '12'),

    'tipotransaccion_fac_id' => (int) env('TIENDANUBE_TIPO_FAC_ID', 1),

    'tipotransaccion_nc_id' => (int) env('TIENDANUBE_TIPO_NC_ID', 2),

    'tipotransaccion_caja_id' => (int) env('TIENDANUBE_TIPO_CAJA_ID', 1),

    'moneda_id' => (int) env('TIENDANUBE_MONEDA_ID', 1),

    'cliente_contado_id' => (int) env('TIENDANUBE_CLIENTE_CONTADO_ID', 1),

    // Artículo para flete / envío (SKU ERP Ferli = FL).
    'articulo_envio_sku' => env('TIENDANUBE_ARTICULO_ENVIO_SKU', 'FL'),

    // Artículo para descuentos / cupones (importe negativo). Vacío = descuento pie.
    'articulo_descuento_sku' => env('TIENDANUBE_ARTICULO_DESCUENTO_SKU', ''),

    // Uso de cuentas de caja del canal (maestro usocuentacaja).
    'usocuentacaja_nombre' => env('TIENDANUBE_USO_CUENTACAJA', 'TIENDA NUBE'),

    'genera_contabilidad_cobranza' => filter_var(
        env('TIENDANUBE_GENERA_CONTABILIDAD_COBRANZA', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    'sync_page_size' => (int) env('TIENDANUBE_SYNC_PAGE_SIZE', 50),

    // Subir invoice a TN tras emitir (requiere write_orders). Off hasta ampliar scopes.
    'publicar_factura_en_pedido' => filter_var(
        env('TIENDANUBE_PUBLICAR_FACTURA', false),
        FILTER_VALIDATE_BOOLEAN
    ),
];
