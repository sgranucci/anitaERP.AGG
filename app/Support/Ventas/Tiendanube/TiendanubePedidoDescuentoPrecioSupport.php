<?php

declare(strict_types=1);

namespace App\Support\Ventas\Tiendanube;

/**
 * Cupón de Tiendanube sin artículo de descuento.
 *
 * El descuento al pie resta el importe del total del comprobante y deja
 * ImpNeto + ImpIVA en el bruto anterior. AFIP WSFE rechaza eso con el código
 * 10048. Acá el cupón (IVA incluido) se baja del precio de los artículos;
 * el flete absorbe solo lo que supera a la mercadería.
 */
final class TiendanubePedidoDescuentoPrecioSupport
{
    /**
     * @param  list<float|int|string>  $precios
     * @param  list<float|int|string>  $cantidades
     * @param  list<string>  $tipos
     * @return list<float>
     */
    public static function aplicar(array $precios, array $cantidades, array $tipos, float $descuento): array
    {
        $descuento = round($descuento, 2);
        if ($descuento <= 0.004 || $precios === []) {
            return array_map(static fn ($precio): float => round((float) $precio, 2), $precios);
        }

        $producto = [];
        $envio = [];
        foreach ($precios as $i => $precio) {
            $tipo = (string) ($tipos[$i] ?? 'producto');
            if ($tipo === 'envio') {
                $envio[] = (int) $i;
            } elseif ($tipo !== 'descuento') {
                $producto[] = (int) $i;
            }
        }

        $precios = array_map(static fn ($precio): float => (float) $precio, $precios);
        $resto = self::repartir($precios, $cantidades, $producto, $descuento);
        if ($resto > 0.004) {
            $resto = self::repartir($precios, $cantidades, $envio, $resto);
        }
        if ($resto > 0.004) {
            throw new \InvalidArgumentException(sprintf(
                'El cupón (%.2f) supera el importe facturable de artículos y envío.',
                $descuento
            ));
        }

        return $precios;
    }

    /**
     * @param  list<float>  $precios
     * @param  list<float|int|string>  $cantidades
     * @param  list<int>  $indices
     */
    private static function repartir(array &$precios, array $cantidades, array $indices, float $descuento): float
    {
        $descuento = round($descuento, 2);
        $brutos = [];
        $suma = 0.;
        foreach ($indices as $i) {
            $bruto = self::brutoLinea((float) $precios[$i], self::cantidad($cantidades, $i));
            if ($bruto <= 0.004) {
                continue;
            }
            $brutos[$i] = $bruto;
            $suma = round($suma + $bruto, 2);
        }
        if ($suma <= 0.004) {
            return $descuento;
        }

        $aRepartir = round(min($descuento, $suma), 2);
        $resto = round($descuento - $aRepartir, 2);
        $acum = 0.;
        $keys = array_keys($brutos);
        $ultimo = count($keys) - 1;
        foreach ($keys as $k => $i) {
            if ($k === $ultimo) {
                $parte = round($aRepartir - $acum, 2);
            } else {
                $parte = round($aRepartir * ($brutos[$i] / $suma), 2);
                $pendiente = round($aRepartir - $acum, 2);
                if ($parte > $pendiente) {
                    $parte = $pendiente;
                }
                $acum = round($acum + $parte, 2);
            }
            $cant = self::cantidad($cantidades, $i);
            $nuevoBruto = round($brutos[$i] - $parte, 2);
            if ($nuevoBruto < 0) {
                $nuevoBruto = 0.;
            }
            $precios[$i] = self::precioUnitario($nuevoBruto, $cant);
        }

        return $resto;
    }

    /**
     * @param  list<float|int|string>  $cantidades
     */
    private static function cantidad(array $cantidades, int $i): float
    {
        $cant = (float) ($cantidades[$i] ?? 1);
        if ($cant <= 0) {
            return 1.;
        }

        return $cant;
    }

    private static function brutoLinea(float $precio, float $cantidad): float
    {
        return round($precio * $cantidad, 2);
    }

    /**
     * Con cantidad 1 el unitario es el bruto. Con cantidad distinta de 1
     * se redondea el unitario a 2 decimales (el centavo que no entra queda
     * en el último renglón del grupo, que suele ser cantidad 1).
     */
    private static function precioUnitario(float $bruto, float $cantidad): float
    {
        if (abs($cantidad - 1.) < 0.00001) {
            return round($bruto, 2);
        }

        return round($bruto / $cantidad, 2);
    }
}
