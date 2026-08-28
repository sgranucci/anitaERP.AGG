<?php

namespace App\Support\Ventas;

use Illuminate\Support\Facades\Cache;

final class CotElectronicoPreferenciasUsuario
{
    private const CACHE_IMPRIMIR_AL_PROCESAR = 'cot-electronico-imprimir-al-procesar';

    public static function persistirImprimirAlProcesar(bool $valor): void
    {
        Cache::forever(generaKey(self::CACHE_IMPRIMIR_AL_PROCESAR), $valor);
    }

    public static function resolverImprimirAlProcesar(): bool
    {
        $valor = cache()->get(generaKey(self::CACHE_IMPRIMIR_AL_PROCESAR));
        if ($valor === null) {
            return false;
        }

        return (bool) $valor;
    }
}
