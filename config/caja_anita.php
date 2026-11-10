<?php

/**
 * Sync Anita → anitaERP: cajas físicas (sistema caja / tabla caja).
 * CROWN: cncadmin + sala (ej. CIP). AGG: sin sala_id.
 */
return [
    'tabla' => 'caja',

    'sistema' => env('CAJA_ANITA_SYNC_ANITA_SISTEMA', 'caja'),

    'ifx_server' => env('CAJA_ANITA_SYNC_ANITA_IFX_SERVER', ''),

    'path_sistema' => env('CAJA_ANITA_SYNC_ANITA_PATH', ''),

    'empresa_default_id' => (int) env('CAJA_ANITA_SYNC_EMPRESA_ID', 0)
        ?: (int) (config('cliente.EMPRESA_DEFAULT_ID') ?? 1),

    /** CROWN: código sala (ej. CIP). Vacío = sin sala_id (AGG). */
    'sala_codigo' => env('CAJA_ANITA_SYNC_SALA_CODIGO', ''),

    /**
     * CROWN: sucursal Anita (ventas) de slots online / resulol.
     * CIP Cipolletti = 15.
     */
    'sucursal_resultado_online' => (int) env('CAJA_ANITA_SUCURSAL_RESULTADO_ONLINE', 15),

    /**
     * CROWN: casino online (a-resonline / resonline) vive en ac-server1.
     * Del Río online = 67, Betsur online = 68.
     */
    'sucursal_del_rio_online' => (int) env('CAJA_ANITA_SUCURSAL_DEL_RIO_ONLINE', 67),
    'sucursal_betsur_online' => (int) env('CAJA_ANITA_SUCURSAL_BETSUR_ONLINE', 68),
    'ifx_server_casino_online' => env('CAJA_ANITA_IFX_SERVER_CASINO_ONLINE', 'acncadmin'),
    'path_sistema_casino_online' => env('CAJA_ANITA_PATH_CASINO_ONLINE', '/usr2/casino'),

    'campos_listado' => 'caja_desc',
];
