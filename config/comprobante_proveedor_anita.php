<?php

/**
 * Sync / auditoría diaria de facturas de proveedor ERP → Anita.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Grabación en el momento de contabilizar. Solo Crown consulta este flag.
    | En AGG y el resto la factura se graba en Anita al contabilizar.
    | false en Crown: el ERP asienta y el cron de las 13 y 16 manda al bridge.
    |--------------------------------------------------------------------------
    */
    'grabacion_en_linea' => filter_var(
        env('COMPROBANTE_PROVEEDOR_ANITA_GRABACION_EN_LINEA', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    |--------------------------------------------------------------------------
    | Barrido ERP → bridge. Solo se agenda en Crown.
    | Horas separadas por coma, formato HH:MM. Default 13:00 y 16:00.
    |--------------------------------------------------------------------------
    */
    'sync_bridge' => [
        'habilitada' => filter_var(env('COMPROBANTE_PROVEEDOR_ANITA_SYNC_BRIDGE_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        'horas' => array_values(array_filter(array_map(
            static fn (string $hora): string => trim($hora),
            explode(',', (string) env('COMPROBANTE_PROVEEDOR_ANITA_SYNC_BRIDGE_HORAS', '13:00,16:00'))
        ), static fn (string $hora): bool => preg_match('/^\d{1,2}:\d{2}$/', $hora) === 1)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auditoría diaria tablas Anita (compra, concmov, promov, aplicped, ctamov)
    |--------------------------------------------------------------------------
    */
    'auditoria_diaria' => [
        'habilitada' => filter_var(env('COMPROBANTE_PROVEEDOR_AUDITORIA_ANITA_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('COMPROBANTE_PROVEEDOR_AUDITORIA_ANITA_HORA', '08:30'),
        'usuario_id' => (int) env('COMPROBANTE_PROVEEDOR_AUDITORIA_ANITA_USUARIO_ID', 1),
        'email' => env('COMPROBANTE_PROVEEDOR_AUDITORIA_ANITA_EMAIL', env('ORDENCOMPRA_AUDITORIA_ANITA_EMAIL', 'sergiogranucci@gmail.com')),
        'ventana_dias' => max(1, (int) env('COMPROBANTE_PROVEEDOR_AUDITORIA_ANITA_VENTANA_DIAS', 7)),
        'auto_reparar' => filter_var(env('COMPROBANTE_PROVEEDOR_AUDITORIA_ANITA_AUTO_REPARAR', true), FILTER_VALIDATE_BOOLEAN),
        'mail_siempre' => filter_var(env('COMPROBANTE_PROVEEDOR_AUDITORIA_ANITA_MAIL_SIEMPRE', false), FILTER_VALIDATE_BOOLEAN),
        'mail_si_reparo' => filter_var(env('COMPROBANTE_PROVEEDOR_AUDITORIA_ANITA_MAIL_SI_REPARO', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conciliación diaria mayor Anita AP (MN/ME) vs cuenta corriente (solo facturas)
    | Apagada: comparaba el neto del período contra solo facturas y no podía cerrar.
    | El auditor deuda vs ficha y delta mayor − deuda cubre ese control.
    |--------------------------------------------------------------------------
    */
    'conciliacion_mayor_cc' => [
        'habilitada' => filter_var(env('COMPROBANTE_PROVEEDOR_MAYOR_CC_HABILITADA', false), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('COMPROBANTE_PROVEEDOR_MAYOR_CC_HORA', '08:35'),
        'email' => env('COMPROBANTE_PROVEEDOR_MAYOR_CC_EMAIL', env('COMPROBANTE_PROVEEDOR_AUDITORIA_ANITA_EMAIL', env('ORDENCOMPRA_AUDITORIA_ANITA_EMAIL', 'sergiogranucci@gmail.com'))),
        'ventana_dias' => max(1, (int) env('COMPROBANTE_PROVEEDOR_MAYOR_CC_VENTANA_DIAS', 30)),
        'tolerancia' => (float) env('COMPROBANTE_PROVEEDOR_MAYOR_CC_TOLERANCIA', 1.00),
        'mail_siempre' => filter_var(env('COMPROBANTE_PROVEEDOR_MAYOR_CC_MAIL_SIEMPRE', true), FILTER_VALIDATE_BOOLEAN),
        // Códigos Anita / plan de cuentas ERP (PROVEEDORES MN / ME).
        'cuenta_mn' => (int) env('COMPROBANTE_PROVEEDOR_MAYOR_CC_CUENTA_MN', 211010001),
        'cuenta_me' => (int) env('COMPROBANTE_PROVEEDOR_MAYOR_CC_CUENTA_ME', 211010011),
        // Otras cuentas de proveedores a controlar (ej. no corrientes). Separadas por coma.
        'cuentas_mn_extra' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('COMPROBANTE_PROVEEDOR_MAYOR_CC_CUENTAS_MN_EXTRA', '222010001'))
        ), static fn (int $c) => $c > 0)),
        'cuentas_me_extra' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('COMPROBANTE_PROVEEDOR_MAYOR_CC_CUENTAS_ME_EXTRA', '221010004'))
        ), static fn (int $c) => $c > 0)),
        // Empresa ERP cuyo código Anita se usa para leer el mayor (0 = primera empresa activa).
        'empresa_id' => (int) env('COMPROBANTE_PROVEEDOR_MAYOR_CC_EMPRESA_ID', 0),
        // Tipos de subdiario a sumar como “factura” (vacío = todos los movimientos del mayor).
        'tipos_factura_subdiario' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'COMPROBANTE_PROVEEDOR_MAYOR_CC_TIPOS_FACTURA',
                'FGA,FGB,FGC,FGD,FGE,FGF,FGG,FGH,FIA,FIB,FIC,FID,FIE,FIF,FIG,FIH,FIS,FNS,FNB,FNC,FAC,FAD,FAE,DIS,CIS,NDC,NDB'
            ))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Control diario factura a factura: CC ERP ↔ asiento ERP ↔ ctamov Anita
    |--------------------------------------------------------------------------
    */
    'imputacion_ap_diaria' => [
        'habilitada' => filter_var(env('COMPROBANTE_PROVEEDOR_IMPUTACION_AP_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('COMPROBANTE_PROVEEDOR_IMPUTACION_AP_HORA', '08:40'),
        'email' => env('COMPROBANTE_PROVEEDOR_IMPUTACION_AP_EMAIL', env('COMPROBANTE_PROVEEDOR_AUDITORIA_ANITA_EMAIL', 'sergiogranucci@gmail.com')),
        'ventana_dias' => max(1, (int) env('COMPROBANTE_PROVEEDOR_IMPUTACION_AP_VENTANA_DIAS', 7)),
        'tolerancia' => (float) env('COMPROBANTE_PROVEEDOR_IMPUTACION_AP_TOLERANCIA', 0.05),
        'mail_siempre' => filter_var(env('COMPROBANTE_PROVEEDOR_IMPUTACION_AP_MAIL_SIEMPRE', true), FILTER_VALIDATE_BOOLEAN),
        'max_filas_mail' => max(10, (int) env('COMPROBANTE_PROVEEDOR_IMPUTACION_AP_MAX_FILAS_MAIL', 80)),
        'anita_reintentos_bridge' => max(1, (int) env('COMPROBANTE_PROVEEDOR_IMPUTACION_AP_ANITA_REINTENTOS', 3)),
        'empresas_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('COMPROBANTE_PROVEEDOR_IMPUTACION_AP_EMPRESAS_IDS', '1,2,3'))
        ))),
    ],
];
