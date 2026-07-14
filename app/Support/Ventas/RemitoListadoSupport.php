<?php

namespace App\Support\Ventas;

final class RemitoListadoSupport
{
    /**
     * @return array{caja: float, pieza: float, kilo: float}
     */
    public static function totalesRemito($remito): array
    {
        $caja = $pieza = $kilo = 0.0;

        foreach ($remito->remito_articulos ?? [] as $item) {
            $caja += (float) ($item->caja ?? 0);
            $pieza += (float) ($item->pieza ?? 0);
            $kilo += (float) ($item->kilo ?? 0);
        }

        return [
            'caja' => $caja,
            'pieza' => $pieza,
            'kilo' => $kilo,
        ];
    }
}
