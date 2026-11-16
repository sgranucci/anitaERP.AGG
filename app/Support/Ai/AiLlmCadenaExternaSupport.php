<?php

namespace App\Support\Ai;

use App\Services\Ai\AiPrompt;
use App\Services\Ai\AiResult;
use App\Support\ConsultaInteligente\ConsultaInteligenteNlLlmSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cadena OpenAI-compatible (Groq → Gemini → primary) reutilizando CI_LLM_*.
 * Usada como última alternativa del copiloto cuando Ollama falla.
 */
final class AiLlmCadenaExternaSupport
{
    public static function habilitadoParaCopiloto(): bool
    {
        if (! filter_var(
            config('ai.skills.consultar_contexto_operativo.llm_failover_externo', false),
            FILTER_VALIDATE_BOOLEAN
        )) {
            return false;
        }

        return self::proveedoresActivos() !== [];
    }

    /**
     * Nombres de la cadena con clave (para UI / logs).
     *
     * @return list<string>
     */
    public static function cadenaActivaNombres(): array
    {
        $out = [];
        foreach (self::proveedoresActivos() as $p) {
            $out[] = (string) ($p['nombre'] ?? $p['id']);
        }

        return $out;
    }

    /**
     * @return list<array{id: string, nombre: string, api_key: string, base_url: string, model: string, timeout: int}>
     */
    public static function proveedoresActivos(): array
    {
        $catalogo = config('consulta_inteligente.llm_providers', []);
        if (! is_array($catalogo)) {
            return [];
        }
        $cadena = config('consulta_inteligente.llm_chain', ['groq', 'gemini', 'primary']);
        if (! is_array($cadena)) {
            $cadena = ['groq', 'gemini', 'primary'];
        }

        $out = [];
        foreach ($cadena as $id) {
            $id = strtolower(trim((string) $id));
            if ($id === '' || ! isset($catalogo[$id]) || ! is_array($catalogo[$id])) {
                continue;
            }
            $apiKey = trim((string) ($catalogo[$id]['api_key'] ?? ''));
            $base = rtrim(trim((string) ($catalogo[$id]['base_url'] ?? '')), '/');
            $model = trim((string) ($catalogo[$id]['model'] ?? ''));
            if ($apiKey === '' || $base === '' || $model === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nombre' => (string) ($catalogo[$id]['nombre'] ?? $id),
                'api_key' => $apiKey,
                'base_url' => $base,
                'model' => $model,
                'timeout' => max(
                    3,
                    (int) ($catalogo[$id]['timeout']
                        ?? config('ai.skills.consultar_contexto_operativo.llm_failover_timeout', config('consulta_inteligente.llm_timeout', 8)))
                ),
            ];
        }

        return $out;
    }

    /**
     * Ejecuta el prompt contra la cadena externa (primer proveedor que responda OK).
     */
    public static function generar(AiPrompt $prompt): AiResult
    {
        $proveedores = self::proveedoresActivos();
        if ($proveedores === []) {
            return AiResult::fallo('Sin proveedores externos CI_LLM_* con clave');
        }

        $system = (string) ($prompt->system ?? '');
        $user = (string) $prompt->prompt;
        if ((bool) config('consulta_inteligente.llm_sanitizar_prompt', true)) {
            $user = ConsultaInteligenteNlLlmSupport::sanitizarParaEnvio($user);
            if ($system !== '') {
                // No sanitizar system (instrucciones); solo el texto del usuario.
            }
        }

        $ultimoError = 'sin respuesta';
        $latenciaTotal = 0;

        foreach ($proveedores as $prov) {
            $inicio = microtime(true);
            $resultado = self::llamarProveedor($prov, $system, $user, $prompt);
            $latencia = (int) round((microtime(true) - $inicio) * 1000);
            $latenciaTotal += $latencia;

            if ($resultado['ok'] ?? false) {
                return AiResult::exito(
                    (string) ($resultado['texto'] ?? ''),
                    is_array($resultado['json'] ?? null) ? $resultado['json'] : null,
                    'externo:'.$prov['id'],
                    $prov['model'],
                    $latenciaTotal,
                    [
                        'proveedor' => $prov['nombre'],
                        'proveedor_id' => $prov['id'],
                        'failover' => true,
                        'meta_prompt' => $prompt->meta,
                    ]
                );
            }

            $ultimoError = (string) ($resultado['error'] ?? $prov['nombre']);
        }

        Log::channel((string) config('ai.log_channel', 'ai'))->warning('ai.failover_externo_agotado', [
            'ultimo_error' => $ultimoError,
            'meta' => $prompt->meta,
        ]);

        return AiResult::fallo(
            'Cadena externa agotada: '.$ultimoError,
            'externo',
            '',
            $latenciaTotal
        );
    }

    /**
     * @param  array{id: string, nombre: string, api_key: string, base_url: string, model: string, timeout: int}  $prov
     * @return array{ok: bool, texto?: string, json?: array<string,mixed>|null, error?: string}
     */
    private static function llamarProveedor(array $prov, string $system, string $user, AiPrompt $prompt): array
    {
        $timeout = $prompt->timeout !== null
            ? max(3, (int) $prompt->timeout)
            : $prov['timeout'];

        $mensajes = [];
        if ($system !== '') {
            $mensajes[] = ['role' => 'system', 'content' => $system];
        }
        $mensajes[] = ['role' => 'user', 'content' => $user];

        $payload = [
            'model' => $prov['model'],
            'messages' => $mensajes,
            'temperature' => $prompt->temperature ?? 0.05,
        ];
        if ($prompt->maxTokens !== null) {
            $payload['max_tokens'] = $prompt->maxTokens;
        }
        if ($prompt->esperaJson) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $url = $prov['base_url'].'/chat/completions';
            $res = AiLlmHttpProxySupport::cliente($prov['api_key'], $timeout)
                ->post($url, $payload);

            if (! $res->successful()) {
                Log::channel((string) config('ai.log_channel', 'ai'))->warning('ai.failover_externo_http', [
                    'proveedor' => $prov['nombre'],
                    'status' => $res->status(),
                    'body' => mb_substr($res->body(), 0, 300),
                    'proxy' => AiLlmHttpProxySupport::hostPuertoResumen(),
                    'meta' => $prompt->meta,
                ]);

                return [
                    'ok' => false,
                    'error' => $prov['nombre'].' HTTP '.$res->status(),
                ];
            }

            $content = (string) data_get($res->json(), 'choices.0.message.content', '');
            if (trim($content) === '') {
                return ['ok' => false, 'error' => $prov['nombre'].' sin contenido'];
            }

            $json = null;
            if ($prompt->esperaJson) {
                $decoded = json_decode($content, true);
                if (! is_array($decoded)) {
                    // Intentar extraer bloque JSON embebido
                    if (preg_match('/\{[\s\S]*\}/u', $content, $m) === 1) {
                        $decoded = json_decode($m[0], true);
                    }
                }
                if (! is_array($decoded)) {
                    return ['ok' => false, 'error' => $prov['nombre'].' JSON inválido'];
                }
                $json = $decoded;
            }

            return [
                'ok' => true,
                'texto' => $content,
                'json' => $json,
            ];
        } catch (Throwable $e) {
            Log::channel((string) config('ai.log_channel', 'ai'))->warning('ai.failover_externo_error', [
                'proveedor' => $prov['nombre'],
                'message' => $e->getMessage(),
                'meta' => $prompt->meta,
            ]);

            return [
                'ok' => false,
                'error' => $prov['nombre'].': '.$e->getMessage(),
            ];
        }
    }
}
