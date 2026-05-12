<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra;
use App\Queries\Configuracion\CotizacionQueryInterface;

/**
 * Total de OC en moneda del primer ítem: suma de cant×precio×cotización por línea.
 */
final class OrdencompraTotalesCabecera
{
    /**
     * @return array{monto: float, moneda_id: int, monedacabecera_abreviatura: string}
     */
    public static function desdeModelo(Ordencompra $oc, CotizacionQueryInterface $cotizacionQuery): array
    {
        $oc->loadMissing(['ordencompra_articulos.monedas']);

        $lineas = collect($oc->ordencompra_articulos ?? [])->sortBy('id');

        if ($lineas->isEmpty()) {
            return ['monto' => 0.0, 'moneda_id' => 1, 'monedacabecera_abreviatura' => ''];
        }

        $primer = $lineas->first();
        $monedaBaseId = (int) ($primer->moneda_id ?: 1);
        $abrev = (string) (optional($primer->monedas)->abreviatura ?? '');

        $suma = 0.0;
        foreach ($lineas as $lin) {
            $cot = (float) ($lin->cotizacion ?? 1);
            if ($cot <= 0) {
                $cot = 1.0;
            }
            $suma += (float) $lin->cantidad * (float) $lin->precio * $cot;
        }

        return [
            'monto' => round($suma, 4),
            'moneda_id' => $monedaBaseId,
            'monedacabecera_abreviatura' => $abrev,
        ];
    }

    public static function aplicarAtributosVirtuales(Ordencompra $oc, CotizacionQueryInterface $cotizacionQuery): void
    {
        $t = self::desdeModelo($oc, $cotizacionQuery);
        $oc->setAttribute('monto', $t['monto']);
        $oc->setAttribute('monedacabecera_abreviatura', $t['monedacabecera_abreviatura']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: float, 1: int}
     */
    public static function montoYMonedaDesdeRequest(array $data, CotizacionQueryInterface $cotizacionQuery): array
    {
        return OrdencompraTotalesResumen::montoYMonedaDesdeRequest($data, $cotizacionQuery);
    }
}
