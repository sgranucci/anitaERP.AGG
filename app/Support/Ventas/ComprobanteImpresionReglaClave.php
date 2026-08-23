<?php

namespace App\Support\Ventas;

final class ComprobanteImpresionReglaClave
{
    public const DEFAULT = 'DEFAULT';

    public const EMPRESA = 'EMPRESA';

    public const TRANSPORTE = 'TRANSPORTE';

    public const PROVINCIA_ENTREGA = 'PROVINCIA_ENTREGA';

    /** @var array<string, int> */
    public const PRECEDENCIA = [
        self::PROVINCIA_ENTREGA => 40,
        self::TRANSPORTE => 30,
        self::EMPRESA => 20,
        self::DEFAULT => 10,
    ];

    /** @return array<string, string> */
    public static function etiquetas(): array
    {
        return [
            self::DEFAULT => 'Default (el resto de casos)',
            self::EMPRESA => 'Empresa (solo si el programa es de todas)',
            self::TRANSPORTE => 'Transporte / reparto',
            self::PROVINCIA_ENTREGA => 'Provincia de entrega',
        ];
    }
}
