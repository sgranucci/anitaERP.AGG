<?php

namespace App\Support\Compras;

/**
 * Estados de precarga de comprobante de proveedor.
 */
final class PrecargaComprobanteEstados
{
    public const PENDIENTE = 'PENDIENTE';

    /** Ya tiene comprobante generado / asignado (no figura en el index por defecto). */
    public const GENERADA = 'GENERADA';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::PENDIENTE,
            self::GENERADA,
        ];
    }

    public static function etiqueta(string $estado): string
    {
        return match ($estado) {
            self::PENDIENTE => 'Pendientes',
            self::GENERADA => 'Generadas',
            default => $estado,
        };
    }
}
