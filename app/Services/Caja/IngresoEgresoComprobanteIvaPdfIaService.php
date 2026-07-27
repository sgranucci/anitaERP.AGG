<?php

namespace App\Services\Caja;

use App\Services\Ai\AiPolicy;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillRegistry;
use App\Services\Caja\Ai\ExtraerComprobanteIvaCajaSkill;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Entrada pública de PDF-IVA de Caja: usa la Skill gobernada cuando está activa
 * y mantiene fallback al core para no interrumpir la operación por kill-switch.
 */
final class IngresoEgresoComprobanteIvaPdfIaService
{
    public function __construct(
        private IngresoEgresoComprobanteIvaPdfIaCoreService $core,
        private AiSkillRegistry $skillRegistry,
        private AiPolicy $aiPolicy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(UploadedFile $pdf, int $empresaId): array
    {
        $this->assertHabilitado();

        $skill = ExtraerComprobanteIvaCajaSkill::NOMBRE;
        if (! $this->skillRegistry->tiene($skill) || ! $this->aiPolicy->puedeEjecutar($skill)) {
            return $this->core->extraer($pdf, $empresaId);
        }

        $resultado = $this->skillRegistry->ejecutar($skill, new AiSkillContext(
            entradas: ['pdf' => $pdf, 'empresa_id' => $empresaId],
            empresaId: $empresaId,
            entidadTipo: ExtraerComprobanteIvaCajaSkill::ENTIDAD,
        ));
        if (! $resultado->ok) {
            throw new RuntimeException($resultado->error ?? 'No se pudo extraer el comprobante IVA con IA.');
        }

        $datos = $resultado->datos;
        $datos['ai_decision_id'] = $resultado->decisionId;
        $datos['ai_score'] = $resultado->score;
        $datos['ai_auto_aplicable'] = $resultado->autoAplicable;

        return $datos;
    }

    private function assertHabilitado(): void
    {
        if (! config('comprobante_proveedor_pdf_ia.habilitado', true)) {
            throw new RuntimeException('La lectura PDF por IA no está habilitada.');
        }
    }
}
