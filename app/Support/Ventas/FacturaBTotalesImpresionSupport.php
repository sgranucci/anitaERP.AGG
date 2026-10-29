<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Support\Configuracion\PercepcionNoCategorizadoSupport;

/**
 * Pie de factura letra B: subtotal y descuento (con IVA, como el renglón),
 * percepción a no categorizado (RG 2126, rótulo «Perc.Iva no categ.») y percepción IIBB CABA cuando
 * el comprobante las tiene. Gravado e IVA del pie no se imprimen: el IVA
 * va en el precio y en la leyenda de transparencia.
 */
final class FacturaBTotalesImpresionSupport
{
    /** RG 2126. No es la percepción IVA del 3 % (RG 5329). */
    public const ETIQUETA_PERCEPCION_IVA = 'Perc.Iva no categ.';

    public const ETIQUETA_PERCEPCION_IIBB_CABA = 'Percepcion IIBB CABA';

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function mostrar(array $fila): bool
    {
        $concepto = (string) ($fila['concepto'] ?? '');
        if ($concepto === 'Total' || $concepto === 'Subtotal') {
            return true;
        }

        if (abs((float) ($fila['importe'] ?? 0)) < 0.00001) {
            return false;
        }

        if (str_starts_with($concepto, 'Descuento')) {
            return true;
        }

        return self::esPercepcionNoCategorizado($fila) || self::esPercepcionIibbCaba($fila);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function etiqueta(array $fila): string
    {
        if (self::esPercepcionNoCategorizado($fila)) {
            return self::ETIQUETA_PERCEPCION_IVA;
        }
        if (self::esPercepcionIibbCaba($fila)) {
            return self::ETIQUETA_PERCEPCION_IIBB_CABA;
        }

        return (string) ($fila['concepto'] ?? '');
    }

    /**
     * @param  iterable<int, mixed>  $conceptos
     * @return list<array{concepto: string, tasa: float, importe: float, es_total: bool}>
     */
    public static function lineas(iterable $conceptos): array
    {
        $lineas = [];
        foreach ($conceptos as $item) {
            $fila = self::filaComoArray($item);
            if ($fila === [] || ! self::mostrar($fila)) {
                continue;
            }
            $lineas[] = [
                'concepto' => self::etiqueta($fila),
                'tasa' => (float) ($fila['tasa'] ?? 0),
                'importe' => (float) ($fila['importe'] ?? 0),
                'es_total' => ((string) ($fila['concepto'] ?? '')) === 'Total',
            ];
        }

        return self::preciosConIvaIncluido($conceptos, $lineas);
    }

    /**
     * El renglón de la B se imprime con IVA. Subtotal y descuento porcentual
     * están guardados en neto: se llevan a la misma base para que cierren con el total.
     *
     * @param  iterable<int, mixed>  $conceptos
     * @param  list<array{concepto: string, tasa: float, importe: float, es_total: bool}>  $lineas
     * @return list<array{concepto: string, tasa: float, importe: float, es_total: bool}>
     */
    private static function preciosConIvaIncluido(iterable $conceptos, array $lineas): array
    {
        $tasa = self::tasaIvaUnica($conceptos);
        if ($tasa === null || $tasa <= 0.00001) {
            return $lineas;
        }

        $factor = 1 + ($tasa / 100);
        $exento = 0.0;
        foreach ($conceptos as $item) {
            $fila = self::filaComoArray($item);
            $concepto = (string) ($fila['concepto'] ?? '');
            if ($concepto === 'Exento' || $concepto === 'No Gravado') {
                $exento += (float) ($fila['importe'] ?? 0);
            }
        }

        foreach ($lineas as $i => $linea) {
            $concepto = (string) $linea['concepto'];
            if ($concepto === 'Subtotal') {
                $gravado = (float) $linea['importe'] - $exento;
                $lineas[$i]['importe'] = round($exento + ($gravado * $factor), 2);
            } elseif (str_starts_with($concepto, 'Descuento') && abs((float) $linea['tasa']) > 0.00001) {
                $lineas[$i]['importe'] = round((float) $linea['importe'] * $factor, 2);
            }
        }

        return $lineas;
    }

    /**
     * @param  iterable<int, mixed>  $conceptos
     */
    private static function tasaIvaUnica(iterable $conceptos): ?float
    {
        $tasa = null;
        foreach ($conceptos as $item) {
            $fila = self::filaComoArray($item);
            $concepto = (string) ($fila['concepto'] ?? '');
            if (! preg_match('/^Iva\s+([0-9]+(?:\.[0-9]+)?)%/i', $concepto, $coincidencias)) {
                continue;
            }
            if (abs((float) ($fila['importe'] ?? 0)) < 0.00001) {
                continue;
            }
            $actual = (float) $coincidencias[1];
            if ($tasa !== null && abs($tasa - $actual) > 0.00001) {
                return null;
            }
            $tasa = $actual;
        }

        return $tasa;
    }

    /**
     * @return array<string, mixed>
     */
    public static function filaComoArray(mixed $fila): array
    {
        if (is_array($fila)) {
            return $fila;
        }
        if (is_object($fila) && method_exists($fila, 'toArray')) {
            return $fila->toArray();
        }

        return is_object($fila) ? (array) $fila : [];
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function esPercepcionNoCategorizado(array $fila): bool
    {
        return PercepcionNoCategorizadoSupport::esConcepto((string) ($fila['concepto'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function esPercepcionIibbCaba(array $fila): bool
    {
        if (ElBierzoFacturaBPercepcionCabaSupport::esJurisdiccionCaba($fila['jurisdiccion'] ?? null)) {
            return true;
        }

        $provincia = $fila['provincias'] ?? $fila['provincia'] ?? null;
        $jurisdiccionProvincia = null;
        if (is_object($provincia)) {
            $jurisdiccionProvincia = $provincia->jurisdiccion ?? null;
        } elseif (is_array($provincia)) {
            $jurisdiccionProvincia = $provincia['jurisdiccion'] ?? null;
        }
        if (ElBierzoFacturaBPercepcionCabaSupport::esJurisdiccionCaba($jurisdiccionProvincia)) {
            return true;
        }

        $concepto = mb_strtolower((string) ($fila['concepto'] ?? ''));
        if ($concepto === '' || ! str_contains($concepto, 'perc')) {
            return false;
        }
        if (PercepcionNoCategorizadoSupport::esConcepto($concepto)) {
            return false;
        }
        if (str_contains($concepto, 'percepcion iva') || str_contains($concepto, 'perc. iva')) {
            return false;
        }

        return str_contains($concepto, 'caba')
            || str_contains($concepto, 'capital federal')
            || str_contains($concepto, 'ciudad autonoma')
            || str_contains($concepto, 'ciudad de buenos aires')
            || str_contains($concepto, 'agip');
    }
}
