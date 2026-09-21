<?php

namespace App\Support\Ventas\FacturacionLocal;

/**
 * Canales, motivos y disposiciones del legajo marketplace.
 */
final class CambioDevolucionMarketplaceCatalogoSupport
{
    public const CANAL_TIENDANUBE = 'tiendanube';

    public const CANAL_MERCADOLIBRE = 'mercadolibre';

    public const CANAL_MANUAL = 'manual';

    /** @var array<string, string> */
    public const CANALES = [
        self::CANAL_TIENDANUBE => 'Tienda Nube',
        self::CANAL_MERCADOLIBRE => 'Mercado Libre',
        self::CANAL_MANUAL => 'Manual',
    ];

    public const TIPO_DEVOLVER = 'devolver';

    public const TIPO_REEMPLAZO = 'reemplazo';

    /** @var array<string, string> */
    public const TIPOS_LINEA = [
        self::TIPO_DEVOLVER => 'A devolver',
        self::TIPO_REEMPLAZO => 'Reemplazo',
    ];

    /** @var array<string, string> */
    public const MOTIVOS = [
        'cambio_talle' => 'Cambio de talle',
        'defecto' => 'Defecto / falla',
        'arrepentimiento' => 'Arrepentimiento',
        'otro' => 'Otro',
    ];

    /** @var array<string, string> */
    public const DISPOSICIONES = [
        'reventa' => 'Reventa',
        'deterioro' => 'Deterioro',
        'no_recibido' => 'No recibido',
    ];
}
