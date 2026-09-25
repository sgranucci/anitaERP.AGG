<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CUPS remoto (térmicas calidad / armado)
    |--------------------------------------------------------------------------
    |
    | En Ferli L12 no hay cups-client. Las colas "calidad" y "armado" están
    | en el CUPS del L8; se imprimen por IPP sin resolver el hostname local.
    |
    */
    'cups_host' => env('CUPS_REMOTO_HOST', '160.132.0.209'),
    'cups_port' => (int) env('CUPS_REMOTO_PORT', 631),
    'cola_empaque' => env('IMPRESION_COLA_EMPAQUE', 'calidad'),
    'cola_armado' => env('IMPRESION_COLA_ARMADO', 'armado'),
    'timeout_segundos' => (int) env('IMPRESION_TERMICA_TIMEOUT', 30),
    'script' => env('IMPRESION_TERMICA_SCRIPT', base_path('bin/imprimir-cups-remoto.sh')),

];
