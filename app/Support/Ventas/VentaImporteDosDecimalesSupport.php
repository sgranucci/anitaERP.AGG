<?php

namespace App\Support\Ventas;

/**
 * Importes de renglón de venta al centavo.
 * Round de la suma cruda (qty × precio a 6 decimales) puede diferir 1 centavo
 * de sumar cada renglón ya redondeado a 2: 893638.3864 → .39 vs .38.
 */
final class VentaImporteDosDecimalesSupport
{
    public static function redondear($importe): float
    {
        return round((float) $importe, 2);
    }
}
