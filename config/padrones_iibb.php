<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cola dedicada para importación masiva de padrones IIBB (CABA/ARBA)
    |--------------------------------------------------------------------------
    |
    | Worker: deploy/supervisor/anitaERP-queue-padrones.conf
    | TIMEOUT del job y --timeout del worker deben ser <= QUEUE_RETRY_AFTER.
    |
    */

    'cola' => env('PADRON_IIBB_COLA', 'padrones'),

    'job_timeout' => (int) env('PADRON_IIBB_JOB_TIMEOUT', 7200),

    'batch_caba' => (int) env('PADRON_IIBB_BATCH_CABA', 2000),

    'batch_arba' => (int) env('PADRON_IIBB_BATCH_ARBA', 5000),

    'pause_ms' => (int) env('PADRON_IIBB_PAUSE_MS', 20),

    /** Destinatarios del mail al terminar (o fallar) la carga. Separados por coma. */
    'notificar_email' => env('PADRON_IIBB_NOTIFICAR_EMAIL', env('QUEUE_VERIFICACION_PICO_EMAIL', '')),

    /*
    |--------------------------------------------------------------------------
    | ARBA DFE — descarga automática (ex bajapadron.sh)
    |--------------------------------------------------------------------------
    */

    'arba' => [
        'user' => env('ARBA_DFE_USER', ''),
        'password' => env('ARBA_DFE_PASSWORD', ''),
        'directorio' => env('PADRON_IIBB_ARBA_DIR', storage_path('app/padrones/arba')),
        'curl_timeout' => (int) env('PADRON_IIBB_ARBA_CURL_TIMEOUT', 600),
        'sync_habilitado' => filter_var(env('PADRON_IIBB_ARBA_SYNC_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        // siguiente = próximo mes (último día del mes); actual = mes en curso
        'sync_periodo' => env('PADRON_IIBB_ARBA_SYNC_PERIODO', 'siguiente'),
        'sync_hora' => env('PADRON_IIBB_ARBA_SYNC_HORA', '22:00'),
    ],

];
