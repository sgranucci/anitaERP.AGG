<?php

return [
    /*
    | Saldos mensuales por cuenta contable (tabla cuentacontable_saldo_mes).
    | Mantenidos on-line por Asiento_MovimientoObserver cuando está habilitado.
    | Desactivado por defecto: activar tras contable:reconstruir-saldos-cuenta-mes.
    */
    'saldos_cuenta_mes' => [
        'observer_habilitado' => env('CONTABLE_SALDOS_CUENTA_MES_OBSERVER', false),
        'moneda_local_id' => (int) env('CONTABLE_SALDOS_CUENTA_MES_MONEDA_LOCAL_ID', env('COTIZACION_ID_MONEDA_DEFAULT', 1)),
    ],

    /*
    | Mayor por concepto (cierre mensual). Volúmenes altos: mes completo, varias empresas en consultas sucesivas.
    */
    'mayor_concepto' => [
        'memory_limit' => env('MAYOR_CONCEPTO_MEMORY_LIMIT', '2048M'),
        'max_execution_time' => (int) env('MAYOR_CONCEPTO_MAX_EXECUTION_TIME', 900),
        // Límite Anita in_limite_caja_banco (argv[13]). Acepta 112010-008 o 112010008.
        'limite_caja_banco' => (int) preg_replace('/\D/', '', (string) env('MAYOR_CONCEPTO_LIMITE_CAJA_BANCO', '112010008')),
        // Tope mayor analítico de control / conciliación (export l_mayor; ej. 112010-008).
        'limite_cuenta_analitico_control' => (int) preg_replace('/\D/', '', (string) env('MAYOR_CONCEPTO_LIMITE_CUENTA_ANALITICO_CONTROL', '112010008')),
    ],

    /*
    | Mayor plano por cuenta (l-mayor). Bridge ctamov + subdiario; volúmenes altos en cierre mensual.
    | fuente_erp_hasta: Y-m-d o Ymd — hasta esa fecha inclusive lee asientos ERP importados;
    | después sigue el bridge Anita. Vacío = solo Anita (legacy).
    */
    'mayor_plano_cuenta' => [
        'memory_limit' => env('MAYOR_PLANO_CUENTA_MEMORY_LIMIT', '1024M'),
        'max_execution_time' => (int) env('MAYOR_PLANO_CUENTA_MAX_EXECUTION_TIME', 900),
        'fuente_erp_hasta' => env('MAYOR_PLANO_CUENTA_FUENTE_ERP_HASTA', '2025-12-31'),
    ],

    /*
    | Balance de sumas y saldos (l-sumsal). Períodos → cuentacontable_saldo_mes; rango → asientos.
    */
    'sumas_saldos' => [
        'memory_limit' => env('SUMAS_SALDOS_MEMORY_LIMIT', '1024M'),
        'max_execution_time' => (int) env('SUMAS_SALDOS_MAX_EXECUTION_TIME', 600),
    ],
];
