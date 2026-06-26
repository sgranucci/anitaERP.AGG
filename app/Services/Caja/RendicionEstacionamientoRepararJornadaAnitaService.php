<?php

namespace App\Services\Caja;

use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Models\Ventas\Puntoventa;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoAnitaRendgastroSupport;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Repara rendg_total_z y rendg_tot_nc en Anita por fecha de jornada, empresa y PV CAE.
 *
 * Portadora del Z/NC del día: secuencia de turno N → T → M (no depende del orden de carga en caja).
 * Si hay varias cabeceras del mismo turno, desempate por hora y nro_oper.
 */
final class RendicionEstacionamientoRepararJornadaAnitaService
{
    public function __construct(
        private readonly RendicionEstacionamientoAnitaSyncService $anitaSyncService,
        private readonly RendicionEstacionamientoAnitaRendgastroSupport $rendgastroSupport,
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroGastroSupport,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reparar(
        JornadaEstacionamiento $jornada,
        ?string $codigoPuntoventaFiltro = null,
        bool $dryRun = false,
    ): array {
        if (! $this->anitaSyncService->sincronizacionHabilitada()) {
            throw new \RuntimeException('RENDICION_ESTACIONAMIENTO_SINCRONIZAR_ANITA está deshabilitado.');
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
            $resultados[] = $this->repararPuntoventaFecha(
                $pv,
                $empresaId,
                $fechaJornada,
                $fechaEntera,
                $dryRun,
            );
        }

        return $resultados;
    }

    /**
     * Repara Z/NC por fecha y empresa sin exigir jornada_estacionamiento en ERP
     * (estacionamiento legacy / Anita-only).
     *
     * @return list<array<string, mixed>>
     */
    public function repararPorFechaEmpresa(
        int $empresaId,
        string $fechaJornada,
        ?string $codigoPuntoventaFiltro = null,
        bool $dryRun = false,
    ): array {
        if (! $this->anitaSyncService->sincronizacionHabilitada()) {
            throw new \RuntimeException('RENDICION_ESTACIONAMIENTO_SINCRONIZAR_ANITA está deshabilitado.');
        }

        $fechaJornada = Carbon::parse($fechaJornada)->toDateString();
        $fechaEntera = (int) Carbon::parse($fechaJornada)->format('Ymd');
        $puntosVenta = $this->resolverPuntosVentaPorFecha($empresaId, $fechaJornada, $codigoPuntoventaFiltro);
        $resultados = [];

        foreach ($puntosVenta as $pv) {
            $resultados[] = $this->repararPuntoventaFecha(
                $pv,
                $empresaId,
                $fechaJornada,
                $fechaEntera,
                $dryRun,
            );
        }

        return $resultados;
    }

    /**
     * @return array<string, mixed>
     */
    private function repararPuntoventaFecha(
        Puntoventa $pv,
        int $empresaId,
        string $fechaJornada,
        int $fechaEntera,
        bool $dryRun,
    ): array {
        $sucursal = $this->rendgastroSupport->codigoPuntoventaEntero($pv->codigo);
        if ($sucursal <= 0) {
            return [
                'puntoventa' => $pv->codigo,
                'sucursal' => 0,
                'estado' => 'sucursal_invalida',
                'total_z' => null,
                'tot_nc' => null,
                'portadora_nro_oper' => null,
                'cabeceras' => 0,
            ];
        }

        if ($this->rendgastroSupport->esSucursalMaquinaVending($sucursal)) {
            return [
                'puntoventa' => $pv->codigo,
                'sucursal' => $sucursal,
                'estado' => 'vending_omitido',
                'total_z' => null,
                'tot_nc' => null,
                'portadora_nro_oper' => null,
                'cabeceras' => 0,
            ];
        }

        $cabeceras = $this->rendgastroSupport->listarCabecerasPorSucursal($empresaId, $fechaEntera, $sucursal);
        if ($cabeceras === []) {
            return [
                'puntoventa' => $pv->codigo,
                'sucursal' => $sucursal,
                'estado' => 'sin_registros_anita',
                'total_z' => null,
                'tot_nc' => null,
                'portadora_nro_oper' => null,
                'cabeceras' => 0,
            ];
        }

        $totales = $this->resolverTotalesDia($pv, $empresaId, $fechaJornada, $cabeceras);
        $totalZ = $totales['z'];
        $totNc = $totales['nc'];

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

        return [
            'puntoventa' => $pv->codigo,
            'sucursal' => $sucursal,
            'estado' => $dryRun ? 'simulado' : 'actualizado',
            'total_z' => $totalZ,
            'tot_nc' => $totNc,
            'totales_origen' => $totales['origen'],
            'portadora_nro_oper' => $portadoraNro,
            'portadora_turno' => $portadoraTurno,
            'portadora_hora' => (string) ($portadora->rendg_hora ?? ''),
            'cabeceras' => count($detalle),
            'detalle' => $detalle,
        ];
    }

    /**
     * Z/NC del día = suma de rendg_total_x / rendg_tot_nc de todas las cabeceras del PV en rendgastro.
     *
     * @param  list<object>  $cabeceras
     * @return array{z: float, nc: float, origen: string}
     */
    private function resolverTotalesDia(
        Puntoventa $pv,
        int $empresaId,
        string $fechaJornada,
        array $cabeceras,
    ): array {
        unset($pv, $empresaId, $fechaJornada);

        $zAnita = 0.0;
        $ncAnita = 0.0;
        foreach ($cabeceras as $fila) {
            $zAnita += round((float) ($fila->rendg_total_x ?? 0), 2);
            $ncAnita += round((float) ($fila->rendg_tot_nc ?? 0), 2);
        }

        return [
            'z' => round($zAnita, 2),
            'nc' => round($ncAnita, 2),
            'origen' => 'anita_x',
        ];
    }

    /**
     * @return Collection<int, Puntoventa>
     */
    private function resolverPuntosVentaPorFecha(int $empresaId, string $fechaJornada, ?string $codigoFiltro): Collection
    {
        $fechaEntera = (int) Carbon::parse($fechaJornada)->format('Ymd');
        $porId = [];

        foreach ($this->rendgastroGastroSupport->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera) as $fila) {
            if ($this->rendgastroGastroSupport->esCabeceraPostCierreWaitry($fila)) {
                continue;
            }
            $sucursal = (int) ($fila->rendg_sucursal ?? 0);
            if ($sucursal <= 0) {
                continue;
            }
            if (! $this->rendgastroGastroSupport->esSucursalDeEstacionamiento($empresaId, $sucursal)) {
                continue;
            }
            $pv = $this->puntoventaPorSucursal($empresaId, $sucursal, $codigoFiltro);
            if ($pv !== null) {
                $porId[(int) $pv->id] = $pv;
            }
        }

        return collect($porId)->sortBy('codigo')->values();
    }

    private function puntoventaPorSucursal(int $empresaId, int $sucursal, ?string $codigoFiltro): ?Puntoventa
    {
        $candidatos = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('modofacturacion', '!=', 'M')
            ->get();

        foreach ($candidatos as $pv) {
            if ($this->rendgastroSupport->codigoPuntoventaEntero($pv->codigo) !== $sucursal) {
                continue;
            }
            if ($codigoFiltro !== null && trim($codigoFiltro) !== ''
                && trim((string) $pv->codigo) !== trim($codigoFiltro)) {
                continue;
            }

            return $pv;
        }

        return null;
    }

    /**
     * @return Collection<int, Puntoventa>
     */
    private function puntosVentaEnJornada(JornadaEstacionamiento $jornada, ?string $codigoFiltro): Collection
    {
        $rendiciones = RendicionEstacionamientoCaja::query()
            ->where('tipo', RendicionEstacionamientoCaja::TIPO_TURNO)
            ->where('empresa_id', (int) $jornada->empresa_id)
            ->whereHas('turnoOperativo', fn ($q) => $q->where('jornada_estacionamiento_id', (int) $jornada->id))
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
