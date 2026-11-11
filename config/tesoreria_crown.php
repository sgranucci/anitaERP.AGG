<?php

/**
 * Tesorería Crown. PXSol de la rendición hotel: API nativa (preferida) o comando.
 */
return [
    /**
     * Token Bearer de gateway-prod.pxsol.com (mismo que trae_comprob.php).
     * Si está cargado, el ERP llama a la API directo; no hace falta SSH ni comando.
     */
    'pxsol_token' => env('TESORERIA_CROWN_PXSOL_TOKEN', ''),

    'pxsol_url' => env(
        'TESORERIA_CROWN_PXSOL_URL',
        'https://gateway-prod.pxsol.com/v2/voucher/list/v2'
    ),

    /**
     * Fallback legacy: ejecutable que imprime facturado=credito=iva_facturas=iva_nc.
     * Solo se usa si no hay token de API.
     */
    'pxsol_comando' => env('TESORERIA_CROWN_PXSOL_COMANDO', ''),

    'pxsol_timeout' => (int) env('TESORERIA_CROWN_PXSOL_TIMEOUT', 20),

    /** Destino SSH del bridge legacy (usuario@host). */
    'pxsol_ssh' => env('TESORERIA_CROWN_PXSOL_SSH', 'sergio@ac-server1-interface'),

    'pxsol_remote_dir' => env('TESORERIA_CROWN_PXSOL_REMOTE_DIR', 'pxsol'),

    'pxsol_remote_php' => env('TESORERIA_CROWN_PXSOL_REMOTE_PHP', 'trae_comprob.php'),

    /**
     * true = al firmar, el PIN inicial (código/legajo) no alcanza: el cajero elige uno propio.
     * false = laboratorio: firma con el PIN cargado (p. ej. = código) sin forzar cambio.
     * Al arrancar producción: TESORERIA_CROWN_EXIGIR_PIN_PROPIO=true
     */
    'exigir_pin_propio' => filter_var(env('TESORERIA_CROWN_EXIGIR_PIN_PROPIO', true), FILTER_VALIDATE_BOOLEAN),
];
