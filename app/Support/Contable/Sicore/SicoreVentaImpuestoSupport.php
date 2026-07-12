<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Impuesto;

final class SicoreVentaImpuestoSupport
{
    public static function esPercepcionIva(Venta_Impuesto $imp): bool
    {
        $concepto = trim((string) $imp->concepto);

        return stripos($concepto, 'Percepcion IVA') !== false
            || stripos($concepto, 'Perc. IVA') !== false;
    }

    public static function esPercepcionNoCategorizada(Venta_Impuesto $imp): bool
    {
        $concepto = trim((string) $imp->concepto);

        if (self::esPercepcionIva($imp)) {
            return false;
        }

        return stripos($concepto, 'no categoriz') !== false
            || stripos($concepto, 'no categ') !== false
            || stripos($concepto, 'Perc. no') !== false;
    }

    public static function signoVenta(Venta $venta): float
    {
        $raw = (int) ($venta->tipotransacciones?->getRawOriginal('signo') ?? 1);

        return $raw < 0 ? -1.0 : 1.0;
    }

    public static function coefMoneda(Venta $venta): float
    {
        $cot = (float) ($venta->cotizacion ?? 1);

        return $cot > 0 ? $cot : 1.0;
    }
}
