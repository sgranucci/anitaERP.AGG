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

    /*
     * Opciones de conexión SQL Server (ODBC Driver 18+).
     * El track_wigos.php legacy usa FreeTDS sin TLS; replicamos ese
     * comportamiento con Encrypt=no. Si el SQL Server tiene cert
     * autofirmado, usar encrypt=yes + trust_server_certificate=yes.
     */
    'encrypt' => env('WIGOS_ENCRYPT', 'no'),

    'trust_server_certificate' => env('WIGOS_TRUST_SERVER_CERTIFICATE', 'yes'),

    'login_timeout' => max(1, (int) env('WIGOS_LOGIN_TIMEOUT', 5)),

    'appname' => env('WIGOS_APPNAME', 'AnitaERP-Gastronomia-Wigos'),

    /**
     * OpenSSL local (putenv OPENSSL_CONF) solo al conectar por ODBC.
     * Necesario con SQL Server 2012 + ODBC 18 + OpenSSL 3 (error 0x2746 sin esto).
     */
    'openssl_conf' => env('WIGOS_OPENSSL_CONF', base_path('config/openssl/wigos-mssql.cnf')),

    /** Ruta al binario PHP CLI (obligatorio bajo php-fpm si PHP_BINARY está vacío). */
    'php_binary' => env('WIGOS_PHP_BINARY'),

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

    /**
     * Overrides por empresa Anita (AGG: Kandiko Wilde = 2, Rebisco = 3).
     * JSON env WIGOS_POR_EMPRESA o defaults embebidos.
     *
     * @var array<int, array<string, mixed>>
     */
    'por_empresa' => (static function (): array {
        $raw = env('WIGOS_POR_EMPRESA');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $map = [];
                foreach ($decoded as $empresaId => $cfg) {
                    if (is_array($cfg) && $cfg !== []) {
                        $map[(int) $empresaId] = $cfg;
                    }
                }
                if ($map !== []) {
                    return $map;
                }
            }
        }

        return [
            2 => [
                'curr_wigos' => 'A',
                'connections' => [
                    'A' => ['host' => 'serverwigosksaa'],
                    'B' => ['host' => 'serverwigosksab'],
                ],
            ],
            3 => [
                'curr_wigos' => 'A',
                'connections' => [
                    'A' => ['host' => 'serverwigosrsaa'],
                    'B' => ['host' => 'serverwigosrsab'],
                ],
            ],
        ];
    })(),
];
