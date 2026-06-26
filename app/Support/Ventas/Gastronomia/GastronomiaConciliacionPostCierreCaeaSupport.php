<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaChequeoVentasAnitaErpService;
use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaFacturaProcesoEmisionService;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoRendicionAnitaSupport;

/**
 * Facturación en PV CAEA posterior al cierre de jornada (proceso Waitry / CF post-cierre).
 */
final class GastronomiaConciliacionPostCierreCaeaSupport
{
    public function __construct(
        private readonly GastronomiaChequeoVentasAnitaErpService $chequeoVentasService,
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
        private readonly GastronomiaConciliacionExclusionEmisionSupport $exclusionEmisionSupport,
    ) {
    }

    /**
     * @return array{
     *   pv_caea: string,
     *   ventas_erp: float,
     *   ventas_anita: float,
     *   rendgastro_z: float|null,
     *   rendgastro_x: float|null,
     *   rendgastro_nro_oper: int|null,
     *   cantidad_facturas: int,
     *   venta_ids: list<int>,
     *   jornada_cierre_en: string|null,
     *   jornada_id: int|null
     * }
     */
    public function totalesDia(int $empresaId, string $fechaJornada, ?array $indiceAnitaBulk = null): array
    {
        $pvCaea = $this->puntoventaCaeaEmpresa($empresaId);
        $pvCaeaCodigo = (string) ($pvCaea?->codigo ?? '—');
        $pvCaeaId = (int) ($pvCaea?->id ?? 0);

        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->orderByDesc('id')
            ->first(['id', 'cierre_en', 'estado']);

        if ($pvCaeaId <= 0 || $jornada === null || $jornada->cierre_en === null) {
            return $this->vacios($pvCaeaCodigo, $jornada);
        }

        $emisiones = VentaGastronomiaEmision::query()
            ->join('venta', 'venta.id', '=', 'venta_gastronomia_emision.venta_id')
            ->where('venta.puntoventa_id', $pvCaeaId)
            ->where(function ($fecha) use ($fechaJornada) {
                $fecha->whereDate('venta.fechajornada', $fechaJornada)
                    ->orWhere(function ($legacy) use ($fechaJornada) {
                        $legacy->whereNull('venta.fechajornada')
                            ->whereDate('venta.fecha', $fechaJornada);
                    });
            })
            ->where('venta.created_at', '>', $jornada->cierre_en)
            ->whereNull('venta_gastronomia_emision.venta_factura_origen_id')
            ->select([
                'venta_gastronomia_emision.venta_id',
                'venta.total',
            ])
            ->get();

        $ventaIds = [];
        $erpTotal = 0.0;
        foreach ($emisiones as $row) {
            $ventaIds[] = (int) $row->venta_id;
            $erpTotal += round((float) ($row->total ?? 0), 2);
        }

        $cacheCabeceras = [];
        $clavesExcluir = $this->exclusionEmisionSupport->clavesExcluirConciliacion(
            $empresaId,
            $fechaJornada,
            $indiceAnitaBulk,
        );
        $anitaTotal = $this->chequeoVentasService->totalFacturacionBrutaAnitaParaVentasIds(
            $ventaIds,
            $fechaJornada,
            $cacheCabeceras,
            $clavesExcluir,
            $indiceAnitaBulk,
        );

        $nroOperSnapshot = $this->nroOperSnapshotJornada((int) $jornada->id);
        $totalXSnapshot = $this->totalXSnapshotJornada((int) $jornada->id);
        $fechaEntera = (int) str_replace('-', '', $fechaJornada);
        $rendg = $this->rendgastroSupport->totalPostCierreWaitry(
            $empresaId,
            $fechaEntera,
            (int) $jornada->id,
            $nroOperSnapshot,
            $totalXSnapshot,
        );

        $rendgEnRendgastro = ($rendg['total'] ?? null) !== null;

        return [
            'pv_caea' => $pvCaeaCodigo,
            'ventas_erp' => round($erpTotal, 2),
            'ventas_anita' => round($anitaTotal, 2),
            'rendgastro_z' => $rendg['total'],
            'rendgastro_x' => $rendg['total_x'],
            'rendgastro_nro_oper' => $rendg['nro_oper'],
            'rendg_snapshot_x' => $rendg['snapshot_total_x'] ?? null,
            'rendg_snapshot_nro_oper' => $rendg['snapshot_nro_oper'] ?? null,
            'rendgastro_fuente' => $rendgEnRendgastro ? 'rendgastro' : null,
            'cantidad_facturas' => count($ventaIds),
            'venta_ids' => $ventaIds,
            'jornada_cierre_en' => $jornada->cierre_en?->format('Y-m-d H:i:s'),
            'jornada_id' => (int) $jornada->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filaReporte(
        int $empresaId,
        string $fechaJornada,
        float $tolerancia,
        ?array $indiceAnitaBulk = null,
    ): array {
        $totales = $this->totalesDia($empresaId, $fechaJornada, $indiceAnitaBulk);
        $diffErpAnita = round($totales['ventas_erp'] - $totales['ventas_anita'], 2);
        $rendgZ = $totales['rendgastro_z'];
        $diffErpRendg = $rendgZ !== null
            ? round($totales['ventas_erp'] - $rendgZ, 2)
            : null;

        $estado = '—';
        if ($totales['cantidad_facturas'] > 0) {
            $okAnita = abs($diffErpAnita) <= $tolerancia;
            if ($rendgZ === null) {
                $estado = 'SIN RENDG';
            } else {
                $okRendg = abs((float) $diffErpRendg) <= $tolerancia;
                $estado = ($okAnita && $okRendg) ? 'OK' : 'DIF';
            }
        }

        return [
            'identificador_pc' => CierreJornadaProcesoRendicionAnitaSupport::HOST,
            'tipo_fila' => 'post_cierre_caea',
            'tipo_pv' => 'CAEA_POST',
            'pv_codigo' => $totales['pv_caea'],
            'descripcion_pc' => 'Facturado post-cierre jornada (PV CAEA)',
            'pv_cae' => '—',
            'pv_caea' => $totales['pv_caea'],
            'ventas_erp_cae' => 0.0,
            'ventas_erp_caea' => $totales['ventas_erp'],
            'ventas_erp' => $totales['ventas_erp'],
            'ventas_anita_cae' => 0.0,
            'ventas_anita_caea' => $totales['ventas_anita'],
            'ventas_anita' => $totales['ventas_anita'],
            'rendgastro_z' => $rendgZ,
            'rendgastro_z_cae' => null,
            'rendgastro_caea' => $totales['rendgastro_x'],
            'rendgastro_suc_caea' => null,
            'rendgastro_nro_oper' => $totales['rendgastro_nro_oper'],
            'rendg_snapshot_x' => $totales['rendg_snapshot_x'] ?? null,
            'rendg_snapshot_nro_oper' => $totales['rendg_snapshot_nro_oper'] ?? null,
            'rendgastro_fuente' => $totales['rendgastro_fuente'] ?? null,
            'diff_erp_anita' => $diffErpAnita,
            'diff_erp_rendg' => $diffErpRendg,
            'estado' => $estado,
            'cantidad_facturas_erp' => $totales['cantidad_facturas'],
            'jornada_cierre_en' => $totales['jornada_cierre_en'],
            'es_post_cierre_caea' => true,
        ];
    }

    private function puntoventaCaeaEmpresa(int $empresaId): ?Puntoventa
    {
        $caeaId = ConfiguracionPuntoventaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('puntoventa_caea_id')
            ->value('puntoventa_caea_id');

        if ($caeaId === null || (int) $caeaId <= 0) {
            return null;
        }

        return Puntoventa::query()->find((int) $caeaId);
    }

    private function snapshotJornada(int $jornadaId): ?GastronomiaCierreJornadaProcesoSnapshot
    {
        return GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();
    }

    private function nroOperSnapshotJornada(int $jornadaId): ?int
    {
        $rend = $this->rendicionSnapshotJornada($jornadaId);
        $nro = (int) ($rend['nro_oper'] ?? 0);

        return $nro > 0 ? $nro : null;
    }

    private function totalXSnapshotJornada(int $jornadaId): ?float
    {
        $rend = $this->rendicionSnapshotJornada($jornadaId);
        if ($rend === null) {
            return null;
        }

        $totalX = round((float) ($rend['total_x'] ?? 0), 2);

        return $totalX > 0 ? $totalX : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rendicionSnapshotJornada(int $jornadaId): ?array
    {
        $snap = $this->snapshotJornada($jornadaId);
        if ($snap === null) {
            return null;
        }

        $rend = $snap->payload['rendicion_proceso_anita'] ?? null;

        return is_array($rend) ? $rend : null;
    }

    /**
     * @return array{
     *   pv_caea: string,
     *   ventas_erp: float,
     *   ventas_anita: float,
     *   rendgastro_z: float|null,
     *   rendgastro_x: float|null,
     *   rendgastro_nro_oper: int|null,
     *   cantidad_facturas: int,
     *   venta_ids: list<int>,
     *   jornada_cierre_en: string|null,
     *   jornada_id: int|null
     * }
     */
    private function vacios(string $pvCaeaCodigo, ?JornadaGastronomia $jornada): array
    {
        return [
            'pv_caea' => $pvCaeaCodigo,
            'ventas_erp' => 0.0,
            'ventas_anita' => 0.0,
            'rendgastro_z' => null,
            'rendgastro_x' => null,
            'rendgastro_nro_oper' => null,
            'cantidad_facturas' => 0,
            'venta_ids' => [],
            'jornada_cierre_en' => $jornada?->cierre_en?->format('Y-m-d H:i:s'),
            'jornada_id' => $jornada !== null ? (int) $jornada->id : null,
        ];
    }
}
