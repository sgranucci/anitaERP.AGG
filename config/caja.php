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
     * Flash automático (Wigos + ERP) a las 14:30 sobre la jornada de ayer (cerrada).
     * Omite la empresa si un usuario ya cargó flash_caja para esa fecha en el ABM.
     */
    'flash_calculo_diario' => [
        'habilitado' => filter_var(env('FLASH_CAJA_CALCULO_DIARIO_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('FLASH_CAJA_CALCULO_DIARIO_HORA', '14:30'),
        'empresas_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('FLASH_CAJA_CALCULO_DIARIO_EMPRESAS_IDS', '1,2,3'))
        ), fn (int $id) => $id > 0)),
    ],

    /*
     * Quién puede marcar el flash diario como validado (tilde verde en Contable).
     * Logins: mbarrios + sergio/admin para soporte. El rol administrador también puede.
     * Ampliar con FLASH_CAJA_VALIDACION_USUARIOS.
     */
    'flash_validacion' => [
        'usuarios' => array_values(array_filter(array_map(
            'strtolower',
            array_map('trim', explode(',', (string) env('FLASH_CAJA_VALIDACION_USUARIOS', 'mbarrios,sergio,admin')))
        ), fn (string $login) => $login !== '')),
    ],

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
     * Cotización tesorería (Informix caja.cotiz_tes) por empresa.
     * Bridge: Biyemas = ANITA_IP; Kandiko/Rebisco = gastronomia.ticket_tarjeta_anita_por_empresa.
     */
    'cotizacion_tesoreria_anita_sistema' => env('CAJA_COTIZACION_TESORERIA_ANITA_SISTEMA', 'caja'),
    'cotizacion_tesoreria_anita_tabla' => env('CAJA_COTIZACION_TESORERIA_ANITA_TABLA', 'cotiz_tes'),
    'cotizacion_tesoreria_anita_empresas_sync' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CAJA_COTIZACION_TESORERIA_ANITA_EMPRESAS_SYNC', '1,2,3'))
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

    /*
     * IE desde solicitud de pago (Pagar IE):
     * tipo de transacción por abreviatura (default OPP). ID opcional pisa la abreviatura.
     */
    'ingresoegreso_sp_tipotransaccion_abreviatura' => env('CAJA_IE_SP_TIPOTRANSACCION_ABREV', 'OPP'),
    'ingresoegreso_sp_tipotransaccion_id' => (int) env('CAJA_IE_SP_TIPOTRANSACCION_ID', 0),
    /*
     * SP con tratamiento ANTICIPADA: cierra como OPA (anticipo a proveedores).
     * ID opcional pisa la abreviatura.
     */
    'ingresoegreso_sp_anticipo_tipotransaccion_abreviatura' => env('CAJA_IE_SP_ANTICIPO_TIPOTRANSACCION_ABREV', 'OPA'),
    'ingresoegreso_sp_anticipo_tipotransaccion_id' => (int) env('CAJA_IE_SP_ANTICIPO_TIPOTRANSACCION_ID', 0),

    /*
     * Numeración IE alineada a Anita ventas.numerador (num_clave por empresa).
     * false = solo MAX+1 ERP (semilla propia). true = lee/avanza Anita (hasta apagarlo).
     * Nota: las semillas viven en sistema "ventas" (no shared); OPP=223/224/225 = mismas OP Anita.
     */
    'ingresoegreso_anita_numeracion_habilitada' => filter_var(
        env('CAJA_IE_ANITA_NUMERACION_HABILITADA', true),
        FILTER_VALIDATE_BOOLEAN
    ),
    'ingresoegreso_anita_sistema_numerador' => env('CAJA_IE_ANITA_SISTEMA_NUMERADOR', 'ventas'),
    'ingresoegreso_anita_semillas' => [
        'OPP' => [
            1 => (int) env('CAJA_IE_ANITA_SEMILLA_OPP_EMP1', 223),
            2 => (int) env('CAJA_IE_ANITA_SEMILLA_OPP_EMP2', 224),
            3 => (int) env('CAJA_IE_ANITA_SEMILLA_OPP_EMP3', 225),
        ],
        // Misma serie de OP Anita; el tipo de comprobante diferencia OPA vs OPP.
        'OPA' => [
            1 => (int) env('CAJA_IE_ANITA_SEMILLA_OPA_EMP1', env('CAJA_IE_ANITA_SEMILLA_OPP_EMP1', 223)),
            2 => (int) env('CAJA_IE_ANITA_SEMILLA_OPA_EMP2', env('CAJA_IE_ANITA_SEMILLA_OPP_EMP2', 224)),
            3 => (int) env('CAJA_IE_ANITA_SEMILLA_OPA_EMP3', env('CAJA_IE_ANITA_SEMILLA_OPP_EMP3', 225)),
        ],
        'EGR' => [
            1 => (int) env('CAJA_IE_ANITA_SEMILLA_EGR_EMP1', 361),
            2 => (int) env('CAJA_IE_ANITA_SEMILLA_EGR_EMP2', 362),
            3 => (int) env('CAJA_IE_ANITA_SEMILLA_EGR_EMP3', 363),
        ],
        'ING' => [
            1 => (int) env('CAJA_IE_ANITA_SEMILLA_ING_EMP1', 346),
            2 => (int) env('CAJA_IE_ANITA_SEMILLA_ING_EMP2', 347),
            3 => (int) env('CAJA_IE_ANITA_SEMILLA_ING_EMP3', 348),
        ],
        'TRA' => [
            1 => (int) env('CAJA_IE_ANITA_SEMILLA_TRA_EMP1', 334),
            2 => (int) env('CAJA_IE_ANITA_SEMILLA_TRA_EMP2', 335),
            3 => (int) env('CAJA_IE_ANITA_SEMILLA_TRA_EMP3', 336),
        ],
    ],

    /*
     * Escritura Anita tesmov al grabar/borrar IE (OPP/EGR/ING/TRA).
     * Una fila por línea de cuentacaja (mismo patrón que cobranza).
     */
    /*
     * Escritura Anita che_ban al grabar/borrar IE (a-movim.c / pago.c):
     * pago + auxpag(tctes) + tesmov; cheques emitidos → cpromae + auxpag(CHP) + tesmov(CHP).
     */
    'ingresoegreso_anita_tesmov_habilitada' => filter_var(
        env('CAJA_IE_ANITA_TESMOV_HABILITADA', true),
        FILTER_VALIDATE_BOOLEAN
    ),
    'ingresoegreso_anita_tesmov_sistema' => env('CAJA_IE_ANITA_TESMOV_SISTEMA', 'che_ban'),
    /** Letra Anita (a-movim usa espacio). */
    'ingresoegreso_anita_tesmov_letra' => (($letraIeAnita = env('CAJA_IE_ANITA_TESMOV_LETRA')) === null
        || $letraIeAnita === '')
            ? ' '
            : (string) $letraIeAnita,
    /**
     * Sucursal Anita (pag_sucursal / tesv_sucursal / axp_sucursal / axp_sucursal_cob).
     * null/vacío = código empresa Anita (MultiEmpresa a-movim).
     * Forzar 0 con CAJA_IE_ANITA_TESMOV_SUCURSAL=0.
     */
    'ingresoegreso_anita_tesmov_sucursal' => (($sucIeAnita = env('CAJA_IE_ANITA_TESMOV_SUCURSAL')) === null
        || $sucIeAnita === '')
            ? null
            : (int) $sucIeAnita,

    /*
     * Árbol de aprobación opcional para IE por umbral de monto.
     * 0 = desactivado (default). Si monto IE >= umbral al guardar: log/warning stub (sin árbol IE completo).
     */
    'arbol_umbral_monto' => (float) env('CAJA_IE_ARBOL_UMBRAL_MONTO', 0),

    /*
     * Flash Report AGG (plantilla oficial). Menú caja/flash/reporte.
     * Distribución: flash:distribuir-reportes (horario de cada suscripción).
     */
    'flash_reporte_agg' => [
        'distribucion_habilitada' => filter_var(
            env('FLASH_REPORTE_AGG_DISTRIBUCION', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'reintento_smtp' => filter_var(
            env('FLASH_REPORTE_AGG_REINTENTO_SMTP', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'reintento_minutos' => (int) env('FLASH_REPORTE_AGG_REINTENTO_MINUTOS', 15),
        'reintento_intentos' => (int) env('FLASH_REPORTE_AGG_REINTENTO_INTENTOS', 2),
        'plantilla' => env(
            'FLASH_REPORTE_AGG_PLANTILLA',
            resource_path('templates/caja/flash/plantilla-flash-agg.xlsx')
        ),
        'empresas' => [
            1 => ['hoja' => 'Biyemas S.A.', 'datos' => 'Datos Biyemas'],
            2 => ['hoja' => 'Kandiko S.A', 'datos' => 'Datos Kandiko'],
            3 => ['hoja' => 'Rebisco S.A.', 'datos' => 'Datos Rebisco'],
        ],
    ],
];
