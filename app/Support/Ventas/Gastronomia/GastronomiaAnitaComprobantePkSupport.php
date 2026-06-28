<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Support\Ventas\KandikoAnitaVentaTipoSupport;

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

    /**
     * Clave de conciliación desde código ERP (ej. FAC B-00070-00003156 → FAC|B|70|3156).
     */
    public static function claveVentaDesdeCodigoErp(string $codigo): ?string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        if (preg_match('/^(\S+)\s+([A-Z])-(\d+)-(\d+)$/', $codigo, $m)) {
            return self::claveVenta($m[1], $m[2], self::sucursalEntera($m[3]), (int) $m[4]);
        }

        return null;
    }

    /**
     * Clave desde cabecera Anita (ven_tipo, ven_letra, ven_sucursal, ven_nro).
     */
    public static function claveVentaDesdeCabeceraAnita(object $cab): ?string
    {
        return self::claveVenta(
            (string) ($cab->ven_tipo ?? ''),
            (string) ($cab->ven_letra ?? 'B'),
            self::sucursalEntera((string) ($cab->ven_sucursal ?? '')),
            (int) ($cab->ven_nro ?? 0),
        );
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, numero: int}|null
     */
    public static function parseClaveVenta(string $clave): ?array
    {
        $partes = explode('|', trim($clave));
        if (count($partes) !== 4) {
            return null;
        }

        $tipo = strtoupper(trim($partes[0]));
        $letra = strtoupper(trim($partes[1]));
        $sucursal = (int) $partes[2];
        $numero = (int) $partes[3];
        if ($tipo === '' || $sucursal <= 0 || $numero <= 0) {
            return null;
        }

        return [
            'tipo' => $tipo,
            'letra' => $letra !== '' ? $letra : 'B',
            'sucursal' => $sucursal,
            'numero' => $numero,
        ];
    }

    /**
     * Claves para emparejar cabecera Anita con venta ERP (alias FAC|… si FAK Kandiko CAEA).
     *
     * @return list<string>
     */
    public static function clavesConciliacionDesdeCabeceraAnita(object $cab, bool $incluirAliasFacKandiko = false): array
    {
        $tipo = strtoupper(trim((string) ($cab->ven_tipo ?? '')));
        $nro = (int) ($cab->ven_nro ?? 0);
        $letra = (string) ($cab->ven_letra ?? 'B');
        $sucursal = self::sucursalEntera((string) ($cab->ven_sucursal ?? ''));

        $claves = [];
        $pk = self::claveVenta($tipo, $letra, $sucursal, $nro);
        if ($pk !== null) {
            $claves[] = $pk;
        }

        if ($incluirAliasFacKandiko && in_array($tipo, KandikoAnitaVentaTipoSupport::tiposAnitaEquivalentesFacErp(), true)) {
            $alias = KandikoAnitaVentaTipoSupport::claveConciliacionDesdeNumero($nro, $letra, $sucursal);
            if (! in_array($alias, $claves, true)) {
                $claves[] = $alias;
            }
        }

        return $claves;
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
