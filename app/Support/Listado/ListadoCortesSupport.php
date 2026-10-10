<?php

declare(strict_types=1);

namespace App\Support\Listado;

use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Eloquent\Builder;
use stdClass;

/**
 * Pack C: totales / cortes por agrupación sobre el universo filtrado (no la página).
 *
 * COUNT(DISTINCT pk) y, si el recurso declara medidas, SUM de cada importe.
 */
final class ListadoCortesSupport
{
    public const MAX_FILAS = 500;

    /**
     * Calcula conteos por path de grupo (nivel 1 y 2) con la misma query filtrada del listado.
     *
     * @param  list<string>  $agrupar
     * @param  array<string, array{column: string, label?: string, type?: string}>  $campos
     * @param  callable(string): array{select: list<string>, groupBy: list<string>}|null  $sqlAgrupacion
     * @param  callable(object, string): string  $valorCelda
     * @param  list<array{key: string, column: string, label?: string}>  $medidas
     * @return array{
     *   activo: bool,
     *   por_clave: array<string, int>,
     *   filas: list<array{nivel: int, path: list<string>, label: string, valor: string, count: int, sumas?: array<string, float>, parent?: string}>,
     *   total: int,
     *   sumas_total: array<string, float>,
     *   medidas: list<array{key: string, label: string}>,
     *   grupos_nivel0: int,
     *   truncado: bool
     * }
     */
    public static function calcular(
        Builder $queryFiltrado,
        array $agrupar,
        array $campos,
        string $countDistinctColumn,
        callable $valorCelda,
        ?callable $sqlAgrupacion = null,
        array $etiquetas = [],
        array $medidas = []
    ): array {
        $medidas = self::medidasSeguras($medidas);
        $vacio = [
            'activo' => false,
            'por_clave' => [],
            'filas' => [],
            'total' => 0,
            'sumas_total' => [],
            'medidas' => [],
            'grupos_nivel0' => 0,
            'truncado' => false,
        ];

        $agrupar = array_values(array_filter($agrupar, static fn ($c) => is_string($c) && $c !== '' && isset($campos[$c])));
        if ($agrupar === []) {
            return $vacio;
        }
        if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($countDistinctColumn)) {
            return $vacio;
        }

        $selects = [];
        $groupBy = [];
        foreach ($agrupar as $campo) {
            $expr = $sqlAgrupacion
                ? $sqlAgrupacion($campo)
                : self::sqlAgrupacionDefault($campo, $campos[$campo] ?? []);
            if ($expr === null) {
                return $vacio;
            }
            foreach ($expr['select'] as $s) {
                $selects[] = $s;
            }
            foreach ($expr['groupBy'] as $g) {
                if (! self::expresionAgrupacionSegura($g)) {
                    return $vacio;
                }
                $groupBy[] = $g;
            }
        }

        if ($selects === [] || $groupBy === []) {
            return $vacio;
        }

        $q = $queryFiltrado->clone();
        $q->getQuery()->orders = null;
        $q->getQuery()->unionOrders = null;
        $q->getQuery()->limit = null;
        $q->getQuery()->offset = null;
        $q->getQuery()->groups = null;
        $q->getQuery()->havings = null;
        $q->getQuery()->columns = null;
        $q->setBindings([], 'order');
        $q->setBindings([], 'select');

        $sumSql = '';
        foreach ($medidas as $medida) {
            $sumSql .= ', SUM('.$medida['column'].') as _lw_sum_'.$medida['key'];
        }

        $q->selectRaw(implode(', ', $selects).', COUNT(DISTINCT '.$countDistinctColumn.') as _lw_count'.$sumSql);
        foreach ($groupBy as $g) {
            if (ListadoOrdenamientoSupport::esColumnaSqlSegura($g)) {
                $q->groupBy($g);
            } else {
                // DATE(col) y equivalentes: groupBy() las cita como nombre de columna.
                $q->groupByRaw($g);
            }
        }
        $q->orderByRaw('1');
        $q->limit(self::MAX_FILAS + 1);

