<?php

namespace App\Services\Contable\Ai;

use App\Models\Ai\AiDecision;
use App\Services\Ai\AiDecisionLogger;
use App\Services\Ai\AiPolicy;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillInterface;
use App\Services\Ai\Skills\AiSkillResult;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaAnomaliaSupport;
use Throwable;

/**
 * Skill gobernada: analiza pares/pendientes de conciliación bancaria y señala anomalías.
 * No persiste pares (eso lo hace ConciliacionBancariaService); solo sugiere revisión.
 */
final class SugerirParesConciliacionBancariaSkill implements AiSkillInterface
{
    public const NOMBRE = 'sugerir_pares_conciliacion_bancaria';

    public const ENTIDAD = 'conciliacion_bancaria_ejecucion';

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
        $snapshot = $contexto->entrada('snapshot');
        if (! is_array($snapshot)) {
            return AiSkillResult::fallo('La skill requiere un snapshot de conciliación.');
        }

        $inicio = microtime(true);
        try {
            $eval = ConciliacionBancariaAnomaliaSupport::evaluar($snapshot);
        } catch (Throwable $e) {
            $this->logger->registrar([
                'skill' => self::NOMBRE,
                'accion' => AiDecision::ACCION_ERROR,
                'driver' => 'reglas',
                'model' => 'matcher+anomalias',
                'empresa_id' => $contexto->empresaId,
                'usuario_id' => $contexto->usuarioId(),
                'entidad_tipo' => self::ENTIDAD,
                'latencia_ms' => $this->latenciaMs($inicio),
                'payload' => ['error' => $e->getMessage()],
            ]);

            return AiSkillResult::fallo($e->getMessage());
        }

        $datos = [
            'anomalias' => $eval['anomalias'],
            'resumen' => $eval['resumen'],
            'score' => $eval['score'],
            'cuentacaja_id' => $contexto->entrada('cuentacaja_id'),
            'mes' => $contexto->entrada('mes'),
            'anio' => $contexto->entrada('anio'),
        ];

        $hayAnomalias = ($eval['resumen']['anomalias'] ?? 0) > 0;
        $accion = $hayAnomalias ? AiDecision::ACCION_SUGERIDA : AiDecision::ACCION_CONFIRMADA;
        $advertencias = array_values(array_filter(array_map(
            static fn (array $a): string => (string) ($a['mensaje'] ?? ''),
            array_slice($eval['anomalias'], 0, 8),
        )));

        $decision = $this->logger->registrar([
            'skill' => self::NOMBRE,
            'accion' => $accion,
            'driver' => 'reglas',
            'model' => 'matcher+anomalias',
            'empresa_id' => $contexto->empresaId,
            'usuario_id' => $contexto->usuarioId(),
            'entidad_tipo' => self::ENTIDAD,
            'entidad_id' => $contexto->entidadId,
            'score' => $eval['score'],
            'latencia_ms' => $this->latenciaMs($inicio),
            'payload' => [
                'resumen' => $eval['resumen'],
                'anomalias' => array_slice($eval['anomalias'], 0, 30),
                'cuentacaja_id' => $contexto->entrada('cuentacaja_id'),
                'mes' => $contexto->entrada('mes'),
                'anio' => $contexto->entrada('anio'),
            ],
        ]);

        return AiSkillResult::sugerencia(
            $datos,
            $eval['score'],
            $advertencias,
            $this->policy->puedeAutoAplicar(self::NOMBRE, $eval['score']),
            $decision?->id,
        );
    }

    private function latenciaMs(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }
}
