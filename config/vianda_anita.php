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

    /** Empresa ERP cuyo bridge Anita se consulta (default Biyemas). */
    'empresa_sync' => (int) env('VIANDA_ANITA_EMPRESA_SYNC', 1),
];
