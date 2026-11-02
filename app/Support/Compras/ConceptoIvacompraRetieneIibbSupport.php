<?php

declare(strict_types=1);

namespace App\Support\Compras;

/**
 * Base de retención de IIBB cuando Anita no trae concc_retiene_ibr (Ferli).
 *
 * Convenio multilateral: el sujeto es el precio de la operación. Entran el
 * gravado, el exento y el no gravado que Anita marca como compra (contenido C),
 * y el precio del monotributo (el comprobante no discrimina IVA). Quedan afuera
 * el IVA, las percepciones, las retenciones y el resto de los tributos.
 *
 * Si Anita trae la columna, esa marca manda. Este criterio no la pisa.
 */
final class ConceptoIvacompraRetieneIibbSupport
{
    /** conccomp.concc_concepto del precio en comprobantes de monotributo. */
    private const CODIGO_MONOTRIBUTO = '4';

    public static function cuandoAnitaNoTraeMarca(
        ?string $codigo,
        ?string $contenido,
        ?string $tipoconcepto = null,
    ): string {
        $contenido = strtoupper(trim((string) $contenido));
        $tipo = strtoupper(trim((string) $tipoconcepto));
        $codigo = ltrim(trim((string) $codigo), '0');

        if ($contenido === 'C' || in_array($tipo, ['G', 'E'], true)) {
            return 'S';
        }

        if ($codigo === self::CODIGO_MONOTRIBUTO) {
            return 'S';
        }

        return 'N';
    }
}
