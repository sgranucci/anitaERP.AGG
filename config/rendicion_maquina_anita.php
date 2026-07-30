<?php

return [

    /**
     * Réplica rendición de máquinas en Informix (rendmaquina + rendvalor + rendmapgasto).
     */
    'sincronizar' => filter_var(
        env('RENDICION_MAQUINA_SINCRONIZAR_ANITA', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    'sistema' => env('RENDICION_MAQUINA_ANITA_SISTEMA', 'caja'),

    'tabla_cabecera' => env('RENDICION_MAQUINA_ANITA_TABLA', 'rendmaquina'),

    'tabla_valor' => env('RENDICION_MAQUINA_ANITA_TABLA_VALOR', 'rendvalor'),

    'tabla_gasto' => env('RENDICION_MAQUINA_ANITA_TABLA_GASTO', 'rendmapgasto'),

    /**
     * Producción Anita usa 'F' (igual gastronomía/bingo/vending).
     * El C histórico mapeaba EFE_FINAL→'1'; no se observa en datos actuales.
     */
    'tipo_oper' => env('RENDICION_MAQUINA_ANITA_TIPO_OPER', 'F'),

    /** Estado pendiente de cierre contable (espacio en blanco). */
    'estado_pendiente' => env('RENDICION_MAQUINA_ANITA_ESTADO_PENDIENTE', ' '),

    /**
     * Columnas Informix rendmapgasto (prefijo histórico renmap_).
     * Ajustables si el bridge reporta columnas distintas.
     */
    'gasto_col_nro_oper' => env('RENDICION_MAQUINA_ANITA_GASTO_COL_NRO', 'renmap_nro_oper'),
    'gasto_col_orden' => env('RENDICION_MAQUINA_ANITA_GASTO_COL_ORDEN', 'renmap_orden'),
    'gasto_col_codigo' => env('RENDICION_MAQUINA_ANITA_GASTO_COL_CODIGO', 'renmap_codigo'),
    'gasto_col_importe' => env('RENDICION_MAQUINA_ANITA_GASTO_COL_IMPORTE', 'renmap_importe'),

    /** Caja Informix por defecto cuando no hay otra fuente. */
    'caja_id_default_por_empresa' => [
        1 => (int) env('RENDICION_MAQUINA_CAJA_DEFAULT_1', 12),
        2 => (int) env('RENDICION_MAQUINA_CAJA_DEFAULT_2', 12),
        3 => (int) env('RENDICION_MAQUINA_CAJA_DEFAULT_3', 12),
        4 => (int) env('RENDICION_MAQUINA_CAJA_DEFAULT_4', 12),
    ],

];
