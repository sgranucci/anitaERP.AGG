<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Factura A: el precio guardado puede traer IVA (y percepciones, si hay detracción).
 * El pie muestra el subtotal neto. El renglón impreso tiene que usar ese mismo neto.
 */
final class FacturaLineaPrecioImpresionSupport
{
    /**
     * Solo circuito concepto con tilde «Precio neto» destildado: el importe tipado
     * es el total del comprobante (IVA + percepciones desagregados). No activa la
     * detracción global de USA_DETRACCION ni otros circuitos (pedido, remito, POS).
     */
    public const FLAG_DETRAER_PERCEPCIONES_PRECIO_FINAL = 'detraer_percepciones_precio_final';

    public static function precioIncluyeImpuesto(mixed $incluye): bool
    {
        $valor = strtoupper(trim((string) $incluye));

        return $valor !== '' && $valor !== 'N' && $valor !== '2';
    }

    /**
     * Detracción fiscal: config AGG (USA_DETRACCION=S) o flag acotado a concepto precio final.
     *
     * @param  array<string, mixed>  $dataCliente
     */
    public static function usaDetraccionDesdeCliente(array $dataCliente): bool
    {
        return (string) config('facturacion.USA_DETRACCION') === 'S'
            || ! empty($dataCliente[self::FLAG_DETRAER_PERCEPCIONES_PRECIO_FINAL]);
    }

    /**
     * Impresión Factura A: misma detracción que al calcular si el renglón fue concepto a precio final.
     *
     * @param  iterable<int, mixed>  $emisiones
     */
    public static function usaDetraccionDesdeEmisiones(iterable $emisiones): bool
    {
        if ((string) config('facturacion.USA_DETRACCION') === 'S') {
            return true;
        }

        foreach ($emisiones as $emision) {
            $articuloId = is_array($emision)
                ? (int) ($emision['articulo_id'] ?? 0)
                : (int) ($emision->articulo_id ?? 0);
            if ($articuloId > 0) {
                continue;
            }
            $incluye = is_array($emision)
                ? ($emision['incluyeimpuesto'] ?? 'N')
                : ($emision->incluyeimpuesto ?? 'N');
            if (self::precioIncluyeImpuesto($incluye)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tasas que ImpuestoService suma al divisor cuando hay detracción:
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
     * Factura B: el precio impreso incluye el IVA. El pie no vuelve a sumarlo.
     */
    public static function conIva(float $precio, mixed $incluye, float $tasaIva): float
    {
        if (self::precioIncluyeImpuesto($incluye) || $tasaIva <= 0.00001) {
            return $precio;
        }

        return round($precio * (1 + ($tasaIva / 100)), 2);
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
