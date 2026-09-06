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

    /**
     * Path Anita Surmar (El Bierzo). Solo se inyecta si SurmarSupport::esEmpresaSurmar().
     * En AGG no aplica (id 3 = Rebisco).
     */
    'surmar_path' => env('ANITA_SURMAR_PATH', env('ORDENCOMPRA_SURMAR_ANITA_PATH', '/usr2/surmar')),

    /**
     * Sistema Informix donde vive subdiario para controles/mayor de ventas.
     * El Bierzo: ventas. AGG / otros: suele ser contab.
     */
    'subdiario_sistema' => env('ANITA_SUBDIARIO_SISTEMA', 'ventas'),

    'bridge_list_reintentos' => (int) env('ANITA_BRIDGE_LIST_REINTENTOS', 6),

    'bridge_connect_timeout' => (int) env('ANITA_BRIDGE_CONNECT_TIMEOUT', 10),

    'bridge_timeout' => (int) env('ANITA_BRIDGE_TIMEOUT', 300),

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

    /**
     * Percepciones IIBB (listas separadas por coma, un valor por jurisdicción en agente_percepcion_iibb).
     */
    'agente_percepcion_iibb' => env('ANITA_AGENTE_PERCEPCION_IIBB', ''),
    'agente_retencion_iibb' => env('ANITA_AGENTE_RETENCION_IIBB', ''),
    'tasas_descarte_iibb' => env('ANITA_TASAS_DESCARTE_IIBB', '0,0'),
    'minimo_neto_iibb' => env('ANITA_MINIMO_NETO_IIBB', '0,0'),
    'minima_percepcion_iibb' => env('ANITA_MINIMA_PERCEPCION_IIBB', '0,0'),
    'agente_percepcion_iva' => env('ANITA_AGENTE_PERCEPCION_IVA', 'no'),
    'tasa_percepcion_iva' => env('ANITA_TASA_PERCEPCION_IVA', 0),
    // Fallback si no hay fila en Configuración → Regímenes de percepción (PIVA / PNC).
    'minimo_base_percepcion_iva' => env('ANITA_MINIMO_BASE_PERCEPCION_IVA', 0),
    'minimo_importe_percepcion_iva' => env('ANITA_MINIMO_IMPORTE_PERCEPCION_IVA', 0),
    // RG 2126: percepción a sujeto no categorizado (a-comprob.c). Fallback si no hay fila PNC.
    'agente_percepcion_no_categorizado' => env('ANITA_AGENTE_PERCEPCION_NO_CATEGORIZADO', 'no'),
    'tasa_percepcion_no_categorizado' => env('ANITA_TASA_PERCEPCION_NO_CATEGORIZADO', 10.5),
    'minimo_percepcion_no_categorizado' => env('ANITA_MINIMO_PERCEPCION_NO_CATEGORIZADO', 0),

];
