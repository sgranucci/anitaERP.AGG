<?php

namespace App\Support\Ventas\FacturacionLocal;

/**
 * Split de carrito con cantidades firmadas → líneas FAC (positivas) y NC (negativas en ABS).
 */
final class FacturacionLocalSplitFacNcSupport
{
    /**
     * @param  list<array<string,mixed>>  $lineas  cada una con cantidad (firmada), articulo_id, precio, ...
     * @return array{fac:list<array<string,mixed>>,nc:list<array<string,mixed>>,tiene_nc:bool,neto_fac:float,neto_nc:float,neto:float}
     */
    public static function partir(array $lineas): array
    {
        $fac = [];
        $nc = [];
        $netoFac = 0.;
        $netoNc = 0.;

        foreach ($lineas as $linea) {
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            if (abs($cantidad) < 0.000001) {
                continue;
            }
            $precio = (float) ($linea['precio'] ?? 0);
            $dto = (float) ($linea['descuento'] ?? $linea['descuentolinea'] ?? 0);
            $importe = round($cantidad * $precio * (1 - $dto / 100), 2);

            if ($cantidad > 0) {
                $copia = $linea;
                $copia['cantidad'] = $cantidad;
                $fac[] = $copia;
                $netoFac += $importe;
            } else {
                $copia = $linea;
                $copia['cantidad'] = abs($cantidad);
                $nc[] = $copia;
                $netoNc += abs($importe);
            }
        }

        return [
            'fac' => $fac,
            'nc' => $nc,
            'tiene_nc' => $nc !== [],
            'neto_fac' => round($netoFac, 2),
            'neto_nc' => round($netoNc, 2),
            'neto' => round($netoFac - $netoNc, 2),
        ];
    }
}
