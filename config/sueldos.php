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

];
