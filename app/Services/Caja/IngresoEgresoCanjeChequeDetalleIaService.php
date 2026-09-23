<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Services\Ai\AiPolicy;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillRegistry;
use App\Services\Caja\Ai\RedactarDetalleCanjeChequeSkill;
use App\Support\Caja\IngresoEgresoCanjeChequeSupport;
use Throwable;

/**
 * Arma el detalle del movimiento de canje: IA si está habilitada, si no texto determinístico.
 */
final class IngresoEgresoCanjeChequeDetalleIaService
{
    public function __construct(
        private readonly AiSkillRegistry $skillRegistry,
        private readonly AiPolicy $aiPolicy,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $cheques
     * @return array{ok:bool, detalle:string, fuente:string, error:?string}
     */
    public function sugerir(array $cheques): array
    {
        $fallback = IngresoEgresoCanjeChequeSupport::detalleDeterministico($cheques);
        $skill = RedactarDetalleCanjeChequeSkill::NOMBRE;

        if (! $this->skillRegistry->tiene($skill) || ! $this->aiPolicy->puedeEjecutar($skill)) {
            return [
                'ok' => true,
                'detalle' => $fallback,
                'fuente' => 'deterministico',
                'error' => null,
            ];
        }

        try {
            $resultado = $this->skillRegistry->ejecutar($skill, new AiSkillContext(
                entradas: ['cheques' => $cheques],
                entidadTipo: RedactarDetalleCanjeChequeSkill::ENTIDAD,
            ));
        } catch (Throwable $e) {
            return [
                'ok' => true,
                'detalle' => $fallback,
                'fuente' => 'deterministico',
                'error' => $e->getMessage(),
            ];
        }

        if (! $resultado->ok) {
            return [
                'ok' => true,
                'detalle' => $fallback,
                'fuente' => 'deterministico',
                'error' => $resultado->error,
            ];
        }

        $detalle = trim((string) ($resultado->datos['detalle'] ?? ''));
        if ($detalle === '') {
            $detalle = $fallback;
        }

        return [
            'ok' => true,
            'detalle' => $detalle,
            'fuente' => (string) ($resultado->datos['fuente'] ?? 'ia'),
            'error' => null,
        ];
    }
}
