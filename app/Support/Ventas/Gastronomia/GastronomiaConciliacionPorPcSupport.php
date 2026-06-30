<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaChequeoVentasAnitaErpService;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;

/**
 * Conciliación por terminal (PC): CAE + CAEA comparten emisión; rendgastro Z por rendg_host (CAE+CAEA).
 */
final class GastronomiaConciliacionPorPcSupport
{
    public function __construct(
        private readonly GastronomiaChequeoVentasAnitaErpService $chequeoVentasService,
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
        private readonly GastronomiaConciliacionPostCierreCaeaSupport $postCierreCaeaSupport,
        private readonly GastronomiaConciliacionExclusionEmisionSupport $exclusionEmisionSupport,
        private readonly GastronomiaConciliacionRebiscoAgregadosCaeaSupport $rebiscoAgregadosSupport,
        private readonly GastronomiaConciliacionVendingRendgSupport $vendingRendgSupport,
    ) {
    }

    /**
     * Conciliación operativa del día: por PC (CAE+CAEA vs rendg host) + totales salón/día.
     * Evita falsos DIF por CAEA facturada en PV sucursal cuando cae ARCA.
     *
     * @return array{
     *   filas_pc: list<array<string, mixed>>,
     *   totales_salon: array<string, float|null>,
     *   filas_totales: list<array<string, mixed>>,
     *   post_cierre: array<string, mixed>,
     *   jornada_abierta: bool
     * }
     */
    public function conciliacionDiaCompleta(
        int $empresaId,
        string $fechaJornada,
        float $tolerancia,
        bool $jornadaAbierta,
        ?array $indiceAnitaBulk = null,
    ): array {
        $filasPc = $this->filasDiaPorPc($empresaId, $fechaJornada, $tolerancia, $jornadaAbierta, $indiceAnitaBulk);

        $totalesSalon = [
            'ventas_erp_cae' => 0.0,
            'ventas_erp_caea' => 0.0,
            'ventas_erp' => 0.0,
            'ventas_anita_cae' => 0.0,
            'ventas_anita_caea' => 0.0,
            'ventas_anita' => 0.0,
            'rendgastro_z' => 0.0,
            'cantidad_facturas_erp' => 0,
        ];
        $algunaPcSinRendg = false;

        foreach ($filasPc as $fila) {
            $totalesSalon['ventas_erp_cae'] += (float) ($fila['ventas_erp_cae'] ?? 0);
            $totalesSalon['ventas_erp_caea'] += (float) ($fila['ventas_erp_caea'] ?? 0);
            $totalesSalon['ventas_erp'] += (float) ($fila['ventas_erp'] ?? 0);
            $totalesSalon['ventas_anita_cae'] += (float) ($fila['ventas_anita_cae'] ?? 0);
            $totalesSalon['ventas_anita_caea'] += (float) ($fila['ventas_anita_caea'] ?? 0);
            $totalesSalon['ventas_anita'] += (float) ($fila['ventas_anita'] ?? 0);
            $totalesSalon['cantidad_facturas_erp'] += (int) ($fila['cantidad_facturas_erp'] ?? 0);
            if (($fila['rendgastro_z'] ?? null) !== null) {
                $totalesSalon['rendgastro_z'] += (float) $fila['rendgastro_z'];
            } elseif (! $jornadaAbierta && (float) ($fila['ventas_erp'] ?? 0) > $tolerancia) {
                $algunaPcSinRendg = true;
            }
        }

        foreach ($totalesSalon as $k => $v) {
            $totalesSalon[$k] = round((float) $v, 2);
        }

        $totalesSalon['diff_erp_anita'] = round($totalesSalon['ventas_erp'] - $totalesSalon['ventas_anita'], 2);
        $totalesSalon['diff_erp_rendg'] = $jornadaAbierta
            ? null
            : round($totalesSalon['ventas_erp'] - $totalesSalon['rendgastro_z'], 2);
        $totalesSalon['alguna_pc_sin_rendg'] = $algunaPcSinRendg;

        $filasTotales = [];
        if ($filasPc !== []) {
            $filasTotales[] = GastronomiaConciliacionEstadoSupport::aplicarEstadosEnFila([
                'tipo_fila' => 'total_salon',
                'identificador_pc' => 'TOTAL-SALON',
                'tipo_pv' => 'TOTAL',
                'pv_codigo' => '—',
                'descripcion_pc' => 'Total salón (suma PCs, sin post-cierre)',
                'pv_cae' => '',
                'pv_caea' => '',
                'ventas_erp_cae' => $totalesSalon['ventas_erp_cae'],
                'ventas_erp_caea' => $totalesSalon['ventas_erp_caea'],
                'ventas_erp' => $totalesSalon['ventas_erp'],
                'ventas_anita_cae' => $totalesSalon['ventas_anita_cae'],
                'ventas_anita_caea' => $totalesSalon['ventas_anita_caea'],
                'ventas_anita' => $totalesSalon['ventas_anita'],
                'rendgastro_z' => $jornadaAbierta ? null : $totalesSalon['rendgastro_z'],
                'diff_erp_anita' => $totalesSalon['diff_erp_anita'],
                'diff_erp_rendg' => $totalesSalon['diff_erp_rendg'],
                'cantidad_facturas_erp' => (int) $totalesSalon['cantidad_facturas_erp'],
                'jornada_abierta' => $jornadaAbierta,
                'es_total' => true,
            ], $tolerancia);
        }

        $postCierre = $this->postCierreCaeaSupport->filaReporte($empresaId, $fechaJornada, $tolerancia, $indiceAnitaBulk);
        $tienePostCierre = (int) ($postCierre['cantidad_facturas_erp'] ?? 0) > 0
            || (float) ($postCierre['ventas_erp'] ?? 0) > $tolerancia;

        $agregados = $this->rebiscoAgregadosSupport->filaReporte($empresaId, $fechaJornada, $tolerancia, $indiceAnitaBulk);
        $tieneAgregados = (int) ($agregados['cantidad_facturas_erp'] ?? 0) > 0
            || (float) ($agregados['ventas_erp'] ?? 0) > $tolerancia;

        $vending = $this->vendingRendgSupport->filasReporte($empresaId, $fechaJornada, $tolerancia, $jornadaAbierta);
        $filasVending = $vending['filas'];
        $totalesVending = $vending['totales'];
        $tieneVending = (float) ($totalesVending['ventas_erp'] ?? 0) > $tolerancia
            || (float) ($totalesVending['rendgastro_z'] ?? 0) > $tolerancia;

        if ($tienePostCierre) {
            $filasTotales[] = $postCierre;
        }
        if ($tieneAgregados) {
            $filasTotales[] = $agregados;
        }
        if ($filasPc !== []) {
            $filasTotales[] = $this->armarFilaTotalGastro(
                $totalesSalon,
                $postCierre,
                $agregados,
                $jornadaAbierta,
                $tolerancia,
                $algunaPcSinRendg,
                $tienePostCierre,
                $tieneAgregados,
            );
        }

        return [
            'filas_pc' => $filasPc,
            'totales_salon' => $totalesSalon,
            'filas_totales' => $filasTotales,
            'post_cierre' => $postCierre,
            'agregados_caea' => $agregados,
            'vending' => $vending,
            'jornada_abierta' => $jornadaAbierta,
        ];
    }

