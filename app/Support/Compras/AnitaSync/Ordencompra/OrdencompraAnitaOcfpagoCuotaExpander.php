<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

use App\Models\Compras\Condicionpago;
use App\Models\Compras\Ordencompra_Comprobante;
use Carbon\Carbon;

/**
 * Genera cuotas ocfpagocuota desde condicionpagocuota cuando el comprobante OC no tiene cuotas cargadas.
 */
final class OrdencompraAnitaOcfpagoCuotaExpander
{
    /**
     * @return list<array{fechavencimiento: string, monto: float}>
     */
    public static function desdeComprobante(Ordencompra_Comprobante $comprobante): array
    {
        $condicionId = (int) ($comprobante->condicionpago_id ?? 0);
        if ($condicionId <= 0) {
            return [];
        }

        $cp = Condicionpago::query()
            ->with(['condicionpagocuotas' => fn ($q) => $q->orderBy('cuota')])
            ->find($condicionId);

        if (! $cp || $cp->condicionpagocuotas->isEmpty()) {
            return [];
        }

        $montoTotal = (float) ($comprobante->monto ?? 0);
        $fechaBase = (string) ($comprobante->fechavencimiento ?? date('Y-m-d'));
        $cuotasDef = $cp->condicionpagocuotas;
        $sumPct = (float) $cuotasDef->sum(fn ($c) => (float) ($c->porcentaje ?? 0));
        $cursor = Carbon::parse($fechaBase)->startOfDay();
        $n = $cuotasDef->count();
        $salida = [];

        foreach ($cuotasDef as $c) {
            $pct = (float) ($c->porcentaje ?? 0);
            if ($sumPct > 0 && $pct > 0) {
                $monto = round($montoTotal * $pct / $sumPct, 4);
            } else {
                $monto = round($montoTotal / max(1, $n), 4);
            }

            $tipo = (string) ($c->tipoplazo ?? '');
            if (! empty($c->fechavencimiento)) {
                $fv = Carbon::parse($c->fechavencimiento)->format('Y-m-d');
            } elseif ($tipo === 'D' || $tipo === 'O') {
                $cursor = $cursor->copy()->addDays((int) ($c->plazo ?? 0));
                $fv = $cursor->format('Y-m-d');
            } elseif ($tipo === 'F') {
                $cursor = $cursor->copy()->addMonth();
                $fv = $cursor->format('Y-m-d');
            } else {
                $cursor = $cursor->copy()->addDays((int) ($c->plazo ?? 0));
                $fv = $cursor->format('Y-m-d');
            }

            $salida[] = [
                'fechavencimiento' => $fv,
                'monto' => $monto,
            ];
        }

        return $salida;
    }
}
