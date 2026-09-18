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

    /** Impuesto interno / I.T.C. (enum valor T). */
    public const IMPUESTO_INTERNO = 'T';

    /**
     * Códigos Anita de II (conccomp). El 5 suele venir como tipo N (no gravado)
     * y el 510 (I.T.C.) como T; ambos son impuesto interno, no mercadería.
     */
    public const CODIGOS_IMPUESTO_INTERNO = ['5', '510'];

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

    public static function esImpuestoInterno(?string $tipoconcepto, string|int|null $codigo = null): bool
    {
        if (strtoupper((string) $tipoconcepto) === self::IMPUESTO_INTERNO) {
            return true;
        }

        $cod = trim((string) $codigo);

        return $cod !== '' && in_array($cod, self::CODIGOS_IMPUESTO_INTERNO, true);
    }

    /**
     * Neto de mercadería (sin impuesto interno, aunque Anita lo haya dejado en tipo N).
     */
    public static function esNetoMercaderia(?string $tipoconcepto, string|int|null $codigo = null): bool
    {
        return self::esNeto($tipoconcepto) && ! self::esImpuestoInterno($tipoconcepto, $codigo);
    }

    /**
     * Contra COM valuada cierran la provisión FAR.
     * El II solo revierte FAR si esa COM ya lo provisionó (cigarrillos).
     * En gastronomía YAFEMA la COM no lleva II: el concepto va a su cuenta.
     */
    public static function revierteProvisionCom(
        ?string $tipoconcepto,
        string|int|null $codigo = null,
        bool $comIncluyeImpuestoInterno = false,
    ): bool {
        if (self::esImpuestoInterno($tipoconcepto, $codigo)) {
            return $comIncluyeImpuestoInterno;
        }

        return self::esNeto($tipoconcepto);
    }

    /** Solo tipoconcepto B — no inferir por nombre ni por retieneIIBB. */
    public static function esPercepcionIibb(?string $tipoconcepto): bool
    {
        return strtoupper((string) $tipoconcepto) === self::PERCEPCION_IIBB;
    }
}
