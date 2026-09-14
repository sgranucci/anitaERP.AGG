<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Proveedor;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Collection;

/**
 * Resuelve proveedores del reporte CC según alcance: todos / puntuales / rango.
 */
final class ProveedorCuentacorrienteReporteProveedorSupport
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{ids: list<int>, faltantes_rango: list<string>, iniciales: list<array{id:int,codigo:string,nombre:string}>}
     */
    public static function resolver(array $filtros): array
    {
        $alcance = (string) ($filtros['alcance_proveedores'] ?? ProveedorCuentacorrienteReporteFiltros::ALCANCE_TODOS);

        return match ($alcance) {
            ProveedorCuentacorrienteReporteFiltros::ALCANCE_PUNTUALES => self::desdePuntuales($filtros),
            ProveedorCuentacorrienteReporteFiltros::ALCANCE_RANGO => self::desdeRango($filtros),
            default => ['ids' => [], 'faltantes_rango' => [], 'iniciales' => []],
        };
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id:int,codigo:string,nombre:string}>
     */
    public static function etiquetasPorIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Proveedor::query()
            ->whereIn('id', $ids)
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
            ->get(['id', 'codigo', 'nombre'])
            ->map(static fn (Proveedor $p) => [
                'id' => (int) $p->id,
                'codigo' => trim((string) $p->codigo),
                'nombre' => (string) $p->nombre,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{ids: list<int>, faltantes_rango: list<string>, iniciales: list<array{id:int,codigo:string,nombre:string}>}
     */
    private static function desdePuntuales(array $filtros): array
    {
        $ids = array_values(array_filter(
            array_map('intval', $filtros['proveedor_ids'] ?? []),
            static fn (int $id) => $id > 0
        ));
        $iniciales = self::etiquetasPorIds($ids);

        return [
            'ids' => array_column($iniciales, 'id'),
            'faltantes_rango' => [],
            'iniciales' => $iniciales,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{ids: list<int>, faltantes_rango: list<string>, iniciales: list<array{id:int,codigo:string,nombre:string}>}
     */
    private static function desdeRango(array $filtros): array
    {
        $desde = trim((string) ($filtros['proveedor_codigo_desde'] ?? ''));
        $hasta = trim((string) ($filtros['proveedor_codigo_hasta'] ?? ''));

        if ($desde === '' && $hasta === '') {
            return ['ids' => [], 'faltantes_rango' => [], 'iniciales' => []];
        }
        if ($desde === '') {
            $desde = $hasta;
        }
        if ($hasta === '') {
            $hasta = $desde;
        }

        $desdeNum = self::codigoAEntero($desde);
        $hastaNum = self::codigoAEntero($hasta);

        if ($desdeNum !== null && $hastaNum !== null) {
            if ($desdeNum > $hastaNum) {
                [$desdeNum, $hastaNum] = [$hastaNum, $desdeNum];
            }
            $cast = SqlDialectSupport::castEntero('codigo');
            /** @var Collection<int, Proveedor> $proveedores */
            $proveedores = Proveedor::query()
                ->whereRaw($cast.' BETWEEN ? AND ?', [$desdeNum, $hastaNum])
                ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
                ->get(['id', 'codigo', 'nombre']);
        } else {
            if (strcmp($desde, $hasta) > 0) {
                [$desde, $hasta] = [$hasta, $desde];
            }
            /** @var Collection<int, Proveedor> $proveedores */
            $proveedores = Proveedor::query()
                ->where('codigo', '>=', $desde)
                ->where('codigo', '<=', $hasta)
                ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
                ->get(['id', 'codigo', 'nombre']);
        }

        $iniciales = $proveedores->map(static fn (Proveedor $p) => [
            'id' => (int) $p->id,
            'codigo' => trim((string) $p->codigo),
            'nombre' => (string) $p->nombre,
        ])->values()->all();

        $faltantes = [];
        if ($iniciales === []) {
            $faltantes[] = $desde.($desde !== $hasta ? '–'.$hasta : '');
        }

        return [
            'ids' => array_column($iniciales, 'id'),
            'faltantes_rango' => $faltantes,
            'iniciales' => $iniciales,
        ];
    }

    private static function codigoAEntero(string $codigo): ?int
    {
        $codigo = trim($codigo);
        if ($codigo === '' || ! preg_match('/^\d+$/', $codigo)) {
            return null;
        }

        return (int) $codigo;
    }
}
