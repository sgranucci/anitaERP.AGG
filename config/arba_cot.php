<?php

return [
    /*
    | test: http://cot.test.arba.gov.ar/...
    | prod: https://cot.arba.gov.ar/...
    */
    'ambiente' => env('ARBA_COT_AMBIENTE', 'test'),

    'url' => env('ARBA_COT_URL', ''),

    'usuario' => env('ARBA_COT_USER', ''),

    'password' => env('ARBA_COT_PASSWORD', ''),

    /** Planta y puerta para nombre de archivo TB_CUIT_planta_puerta_fecha_seq.txt */
    'planta' => env('ARBA_COT_PLANTA', '000'),

    'puerta' => env('ARBA_COT_PUERTA', '002'),

    /** Código AFIP de remito (tabla ARBA post-2019). */
    'codigo_comprobante_remito' => env('ARBA_COT_CODIGO_COMPROBANTE', '091'),

    /** Origen del traslado (domicilio fiscal emisor / planta). */
    'origen' => [
        'cuit' => env('ARBA_COT_ORIGEN_CUIT', ''),
        'razon_social' => env('ARBA_COT_ORIGEN_RAZON_SOCIAL', ''),
        'calle' => env('ARBA_COT_ORIGEN_CALLE', ''),
        'numero' => env('ARBA_COT_ORIGEN_NUMERO', 'S/N'),
        'localidad' => env('ARBA_COT_ORIGEN_LOCALIDAD', ''),
        'provincia' => env('ARBA_COT_ORIGEN_PROVINCIA', 'B'),
        'codigo_postal' => env('ARBA_COT_ORIGEN_CODIGO_POSTAL', ''),
    ],

    /** Tipo de recorrido por defecto si el reparto no define otro. */
    'tipo_recorrido_default' => env('ARBA_COT_TIPO_RECORRIDO', 'U'),

    'timeout_segundos' => (int) env('ARBA_COT_TIMEOUT', 120),

    'storage_path' => storage_path('app/arba/cot'),
];
