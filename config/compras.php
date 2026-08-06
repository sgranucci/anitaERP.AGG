<?php

return [

    /*
    | Cumplimiento de requisiciones de compra → transferencia de mercadería.
    |
    | tipotransaccion_stock.id para la transferencia que genera el cumplimiento.
    | Si la variable está vacía, se resuelve por abreviatura 'TRA' (transferencia
    | normal) en tipotransaccion_stock. Setear el id explícito solo para forzar
    | otro tipo de transacción.
    */
    'requisicion_cumplimiento_tipotransaccion_stock_id' => env('COMPRAS_REQUISICION_CUMPLIMIENTO_TIPOTRANSACCION_ID'),

    /** Abreviatura del tipo de transacción usado como fallback/precarga (transferencia normal). */
    'requisicion_cumplimiento_tipotransaccion_abreviatura' => env('COMPRAS_REQUISICION_CUMPLIMIENTO_TIPOTRANSACCION_ABREVIATURA', 'TRA'),

    /*
    | Aviso diario de OC abiertas (compras:alertas-ordencompra-abiertas).
    | Destinatarios y plantilla: Configuración → Avisos por módulo (compras / ordencompra_alertas_abiertas).
    */
    'oc_alertas_abiertas' => [
        'habilitado' => filter_var(env('COMPRAS_OC_ALERTAS_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('COMPRAS_OC_ALERTAS_HORA', '08:15'),
        'dias_sin_recepcion' => max(1, (int) env('COMPRAS_OC_ALERTAS_DIAS_SIN_RECEPCION', 7)),
        'limite_por_seccion' => max(1, (int) env('COMPRAS_OC_ALERTAS_LIMITE_POR_SECCION', 80)),
    ],

    /*
    | Metas de KPIs de proceso de Compras (tablero + panel IA).
    */
    'kpis' => [
        'meta_ciclo_dias' => (float) env('COMPRAS_KPI_META_CICLO_DIAS', 2),
        'meta_gestion_oc_dias' => (float) env('COMPRAS_KPI_META_GESTION_OC_DIAS', 2),
        'meta_pct_oc_abiertas' => (float) env('COMPRAS_KPI_META_PCT_OC_ABIERTAS', 10),
    ],

];
