<?php

/**
 * Mapeo Anita → anitaERP para sincronización de mesas de gastronomía.
 * Tabla origen (Informix): mesa → mesa_gastronomia
 *
 * Campos Anita (mesa.sql):
 *   mes_codigo, mes_ubicacion, mes_detalle, mes_plano, mes_x, mes_y,
 *   mes_size, mes_path_icono, mes_clave, mes_estado
 */
return [
    'tabla' => 'mesa',

    /** Sistema Informix en apiERP.php (null = sin cláusula de sistema, como stkcmae). */
    'sistema' => env('MESA_GASTRONOMIA_SYNC_ANITA_SISTEMA', 'ventas'),

    'empresa_default_id' => (int) env('MESA_GASTRONOMIA_SYNC_EMPRESA_ID', 0) ?: (int) (config('cliente.EMPRESA_DEFAULT_ID') ?? 1),

    'campos_listado' => 'mes_codigo, mes_ubicacion, mes_detalle, mes_clave, mes_estado',

    'mapeo' => [
        ['origen' => 'mes_codigo', 'destino' => 'codigo', 'transform' => 'codigo_anita'],
        ['origen' => 'mes_detalle', 'destino' => 'nombre', 'transform' => 'trim'],
        ['origen' => 'mes_ubicacion', 'destino' => 'ubicacion_nombre', 'transform' => 'trim_nullable'],
        ['origen' => 'mes_clave', 'destino' => 'numeromesa', 'transform' => 'trim'],
        ['origen' => '—', 'destino' => 'empresa_id', 'transform' => 'empresa_default'],
    ],
];
