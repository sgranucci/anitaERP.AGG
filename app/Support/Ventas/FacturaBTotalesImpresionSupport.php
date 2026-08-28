<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Support\Configuracion\PercepcionNoCategorizadoSupport;

/**
 * Pie de factura letra B: muestra percepción IVA (RG 2126 no categorizado)
 * y percepción IIBB CABA cuando el comprobante las tiene.
 */
final class FacturaBTotalesImpresionSupport
{
    public const ETIQUETA_PERCEPCION_IVA = 'Percepcion IVA';

    public const ETIQUETA_PERCEPCION_IIBB_CABA = 'Percepcion IIBB CABA';

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function mostrar(array $fila): bool
    {
        $concepto = (string) ($fila['concepto'] ?? '');
        if ($concepto === 'Total') {
            return true;
        }

        if (abs((float) ($fila['importe'] ?? 0)) < 0.00001) {
            return false;
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

        return $lineas;
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
