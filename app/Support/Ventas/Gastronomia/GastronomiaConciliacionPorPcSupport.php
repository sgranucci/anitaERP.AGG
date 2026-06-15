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
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function filasDiaPorPc(int $empresaId, string $fechaJornada, float $tolerancia, bool $jornadaAbierta): array
    {
        $terminales = ConfiguracionPuntoventaGastronomia::query()
            ->with(['puntoventaCae', 'puntoventaCaea'])
            ->where('empresa_id', $empresaId)
            ->orderBy('identificador_pc')
            ->get();

        $filas = [];
        $cacheCabecerasAnita = [];

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
            );
            $anitaCaea = $caeaId > 0
                ? $this->chequeoVentasService->totalFacturacionBrutaAnitaParaVentasIds(
                    $erp['venta_ids_caea'],
                    $fechaJornada,
                    $cacheCabecerasAnita,
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

            $diffErpAnita = round($erp['erp_total'] - $anitaTotal, 2);
            $diffErpRendg = $rendgastroZ !== null ? round($erp['erp_total'] - $rendgastroZ, 2) : null;

            $filas[] = [
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
                'diff_erp_rendg' => $diffErpRendg,
                'estado' => $this->resolverEstado($diffErpAnita, $diffErpRendg, $tolerancia),
                'cantidad_facturas_erp_cae' => $erp['cant_cae'],
                'cantidad_facturas_erp_caea' => $erp['cant_caea'],
                'cantidad_facturas_erp' => $erp['cant_facturas'],
                'jornada_abierta' => $jornadaAbierta,
            ];
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

    private function resolverEstado(float $diffErpAnita, ?float $diffErpRendg, float $tolerancia): string
    {
        $okAnita = abs($diffErpAnita) <= $tolerancia;
        $okRendg = $diffErpRendg === null || abs($diffErpRendg) <= $tolerancia;

        return ($okAnita && $okRendg) ? 'OK' : 'DIF';
    }
}
