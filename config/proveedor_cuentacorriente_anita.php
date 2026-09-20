<?php

/**
 * Sync Anita (promov + compra + aplmovp) → proveedor_cuentacorriente.
 * Formatos Informix varían: AGG suele tener com_empresa/prov_empresa; Ferli no.
 */
return [
    'sistema' => env('PROVEEDOR_CC_ANITA_SISTEMA', 'compras'),

    'tabla_promov' => 'promov',
    'tabla_compra' => 'compra',
    'tabla_aplmovp' => env('PROVEEDOR_CC_ANITA_TABLA_APLMOVP', 'aplmovp'),

    /**
     * null = auto (AGG=true, resto=false).
     */
    'tiene_empresa' => env('PROVEEDOR_CC_ANITA_TIENE_EMPRESA'),

    'campos_promov' => env('PROVEEDOR_CC_ANITA_CAMPOS_PROMOV', ''),
    'campos_compra' => env('PROVEEDOR_CC_ANITA_CAMPOS_COMPRA', ''),
    'campos_aplmovp' => env('PROVEEDOR_CC_ANITA_CAMPOS_APLMOVP', ''),

    /**
     * Tipos que no son deuda/crédito pendiente de CC (pagos/anulaciones, recibos, etc.).
     * AOP = anulación de OP (espejo de OPP): se excluye igual que OPP.
     * OPA/EGR/IEV/NCJ sí se importan como crédito (anticipo / egreso / ajuste).
     */
    'tipos_no_deuda' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PROVEEDOR_CC_ANITA_TIPOS_NO_DEUDA', 'OPP,AOP,APA,REC,CHP,ANT'))
    ))),

    /**
     * Créditos pendientes en promov sin fila en Anita `compra`.
     * Se sintetiza pagoproveedor + CC negativa.
     */
    'tipos_credito_sin_compra' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PROVEEDOR_CC_ANITA_TIPOS_CREDITO_SIN_COMPRA', 'OPA,EGR,IEV,NCJ'))
    ))),

    'tolerancia_aplicado' => (float) env('PROVEEDOR_CC_ANITA_TOLERANCIA', 0.02),

    'empresa_id_default' => (int) env('PROVEEDOR_CC_ANITA_EMPRESA_ID', 1),

    'bridge_list_reintentos' => (int) env(
        'PROVEEDOR_CC_ANITA_BRIDGE_REINTENTOS',
        (int) env('ANITA_BRIDGE_LIST_REINTENTOS', 6)
    ),
];
