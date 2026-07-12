<?php

return [

    /**
     * Réplica rendición bingo en Informix (tabla rendbingo) vía bridge Anita.
     */
    'sincronizar' => filter_var(
        env('RENDICION_BINGO_SINCRONIZAR_ANITA', env('BINGO_SINCRONIZAR_ANITA', true)),
        FILTER_VALIDATE_BOOLEAN
    ),

    'sistema' => env('RENDICION_BINGO_ANITA_SISTEMA', env('BINGO_ANITA_SISTEMA', 'caja')),

    'tabla_cabecera' => 'rendbingo',

    /** Detalle de la rendición bingo en Informix. */
    'tabla_premio' => 'rendpremio',

    'tabla_carton' => 'rendcarton',

    'tipo_oper' => env('RENDICION_BINGO_ANITA_TIPO_OPER', env('BINGO_ANITA_TIPO_OPER', 'F')),

    /** Estado pendiente de cierre contable (espacio en blanco, como vending/estacionamiento). */
    'estado_pendiente' => env('RENDICION_BINGO_ANITA_ESTADO_PENDIENTE', ' '),

    'nro_oper_piso_global' => (int) env('RENDICION_BINGO_NRO_OPER_PISO_GLOBAL', 700001),

    'nro_oper_techo_global' => (int) env('RENDICION_BINGO_NRO_OPER_TECHO_GLOBAL', 0),

    /** Caja Informix por defecto cuando la terminal no tiene cuenta asignada. */
    'caja_id_default_por_empresa' => [
        1 => (int) env('RENDICION_BINGO_CAJA_DEFAULT_1', 1),
        2 => (int) env('RENDICION_BINGO_CAJA_DEFAULT_2', 1),
        3 => (int) env('RENDICION_BINGO_CAJA_DEFAULT_3', 1),
        4 => (int) env('RENDICION_BINGO_CAJA_DEFAULT_4', 1),
    ],
];
