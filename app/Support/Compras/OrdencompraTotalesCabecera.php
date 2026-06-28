<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra;
use App\Queries\Configuracion\CotizacionQueryInterface;

/**
 * Total de OC en moneda del primer ítem: suma de cant×precio convertido con cotización de línea
 * solo cuando la moneda de la línea difiere de la de referencia (mismo criterio que requisiciones).
 */
final class OrdencompraTotalesCabecera
{
    /**
     * @param  iterable<int, object{cantidad?:mixed,precio?:mixed,moneda_id?:mixed,cotizacion?:mixed}>  $lineas
     * @return array{monto: float, moneda_id: int, monedacabecera_abreviatura: string}
     */
    public static function sumaLineasEnMonedaReferencia(iterable $lineas, ?int $monedaReferenciaId = null, string $monedaReferenciaAbrev = ''): array
    {
        $ordenadas = collect($lineas)->sortBy(static fn ($lin) => (int) ($lin->id ?? 0));

        if ($ordenadas->isEmpty()) {
            return ['monto' => 0.0, 'moneda_id' => 1, 'monedacabecera_abreviatura' => ''];
        }

        $primer = $ordenadas->first();
        $monedaBaseId = $monedaReferenciaId ?? (int) ($primer->moneda_id ?: 1);
        $abrev = $monedaReferenciaAbrev !== ''
            ? $monedaReferenciaAbrev
            : (string) (optional($primer->monedas ?? null)->abreviatura ?? '');

        $suma = 0.0;
        foreach ($ordenadas as $lin) {
            $cant = (float) ($lin->cantidad ?? 0);
            if ($cant <= 0) {
                continue;
            }
            $suma += self::importeLineaEnMonedaReferencia(
                $monedaBaseId,
                (int) ($lin->moneda_id ?: $monedaBaseId ?: 1),
                $cant,
                (float) ($lin->precio ?? 0),
                (float) ($lin->cotizacion ?? 1),
            );
        }

        return [
            'monto' => round($suma, 4),
            'moneda_id' => $monedaBaseId,
            'monedacabecera_abreviatura' => $abrev,
        ];
    }

    public static function importeLineaEnMonedaReferencia(
        int $monedaReferenciaId,
        int $lineMonedaId,
        float $cantidad,
        float $precio,
        float $cotizacionLinea,
    ): float {
        $cot = $cotizacionLinea;
        if ($cot <= 0) {
            $cot = 1.0;
        }

        $coef = calculaCoeficienteMoneda(
            $monedaReferenciaId,
            $lineMonedaId ?: $monedaReferenciaId ?: 1,
            ['cotizacionventa' => $cot],
        );

        return $coef * $cantidad * $precio;
    }

    /**
     * @return array{monto: float, moneda_id: int, monedacabecera_abreviatura: string}
     */
    public static function desdeModelo(Ordencompra $oc, CotizacionQueryInterface $cotizacionQuery): array
    {
        $oc->loadMissing(['ordencompra_articulos.monedas']);

        return self::sumaLineasEnMonedaReferencia($oc->ordencompra_articulos ?? []);
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
