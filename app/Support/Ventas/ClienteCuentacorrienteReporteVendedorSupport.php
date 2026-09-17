<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Vendedor;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Collection;

/**
 * Resuelve vendedores del reporte CC según alcance: todos / puntuales / rango.
 */
final class ClienteCuentacorrienteReporteVendedorSupport
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{ids: list<int>, faltantes_rango: list<string>, iniciales: list<array{id:int,codigo:string,nombre:string}>}
     */
    public static function resolver(array $filtros): array
    {
        $alcance = (string) ($filtros['alcance_vendedores'] ?? ClienteCuentacorrienteReporteFiltros::ALCANCE_TODOS);

        return match ($alcance) {
            ClienteCuentacorrienteReporteFiltros::ALCANCE_PUNTUALES => self::desdePuntuales($filtros),
            ClienteCuentacorrienteReporteFiltros::ALCANCE_RANGO => self::desdeRango($filtros),
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

        return Vendedor::query()
            ->whereIn('id', $ids)
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
            ->get(['id', 'codigo', 'nombre'])
            ->map(static fn (Vendedor $v) => [
                'id' => (int) $v->id,
                'codigo' => trim((string) $v->codigo),
                'nombre' => (string) $v->nombre,
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
            array_map('intval', $filtros['vendedor_ids'] ?? []),
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
        $desde = trim((string) ($filtros['vendedor_codigo_desde'] ?? ''));
        $hasta = trim((string) ($filtros['vendedor_codigo_hasta'] ?? ''));

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
            /** @var Collection<int, Vendedor> $vendedores */
            $vendedores = Vendedor::query()
                ->whereRaw($cast.' BETWEEN ? AND ?', [$desdeNum, $hastaNum])
                ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
                ->get(['id', 'codigo', 'nombre']);
        } else {
            if (strcmp($desde, $hasta) > 0) {
                [$desde, $hasta] = [$hasta, $desde];
            }
            /** @var Collection<int, Vendedor> $vendedores */
            $vendedores = Vendedor::query()
                ->where('codigo', '>=', $desde)
                ->where('codigo', '<=', $hasta)
                ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
                ->get(['id', 'codigo', 'nombre']);
        }

        $iniciales = $vendedores->map(static fn (Vendedor $v) => [
            'id' => (int) $v->id,
            'codigo' => trim((string) $v->codigo),
            'nombre' => (string) $v->nombre,
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
