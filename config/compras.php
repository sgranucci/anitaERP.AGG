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
    | Aviso de vencimiento de contratos / OC abiertas (compras:alertas-contratos-vencimiento).
    | Destinatarios y plantilla: Configuración → Avisos por módulo
    | (compras / ordencompra_contrato_vencimiento y ordencompra_contrato_vencido).
    */
    'contratos_vencimiento' => [
        'habilitado' => filter_var(env('COMPRAS_CONTRATOS_ALERTAS_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('COMPRAS_CONTRATOS_ALERTAS_HORA', '08:30'),
        'dias_aviso' => env('COMPRAS_CONTRATOS_DIAS_AVISO', '60,30,15'),
        'porcentajes_consumo' => env('COMPRAS_CONTRATOS_PORCENTAJES_CONSUMO', '80,100'),
        'dias_repeticion_vencido' => max(1, (int) env('COMPRAS_CONTRATOS_DIAS_REPETICION_VENCIDO', 7)),
    ],

    /*
    | Metas de KPIs de proceso de Compras (tablero + panel IA).
    | Roles comprador: solo estos cuentan en OC gestionadas / productividad / ahorro.
    */
    'kpis' => [
        'meta_ciclo_dias' => (float) env('COMPRAS_KPI_META_CICLO_DIAS', 2),
        'meta_gestion_oc_dias' => (float) env('COMPRAS_KPI_META_GESTION_OC_DIAS', 2),
        'meta_pct_oc_abiertas' => (float) env('COMPRAS_KPI_META_PCT_OC_ABIERTAS', 10),
        /** Nombres exactos en tabla rol (Enc-compras, Op-Compras). Separados por coma en .env. */
        'roles_comprador' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('COMPRAS_KPI_ROLES_COMPRADOR', 'Enc-compras,Op-Compras'))
        ))),
    ],

];
