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

    'campos_listado' => 'caja_desc',
];
