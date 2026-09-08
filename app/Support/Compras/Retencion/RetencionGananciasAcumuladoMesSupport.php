<?php

namespace App\Support\Compras\Retencion;

use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Compras\Retencionganancia;
use Carbon\Carbon;

/**
 * Acumulados mensuales de Ganancias (RG 830 / ARCA) para forma de cálculo S/O.
 *
 * Período: mes calendario del pago. Si el régimen tiene cantidadperiodoacumula > 1,
 * mira hacia atrás esa cantidad de meses (inclusive el mes del pago).
 *
 * Neto previo: suma de detalle_calculo.neto_pago (fallback base_calculo).
 * Retenido previo: suma de importe de retenciones G del período.
 */
final class RetencionGananciasAcumuladoMesSupport
{
    /**
     * @return array{neto: float, retenido: float, desde: string, hasta: string, pagos: int}
     */
    public function acumular(
        int $proveedorId,
        string $fechaPago,
        ?int $empresaId = null,
        ?int $retenciongananciaId = null,
        ?int $excluirPagoproveedorId = null,
        ?int $cantidadPeriodos = null,
    ): array {
        $fecha = Carbon::parse($fechaPago)->startOfDay();
        $periodos = $cantidadPeriodos;
        if ($periodos === null && $retenciongananciaId) {
            $periodos = (int) (Retencionganancia::query()->whereKey($retenciongananciaId)->value('cantidadperiodoacumula') ?? 0);
        }
        $periodos = max(1, (int) ($periodos ?: 1));

        $hasta = $fecha->copy()->endOfMonth();
        $desde = $fecha->copy()->startOfMonth()->subMonths($periodos - 1);

        $query = Pagoproveedor_Retencion::query()
            ->select([
                'pagoproveedor_retencion.id',
                'pagoproveedor_retencion.pagoproveedor_id',
                'pagoproveedor_retencion.importe',
                'pagoproveedor_retencion.base_calculo',
                'pagoproveedor_retencion.detalle_calculo',
                'pagoproveedor_retencion.retencionganancia_id',
            ])
            ->join('pagoproveedor as pp', 'pp.id', '=', 'pagoproveedor_retencion.pagoproveedor_id')
            ->where('pagoproveedor_retencion.tiporetencion', Pagoproveedor_Retencion::TIPO_GANANCIAS)
            ->where('pp.proveedor_id', $proveedorId)
            ->whereBetween('pp.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where('pp.estado', '!=', 'ANULADA')
            ->where('pagoproveedor_retencion.importe', '>', 0);

        if ($empresaId && $empresaId > 0) {
            $query->where('pp.empresa_id', $empresaId);
        }
        if ($retenciongananciaId && $retenciongananciaId > 0) {
            $query->where('pagoproveedor_retencion.retencionganancia_id', $retenciongananciaId);
        }
        if ($excluirPagoproveedorId && $excluirPagoproveedorId > 0) {
            $query->where('pp.id', '!=', $excluirPagoproveedorId);
        }

        // Solo pagos anteriores o del mismo día con id menor (estable al reeditar).
        $query->where(function ($q) use ($fecha, $excluirPagoproveedorId) {
            $q->where('pp.fecha', '<', $fecha->toDateString());
            if ($excluirPagoproveedorId && $excluirPagoproveedorId > 0) {
                $q->orWhere(function ($q2) use ($fecha, $excluirPagoproveedorId) {
                    $q2->whereDate('pp.fecha', $fecha->toDateString())
                        ->where('pp.id', '<', $excluirPagoproveedorId);
                });
            } else {
                $q->orWhereDate('pp.fecha', $fecha->toDateString());
            }
        });

        $filas = $query->get();
        $neto = 0.0;
        $retenido = 0.0;
        $pagos = [];

        foreach ($filas as $fila) {
            $detalle = is_array($fila->detalle_calculo) ? $fila->detalle_calculo : [];
            $netoPago = (float) ($detalle['neto_pago'] ?? 0);
            if ($netoPago <= 0) {
                $netoPago = (float) ($fila->base_calculo ?? 0);
            }
            $neto = round($neto + $netoPago, 2);
            $retenido = round($retenido + (float) $fila->importe, 2);
            $pagos[(int) $fila->pagoproveedor_id] = true;
        }

        return [
            'neto' => $neto,
            'retenido' => $retenido,
            'desde' => $desde->toDateString(),
            'hasta' => min($fecha->toDateString(), $hasta->toDateString()),
            'pagos' => count($pagos),
        ];
    }

    /**
     * Resuelve régimen efectivo del proveedor (para saber si acumula y cuántos períodos).
     */
    public function regimenIdDesdeProveedor(?int $retenciongananciaIdPago, ?int $retenciongananciaIdProveedor): ?int
    {
        if ($retenciongananciaIdPago && $retenciongananciaIdPago > 0) {
            return $retenciongananciaIdPago;
        }
        if ($retenciongananciaIdProveedor && $retenciongananciaIdProveedor > 0) {
            return $retenciongananciaIdProveedor;
        }

        return null;
    }

    public function regimenTomaAcumulados(?int $retenciongananciaId): bool
    {
        if (! $retenciongananciaId) {
            return false;
        }
        $forma = strtoupper(trim((string) Retencionganancia::query()
            ->whereKey($retenciongananciaId)
            ->value('formacalculo')));

        return in_array($forma, ['S', 'O'], true);
    }
}
