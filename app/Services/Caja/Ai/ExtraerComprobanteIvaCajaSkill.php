<?php

namespace App\Services\Caja\Ai;

use App\Models\Ai\AiDecision;
use App\Services\Ai\AiDecisionLogger;
use App\Services\Ai\AiPolicy;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillInterface;
use App\Services\Ai\Skills\AiSkillResult;
use App\Services\Caja\IngresoEgresoComprobanteIvaPdfIaCoreService;
use App\Support\Caja\IngresoEgresoComprobanteIvaAiHashSupport;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Skill gobernada para leer comprobantes IVA en Ingresos/Egresos de Caja.
 */
final class ExtraerComprobanteIvaCajaSkill implements AiSkillInterface
{
    public const NOMBRE = 'extraer_comprobante_iva_caja';

    public const ENTIDAD = 'comprobante_proveedor';

    public function __construct(
        private IngresoEgresoComprobanteIvaPdfIaCoreService $core,
        private AiPolicy $policy,
        private AiDecisionLogger $logger,
    ) {}

    public function nombre(): string
    {
        return self::NOMBRE;
    }

    public function ejecutar(AiSkillContext $contexto): AiSkillResult
    {
        $pdf = $contexto->entrada('pdf');
        $empresaId = (int) ($contexto->empresaId ?? $contexto->entrada('empresa_id', 0));
        if (! $pdf instanceof UploadedFile || $empresaId <= 0) {
            return AiSkillResult::fallo('La skill requiere PDF y empresa.');
        }

        $inicio = microtime(true);
        try {
            $resultado = $this->core->extraer($pdf, $empresaId);
        } catch (Throwable $e) {
            $this->logger->registrar([
                'skill' => self::NOMBRE,
                'accion' => AiDecision::ACCION_ERROR,
                'driver' => $this->driver(),
                'model' => $this->modelo(),
                'empresa_id' => $empresaId,
                'usuario_id' => $contexto->usuarioId(),
                'entidad_tipo' => self::ENTIDAD,
                'latencia_ms' => $this->latenciaMs($inicio),
                'input_hash' => $this->hashPdf($pdf),
                'payload' => ['error' => $e->getMessage(), 'archivo' => $pdf->getClientOriginalName()],
            ]);

            return AiSkillResult::fallo($e->getMessage());
        }

        $score = $this->score($resultado);
        $advertencias = array_values((array) ($resultado['advertencias'] ?? []));
        $resultado['ai_sugerencia_hash'] = IngresoEgresoComprobanteIvaAiHashSupport::calcular($resultado);

        $decision = $this->logger->registrar([
            'skill' => self::NOMBRE,
            'accion' => AiDecision::ACCION_SUGERIDA,
            'driver' => $this->driver(),
            'model' => $this->modelo(),
            'empresa_id' => $empresaId,
            'usuario_id' => $contexto->usuarioId(),
            'entidad_tipo' => self::ENTIDAD,
            'score' => $score,
            'latencia_ms' => $this->latenciaMs($inicio),
            'input_hash' => $this->hashPdf($pdf),
            'payload' => [
                'archivo' => $pdf->getClientOriginalName(),
                'cabecera' => $resultado['cabecera'] ?? [],
                'conceptos_detectados' => count((array) ($resultado['conceptos'] ?? [])),
                'advertencias' => $advertencias,
            ],
        ]);

        return AiSkillResult::sugerencia(
            $resultado,
            $score,
            $advertencias,
            $this->policy->puedeAutoAplicar(self::NOMBRE, $score),
            $decision?->id,
        );
    }

    /** @param array<string, mixed> $resultado */
    private function score(array $resultado): float
    {
        $cab = is_array($resultado['cabecera'] ?? null) ? $resultado['cabecera'] : [];
        $puntos = 0;
        $total = 6;
        $puntos += (int) ((int) ($cab['proveedor_id'] ?? 0) > 0 || trim((string) ($cab['proveedor_documento_eventual'] ?? '')) !== '');
        $puntos += (int) (trim((string) ($cab['letra'] ?? '')) !== '');
        $puntos += (int) ((int) ($cab['sucursal'] ?? 0) > 0);
        $puntos += (int) ((int) ($cab['numerocomprobante'] ?? 0) > 0);
        $puntos += (int) ((float) ($cab['total'] ?? 0) > 0);
        $puntos += (int) (count((array) ($resultado['conceptos'] ?? [])) > 0);

        return round($puntos / $total, 4);
    }

    private function driver(): string
    {
        return (string) config('comprobante_proveedor_pdf_ia.driver', 'interno');
    }

    private function modelo(): string
    {
        return (string) config('comprobante_proveedor_pdf_ia.ollama.model', '');
    }

    private function latenciaMs(float $inicio): int
    {
        return (int) round((microtime(true) - $inicio) * 1000);
    }

    private function hashPdf(UploadedFile $pdf): ?string
    {
        $ruta = $pdf->getRealPath();
        if (! is_string($ruta) || ! is_readable($ruta)) {
            return null;
        }
        $hash = @hash_file('sha256', $ruta);

        return is_string($hash) ? $hash : null;
    }
}
