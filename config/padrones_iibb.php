<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cola dedicada para importación masiva de padrones IIBB (CABA/ARBA/Santa Fe)
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

    'batch_santafe' => (int) env('PADRON_IIBB_BATCH_SANTAFE', 3000),

    /** Córdoba, Entre Ríos, Misiones y Tucumán (padron_iibb_tasa). */
    'batch_provincia' => (int) env('PADRON_IIBB_BATCH_PROVINCIA', 3000),

    'pause_ms' => (int) env('PADRON_IIBB_PAUSE_MS', 20),

    /*
    |--------------------------------------------------------------------------
    | Directorios habilitados para el campo "ruta en servidor"
    |--------------------------------------------------------------------------
    |
    | Separados por coma. storage/app siempre está permitido y no hace falta
    | listarlo. Evita que la pantalla pueda leer cualquier archivo del servidor.
    |
    */

    'directorios' => env(
        'PADRON_IIBB_DIRS',
        '/home/sergio/padroncaba,/home/sergio/padronarba,/home/sergio/padronsantafe,'
        .         '/home/sergio/padronmisiones,/home/sergio/padroncordoba,'
        . '/home/sergio/padronerios,/home/sergio/padrontucuman'
    ),

    /** Destinatarios del mail al terminar (o fallar) la carga. Separados por coma. */
    'notificar_email' => env('PADRON_IIBB_NOTIFICAR_EMAIL', env('QUEUE_VERIFICACION_PICO_EMAIL', '')),

    /** Aviso por mail cuando un padrón no cubre el período en curso. */
    'alertar_vencidos' => filter_var(env('PADRON_IIBB_ALERTAR_VENCIDOS', true), FILTER_VALIDATE_BOOLEAN),

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
        'sync_hora' => env('PADRON_IIBB_ARBA_SYNC_HORA', '19:00'),
    ],

];
