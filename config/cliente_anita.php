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
];
