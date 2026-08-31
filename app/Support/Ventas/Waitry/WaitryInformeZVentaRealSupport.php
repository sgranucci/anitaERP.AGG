<?php

namespace App\Support\Ventas\Waitry;

use Illuminate\Support\Facades\DB;

/**
 * Contrasta el recomputo del proceso contra venta real (Waitry del cierre + facturas ERP).
 *
 * A las 07:45 el asiento todavía no está: no se usa como confirmación.
 * Después de grabar asientos, {@see $mpContabilizado} (MP/QR tótem) sí confirma:
 * si el recomputo = lo contabilizado, se alinea el Z aunque el Waitry persistido
 * se haya congelado antes de la última comanda de la jornada.
 */
final class WaitryInformeZVentaRealSupport
{
    /**
     * @return array{
     *   decision: string,
     *   venta_waitry: float,
     *   venta_erp: float,
     *   venta_real: float,
     *   z_mp_actual: float,
     *   recomputado: float,
     *   mp_contabilizado: float|null
     * }
     */
    public static function decidirRegeneracion(
        float $recomputado,
        float $ventaWaitry,
        float $ventaErp,
        float $zMpActual,
        float $tolerancia = 0.02,
        ?float $mpContabilizado = null,
    ): array {
        $tol = max(0.0, $tolerancia);
        $recomputado = round($recomputado, 2);
        $ventaWaitry = round($ventaWaitry, 2);
        $ventaErp = round($ventaErp, 2);
        $zMpActual = round($zMpActual, 2);
        $mpContab = $mpContabilizado !== null ? round($mpContabilizado, 2) : null;
        $ventaReal = round(max($ventaWaitry, $zMpActual), 2);

        $base = [
            'venta_waitry' => $ventaWaitry,
            'venta_erp' => $ventaErp,
            'venta_real' => $ventaReal,
            'z_mp_actual' => $zMpActual,
            'recomputado' => $recomputado,
            'mp_contabilizado' => $mpContab,
        ];

        $hayVentaReal = $ventaWaitry > $tol || $ventaErp > $tol || $zMpActual > $tol;

        if ($recomputado <= $tol && $hayVentaReal) {
            return $base + ['decision' => 'omitido_recomputo_cero'];
        }

        if ($mpContab !== null && $mpContab > $tol && abs($recomputado - $mpContab) <= $tol) {
            if (abs($recomputado - $zMpActual) <= $tol) {
                return $base + ['decision' => 'ok'];
            }

            return $base + ['decision' => 'regenerar'];
        }

        if (abs($recomputado - $ventaWaitry) <= $tol && $recomputado > $tol) {
            if (abs($recomputado - $zMpActual) <= $tol) {
                return $base + ['decision' => 'ok'];
            }

            return $base + ['decision' => 'regenerar'];
        }

        if (abs($recomputado - $zMpActual) <= $tol) {
            return $base + ['decision' => 'ok'];
        }

        return $base + ['decision' => 'revisar_venta'];
    }

    /**
     * El asiento TOTEM usa el total de la factura ERP, no el cobro Waitry.
     * El Informe Z, al regenerarse desde el proceso, tiene que usar el mismo importe
     * para no desalinearse de ventas / asientos / medios.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    public static function aplicarImporteErpALineasInformeZ(array $lineas): array
    {
        $ids = [];
        foreach ($lineas as $ln) {
            $id = (int) ($ln['venta_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return $lineas;
        }

        $totales = DB::table('venta')
            ->whereIn('id', array_values($ids))
            ->pluck('total', 'id')
            ->all();

        foreach ($lineas as $i => $ln) {
            $ventaId = (int) ($ln['venta_id'] ?? 0);
            if ($ventaId <= 0 || ! isset($totales[$ventaId])) {
                continue;
            }
            $erp = round((float) $totales[$ventaId], 2);
            if ($erp <= 0.0001) {
                continue;
            }
            $lineas[$i]['total'] = $erp;
            $lineas[$i]['monto_cobro_waitry'] = $erp;
        }

        return $lineas;
    }

    /**
     * Facturación ERP de la jornada (todas las facturas). A las 07:45 ya está: se emite durante el día.
     */
    public static function totalFacturadoErp(int $empresaId, string $fechaJornada): float
    {
        if ($empresaId <= 0 || $fechaJornada === '') {
            return 0.0;
        }

        $row = DB::table('venta_gastronomia_emision as vge')
            ->join('venta as v', 'vge.venta_id', '=', 'v.id')
            ->join('puntoventa as pv', 'v.puntoventa_id', '=', 'pv.id')
            ->where('pv.empresa_id', $empresaId)
            ->whereNull('pv.deleted_at')
            ->where(function ($q) use ($fechaJornada) {
                $q->whereDate('v.fechajornada', $fechaJornada)
                    ->orWhere(function ($legacy) use ($fechaJornada) {
                        $legacy->whereNull('v.fechajornada')
                            ->whereDate('v.fecha', $fechaJornada);
                    });
            })
            ->selectRaw('COALESCE(SUM(v.total), 0) as total')
            ->first();

        return round((float) ($row->total ?? 0), 2);
    }
}
