<?php

namespace App\Support\Caja\AnitaSync;

use App\Models\Caja\Cuentacaja;

/**
 * Movimientos sintéticos para rendvalor: neto por medio (facturas − NC en su cuenta).
 */
final class RendicionAnitaMovimientosRendvalorSupport
{
    /**
     * @param  list<array{cuentacaja_id?:int, total?:float, codigo?:string, nombre?:string}>  $porMedioPago
     * @return list<object{cuentacaja_id:int, monto:float, cotizacion:float, cuentacaja: ?Cuentacaja}>
     */
    public static function desdePorMedioPago(array $porMedioPago): array
    {
        $cuentaIds = [];
        foreach ($porMedioPago as $p) {
            $id = (int) ($p['cuentacaja_id'] ?? 0);
            if ($id > 0) {
                $cuentaIds[$id] = $id;
            }
        }

        $cuentas = $cuentaIds !== []
            ? Cuentacaja::query()->whereIn('id', array_values($cuentaIds))->get()->keyBy('id')
            : collect();

        $out = [];
        foreach ($porMedioPago as $p) {
            $cuentaId = (int) ($p['cuentacaja_id'] ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }

            $monto = round((float) ($p['total'] ?? 0), 2);
            if (abs($monto) < 0.005) {
                continue;
            }

            $out[] = (object) [
                'cuentacaja_id' => $cuentaId,
                'monto' => $monto,
                'cotizacion' => 1.0,
                'cuentacaja' => $cuentas->get($cuentaId),
            ];
        }

        return $out;
    }
}
