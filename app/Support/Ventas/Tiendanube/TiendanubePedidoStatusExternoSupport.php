<?php

namespace App\Support\Ventas\Tiendanube;

/**
 * Estados del pedido en Tiendanube (campo status de la API).
 */
final class TiendanubePedidoStatusExternoSupport
{
    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public const CANCELLED = 'cancelled';

    /** @return array<string,string> */
    public static function etiquetas(): array
    {
        return [
            self::OPEN => 'Abierto',
            self::CLOSED => 'Cerrado',
            self::CANCELLED => 'Cancelado',
        ];
    }

    public static function etiqueta(?string $status): string
    {
        $s = strtolower(trim((string) $status));
        if ($s === '') {
            return '—';
        }

        return self::etiquetas()[$s] ?? $status;
    }

    public static function badgeClass(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            self::OPEN => 'badge-info',
            self::CLOSED => 'badge-secondary',
            self::CANCELLED => 'badge-danger',
            default => 'badge-light',
        };
    }
}
