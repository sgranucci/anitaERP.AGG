<?php

return [

    /*
    | Consulta de clientes VIP cargados en Emita (SQL Server, solo lectura).
    | Canjes de marketing: por nombre y por alias.
    */
    'sql' => [
        'host' => env('EMITA_SQL_HOST', ''),
        'database' => env('EMITA_SQL_DATABASE', 'Emita'),
        'username' => env('EMITA_SQL_USERNAME', ''),
        'password' => env('EMITA_SQL_PASSWORD', ''),
        'encrypt' => env('EMITA_SQL_ENCRYPT', 'no'),
        'trust_server_certificate' => env('EMITA_SQL_TRUST_SERVER_CERTIFICATE', 'yes'),
        'login_timeout' => (int) env('EMITA_SQL_LOGIN_TIMEOUT', 12),
    ],

];
