<?php

/**
 * Sync / auditoría diaria de facturas de proveedor ERP → Anita.
 */
return [

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
    |--------------------------------------------------------------------------
    */
    'conciliacion_mayor_cc' => [
        'habilitada' => filter_var(env('COMPROBANTE_PROVEEDOR_MAYOR_CC_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('COMPROBANTE_PROVEEDOR_MAYOR_CC_HORA', '08:35'),
        'email' => env('COMPROBANTE_PROVEEDOR_MAYOR_CC_EMAIL', env('COMPROBANTE_PROVEEDOR_AUDITORIA_ANITA_EMAIL', env('ORDENCOMPRA_AUDITORIA_ANITA_EMAIL', 'sergiogranucci@gmail.com'))),
        'ventana_dias' => max(1, (int) env('COMPROBANTE_PROVEEDOR_MAYOR_CC_VENTANA_DIAS', 30)),
        'tolerancia' => (float) env('COMPROBANTE_PROVEEDOR_MAYOR_CC_TOLERANCIA', 1.00),
        'mail_siempre' => filter_var(env('COMPROBANTE_PROVEEDOR_MAYOR_CC_MAIL_SIEMPRE', true), FILTER_VALIDATE_BOOLEAN),
        // Códigos Anita / plan de cuentas ERP (PROVEEDORES MN / ME).
        'cuenta_mn' => (int) env('COMPROBANTE_PROVEEDOR_MAYOR_CC_CUENTA_MN', 211010001),
        'cuenta_me' => (int) env('COMPROBANTE_PROVEEDOR_MAYOR_CC_CUENTA_ME', 211010011),
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
];
