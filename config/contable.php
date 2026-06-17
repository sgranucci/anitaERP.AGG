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
        'memory_limit' => env('MAYOR_CONCEPTO_MEMORY_LIMIT', '1024M'),
        'max_execution_time' => (int) env('MAYOR_CONCEPTO_MAX_EXECUTION_TIME', 900),
    ],

    /*
    | Mayor plano por cuenta (l-mayor). Bridge ctamov + subdiario; volúmenes altos en cierre mensual.
    */
    'mayor_plano_cuenta' => [
        'memory_limit' => env('MAYOR_PLANO_CUENTA_MEMORY_LIMIT', '1024M'),
        'max_execution_time' => (int) env('MAYOR_PLANO_CUENTA_MAX_EXECUTION_TIME', 900),
    ],
];
