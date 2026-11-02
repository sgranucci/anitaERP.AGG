<?php

/**
 * Tesorería Crown. La consulta PXSol de la rendición de hotel no tiene host
 * fijo en el código: el comando lo pone el entorno.
 */
return [
    /**
     * Ejecutable que imprime una línea facturado=credito=iva_facturas=iva_nc.
     * Recibe el código externo del cajero, el desde y el hasta (Y-m-d H:i:s).
     * Vacío: el botón de la rendición avisa que PXSol no está configurado.
     */
    'pxsol_comando' => env('TESORERIA_CROWN_PXSOL_COMANDO', ''),

    'pxsol_timeout' => (int) env('TESORERIA_CROWN_PXSOL_TIMEOUT', 20),
];
