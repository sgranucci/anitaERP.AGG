<?php

/**
 * Auditores ERP-only de facturas de proveedor (sin Anita).
 * Contaduría: P1 FAR vs cuenta OC; P3 IVA fuera de tipo; anticipo BS.USO sin Capex.
 */
return [

    'email' => env(
        'COMPROBANTE_PROVEEDOR_AUDITORIA_ERP_EMAIL',
        env('COMPROBANTE_PROVEEDOR_AUDITORIA_ANITA_EMAIL', 'sergiogranucci@gmail.com')
    ),

    /*
    |--------------------------------------------------------------------------
    | P1 — Diferencia FC vs COM imputada a FAR en lugar de cuentas de la OC
    |--------------------------------------------------------------------------
    | Compara el FAR del asiento grabado vs el FAR que armaría hoy el motor
    | (provisión COM; la diferencia debe ir a cuentas de artículos / OC).
    */
    'far_diferencia' => [
        'habilitada' => filter_var(env('CP_AUDITOR_FAR_DIFERENCIA_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('CP_AUDITOR_FAR_DIFERENCIA_HORA', '08:55'),
        // fechaiva = período contable Contaduría. Default: mes calendario en curso.
        'campo_fecha' => env('CP_AUDITOR_FAR_DIFERENCIA_CAMPO_FECHA', 'fechaiva'),
        'tolerancia' => (float) env('CP_AUDITOR_FAR_DIFERENCIA_TOLERANCIA', 0.05),
        'mail_siempre' => filter_var(env('CP_AUDITOR_FAR_DIFERENCIA_MAIL_SIEMPRE', false), FILTER_VALIDATE_BOOLEAN),
        'max_filas_mail' => max(10, (int) env('CP_AUDITOR_FAR_DIFERENCIA_MAX_FILAS', 80)),
        'codigo_far' => (string) env('CP_AUDITOR_FAR_DIFERENCIA_CODIGO_FAR', '211010004'),
    ],

    /*
    |--------------------------------------------------------------------------
    | P3 — Conceptos IVA que no pertenecen al tipo de comprobante
    |--------------------------------------------------------------------------
    */
    'iva_fuera_tipo' => [
        'habilitada' => filter_var(env('CP_AUDITOR_IVA_TIPO_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('CP_AUDITOR_IVA_TIPO_HORA', '09:00'),
        'campo_fecha' => env('CP_AUDITOR_IVA_TIPO_CAMPO_FECHA', 'fechaiva'),
        'mail_siempre' => filter_var(env('CP_AUDITOR_IVA_TIPO_MAIL_SIEMPRE', false), FILTER_VALIDATE_BOOLEAN),
        'max_filas_mail' => max(10, (int) env('CP_AUDITOR_IVA_TIPO_MAX_FILAS', 80)),
        // Incluir solo tipoconcepto I (impuesto IVA). Percepciones/retenciones pueden
        // estar en varios tipos sin ser el reclamo de Contaduría.
        'solo_impuesto_iva' => filter_var(env('CP_AUDITOR_IVA_TIPO_SOLO_I', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anticipo bienes de uso (123010007) sin Capex en la OC
    |--------------------------------------------------------------------------
    */
    'anticipo_bsuso_sin_capex' => [
        'habilitada' => filter_var(env('CP_AUDITOR_BSUSO_CAPEX_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('CP_AUDITOR_BSUSO_CAPEX_HORA', '09:05'),
        'campo_fecha' => env('CP_AUDITOR_BSUSO_CAPEX_CAMPO_FECHA', 'fechaiva'),
        'mail_siempre' => filter_var(env('CP_AUDITOR_BSUSO_CAPEX_MAIL_SIEMPRE', false), FILTER_VALIDATE_BOOLEAN),
        'max_filas_mail' => max(10, (int) env('CP_AUDITOR_BSUSO_CAPEX_MAX_FILAS', 80)),
        'codigo_cuenta' => (string) env('CP_AUDITOR_BSUSO_CAPEX_CODIGO', '123010007'),
    ],
];
