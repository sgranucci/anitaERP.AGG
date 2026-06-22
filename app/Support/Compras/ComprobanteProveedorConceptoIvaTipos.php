<?php

namespace App\Support\Compras;

/**
 * Clasificación de conceptos IVA compra para armado de asiento (factura contra COM).
 */
final class ComprobanteProveedorConceptoIvaTipos
{
    /** Neto / gravado / exento → reversan provisión en modo ASIGNA_RECEPCION. */
    public const NETO = ['N', 'G', 'E'];

    /** Impuestos y percepciones → deben por cuenta del concepto. */
    public const IMPUESTO = ['I', 'P', 'B', 'M', 'T', 'S', 'A'];

    public static function esNeto(?string $tipoconcepto): bool
    {
        return in_array((string) $tipoconcepto, self::NETO, true);
    }

    public static function esImpuesto(?string $tipoconcepto): bool
    {
        return in_array((string) $tipoconcepto, self::IMPUESTO, true);
    }
}
