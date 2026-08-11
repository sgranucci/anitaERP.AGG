<?php

/**
 * Import recepciones Anita Surmar (recepmae/recepmov COM/D).
 * Aislado del import AGG (COM/X, path bierzo). No escribe de vuelta a Anita.
 */
return [
    'path_sistema' => env('RECEPCION_SURMAR_ANITA_PATH', '/usr2/surmar'),
    'sistema_compras' => env('RECEPCION_SURMAR_ANITA_SISTEMA', 'compras'),
    'fecha_desde' => (int) env('RECEPCION_SURMAR_SYNC_FECHA_DESDE', 20260101),

    /** Empresa ERP Surmar (El Bierzo). */
    'empresa_id' => (int) env('RECEPCION_SURMAR_EMPRESA_ID', 3),

    /**
     * Centro de costo cabecera por defecto.
     * En este ERP: id 1 = Producción, id 2 = Administración.
     */
    'centrocosto_id' => (int) env('RECEPCION_SURMAR_CENTROCOSTO_ID', 1),

    'tipo' => env('RECEPCION_SURMAR_ANITA_TIPO', 'COM'),
    'letra' => env('RECEPCION_SURMAR_ANITA_LETRA', 'D'),

    'origen_carga' => env('RECEPCION_SURMAR_ORIGEN_CARGA', 'ANITA_IMPORT'),

    'tablas' => [
        'cabecera' => 'recepmae',
        'linea' => 'recepmov',
    ],
];
