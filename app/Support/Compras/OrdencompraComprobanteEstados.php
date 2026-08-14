<?php

namespace App\Support\Compras;

/**
 * Estado del comprobante a venir en la OC.
 *
 * PENDIENTE: aún se espera factura.
 * CARGADO: ya no viene (pagado/anulado históricamente, o factura ERP vinculada).
 */
final class OrdencompraComprobanteEstados
{
    public const PENDIENTE = 'PENDIENTE';

    public const CARGADO = 'CARGADO';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::PENDIENTE,
            self::CARGADO,
        ];
    }

    public static function esValido(?string $estado): bool
    {
        return in_array((string) $estado, self::todos(), true);
    }

    public static function normalizar(?string $estado): string
    {
        $estado = strtoupper(trim((string) $estado));

        return self::esValido($estado) ? $estado : self::PENDIENTE;
    }

    public static function etiqueta(string $estado): string
    {
        return match (self::normalizar($estado)) {
            self::CARGADO => 'Ya cargado',
            default => 'Pendiente',
        };
    }
}
