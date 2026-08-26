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

        /*
        | Control nocturno de integridad: compara el snapshot mensual contra los asientos.
        | Un desvío silencioso hace salir mal el balance impreso (incidente 11/ago/2026).
        | empresas: IDs separados por coma. ventana_meses: 0 = todo el histórico.
        */
        'integridad_diaria' => [
            'habilitada' => env('CONTABLE_SALDOS_INTEGRIDAD_HABILITADA', true),
            'hora' => env('CONTABLE_SALDOS_INTEGRIDAD_HORA', '06:10'),
            'email' => env('CONTABLE_SALDOS_INTEGRIDAD_EMAIL', ''),
            'empresas' => env('CONTABLE_SALDOS_INTEGRIDAD_EMPRESAS', '1,2,3'),
            'ventana_meses' => (int) env('CONTABLE_SALDOS_INTEGRIDAD_VENTANA_MESES', 24),
            'mail_siempre' => env('CONTABLE_SALDOS_INTEGRIDAD_MAIL_SIEMPRE', false),
        ],
    ],

    /*
    | Reportes contables definibles.
    | distribucion: envío automático por mail de los informes suscriptos. El comando
    | contable:distribuir-reportes-definibles corre cada hora y cada suscripción define
    | su día y hora; por eso acá solo se habilita o deshabilita el mecanismo.
    */
    'reporte_definible' => [
        'distribucion' => [
            'habilitada' => env('CONTABLE_REPORTE_DEFINIBLE_DISTRIBUCION', true),
        ],
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
    | Cierre rendición estacionamiento / bingo: un proceso por empresa (evita ctamov duplicado).
    */
    'cierre_estacionamiento_lock_segundos' => (int) env('CIERRE_ESTACIONAMIENTO_LOCK_SEGUNDOS', 600),
    'cierre_estacionamiento_lock_espera_segundos' => (int) env('CIERRE_ESTACIONAMIENTO_LOCK_ESPERA_SEGUNDOS', 30),
    'cierre_bingo_lock_segundos' => (int) env('CIERRE_BINGO_LOCK_SEGUNDOS', 600),
    'cierre_bingo_lock_espera_segundos' => (int) env('CIERRE_BINGO_LOCK_ESPERA_SEGUNDOS', 30),
    'asiento_numeracion_lock_segundos' => (int) env('ASIENTO_NUMERACION_LOCK_SEGUNDOS', 60),
    'asiento_numeracion_lock_espera_segundos' => (int) env('ASIENTO_NUMERACION_LOCK_ESPERA_SEGUNDOS', 30),

    /*
    | Balance de sumas y saldos (l-sumsal). Períodos → cuentacontable_saldo_mes; rango → asientos.
    */
    'sumas_saldos' => [
        'memory_limit' => env('SUMAS_SALDOS_MEMORY_LIMIT', '1024M'),
        'max_execution_time' => (int) env('SUMAS_SALDOS_MAX_EXECUTION_TIME', 600),
    ],
];
