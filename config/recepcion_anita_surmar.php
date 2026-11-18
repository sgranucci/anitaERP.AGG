<?php

/**
 * Import recepciones Anita Surmar (recepmae/recepmov COM/D).
 * Aislado del import AGG (COM/X, path bierzo). No escribe de vuelta a Anita.
 */
return [
    'path_sistema' => env('RECEPCION_SURMAR_ANITA_PATH', env('ANITA_SURMAR_PATH', '/usr2/surmar')),
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

    /**
     * Destino por defecto al imprimir etiqueta en recepción Surmar:
     * impresora = salida seteosalida (Zebra red / CUPS via bin/imprimir-etiqueta-zebra.sh)
     * pdf = DomPDF descargable / abrir en navegador
     */
    'etiqueta_destino_default' => env('RECEPCION_SURMAR_ETIQUETA_DESTINO', 'impresora'),

    /**
     * Code39 en ZPL de etiqueta Surmar (recepción y movimientos):
     * - anita = sku-nint-nap-tipo-orden (espejo eti_prod.fc / a-stkmov.c)
     * - id    = id físico stock_etiqueta (formato ERP definitivo)
     * Transición: default anita mientras Surmar lea en Anita; volver a id
     * con SURMAR_ETIQUETA_BARCODE_FORMATO=id cuando el circuito sea 100% ERP.
     * Si formato=anita pero falta anita_nro_interno, cae a id.
     */
    'etiqueta_barcode_formato' => strtolower(trim((string) env('SURMAR_ETIQUETA_BARCODE_FORMATO', 'anita'))),

    'tablas' => [
        'cabecera' => 'recepmae',
        'linea' => 'recepmov',
    ],
];
