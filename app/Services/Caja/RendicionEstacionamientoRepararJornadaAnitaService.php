<?php

namespace App\Services\Caja;

use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Models\Ventas\Puntoventa;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoAnitaRendgastroSupport;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoAnitaTotalZPorPcService;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoCabeceraAnitaMapper;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use App\Support\Caja\Estacionamiento\EstacionamientoTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Repara rendg_total_z, rendg_tot_nc y rendvalor (neto por medio) en Anita por fecha de jornada.
 *
 * 1) Por PV CAE: portadora N→T→M, Z/NC/X y rendvalor.
 * 2) Por PC: Z del día = CAE + CAEA compartido (20/31/30) vía TotalZPorPcService.
 * 3) Desglose CAEA: rendg_tot_fc_caea / tot_nc_caea / suc_caea por host originador.
 */
final class RendicionEstacionamientoRepararJornadaAnitaService
{
    public function __construct(
        private readonly RendicionEstacionamientoAnitaSyncService $anitaSyncService,
        private readonly RendicionEstacionamientoAnitaRendgastroSupport $rendgastroSupport,
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroGastroSupport,
        private readonly RendicionEstacionamientoAnitaTotalZPorPcService $totalZPorPcService,
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

        // Sin filtro de un solo PV: Z por PC (incluye CAEA 20/31/30) + desglose CAEA en cabecera.
        if ($codigoPuntoventaFiltro === null || trim($codigoPuntoventaFiltro) === '') {
            $caea = $this->aplicarTotalesPorPcYCaea($jornada, $dryRun);
            $resultados[] = $caea;
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
     * Z/NC del día por identificador_pc (CAE+CAEA) y campos rendg_*_caea por turno.
     *
     * @return array<string, mixed>
     */
    private function aplicarTotalesPorPcYCaea(JornadaEstacionamiento $jornada, bool $dryRun): array
    {
        $rendiciones = RendicionEstacionamientoCaja::query()
            ->where('tipo', RendicionEstacionamientoCaja::TIPO_TURNO)
            ->where('empresa_id', (int) $jornada->empresa_id)
            ->whereHas('turnoOperativo', fn ($q) => $q->where('jornada_estacionamiento_id', (int) $jornada->id))
            ->with(['puntoventaCaea', 'turnoOperativo.turno', 'turnoOperativo.jornada'])
            ->get();

        $porPc = [];
        $caeaActualizados = 0;

        foreach ($rendiciones as $rendicion) {
            $pc = trim((string) ($rendicion->turnoOperativo?->identificador_pc ?? ''));
            if ($pc !== '') {
                $porPc[$pc] = ($porPc[$pc] ?? 0) + 1;
            }

            if ($dryRun) {
                continue;
            }

            $nroOper = (int) ($rendicion->nro_oper_anita
                ?? RendicionEstacionamientoCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));
            if ($nroOper <= 0) {
                continue;
            }

            $this->anitaSyncService->reaplicarCamposCaeaEnAnita($rendicion);
            $caeaActualizados++;
        }

        if (! $dryRun) {
            $this->totalZPorPcService->aplicarForzado($jornada);
        }

        $detallePc = [];
        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d')
            ?? $jornada->cierre_en?->format('Y-m-d')
            ?? '';
        foreach (array_keys($porPc) as $pc) {
            $detallePc[] = [
                'host' => $pc,
                'rendiciones' => $porPc[$pc],
                'z_dia_pc' => $fechaJornada !== ''
                    ? EstacionamientoTurnoOperativoTotalesSupport::totalFacturasSinNotasCredito(
                        $pc,
                        $empresaId,
                        $fechaJornada,
                    )
                    : null,
                'nc_dia_pc' => $fechaJornada !== ''
                    ? EstacionamientoTurnoOperativoTotalesSupport::totalNotasCreditoPorPc(
                        $pc,
                        $empresaId,
                        $fechaJornada,
                    )
                    : null,
            ];
        }

        return [
            'puntoventa' => 'POR_PC+CAEA',
            'sucursal' => 0,
            'estado' => $dryRun ? 'simulado_pc_caea' : 'actualizado_pc_caea',
            'total_z' => null,
            'tot_nc' => null,
            'portadora_nro_oper' => null,
            'cabeceras' => $rendiciones->count(),
            'hosts_pc' => count($porPc),
            'caea_campos_actualizados' => $dryRun ? 0 : $caeaActualizados,
            'detalle_pc' => $detallePc,
        ];
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

        $portadora = $this->rendgastroSupport->elegirPortadora($cabeceras);
        $portadoraNro = (int) ($portadora->rendg_nro_oper ?? 0);
        $totales = $this->resolverTotalesDia($pv, $empresaId, $fechaJornada, $cabeceras, $portadoraNro);
        $totalZ = $totales['z'];
        $totNc = $totales['nc'];

        $detalle = [];
        $rendvalorReparadas = 0;

        foreach ($this->rendgastroSupport->detalleCabecerasOrdenado($cabeceras, $portadoraNro) as $d) {
            $nroOper = (int) $d['nro_oper'];
            $esPortadora = ! empty($d['portadora']);
            $z = $esPortadora ? $totalZ : 0.0;
            $nc = $esPortadora ? $totNc : 0.0;

            if (! $dryRun) {
                $this->anitaSyncService->actualizarTotalZYNcPorNroOper($nroOper, $z, $nc);

                $rendicion = RendicionEstacionamientoCaja::query()
                    ->where('empresa_id', $empresaId)
                    ->where('nro_oper_anita', $nroOper)
                    ->first();
                if ($rendicion !== null) {
                    $this->anitaSyncService->actualizarInvitacionYRedondeoPorNroOper($nroOper, $rendicion);
                    $this->anitaSyncService->reaplicarRendvalorEnAnita($rendicion);
                    $rendvalorReparadas++;
                }
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
            'rendvalor_reparadas' => $dryRun ? 0 : $rendvalorReparadas,
            'detalle' => $detalle,
        ];
    }

    /**
     * @param  list<object>  $cabeceras
     * @return array{z: float, nc: float, origen: string}
     */
    private function resolverTotalesDia(
        Puntoventa $pv,
        int $empresaId,
        string $fechaJornada,
        array $cabeceras,
        int $portadoraNro,
    ): array {
        unset($portadoraNro);

        $ncCabeceras = 0.0;
        foreach ($cabeceras as $fila) {
            $ncCabeceras += round((float) ($fila->rendg_tot_nc ?? 0), 2);
        }
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
        $nc = round(max($ncCabeceras, $erpNc), 2);

        // Preferir Z/NC del ERP (misma fuente que la auditoría). Fallback a suma rendg_total_x.
        if ($erpZ > 0.005 || $erpNc > 0.005) {
            return [
                'z' => $erpZ,
                'nc' => $nc,
                'origen' => 'erp',
            ];
        }

        $totales = $this->rendgastroSupport->totalesZPortadoraParaCierre($cabeceras, $nc);

        return [
            'z' => $totales['z'],
            'nc' => $totales['nc'],
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
