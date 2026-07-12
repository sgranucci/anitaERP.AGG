<?php

/**
 * Import Anita → anitaERP (tipomvianda / artmvianda, sistema ventas Biyemas).
 */
return [
    'tabla_tipo_menu' => 'tipomvianda',
    'tabla_articulo' => 'artmvianda',

    'campos_tipo_menu' => 'tipom_codigo, tipom_desc',
    'campos_articulo' => 'artm_codigo, artm_dia, artm_articulo',

    'tabla_usuario' => 'usuvianda',
    'campos_usuario' => 'usuv_usuario, usuv_nombre, usuv_password, usuv_ccosto, usuv_tipo_usuario, usuv_tipo_menu',

    /** Empresa ERP cuyo bridge Anita se consulta por defecto (Biyemas). */
    'empresa_sync' => (int) env('VIANDA_ANITA_EMPRESA_SYNC', 1),

    /**
     * Empresas cuyos bridges Anita se recorren al sincronizar (usuarios y demás).
     * 1 = Biyemas, 2 = Kandiko, 3 = Rebisco. Cada una consulta su propio bridge
     * (ver gastronomia.ticket_tarjeta_anita_por_empresa / stock.anita_por_empresa).
     */
    'empresas_sync' => array_values(array_filter(array_map(
        fn ($valor) => (int) trim((string) $valor),
        explode(',', (string) env('VIANDA_ANITA_EMPRESAS_SYNC', '1,2,3'))
    ), fn ($valor) => $valor > 0)),

    /**
     * Conversión artm_dia (Anita) → día ISO ERP (1=lun … 7=dom) al importar artmvianda.
     * domingo_primero: Biyemas/Rebisco (artm 1=dom … 7=sáb).
     * kandiko: permutación legacy del bridge Kandiko.
     */
    'mapeo_artm_dia_por_empresa' => [
        1 => 'domingo_primero',
        2 => 'kandiko',
        3 => 'domingo_primero',
    ],
];
