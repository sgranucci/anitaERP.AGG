<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mercado Libre — facturación de ventas (Ferli)
    |--------------------------------------------------------------------------
    | App "Ferli ERP Facturacion". OAuth Authorization Code + Refresh Token.
    | Emisión vía FacturacionService. Una factura por pack. Medio fijo 608.
    */

    'client_id' => env('MERCADOLIBRE_CLIENT_ID', ''),

    'client_secret' => env('MERCADOLIBRE_CLIENT_SECRET', ''),

    'redirect_uri' => env(
        'MERCADOLIBRE_REDIRECT_URI',
        rtrim((string) env('APP_URL', ''), '/').'/ventas/mercadolibre-oauth/callback'
    ),

    'notifications_uri' => env(
        'MERCADOLIBRE_NOTIFICATIONS_URI',
        rtrim((string) env('APP_URL', ''), '/').'/ventas/mercadolibre-webhook'
    ),

    'auth_base' => env('MERCADOLIBRE_AUTH_BASE', 'https://auth.mercadolibre.com.ar'),

    'api_base' => env('MERCADOLIBRE_API_BASE', 'https://api.mercadolibre.com'),

    'site_id' => env('MERCADOLIBRE_SITE_ID', 'MLA'),

    // Fallback si aún no hay fila en mercadolibre_oauth_token
    'access_token' => env('MERCADOLIBRE_ACCESS_TOKEN', ''),

    'refresh_token' => env('MERCADOLIBRE_REFRESH_TOKEN', ''),

    'seller_id' => env('MERCADOLIBRE_SELLER_ID', ''),

    'empresa_id' => (int) env('MERCADOLIBRE_EMPRESA_ID', 1),

    // Local Mercadolibre (PV 21 / depósito 10)
    'puntoventa_codigo' => env('MERCADOLIBRE_PUNTOVENTA_CODIGO', '00021'),

    'deposito_codigo' => env('MERCADOLIBRE_DEPOSITO_CODIGO', '10'),

    // Full (fulfillment): PV 27 / depósito 27
    'full_puntoventa_codigo' => env('MERCADOLIBRE_FULL_PUNTOVENTA_CODIGO', '00027'),

    'full_deposito_codigo' => env('MERCADOLIBRE_FULL_DEPOSITO_CODIGO', '27'),

    'cuentacaja_codigo' => env('MERCADOLIBRE_CUENTACAJA', '608'),

    'listaprecio_codigo' => env('MERCADOLIBRE_LISTAPRECIO_CODIGO', '12'),

    'tipotransaccion_fac_id' => (int) env('MERCADOLIBRE_TIPO_FAC_ID', 1),

    'tipotransaccion_nc_id' => (int) env('MERCADOLIBRE_TIPO_NC_ID', 2),

    'tipotransaccion_caja_id' => (int) env('MERCADOLIBRE_TIPO_CAJA_ID', 0),

    'moneda_id' => (int) env('MERCADOLIBRE_MONEDA_ID', 1),

    'cliente_cf_id' => (int) env('MERCADOLIBRE_CLIENTE_CF_ID', 2194),

    'cliente_ri_id' => (int) env('MERCADOLIBRE_CLIENTE_RI_ID', 1),

    'marketplace_codigo' => (int) env('MERCADOLIBRE_MARKETPLACE_CODIGO', 3),

    'publicar_factura' => filter_var(env('MERCADOLIBRE_PUBLICAR_FACTURA', true), FILTER_VALIDATE_BOOLEAN),

    // Motivo devolución para NC automática (default 3 = arrepentimiento, vuelve stock).
    'motivo_devolucion_id' => (int) env('MERCADOLIBRE_MOTIVO_DEVOLUCION_ID', 3),

    'sync_cron_habilitado' => filter_var(env('MERCADOLIBRE_SYNC_CRON_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),

    'sync_cron_dias' => (int) env('MERCADOLIBRE_SYNC_CRON_DIAS', 7),

    'alerta_email_habilitado' => filter_var(env('MERCADOLIBRE_ALERTA_EMAIL_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),

    'alerta_email' => env('MERCADOLIBRE_ALERTA_EMAIL', 'sergiogranucci@gmail.com'),

    'user_agent' => env('MERCADOLIBRE_USER_AGENT', 'anitaERP Ferli ERP Facturacion'),
];
