<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Factura A: el precio guardado puede traer IVA (y percepciones, si hay detracción).
 * El pie muestra el subtotal neto. El renglón impreso tiene que usar ese mismo neto.
 */
final class FacturaLineaPrecioImpresionSupport
{
    public static function precioIncluyeImpuesto(mixed $incluye): bool
    {
        $valor = strtoupper(trim((string) $incluye));

        return $valor !== '' && $valor !== 'N' && $valor !== '2';
    }

    /**
     * Tasas que ImpuestoService suma al divisor cuando USA_DETRACCION está activo:
     * percepciones IIBB (Perc. …) y percepción IVA. No el IVA del renglón ni la
     * percepción de no categorizado, que se calcula después del neto.
     *
     * @param  iterable<int, mixed>  $conceptos
     */
    public static function tasaDetraccion(iterable $conceptos, bool $usaDetraccion): float
    {
        if (! $usaDetraccion) {
            return 0.0;
        }

        $tasa = 0.0;
        foreach ($conceptos as $fila) {
            $datos = self::fila($fila);
            if ($datos === null || ! self::conceptoEntraEnDetraccion($datos['concepto'])) {
                continue;
            }
            $tasa += $datos['tasa'];
        }

        return $tasa;
    }

    public static function conceptoEntraEnDetraccion(string $concepto): bool
    {
        return str_starts_with($concepto, 'Perc.')
            || str_starts_with($concepto, 'Percepcion IVA')
            || str_starts_with($concepto, 'Percepcion IIBB');
    }

    /**
     * @return array{precio: float, preciosindescuento: float}
     */
    public static function netear(
        float $precio,
        float $precioSinDescuento,
        mixed $incluye,
        float $tasaIva,
        float $tasaDetraccion
    ): array {
        $tasa = $tasaIva + $tasaDetraccion;
        if (! self::precioIncluyeImpuesto($incluye) || $tasa <= 0.00001) {
            return [
                'precio' => $precio,
                'preciosindescuento' => $precioSinDescuento,
            ];
        }

        $divisor = 1 + ($tasa / 100);

        return [
            'precio' => $precio / $divisor,
            'preciosindescuento' => $precioSinDescuento / $divisor,
        ];
    }

    /**
     * @return array{concepto: string, tasa: float}|null
     */
    private static function fila(mixed $fila): ?array
    {
        if (is_array($fila)) {
            return [
                'concepto' => (string) ($fila['concepto'] ?? ''),
                'tasa' => (float) ($fila['tasa'] ?? 0),
            ];
        }
        if (is_object($fila)) {
            return [
                'concepto' => (string) ($fila->concepto ?? ''),
                'tasa' => (float) ($fila->tasa ?? 0),
            ];
        }

        return null;
    }
}
