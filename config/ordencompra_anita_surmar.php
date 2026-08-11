<?php

/**
 * Import OC Anita Surmar (aislado del sync AGG ordencompra_anita).
 * Bridge: /usr2/surmar/compras. No escribe de vuelta a Anita.
 */
return [
    'path_sistema' => env('ORDENCOMPRA_SURMAR_ANITA_PATH', '/usr2/surmar'),
    'sistema_compras' => env('ORDENCOMPRA_SURMAR_ANITA_SISTEMA', 'compras'),
    'fecha_desde' => (int) env('ORDENCOMPRA_SURMAR_SYNC_FECHA_DESDE', 20260100),

    /** Empresa ERP Surmar (El Bierzo). */
    'empresa_id' => (int) env('ORDENCOMPRA_SURMAR_EMPRESA_ID', 3),

    /**
     * Centro de costo cabecera por defecto.
     * En este ERP: id 1 = Producción, id 2 = Administración.
     * (Si pediste “2 producción”, acá Producción es 1.)
     */
    'centrocosto_id' => (int) env('ORDENCOMPRA_SURMAR_CENTROCOSTO_ID', 1),

    'tablas' => [
        'cabecera' => 'pendmaep',
        'linea' => 'pendmovp',
    ],
];
