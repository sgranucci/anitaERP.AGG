<?php

namespace App\Support\Caja\AnitaSync;

use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Services\Caja\RendicionEstacionamientoAnitaSyncService;
use App\Support\Caja\Estacionamiento\EstacionamientoTurnoOperativoTotalesSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Asigna rendg_total_z en Anita por PC al cierre de jornada (facturación bruta del día, sin NC).
 *
 * Mientras la jornada no fue presentada en caja, las rendiciones de turno van con Z=0 en Anita.
 * El recálculo (facturación bruta del día por PC → Z solo en la última rendición de esa PC
 * por fecharendicion) se dispara al presentar o corregir la rendición tipo jornada en Caja,
 * o al anular esa presentación / borrar una rendición de turno si la jornada ya fue presentada.
 * No se dispara al rendir turnos ni al cerrar la jornada en Ventas → Estacionamiento.
 */
final class RendicionEstacionamientoAnitaTotalZPorPcService
{
    private const LOG_EVENTO = 'rendicion_estacionamiento.anita_total_z';

    public function __construct(
        private readonly RendicionEstacionamientoAnitaSyncService $anitaSyncService,
    ) {
    }

    public function aplicarSiJornadaCerrada(int $jornadaId): void
    {
        if ($jornadaId <= 0 || ! $this->anitaSyncService->sincronizacionHabilitada()) {
            return;
        }

        $jornada = JornadaEstacionamiento::query()->find($jornadaId);
        if ($jornada === null
            || $jornada->estado !== JornadaEstacionamiento::ESTADO_CERRADA
            || $jornada->cierre_en === null) {
            return;
        }

        $this->aplicar($jornada);
    }

    public function aplicarDesdeRendicionTurno(RendicionEstacionamientoCaja $rendicion): void
    {
        if ($rendicion->esRendicionJornada()) {
            return;
        }

        $jornadaId = (int) ($rendicion->turnoOperativo?->jornada_estacionamiento_id ?? 0);
        if ($jornadaId <= 0) {
            $rendicion->loadMissing('turnoOperativo');
            $jornadaId = (int) ($rendicion->turnoOperativo?->jornada_estacionamiento_id ?? 0);
        }

        $this->aplicarSiJornadaCerrada($jornadaId);
    }

