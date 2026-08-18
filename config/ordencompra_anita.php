<?php

/**
 * Mapeo Anita → anitaERP para sincronización de órdenes de compra.
 * Cada entrada documenta un campo destino; la transformación vive en
 * App\Support\Compras\AnitaSync\Ordencompra\*FieldMapper (un método por campo).
 *
 * Tablas origen (Informix / compras):
 *   pendmaep   → ordencompra
 *   pendmovp   → ordencompra_articulo
 *   movpresup  → partidagasto_id / capex_id en línea (por movp_nro_interno)
 *   ocvley     → detalle ampliado en línea (por ocvl_nro_orden)
 *   occuota    → ordencompra_comprobante + ordencompra_comprobante_cuota
 *   legcompra  → ordencompra_historia (legc_id = penmp_nro; FK ordencompra_id = id ERP secuencial)
 */
return [
    'fecha_desde' => (int) env('ORDENCOMPRA_SYNC_ANITA_FECHA_DESDE', 20250100),

    /*
    | Escritura ERP → Anita (pendmaep, pendmovp, movpresup) en alta/edición/baja de OC.
    */
    'escritura_habilitada' => filter_var(env('ORDENCOMPRA_ANITA_ESCRITURA_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),

    'escritura' => [
        'sistema_compras' => env('ORDENCOMPRA_ANITA_SISTEMA_COMPRAS', 'compras'),
        // numerador PEP (tcomp_refer=206) vive en Informix ventas, igual que COM.
        'sistema_numerador' => env('ORDENCOMPRA_ANITA_SISTEMA_NUMERADOR', 'ventas'),
        't_comp_clave' => env('ORDENCOMPRA_ANITA_T_COMP_CLAVE', 'PEP'),
        'oc_tipo' => 'PEP',
        'oc_letra' => 'X',
        'oc_sucursal' => 0,
        'deposito_default' => (int) env('ORDENCOMPRA_ANITA_DEPOSITO_DEFAULT', 1),
        'piso_nro_interno' => (int) env('ORDENCOMPRA_ANITA_PISO_NRO_INTERNO', 500000),
        'piso_nro_oc' => (int) env('ORDENCOMPRA_ANITA_PISO_NRO_OC', 200000),
    ],

    /*
    | Auditoría diaria OC ERP ↔ Anita (ordencompra:auditoria-anita-diaria).
    | Detecta/repara: cabecera pendmaep faltante, líneas huérfanas, proveedor sin pad 6,
    | legcompra/pendfecha/occuota, aplicped de recepciones confirmadas,
    | y cobertura pendmovp por nro_interno (faltantes + duplicados con count engañoso; OC 223049).
    | Vive hasta desactivar escritura Anita / ORDENCOMPRA_AUDITORIA_ANITA_HABILITADA=false.
    */
    'auditoria_diaria' => [
        'habilitada' => filter_var(env('ORDENCOMPRA_AUDITORIA_ANITA_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('ORDENCOMPRA_AUDITORIA_ANITA_HORA', '07:50'),
        'usuario_id' => (int) env('ORDENCOMPRA_AUDITORIA_ANITA_USUARIO_ID', 1),
        'email' => env('ORDENCOMPRA_AUDITORIA_ANITA_EMAIL', 'sergiogranucci@gmail.com'),
        'ventana_dias' => max(1, (int) env('ORDENCOMPRA_AUDITORIA_ANITA_VENTANA_DIAS', 7)),
        'auto_reparar' => filter_var(env('ORDENCOMPRA_AUDITORIA_ANITA_AUTO_REPARAR', true), FILTER_VALIDATE_BOOLEAN),
        'mail_siempre' => filter_var(env('ORDENCOMPRA_AUDITORIA_ANITA_MAIL_SIEMPRE', false), FILTER_VALIDATE_BOOLEAN),
        // Mail también cuando hubo reparaciones exitosas (pendmovp, pad, aplicped, etc.).
        'mail_si_reparo' => filter_var(env('ORDENCOMPRA_AUDITORIA_ANITA_MAIL_SI_REPARO', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'tablas' => [
        'cabecera' => 'pendmaep',
        'linea' => 'pendmovp',
        'presupuesto_linea' => 'movpresup',
        'leyenda_linea' => 'ocvley',
        'cuota' => 'occuota',
        'cuota_fpago' => 'ocfpagocuota',
        'historia' => 'legcompra',
        'fecha_oc' => 'pendfecha',
    ],

    'cabecera' => [
        ['origen' => '—', 'destino' => 'id', 'nota' => 'Secuencial auto (no penmp_nro)'],
        ['origen' => 'penmp_nro', 'destino' => 'numeroordencompra'],
        ['origen' => 'penmp_fecha', 'destino' => 'fecha', 'transform' => 'fechaYmd'],
        ['origen' => 'penmp_fecha_ent', 'destino' => 'fechaentrega', 'transform' => 'fechaYmd'],
        ['origen' => 'penmp_empresa', 'destino' => 'empresa_id', 'transform' => 'fk_empresa'],
        ['origen' => 'penmp_requisicion', 'destino' => 'requisicion_id', 'transform' => 'fk_requisicion_por_numero'],
        ['origen' => 'penmp_ccosto', 'destino' => 'centrocosto_id', 'transform' => 'fk_centrocosto'],
        ['origen' => 'penmp_leyenda', 'destino' => 'detalle'],
        ['origen' => 'penmp_fecha_ing, penmp_hora_ing, penmp_usuario_ini', 'destino' => 'comentario', 'transform' => 'comentario_ingreso'],
        ['origen' => 'penmp_entrega', 'destino' => 'lugarentrega'],
        ['origen' => 'penmp_expreso', 'destino' => 'transporte_id', 'transform' => 'fk_transporte'],
        ['origen' => 'penmp_es_anticipo', 'destino' => 'tratamiento', 'transform' => 'tratamiento_anticipo'],
        ['origen' => 'penmp_proveedor', 'destino' => 'proveedor_id', 'transform' => 'fk_proveedor'],
        ['origen' => 'penmp_cond_compra', 'destino' => 'condicioncompra_id', 'transform' => 'fk_condicioncompra'],
        ['origen' => 'penmp_cond_entrega', 'destino' => 'condicionentrega_id', 'transform' => 'fk_condicionentrega'],
        ['origen' => 'penmp_dto', 'destino' => 'descuento'],
        ['origen' => 'penmp_estado', 'destino' => 'estadoordencompra', 'transform' => 'estado_oc'],
        ['origen' => '—', 'destino' => 'sector_legajocompra_id', 'transform' => 'sector_compras_default'],
        ['origen' => '—', 'destino' => 'creousuario_id', 'transform' => 'usuario_sync'],
    ],

    'linea' => [
        ['origen' => 'penvp_orden', 'destino' => 'penvp_orden'],
        ['origen' => 'penvp_fecha_ent', 'destino' => 'fechaentrega', 'transform' => 'fechaYmd'],
        ['origen' => 'penvp_articulo', 'destino' => 'articulo_id', 'transform' => 'fk_articulo_sku'],
        ['origen' => 'penvp_cantidad', 'destino' => 'cantidad'],
        ['origen' => 'penvp_precio', 'destino' => 'precio'],
        ['origen' => 'penmp_cod_mon / penvp_cod_mon', 'destino' => 'moneda_id', 'transform' => 'fk_moneda'],
        ['origen' => 'penmp_cotizacion', 'destino' => 'cotizacion'],
        ['origen' => 'penvp_dto_art', 'destino' => 'descuento'],
        ['origen' => '—', 'destino' => 'cantidadalternativa', 'nota' => 'Sin equivalente en pendmovp; 0'],
        ['origen' => 'penvp_desc + ocvl_leyenda', 'destino' => 'detalle', 'transform' => 'detalle_con_leyenda'],
        ['origen' => 'penvp_ccosto', 'destino' => 'centrocostodestino_id', 'transform' => 'fk_centrocosto'],
        ['origen' => 'movp_partida', 'destino' => 'partidagasto_id', 'transform' => 'fk_partidagasto'],
        ['origen' => 'movp_proyecto', 'destino' => 'capex_id', 'transform' => 'fk_capex'],
    ],

    'comprobante' => [
        ['origen' => 'occ_cond_pago (agrupado)', 'destino' => 'condicionpago_id', 'transform' => 'fk_condicionpago'],
        ['origen' => 'sum(occ_monto)', 'destino' => 'monto'],
        ['origen' => 'min(occ_fecha_vto)', 'destino' => 'fechavencimiento', 'transform' => 'fechaYmd'],
        ['origen' => 'penmp_cod_mon', 'destino' => 'moneda_id', 'transform' => 'fk_moneda'],
        ['origen' => 'penmp_cotizacion', 'destino' => 'cotizacion'],
        ['origen' => 'count(cuotas)', 'destino' => 'cantidadcuota'],
        ['origen' => '—', 'destino' => 'tipocomprobante', 'nota' => 'FACTURA por defecto'],
    ],

    'comprobante_cuota' => [
        ['origen' => 'occ_fecha_vto', 'destino' => 'fechavencimiento', 'transform' => 'fechaYmd'],
        ['origen' => 'occ_monto', 'destino' => 'monto'],
        ['origen' => 'penmp_cod_mon', 'destino' => 'moneda_id', 'transform' => 'fk_moneda'],
        ['origen' => 'penmp_cotizacion', 'destino' => 'cotizacion'],
        ['origen' => 'occ_medio_pago', 'destino' => 'formapago_id', 'transform' => 'fk_formapago_medio'],
        ['origen' => 'occ_detalle', 'destino' => 'detalle'],
    ],

    'historia' => [
        ['origen' => 'legc_id', 'destino' => '—', 'nota' => 'Clave Anita (= penmp_nro); ordencompra_id = id ERP del registro importado'],
        ['origen' => '—', 'destino' => 'sector_legajocompra_id', 'transform' => 'sector_compras_default'],
        ['origen' => 'legc_fecha, legc_hora', 'destino' => 'fecha', 'transform' => 'fecha_hora_anita'],
        ['origen' => 'legc_observacion', 'destino' => 'observacion'],
        ['origen' => 'legc_estado', 'destino' => 'leyenda', 'transform' => 'leyenda_estado'],
        ['origen' => 'legc_usuario', 'destino' => 'creousuario_id', 'transform' => 'fk_usuario_anita'],
    ],
];
