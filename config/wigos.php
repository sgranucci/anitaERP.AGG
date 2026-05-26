<?php

/**
 * Conexión a Wigos (SQL Server) para canje de premios gastronomía.
 * Réplica la lógica de track_wigos.php / lee_ticket_wigos (spVoucherGiftData).
 */
return [
    'habilitado' => filter_var(env('WIGOS_HABILITADO', false), FILTER_VALIDATE_BOOLEAN),

    /**
     * Consulta AccountInfoJSON por trackdata (tarjeta).
     * Plantilla con %s o URL base; si no tiene %s se agrega ?trackdata=
     */
    'account_info_habilitado' => filter_var(env('WIGOS_ACCOUNT_INFO_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),

    'account_info_url' => env(
        'WIGOS_ACCOUNT_INFO_URL',
        'http://serverwigosws:7788/WIGOS/AccountInfoJSON?trackdata=%s'
    ),

    'account_info_timeout' => max(3, (int) env('WIGOS_ACCOUNT_INFO_TIMEOUT', 8)),

    /** A o B: servidor primario (fallback al otro si falla la conexión). */
    'curr_wigos' => strtoupper(trim((string) env('CURR_WIGOS', 'A'))),

    'connections' => [
        'A' => [
            'host' => env('WIGOS_A_HOST', env('WIGOS_HOST')),
            'port' => env('WIGOS_A_PORT', env('WIGOS_PORT', '1433')),
            'database' => env('WIGOS_A_DATABASE', env('WIGOS_DATABASE', 'wgdb_000')),
            'username' => env('WIGOS_A_USERNAME', env('WIGOS_USERNAME', 'reader_anita')),
            'password' => env('WIGOS_A_PASSWORD', env('WIGOS_PASSWORD')),
        ],
        'B' => [
            'host' => env('WIGOS_B_HOST'),
            'port' => env('WIGOS_B_PORT', '1433'),
            'database' => env('WIGOS_B_DATABASE', 'wgdb_000'),
            'username' => env('WIGOS_B_USERNAME', 'reader_anita'),
            'password' => env('WIGOS_B_PASSWORD'),
        ],
    ],
];
