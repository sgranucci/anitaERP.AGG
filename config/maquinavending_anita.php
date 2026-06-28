<?php

/**
 * Import Anita → anitaERP (solo lectura en Anita; sin bridge create/update/delete).
 *
 * maqvmae → maquinavending
 * ubimvending → maquinavending_articulo
 *
 * Bridges por empresa (StockAnitaBridgeSupport / ventas):
 *   1 = Biyemas (central), 2 = Kandiko, 3 = Rebisco — orden de sync: 1, 2, 3
 */
return [
    'tabla_maquina' => 'maqvmae',
    'tabla_articulo' => 'ubimvending',

    /**
     * Empresas ERP cuyo bridge Anita se consulta al sincronizar (orden: Biyemas 1, Kandiko 2, Rebisco 3).
     * Override: MAQUINAVENDING_ANITA_EMPRESAS_SYNC=1,2,3
     */
    'empresas_sync' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('MAQUINAVENDING_ANITA_EMPRESAS_SYNC', '1,2,3'))
    ), fn (int $id) => $id > 0)),

    /** @deprecated El sistema Informix lo resuelve StockAnitaBridgeSupport por empresa. */
    'sistema' => env('MAQUINAVENDING_SYNC_ANITA_SISTEMA', 'ventas'),

    'campos_maquina' => 'maqvm_codigo, maqvm_desc, maqvm_sucursal, maqvm_ubicacion, maqvm_deposito, maqvm_cod_afip, maqvm_nro_serie',

    'campos_articulo' => 'ubimv_codigo, ubimv_ubicacion, ubimv_articulo',
];
