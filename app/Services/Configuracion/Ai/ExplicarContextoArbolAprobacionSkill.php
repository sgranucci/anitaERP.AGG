<?php

namespace App\Services\Configuracion\Ai;

use App\Models\Ai\AiDecision;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Services\Ai\AiDecisionLogger;
use App\Services\Ai\AiPolicy;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillInterface;
use App\Services\Ai\Skills\AiSkillResult;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Support\Configuracion\ArbolAprobacionContextoSupport;
use Throwable;

/**
 * Skill de solo lectura: explica un paso de árbol (RE/OC/SP/OV/RS/PE) y el impacto de aprobar.
 */
final class ExplicarContextoArbolAprobacionSkill implements AiSkillInterface
{
    public const NOMBRE = 'explicar_contexto_arbol_aprobacion';

    /** @deprecated usar NOMBRE; se mantiene para filas/config legacy OC */
    public const NOMBRE_LEGACY_OC = 'explicar_contexto_arbol_aprobacion_oc';

    public function __construct(
        private AiPolicy $policy,
        private AiDecisionLogger $logger,
        private ArbolaprobacionService $arbolService,
        private UsuarioRepositoryInterface $usuarioRepository,
    ) {}

    public function nombre(): string
    {
        return self::NOMBRE;
    }

    public function ejecutar(AiSkillContext $contexto): AiSkillResult
    {
        $snapshot = $contexto->entrada('snapshot');
        if (! is_array($snapshot) || empty($snapshot['tipocomprobante'])) {
            return AiSkillResult::fallo('La skill requiere un snapshot de comprobante del árbol.');
        }

        $movimiento = $contexto->entrada('movimiento');
        $estadoTras = $contexto->entrada('estado_tras_aprobar');
        $estadoTras = is_string($estadoTras) ? $estadoTras : null;
        $tipo = strtoupper((string) $snapshot['tipocomprobante']);
        $entidadTipo = ArbolAprobacionContextoSupport::entidadTipoAi($tipo);
        $documentoId = (int) ($snapshot['documento_id'] ?? $contexto->entidadId ?? 0);

        $inicio = microtime(true);
        try {
            $datos = ArbolAprobacionContextoSupport::construir(
                $this->arbolService,
                $snapshot,
                is_object($movimiento) ? $movimiento : null,
                $this->usuarioRepository,
                $estadoTras,
            );
        } catch (Throwable $e) {
            $this->logger->registrar([
                'skill' => self::NOMBRE,
                'accion' => AiDecision::ACCION_ERROR,
                'driver' => 'reglas',
                'model' => 'arbol-contexto',
                'empresa_id' => $contexto->empresaId,
                'usuario_id' => $contexto->usuarioId(),
                'entidad_tipo' => $entidadTipo,
                'entidad_id' => $documentoId > 0 ? $documentoId : null,
                'latencia_ms' => $this->latenciaMs($inicio),
                'payload' => ['error' => $e->getMessage(), 'tipocomprobante' => $tipo],
            ]);

            return AiSkillResult::fallo($e->getMessage());
        }

        $decision = $this->logger->registrar([
            'skill' => self::NOMBRE,
            'accion' => AiDecision::ACCION_SUGERIDA,
            'driver' => 'reglas',
            'model' => 'arbol-contexto',
            'empresa_id' => $contexto->empresaId ?? ($datos['empresa_id'] ?? null),
            'usuario_id' => $contexto->usuarioId(),
            'entidad_tipo' => $entidadTipo,
            'entidad_id' => $documentoId > 0 ? $documentoId : null,
            'score' => $datos['score'],
            'latencia_ms' => $this->latenciaMs($inicio),
            'payload' => [
                'tipocomprobante' => $tipo,
                'resumen' => $datos['resumen'],
                'parrafos' => $datos['parrafos'],
                'trigger' => $datos['trigger'],
                'capex_excesos' => array_slice($datos['capex_excesos'], 0, 10),
                'si_aprobas' => $datos['si_aprobas'],
            ],
        ]);

        return AiSkillResult::sugerencia(
            $datos,
            $datos['score'],
            array_slice($datos['parrafos'], 0, 8),
            $this->policy->puedeAutoAplicar(self::NOMBRE, $datos['score']),
            $decision?->id,
        );
    }

    private function latenciaMs(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }
}
