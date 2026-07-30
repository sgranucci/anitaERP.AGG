<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\CuentaGastronomiaLinea;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionGastroTotalDiaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionPostCierreCaeaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionRendgAsientosDiaSupport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auditoría integridad circuitos gastronomía ↔ estacionamiento, asientos Waitry y rendgastro.
 */
final class GastronomiaCircuitosFacturacionIntegridadService
{
    public function __construct(
        private readonly GastronomiaConciliacionRendgAsientosDiaSupport $rendgAsientosSupport,
        private readonly GastronomiaConciliacionGastroTotalDiaSupport $gastroTotalDiaSupport,
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
        private readonly GastronomiaConciliacionPostCierreCaeaSupport $postCierreSupport,
    ) {
    }

    /**
     * Ventas con emisión en ambos circuitos (etiquetado gastro erróneo sobre estacionamiento).
     *
     * @return list<array<string, mixed>>
     */
    public function listarEtiquetadoDualErroneo(int $empresaId, string $fechaDesde, ?string $fechaHasta = null): array
    {
        $fechaHasta = $fechaHasta ?? $fechaDesde;

        $rows = DB::table('venta as v')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('venta_estacionamiento_emision as vee', 'vee.venta_id', '=', 'v.id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->where('pv.empresa_id', $empresaId)
            ->whereBetween(DB::raw('DATE(COALESCE(v.fechajornada, v.fecha))'), [$fechaDesde, $fechaHasta])
            ->orderBy('v.fechajornada')
            ->orderBy('v.id')
            ->get([
                'v.id',
                'v.codigo',
                'v.total',
                'v.fechajornada',
                'v.leyenda',
                'vge.identificador_pc as pc_gastro',
                'vge.created_at as gastro_emision_at',
                'vge.cuenta_gastronomia_id',
                'vee.identificador_pc as pc_estac',
                'vee.created_at as estac_emision_at',
            ]);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'venta_id' => (int) $row->id,
                'codigo' => (string) $row->codigo,
                'total' => round((float) $row->total, 2),
                'fecha_jornada' => (string) ($row->fechajornada ?? ''),
                'leyenda' => (string) ($row->leyenda ?? ''),
                'pc_gastro' => (string) ($row->pc_gastro ?? ''),
                'pc_estac' => (string) ($row->pc_estac ?? ''),
                'gastro_emision_at' => (string) ($row->gastro_emision_at ?? ''),
                'estac_emision_at' => (string) ($row->estac_emision_at ?? ''),
                'cuenta_gastronomia_id' => (int) ($row->cuenta_gastronomia_id ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Quita emisión gastronomía errónea en ventas que pertenecen al circuito estacionamiento.
     *
     * @return array{corregidas: int, venta_ids: list<int>, errores: list<string>}
     */
    public function corregirEtiquetadoDualErroneo(int $empresaId, string $fechaDesde, ?string $fechaHasta = null, bool $dryRun = false): array
    {
        $filas = $this->listarEtiquetadoDualErroneo($empresaId, $fechaDesde, $fechaHasta);
        $corregidas = 0;
        $ventaIds = [];
        $errores = [];

        foreach ($filas as $fila) {
            $ventaId = (int) ($fila['venta_id'] ?? 0);
            if ($ventaId <= 0) {
                continue;
            }

            if ($dryRun) {
                $corregidas++;
                $ventaIds[] = $ventaId;

                continue;
            }

            try {
                DB::transaction(function () use ($ventaId, $fila): void {
                    $emision = VentaGastronomiaEmision::query()->find($ventaId);
                    if ($emision === null) {
                        return;
                    }

                    $cuentaId = (int) ($emision->cuenta_gastronomia_id ?? 0);
                    $emision->delete();

                    if ($cuentaId > 0) {
                        $otras = VentaGastronomiaEmision::query()
                            ->where('cuenta_gastronomia_id', $cuentaId)
                            ->exists();
                        if (! $otras) {
                            CuentaGastronomiaLinea::query()
                                ->where('cuenta_gastronomia_id', $cuentaId)
                                ->delete();
                            CuentaGastronomia::query()->whereKey($cuentaId)->delete();
                        }
                    }

                    Log::info('gastronomia.integridad.quitar_emision_gastro_erronea', [
                        'venta_id' => $ventaId,
                        'codigo' => $fila['codigo'] ?? null,
                        'pc_estac' => $fila['pc_estac'] ?? null,
                        'pc_gastro' => $fila['pc_gastro'] ?? null,
                    ]);
                });
                $corregidas++;
                $ventaIds[] = $ventaId;
            } catch (\Throwable $e) {
                $errores[] = 'Venta '.$ventaId.': '.$e->getMessage();
            }
        }

        return [
            'corregidas' => $corregidas,
            'venta_ids' => $ventaIds,
            'errores' => $errores,
        ];
    }

    /**
     * Control rendg ↔ asientos Waitry por jornada (solo días con jornada cerrada).
     *
     * @return list<array<string, mixed>>
     */
    public function auditarAsientosVsRendgPorRango(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        float $tolerancia = 0.02,
    ): array {
        $resultados = [];

        foreach (CarbonPeriod::create($fechaDesde, $fechaHasta) as $dia) {
            $fecha = $dia->toDateString();
            if ($this->esJornadaPreMigracion($empresaId, $fecha)) {
                continue;
            }

            $jornada = JornadaGastronomia::query()
                ->where('empresa_id', $empresaId)
                ->whereDate('fecha_jornada', $fecha)
                ->orderByDesc('id')
                ->first(['id', 'estado', 'cierre_en']);

            if ($jornada === null || (string) ($jornada->estado ?? '') !== 'cerrada') {
                $resultados[] = [
                    'fecha_jornada' => $fecha,
                    'estado' => 'jornada_abierta_o_inexistente',
                    'rendg_total' => null,
                    'asientos_total' => null,
                    'diff_rendg_asientos' => null,
                ];

                continue;
            }

            $gastroNeto = $this->gastroTotalDiaSupport->totalesDiaEmpresa($empresaId, $fecha)['neto'];
            $asientos = $this->rendgAsientosSupport->auditarAsientosFacturacionJornada($empresaId, $fecha);
            $asientosTotal = (float) ($asientos['total'] ?? 0);
            $tieneAsientos = (int) ($asientos['cantidad'] ?? 0) > 0;

            $fechaEntera = (int) Carbon::parse($fecha)->format('Ymd');
            $rendgSalon = $this->sumarRendgSalonEmpresa($empresaId, $fechaEntera);
            $post = $this->postCierreSupport->totalesDia($empresaId, $fecha);
            $rendgPost = round((float) ($post['rendgastro_z'] ?? 0), 2);
            $rendgTotal = round($rendgSalon + $rendgPost, 2);

            $diffRendgAsientos = $tieneAsientos ? round($rendgTotal - $asientosTotal, 2) : null;
            $diffErpRendg = round($gastroNeto - $rendgTotal, 2);

            $resultados[] = [
                'fecha_jornada' => $fecha,
                'estado' => $this->resolverEstadoDia($diffRendgAsientos, $diffErpRendg, $tieneAsientos, $tolerancia),
                'erp_gastro_neto' => round($gastroNeto, 2),
                'rendg_total' => $rendgTotal,
                'rendg_salon' => round($rendgSalon, 2),
                'rendg_post_cierre' => $rendgPost,
                'asientos_total' => round($asientosTotal, 2),
                'asientos_factura_dia' => (float) ($asientos['factura_dia'] ?? 0),
                'asientos_post_cierre' => (float) ($asientos['post_cierre'] ?? 0),
                'diff_rendg_asientos' => $diffRendgAsientos,
                'diff_erp_rendg' => $diffErpRendg,
                'asientos_cantidad' => (int) ($asientos['cantidad'] ?? 0),
                'dual_tag' => count($this->listarEtiquetadoDualErroneo($empresaId, $fecha, $fecha)),
            ];
        }

        return $resultados;
    }

    private function esJornadaPreMigracion(int $empresaId, string $fechaJornada): bool
    {
        $map = config('gastronomia.conciliacion_diaria_reporte.fecha_jornada_desde_por_empresa', []);
        $desde = trim((string) ($map[(string) $empresaId] ?? $map[$empresaId] ?? ''));

        return $desde !== '' && $fechaJornada < $desde;
    }

    private function sumarRendgSalonEmpresa(int $empresaId, int $fechaEntera): float
    {
        $filas = $this->rendgastroSupport->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera);

        $total = 0.0;
        foreach ($filas as $fila) {
            if ($this->rendgastroSupport->esCabeceraEstacionamiento($fila, $empresaId)) {
                continue;
            }
            $host = mb_strtoupper(trim((string) ($fila->rendg_host ?? '')));
            if ($host === 'CIERRE-WAITRY') {
                continue;
            }
            $total += (float) ($fila->rendg_total_z ?? 0);
        }

        return round($total, 2);
    }

    private function resolverEstadoDia(?float $diffRendgAsientos, float $diffErpRendg, bool $tieneAsientos, float $tolerancia): string
    {
        if ($tieneAsientos && $diffRendgAsientos !== null && abs($diffRendgAsientos) > $tolerancia) {
            return 'DIF_rendg_asientos';
        }

        if (abs($diffErpRendg) > $tolerancia) {
            return 'DIF_erp_rendg';
        }

        return 'OK';
    }
}
