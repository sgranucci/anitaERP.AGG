<?php

/*
|--------------------------------------------------------------------------
| Plataforma de IA (App\Services\Ai)
|--------------------------------------------------------------------------
| Capa transversal equivalente al modelo SAP Business AI:
|   - Context : el contexto lo arma cada Skill (maestros + permisos).
|   - Build   : AiGateway + drivers (Ollama / HTTP) + Skills tipadas.
|   - Governance: AiPolicy (habilitación, permisos, umbral auto-aplicar,
|                 kill-switch) + AiDecisionLogger (auditoría en ai_decision).
|
| Reutiliza el mismo Ollama del pipeline de facturas (config/comprobante_proveedor_pdf_ia.php).
*/

return [

    // Interruptor global. Si es false, ninguna Skill se ejecuta (kill-switch maestro).
    'habilitado' => filter_var(env('AI_HABILITADO', false), FILTER_VALIDATE_BOOLEAN),

    // Kill-switch de emergencia independiente del flag de habilitación (para cortar sin tocar features).
    'kill_switch' => filter_var(env('AI_KILL_SWITCH', false), FILTER_VALIDATE_BOOLEAN),

    // Driver por defecto cuando la Skill no fija uno.
    'driver' => env('AI_DRIVER', 'ollama'),

    // Canal de log estructurado de llamadas al modelo (trazas técnicas).
    'log_channel' => env('AI_LOG_CHANNEL', 'ai'),

    /*
    | Persistencia de decisiones de negocio en tabla ai_decision.
    | Es la "governance layer": qué propuso la IA, con qué score y qué hizo el humano.
    */
    'decision_log' => [
        'persistir' => filter_var(env('AI_DECISION_LOG_PERSISTIR', true), FILTER_VALIDATE_BOOLEAN),
        // Recorte del payload guardado (evita filas gigantes con OCR completo).
        'max_payload_chars' => (int) env('AI_DECISION_LOG_MAX_PAYLOAD_CHARS', 20000),
    ],

    /*
    | Drivers disponibles. AiGateway resuelve por nombre.
    */
    'drivers' => [

        'ollama' => [
            'url' => rtrim((string) env('AI_OLLAMA_URL', env('COMPROBANTE_PROVEEDOR_PDF_IA_OLLAMA_URL', 'http://127.0.0.1:11434')), '/'),
            'model' => env('AI_OLLAMA_MODEL', 'qwen2.5:14b-instruct'),
            'timeout' => (int) env('AI_OLLAMA_TIMEOUT', 180),
            'temperature' => (float) env('AI_OLLAMA_TEMPERATURE', 0.05),
            'max_tokens' => (int) env('AI_OLLAMA_MAX_TOKENS', 4096),
        ],

        // OpenAI-compatible (/v1/chat/completions): sirve para OpenAI, vLLM, LM Studio, etc.
        'http' => [
            'url' => rtrim((string) env('AI_HTTP_URL', ''), '/'),
            'api_key' => env('AI_HTTP_API_KEY', ''),
            'model' => env('AI_HTTP_MODEL', ''),
            'timeout' => (int) env('AI_HTTP_TIMEOUT', 120),
            'temperature' => (float) env('AI_HTTP_TEMPERATURE', 0.05),
            'max_tokens' => (int) env('AI_HTTP_MAX_TOKENS', 4096),
        ],
    ],

    /*
    | Registro de Skills. Cada entrada declara su gobernanza.
    |   habilitada         : encender/apagar sin desplegar código.
    |   permiso            : permiso Laravel requerido para ejecutar (null = sin chequeo extra).
    |   auto_aplicar_score : umbral [0..1]; por encima, la Skill puede auto-aplicar sin HITL.
    |                        0 (default) = SIEMPRE requiere confirmación humana (recomendado en $/stock).
    |   driver             : override de driver (null = usa el default global).
    |
    | Las claves deben coincidir con AiSkillInterface::nombre().
    */
    'skills' => [
        'extraer_factura_proveedor' => [
            'habilitada' => filter_var(env('AI_SKILL_EXTRAER_FACTURA', true), FILTER_VALIDATE_BOOLEAN),
            'permiso' => 'crear-precarga-proveedores',
            'auto_aplicar_score' => (float) env('AI_SKILL_EXTRAER_FACTURA_AUTO_SCORE', 0),
            'driver' => null,
        ],

        'extraer_comprobante_iva_caja' => [
            'habilitada' => filter_var(env('AI_SKILL_EXTRAER_COMPROBANTE_IVA_CAJA', true), FILTER_VALIDATE_BOOLEAN),
            'permiso' => 'crear-ingresos-egresos-caja',
            'auto_aplicar_score' => (float) env('AI_SKILL_EXTRAER_COMPROBANTE_IVA_CAJA_AUTO_SCORE', 0),
            'driver' => null,
        ],

        'emparejar_remito_recepcion' => [
            'habilitada' => filter_var(env('AI_SKILL_EMPAREJAR_REMITO_RECEPCION', true), FILTER_VALIDATE_BOOLEAN),
            'permiso' => 'ocr-recepcion-proveedor',
            'auto_aplicar_score' => (float) env('AI_SKILL_EMPAREJAR_REMITO_RECEPCION_AUTO_SCORE', 0),
            'driver' => null,
        ],

        'sugerir_pares_conciliacion_bancaria' => [
            'habilitada' => filter_var(env('AI_SKILL_SUGERIR_PARES_CONCILIACION_BANCARIA', true), FILTER_VALIDATE_BOOLEAN),
            'permiso' => 'ejecutar-conciliacion-bancaria',
            'auto_aplicar_score' => (float) env('AI_SKILL_SUGERIR_PARES_CONCILIACION_BANCARIA_AUTO_SCORE', 0),
            'driver' => null,
        ],

        // Portales árbol (hash público): permiso vacío. Flag legacy OC sigue valiendo como fallback.
        'explicar_contexto_arbol_aprobacion' => [
            'habilitada' => filter_var(
                env(
                    'AI_SKILL_EXPLICAR_CONTEXTO_ARBOL_APROBACION',
                    env('AI_SKILL_EXPLICAR_CONTEXTO_ARBOL_APROBACION_OC', true)
                ),
                FILTER_VALIDATE_BOOLEAN
            ),
            'permiso' => null,
            'auto_aplicar_score' => (float) env(
                'AI_SKILL_EXPLICAR_CONTEXTO_ARBOL_APROBACION_AUTO_SCORE',
                env('AI_SKILL_EXPLICAR_CONTEXTO_ARBOL_APROBACION_OC_AUTO_SCORE', 0)
            ),
            'driver' => null,
        ],

        'explicar_diferencias_conciliacion_turno_gastronomia' => [
            'habilitada' => filter_var(env('AI_SKILL_EXPLICAR_DIFERENCIAS_CONCILIACION_TURNO_GASTRONOMIA', true), FILTER_VALIDATE_BOOLEAN),
            'permiso' => 'gestionar-habilitacion-turno-gastronomia',
            'auto_aplicar_score' => (float) env('AI_SKILL_EXPLICAR_DIFERENCIAS_CONCILIACION_TURNO_GASTRONOMIA_AUTO_SCORE', 0),
            'driver' => null,
        ],

        // Fase C — diálogo operativo (chips + NL; LLM solo clasifica)
        'consultar_contexto_operativo' => [
            'habilitada' => filter_var(env('AI_SKILL_CONSULTAR_CONTEXTO_OPERATIVO', true), FILTER_VALIDATE_BOOLEAN),
            'permiso' => 'ejecutar-consulta-ia',
            'auto_aplicar_score' => (float) env('AI_SKILL_CONSULTAR_CONTEXTO_OPERATIVO_AUTO_SCORE', 0),
            'driver' => null,
            // Si reglas no alcanzan: clasificar frase con Ollama/HTTP (JSON tipado)
            'llm_router' => filter_var(env('AI_SKILL_CONSULTAR_CONTEXTO_OPERATIVO_LLM_ROUTER', true), FILTER_VALIDATE_BOOLEAN),
            'llm_timeout' => (int) env('AI_SKILL_CONSULTAR_CONTEXTO_OPERATIVO_LLM_TIMEOUT', 45),
        ],

        // Pedido por consumo (CC + depósito) → sugerencia RQ compra / sala (HITL)
        'sugerir_pedido_consumo_sector' => [
            'habilitada' => filter_var(env('AI_SKILL_SUGERIR_PEDIDO_CONSUMO_SECTOR', true), FILTER_VALIDATE_BOOLEAN),
            'permiso' => 'ejecutar-consulta-ia',
            'auto_aplicar_score' => (float) env('AI_SKILL_SUGERIR_PEDIDO_CONSUMO_SECTOR_AUTO_SCORE', 0),
            'driver' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agente por evento (puente auditores → plan HITL)
    |--------------------------------------------------------------------------
    | Los auditores (WSAPOC, Z faltante, etc.) siguen siendo determinísticos.
    | Si hay hallazgo, opcionalmente registran ai_agente_evento + plan sugerido.
    */
    'agente_evento' => [
        'habilitado' => filter_var(env('AI_AGENTE_EVENTO_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'max_payload_chars' => (int) env('AI_AGENTE_EVENTO_MAX_PAYLOAD_CHARS', 8000),
        // Vacío = todos los eventos conocidos; lista CSV para restringir
        'eventos' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'AI_AGENTE_EVENTO_PERMITIDOS',
                'desvio_conciliacion,factura_apocrifa,z_transmision_faltante,deuda_proveedor,deuda_cliente,firma_oc,stock_insumo,planear_pedido_consumo'
            ))
        ))),
    ],

    /*
    | RAG léxico sobre docs/manual-* (sin embeddings).
    | php artisan ai:indexar-manuales → storage/app/ai/manual_rag_index.json
    */
    'rag_manuales' => [
        'habilitado' => filter_var(env('AI_RAG_MANUALES_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'index_path' => env('AI_RAG_MANUALES_INDEX', 'ai/manual_rag_index.json'),
        'top_k' => max(1, (int) env('AI_RAG_MANUALES_TOP_K', 5)),
    ],

    /*
    | Puente MCP HTTP (tools/list + tools/call). Bearer = AI_MCP_TOKEN.
    */
    'mcp' => [
        'habilitado' => filter_var(env('AI_MCP_HABILITADO', false), FILTER_VALIDATE_BOOLEAN),
        'token' => (string) env('AI_MCP_TOKEN', ''),
    ],
];
