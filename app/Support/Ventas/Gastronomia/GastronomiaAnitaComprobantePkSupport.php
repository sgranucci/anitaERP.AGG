<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

/**
 * Clave única Informix: tipo + letra + sucursal + número (+ codigo_tasa en vengrav).
 */
final class GastronomiaAnitaComprobantePkSupport
{
    public static function claveVenta(string $tipo, string $letra, int $sucursal, int $numero): ?string
    {
        $tipo = strtoupper(trim($tipo));
        $letra = strtoupper(trim($letra !== '' ? $letra : 'B'));
        if ($tipo === '' || $sucursal <= 0 || $numero <= 0) {
            return null;
        }

        return $tipo.'|'.$letra.'|'.$sucursal.'|'.$numero;
    }

    public static function claveVengrav(string $tipo, string $letra, int $sucursal, int $numero, string $codigoTasa): ?string
    {
        $codigoTasa = trim($codigoTasa);
        if ($codigoTasa === '') {
            return null;
        }

        $base = self::claveVenta($tipo, $letra, $sucursal, $numero);

        return $base !== null ? $base.'|'.$codigoTasa : null;
    }

    public static function claveVencae(string $tipo, string $letra, int $sucursal, int $numero): ?string
    {
        return self::claveVenta($tipo, $letra, $sucursal, $numero);
    }

    /**
     * @param  list<object>  $filasVenta
     * @return array<string, true>
     */
    public static function indexarVenta(array $filasVenta): array
    {
        $map = [];
        foreach ($filasVenta as $fila) {
            $pk = self::claveVenta(
                (string) ($fila->ven_tipo ?? ''),
                (string) ($fila->ven_letra ?? 'B'),
                self::sucursalEntera((string) ($fila->ven_sucursal ?? '')),
                (int) ($fila->ven_nro ?? 0),
            );
            if ($pk !== null) {
                $map[$pk] = true;
            }
        }

        return $map;
    }

    /**
     * @param  list<object>  $filasVengrav
     * @return array<string, true>
     */
    public static function indexarVengrav(array $filasVengrav): array
    {
        $map = [];
        foreach ($filasVengrav as $fila) {
            $pk = self::claveVengrav(
                (string) ($fila->veng_tipo ?? ''),
                (string) ($fila->veng_letra ?? 'B'),
                self::sucursalEntera((string) ($fila->veng_sucursal ?? '')),
                (int) ($fila->veng_nro ?? 0),
                (string) ($fila->veng_codigo_tasa ?? ''),
            );
            if ($pk !== null) {
                $map[$pk] = true;
            }
        }

        return $map;
    }

    /**
     * @param  list<object>  $filasVencae
     * @return array<string, true>
     */
    public static function indexarVencae(array $filasVencae): array
    {
        $map = [];
        foreach ($filasVencae as $fila) {
            $pk = self::claveVencae(
                (string) ($fila->venc_tipo ?? ''),
                (string) ($fila->venc_letra ?? 'B'),
                self::sucursalEntera((string) ($fila->venc_sucursal ?? '')),
                (int) ($fila->venc_nro ?? 0),
            );
            if ($pk !== null) {
                $map[$pk] = true;
            }
        }

        return $map;
    }

    public static function sucursalEntera(string $sucursal): int
    {
        return (int) preg_replace('/\D+/', '', trim($sucursal));
    }
}
