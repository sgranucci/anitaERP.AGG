<?php

namespace App\Support\Ventas;

final class ComprobanteImpresionFormulario
{
    public const FACTURA = 'FACTURA';

    public const REMITO = 'REMITO';

    public const PEDIDO = 'PEDIDO';

    public const ENVIO = 'ENVIO';

    /** @return array<string, string> */
    public static function etiquetas(): array
    {
        return [
            self::FACTURA => 'Factura',
            self::REMITO => 'Remito',
            self::PEDIDO => 'Pedido',
            self::ENVIO => 'Envío',
        ];
    }

    /** @return list<string> */
    public static function todos(): array
    {
        return array_keys(self::etiquetas());
    }
}
