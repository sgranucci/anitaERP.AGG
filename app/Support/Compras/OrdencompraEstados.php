<?php

namespace App\Support\Compras;

/**
 * Estados de cabecera de orden de compra (columna ordencompra.estadoordencompra).
 */
final class OrdencompraEstados
{
    public const PENDIENTE = 'PENDIENTE';

    public const APROBADA = 'APROBADA';

    public const CUMPLIDA = 'CUMPLIDA';

    public const SUSPENDIDA = 'SUSPENDIDA';

    public const CERRADA = 'CERRADA';

    /** @return list<string> */
    public static function todos(): array
    {
        return [self::PENDIENTE, self::APROBADA, self::CUMPLIDA, self::SUSPENDIDA, self::CERRADA];
    }

    /** Estados que se pueden asignar por nivel del árbol de aprobación (nombre exacto). */
    public static function estadosArbolConfigurables(): array
    {
        return [
            ['id' => '1', 'valor' => 'P', 'nombre' => self::PENDIENTE],
            ['id' => '2', 'valor' => 'A', 'nombre' => self::APROBADA],
        ];
    }

    public static function esNombreValido(string $nombre): bool
    {
        return in_array($nombre, self::todos(), true);
    }
}