    /**
     * Al anular el cierre de jornada, Anita debe volver a Z=0 hasta un nuevo cierre.
     */
    public function resetTotalZEnJornada(int $jornadaId): void
    {
        if ($jornadaId <= 0 || ! $this->anitaSyncService->sincronizacionHabilitada()) {
            return;
        }

        $rendiciones = $this->rendicionesTurnoSincronizablesEnJornada($jornadaId);
        foreach ($rendiciones as $rendicion) {
            try {
                $this->anitaSyncService->actualizarSoloTotalZEnAnita($rendicion, 0.0);
            } catch (\Throwable $e) {
                Log::warning(self::LOG_EVENTO.'.reset_fallo', [
                    'jornada_id' => $jornadaId,
                    'rendicion_id' => $rendicion->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function aplicar(JornadaEstacionamiento $jornada): void
    {
        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d')
            ?? $jornada->cierre_en?->format('Y-m-d');

        if ($empresaId <= 0 || $fechaJornada === null || $fechaJornada === '') {
            return;
        }

        $rendiciones = $this->rendicionesTurnoSincronizablesEnJornada((int) $jornada->id);

        if ($rendiciones->isEmpty()) {
            return;
        }

        /** @var Collection<string, Collection<int, RendicionEstacionamientoCaja>> $porPc */
        $porPc = $rendiciones->groupBy(
            fn (RendicionEstacionamientoCaja $r) => trim((string) ($r->turnoOperativo?->identificador_pc ?? '')),
        );

        foreach ($porPc as $identificadorPc => $grupoPc) {
            if ($identificadorPc === '') {
                continue;
            }

            $totalDiaPc = EstacionamientoTurnoOperativoTotalesSupport::totalFacturasSinNotasCredito(
                $identificadorPc,
                $empresaId,
                $fechaJornada,
                null,
                null,
            );

            $portadora = $this->resolverRendicionPortadoraZ($grupoPc);

            foreach ($grupoPc as $rendicion) {
                $totalZ = ($portadora !== null && (int) $rendicion->id === (int) $portadora->id)
                    ? $totalDiaPc
                    : 0.0;

                try {
                    $this->anitaSyncService->actualizarSoloTotalZEnAnita($rendicion, $totalZ);
                } catch (\Throwable $e) {
                    Log::warning(self::LOG_EVENTO.'.fallo', [
                        'jornada_id' => $jornada->id,
                        'rendicion_id' => $rendicion->id,
                        'pc' => $identificadorPc,
                        'total_z' => $totalZ,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Portadora del Z del día en la PC: turno N → T → M (misma regla que rendgastro / reparación).
     * Si hay varias rendiciones del mismo turno, la de mayor fecharendicion.
     */
    private function resolverRendicionPortadoraZ(Collection $grupoPc): ?RendicionEstacionamientoCaja
    {
        /** @var array<string, Collection<int, RendicionEstacionamientoCaja>> $porLetra */
        $porLetra = [];
        foreach ($grupoPc as $rendicion) {
            $letra = RendicionEstacionamientoAnitaRendgastroSupport::letraTurnoDesdeNombre(
                $rendicion->turnoOperativo?->turno?->nombre,
            );
            if (! isset($porLetra[$letra])) {
                $porLetra[$letra] = collect();
            }
            $porLetra[$letra]->push($rendicion);
        }

        foreach (RendicionEstacionamientoAnitaRendgastroSupport::SECUENCIA_TURNO_PORTADORA as $letra) {
            if (! empty($porLetra[$letra]) && $porLetra[$letra]->isNotEmpty()) {
                return $this->elegirUltimaRendicionPorFecharendicion($porLetra[$letra]);
            }
        }

        return $this->elegirUltimaRendicionPorFecharendicion($grupoPc);
    }

    private function elegirUltimaRendicionPorFecharendicion(Collection $grupo): ?RendicionEstacionamientoCaja
    {
        return $grupo
            ->sort(function (RendicionEstacionamientoCaja $a, RendicionEstacionamientoCaja $b): int {
                $tsA = $a->fecharendicion?->getTimestamp() ?? 0;
                $tsB = $b->fecharendicion?->getTimestamp() ?? 0;
                if ($tsA !== $tsB) {
                    return $tsB <=> $tsA;
                }

                return (int) $b->id <=> (int) $a->id;
            })
            ->first();
    }

    /**
     * @return Collection<int, RendicionEstacionamientoCaja>
     */
    private function rendicionesTurnoSincronizablesEnJornada(int $jornadaId): Collection
    {
        if ($jornadaId <= 0) {
            return collect();
        }

        return RendicionEstacionamientoCaja::query()
            ->where('tipo', RendicionEstacionamientoCaja::TIPO_TURNO)
            ->whereNotNull('turno_operativo_estacionamiento_id')
            ->whereHas('turnoOperativo', fn ($q) => $q->where('jornada_estacionamiento_id', $jornadaId))
            ->with([
                'movimientos.cuentacaja',
                'puntoventaCae',
                'puntoventaCaea',
                'turnoOperativo.turno',
                'turnoOperativo.jornada',
            ])
            ->get()
            ->filter(fn (RendicionEstacionamientoCaja $r) => $this->puedeSincronizarAnita($r));
    }

    private function puedeSincronizarAnita(RendicionEstacionamientoCaja $rendicion): bool
    {
        $nroOper = (int) ($rendicion->nro_oper_anita
            ?? RendicionEstacionamientoCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));

        return $nroOper > 0
            || $rendicion->anita_sincronizado_en !== null;
    }
}
