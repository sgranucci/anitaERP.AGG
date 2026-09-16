<?php

declare(strict_types=1);

namespace App\Support\Caja\Macro;

/**
 * Resuelve el canal activo (archivo hoy; webservice cuando se registre).
 */
final class MacroPagoCanalSupport
{
    public static function canalActivo(): MacroPagoCanal
    {
        $codigo = (string) config('macro.canal', 'archivo');
        $map = (array) config('macro.canales', []);
        $class = $map[$codigo] ?? MacroPagoCanalArchivo::class;
        if (! class_exists($class)) {
            return new MacroPagoCanalArchivo;
        }
        $canal = app($class);
        if (! $canal instanceof MacroPagoCanal) {
            return new MacroPagoCanalArchivo;
        }

        return $canal;
    }
}
