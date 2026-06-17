<?php

return [

    /*
    | Modo de aprobación de transferencias de mercadería:
    | - inmediata: salida + entrada en el mismo paso (sin bandeja pendiente)
    | - tipo_transaccion: usa tipotransaccion_stock.requiere_aprobacion
    | - siempre: toda transferencia exige aprobación del receptor
    */
    'transferencia_modo_aprobacion' => env('STOCK_TRANSFERENCIA_MODO_APROBACION', 'tipo_transaccion'),

    /** Horas de validez de enlaces de aprobación/rechazo por correo. */
    'transferencia_horas_validez_token' => (int) env('STOCK_TRANSFERENCIA_HORAS_TOKEN', 168),

    /** SKUs legacy TITO: precio promedio ponderado para asiento contable (Crown/Biyemas). */
    'transferencia_skus_tito' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('STOCK_TRANSFERENCIA_SKUS_TITO', '0000000201421,000100000-006,0000000201266,0000000201265'))
    ))),

];
