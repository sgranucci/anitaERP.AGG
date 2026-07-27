<?php

namespace App\Services\Ventas\Ai;

use App\Models\Ai\AiDecision;
use App\Services\Ai\AiDecisionLogger;
use App\Services\Ai\AiPolicy;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillInterface;
use App\Services\Ai\Skills\AiSkillResult;
use App\Support\Ventas\GastronomiaConciliacionTurnoExplicacionSupport;
use Throwable;

/**
 * Skill de solo lectura: explica filas DIF de conciliación de turno gastronomía.
 */
final class ExplicarDiferenciasConciliacionTurnoGastronomiaSkill implements AiSkillInterface
{
    public const NOMBRE = 'explicar_diferencias_conciliacion_turno_gastronomia';

    public const ENTIDAD = 'turno_operativo_gastronomia';

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
        $filas = $contexto->entrada('filas_dif');
        if (! is_array($filas)) {
            return AiSkillResult::fallo('La skill requiere filas DIF de conciliación.');
        }
        $totales = $contexto->entrada('totales');
        $totales = is_array($totales) ? $totales : [];

        $inicio = microtime(true);
        try {
            $eval = GastronomiaConciliacionTurnoExplicacionSupport::evaluar($filas, $totales);
        } catch (Throwable $e) {
            $this->logger->registrar([
                'skill' => self::NOMBRE,
                'accion' => AiDecision::ACCION_ERROR,
                'driver' => 'reglas',
                'model' => 'gastro-dif',
                'empresa_id' => $contexto->empresaId,
                'usuario_id' => $contexto->usuarioId(),
                'entidad_tipo' => self::ENTIDAD,
                'entidad_id' => $contexto->entidadId,
                'latencia_ms' => $this->latenciaMs($inicio),
                'payload' => ['error' => $e->getMessage()],
            ]);

            return AiSkillResult::fallo($e->getMessage());
        }

        $datos = [
            'parrafos' => $eval['parrafos'],
            'hipotesis' => $eval['hipotesis'],
            'resumen' => $eval['resumen'],
            'score' => $eval['score'],
            'identificador_pc' => $contexto->entrada('identificador_pc'),
            'fecha_jornada' => $contexto->entrada('fecha_jornada'),
        ];

        $decision = $this->logger->registrar([
            'skill' => self::NOMBRE,
            'accion' => AiDecision::ACCION_SUGERIDA,
            'driver' => 'reglas',
            'model' => 'gastro-dif',
            'empresa_id' => $contexto->empresaId,
            'usuario_id' => $contexto->usuarioId(),
            'entidad_tipo' => self::ENTIDAD,
            'entidad_id' => $contexto->entidadId,
            'score' => $eval['score'],
            'latencia_ms' => $this->latenciaMs($inicio),
            'payload' => [
                'resumen' => $eval['resumen'],
                'parrafos' => array_slice($eval['parrafos'], 0, 15),
                'hipotesis' => array_slice($eval['hipotesis'], 0, 25),
                'identificador_pc' => $contexto->entrada('identificador_pc'),
                'fecha_jornada' => $contexto->entrada('fecha_jornada'),
            ],
        ]);

        return AiSkillResult::sugerencia(
            $datos,
            $eval['score'],
            array_slice($eval['parrafos'], 0, 8),
            $this->policy->puedeAutoAplicar(self::NOMBRE, $eval['score']),
            $decision?->id,
        );
    }

    private function latenciaMs(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }
}
