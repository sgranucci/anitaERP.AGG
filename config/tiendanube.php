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

    // Cliente CF (letra B) — Ferli: CONSUMIDOR FINAL import Anita
    'cliente_cf_id' => (int) env('TIENDANUBE_CLIENTE_CF_ID', 2194),

    // Cliente RI base para Factura A (percepciones según padrón)
    'cliente_ri_id' => (int) env('TIENDANUBE_CLIENTE_RI_ID', 1),

    // Artículo para flete / envío (SKU ERP Ferli = FL).
    'articulo_envio_sku' => env('TIENDANUBE_ARTICULO_ENVIO_SKU', 'FL'),

    // Artículo para descuentos / cupones (importe negativo). Vacío = descuento pie.
    'articulo_descuento_sku' => env('TIENDANUBE_ARTICULO_DESCUENTO_SKU', ''),

    // Uso de cuentas de caja del canal (maestro usocuentacaja).
    'usocuentacaja_nombre' => env('TIENDANUBE_USO_CUENTACAJA', 'TIENDA NUBE'),

    // Mapa opcional gateway/method → id o código cuentacaja (JSON). Vacío = heurística + uso.
    // Ej: {"credit_card":"611","custom":"609","tarjeta_naranja":"611"}
    'gateway_cuentacaja' => json_decode((string) env('TIENDANUBE_GATEWAY_CUENTACAJA', '{}'), true) ?: [],

    'genera_contabilidad_cobranza' => filter_var(
        env('TIENDANUBE_GENERA_CONTABILIDAD_COBRANZA', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    'sync_page_size' => (int) env('TIENDANUBE_SYNC_PAGE_SIZE', 50),

    // Subir invoice a TN tras emitir (requiere write_orders).
    'publicar_factura_en_pedido' => filter_var(
        env('TIENDANUBE_PUBLICAR_FACTURA', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    // Mail automático al email del comprador TN al emitir.
    'enviar_factura_mail' => filter_var(
        env('TIENDANUBE_ENVIAR_FACTURA_MAIL', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    |--------------------------------------------------------------------------
    | Salud API / sync automático (Ferli)
    |--------------------------------------------------------------------------
    | El access_token NO se renueva solo. Health check + sync cron detectan 401
    | y avisan por mail / banner en pantalla.
    */
    'health_cron_habilitado' => filter_var(
        env('TIENDANUBE_HEALTH_CRON_HABILITADO', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    'health_cache_segundos' => (int) env('TIENDANUBE_HEALTH_CACHE_SEGUNDOS', 300),

    'sync_cron_habilitado' => filter_var(
        env('TIENDANUBE_SYNC_CRON_HABILITADO', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    'sync_cron_dias' => (int) env('TIENDANUBE_SYNC_CRON_DIAS', 7),

    // Banner si el último sync OK es más viejo que esto.
    'sync_stale_horas' => (int) env('TIENDANUBE_SYNC_STALE_HORAS', 36),

    'alerta_email_habilitado' => filter_var(
        env('TIENDANUBE_ALERTA_EMAIL_HABILITADO', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    'alerta_email' => env('TIENDANUBE_ALERTA_EMAIL', 'sergiogranucci@gmail.com'),

    'alerta_email_throttle_horas' => (int) env('TIENDANUBE_ALERTA_EMAIL_THROTTLE_HORAS', 6),
];
