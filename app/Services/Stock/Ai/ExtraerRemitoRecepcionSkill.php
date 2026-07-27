<?php

namespace App\Services\Stock\Ai;

use App\Models\Ai\AiDecision;
use App\Services\Ai\AiDecisionLogger;
use App\Services\Ai\AiPolicy;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillInterface;
use App\Services\Ai\Skills\AiSkillResult;
use App\Services\Stock\RecepcionProveedorOcrCoreService;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrAiHashSupport;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrMatchScoreSupport;
use Throwable;

/**
 * Skill gobernada: leer remito/factura y emparejar cantidades con la OC de recepción.
 */
final class ExtraerRemitoRecepcionSkill implements AiSkillInterface
{
    public const NOMBRE = 'emparejar_remito_recepcion';

    public const ENTIDAD = 'recepcion_proveedor';

    public function __construct(
        private RecepcionProveedorOcrCoreService $core,
        private AiPolicy $policy,
        private AiDecisionLogger $logger,
    ) {}

    public function nombre(): string
    {
        return self::NOMBRE;
    }

    public function ejecutar(AiSkillContext $contexto): AiSkillResult
    {
        $ruta = (string) $contexto->entrada('ruta_absoluta', '');
        $mime = (string) $contexto->entrada('mime', 'application/pdf');
        if ($ruta === '' || ! is_readable($ruta)) {
            return AiSkillResult::fallo('La skill requiere un archivo legible (ruta_absoluta).');
        }

        $ordencompraId = (int) $contexto->entrada('ordencompra_id', 0) ?: null;
        $numeroOcForm = (int) $contexto->entrada('numero_oc', 0) ?: null;
        $recepcion = $contexto->entrada('recepcion');
        $inicio = microtime(true);

        try {
            $resultado = $this->core->analizar(
                $ruta,
                $mime,
                $ordencompraId,
                $numeroOcForm,
                $recepcion instanceof \App\Models\Stock\Recepcion_Proveedor ? $recepcion : null,
            );
        } catch (Throwable $e) {
            $this->logger->registrar([
                'skill' => self::NOMBRE,
                'accion' => AiDecision::ACCION_ERROR,
                'driver' => 'tesseract',
                'model' => 'ocr+matcher',
                'empresa_id' => $contexto->empresaId,
                'usuario_id' => $contexto->usuarioId(),
                'entidad_tipo' => self::ENTIDAD,
                'entidad_id' => $contexto->entidadId,
                'latencia_ms' => $this->latenciaMs($inicio),
                'input_hash' => @hash_file('sha256', $ruta) ?: null,
                'payload' => [
                    'error' => $e->getMessage(),
                    'archivo' => $contexto->entrada('archivo_nombre'),
                ],
            ]);

            return AiSkillResult::fallo($e->getMessage());
        }

        $eval = RecepcionProveedorOcrMatchScoreSupport::evaluar($resultado);
        $resultado['ai_sugerencia_hash'] = RecepcionProveedorOcrAiHashSupport::calcular($resultado);
        $resultado['advertencias'] = $eval['advertencias'];

        $decision = $this->logger->registrar([
            'skill' => self::NOMBRE,
            'accion' => AiDecision::ACCION_SUGERIDA,
            'driver' => 'tesseract',
            'model' => 'ocr+matcher',
            'empresa_id' => (int) ($resultado['empresa_id'] ?? $contexto->empresaId),
            'usuario_id' => $contexto->usuarioId(),
            'entidad_tipo' => self::ENTIDAD,
            'entidad_id' => $contexto->entidadId,
            'score' => $eval['score'],
            'latencia_ms' => $this->latenciaMs($inicio),
            'input_hash' => @hash_file('sha256', $ruta) ?: null,
            'payload' => [
                'archivo' => $contexto->entrada('archivo_nombre'),
                'ordencompra_id' => $resultado['ordencompra_id'] ?? null,
                'numeroordencompra' => $resultado['numeroordencompra'] ?? null,
                'ocr_lineas_detectadas' => $resultado['ocr_lineas_detectadas'] ?? 0,
                'resumen_match' => $resultado['_resumen_arr'] ?? null,
                'advertencias' => $eval['advertencias'],
            ],
        ]);

        return AiSkillResult::sugerencia(
            $resultado,
            $eval['score'],
            $eval['advertencias'],
            $this->policy->puedeAutoAplicar(self::NOMBRE, $eval['score']),
            $decision?->id,
        );
    }

    private function latenciaMs(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }
}
