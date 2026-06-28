<?php

return [
	'MANEJA_TABLA_CAJA' => 'S',
    'MUESTRA_MONEDAS' => [ '1', '2', '3', '4' ],
    'ID_MONEDA_DEFAULT_VOUCHER' => 1,

    /*
     * Cheques propios en ingreso/egreso y pago a proveedores:
     * posdatados → cuenta cheques diferidos; al día → banco (cuentacaja.cuentacontable_id).
     * Reclasificación diaria: caja:reclasificar-cheques-diferidos
     * Dejar CAJA_CHEQUE_PROPIO_IMPUTACION_DIFERIDOS=false hasta pruebas; catálogo 211010-013 ya puede cargarse.
     */
    'cheque_propio_imputacion_diferidos_habilitado' => filter_var(
        env('CAJA_CHEQUE_PROPIO_IMPUTACION_DIFERIDOS', false),
        FILTER_VALIDATE_BOOLEAN
    ),
    'cheques_diferidos_cuenta_codigo' => env('CAJA_CHEQUES_DIFERIDOS_CUENTA_CODIGO', '211010013'),
    'cheques_diferidos_cuenta_id' => (int) env('CAJA_CHEQUES_DIFERIDOS_CUENTA_ID', 0),

    /** Cheques recibidos de terceros (haber/debe según signo transacción). Fallback si no hay catálogo central. */
    'valores_a_depositar_cuenta_codigo' => env('CAJA_VALORES_A_DEPOSITAR_CUENTA_CODIGO', '111040000'),
    'valores_a_depositar_cuenta_id' => (int) env('CAJA_VALORES_A_DEPOSITAR_CUENTA_ID', 0),

    /** Hora del agente diario de reclasificación posdatados → banco (Kernel schedule). */
    'reclasificar_cheques_diferidos_hora' => env('CAJA_RECLASIFICAR_CHEQUES_DIFERIDOS_HORA', '06:30'),
    ];
