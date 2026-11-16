<?php

declare(strict_types=1);

namespace App\Support\Compras;

final class ImportacionGastoTratamiento
{
    public const INVENTARIABLE = 'inventariable';

    public const GASTO = 'gasto';

    /** @var array<string, string> */
    public const ETIQUETAS = [
        self::INVENTARIABLE => 'Inventariable (capitaliza al stock)',
        self::GASTO => 'Gasto (resultado)',
    ];

    public const BASE_VALOR = 'valor';

    public const BASE_CANTIDAD = 'cantidad';

    public const BASE_PESO = 'peso';

    public const BASE_VOLUMEN = 'volumen';

    public const BASE_IGUAL = 'igual';

    /** @var array<string, string> */
    public const BASES = [
        self::BASE_VALOR => 'Por valor',
        self::BASE_CANTIDAD => 'Por cantidad',
        self::BASE_PESO => 'Por peso',
        self::BASE_VOLUMEN => 'Por volumen',
        self::BASE_IGUAL => 'Partes iguales',
    ];
}
