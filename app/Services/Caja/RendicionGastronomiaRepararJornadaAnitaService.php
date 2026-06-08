<?php

namespace App\Services\Caja;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Repara rendg_total_z y rendg_tot_nc en Anita por fecha de jornada, empresa y PV CAE.
 *
 * Portadora del Z/NC del día: secuencia de turno N → T → M (no depende del orden de carga en caja).
 * Si hay varias cabeceras del mismo turno, desempate por hora y nro_oper.
 */
final class RendicionGastronomiaRepararJornadaAnitaService
{
    public function __construct(
        private readonly RendicionGastronomiaAnitaSyncService $anitaSyncService,
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reparar(
        JornadaGastronomia $jornada,
        ?string $codigoPuntoventaFiltro = null,
        bool $dryRun = false,
    ): array {
        if (! $this->anitaSyncService->sincronizacionHabilitada()) {
            throw new \RuntimeException('RENDICION_GASTRONOMIA_SINCRONIZAR_ANITA está deshabilitado.');
        }

        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d')
            ?? $jornada->cierre_en?->format('Y-m-d');

        if ($empresaId <= 0 || $fechaJornada === null || $fechaJornada === '') {
            throw new \InvalidArgumentException('La jornada no tiene empresa o fecha de jornada válida.');
        }

        $fechaEntera = (int) Carbon::parse($fechaJornada)->format('Ymd');
        $puntosVenta = $this->puntosVentaEnJornada($jornada, $codigoPuntoventaFiltro);
        $resultados = [];

        foreach ($puntosVenta as $pv) {
            $sucursal = $this->rendgastroSupport->codigoPuntoventaEntero($pv->codigo);
            if ($sucursal <= 0) {
                continue;
            }

            $cabeceras = $this->rendgastroSupport->listarCabecerasPorSucursal($empresaId, $fechaEntera, $sucursal);
            if ($cabeceras === []) {
                $resultados[] = [
                    'puntoventa' => $pv->codigo,
                    'sucursal' => $sucursal,
                    'estado' => 'sin_registros_anita',
                    'total_z' => null,
                    'tot_nc' => null,
                    'portadora_nro_oper' => null,
                    'cabeceras' => 0,
                ];

                continue;
            }

            $totalZ = GastronomiaTurnoOperativoTotalesSupport::totalFacturasSinNotasCreditoPorPuntoventa(
                (int) $pv->id,
                $empresaId,
                $fechaJornada,
            );
            $totNc = GastronomiaTurnoOperativoTotalesSupport::totalNotasCreditoPorPuntoventa(
                (int) $pv->id,
                $empresaId,
                $fechaJornada,
            );

            $portadora = $this->rendgastroSupport->elegirPortadora($cabeceras);
            $portadoraNro = (int) ($portadora->rendg_nro_oper ?? 0);
            $detalle = [];

            foreach ($this->rendgastroSupport->detalleCabecerasOrdenado($cabeceras, $portadoraNro) as $d) {
                $nroOper = (int) $d['nro_oper'];
                $esPortadora = ! empty($d['portadora']);
                $z = $esPortadora ? $totalZ : 0.0;
                $nc = $esPortadora ? $totNc : 0.0;

                if (! $dryRun) {
                    $this->anitaSyncService->actualizarTotalZYNcPorNroOper($nroOper, $z, $nc);
                }

                $detalle[] = array_merge($d, [
                    'z' => $z,
                    'tot_nc' => $nc,
                ]);
            }

            $portadoraTurno = '—';
            foreach ($detalle as $d) {
                if (! empty($d['portadora'])) {
                    $portadoraTurno = (string) ($d['turno'] ?? '—');
                    break;
                }
            }

            $resultados[] = [
                'puntoventa' => $pv->codigo,
                'sucursal' => $sucursal,
                'estado' => $dryRun ? 'simulado' : 'actualizado',
                'total_z' => $totalZ,
                'tot_nc' => $totNc,
                'portadora_nro_oper' => $portadoraNro,
                'portadora_turno' => $portadoraTurno,
                'portadora_hora' => (string) ($portadora->rendg_hora ?? ''),
                'cabeceras' => count($detalle),
                'detalle' => $detalle,
            ];
        }

        return $resultados;
    }

    /**
     * @return Collection<int, Puntoventa>
     */
    private function puntosVentaEnJornada(JornadaGastronomia $jornada, ?string $codigoFiltro): Collection
    {
        $rendiciones = RendicionGastronomiaCaja::query()
            ->where('tipo', RendicionGastronomiaCaja::TIPO_TURNO)
            ->where('empresa_id', (int) $jornada->empresa_id)
            ->whereHas('turnoOperativo', fn ($q) => $q->where('jornada_gastronomia_id', (int) $jornada->id))
            ->with('puntoventaCae')
            ->get();

        $porId = [];
        foreach ($rendiciones as $rendicion) {
            $pv = $rendicion->puntoventaCae;
            if ($pv === null) {
                continue;
            }
            if ($codigoFiltro !== null && trim($codigoFiltro) !== ''
                && trim((string) $pv->codigo) !== trim($codigoFiltro)) {
                continue;
            }
            $porId[(int) $pv->id] = $pv;
        }

        return collect($porId)->sortBy('codigo')->values();
    }
}
