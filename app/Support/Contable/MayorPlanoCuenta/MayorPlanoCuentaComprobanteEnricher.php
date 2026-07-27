<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use Illuminate\Support\Facades\DB;

/**
 * Completa IDs de comprobante proveedor / venta desde el asiento ERP ya resuelto.
 */
class MayorPlanoCuentaComprobanteEnricher
{
    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function enriquecer(array $filas): array
    {
        $asientoIds = array_values(array_unique(array_filter(array_map(
            fn (array $f) => (int) ($f['asiento_id'] ?? 0),
            $filas,
        ), fn (int $n) => $n > 0)));

        if ($asientoIds === []) {
            foreach ($filas as $idx => $fila) {
                if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                    continue;
                }
                $filas[$idx]['comprobante_proveedor_id'] = 0;
                $filas[$idx]['venta_id'] = 0;
            }

            return $filas;
        }

        $mapa = DB::table('asiento')
            ->whereIn('id', $asientoIds)
            ->get(['id', 'comprobante_proveedor_id', 'venta_id'])
            ->keyBy('id');

        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $asientoId = (int) ($fila['asiento_id'] ?? 0);
            $row = $asientoId > 0 ? $mapa->get($asientoId) : null;
            $filas[$idx]['comprobante_proveedor_id'] = (int) ($row->comprobante_proveedor_id ?? 0);
            $filas[$idx]['venta_id'] = (int) ($row->venta_id ?? 0);
        }

        return $filas;
    }
}
