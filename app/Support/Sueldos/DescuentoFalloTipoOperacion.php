<?php

namespace App\Support\Sueldos;

/**
 * Tipos de movimiento del registro de descuentos por fallo
 * (Anita: campo dtof_tipo_oper).
 */
class DescuentoFalloTipoOperacion
{
    public const DESCUENTO = 'D';

    public const SANCION = 'S';

    public const INGRESO = 'I';

    /** @var array<string, string> */
    public const ETIQUETAS = [
        self::DESCUENTO => 'Descuento fallo',
        self::SANCION => 'Sanción',
        self::INGRESO => 'Ingreso',
    ];

    public static function etiqueta(?string $tipo): string
    {
        return self::ETIQUETAS[$tipo] ?? (string) $tipo;
    }

    public static function esValido(?string $tipo): bool
    {
        return $tipo !== null && isset(self::ETIQUETAS[$tipo]);
    }

    /**
     * En la cuenta corriente Anita:
     * - pérdida / ingreso → Debe
     * - descuento / sanción → Haber
     */
    public static function esDebe(string $tipo): bool
    {
        return $tipo === self::INGRESO;
    }

    public static function esHaber(string $tipo): bool
    {
        return in_array($tipo, [self::DESCUENTO, self::SANCION], true);
    }
}
