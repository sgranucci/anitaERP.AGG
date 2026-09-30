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
            $dtoImporte = abs((float) ($linea['descuento_importe'] ?? 0));
            $bruto = round(abs($cantidad) * $precio * (1 - $dto / 100), 2);
            $neto = $dtoImporte > 0.00001
                ? max(0., round($bruto - $dtoImporte, 2))
                : $bruto;
            $copia = $linea;
            if ($dtoImporte > 0.00001) {
                $copia['precio'] = self::precioUnitarioParaNeto(abs($cantidad), $neto);
                $copia['descuento'] = 0;
                $copia['descuentolinea'] = 0;
                $copia['descuento_importe'] = 0;
            }

            if ($cantidad > 0) {
                $copia['cantidad'] = $cantidad;
                $fac[] = $copia;
                $netoFac += $neto;
            } else {
                $copia['cantidad'] = abs($cantidad);
                $nc[] = $copia;
                $netoNc += $neto;
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

    /**
     * Precio unitario tal que round(cantidad * precio, 2) coincide con el neto en pesos.
     * Evita convertir un importe a un porcentaje periódico (16,666…).
     */
    public static function precioUnitarioParaNeto(float $cantidad, float $neto): float
    {
        $cant = abs($cantidad);
        $objetivo = round(abs($neto), 2);
        if ($cant < 0.000001) {
            return 0.;
        }

        $precio = $objetivo / $cant;
        for ($i = 0; $i < 6; $i++) {
            $calculado = round($cant * $precio, 2);
            $dif = round($objetivo - $calculado, 2);
            if (abs($dif) < 0.001) {
                break;
            }
            $precio += $dif / $cant;
        }

        return $precio;
    }
}
