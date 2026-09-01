<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Qué sucursales entran en la presentación quincenal CAEA.
 *
 * El Bierzo: solo PV 5 (Ventas CAEA) sobre Anita /usr2/bierzo.
 * Villafranca (empresa 2 / path /usr2/villafranca) no informa CAEA.
 */
final class ArcaCaeaSucursalesInformeSupport
{
    public const SUCURSAL_BIERZO_CAEA = 5;

    /**
     * null = todas las sucursales CAEA de la empresa.
     * [] = ninguna (p. ej. Villafranca).
     *
     * @return list<int>|null
     */
    public static function sucursalesPermitidas(?int $empresaId = null): ?array
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return null;
        }

        if ($empresaId !== null && $empresaId !== 1) {
            return [];
        }

        return [self::SUCURSAL_BIERZO_CAEA];
    }

    public static function esSucursalPermitida(int $sucursal, ?int $empresaId = null): bool
    {
        if ($sucursal < 1) {
            return false;
        }

        $permitidas = self::sucursalesPermitidas($empresaId);
        if ($permitidas === null) {
            return true;
        }

        return in_array($sucursal, $permitidas, true);
    }

    /**
     * @param  array<int, mixed>  $pvsPorCodigo
     * @return array<int, mixed>
     */
    public static function filtrarPorCodigo(array $pvsPorCodigo, ?int $empresaId = null): array
    {
        $permitidas = self::sucursalesPermitidas($empresaId);
        if ($permitidas === null) {
            return $pvsPorCodigo;
        }

        $out = [];
        foreach ($pvsPorCodigo as $codigo => $pv) {
            $nro = (int) $codigo;
            if (in_array($nro, $permitidas, true)) {
                $out[$nro] = $pv;
            }
        }

        return $out;
    }

    /**
     * Path Informix del informe CAEA. Nunca Villafranca.
     */
    public static function pathAnitaInforme(): ?string
    {
        $path = rtrim((string) config('anita.bdd_path', ''), '/');
        if ($path === '' || self::esPathVillafranca($path)) {
            return null;
        }

        return $path;
    }

    public static function esPathVillafranca(string $path): bool
    {
        return str_contains(strtolower($path), 'villafranca');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function mergePathAnita(array $payload): array
    {
        $path = self::pathAnitaInforme();
        if ($path !== null) {
            $payload['path_sistema'] = $path;
        } else {
            unset($payload['path_sistema']);
        }

        return $payload;
    }
}
