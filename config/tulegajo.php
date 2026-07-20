<?php

return [
    // Integración con TuLegajo.com (subida de comprobantes de entrega de indumentaria).
    'enabled' => env('TULEGAJO_ENABLED', false),

    // URL base de la API (V2). Cada endpoint concatena su ruta.
    'url' => env('TULEGAJO_API_URL', 'https://api.tulegajo.com/V2'),

    // API KEY por defecto (header x-api-key). Provista por soporte de TuLegajo.
    'api_key' => env('TULEGAJO_API_KEY', ''),

    // Header obligatorio desde 01/03/2025.
    'user_agent' => env('TULEGAJO_USER_AGENT', 'api-consumer'),

    // Lote donde se agrupan los comprobantes (se crea si no existe, por nombre + período MM-YYYY).
    'lote_nombre' => env('TULEGAJO_LOTE_NOMBRE', 'Entregas de indumentaria'),

    // Tipo de documento (opcional). Vacío = tipo por defecto de la empresa en TuLegajo.
    'tipo_documento_id' => env('TULEGAJO_TIPO_DOCUMENTO_ID', ''),

    // Timeout de las llamadas HTTP en segundos.
    'timeout' => (int) env('TULEGAJO_TIMEOUT', 60),

    /*
     * API KEY por empresa (opcional). La corporación puede tener una key por empresa.
     * Mapear empresa_id (ERP) => API KEY. Si no hay entrada, se usa api_key por defecto.
     * Configurable por env JSON: TULEGAJO_API_KEYS={"1":"clave-emp-1","2":"clave-emp-2"}
     */
    'api_keys' => json_decode((string) env('TULEGAJO_API_KEYS', '{}'), true) ?: [],
];
