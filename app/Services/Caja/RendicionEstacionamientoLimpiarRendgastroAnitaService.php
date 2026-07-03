<?php

namespace App\Services\Caja;

use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Ventas\Puntoventa;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoAnitaRendgastroSupport;
use App\Support\Caja\Estacionamiento\EstacionamientoTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Limpia Z/NC en rendgastro para una jornada estacionamiento: solo cabeceras de estacionamiento,
 * totales desde ERP, portadora N→T→M, resto en cero.
 */
final class RendicionEstacionamientoLimpiarRendgastroAnitaService
{
    public function __construct(
        private readonly RendicionEstacionamientoAnitaSyncService $anitaSyncService,
        private readonly RendicionEstacionamientoAnitaRendgastroSupport $rendgastroSupport,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function limpiarJornada(JornadaEstacionamiento $jornada, bool $dryRun = false): array
    {
        if (! $this->anitaSyncService->sincronizacionHabilitada()) {
            throw new \RuntimeException('RENDICION_ESTACIONAMIENTO_SINCRONIZAR_ANITA está deshabilitado.');
        }

        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d')
            ?? $jornada->cierre_en?->format('Y-m-d');

        if ($empresaId <= 0 || $fechaJornada === null || $fechaJornada === '') {
            throw new \InvalidArgumentException('La jornada no tiene empresa o fecha de jornada válida.');
        }

        $rendiciones = $this->rendicionesTurnoJornada($jornada);
        $nroOperErp = $this->nroOperDesdeRendiciones($rendiciones);
        $turnoOperIds = $rendiciones
            ->pluck('turno_operativo_estacionamiento_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (! $dryRun) {
            foreach ($rendiciones as $rendicion) {
                $pv = $rendicion->puntoventaCae;
                $sucursal = $this->rendgastroSupport->codigoPuntoventaEntero($pv?->codigo);
                if ($this->rendgastroSupport->esSucursalMaquinaVending($sucursal)) {
                    continue;
                }

                try {
                    $this->anitaSyncService->limpiarHuerfanosYResincronizar($rendicion, true);
                } catch (\Throwable $e) {
                    // Continúa con el resto; el log ya registra fallos de bridge.
                }
            }

            $this->anitaSyncService->reaplicarTotalZPorPcEnJornada((int) $jornada->id);
        }

        $fechaEntera = (int) Carbon::parse($fechaJornada)->format('Ymd');
        $puntosVenta = $this->puntosVentaDesdeRendiciones($rendiciones);
        $resultados = [];

        foreach ($puntosVenta as $pv) {
            $resultados[] = $this->limpiarPuntoventa(
                $pv,
                $empresaId,
                $fechaJornada,
                $fechaEntera,
                $nroOperErp,
                $turnoOperIds,
                $dryRun,
            );
        }

        return $resultados;
    }

    /**
     * @param  list<int>  $nroOperErp
     * @param  list<int>  $turnoOperIds
     * @return array<string, mixed>
     */
    private function limpiarPuntoventa(
        Puntoventa $pv,
        int $empresaId,
        string $fechaJornada,
        int $fechaEntera,
        array $nroOperErp,
        array $turnoOperIds,
        bool $dryRun,
    ): array {
        $sucursal = $this->rendgastroSupport->codigoPuntoventaEntero($pv->codigo);
        if ($sucursal <= 0) {
            return [
                'puntoventa' => $pv->codigo,
                'sucursal' => 0,
                'estado' => 'sucursal_invalida',
                'erp_z' => null,
                'erp_nc' => null,
                'portadora_nro_oper' => null,
                'cabeceras_estacionamiento' => 0,
                'cabeceras_ignoradas' => 0,
            ];
        }

        if ($this->rendgastroSupport->esSucursalMaquinaVending($sucursal)) {
            return [
                'puntoventa' => $pv->codigo,
                'sucursal' => $sucursal,
                'estado' => 'vending_omitido',
                'erp_z' => null,
                'erp_nc' => null,
                'portadora_nro_oper' => null,
                'cabeceras_estacionamiento' => 0,
                'cabeceras_ignoradas' => 0,
            ];
        }

        $todas = $this->rendgastroSupport->listarCabecerasPorSucursal($empresaId, $fechaEntera, $sucursal);
        $estacionamiento = $this->rendgastroSupport->filtrarCabecerasSoloEstacionamiento(
            $todas,
            $empresaId,
            $nroOperErp,
            $turnoOperIds,
        );

        $erpZ = EstacionamientoTurnoOperativoTotalesSupport::totalFacturasSinNotasCreditoPorPuntoventa(
            (int) $pv->id,
            $empresaId,
            $fechaJornada,
        );
        $erpNc = EstacionamientoTurnoOperativoTotalesSupport::totalNotasCreditoPorPuntoventa(
            (int) $pv->id,
            $empresaId,
            $fechaJornada,
        );

        if ($estacionamiento === []) {
            return [
                'puntoventa' => $pv->codigo,
                'sucursal' => $sucursal,
                'estado' => $erpZ > 0.02 || $erpNc > 0.02 ? 'sin_cabeceras_estacionamiento' : 'sin_ventas',
                'erp_z' => $erpZ,
                'erp_nc' => $erpNc,
                'portadora_nro_oper' => null,
                'cabeceras_estacionamiento' => 0,
                'cabeceras_ignoradas' => count($todas),
            ];
        }

        $portadora = $this->rendgastroSupport->elegirPortadora($estacionamiento);
        $portadoraNro = (int) ($portadora->rendg_nro_oper ?? 0);
        $ncCabeceras = 0.0;
        foreach ($estacionamiento as $fila) {
            $ncCabeceras += round((float) ($fila->rendg_tot_nc ?? 0), 2);
        }
        $totalesPortadora = $this->rendgastroSupport->totalesZPortadoraParaCierre(
            $estacionamiento,
            round(max($ncCabeceras, $erpNc), 2),
        );
        $totalZ = $totalesPortadora['z'];
        $totNc = $totalesPortadora['nc'];
        $detalle = [];

        foreach ($estacionamiento as $fila) {
            $nroOper = (int) ($fila->rendg_nro_oper ?? 0);
            if ($nroOper <= 0) {
                continue;
            }

            $esPortadora = $nroOper === $portadoraNro;
            $z = $esPortadora ? $totalZ : 0.0;
            $nc = $esPortadora ? $totNc : 0.0;

            if (! $dryRun) {
                $this->anitaSyncService->actualizarTotalZYNcPorNroOper($nroOper, $z, $nc);
            }

            $detalle[] = [
                'nro_oper' => $nroOper,
                'turno' => $this->rendgastroSupport->letraTurnoDesdeNombre(
                    $this->letraTurnoDesdeCabecera($fila),
                ),
                'hora' => (string) ($fila->rendg_hora ?? ''),
                'host' => (string) ($fila->rendg_host ?? ''),
                'z' => $z,
                'tot_nc' => $nc,
                'portadora' => $esPortadora,
            ];
        }

        return [
            'puntoventa' => $pv->codigo,
            'sucursal' => $sucursal,
            'estado' => $dryRun ? 'simulado' : 'limpiado',
            'erp_z' => $erpZ,
            'erp_nc' => $erpNc,
            'portadora_nro_oper' => $portadoraNro,
            'cabeceras_estacionamiento' => count($estacionamiento),
            'cabeceras_ignoradas' => count($todas) - count($estacionamiento),
            'detalle' => $detalle,
        ];
    }

    private function letraTurnoDesdeCabecera(object $fila): string
    {
        $letra = trim((string) ($fila->rendg_turno ?? ''));
        if ($letra !== '' && $letra !== ' ') {
            return $letra;
        }

        return '?';
    }

    /**
     * @return Collection<int, RendicionEstacionamientoCaja>
     */
    private function rendicionesTurnoJornada(JornadaEstacionamiento $jornada): Collection
    {
        return RendicionEstacionamientoCaja::query()
            ->where('tipo', RendicionEstacionamientoCaja::TIPO_TURNO)
            ->where('empresa_id', (int) $jornada->empresa_id)
            ->whereHas('turnoOperativo', fn ($q) => $q->where('jornada_estacionamiento_id', (int) $jornada->id))
            ->with(['puntoventaCae', 'turnoOperativo.turno'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, RendicionEstacionamientoCaja>  $rendiciones
     * @return list<int>
     */
    private function nroOperDesdeRendiciones(Collection $rendiciones): array
    {
        $nros = [];
        foreach ($rendiciones as $rendicion) {
            $nro = (int) ($rendicion->nro_oper_anita ?? 0);
            if ($nro > 0) {
                $nros[] = $nro;
            }
        }

        return array_values(array_unique($nros));
    }

    /**
     * @param  Collection<int, RendicionEstacionamientoCaja>  $rendiciones
     * @return Collection<int, Puntoventa>
     */
    private function puntosVentaDesdeRendiciones(Collection $rendiciones): Collection
    {
        $porId = [];
        foreach ($rendiciones as $rendicion) {
            $pv = $rendicion->puntoventaCae;
            if ($pv === null) {
                continue;
            }
            $sucursal = $this->rendgastroSupport->codigoPuntoventaEntero($pv->codigo);
            if ($this->rendgastroSupport->esSucursalMaquinaVending($sucursal)) {
                continue;
            }
            $porId[(int) $pv->id] = $pv;
        }

        return collect($porId)->sortBy('codigo')->values();
    }
}
