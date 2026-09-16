<?php

/**
 * Exportación de pagos Banco Macro (diskette / archivo).
 *
 * Canal actual: archivo (BNF.TXT + OPG.TXT + RTN.TXT) al estilo Anita p-enviamacro.c.
 * Premium / futuro: webservice — registrar driver en `canales` y setear MACRO_PAGO_CANAL.
 */

return [
    // archivo | webservice (webservice aún no implementado)
    'canal' => (string) env('MACRO_PAGO_CANAL', 'archivo'),

    'canales' => [
        'archivo' => \App\Support\Caja\Macro\MacroPagoCanalArchivo::class,
        // 'webservice' => \App\Support\Caja\Macro\MacroPagoCanalWebService::class,
    ],

    /** Código BCRA Macro (prefijo CBU / prop_cod_banco Anita). */
    'codigo_banco' => 285,

    /**
     * Cuenta débito Macro (15 dígitos) por código empresa Anita, como p-enviamacro.c.
     * Se completa sola: preferencia CBU de la cuenta de caja + sucursal; si no, este mapa.
     */
    'cuentas_debito' => [
        1 => (string) env('MACRO_CUENTA_DEBITO_1', '365109401242909'), // Biyemas
        2 => (string) env('MACRO_CUENTA_DEBITO_2', '365109401246079'), // Kandiko
        3 => (string) env('MACRO_CUENTA_DEBITO_3', '365109401250715'), // Rebisco
    ],

    'cuenta_debito_default' => (string) env('MACRO_CUENTA_DEBITO', ''),

    /**
     * Firmante / usuario de retenciones en RTN por empresa Anita (p-enviamacro).
     * Biyemas → SECORNEJO, Kandiko → SECORNEJO2, Rebisco → SECORNEJO1.
     */
    'usuarios_retencion' => [
        1 => (string) env('MACRO_USUARIO_RETENCION_1', 'SECORNEJO'),
        2 => (string) env('MACRO_USUARIO_RETENCION_2', 'SECORNEJO2'),
        3 => (string) env('MACRO_USUARIO_RETENCION_3', 'SECORNEJO1'),
    ],

    /** Tipos auxpag transferencia (Anita). */
    'tipos_ap_transferencia' => ['TMR', 'TMK', 'TMB'],

    /** Tipos auxpag cheque propio / pago bancarizado. */
    'tipos_ap_cheque' => ['CHP', 'CPC'],

    /**
     * Con tipo OPP (o vacío/0) también se incluyen estos tipos (reemplazos de cheques, etc.).
     */
    'tipos_op_extra_con_opp' => ['IEV'],

    /** Sucursal entrega banco Gerli (siempre 651 en AGG). */
    'sucursal_default' => (int) env('MACRO_SUCURSAL_DEFAULT', 651),
];
