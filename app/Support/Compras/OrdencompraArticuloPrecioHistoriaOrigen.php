<?php

namespace App\Support\Compras;

class OrdencompraArticuloPrecioHistoriaOrigen
{
    public const RECEPCION_CONFIRMADA = 'recepcion_confirmada';

    public const APLICACION_MANUAL = 'aplicacion_manual';

    /**
     * @return array<string, string>
     */
    public static function etiquetas(): array
    {
        return [
            self::RECEPCION_CONFIRMADA => 'Confirmación de recepción',
            self::APLICACION_MANUAL => 'Aplicación manual desde OC',
        ];
    }

    public static function etiqueta(string $origen): string
    {
        return self::etiquetas()[$origen] ?? $origen;
    }
}
