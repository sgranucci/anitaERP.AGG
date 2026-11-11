<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

/**
 * Criterio de rango Anita para transferencia / import de ventas Crown.
 *
 * - jornada: ven_fecha_vto (día operativo; el cierre puede pasar medianoche)
 * - fisica: ven_fecha (emisión / impresión del comprobante)
 */
final class TransferenciaCrownCriterioFecha
{
    public const JORNADA = 'jornada';

    public const FISICA = 'fisica';

    public static function normalizar(mixed $criterio): string
    {
        $valor = strtolower(trim((string) $criterio));

        return $valor === self::FISICA ? self::FISICA : self::JORNADA;
    }

    public static function campoAnita(string $criterio): string
    {
        return self::normalizar($criterio) === self::FISICA ? 'ven_fecha' : 'ven_fecha_vto';
    }

    public static function etiqueta(string $criterio): string
    {
        return self::normalizar($criterio) === self::FISICA
            ? 'Fecha física (ven_fecha)'
            : 'Fecha de jornada (ven_fecha_vto)';
    }
}
