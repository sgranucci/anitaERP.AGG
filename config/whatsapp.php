<?php

return [

    /*
    | Master: si false no se intenta ningún envío WhatsApp.
    | Además hace falta cuenta activa por empresa (empresa_whatsapp_cuenta).
    */
    'habilitado' => filter_var(env('WHATSAPP_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Driver: meta_cloud | null (solo log / dry)
    */
    'driver' => env('WHATSAPP_DRIVER', 'meta_cloud'),

    'meta' => [
        'api_base' => env('WHATSAPP_META_API_BASE', 'https://graph.facebook.com'),
        'api_version_default' => env('WHATSAPP_META_API_VERSION', 'v21.0'),
        'timeout' => max(5, (int) env('WHATSAPP_META_TIMEOUT', 20)),
    ],

    /*
    | Template de texto libre (sesión) vs plantilla aprobada.
    | Por defecto texto libre; en producción Meta exige plantilla fuera de ventana 24h.
    */
    'usar_plantilla' => filter_var(env('WHATSAPP_USAR_PLANTILLA', false), FILTER_VALIDATE_BOOLEAN),
    'plantilla_nombre' => env('WHATSAPP_PLANTILLA_NOMBRE', 'anita_aviso'),
    'plantilla_idioma' => env('WHATSAPP_PLANTILLA_IDIOMA', 'es_AR'),

];
