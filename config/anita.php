<?php

return [

    /**
     * Host del bridge HTTP Informix (sin esquema): IP o IP:puerto.
     * Ej.: 10.20.30.200:8080 → http://10.20.30.200:8080/apiERP.php
     */
    'ip' => env('ANITA_IP', ''),

    'local_ip' => env('LOCAL_IP', ''),

    'bridge_type' => env('ANITA_BRIDGE_TYPE', 'HTTP'),

    'api_script' => env('ANITA_API_SCRIPT', 'apiERP.php'),

    'bdd' => env('ANITA_BDD', 'ventas'),

    'bdd_path' => env('ANITA_BDD_PATH', ''),

    'ifx_server' => env('IFX_SERVER', ''),

    'ifx_server_local' => env('IFX_SERVER_LOCAL', ''),

    'puerto_ssh' => env('ANITA_PUERTO_SSH'),

    /**
     * Claves usadas en $data['servidor'] / $data['ifx_server'] de ApiAnita (nombre de variable .env).
     */
    'servidores' => [
        'ANITA_IP' => env('ANITA_IP', ''),
        'LOCAL_IP' => env('LOCAL_IP', ''),
    ],

    'ifx_servers' => [
        'IFX_SERVER' => env('IFX_SERVER', ''),
        'IFX_SERVER_LOCAL' => env('IFX_SERVER_LOCAL', ''),
    ],

];
