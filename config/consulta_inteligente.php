<?php

$timeout = (int) env('CI_LLM_TIMEOUT', 30);

$proveedoresNombrados = [
    'groq' => [
        'nombre' => 'Groq',
        'api_key' => (string) env('CI_LLM_GROQ_API_KEY', ''),
        'base_url' => rtrim((string) env('CI_LLM_GROQ_BASE_URL', 'https://api.groq.com/openai/v1'), '/'),
        'model' => (string) env('CI_LLM_GROQ_MODEL', 'openai/gpt-oss-20b'),
        'timeout' => $timeout,
    ],
    'gemini' => [
        'nombre' => 'Gemini',
        'api_key' => (string) env('CI_LLM_GEMINI_API_KEY', ''),
        'base_url' => rtrim((string) env('CI_LLM_GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/openai'), '/'),
        'model' => (string) env('CI_LLM_GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'timeout' => $timeout,
    ],
    // Compat: un solo proveedor genérico (OpenAI / Azure / otro)
    'primary' => [
        'nombre' => 'Primary',
        'api_key' => (string) env('CI_LLM_API_KEY', ''),
        'base_url' => rtrim((string) env('CI_LLM_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'model' => (string) env('CI_LLM_MODEL', 'gpt-4o-mini'),
        'timeout' => $timeout,
    ],
];

// Orden de failover. Default: groq → gemini → primary (solo los que tengan API key).
$cadena = array_values(array_filter(array_map(
    static fn (string $s): string => strtolower(trim($s)),
    explode(',', (string) env('CI_LLM_CHAIN', 'groq,gemini,primary'))
)));

return [
    /*
    |--------------------------------------------------------------------------
    | LLM opcional para lenguaje natural
    |--------------------------------------------------------------------------
    | API compatible con OpenAI Chat Completions. Desactivado por defecto.
    | Cadena de failover: CI_LLM_CHAIN=groq,gemini (si uno se acaba/falla, va el otro).
    | Claves: CI_LLM_GROQ_API_KEY / CI_LLM_GEMINI_API_KEY (y opcional CI_LLM_API_KEY).
    | Atención free: el proveedor puede usar el texto para mejorar productos.
    */
    'llm_enabled' => (bool) env('CI_LLM_ENABLED', false),
    'llm_timeout' => $timeout,
    'llm_providers' => $proveedoresNombrados,
    'llm_chain' => $cadena !== [] ? $cadena : ['groq', 'gemini', 'primary'],

    // Legacy (leídos también vía llm_providers.primary)
    'llm_api_key' => env('CI_LLM_API_KEY', ''),
    'llm_base_url' => rtrim((string) env('CI_LLM_BASE_URL', 'https://api.openai.com/v1'), '/'),
    'llm_model' => env('CI_LLM_MODEL', 'gpt-4o-mini'),

    'llm_aviso_privacidad' => env(
        'CI_LLM_AVISO_PRIVACIDAD',
        'No escribas datos personales ni secretos en la pregunta (CUIT, DNI, mails, cuentas, claves). Usá criterios de negocio: «pedidos pendientes este mes», «cheques para depositar».'
    ),
    'llm_sanitizar_prompt' => (bool) env('CI_LLM_SANITIZAR_PROMPT', true),

    /*
    | Proxy de salida hacia Groq/Gemini (solo se aplica si EMPRESA=CROWN).
    | Vacío / omitido = mismo Squid que ARCA_PADRON_PROXY_*. AGG/resto ignoran este bloque.
    */
    'llm_proxy_habilitado' => filter_var(env('CI_LLM_PROXY_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
    'llm_proxy_host' => (($__ciProxyHost = trim((string) env('CI_LLM_PROXY_HOST', ''))) !== ''
        ? $__ciProxyHost
        : trim((string) env('ARCA_PADRON_PROXY_HOST', ''))),
    'llm_proxy_port' => (int) ((trim((string) env('CI_LLM_PROXY_PORT', '')) !== '')
        ? env('CI_LLM_PROXY_PORT')
        : env('ARCA_PADRON_PROXY_PORT', 3128)),
    'llm_proxy_user' => (($__ciProxyUser = trim((string) env('CI_LLM_PROXY_USER', ''))) !== ''
        ? $__ciProxyUser
        : trim((string) env('ARCA_PADRON_PROXY_USER', ''))),
    'llm_proxy_password' => (($__ciProxyPass = (string) env('CI_LLM_PROXY_PASSWORD', '')) !== ''
        ? $__ciProxyPass
        : (string) env('ARCA_PADRON_PROXY_PASSWORD', '')),
];
