<?php

namespace App\Support\Compras;

use Illuminate\Support\Facades\Cache;

final class ProveedorCuentacorrientePreferenciasUsuario
{
    public const MODO_CUENTA_CORRIENTE = 'cuenta_corriente';

    public const MODO_DEUDA = 'deuda';

    private const CACHE_MODO_VISTA = 'proveedor-cuentacorriente-modo-vista';

    public static function persistirModoVista(string $modo): void
    {
        if (! self::modoValido($modo)) {
            return;
        }

        Cache::forever(generaKey(self::CACHE_MODO_VISTA), $modo);
    }

    public static function resolverModoVista(?string $modoRequest = null): string
    {
        if ($modoRequest !== null && $modoRequest !== '' && self::modoValido($modoRequest)) {
            return $modoRequest;
        }

        $cached = cache()->get(generaKey(self::CACHE_MODO_VISTA));
        if (is_string($cached) && self::modoValido($cached)) {
            return $cached;
        }

        return self::MODO_CUENTA_CORRIENTE;
    }

    public static function modoValido(string $modo): bool
    {
        return in_array($modo, [self::MODO_CUENTA_CORRIENTE, self::MODO_DEUDA], true);
    }
}
