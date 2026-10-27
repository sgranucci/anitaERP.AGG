<?php
// Constantes de lectura automatica de cotizaciones y moneda default del sistema

return [
    'monedaIdCommand' => 2,
    // Euro (moneda.id 3). El cron cotizacion:leeapi lo graba junto con el dólar.
    'monedaIdEuroCommand' => 3,
    'usuarioIdCommand' => (int) env('COTIZACION_USUARIO_ID', 1),
    'ID_MONEDA_DEFAULT' => 1,
    'ABREVIATURA_LOCAL' => env('COTIZACION_ABREVIATURA_LOCAL', 'ARS'),
    // Cron cotizacion:leeapi — después de publicación BNA (~10hs). 06:00 tomaba día anterior.
    'hora_command' => env('COTIZACION_CRON_HORA', '11:00'),
    // Salida HTTPS del dólar oficial. Vacío = el proxy del padrón ARCA (misma salida del servidor).
    'proxy_host' => env('COTIZACION_PROXY_HOST', env('ARCA_PADRON_PROXY_HOST', '')),
    'proxy_port' => (int) env('COTIZACION_PROXY_PORT', env('ARCA_PADRON_PROXY_PORT', 3128)),
    'proxy_user' => env('COTIZACION_PROXY_USER', env('ARCA_PADRON_PROXY_USER', '')),
    'proxy_password' => env('COTIZACION_PROXY_PASSWORD', env('ARCA_PADRON_PROXY_PASSWORD', '')),
];
