<?php

declare(strict_types=1);

namespace App\Support\Listado;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Agrupación de filas estilo SAP Fiori P13n Group / NetSuite summary-by.
 *
 * Contrato en filtros: `agrupar` = list<string> de keys del catálogo (máx. 2).
 * El ORDER BY antepone esas columnas; la vista inserta cabeceras al cambiar de valor.
 */
final class ListadoAgrupacionSupport
{
    public const MAX_NIVELES = 2;

    /**
     * @param  array<string, array{column: string, label?: string, type?: string}>  $camposAgrupables
     * @return list<string>
     */
    public static function resolverDesdeRequest(Request $request, array $camposAgrupables): array
    {
        return self::normalizar($request->input('group', $request->input('agrupar', [])), $camposAgrupables);
    }

    /**
     * @param  mixed  $raw
     * @param  array<string, array{column: string, label?: string, type?: string}>  $camposAgrupables
     * @return list<string>
     */
    public static function normalizar(mixed $raw, array $camposAgrupables): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }
        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (count($out) >= self::MAX_NIVELES) {
                break;
            }
            $campo = is_array($item) ? (string) ($item['campo'] ?? '') : (string) $item;
            if ($campo === '' || ! isset($camposAgrupables[$campo]) || in_array($campo, $out, true)) {
                continue;
            }
            $column = (string) ($camposAgrupables[$campo]['column'] ?? '');
            if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
                continue;
            }
            $out[] = $campo;
        }

        return $out;
    }

    /**
     * @param  list<string>  $agrupar
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $agrupar): array
    {
        if ($agrupar === []) {
            return [];
        }

        return ['group' => array_values($agrupar)];
    }

    /**
     * Antepone ORDER BY de columnas de agrupación (ASC) antes del sort del usuario.
     *
     * @param  list<string>  $agrupar
     * @param  array<string, array{column: string}>  $camposAgrupables
     */
    public static function aplicarOrdenPrefijo(Builder $query, array $agrupar, array $camposAgrupables): void
    {
        foreach ($agrupar as $campo) {
            if (! isset($camposAgrupables[$campo])) {
                continue;
            }
            $column = (string) ($camposAgrupables[$campo]['column'] ?? '');
            if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
                continue;
            }
            $query->orderBy($column, 'asc');
        }
    }

    /**
     * Segmenta filas en cabeceras de grupo + datos (para la página o el export completo).
     *
     * @param  Collection<int, object>|Paginator|iterable<int, object>  $filas
     * @param  list<string>  $agrupar
     * @param  callable(object, string): string  $valorCelda
     * @param  array<string, string>  $etiquetas  key => label
     * @param  array<string, int>|null  $conteosUniverso  path "\0" => count (Pack C); null = conteo de página
     * @return list<array{type: string, nivel?: int, campo?: string, label?: string, valor?: string, count?: int, count_universo?: bool, row?: object}>
     */
    public static function segmentar(
        iterable $filas,
        array $agrupar,
        callable $valorCelda,
        array $etiquetas = [],
        ?array $conteosUniverso = null
    ): array {
        if ($agrupar === []) {
            $out = [];
            foreach ($filas as $row) {
                $out[] = ['type' => 'row', 'row' => $row];
            }

            return $out;
        }

        $items = $filas instanceof Paginator
            ? collect($filas->items())
            : collect($filas);

        if ($items->isEmpty()) {
            return [];
        }

        $usarUniverso = is_array($conteosUniverso) && $conteosUniverso !== [];

        // Fallback: conteos de la página visible
        $conteosPagina = [];
        if (! $usarUniverso) {
            foreach ($items as $row) {
                $path = [];
                foreach ($agrupar as $campo) {
                    $path[] = self::claveValor($valorCelda($row, $campo));
                    $k = implode("\0", $path);
                    $conteosPagina[$k] = ($conteosPagina[$k] ?? 0) + 1;
                }
            }
        }

        $out = [];
        $prev = array_fill(0, count($agrupar), null);

        foreach ($items as $row) {
            $path = [];
            foreach ($agrupar as $nivel => $campo) {
                $valor = self::claveValor($valorCelda($row, $campo));
                $path[] = $valor;
                if ($prev[$nivel] !== $valor || self::cambioAncestro($prev, $path, $nivel)) {
                    for ($i = $nivel; $i < count($agrupar); $i++) {
                        $prev[$i] = null;
                    }
                    $prev[$nivel] = $valor;
                    $k = implode("\0", $path);
                    if ($usarUniverso) {
                        $count = (int) ($conteosUniverso[$k] ?? 0);
                        $esUniverso = array_key_exists($k, $conteosUniverso);
                    } else {
                        $count = (int) ($conteosPagina[$k] ?? 0);
                        $esUniverso = false;
                    }
                    $out[] = [
                        'type' => 'header',
                        'nivel' => $nivel,
                        'campo' => $campo,
                        'label' => $etiquetas[$campo] ?? $campo,
                        'valor' => $valor === '' ? '(vacío)' : $valor,
                        'count' => $count,
                        'count_universo' => $esUniverso,
                    ];
                }
            }
            $out[] = ['type' => 'row', 'row' => $row];
        }

        return $out;
    }

    private static function claveValor(string $valor): string
    {
        return trim($valor);
    }

    /**
     * @param  list<string|null>  $prev
     * @param  list<string>  $path
     */
    private static function cambioAncestro(array $prev, array $path, int $nivel): bool
    {
        for ($i = 0; $i < $nivel; $i++) {
            if (($prev[$i] ?? null) !== ($path[$i] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
