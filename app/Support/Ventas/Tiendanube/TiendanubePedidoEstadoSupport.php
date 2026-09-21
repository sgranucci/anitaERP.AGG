<?php

namespace App\Support\Ventas\Tiendanube;

/**
 * Estados ERP del staging Tiendanube.
 */
final class TiendanubePedidoEstadoSupport
{
    public const PENDIENTE = 'pendiente';

    public const LISTO = 'listo';

    public const BLOQUEADO_FISCAL = 'bloqueado_fiscal';

    public const FACTURADO = 'facturado';

    public const PARCIAL = 'parcial';

    public const ERROR = 'error';

    public const OMITIDO = 'omitido';

    /** @return array<string,string> */
    public static function etiquetas(): array
    {
        return [
            self::PENDIENTE => 'Pendiente',
            self::LISTO => 'Listo para facturar',
            self::BLOQUEADO_FISCAL => 'Faltan datos fiscales',
            self::FACTURADO => 'Facturado',
            self::PARCIAL => 'Facturado parcial',
            self::ERROR => 'Error',
            self::OMITIDO => 'Omitido',
        ];
    }

    public static function etiqueta(string $estado): string
    {
        return self::etiquetas()[$estado] ?? $estado;
    }

    public static function badgeClass(string $estado): string
    {
        return match ($estado) {
            self::FACTURADO => 'badge-success',
            self::PARCIAL => 'badge-warning',
            self::LISTO => 'badge-primary',
            self::BLOQUEADO_FISCAL => 'badge-warning',
            self::ERROR => 'badge-danger',
            self::OMITIDO => 'badge-secondary',
            default => 'badge-info',
        };
    }
}
