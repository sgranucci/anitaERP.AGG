<?php

namespace App\Support\Sueldos\ReporteDefinible;

/**
 * Pivot server-side sobre filas materializadas / resultado de ejecución.
 */
final class ReporteSueldosDefiniblePivotSupport
{
    public const UMBRAL_SYNC = 5000;

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array{
     *   filas?:list<string>,
     *   columnas?:list<string>,
     *   medidas?:list<array{campo:string,agregacion?:string}>,
     *   filtros?:array<string,mixed>
     * }  $spec
     * @return array{headers:list<string>,rows:list<list<mixed>>,kpis:array<string,float>,meta:array}
     */
    public function pivotar(array $filas, array $spec): array
    {
        $dimFilas = array_values(array_filter((array) ($spec['filas'] ?? ['grupo_label'])));
        $dimCols = array_values(array_filter((array) ($spec['columnas'] ?? [])));
        $medidas = (array) ($spec['medidas'] ?? [['campo' => 'c1', 'agregacion' => 'sum']]);
        if ($medidas === []) {
            $medidas = [['campo' => 'c1', 'agregacion' => 'sum']];
        }

        $filas = $this->aplicarFiltros($filas, (array) ($spec['filtros'] ?? []));

        $matriz = [];
        $colKeys = [];
        foreach ($filas as $fila) {
            $rowKey = $this->clave($fila, $dimFilas);
            $colKey = $dimCols === [] ? '_total' : $this->clave($fila, $dimCols);
            $colKeys[$colKey] = true;
            foreach ($medidas as $m) {
                $campo = (string) ($m['campo'] ?? 'c1');
                $agg = strtolower((string) ($m['agregacion'] ?? 'sum'));
                $valor = is_numeric($fila[$campo] ?? null) ? (float) $fila[$campo] : 0.0;
                $mk = $colKey.'|'.$campo.'|'.$agg;
                if (! isset($matriz[$rowKey][$mk])) {
                    $matriz[$rowKey][$mk] = ['sum' => 0.0, 'count' => 0, 'min' => null, 'max' => null];
                }
                $celda = &$matriz[$rowKey][$mk];
                $celda['sum'] += $valor;
                $celda['count']++;
                $celda['min'] = $celda['min'] === null ? $valor : min($celda['min'], $valor);
                $celda['max'] = $celda['max'] === null ? $valor : max($celda['max'], $valor);
                unset($celda);
            }
        }

        $colKeys = array_keys($colKeys);
        sort($colKeys);
        $headers = array_merge($dimFilas, []);
        foreach ($colKeys as $ck) {
            foreach ($medidas as $m) {
                $campo = (string) ($m['campo'] ?? 'c1');
                $agg = strtolower((string) ($m['agregacion'] ?? 'sum'));
                $headers[] = ($ck === '_total' ? '' : $ck.' / ').$campo.' ('.$agg.')';
            }
        }

        $rows = [];
        $kpis = [];
        ksort($matriz);
        foreach ($matriz as $rowKey => $celdas) {
            $partes = $rowKey === '' ? [] : explode('||', $rowKey);
            $row = [];
            foreach ($dimFilas as $i => $_) {
                $row[] = $partes[$i] ?? '';
            }
            foreach ($colKeys as $ck) {
                foreach ($medidas as $m) {
                    $campo = (string) ($m['campo'] ?? 'c1');
                    $agg = strtolower((string) ($m['agregacion'] ?? 'sum'));
                    $mk = $ck.'|'.$campo.'|'.$agg;
                    $acc = $celdas[$mk] ?? ['sum' => 0.0, 'count' => 0, 'min' => 0.0, 'max' => 0.0];
                    $valor = match ($agg) {
                        'avg', 'average' => $acc['count'] > 0 ? round($acc['sum'] / $acc['count'], 4) : 0.0,
                        'count' => (float) $acc['count'],
                        'min' => (float) ($acc['min'] ?? 0),
                        'max' => (float) ($acc['max'] ?? 0),
                        default => round((float) $acc['sum'], 4),
                    };
                    $row[] = $valor;
                    $kpis[$campo.'_'.$agg] = ($kpis[$campo.'_'.$agg] ?? 0) + (float) $acc['sum'];
                }
            }
            $rows[] = $row;
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'kpis' => array_map(fn ($v) => round((float) $v, 2), $kpis),
            'meta' => [
                'cantidad_filas_origen' => count($filas),
                'cantidad_filas_pivot' => count($rows),
                'async' => count($filas) > self::UMBRAL_SYNC,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function aplicarFiltros(array $filas, array $filtros): array
    {
        if ($filtros === []) {
            return $filas;
        }
        return array_values(array_filter($filas, function (array $fila) use ($filtros) {
            foreach ($filtros as $campo => $valor) {
                if ($valor === null || $valor === '' || $valor === []) {
                    continue;
                }
                if (is_array($valor)) {
                    if (! in_array($fila[$campo] ?? null, $valor, false)) {
                        return false;
                    }
                } elseif ((string) ($fila[$campo] ?? '') !== (string) $valor) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  list<string>  $dims
     */
    private function clave(array $fila, array $dims): string
    {
        $partes = [];
        foreach ($dims as $d) {
            $partes[] = (string) ($fila[$d] ?? '');
        }

        return implode('||', $partes);
    }
}
