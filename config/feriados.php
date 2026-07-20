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
];
