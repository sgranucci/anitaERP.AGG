<?php

/**
 * Puente L8 → L12 (Calzados Ferli).
 * L8 = servidor legacy (típicamente 160.132.0.209); L12 = este ERP (anitaERP_l12).
 */
return [

    /*
     * Base URL del ERP L8 (sin barra final), para el bridge HTTP si mysql_l8 no responde.
     * Ejemplo: http://160.132.0.209/anitaERP/public
     */
    'http_base_url' => rtrim((string) env('FERLI_L8_HTTP_BASE_URL', 'http://160.132.0.209/anitaERP/public'), '/'),

    /*
     * Token compartido L8↔L12 para export/import. Vacío = bridge HTTP deshabilitado.
     */
    'http_token' => (string) env('FERLI_L8_HTTP_TOKEN', ''),

    /*
     * Timeout HTTP al pedir payloads a L8 (segundos).
     */
    'http_timeout' => (int) env('FERLI_L8_HTTP_TIMEOUT', 120),

    /*
     * En L8: habilitar rutas de exportación (solo lectura) protegidas por token.
     * En L12 dejar false (acá se consume, no se exporta la BD operativa).
     */
    'export_enabled' => filter_var(env('FERLI_L8_EXPORT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /*
     * Bloquear altas de pedido y OT en L8 (evitar colisiones de numerador con L12).
     * Vacío/ausente = auto (Ferli + DB_DATABASE=anitaERP); true/false fuerza.
     * En L12 dejar false o ausente (BD anitaERP_l12 no dispara el auto).
     */
    'bloquear_altas_pedido_ot' => env('FERLI_L8_BLOQUEAR_ALTAS_PEDIDO_OT'),
];
