<?php

namespace App\Support\Ventas\Mercadolibre;

final class MercadolibrePedidoEstadoSupport
{
    public const PENDIENTE = 'pendiente';

    public const LISTO = 'listo';

    public const BLOQUEADO_FISCAL = 'bloqueado_fiscal';

    public const FACTURADO = 'facturado';

    public const ERROR = 'error';

    public const OMITIDO = 'omitido';

    public const CANCELADO = 'cancelado';

    /**
     * @return array<string,string>
     */
    public static function etiquetas(): array
    {
        return [
            self::PENDIENTE => 'Pendiente',
            self::LISTO => 'Listo',
            self::BLOQUEADO_FISCAL => 'Bloqueado fiscal',
            self::FACTURADO => 'Facturado',
            self::ERROR => 'Error',
            self::OMITIDO => 'Omitido',
            self::CANCELADO => 'Cancelado',
        ];
    }
}
