<?php

namespace App\Support\Ai;

use App\Models\Ai\AiDecision;
use App\Services\Ai\AiDecisionLogger;
use App\Services\Ai\AiPolicy;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillRegistry;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Puente HTTP estilo MCP: tools/list + tools/call sobre AiSkillRegistry + AiPolicy.
 * No es el protocolo stdio completo; sirve para clientes externos con Bearer token.
 */
final class AiMcpBridgeSupport
{
    public function __construct(
        private AiSkillRegistry $registry,
        private AiPolicy $policy,
        private AiDecisionLogger $logger,
    ) {}

    public function habilitado(): bool
    {
        if (filter_var(config('ai.kill_switch', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return filter_var(config('ai.mcp.habilitado', false), FILTER_VALIDATE_BOOLEAN)
            && trim((string) config('ai.mcp.token', '')) !== '';
    }

    public function tokenValido(?string $bearer): bool
    {
        $esperado = trim((string) config('ai.mcp.token', ''));
        if ($esperado === '' || $bearer === null || $bearer === '') {
            return false;
        }

        return hash_equals($esperado, $bearer);
    }

    /**
     * @return array{tools: list<array{name: string, description: string, inputSchema: array<string,mixed>}>}
     */
    public function listarTools(): array
    {
        $tools = [];
        foreach ($this->registry->nombres() as $nombre) {
            $cfg = (array) config("ai.skills.{$nombre}", []);
            $habilitada = filter_var($cfg['habilitada'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $tools[] = [
                'name' => $nombre,
                'description' => $habilitada
                    ? 'Skill anitaERP (gobernada). Propone; no escribe sola salvo auto-aplicar.'
                    : 'Skill registrada pero deshabilitada en config.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'payload' => [
                            'type' => 'object',
                            'description' => 'Contexto tipado de la skill (PDF path, ids, texto, etc.).',
                        ],
                    ],
                    'additionalProperties' => true,
                ],
                'habilitada' => $habilitada,
                'permiso' => $cfg['permiso'] ?? null,
                'auto_aplicar_score' => (float) ($cfg['auto_aplicar_score'] ?? 0),
            ];
        }

        // Tool de consulta operativa / RAG (no es skill tipada PDF)
        $tools[] = [
            'name' => 'consultar_contexto_operativo_nl',
            'description' => 'Consulta operativa NL (maestros/CT/mayor/manual RAG) vía router anitaERP.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'pregunta' => ['type' => 'string'],
                    'intent' => ['type' => 'string'],
                    'params' => ['type' => 'object'],
                ],
                'required' => ['pregunta'],
            ],
            'habilitada' => (bool) config('ai.skills.consultar_contexto_operativo.habilitada', true),
            'permiso' => 'ejecutar-consulta-ia',
            'auto_aplicar_score' => 0,
        ];

        return ['tools' => $tools];
    }

    /**
     * @param  array<string,mixed>  $arguments
     * @return array{ok: bool, content?: mixed, error?: string, decision_id?: int|null}
     */
    public function llamarTool(string $name, array $arguments = []): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'name requerido'];
        }

        if ($name === 'consultar_contexto_operativo_nl') {
            return $this->llamarConsultaNl($arguments);
        }

        if (! $this->registry->tiene($name)) {
            return ['ok' => false, 'error' => 'Tool/skill desconocida: '.$name];
        }

        if (! $this->policy->skillHabilitada($name)) {
            return ['ok' => false, 'error' => 'Skill deshabilitada: '.$name];
        }

        // MCP externo no tiene sesión de rol: solo flags de skill + kill-switch.
        // Las skills de escritura siguen devolviendo sugerencia (HITL).
        try {
            $payload = is_array($arguments['payload'] ?? null) ? $arguments['payload'] : $arguments;
            $usuario = Auth::user();
            $usuarioModel = $usuario instanceof \App\Models\Seguridad\Usuario ? $usuario : null;
            $ctx = new AiSkillContext(
                entradas: $payload,
                empresaId: isset($payload['empresa_id']) ? (int) $payload['empresa_id'] : null,
                usuario: $usuarioModel,
            );
            $resultado = $this->registry->get($name)->ejecutar($ctx);

            $decision = $this->logger->registrar([
                'skill' => $name,
                'accion' => $resultado->ok
                    ? AiDecision::ACCION_SUGERIDA
                    : AiDecision::ACCION_ERROR,
                'score' => $resultado->score,
                'payload' => [
                    'origen' => 'mcp',
                    'auto_aplicable' => $resultado->autoAplicable,
                    'error' => $resultado->error,
                    'advertencias' => $resultado->advertencias,
                ],
            ]);

            return [
                'ok' => $resultado->ok,
                'content' => [
                    'score' => $resultado->score,
                    'auto_aplicable' => $resultado->autoAplicable,
                    'advertencias' => $resultado->advertencias,
                    'datos' => $resultado->datos,
                    'decision_id_skill' => $resultado->decisionId,
                ],
                'error' => $resultado->ok ? null : ($resultado->error ?? 'Error skill'),
                'decision_id' => $decision?->id ?? $resultado->decisionId,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string,mixed>  $arguments
     * @return array{ok: bool, content?: mixed, error?: string, decision_id?: int|null}
     */
    private function llamarConsultaNl(array $arguments): array
    {
        if (! filter_var(config('ai.skills.consultar_contexto_operativo.habilitada', true), FILTER_VALIDATE_BOOLEAN)) {
            return ['ok' => false, 'error' => 'consultar_contexto_operativo deshabilitada'];
        }

        $pregunta = trim((string) ($arguments['pregunta'] ?? ''));
        $intent = trim((string) ($arguments['intent'] ?? ''));
        $params = is_array($arguments['params'] ?? null) ? $arguments['params'] : [];

        try {
            // Token MCP actúa como autorización de canal; no hay rol de sesión.
            if ($intent !== '') {
                $resultado = AiConsultaOperativaSupport::consultar(
                    $intent,
                    $params + ['valor' => $pregunta, '_mcp_bridge' => true]
                );
            } else {
                if ($pregunta === '') {
                    return ['ok' => false, 'error' => 'pregunta requerida'];
                }
                $plan = AiConsultaOperativaRouterSupport::interpretar($pregunta);
                if (! ($plan['ok'] ?? false)) {
                    return [
                        'ok' => false,
                        'error' => $plan['error'] ?? $plan['clarification'] ?? 'No se pudo interpretar',
                        'content' => $plan,
                    ];
                }
                $paramsPlan = is_array($plan['params'] ?? null) ? $plan['params'] : [];
                $paramsPlan['_mcp_bridge'] = true;
                $resultado = AiConsultaOperativaSupport::consultar(
                    (string) $plan['intent'],
                    $paramsPlan
                );
            }

            $decision = $this->logger->registrar([
                'skill' => 'consultar_contexto_operativo',
                'accion' => ($resultado['ok'] ?? false) ? AiDecision::ACCION_SUGERIDA : AiDecision::ACCION_ERROR,
                'score' => $resultado['score'] ?? null,
                'payload' => [
                    'origen' => 'mcp',
                    'intent' => $resultado['intent'] ?? $intent,
                    'pregunta' => $pregunta,
                ],
            ]);

            return [
                'ok' => (bool) ($resultado['ok'] ?? false),
                'content' => $resultado,
                'error' => ($resultado['ok'] ?? false) ? null : ($resultado['error'] ?? 'Consulta fallida'),
                'decision_id' => $decision?->id,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
