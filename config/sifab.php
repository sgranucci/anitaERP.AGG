<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Conexión SQL Server SIFAB (INTERFORMING)
    |--------------------------------------------------------------------------
    | Usada por comandos de sincronización de maestros. Auth Windows/NTLM.
    */
    'host' => env('SIFAB_HOST', '132.147.165.3'),
    'port' => (int) env('SIFAB_PORT', 1433),
    'database' => env('SIFAB_DATABASE', 'SIFAB2'),
    'username' => env('SIFAB_USERNAME', 'administrador'),
    'password' => env('SIFAB_PASSWORD', ''),
];
