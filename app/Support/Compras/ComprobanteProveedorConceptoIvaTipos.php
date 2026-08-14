<?php

namespace App\Support\Compras;

/**
 * Clasificación de conceptos IVA compra para armado de asiento (factura contra COM).
 *
 * Códigos = Concepto_Ivacompra::$enumTipoConcepto (Anita concc_tipo_conc).
 */
final class ComprobanteProveedorConceptoIvaTipos
{
    /** Neto / gravado / exento → reversan provisión en modo ASIGNA_RECEPCION. */
    public const NETO = ['N', 'G', 'E'];

    /** Impuestos y percepciones → deben por cuenta del concepto. */
    public const IMPUESTO = ['I', 'P', 'B', 'M', 'T', 'S', 'A'];

    /** Percepción IVA (enum valor P). */
    public const PERCEPCION_IVA = 'P';

    /** Percepción ingresos brutos / IIBB provincial (enum valor B). */
    public const PERCEPCION_IIBB = 'B';

    /** Percepción SIRCREB (enum valor S); también IIBB a efectos de libro, no padrón provincial. */
    public const PERCEPCION_SIRCREB = 'S';

    public static function esNeto(?string $tipoconcepto): bool
    {
        return in_array((string) $tipoconcepto, self::NETO, true);
    }

    public static function esImpuesto(?string $tipoconcepto): bool
    {
        return in_array((string) $tipoconcepto, self::IMPUESTO, true);
    }

    /** Solo tipoconcepto B — no inferir por nombre ni por retieneIIBB. */
    public static function esPercepcionIibb(?string $tipoconcepto): bool
    {
        return strtoupper((string) $tipoconcepto) === self::PERCEPCION_IIBB;
    }
}
