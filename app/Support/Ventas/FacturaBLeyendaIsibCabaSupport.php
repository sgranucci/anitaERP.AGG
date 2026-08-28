<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Leyenda AGIP en Factura / ND / NC letra B de empresas en CABA (ej. El Bierzo).
 * Va debajo de «Otros Tributos Nac. que inciden en el precio».
 */
final class FacturaBLeyendaIsibCabaSupport
{
    public const TEXTO = 'ALICUOTA ISIB CABA 5%';

    /**
     * @param  object|array<string, mixed>|null  $venta
     */
    public static function corresponde(mixed $venta, ?string $letra = null): bool
    {
        if (! self::esLetraB($letra)) {
            return false;
        }

        if (EntornoEmpresaSupport::esElBierzo()) {
            return true;
        }

        return self::emisorEsCaba($venta);
    }

    /**
     * @param  object|array<string, mixed>|null  $venta
     */
    public static function emisorEsCaba(mixed $venta): bool
    {
        foreach (self::jurisdiccionesEmisor($venta) as $jurisdiccion) {
            if (ElBierzoFacturaBPercepcionCabaSupport::esJurisdiccionCaba($jurisdiccion)) {
                return true;
            }
        }

        foreach (self::nombresProvinciaEmisor($venta) as $nombre) {
            if (self::esNombreProvinciaCaba($nombre)) {
                return true;
            }
        }

        return false;
    }

    private static function esLetraB(?string $letra): bool
    {
        return strtoupper(trim((string) $letra)) === 'B';
    }

    /**
     * @param  object|array<string, mixed>|null  $venta
     * @return list<mixed>
     */
    private static function jurisdiccionesEmisor(mixed $venta): array
    {
        return array_values(array_filter([
            self::valorAnidado($venta, ['puntoventas', 'provincias', 'jurisdiccion']),
            self::valorAnidado($venta, ['puntoventas', 'provincia', 'jurisdiccion']),
            self::valorAnidado($venta, ['puntoventas', 'empresas', 'provincia', 'jurisdiccion']),
            self::valorAnidado($venta, ['puntoventas', 'empresas', 'provincias', 'jurisdiccion']),
            self::valorAnidado($venta, ['empresas', 'provincia', 'jurisdiccion']),
        ], static fn ($valor) => $valor !== null && $valor !== ''));
    }

    /**
     * @param  object|array<string, mixed>|null  $venta
     * @return list<string>
     */
    private static function nombresProvinciaEmisor(mixed $venta): array
    {
        $nombres = [];
        foreach ([
            self::valorAnidado($venta, ['puntoventas', 'provincias', 'nombre']),
            self::valorAnidado($venta, ['puntoventas', 'provincia', 'nombre']),
            self::valorAnidado($venta, ['puntoventas', 'empresas', 'provincia', 'nombre']),
            self::valorAnidado($venta, ['empresas', 'provincia', 'nombre']),
        ] as $nombre) {
            $texto = trim((string) $nombre);
            if ($texto !== '') {
                $nombres[] = $texto;
            }
        }

        return $nombres;
    }

    private static function esNombreProvinciaCaba(string $nombre): bool
    {
        $n = mb_strtolower($nombre);

        return str_contains($n, 'capital federal')
            || str_contains($n, 'ciudad autonoma')
            || str_contains($n, 'ciudad autónoma')
            || str_contains($n, 'ciudad de buenos aires')
            || $n === 'caba';
    }

    /**
     * @param  list<string>  $path
     */
    private static function valorAnidado(mixed $origen, array $path): mixed
    {
        $actual = $origen;
        foreach ($path as $clave) {
            if (is_object($actual)) {
                $actual = $actual->{$clave} ?? null;
            } elseif (is_array($actual)) {
                $actual = $actual[$clave] ?? null;
            } else {
                return null;
            }
        }

        return $actual;
    }
}
