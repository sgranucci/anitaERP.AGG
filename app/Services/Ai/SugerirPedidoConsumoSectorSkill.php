<?php

namespace App\Services\Ai;

use App\Models\Ai\AiDecision;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillInterface;
use App\Services\Ai\Skills\AiSkillResult;
use App\Support\Ai\PedidoConsumoSectorProyeccionSupport;
use Throwable;

/**
 * Skill de ejemplo: proyecta pedido por consumo (CC + depósito) y propone RQ compra o sala.
 * Solo sugiere; la confirmación humana crea el documento.
 */
final class SugerirPedidoConsumoSectorSkill implements AiSkillInterface
{
    public const NOMBRE = 'sugerir_pedido_consumo_sector';

    public function __construct(
        private AiPolicy $policy,
        private AiDecisionLogger $logger,
    ) {}

    public function nombre(): string
    {
        return self::NOMBRE;
    }

    public function ejecutar(AiSkillContext $contexto): AiSkillResult
    {
        $params = $contexto->entrada('params');
        if (! is_array($params)) {
            $params = [];
        }
        foreach (['centrocosto_id', 'centrocosto_codigo', 'deposito_consumo_id', 'deposito_id',
            'deposito_codigo', 'deposito_origen_id', 'fecha_desde', 'fecha_hasta',
            'dias_cobertura', 'solo_insumo', 'empresa_id', 'multiplicador_evento', 'solo_sabados',
            'lead_time_dias', 'buffer_dias', 'max_lineas', 'valor', 'codigo',
        ] as $k) {
            if ($contexto->entrada($k) !== null && ! array_key_exists($k, $params)) {
                $params[$k] = $contexto->entrada($k);
            }
        }
        if ($contexto->empresaId && empty($params['empresa_id'])) {
            $params['empresa_id'] = $contexto->empresaId;
        }

        $inicio = microtime(true);
        try {
            $resultado = PedidoConsumoSectorProyeccionSupport::proyectar($params);
        } catch (Throwable $e) {
            $this->logger->registrar([
                'skill' => self::NOMBRE,
                'accion' => AiDecision::ACCION_ERROR,
                'driver' => 'reglas',
                'model' => 'pedido-consumo-sector',
                'empresa_id' => $contexto->empresaId,
                'usuario_id' => $contexto->usuarioId(),
                'entidad_tipo' => 'pedido_consumo_sector',
                'entidad_id' => null,
                'latencia_ms' => (int) round((microtime(true) - $inicio) * 1000),
                'payload' => ['error' => $e->getMessage()],
            ]);

            return AiSkillResult::fallo($e->getMessage());
        }

        if (! ($resultado['ok'] ?? false)) {
            $this->logger->registrar([
                'skill' => self::NOMBRE,
                'accion' => AiDecision::ACCION_ERROR,
                'driver' => 'reglas',
                'model' => 'pedido-consumo-sector',
                'empresa_id' => $contexto->empresaId,
                'usuario_id' => $contexto->usuarioId(),
                'entidad_tipo' => 'pedido_consumo_sector',
                'entidad_id' => null,
                'score' => 0,
                'latencia_ms' => (int) round((microtime(true) - $inicio) * 1000),
                'payload' => ['error' => $resultado['error'] ?? 'sin resultado', 'params' => $params],
            ]);

            return AiSkillResult::fallo($resultado['error'] ?? 'No se pudo proyectar el pedido.');
        }

        $score = (float) ($resultado['score'] ?? 0.85);
        $datos = [
            'intent' => 'pedido_consumo_sector',
            'parrafos' => $resultado['parrafos'] ?? [],
            'links' => $resultado['links'] ?? [],
            'tabla' => $resultado['tabla'] ?? null,
            'datos' => $resultado['datos'] ?? [],
        ];

        $decision = $this->logger->registrar([
            'skill' => self::NOMBRE,
            'accion' => AiDecision::ACCION_SUGERIDA,
            'driver' => 'reglas',
            'model' => 'pedido-consumo-sector',
            'empresa_id' => $contexto->empresaId ?? ($params['empresa_id'] ?? null),
            'usuario_id' => $contexto->usuarioId(),
            'entidad_tipo' => 'pedido_consumo_sector',
            'entidad_id' => $resultado['datos']['centrocosto_id'] ?? null,
            'score' => $score,
            'latencia_ms' => (int) round((microtime(true) - $inicio) * 1000),
            'payload' => [
                'params' => array_filter($params, static fn ($v) => is_scalar($v) || $v === null),
                'parrafos' => array_slice($resultado['parrafos'] ?? [], 0, 8),
                'lineas' => count($resultado['datos']['lineas'] ?? []),
                'tiene_borrador_compra' => ! empty($resultado['datos']['borrador_compra']),
                'tiene_borrador_sala' => ! empty($resultado['datos']['borrador_sala']),
                '_meta' => $resultado['datos']['_meta'] ?? [],
            ],
        ]);

        return AiSkillResult::sugerencia(
            $datos,
            $score,
            array_slice($resultado['parrafos'] ?? [], 0, 8),
            $this->policy->puedeAutoAplicar(self::NOMBRE, $score),
            $decision?->id,
        );
    }
}