    /**
     * Total gastronomía salón (PCs + post-cierre + agregados CAEA). Sin vending ni estacionamiento.
     *
     * @param  array<string, float|null>  $totalesSalon
     * @param  array<string, mixed>  $postCierre
     * @param  array<string, mixed>  $agregados
     * @return array<string, mixed>
     */
    private function armarFilaTotalGastro(
        array $totalesSalon,
        array $postCierre,
        array $agregados,
        bool $jornadaAbierta,
        float $tolerancia,
        bool $algunaPcSinRendg,
        bool $incluyePostCierre,
        bool $incluyeAgregados,
    ): array {
        $erpPost = $incluyePostCierre ? (float) ($postCierre['ventas_erp'] ?? 0) : 0.0;
        $erpAgreg = $incluyeAgregados ? (float) ($agregados['ventas_erp'] ?? 0) : 0.0;
        $anitaPost = $incluyePostCierre ? (float) ($postCierre['ventas_anita'] ?? 0) : 0.0;
        $anitaAgreg = $incluyeAgregados ? (float) ($agregados['ventas_anita'] ?? 0) : 0.0;
        $erpTotal = round((float) ($totalesSalon['ventas_erp'] ?? 0) + $erpPost + $erpAgreg, 2);
        $anitaTotal = round((float) ($totalesSalon['ventas_anita'] ?? 0) + $anitaPost + $anitaAgreg, 2);
        $rendgTotal = $jornadaAbierta
            ? null
            : round(
                (float) ($totalesSalon['rendgastro_z'] ?? 0)
                + ($incluyePostCierre ? (float) ($postCierre['rendgastro_z'] ?? 0) : 0.0)
                + ($incluyeAgregados ? (float) ($agregados['rendgastro_z'] ?? 0) : 0.0),
                2,
            );
        $diffErpAnita = round($erpTotal - $anitaTotal, 2);
        $diffErpRendg = $jornadaAbierta ? null : round($erpTotal - (float) $rendgTotal, 2);

        return GastronomiaConciliacionEstadoSupport::aplicarEstadosEnFila([
            'tipo_fila' => 'total_gastro',
            'circuito' => 'GASTRO',
            'identificador_pc' => 'TOTAL-GASTRO',
            'tipo_pv' => 'TOTAL',
            'pv_codigo' => '—',
            'descripcion_pc' => 'Total gastronomía salón (PCs + post-cierre CAEA + agregados)',
            'pv_cae' => '',
            'pv_caea' => (string) ($postCierre['pv_caea'] ?? $agregados['pv_caea'] ?? '—'),
            'ventas_erp_cae' => (float) ($totalesSalon['ventas_erp_cae'] ?? 0),
            'ventas_erp_caea' => round(
                (float) ($totalesSalon['ventas_erp_caea'] ?? 0) + $erpPost + $erpAgreg,
                2,
            ),
            'ventas_erp' => $erpTotal,
            'ventas_anita_cae' => (float) ($totalesSalon['ventas_anita_cae'] ?? 0),
            'ventas_anita_caea' => round(
                (float) ($totalesSalon['ventas_anita_caea'] ?? 0) + $anitaPost + $anitaAgreg,
                2,
            ),
            'ventas_anita' => $anitaTotal,
            'rendgastro_z' => $rendgTotal,
            'diff_erp_anita' => $diffErpAnita,
            'diff_erp_rendg' => $diffErpRendg,
            'cantidad_facturas_erp' => (int) ($totalesSalon['cantidad_facturas_erp'] ?? 0)
                + ($incluyePostCierre ? (int) ($postCierre['cantidad_facturas_erp'] ?? 0) : 0)
                + ($incluyeAgregados ? (int) ($agregados['cantidad_facturas_erp'] ?? 0) : 0),
            'jornada_abierta' => $jornadaAbierta,
            'es_total' => true,
            'alguna_pc_sin_rendg' => $algunaPcSinRendg,
        ], $tolerancia);
    }