        $rows = $q->get();
        $truncado = $rows->count() > self::MAX_FILAS;
        if ($truncado) {
            $rows = $rows->take(self::MAX_FILAS);
        }

        $porClave = [];
        $sumasPorClave = [];
        $filasAcc = [];
        $total = 0;
        $sumasTotal = [];
        $nivel0 = [];

        foreach ($rows as $raw) {
            $fake = self::filaParaValorCelda($raw);
            $path = [];
            $count = (int) ($raw->_lw_count ?? 0);
            $total += $count;
            $sumasFila = [];
            foreach ($medidas as $medida) {
                $alias = '_lw_sum_'.$medida['key'];
                $sumasFila[$medida['key']] = round((float) ($raw->{$alias} ?? 0), 2);
                $sumasTotal[$medida['key']] = round(($sumasTotal[$medida['key']] ?? 0) + $sumasFila[$medida['key']], 2);
            }

            foreach ($agrupar as $nivel => $campo) {
                $valor = trim($valorCelda($fake, $campo));
                $path[] = $valor;
                $k = implode("\0", $path);
                $porClave[$k] = ($porClave[$k] ?? 0) + $count;
                foreach ($sumasFila as $mk => $mv) {
                    $sumasPorClave[$k][$mk] = round(($sumasPorClave[$k][$mk] ?? 0) + $mv, 2);
                }
            }

            $nivel = count($agrupar) - 1;
            $campo = $agrupar[$nivel];
            $leafKey = implode("\0", $path);
            if (! isset($filasAcc[$leafKey])) {
                $filasAcc[$leafKey] = [
                    'nivel' => $nivel,
                    'path' => $path,
                    'label' => $etiquetas[$campo] ?? ($campos[$campo]['label'] ?? $campo),
                    'valor' => ($path[$nivel] ?? '') === '' ? '(vacío)' : $path[$nivel],
                    'count' => 0,
                    'sumas' => [],
                    'parent' => $nivel > 0 ? implode("\0", array_slice($path, 0, -1)) : null,
                ];
            }
            $filasAcc[$leafKey]['count'] += $count;
            foreach ($sumasFila as $mk => $mv) {
                $filasAcc[$leafKey]['sumas'][$mk] = round(($filasAcc[$leafKey]['sumas'][$mk] ?? 0) + $mv, 2);
            }
            $nivel0[$path[0] ?? ''] = true;
        }

        $filasUi = array_values($filasAcc);

        // Filas UI de nivel 0 (padres) cuando hay 2 niveles
        if (count($agrupar) > 1) {
            $campo0 = $agrupar[0];
            $padres = [];
            foreach ($porClave as $k => $c) {
                if (substr_count($k, "\0") === 0) {
                    $padres[] = [
                        'nivel' => 0,
                        'path' => [$k],
                        'label' => $etiquetas[$campo0] ?? ($campos[$campo0]['label'] ?? $campo0),
                    'valor' => $k === '' ? '(vacío)' : $k,
                    'count' => $c,
                    'sumas' => $sumasPorClave[$k] ?? [],
                    'parent' => null,
                    ];
                }
            }
            usort($padres, static fn ($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['valor'], $b['valor']));
            usort($filasUi, static fn ($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['valor'], $b['valor']));
            $filasUi = array_merge($padres, $filasUi);
        } else {
            usort($filasUi, static fn ($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['valor'], $b['valor']));
        }

        $medidasUi = [];
        foreach ($medidas as $medida) {
            $medidasUi[] = [
                'key' => $medida['key'],
                'label' => $medida['label'] !== '' ? $medida['label'] : $medida['key'],
            ];
        }

