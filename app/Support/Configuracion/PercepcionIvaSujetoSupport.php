<?php

namespace App\Support\Configuracion;

/**
 * A quién corresponde percepción de IVA (RG 2408 / RG 5329):
 * solo comprador Responsable Inscripto. Monotributo, CF, exento, etc. no.
 */
final class PercepcionIvaSujetoSupport
{
    /** Código AFIP CondicionIVAReceptor: IVA Responsable Inscripto. */
    public const CODIGO_AFIP_RESPONSABLE_INSCRIPTO = 1;

    public static function correspondePercepcionIva(?object $condicioniva): bool
    {
        if ($condicioniva === null) {
            return false;
        }

        $codigo = trim((string) ($condicioniva->codigoexterno ?? ''));
        if ($codigo !== '') {
            return (int) $codigo === self::CODIGO_AFIP_RESPONSABLE_INSCRIPTO;
        }

        $id = (int) ($condicioniva->id ?? 0);
        if ($id <= 0) {
            return false;
        }

        return $id === (int) config('arca.padron_validacion_cliente.condicioniva_responsable_inscripto_id', 1);
    }
}
