<?php

declare(strict_types=1);

namespace App\Support\Sueldos;

/**
 * SKU de indumentaria: Anita suele mandar el artículo con ceros a la izquierda;
 * en el maestro ERP (articulo.sku) se guardan sin ese padding.
 */
final class IndumentariaSkuSupport
{
    public static function normalizarDesdeAnita(?string $sku): string
    {
        $sku = trim((string) $sku);
        if ($sku === '') {
            return '';
        }
        $sinCeros = ltrim($sku, '0');

        return $sinCeros !== '' ? $sinCeros : $sku;
    }
}