    /**
     * @param  array<string, float|null>  $totalesSalon
     * @param  array<string, mixed>  $postCierre
     * @param  array<string, mixed>  $agregados
     * @param  array{rendgastro_z: float, ventas_erp?: float, cantidad: int}  $totalesVending
     * @return array<string, mixed>
     * @deprecated Usar armarFilaTotalGastro; conservado para referencias legacy.
     */
    private function armarFilaTotalDia(
        array $totalesSalon,
        array $postCierre,
        array $agregados,
        array $totalesVending,
        bool $jornadaAbierta,
        float $tolerancia,
        bool $algunaPcSinRendg,
        bool $incluyePostCierre,
        bool $incluyeAgregados,
        bool $incluyeVending,
    ): array {
        $erpPost = $incluyePostCierre ? (float) ($postCierre['ventas_erp'] ?? 0) : 0.0;
        $erpAgreg = $incluyeAgregados ? (float) ($agregados['ventas_erp'] ?? 0) : 0.0;
        $anitaPost = $incluyePostCierre ? (float) ($postCierre['ventas_anita'] ?? 0) : 0.0;
        $anitaAgreg = $incluyeAgregados ? (float) ($agregados['ventas_anita'] ?? 0) : 0.0;
        $rendgVending = $incluyeVending ? (float) ($totalesVending['rendgastro_z'] ?? 0) : 0.0;
        $erpTotal = round((float) ($totalesSalon['ventas_erp'] ?? 0) + $erpPost + $erpAgreg, 2);
        $anitaTotal = round((float) ($totalesSalon['ventas_anita'] ?? 0) + $anitaPost + $anitaAgreg, 2);
        $rendgTotal = $jornadaAbierta
            ? null
            : round(
                (float) ($totalesSalon['rendgastro_z'] ?? 0)
                + ($incluyePostCierre ? (float) ($postCierre['rendgastro_z'] ?? 0) : 0.0)
                + ($incluyeAgregados ? (float) ($agregados['rendgastro_z'] ?? 0) : 0.0)
                + $rendgVending,
                2,
            );
        $diffErpAnita = round($erpTotal - $anitaTotal, 2);
        $diffErpRendg = $jornadaAbierta ? null : round($erpTotal - (float) $rendgTotal, 2);
        $diffErpRendgGastro = $diffErpRendg !== null && $incluyeVending
            ? round($diffErpRendg + $rendgVending, 2)
            : $diffErpRendg;

        return GastronomiaConciliacionEstadoSupport::aplicarEstadosEnFila([
            'tipo_fila' => 'total_dia',
            'identificador_pc' => 'TOTAL-DIA',
            'tipo_pv' => 'TOTAL',
            'pv_codigo' => '—',
            'descripcion_pc' => 'Total día (salón + vending + post-cierre CAEA + agregados)',
            'pv_cae' => '',
            'pv_caea' => (string) ($postCierre['pv_caea'] ?? $agregados['pv_caea'] ?? '—'),
            'ventas_erp_cae' => (float) ($totalesSalon['ventas_erp_cae'] ?? 0),
            'ventas_erp_caea' => round(
                (float) ($totalesSalon['ventas_erp_caea'] ?? 0) + $erpPost + $erpAgreg,
                2,
            ),
            'ventas_erp' => $erpTotal,
            'ventas_anita_cae' => (float) ($totalesSalon['ventas_anita_cae'] ?? 0),
            'ventas_anita_caea' => round(
                (float) ($totalesSalon['ventas_anita_caea'] ?? 0) + $anitaPost + $anitaAgreg,
                2,
            ),
            'ventas_anita' => $anitaTotal,
            'rendgastro_z' => $rendgTotal,
            'rendg_vending' => $incluyeVending ? $rendgVending : null,
            'diff_erp_anita' => $diffErpAnita,
            'diff_erp_rendg' => $diffErpRendgGastro,
            'cantidad_facturas_erp' => (int) ($totalesSalon['cantidad_facturas_erp'] ?? 0)
                + ($incluyePostCierre ? (int) ($postCierre['cantidad_facturas_erp'] ?? 0) : 0)
                + ($incluyeAgregados ? (int) ($agregados['cantidad_facturas_erp'] ?? 0) : 0),
            'jornada_abierta' => $jornadaAbierta,
            'es_total' => true,
            'alguna_pc_sin_rendg' => $algunaPcSinRendg,
        ], $tolerancia);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function filasDiaPorPc(
        int $empresaId,
        string $fechaJornada,
        float $tolerancia,
        bool $jornadaAbierta,
        ?array $indiceAnitaBulk = null,
    ): array {
        $terminales = ConfiguracionPuntoventaGastronomia::query()
            ->with(['puntoventaCae', 'puntoventaCaea'])
            ->where('empresa_id', $empresaId)
            ->orderBy('identificador_pc')
            ->get();

        $filas = [];
        $cacheCabecerasAnita = [];
        $clavesPorPv = $this->exclusionEmisionSupport->clavesExcluirPorPuntoventa(
            $empresaId,
            $fechaJornada,
            $indiceAnitaBulk,
        );

        foreach ($terminales as $cfg) {
            $cae = $cfg->puntoventaCae;
            if ($cae === null) {
                continue;
            }

            if ($this->esTerminalEstacionamiento($cae)) {
                continue;
            }

            $caea = $cfg->puntoventaCaea;
            $caeId = (int) $cae->id;
            $caeaId = $caea !== null ? (int) $caea->id : 0;
            $pc = trim((string) $cfg->identificador_pc);

            $erp = $this->facturacionBrutaErpPorPcSplitPv(
                $pc,
                $empresaId,
                $fechaJornada,
                $caeId,
                $caeaId,
            );

            if ($erp['erp_total'] <= $tolerancia && $erp['cant_facturas'] === 0) {
                continue;
            }

            $anitaCae = $this->chequeoVentasService->totalFacturacionBrutaAnitaParaVentasIds(
                $erp['venta_ids_cae'],
                $fechaJornada,
                $cacheCabecerasAnita,
                $clavesPorPv[$caeId] ?? [],
                $indiceAnitaBulk,
            );
            $anitaCaea = $caeaId > 0
                ? $this->chequeoVentasService->totalFacturacionBrutaAnitaParaVentasIds(
                    $erp['venta_ids_caea'],
                    $fechaJornada,
                    $cacheCabecerasAnita,
                    $clavesPorPv[$caeaId] ?? [],
                    $indiceAnitaBulk,
                )
                : 0.0;
            $anitaTotal = round($anitaCae + $anitaCaea, 2);

            $rendgastro = $jornadaAbierta
                ? null
                : $this->rendgastroTotalPorPc(
                    $empresaId,
                    $fechaJornada,
                    $pc,
                    $erp['erp_cae'],
                    $erp['erp_caea'],
                    $tolerancia,
                );

            $rendgastroZ = $rendgastro !== null ? ($rendgastro['total'] ?? null) : null;

            $fechaEntera = (int) str_replace('-', '', $fechaJornada);
            $ncErp = $this->notasCreditoErpPorPc(
                $pc,
                $empresaId,
                $fechaJornada,
                $caeId,
                $caeaId,
            );
            $ncRendg = $jornadaAbierta
                ? 0.0
                : $this->rendgastroSupport->ncPorHost($empresaId, $fechaEntera, $pc);

            $diffErpAnita = round($erp['erp_total'] - $anitaTotal, 2);

            $fila = [
                'identificador_pc' => $pc,
                'descripcion_pc' => trim((string) ($cfg->descripcion ?? '')),
                'pv_cae' => (string) $cae->codigo,
                'pv_caea' => $caea !== null ? (string) $caea->codigo : '—',
                'ventas_erp_cae' => $erp['erp_cae'],
                'ventas_erp_caea' => $erp['erp_caea'],
                'ventas_erp' => $erp['erp_total'],
                'ventas_anita_cae' => $anitaCae,
                'ventas_anita_caea' => $anitaCaea,
                'ventas_anita' => $anitaTotal,
                'rendgastro_z' => $rendgastroZ,
                'rendgastro_z_cae' => ($rendgastro ?? [])['z_portadora'] ?? null,
                'rendgastro_caea' => ($rendgastro ?? [])['caea_neto'] ?? null,
                'rendgastro_suc_caea' => ($rendgastro ?? [])['suc_caea'] ?? null,
                'diff_erp_anita' => $diffErpAnita,
                'cantidad_facturas_erp_cae' => $erp['cant_cae'],
                'cantidad_facturas_erp_caea' => $erp['cant_caea'],
                'cantidad_facturas_erp' => $erp['cant_facturas'],
                'jornada_abierta' => $jornadaAbierta,
            ];

            $fila = GastronomiaConciliacionNetoSupport::enriquecerFila($fila, $ncErp, $ncRendg);

            $filas[] = GastronomiaConciliacionEstadoSupport::aplicarEstadosEnFila($fila, $tolerancia);
        }

        usort($filas, fn (array $a, array $b): int => strcmp((string) $a['identificador_pc'], (string) $b['identificador_pc']));

        return $filas;
    }

    /**
     * Expande filas por PC en detalle auditoría: PV CAE, PV CAEA (salón) y total PC vs rendgastro.
     *
     * @param  list<array<string, mixed>>  $filasPc
     * @return list<array<string, mixed>>
     */
    public function expandirFilasAuditoria(array $filasPc, float $tolerancia = 0.02): array
    {
        $out = [];

        foreach ($filasPc as $pc) {
            $identificadorPc = (string) ($pc['identificador_pc'] ?? '');
            $pvCae = (string) ($pc['pv_cae'] ?? '');
            $pvCaea = (string) ($pc['pv_caea'] ?? '—');

            $out[] = [
                'tipo_fila' => 'pv_cae',
                'identificador_pc' => $identificadorPc,
                'tipo_pv' => 'CAE',
                'pv_codigo' => $pvCae,
                'pv_cae' => $pvCae,
                'pv_caea' => $pvCaea,
                'ventas_erp' => (float) ($pc['ventas_erp_cae'] ?? 0),
                'ventas_anita' => (float) ($pc['ventas_anita_cae'] ?? 0),
                'ventas_erp_cae' => (float) ($pc['ventas_erp_cae'] ?? 0),
                'ventas_erp_caea' => 0.0,
                'ventas_anita_cae' => (float) ($pc['ventas_anita_cae'] ?? 0),
                'ventas_anita_caea' => 0.0,
                'rendgastro_z' => null,
                'rendgastro_z_cae' => null,
                'rendgastro_caea' => null,
                'diff_erp_anita' => round((float) ($pc['ventas_erp_cae'] ?? 0) - (float) ($pc['ventas_anita_cae'] ?? 0), 2),
                'diff_erp_rendg' => null,
                'estado' => $this->resolverEstadoSoloAnita(
                    (float) ($pc['ventas_erp_cae'] ?? 0),
                    (float) ($pc['ventas_anita_cae'] ?? 0),
                    $tolerancia,
                ),
                'cantidad_facturas_erp' => (int) ($pc['cantidad_facturas_erp_cae'] ?? 0),
                'jornada_abierta' => (bool) ($pc['jornada_abierta'] ?? false),
            ];

            if ($pvCaea !== '—') {
                $out[] = [
                    'tipo_fila' => 'pv_caea',
                    'identificador_pc' => $identificadorPc,
                    'tipo_pv' => 'CAEA',
                    'pv_codigo' => $pvCaea,
                    'pv_cae' => $pvCae,
                    'pv_caea' => $pvCaea,
                    'ventas_erp' => (float) ($pc['ventas_erp_caea'] ?? 0),
                    'ventas_anita' => (float) ($pc['ventas_anita_caea'] ?? 0),
                    'ventas_erp_cae' => 0.0,
                    'ventas_erp_caea' => (float) ($pc['ventas_erp_caea'] ?? 0),
                    'ventas_anita_cae' => 0.0,
                    'ventas_anita_caea' => (float) ($pc['ventas_anita_caea'] ?? 0),
                    'rendgastro_z' => null,
                    'rendgastro_z_cae' => null,
                    'rendgastro_caea' => ($pc['rendgastro_caea'] ?? null),
                    'diff_erp_anita' => round((float) ($pc['ventas_erp_caea'] ?? 0) - (float) ($pc['ventas_anita_caea'] ?? 0), 2),
                    'diff_erp_rendg' => null,
                    'estado' => $this->resolverEstadoSoloAnita(
                        (float) ($pc['ventas_erp_caea'] ?? 0),
                        (float) ($pc['ventas_anita_caea'] ?? 0),
                        $tolerancia,
                    ),
                    'cantidad_facturas_erp' => (int) ($pc['cantidad_facturas_erp_caea'] ?? 0),
                    'jornada_abierta' => (bool) ($pc['jornada_abierta'] ?? false),
                ];
            }

            $out[] = array_merge($pc, [
                'tipo_fila' => 'pc_total',
                'tipo_pv' => 'PC_TOTAL',
                'pv_codigo' => $pvCae.'+'.$pvCaea,
                'es_pc_total' => true,
                'descripcion_pc' => 'Total PC vs rendgastro Z portadora',
            ]);
        }

        return $out;
    }

    private function resolverEstadoSoloAnita(float $erp, float $anita, float $tolerancia): string
    {
        if ($erp <= $tolerancia && $anita <= $tolerancia) {
            return '—';
        }

        return abs($erp - $anita) <= $tolerancia ? 'OK' : 'DIF';
    }

    /**
     * @return array{
     *   erp_cae: float,
     *   erp_caea: float,
     *   erp_total: float,
     *   cant_cae: int,
     *   cant_caea: int,
     *   cant_facturas: int,
     *   venta_ids_cae: list<int>,
     *   venta_ids_caea: list<int>
     * }
     */
    public function facturacionBrutaErpPorPcSplitPv(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        int $puntoventaCaeId,
        int $puntoventaCaeaId,
    ): array {
        $emisiones = VentaGastronomiaEmision::query()
            ->where('identificador_pc', $identificadorPc)
            ->whereHas('venta', function ($v) use ($empresaId, $fechaJornada) {
                $v->where(function ($fecha) use ($fechaJornada) {
                    $fecha->whereDate('fechajornada', $fechaJornada)
                        ->orWhere(function ($legacy) use ($fechaJornada) {
                            $legacy->whereNull('fechajornada')
                                ->whereDate('fecha', $fechaJornada);
                        });
                })->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
            })
            ->with('venta:id,puntoventa_id,total')
            ->get();

        $erpCae = 0.0;
        $erpCaea = 0.0;
        $cntCae = 0;
        $cntCaea = 0;
        $idsCae = [];
        $idsCaea = [];

        foreach ($emisiones as $em) {
            if (($em->venta_factura_origen_id ?? null) !== null) {
                continue;
            }
            $venta = $em->venta;
            if ($venta === null) {
                continue;
            }

            $pvId = (int) ($venta->puntoventa_id ?? 0);
            $monto = round((float) ($venta->total ?? 0), 2);
            $ventaId = (int) $venta->id;

            if ($pvId === $puntoventaCaeId) {
                $erpCae += $monto;
                $cntCae++;
                $idsCae[] = $ventaId;
            } elseif ($puntoventaCaeaId > 0 && $pvId === $puntoventaCaeaId) {
                $erpCaea += $monto;
                $cntCaea++;
                $idsCaea[] = $ventaId;
            }
        }

        return [
            'erp_cae' => round($erpCae, 2),
            'erp_caea' => round($erpCaea, 2),
            'erp_total' => round($erpCae + $erpCaea, 2),
            'cant_cae' => $cntCae,
            'cant_caea' => $cntCaea,
            'cant_facturas' => $cntCae + $cntCaea,
            'venta_ids_cae' => $idsCae,
            'venta_ids_caea' => $idsCaea,
        ];
    }

    /**
     * NC gastronomía del día por PC (valor positivo).
     */
    public function notasCreditoErpPorPc(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        int $puntoventaCaeId,
        int $puntoventaCaeaId,
    ): float {
        $emisiones = VentaGastronomiaEmision::query()
            ->where('identificador_pc', $identificadorPc)
            ->whereNotNull('venta_factura_origen_id')
            ->whereHas('venta', function ($v) use ($empresaId, $fechaJornada) {
                $v->where(function ($fecha) use ($fechaJornada) {
                    $fecha->whereDate('fechajornada', $fechaJornada)
                        ->orWhere(function ($legacy) use ($fechaJornada) {
                            $legacy->whereNull('fechajornada')
                                ->whereDate('fecha', $fechaJornada);
                        });
                })->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
            })
            ->with('venta:id,puntoventa_id,total')
            ->get();

        $nc = 0.0;
        foreach ($emisiones as $em) {
            $venta = $em->venta;
            if ($venta === null) {
                continue;
            }
            $pvId = (int) ($venta->puntoventa_id ?? 0);
            if ($pvId !== $puntoventaCaeId && ($puntoventaCaeaId <= 0 || $pvId !== $puntoventaCaeaId)) {
                continue;
            }
            $nc += abs(round((float) ($venta->total ?? 0), 2));
        }

        return round($nc, 2);
    }

    /**
     * @return array{
     *   total: float|null,
     *   z_portadora: float|null,
     *   caea_neto: float|null,
     *   suc_caea: int|null
     * }
     */
    private function rendgastroTotalPorPc(
        int $empresaId,
        string $fechaJornada,
        string $identificadorPc,
        float $erpCae,
        float $erpCaea,
        float $tolerancia,
    ): array {
        $fechaEntera = (int) str_replace('-', '', $fechaJornada);

        $porHost = $this->rendgastroSupport->totalBrutoPorHost(
            $empresaId,
            $fechaEntera,
            $identificadorPc,
            $erpCae,
            $erpCaea,
            $tolerancia,
        );

        if ($porHost['total'] !== null) {
            return [
                'total' => $porHost['total'],
                'z_portadora' => $porHost['z_portadora'],
                'caea_neto' => $porHost['caea_neto'],
                'suc_caea' => $porHost['suc_caea'],
            ];
        }

        return [
            'total' => null,
            'z_portadora' => null,
            'caea_neto' => null,
            'suc_caea' => null,
        ];
    }

    private function esTerminalEstacionamiento(Puntoventa $pvCae): bool
    {
        $codigos = config('rendicion_gastronomia_anita.auditoria_diaria.puntoventa_codigos_solo_anita', []);
        if (in_array(trim((string) $pvCae->codigo), $codigos, true)) {
            return true;
        }

        $nombre = mb_strtolower(trim((string) $pvCae->nombre));

        return str_contains($nombre, 'estacionamiento')
            || str_contains($nombre, 'estac.');
    }
}
