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
    | OC: pedir partida de presupuesto y CAPEX en líneas.
    | Default true = AGG (columnas visibles + validación contra presupuesto vigente).
    | El Bierzo: false (no pide ni valida; permite operar sin presupuestos cargados).
    */
    'oc_pedir_partida_capex' => filter_var(env('ORDENCOMPRA_PEDIR_PARTIDA_CAPEX', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | OC: mostrar peso unitario del artículo (ABM) y peso total (cant. × peso unit.).
    | Default false = AGG sin columnas ni impacto.
    | El Bierzo: true (persiste peso_unitario / peso_total en la línea; PDF/Excel).
    */
    'oc_mostrar_peso_articulo' => filter_var(env('ORDENCOMPRA_MOSTRAR_PESO_ARTICULO', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | OC: entregas semanales por línea (fecha + cantidad; suma → cantidad de grilla).
    | Default false = AGG sin UI ni sync de hijas.
    | El Bierzo / Surmar: true (modal por artículo; consultable desde recepción).
    */
    'oc_entrega_semanal' => filter_var(env('ORDENCOMPRA_ENTREGA_SEMANAL', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | Metas de KPIs de compras (tablero + panel IA).
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

    /*
    | Robot diario: concilia ficha CC proveedor ↔ deuda abierta ↔ mayor AP (MN/ME).
    | Alerta por mail si hay descalce (el problema histórico de Anita en multi-moneda).
    */
    'conciliacion_cc_proveedor' => [
        'habilitada' => filter_var(env('COMPRAS_CC_CONCILIACION_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('COMPRAS_CC_CONCILIACION_HORA', '07:40'),
        'ventana_dias' => max(1, (int) env('COMPRAS_CC_CONCILIACION_VENTANA_DIAS', 45)),
        'tolerancia' => (float) env('COMPRAS_CC_CONCILIACION_TOLERANCIA', 0.05),
        'tolerancia_gl' => (float) env('COMPRAS_CC_CONCILIACION_TOLERANCIA_GL', 1.00),
        'email' => env('COMPRAS_CC_CONCILIACION_EMAIL', env('MAIL_FROM_ADDRESS', '')),
        'mail_siempre' => filter_var(env('COMPRAS_CC_CONCILIACION_MAIL_SIEMPRE', false), FILTER_VALIDATE_BOOLEAN),
        'limite_filas_mail' => max(10, (int) env('COMPRAS_CC_CONCILIACION_LIMITE_MAIL', 80)),
    ],

    /*
    | Legajo de compras / autorización Gastronomía.
    */
    'legajo' => [
        'link_dias_vencimiento' => max(1, (int) env('COMPRAS_LEGAJO_LINK_DIAS', 3)),
        'recordatorio_habilitado' => filter_var(env('COMPRAS_LEGAJO_RECORDATORIO_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'recordatorio_hora' => env('COMPRAS_LEGAJO_RECORDATORIO_HORA', '09:15'),
        'recordatorio_dias' => max(1, (int) env('COMPRAS_LEGAJO_RECORDATORIO_DIAS', 3)),
    ],

    /*
    | Aviso diario de facturas de proveedor en BORRADOR (compras:avisar-comprobantes-borrador).
    | Destinatarios y plantilla: Configuración → Avisos por módulo
    | (compras / comprobante_proveedor_borrador_pendiente).
    */
    'factura_borrador_aviso' => [
        'habilitado' => filter_var(env('COMPRAS_FACTURA_BORRADOR_AVISO_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('COMPRAS_FACTURA_BORRADOR_AVISO_HORA', '09:30'),
        'limite_mail' => max(10, (int) env('COMPRAS_FACTURA_BORRADOR_AVISO_LIMITE_MAIL', 80)),
    ],

    /*
    | Reporte sábana de pagos: lectura temporal Anita (pago + auxpag) en 2 lists.
    | Apagar cuando el circuito de pagos esté 100% en ERP.
    */
    'pagos_sabana_anita_habilitada' => filter_var(
        env('COMPRAS_PAGOS_SABANA_ANITA_HABILITADA', false),
        FILTER_VALIDATE_BOOLEAN
    ),

];
