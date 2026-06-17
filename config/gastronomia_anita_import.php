<?php

/**
 * Importación puntual de facturas gastronomía desde Informix (venta + resvta) tras caída de BD ERP.
 */
return [
    'empresa_id' => (int) env('GASTRONOMIA_ANITA_IMPORT_EMPRESA_ID', 1),

    'cuentacaja_fiserv_id' => (int) env('GASTRONOMIA_ANITA_IMPORT_CUENTACAJA_FISERV_ID', 233),

    /**
     * identificador_pc por código de sucursal Anita (ven_sucursal / resv_sucursal).
     *
     * @var array<int, string>
     */
    'identificador_pc_por_sucursal' => [
        3 => env('GASTRONOMIA_ANITA_IMPORT_PC_SUCURSAL_3', '10.20.30.98'),
        5 => env('GASTRONOMIA_ANITA_IMPORT_PC_SUCURSAL_5', '10.20.29.40'),
        8 => env('GASTRONOMIA_ANITA_IMPORT_PC_SUCURSAL_8', '10.20.30.92'),
        15 => env('GASTRONOMIA_ANITA_IMPORT_PC_SUCURSAL_15', '192.168.20.152'),
        20 => env('GASTRONOMIA_ANITA_IMPORT_PC_SUCURSAL_20', '10.20.30.92'),
        31 => env('GASTRONOMIA_ANITA_IMPORT_PC_SUCURSAL_31', '192.168.20.152'),
        34 => env('GASTRONOMIA_ANITA_IMPORT_PC_SUCURSAL_34', '192.168.40.142'),
        30 => env('GASTRONOMIA_ANITA_IMPORT_PC_SUCURSAL_30', '192.168.40.142'),
        35 => env('GASTRONOMIA_ANITA_IMPORT_PC_SUCURSAL_35', '192.168.40.227'),
    ],

    'cliente_consumidor_final_id' => (int) env('GASTRONOMIA_ANITA_IMPORT_CLIENTE_ID', 1),

    'actividad_arca_id' => (int) env('GASTRONOMIA_ANITA_IMPORT_ACTIVIDAD_ARCA_ID', 1),

    'condicioniva_consumidor_final_id' => (int) env('GASTRONOMIA_CONSUMIDOR_FINAL_CONDICIONIVA_ID', 3),

    'genera_contabilidad_cobranza' => filter_var(
        env('GASTRONOMIA_ANITA_IMPORT_CONTABILIDAD_COBRANZA', false),
        FILTER_VALIDATE_BOOLEAN,
    ),
];
