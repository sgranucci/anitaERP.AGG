<?php

namespace App\Support\Ventas\Waitry;

use Illuminate\Support\Facades\DB;

/**
 * Reconstruye medios de cobro del POS a partir de la venta (reintentos Waitry).
 */
final class WaitryMediosPagoFromVentaSupport
{
    /**
     * @return list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion:float,observacion:?string}>
     */
    public function desdeVentaId(int $ventaId): array
    {
        if ($ventaId <= 0) {
            return [];
        }

        $movimientoId = DB::table('caja_movimiento')
            ->where('venta_id', $ventaId)
            ->orderByDesc('id')
            ->value('id');

        if ($movimientoId === null) {
            return [];
        }

        $lineas = DB::table('caja_movimiento_cuentacaja')
            ->where('caja_movimiento_id', $movimientoId)
            ->orderBy('id')
            ->get(['cuentacaja_id', 'moneda_id', 'monto', 'cotizacion', 'observacion']);

        $medios = [];
        foreach ($lineas as $linea) {
            $monto = (float) ($linea->monto ?? 0);
            if ($monto <= 0.) {
                continue;
            }

            $medios[] = [
                'cuentacaja_id' => (int) $linea->cuentacaja_id,
                'moneda_id' => (int) $linea->moneda_id,
                'monto' => $monto,
                'cotizacion' => (float) ($linea->cotizacion ?? 1.),
                'observacion' => $linea->observacion !== null ? (string) $linea->observacion : null,
            ];
        }

        return $medios;
    }
}
