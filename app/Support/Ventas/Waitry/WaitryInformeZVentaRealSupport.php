<?php

namespace App\Support\Ventas\Waitry;

use Illuminate\Support\Facades\DB;

/**
 * Contrasta el recomputo del proceso contra venta real (Waitry del cierre + facturas ERP).
 *
 * El asiento del proceso se graba ~09:00: no sirve como confirmación a las 07:45.
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
     *   recomputado: float
     * }
     */
    public static function decidirRegeneracion(
        float $recomputado,
        float $ventaWaitry,
        float $ventaErp,
        float $zMpActual,
        float $tolerancia = 0.02,
    ): array {
        $tol = max(0.0, $tolerancia);
        $recomputado = round($recomputado, 2);
        $ventaWaitry = round($ventaWaitry, 2);
        $ventaErp = round($ventaErp, 2);
        $zMpActual = round($zMpActual, 2);
        $ventaReal = round(max($ventaWaitry, $zMpActual), 2);

        $base = [
            'venta_waitry' => $ventaWaitry,
            'venta_erp' => $ventaErp,
            'venta_real' => $ventaReal,
            'z_mp_actual' => $zMpActual,
            'recomputado' => $recomputado,
        ];

        $hayVentaReal = $ventaWaitry > $tol || $ventaErp > $tol || $zMpActual > $tol;

        if ($recomputado <= $tol && $hayVentaReal) {
            return $base + ['decision' => 'omitido_recomputo_cero'];
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
