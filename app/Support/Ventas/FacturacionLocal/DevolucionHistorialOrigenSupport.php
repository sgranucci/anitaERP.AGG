<?php

namespace App\Support\Ventas\FacturacionLocal;

final class DevolucionHistorialOrigenSupport
{
    public const POS = 'pos';

    public const NOTA_CREDITO = 'nota_credito';

    public const TIENDANUBE = 'tiendanube';

    /** @var array<string, string> */
    public const ETIQUETAS = [
        self::POS => 'POS local',
        self::NOTA_CREDITO => 'Nota de crédito',
        self::TIENDANUBE => 'Tienda Nube',
    ];

    public static function etiqueta(string $origen): string
    {
        return self::ETIQUETAS[$origen] ?? $origen;
    }
}
