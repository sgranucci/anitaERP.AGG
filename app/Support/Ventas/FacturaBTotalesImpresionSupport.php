<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Support\Configuracion\PercepcionNoCategorizadoSupport;

/**
 * Pie de factura letra B: subtotal y descuento (con IVA, como el renglón),
 * percepción a no categorizado (RG 2126, rótulo «Perc.Iva no categ.») y percepción IIBB CABA cuando
 * el comprobante las tiene. Gravado e IVA del pie no se imprimen: el IVA
 * va en el precio y en la leyenda de transparencia.
 *
 * Invariante: Subtotal + descuentos + percepciones = Total ({@see self::descuadre()}).
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

        return self::subtotalQueCierraConElTotal($conceptos, $lineas);
    }

    /**
     * Subtotal del pie derivado del Total, no del neto guardado.
     *
     * El renglón de la B se imprime con IVA, así que el Subtotal tiene que ser la
     * misma base. No se puede obtener multiplicando el Subtotal neto por (1 + IVA):
     * ese atajo se rompe con exento, impuesto interno, logística, abasto o dos
     * alícuotas en el mismo comprobante.
     *
     * El descuento de cabecera **no se toca**: `AnitaComprobDescuentoSupport` ya lo
     * guardó sobre bruto en letra ≠ A (a-comprob), y volver a multiplicarlo deja el
     * pie corto en descuento × tasa_iva.
     *
     * Subtotal = Total − percepciones impresas − descuentos (negativos), con lo que
     * Subtotal + descuentos + percepciones = Total siempre.
     *
     * @param  iterable<int, mixed>  $conceptos
     * @param  list<array{concepto: string, tasa: float, importe: float, es_total: bool}>  $lineas
     * @return list<array{concepto: string, tasa: float, importe: float, es_total: bool}>
     */
    private static function subtotalQueCierraConElTotal(iterable $conceptos, array $lineas): array
    {
        $total = null;
        foreach ($conceptos as $item) {
            $fila = self::filaComoArray($item);
            if ((string) ($fila['concepto'] ?? '') === 'Total') {
                $total = (float) ($fila['importe'] ?? 0);
            }
        }
        if ($total === null) {
            return $lineas;
        }

        $indiceSubtotal = null;
        $descuentos = 0.0;
        $percepciones = 0.0;
        foreach ($lineas as $i => $linea) {
            if ($linea['es_total']) {
                continue;
            }
            $concepto = (string) $linea['concepto'];
            if ($concepto === 'Subtotal') {
                $indiceSubtotal = $i;
            } elseif (str_starts_with($concepto, 'Descuento')) {
                $descuentos += (float) $linea['importe'];
            } else {
                $percepciones += (float) $linea['importe'];
            }
        }
        if ($indiceSubtotal === null) {
            return $lineas;
        }

        $lineas[$indiceSubtotal]['importe'] = round($total - $percepciones - $descuentos, 2);

        return $lineas;
    }

    /**
     * Diferencia entre la suma de las líneas impresas y el Total. Debe ser 0.
     *
     * @param  list<array{concepto: string, tasa: float, importe: float, es_total: bool}>  $lineas
     */
    public static function descuadre(array $lineas): float
    {
        $total = null;
        $suma = 0.0;
        foreach ($lineas as $linea) {
            if ($linea['es_total']) {
                $total = (float) $linea['importe'];

                continue;
            }
            $suma += (float) $linea['importe'];
        }

        return $total === null ? 0.0 : round($suma - $total, 2);
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