        return [
            'activo' => true,
            'por_clave' => $porClave,
            'filas' => $filasUi,
            'total' => $total,
            'sumas_total' => $sumasTotal,
            'medidas' => $medidasUi,
            'grupos_nivel0' => count($nivel0),
            'truncado' => $truncado,
        ];
    }

    /**
     * @param  list<array{key?: string, column?: string, label?: string}>  $medidas
     * @return list<array{key: string, column: string, label: string}>
     */
    private static function medidasSeguras(array $medidas): array
    {
        $out = [];
        foreach ($medidas as $medida) {
            $key = (string) ($medida['key'] ?? '');
            $column = (string) ($medida['column'] ?? '');
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                continue;
            }
            if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'column' => $column,
                'label' => (string) ($medida['label'] ?? $key),
            ];
        }

        return $out;
    }

    private static function expresionAgrupacionSegura(string $expr): bool
    {
        if (ListadoOrdenamientoSupport::esColumnaSqlSegura($expr)) {
            return true;
        }
        if (preg_match('/^DATE\(([a-z_][a-z0-9_]*\.[a-z_][a-z0-9_]*)\)$/i', $expr)) {
            return true;
        }
        if (preg_match('/^\(([a-z_][a-z0-9_]*\.[a-z_][a-z0-9_]*)\)::date$/i', $expr)) {
            return true;
        }
        if (preg_match('/[;]|--|\/\*|#/', $expr)) {
            return false;
        }

        return (bool) preg_match("/^[A-Za-z0-9_.,()'\\s=]+$/", $expr);
    }

    /**
     * @param  array{column?: string, source?: string, attr?: string, label?: string}  $meta
     * @return array{select: list<string>, groupBy: list<string>}|null
     */
    public static function sqlAgrupacionDefault(string $campo, array $meta): ?array
    {
        $column = (string) ($meta['column'] ?? $meta['source'] ?? '');
        $attr = (string) ($meta['attr'] ?? $campo);
        if ($column === '' || ! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
            return null;
        }
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $attr)) {
            $attr = 'g_'.$campo;
            $attr = preg_replace('/[^A-Za-z0-9_]/', '_', $attr) ?: 'g_val';
        }

        if (($meta['type'] ?? '') === 'fecha') {
            $expr = SqlDialectSupport::fecha($column);

            return [
                'select' => [$expr.' as '.$attr],
                'groupBy' => [$expr],
            ];
        }

        return [
            'select' => [$column.' as '.$attr],
            'groupBy' => [$column],
        ];
    }

    private static function filaParaValorCelda(object $raw): stdClass
    {
        $o = new stdClass;
        $attrs = $raw instanceof \Illuminate\Database\Eloquent\Model
            ? $raw->getAttributes()
            : get_object_vars($raw);
        foreach ($attrs as $k => $v) {
            if ($k === '_lw_count') {
                continue;
            }
            $o->{$k} = $v;
        }

        return $o;
    }

    /**
     * Resumen corto para chips / preview.
     *
     * @param  array{filas?: list, grupos_nivel0?: int, total?: int, truncado?: bool}  $cortes
     * @return list<string>
     */
    public static function chipsResumen(array $cortes, int $max = 6): array
    {
        if (empty($cortes['activo'])) {
            return [];
        }
        $chips = [
            'Cortes: '.(int) ($cortes['grupos_nivel0'] ?? 0).' grupos · '.(int) ($cortes['total'] ?? 0).' regs.',
        ];
        $n = 0;
        foreach ($cortes['filas'] ?? [] as $f) {
            if ((int) ($f['nivel'] ?? 0) !== 0) {
                continue;
            }
            if ($n >= $max) {
                $chips[] = '…';
                break;
            }
            $chips[] = ($f['valor'] ?? '').' ('.(int) ($f['count'] ?? 0).')';
            $n++;
        }
        if (! empty($cortes['truncado'])) {
            $chips[] = 'Lista truncada';
        }

        return $chips;
    }
}
