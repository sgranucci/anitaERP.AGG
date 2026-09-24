<?php

declare(strict_types=1);

namespace App\Support\Compras;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Expande el listado de proveedores con CBU/alias de proveedor_formapago
 * para exportaciones (Excel/CSV/PDF). Una fila por cuenta con dato bancario;
 * sin cuentas → una fila con CBU/alias vacíos.
 */
final class ProveedorListadoBancarioSupport
{
    /**
     * Agrega cbu / alias_cbu resumidos (varios unidos con " | ") sin expandir filas.
     * Ideal para grilla en pantalla.
     *
     * @param  iterable<int, object>  $proveedores
     * @return Collection<int, object>
     */
    public static function anexarResumenCbuAlias(iterable $proveedores): Collection
    {
        $coleccion = collect($proveedores);
        if ($coleccion->isEmpty()) {
            return collect();
        }

        $ids = $coleccion->pluck('id')->map(static fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $porProveedor = self::cuentasPorProveedor($ids);

        return $coleccion->map(function ($proveedor) use ($porProveedor) {
            $proveedorId = (int) ($proveedor->id ?? 0);
            $cuentas = $porProveedor->get($proveedorId, collect());
            $cbus = [];
            $aliases = [];
            foreach ($cuentas as $cuenta) {
                $cbu = trim((string) ($cuenta->cbu ?? ''));
                $alias = trim((string) ($cuenta->alias_cbu ?? ''));
                if ($cbu !== '') {
                    $cbus[] = $cbu;
                }
                if ($alias !== '') {
                    $aliases[] = $alias;
                }
            }
            $fila = is_object($proveedor) ? clone $proveedor : (object) $proveedor;
            $fila->cbu = implode(' | ', array_values(array_unique($cbus)));
            $fila->alias_cbu = implode(' | ', array_values(array_unique($aliases)));

            return $fila;
        })->values();
    }

    /**
     * @param  iterable<int, object>  $proveedores
     * @return Collection<int, object>
     */
    public static function expandirConCbuAlias(iterable $proveedores): Collection
    {
        $coleccion = collect($proveedores);
        if ($coleccion->isEmpty()) {
            return collect();
        }

        $ids = $coleccion->pluck('id')->map(static fn ($id) => (int) $id)->filter()->unique()->values()->all();
        if ($ids === []) {
            return $coleccion->values();
        }

        $porProveedor = self::cuentasPorProveedor($ids);

        $filas = collect();
        foreach ($coleccion as $proveedor) {
            $proveedorId = (int) ($proveedor->id ?? 0);
            $cuentas = $porProveedor->get($proveedorId, collect());

            if ($cuentas->isEmpty()) {
                $filas->push(self::clonarConBancario($proveedor, '', ''));
                continue;
            }

            foreach ($cuentas as $cuenta) {
                $filas->push(self::clonarConBancario(
                    $proveedor,
                    trim((string) ($cuenta->cbu ?? '')),
                    trim((string) ($cuenta->alias_cbu ?? ''))
                ));
            }
        }

        return $filas;
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Collection<int, object>>
     */
    private static function cuentasPorProveedor(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return DB::table('proveedor_formapago')
            ->whereIn('proveedor_id', $ids)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('cbu')->where('cbu', '!=', '');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('alias_cbu')->where('alias_cbu', '!=', '');
                });
            })
            ->orderBy('proveedor_id')
            ->orderBy('id')
            ->get(['proveedor_id', 'cbu', 'alias_cbu'])
            ->groupBy(static fn ($row) => (int) $row->proveedor_id);
    }

    private static function clonarConBancario(object $proveedor, string $cbu, string $alias): object
    {
        $fila = clone $proveedor;
        $fila->cbu = $cbu;
        $fila->alias_cbu = $alias;

        return $fila;
    }
}
