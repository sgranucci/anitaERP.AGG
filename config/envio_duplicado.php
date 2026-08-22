<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bloqueo de envíos duplicados (doble click en Guardar)
    |--------------------------------------------------------------------------
    |
    | Red de seguridad de App\Http\Middleware\PrevenirEnvioDuplicado: descarta
    | un POST/PUT/DELETE idéntico del mismo usuario repetido dentro de la
    | ventana. El bloqueo de pantalla vive en assets/js/grabacion-bloqueo-submit.js;
    | esto cubre cuando el navegador igual manda el segundo envío.
    |
    */

    'habilitado' => filter_var(env('ENVIO_DUPLICADO_BLOQUEO', true), FILTER_VALIDATE_BOOLEAN),

    'ventana_segundos' => (int) env('ENVIO_DUPLICADO_VENTANA_SEGUNDOS', 12),

    /*
    | Rutas que repiten el mismo POST de forma legítima (consultas de modales,
    | resolución por código, endpoints de proceso). Patrones estilo Request::is().
    */
    'rutas_excluidas' => [
        'api/*',
        '*/api/*',
        '*consulta*',
        '*/leer*',
        '*/resolver*',
        '*/preview*',
        'ajax-sesion',
        'login',
        'logout',
    ],

];
