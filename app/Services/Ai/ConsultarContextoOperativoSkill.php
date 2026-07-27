<?php

namespace App\Services\Ai;

use App\Models\Ai\AiDecision;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillInterface;
use App\Services\Ai\Skills\AiSkillResult;
use App\Support\Ai\AiConsultaOperativaSupport;
use Throwable;

/**
 * Fase C: consultas operativas acotadas (chips + grounding en maestros).
 * Solo lectura; nunca escribe negocio.
 */
final class ConsultarContextoOperativoSkill implements AiSkillInterface
{
    public const NOMBRE = 'consultar_contexto_operativo';

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
        $intent = strtolower(trim((string) $contexto->entrada('intent', '')));
        $pregunta = $contexto->entrada('pregunta');
        $pregunta = is_string($pregunta) && trim($pregunta) !== '' ? trim($pregunta) : null;
        $fuenteRouter = $contexto->entrada('fuente_router');
        $fuenteRouter = is_string($fuenteRouter) ? $fuenteRouter : null;
        $params = $contexto->entrada('params');
        if (! is_array($params)) {
            $params = [];
        }

        $inicio = microtime(true);
        try {
            $resultado = AiConsultaOperativaSupport::consultar($intent, $params);
        } catch (Throwable $e) {
            $this->logger->registrar([
                'skill' => self::NOMBRE,
                'accion' => AiDecision::ACCION_ERROR,
                'driver' => 'reglas',
                'model' => 'consulta-operativa',
                'empresa_id' => $contexto->empresaId,
                'usuario_id' => $contexto->usuarioId(),
                'entidad_tipo' => 'consulta_operativa',
                'entidad_id' => null,
                'latencia_ms' => $this->latenciaMs($inicio),
                'payload' => [
                    'intent' => $intent,
                    'error' => $e->getMessage(),
                ],
            ]);

            return AiSkillResult::fallo($e->getMessage());
        }

        if (! ($resultado['ok'] ?? false)) {
            $this->logger->registrar([
                'skill' => self::NOMBRE,
                'accion' => AiDecision::ACCION_ERROR,
                'driver' => 'reglas',
                'model' => 'consulta-operativa',
                'empresa_id' => $contexto->empresaId,
                'usuario_id' => $contexto->usuarioId(),
                'entidad_tipo' => 'consulta_operativa',
                'entidad_id' => null,
                'score' => 0,
                'latencia_ms' => $this->latenciaMs($inicio),
                'payload' => [
                    'intent' => $intent,
                    'params' => $this->paramsSeguros($params),
                    'error' => $resultado['error'] ?? 'Consulta sin resultado',
                ],
            ]);

            return AiSkillResult::fallo($resultado['error'] ?? 'Consulta sin resultado');
        }

        $datos = [
            'intent' => $resultado['intent'],
            'parrafos' => $resultado['parrafos'],
            'links' => $resultado['links'],
            'datos' => $resultado['datos'],
            'tabla' => $resultado['tabla'] ?? null,
        ];
        $score = (float) ($resultado['score'] ?? 0.85);
        $entidadId = $this->entidadIdDesdeDatos($resultado['datos'] ?? []);

        $decision = $this->logger->registrar([
            'skill' => self::NOMBRE,
            'accion' => AiDecision::ACCION_SUGERIDA,
            'driver' => 'reglas',
            'model' => 'consulta-operativa',
            'empresa_id' => $contexto->empresaId ?? ($params['empresa_id'] ?? null),
            'usuario_id' => $contexto->usuarioId(),
            'entidad_tipo' => 'consulta_operativa',
            'entidad_id' => $entidadId,
            'score' => $score,
            'latencia_ms' => $this->latenciaMs($inicio),
            'payload' => [
                'intent' => $resultado['intent'],
                'pregunta' => $pregunta,
                'fuente_router' => $fuenteRouter,
                'params' => $this->paramsSeguros($params),
                'parrafos' => array_slice($resultado['parrafos'], 0, 12),
                'links' => $resultado['links'],
                'datos' => $resultado['datos'],
            ],
        ]);

        return AiSkillResult::sugerencia(
            $datos,
            $score,
            array_slice($resultado['parrafos'], 0, 8),
            $this->policy->puedeAutoAplicar(self::NOMBRE, $score),
            $decision?->id,
        );
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private function paramsSeguros(array $params): array
    {
        $out = [];
        foreach ($params as $k => $v) {
            if (! is_scalar($v) && $v !== null) {
                continue;
            }
            $out[(string) $k] = $v;
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $datos
     */
    private function entidadIdDesdeDatos(array $datos): ?int
    {
        foreach ([
            'articulo_id', 'cliente_id', 'proveedor_id', 'ordencompra_id',
            'asiento_id', 'comprobante_proveedor_id', 'venta_id', 'cuentacontable_id',
        ] as $clave) {
            if (! empty($datos[$clave]) && (int) $datos[$clave] > 0) {
                return (int) $datos[$clave];
            }
        }

        return null;
    }

    private function latenciaMs(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }
}
