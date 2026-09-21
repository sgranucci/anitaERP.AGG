<?php

namespace App\Support\Ventas\FacturacionLocal;

/**
 * Estados del remito interno (Facturación Local Ferli).
 */
final class RemitoInternoEstadosSupport
{
    public const BORRADOR = 'borrador';

    public const CONFIRMADO = 'confirmado';

    public const ANULADO = 'anulado';

    /** @var array<string, string> */
    public const ETIQUETAS = [
        self::BORRADOR => 'Borrador',
        self::CONFIRMADO => 'Confirmado',
        self::ANULADO => 'Anulado',
    ];

    public static function esValido(string $estado): bool
    {
        return isset(self::ETIQUETAS[$estado]);
    }

    public static function etiqueta(string $estado): string
    {
        return self::ETIQUETAS[$estado] ?? $estado;
    }

    /**
     * @return array<string, string>
     */
    public static function opcionesSelect(): array
    {
        return self::ETIQUETAS;
    }

    public static function esEditable(string $estado): bool
    {
        return $estado === self::BORRADOR;
    }
}
