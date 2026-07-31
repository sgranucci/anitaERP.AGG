<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for every one. Here you may define a default connection.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with Laravel. You are free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            // Debe ser > timeout del job más largo (padrones IIBB ~7200s; CAEA ~1800s).
            // Si es menor, Laravel reencola el mismo job y dispara MaxAttemptsExceeded.
            'retry_after' => (int) env('QUEUE_RETRY_AFTER', 7500),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => 'localhost',
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('QUEUE_RETRY_AFTER', 2000),
            'block_for' => null,
            'after_commit' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the jobs that
    | have failed. You may change them to any database / table you wish.
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],

    /*
    | Verificación worker/cola en hora pico gastronomía (schedule + mail SMTP).
    | Comando: php artisan queue:verificar-pico
    */
    'workers_numprocs' => max(1, (int) env('QUEUE_WORKERS_NUMPROCS', 3)),

    'verificacion_pico' => [
        'habilitada' => filter_var(env('QUEUE_VERIFICACION_PICO_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        'hora_desde' => (int) env('QUEUE_VERIFICACION_PICO_HORA_DESDE', 12),
        'hora_hasta' => (int) env('QUEUE_VERIFICACION_PICO_HORA_HASTA', 1),
        'email' => env(
            'QUEUE_VERIFICACION_PICO_EMAIL',
            env('GASTRONOMIA_AUDITORIA_ANITA_EMAIL', 'sergiogranucci@gmail.com'),
        ),
        'email_si_ok' => filter_var(env('QUEUE_VERIFICACION_PICO_EMAIL_SI_OK', false), FILTER_VALIDATE_BOOLEAN),
        'email_throttle_minutos' => (int) env('QUEUE_VERIFICACION_PICO_EMAIL_THROTTLE', 15),
        // Debe ser > ARCA_CAEA_INFORME_JOB_TIMEOUT (1800). Un job CAEA reservado 3–5 min es normal.
        'reserved_stuck_sec' => max(180, (int) env('QUEUE_VERIFICACION_PICO_RESERVED_STUCK_SEC', 2100)),
    ],

];
