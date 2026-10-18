<?php

/**
 * Sync Anita → anitaERP: cajeros del módulo caja Crown
 * (Informix sistema caja / tablas cajero + cajeroexterno).
 *
 * CROWN Cipolletti: cncadmin + sala CIP. Enlace a mozo_gastronomia por caj_mozo.
 */
return [
    'tabla' => 'cajero',

    'tabla_externo' => 'cajeroexterno',

    'sistema' => env('CAJERO_CROWN_ANITA_SISTEMA', 'caja'),

    'ifx_server' => env('CAJERO_CROWN_ANITA_IFX_SERVER', ''),

    'path_sistema' => env('CAJERO_CROWN_ANITA_PATH', ''),

    'empresa_default_id' => (int) env('CAJERO_CROWN_ANITA_EMPRESA_ID', 0)
        ?: (int) (config('cliente.EMPRESA_DEFAULT_ID') ?? 1),

    /** CROWN: código sala (ej. CIP). Vacío = sin sala_id. */
    'sala_codigo' => env('CAJERO_CROWN_ANITA_SALA_CODIGO', ''),

    'campos_listado' => 'caj_cajero, caj_nombre, caj_usuario, caj_empresa, caj_legajo, caj_caja, caj_mozo',

    'campos_externo' => 'caje_cajero, caje_sistema, caje_cajero_ext',
];
