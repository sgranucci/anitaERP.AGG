<?php

/**
 * Importación Anita (Informix) → cliente del ERP.
 * Dirección: Anita es la fuente de verdad; el comando cliente:sincronizar-anita actualiza anitaERP.
 * Escritura ERP → Anita: ANITA_SYNC_CLIENTE_WRITE en .env (config app.anita_sync_cliente_write).
 *
 * Tabla origen: climae (sistema ventas).
 */
return [
    'tabla' => 'climae',

    'sistema' => env('CLIENTE_SYNC_ANITA_SISTEMA', 'ventas'),

    'key_field_anita' => 'clim_cliente',

    /**
     * Columnas del listado masivo (solo código para recorrer todos los clientes).
     */
    'campos_listado' => env('CLIENTE_SYNC_ANITA_CAMPOS_LISTADO')
        ?: 'clim_cliente as codigo, clim_cliente',

    /*
     * Alta cliente EL BIERZO: numerador t_comp CLI → numerador (num_ult_numero).
     * Si el código propuesto existe en ERP o climae, se incrementa hasta hallar uno libre.
     */
    'numeracion' => [
        'habilitada' => filter_var(env('CLIENTE_ANITA_NUMERACION_BIERZO', true), FILTER_VALIDATE_BOOLEAN),
        't_comp_clave' => env('CLIENTE_ANITA_T_COMP_CLAVE', 'CLI'),
        'sistema_t_comp' => env('CLIENTE_ANITA_SISTEMA_T_COMP', 'ventas'),
        'sistema_numerador' => env('CLIENTE_ANITA_SISTEMA_NUMERADOR', 'ventas'),
    ],
];
