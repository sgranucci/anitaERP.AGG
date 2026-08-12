<?php

return [
    /*
     * Modo por defecto si la empresa no tiene fila en configuracion_propuesta_pago.
     * premium = árbol de aprobación del lote | light = auto-autoriza sin árbol
     */
    'modo_default' => env('PROPUESTA_PAGO_MODO_DEFAULT', 'premium'),

    'exige_arbol_default' => filter_var(
        env('PROPUESTA_PAGO_EXIGE_ARBOL_DEFAULT', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
     * Al ejecutar una propuesta autorizada, crear OP en CONFIRMADA (true) o PRE CARGA (false).
     * Por empresa se puede override en configuracion_propuesta_pago.
     */
    'ejecutar_confirmada' => filter_var(
        env('PROPUESTA_PAGO_EJECUTAR_CONFIRMADA', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    'permite_op_sin_propuesta_default' => filter_var(
        env('PROPUESTA_PAGO_PERMITE_OP_SIN_PROPUESTA', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
     * Al ejecutar lote, calcular y persistir retenciones en cada OP (G/I/S/B).
     */
    'calcular_retenciones_al_ejecutar' => filter_var(
        env('PROPUESTA_PAGO_CALCULAR_RETENCIONES', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
     * Conciliar OP del lote con transferencias Interbanking ya sincronizadas.
     * El archivo bancario (CSV) se genera aparte vía lote_bancario (no hay POST create en API IB).
     */
    'bridge_bancario_habilitado' => filter_var(
        env('PROPUESTA_PAGO_BRIDGE_BANCARIO', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /* Clearing avanzado (scoring / sugerencias) */
    'clearing_score_auto' => (int) env('PROPUESTA_PAGO_CLEARING_SCORE_AUTO', 90),
    'clearing_score_sugerir' => (int) env('PROPUESTA_PAGO_CLEARING_SCORE_SUGERIR', 60),
    'clearing_dias_ventana' => (int) env('PROPUESTA_PAGO_CLEARING_DIAS', 7),
    'clearing_tolerancia_monto' => (float) env('PROPUESTA_PAGO_CLEARING_TOLERANCIA', 0.05),

    /*
     * Driver de exportación bancaria por convenio.
     * Default csv_generico. Cablear driver a medida cuando se cierre convenio con el banco:
     * implementar PropuestaPagoConvenioBancarioDriver y registrarlo en convenio_drivers.
     */
    'convenio_driver' => env('PROPUESTA_PAGO_CONVENIO_DRIVER', 'csv_generico'),

    'convenio_drivers' => [
        'csv_generico' => \App\Support\Compras\PropuestaPagoConvenioCsvGenericoDriver::class,
        // 'galicia_xxx' => \App\Support\Compras\Convenios\GaliciaXxxDriver::class,
    ],
];
