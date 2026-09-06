<?php

return [
    /*
     * Backfill nocturno del índice del tracking.
     *
     * El índice resuelve PDF, fecha de carga real y estado de pago consultando
     * el puente del Anita, que es lento: por eso se materializa de noche y la
     * grilla lee sólo tablas locales.
     */
    'indice' => [
        'backfill_habilitado' => env('TRACKING_FACTURAS_BACKFILL_HABILITADO', true),

        /* Los comprobantes nuevos del día: corre siempre y termina rápido. */
        'faltantes_hora' => env('TRACKING_FACTURAS_FALTANTES_HORA', '02:40'),

        /*
         * Repaso completo, para que se actualicen los pagos de comprobantes ya
         * indexados. Un día por semana porque recorre todo el histórico.
         */
        'completo_dia' => (int) env('TRACKING_FACTURAS_COMPLETO_DIA', 0), // 0 = domingo
        'completo_hora' => env('TRACKING_FACTURAS_COMPLETO_HORA', '03:10'),

        'lote' => (int) env('TRACKING_FACTURAS_LOTE', 200),
    ],

    /*
     * Copia masiva Scan → Facturas_scan.
     *
     * Deshabilitada a propósito: lo viejo del Anita se sirve desde `/scan`
     * (ruta cacheada en el índice) y lo nuevo vive en `/Facturas_scan`. No hace
     * falta duplicar ~15.000 PDF. El comando `tracking-facturas:migrar-pdf`
     * queda disponible por si algún día se quiere independizar del montaje
     * legacy, pero el scheduler no lo corre.
     */
    'migracion_pdf' => [
        'habilitada' => env('TRACKING_FACTURAS_MIGRAR_PDF_HABILITADA', false),
        'hora' => env('TRACKING_FACTURAS_MIGRAR_PDF_HORA', '04:20'),
        'lote' => (int) env('TRACKING_FACTURAS_MIGRAR_PDF_LOTE', 100),
        'limite_por_corrida' => (int) env('TRACKING_FACTURAS_MIGRAR_PDF_LIMITE', 2000),
    ],
];
