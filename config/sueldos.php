<?php

return [

    // Días de validez del link firmado de autorización de alta provisoria de empleado
    'empleado_alta_link_dias' => (int) env('SUELDOS_EMPLEADO_ALTA_LINK_DIAS', 14),

    // Firma pie recibo Anexo III
    'recibo_firma_nombre' => env('SUELDOS_RECIBO_FIRMA_NOMBRE', 'ERIKA PEREZ'),
    'recibo_firma_cargo' => env('SUELDOS_RECIBO_FIRMA_CARGO', 'CAPITAL HUMANO'),

    /**
     * Columna BASE del recibo (solo presentación, no altera importe).
     * sin_valor | siempre | no  — patrón Onvio Dto. 407
     */
    'recibo_base_modo' => env('SUELDOS_RECIBO_BASE_MODO', 'sin_valor'),

    /**
     * Proceso p-dtofallo: concepto de liquidación para cuotas de descuento por fallo.
     * Código Anita/ERP (ej. 192 = DESCUENTO PREMIO FALLO CAJA). 0 = no genera novedad.
     */
    'concepto_descuento_fallo_codigo' => (int) env('SUELDOS_CONCEPTO_DTO_FALLO', 192),

    /** Cuotas mensuales del plan de descuento (Anita: MESES_A_DESCONTAR = 10). */
    'meses_descuento_fallo' => (int) env('SUELDOS_MESES_DTO_FALLO', 10),

];
