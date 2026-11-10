<?php

return [
    /*
     * API pública para importar los feriados de Argentina.
     * El literal {year} se reemplaza por el año solicitado en tiempo de ejecución.
     * Fuente por defecto: https://argentinadatos.com (sin autenticación).
     */
    'api_url' => env('FERIADOS_API_URL', 'https://api.argentinadatos.com/v1/feriados/{year}'),

    /*
     * Timeout en segundos del request HTTP a la API de feriados.
     */
    'api_timeout' => (int) env('FERIADOS_API_TIMEOUT', 20),

    /*
     * Salida HTTPS hacia argentinadatos. Vacío = el proxy del padrón ARCA
     * (el servidor no tiene internet directo).
     */
    'proxy_host' => env('FERIADOS_PROXY_HOST', env('ARCA_PADRON_PROXY_HOST', '')),
    'proxy_port' => (int) env('FERIADOS_PROXY_PORT', env('ARCA_PADRON_PROXY_PORT', 3128)),
    'proxy_user' => env('FERIADOS_PROXY_USER', env('ARCA_PADRON_PROXY_USER', '')),
    'proxy_password' => env('FERIADOS_PROXY_PASSWORD', env('ARCA_PADRON_PROXY_PASSWORD', '')),
];
