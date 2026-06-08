<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;

class RecepcionProveedorParteUnicaSupport
{
    /** Artículo con numeroparte = 1 (Lleva número de parte). */
    public static function articuloManejaParteUnica(?Articulo $articulo): bool
    {
        if (! $articulo) {
            return false;
        }

        return (string) ($articulo->numeroparte ?? '0') === '1';
    }

    /** Cantidad entera de unidades físicas a numerar. */
    public static function unidadesDesdeCantidad(float $cantidad): int
    {
        if ($cantidad <= 0) {
            return 0;
        }

        return max(1, (int) round($cantidad, 0));
    }

    public static function skuAnita13(?Articulo $articulo): string
    {
        return str_pad(substr((string) ($articulo->sku ?? ''), 0, 13), 13, ' ', STR_PAD_RIGHT);
    }
}
