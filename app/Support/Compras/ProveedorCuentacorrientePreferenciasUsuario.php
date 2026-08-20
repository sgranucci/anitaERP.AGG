<?php

namespace App\Support\Compras;

use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;
use Illuminate\Support\Facades\Cache;

final class ProveedorCuentacorrientePreferenciasUsuario
{
    public const MODO_CUENTA_CORRIENTE = 'cuenta_corriente';

    public const MODO_DEUDA = 'deuda';

    private const CACHE_MODO_VISTA = 'proveedor-cuentacorriente-modo-vista';

    private const CACHE_MONEDA = 'proveedor-cuentacorriente-moneda';

    private const CACHE_EXPRESION = 'proveedor-cuentacorriente-expresion';

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

    public static function persistirMonedaId(?int $monedaId): void
    {
        Cache::forever(generaKey(self::CACHE_MONEDA), $monedaId);
    }

    public static function resolverMonedaId(mixed $valorRequest = null, bool $requestTieneMoneda = false): ?int
    {
        if ($requestTieneMoneda) {
            return CuentacorrienteSaldosPorMoneda::resolverMonedaId($valorRequest);
        }

        $cached = cache()->get(generaKey(self::CACHE_MONEDA));

        return CuentacorrienteSaldosPorMoneda::resolverMonedaId($cached);
    }

    public static function persistirExpresion(string $expresion): void
    {
        Cache::forever(
            generaKey(self::CACHE_EXPRESION),
            CuentacorrienteSaldosPorMoneda::resolverExpresion($expresion)
        );
    }

    public static function resolverExpresion(mixed $valorRequest = null, bool $requestTieneExpresion = false): string
    {
        if ($requestTieneExpresion) {
            return CuentacorrienteSaldosPorMoneda::resolverExpresion($valorRequest);
        }

        $cached = cache()->get(generaKey(self::CACHE_EXPRESION));

        return CuentacorrienteSaldosPorMoneda::resolverExpresion($cached);
    }
}
