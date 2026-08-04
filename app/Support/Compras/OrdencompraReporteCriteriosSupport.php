<?php

namespace App\Support\Compras;

use Illuminate\Database\Query\Builder;

/**
 * Criterios auxiliares del informe de OC (proveedores, subtítulos).
 */
final class OrdencompraReporteCriteriosSupport
{
    public static function aplicarFiltroProveedoresCodigo(
        Builder $query,
        string $proveedores,
        string $columna = 'p.codigo',
    ): void {
        $proveedores = trim($proveedores);
        if ($proveedores === '') {
            return;
        }

        $lista = RequisicionReporteCriteriosSupport::parseListaCodigos($proveedores);
        if ($lista === []) {
            return;
        }

        $query->whereIn($columna, $lista);
    }

    public static function subtituloProveedores(array $filtros): ?string
    {
        $valor = trim((string) ($filtros['proveedores'] ?? ''));
        if ($valor === '') {
            return null;
        }

        return 'Proveedores: '.$valor;
    }

    public static function subtituloOrdenescompra(array $filtros): ?string
    {
        $desde = trim((string) ($filtros['ordencompra_desde'] ?? ''));
        $hasta = trim((string) ($filtros['ordencompra_hasta'] ?? ''));
        if ($desde === '' && $hasta === '') {
            return null;
        }

        if ($desde !== '' && $hasta !== '') {
            return 'OC: '.$desde.' — '.$hasta;
        }

        return 'OC: '.($desde !== '' ? $desde : $hasta);
    }

    public static function metaTextoProveedores(string $proveedores): string
    {
        $proveedores = trim($proveedores);
        if ($proveedores === '') {
            return 'Todos los proveedores';
        }

        if (str_contains($proveedores, ',') || str_contains($proveedores, ';')) {
            $lista = RequisicionReporteCriteriosSupport::parseListaCodigos($proveedores);

            return count($lista) > 1
                ? 'Lista proveedores ('.count($lista).'): '.implode(', ', $lista)
                : 'Lista proveedores';
        }

        return $proveedores;
    }
}
