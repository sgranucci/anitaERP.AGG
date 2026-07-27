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

    /*
     * Clientes VIP caja (Anita base_admin.clivip). Solo importación; no create/update/delete hacia Anita.
     * Reutiliza bridge por empresa de tickettarj/gastronomía (mismo host Informix).
     */
    'cliente_vip_anita_sistema' => env('CAJA_CLIENTE_VIP_ANITA_SISTEMA', 'base_admin'),
    'cliente_vip_anita_tabla' => env('CAJA_CLIENTE_VIP_ANITA_TABLA', 'clivip'),
    'cliente_vip_anita_campos_listado' => env(
        'CAJA_CLIENTE_VIP_ANITA_CAMPOS_LISTADO',
        'inumeroid,cnrodocumento,capellido,cnombre,iusualtaid,ifechaalta,choraalta,iusuumodid,ifechaumod,choraumod,clivi_nickname,clivi_localidad'
    ),

    /**
     * Orden de importación desde Anita: 1=Biyemas, 2=Kandiko, 3=Rebisco.
     *
     * @var list<int>
     */
    'cliente_vip_anita_empresas_sync' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CAJA_CLIENTE_VIP_ANITA_EMPRESAS_SYNC', '1,2,3'))
    ), fn (int $id) => $id > 0)),

    /*
     * Generación de tickets canje (vales gastronomía) en anitaERP.
     * Numeración movimiento_id + numero_ticket por empresa (código barras 6+6).
     */
    'ticket_canje_porcentaje' => (float) env('CAJA_TICKET_CANJE_PORCENTAJE', 5),
    'ticket_canje_vencimiento_dias' => (int) env('CAJA_TICKET_CANJE_VENCIMIENTO_DIAS', 30),
    'ticket_canje_comando_timeout_segundos' => (int) env('CAJA_TICKET_CANJE_COMANDO_TIMEOUT', 30),

    /*
     * Asignación diaria cajero↔caja (ABM caja/cajaasignacion).
     * AGG: false (caja desde config PC + fallback). Crown u otros: true.
     * Default: false si EMPRESA=AGG.
     */
    'requiere_asignacion' => filter_var(
        env(
            'CAJA_REQUIERE_ASIGNACION',
            strtoupper(trim((string) env('EMPRESA', 'AGG'), " \t\n\r\0\x0B'\"")) !== 'AGG'
        ),
        FILTER_VALIDATE_BOOLEAN
    ),

    /** Fallback de caja física cuando la PC no tiene caja_id en config. */
    'caja_default_id' => (int) env('CAJA_DEFAULT_ID', 1),
];
