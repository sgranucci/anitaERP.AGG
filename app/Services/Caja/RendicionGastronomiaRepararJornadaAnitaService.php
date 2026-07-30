<?php

namespace App\Services\Caja;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoRendicionAnitaSupport;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Carbon\Carbon;

/**
 * Repara rendg_total_z y rendg_tot_nc en Anita por fecha de jornada, empresa y PC (rendg_host).
 *
 * Z del día = facturación bruta CAE + CAEA de la PC (sin NC). Los campos rendg_tot_fc_caea
 * por turno se mantienen; solo se actualiza la portadora (turno N → T → M).
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
        ?string $identificadorPcFiltro = null,
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
        $cabecerasDia = $this->rendgastroSupport->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera);
        $pcs = $this->identificadoresPcEnJornada($jornada, $identificadorPcFiltro);
        $resultados = [];

        foreach ($pcs as $identificadorPc) {
            $cabeceras = array_values(array_filter(
                $this->rendgastroSupport->filtrarCabecerasPorHost($cabecerasDia, $identificadorPc),
                fn (object $fila): bool => ! $this->rendgastroSupport->esCabeceraPostCierreWaitry($fila),
            ));

            if ($cabeceras === []) {
                $resultados[] = [
                    'identificador_pc' => $identificadorPc,
                    'puntoventa' => $identificadorPc,
                    'estado' => 'sin_registros_anita',
                    'total_z' => null,
                    'tot_nc' => null,
                    'portadora_nro_oper' => null,
                    'cabeceras' => 0,
                ];

                continue;
            }

            $totalZ = GastronomiaTurnoOperativoTotalesSupport::totalFacturasSinNotasCredito(
                $identificadorPc,
                $empresaId,
                $fechaJornada,
            );
            $totNc = GastronomiaTurnoOperativoTotalesSupport::totalNotasCreditoPorPc(
                $identificadorPc,
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
                $totFcCaea = null;
                if ($esPortadora && $z > 0.02) {
                    $fcCaeaPortadora = round((float) ($portadora->rendg_tot_fc_caea ?? 0), 2);
                    if ($fcCaeaPortadora > 0.02) {
                        $totFcCaea = 0.0;
                    }
                }

                if (! $dryRun) {
                    $this->anitaSyncService->actualizarTotalesReparacionPorNroOper($nroOper, $z, $nc, $totFcCaea);
                }

                $detalle[] = array_merge($d, [
                    'z' => $z,
                    'tot_nc' => $nc,
                    'fc_caea_limpio' => $totFcCaea,
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
                'identificador_pc' => $identificadorPc,
                'puntoventa' => $identificadorPc,
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

        if ($identificadorPcFiltro === null) {
            $legacy = $this->limpiarCabecerasHuérfanas($empresaId, $fechaEntera, $cabecerasDia, $dryRun);
            if ($legacy !== []) {
                $resultados[] = $legacy;
            }
            $caea = $this->normalizarCamposCaeaSalonYPostCierre($empresaId, $fechaEntera, $cabecerasDia, $dryRun);
            if ($caea !== []) {
                $resultados[] = $caea;
            }
        }

        return $resultados;
    }

    /**
     * PV CAEA (30/31) en Anita: CIERRE-WAITRY con Z = total_x = post-cierre Waitry solamente.
     * CAEA de salón no debe quedar en rendg_tot_fc_caea de turnos (ya está en Z de la PC CAE).
     *
     * @param  list<object>  $cabecerasDia
     * @return array<string, mixed>
     */
    public function normalizarCamposCaeaSalonYPostCierre(
        int $empresaId,
        int $fechaEntera,
        array $cabecerasDia,
        bool $dryRun = false,
    ): array {
        $detalle = [];

        foreach ($cabecerasDia as $fila) {
            $nroOper = (int) ($fila->rendg_nro_oper ?? 0);
            if ($nroOper <= 0) {
                continue;
            }

            if ($this->rendgastroSupport->esCabeceraPostCierreWaitry($fila)) {
                $totalX = round((float) ($fila->rendg_total_x ?? 0), 2);
                $fcCaea = round((float) ($fila->rendg_tot_fc_caea ?? 0), 2);
                $z = round((float) ($fila->rendg_total_z ?? 0), 2);
                $importePostCierre = $totalX > 0 ? $totalX : $fcCaea;
                if ($importePostCierre <= 0.02) {
                    continue;
                }
                if (abs($z - $importePostCierre) <= 0.02 && abs($fcCaea - $importePostCierre) <= 0.02) {
                    continue;
                }
                if (! $dryRun) {
                    $this->anitaSyncService->actualizarTotalesReparacionPorNroOper(
                        $nroOper,
                        $importePostCierre,
                        round((float) ($fila->rendg_tot_nc ?? 0), 2),
                        $importePostCierre,
                        round((float) ($fila->rendg_tot_nc_caea ?? 0), 2),
                    );
                }
                $detalle[] = [
                    'nro_oper' => $nroOper,
                    'host' => CierreJornadaProcesoRendicionAnitaSupport::HOST,
                    'z_antes' => $z,
                    'z_esperado' => $importePostCierre,
                    'fc_caea' => $importePostCierre,
                ];

                continue;
            }

            if ($this->rendgastroSupport->esCabeceraEstacionamiento($fila, $empresaId)) {
                continue;
            }

            if ($this->rendgastroSupport->esCabeceraAgregadosCaea($fila)) {
                continue;
            }

            $fcCaea = round((float) ($fila->rendg_tot_fc_caea ?? 0), 2);
            $ncCaea = round((float) ($fila->rendg_tot_nc_caea ?? 0), 2);
            if ($fcCaea <= 0.02 && $ncCaea <= 0.02) {
                continue;
            }

            if (! $dryRun) {
                $this->anitaSyncService->actualizarTotalesReparacionPorNroOper(
                    $nroOper,
                    round((float) ($fila->rendg_total_z ?? 0), 2),
                    round((float) ($fila->rendg_tot_nc ?? 0), 2),
                    0.0,
                    0.0,
                );
            }

            $detalle[] = [
                'nro_oper' => $nroOper,
                'host' => trim((string) ($fila->rendg_host ?? '')),
                'fc_caea_antes' => $fcCaea,
                'nc_caea_antes' => $ncCaea,
            ];
        }

        if ($detalle === []) {
            return [];
        }

        return [
            'identificador_pc' => 'NORMALIZAR-CAEA',
            'puntoventa' => 'PV-CAEA-SALON',
            'estado' => $dryRun ? 'caea_simulado' : 'caea_normalizado',
            'total_z' => null,
            'tot_nc' => null,
            'portadora_nro_oper' => null,
            'portadora_turno' => '—',
            'portadora_hora' => '—',
            'cabeceras' => count($detalle),
            'detalle' => $detalle,
        ];
    }

    /**
     * Anula Z/NC/fc_caea en cabeceras con host legacy (pc-caja*, bingo…) no registradas en configuración.
     *
     * @param  list<object>  $cabecerasDia
     * @return array<string, mixed>
     */
    public function limpiarCabecerasHuérfanas(
        int $empresaId,
        int $fechaEntera,
        array $cabecerasDia,
        bool $dryRun = false,
    ): array {
        $hostsConfig = ConfiguracionPuntoventaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->pluck('identificador_pc')
            ->map(static fn ($host): string => trim((string) $host))
            ->filter()
            ->values()
            ->all();

        $audit = $this->rendgastroSupport->auditarCabecerasHuérfanasLegacy($empresaId, $fechaEntera, $hostsConfig);
        $detalle = [];

        foreach ($audit['filas_legacy'] as $fila) {
            $nroOper = (int) ($fila['nro_oper'] ?? 0);
            if ($nroOper <= 0) {
                continue;
            }

            if (! $dryRun) {
                $this->anitaSyncService->actualizarTotalesReparacionPorNroOper($nroOper, 0.0, 0.0, 0.0);
            }

            $detalle[] = $fila;
        }

        if ($detalle === []) {
            return [];
        }

        return [
            'identificador_pc' => 'LEGACY-HUERFANAS',
            'puntoventa' => 'LEGACY-HUERFANAS',
            'estado' => $dryRun ? 'legacy_simulado' : 'legacy_limpiado',
            'total_z' => $audit['rendg_legacy_z'],
            'tot_nc' => null,
            'portadora_nro_oper' => null,
            'portadora_turno' => '—',
            'portadora_hora' => '—',
            'cabeceras' => count($detalle),
            'detalle' => $detalle,
        ];
    }

    /**
     * @return list<string>
     */
    private function identificadoresPcEnJornada(JornadaGastronomia $jornada, ?string $pcFiltro): array
    {
        $rendiciones = RendicionGastronomiaCaja::query()
            ->where('tipo', RendicionGastronomiaCaja::TIPO_TURNO)
            ->where('empresa_id', (int) $jornada->empresa_id)
            ->whereHas('turnoOperativo', fn ($q) => $q->where('jornada_gastronomia_id', (int) $jornada->id))
            ->with('turnoOperativo')
            ->get();

        /** @var array<string, string> $porPc */
        $porPc = [];
        foreach ($rendiciones as $rendicion) {
            $pc = trim((string) ($rendicion->turnoOperativo?->identificador_pc ?? ''));
            if ($pc === '' || ! preg_match('/^\d{1,3}(?:\.\d{1,3}){3}$/', $pc)) {
                continue;
            }
            if ($pcFiltro !== null && trim($pcFiltro) !== '' && $pc !== trim($pcFiltro)) {
                continue;
            }
            $porPc[$pc] = $pc;
        }

        $lista = array_values($porPc);
        sort($lista);

        return $lista;
    }
}
