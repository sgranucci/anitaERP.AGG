<?php

/*
| Motor de reglas de negocio transversal (sync decide / async avisos futuro).
| Interruptor fino: tabla regla_negocio_config.motor_habilitado (pantalla).
| Este flag es kill-switch de entorno (false = nunca evalúa aunque la pantalla diga sí).
*/

return [
    'habilitado_entorno' => filter_var(env('REGLA_NEGOCIO_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),

    'log_ejecuciones' => filter_var(env('REGLA_NEGOCIO_LOG', true), FILTER_VALIDATE_BOOLEAN),

    /** Días a conservar en regla_negocio_log (comando regla-negocio:purgar-logs). */
    'retencion_logs_dias' => max(1, (int) env('REGLA_NEGOCIO_LOG_RETENCION_DIAS', 90)),

    /**
     * Puntos ya consultados desde código (panel cobertura).
     * clave = punto, valor = clase Support/Service.
     */
    'puntos_cableados' => [
        'stock.tm.requiere_aprobacion' => 'App\\Support\\Stock\\TransferenciaMercaderiaAprobacionSupport',
        'compras.com.reserva_factura' => 'App\\Support\\Compras\\ComprobanteProveedorReservaComLegajoSupport',
        'contable.canon.alicuota' => 'App\\Support\\Contable\\CanonEntidades\\CanonEntidadesReglasSupport',
        'caja.programa_pago.cheques_param' => 'App\\Support\\Caja\\ProgramaPagoAsignarChequesConfigSupport',
        'configuracion.arbol.umbral_doble_aprobacion' => 'App\\Support\\Configuracion\\ArbolAprobacionUmbralSupport',
        'ventas.afip.circuito' => 'App\\Support\\Ventas\\FacturacionCircuitoAfipSupport',
    ],

    /*
    | Campos genéricos del diseñador. Cada punto puede declarar campos propios
    | en config/regla_negocio_puntos.php (se mergean en UI).
    */
    'campos' => [
        'empresa_id' => ['etiqueta' => 'Empresa ID', 'tipo' => 'numero'],
        'entity_id' => ['etiqueta' => 'Entidad ID', 'tipo' => 'numero'],
        'monto' => ['etiqueta' => 'Monto', 'tipo' => 'numero'],
        'moneda_id' => ['etiqueta' => 'Moneda ID', 'tipo' => 'numero'],
        'tipo' => ['etiqueta' => 'Tipo / abreviatura', 'tipo' => 'texto'],
        'estado' => ['etiqueta' => 'Estado', 'tipo' => 'texto'],
        'centrocosto_id' => ['etiqueta' => 'Centro de costo ID', 'tipo' => 'numero'],
        'proveedor_id' => ['etiqueta' => 'Proveedor ID', 'tipo' => 'numero'],
        'cuit' => ['etiqueta' => 'CUIT', 'tipo' => 'texto'],
        'entorno' => ['etiqueta' => 'Entorno (ferli/crown/…)', 'tipo' => 'texto'],
    ],

    'operadores' => [
        'eq' => 'Igual a',
        'neq' => 'Distinto de',
        'gt' => 'Mayor que',
        'gte' => 'Mayor o igual',
        'lt' => 'Menor que',
        'lte' => 'Menor o igual',
        'in' => 'En lista (coma)',
        'not_in' => 'No en lista (coma)',
        'contains' => 'Contiene texto',
        'empty' => 'Vacío / nulo',
        'not_empty' => 'Con valor',
    ],
];
