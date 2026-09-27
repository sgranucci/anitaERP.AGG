<?php

declare(strict_types=1);

namespace App\Support\Listado;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Ordenamiento multi-criterio de listados workbench (paridad Fiori P13n / NetSuite Saved Search).
 *
 * Contrato en filtros: `orden` = list<{campo, dir}> con dir asc|desc.
 * Solo columnas whitelist del catálogo (`source` table.column).
 */
final class ListadoOrdenamientoSupport
{
    public const DIR_ASC = 'asc';

    public const DIR_DESC = 'desc';

    /** Máximo de criterios (Fiori permite N; NetSuite 3; dejamos margen). */
    public const MAX_CRITERIOS = 5;

    /**
     * @param  array<string, array{column: string, label?: string, type?: string}>  $camposOrdenables
     * @return list<array{campo: string, dir: string}>
     */
    public static function resolverDesdeRequest(Request $request, array $camposOrdenables): array
    {
        return self::normalizar($request->input('sort', []), $camposOrdenables);
    }

    /**
     * @param  mixed  $raw
     * @param  array<string, array{column: string, label?: string, type?: string}>  $camposOrdenables
     * @return list<array{campo: string, dir: string}>
     */
    public static function normalizar(mixed $raw, array $camposOrdenables): array
    {
        if (! is_array($raw) || $raw === [] || $camposOrdenables === []) {
            return [];
        }

        $filas = [];
        if (array_is_list($raw) || isset($raw[0])) {
            foreach ($raw as $fila) {
                if (is_array($fila)) {
                    $filas[] = $fila;
                }
            }
        } else {
            // Legacy / atajo: sort[campo]=asc
            foreach ($raw as $campo => $dir) {
                if (is_string($campo)) {
                    $filas[] = ['campo' => $campo, 'dir' => $dir];
                }
            }
        }

        $out = [];
        $vistos = [];
        foreach ($filas as $fila) {
            if (count($out) >= self::MAX_CRITERIOS) {
                break;
            }
            $campo = (string) ($fila['campo'] ?? '');
            if ($campo === '' || ! isset($camposOrdenables[$campo]) || isset($vistos[$campo])) {
                continue;
            }
            $column = (string) ($camposOrdenables[$campo]['column'] ?? '');
            if (! self::esColumnaSqlSegura($column)) {
                continue;
            }
            $dir = self::normalizarDir((string) ($fila['dir'] ?? self::DIR_ASC));
            $out[] = ['campo' => $campo, 'dir' => $dir];
            $vistos[$campo] = true;
        }

        return $out;
    }

    public static function normalizarDir(string $dir): string
    {
        $dir = strtolower(trim($dir));

        return $dir === self::DIR_DESC ? self::DIR_DESC : self::DIR_ASC;
    }

    public static function esColumnaSqlSegura(string $column): bool
    {
        return (bool) preg_match('/^[a-z_][a-z0-9_]*\.[a-z_][a-z0-9_]*$/i', $column);
    }

    /**
     * @param  list<array{campo: string, dir: string}>  $orden
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $orden): array
    {
        if ($orden === []) {
            return [];
        }

        $params = [];
        foreach (array_values($orden) as $i => $criterio) {
            $params['sort'][$i] = [
                'campo' => $criterio['campo'],
                'dir' => $criterio['dir'],
            ];
        }

        return $params;
    }

    /**
     * @param  list<array{campo: string, dir: string}>  $orden
     * @param  array<string, array{column: string, label?: string, type?: string}>  $camposOrdenables
     * @param  array{campo: string, dir: string}|null  $fallback  Ej. id DESC si no hay criterios
     */
    public static function aplicar(
        Builder $query,
        array $orden,
        array $camposOrdenables,
        ?array $fallback = null
    ): void {
        $aplicados = 0;
        foreach ($orden as $criterio) {
            $campo = (string) ($criterio['campo'] ?? '');
            if (! isset($camposOrdenables[$campo])) {
                continue;
            }
            $column = (string) ($camposOrdenables[$campo]['column'] ?? '');
            if (! self::esColumnaSqlSegura($column)) {
                continue;
            }
            $dir = self::normalizarDir((string) ($criterio['dir'] ?? self::DIR_ASC));
            $query->orderBy($column, $dir);
            $aplicados++;
        }

        if ($aplicados > 0) {
            return;
        }

        if ($fallback === null) {
            return;
        }

        $campo = (string) ($fallback['campo'] ?? '');
        if (! isset($camposOrdenables[$campo])) {
            return;
        }
        $column = (string) ($camposOrdenables[$campo]['column'] ?? '');
        if (! self::esColumnaSqlSegura($column)) {
            return;
        }
        $query->orderBy($column, self::normalizarDir((string) ($fallback['dir'] ?? self::DIR_DESC)));
    }

    /**
     * Click en thead: primer criterio = columna; si ya era el primero, invierte dirección.
     *
     * @param  list<array{campo: string, dir: string}>  $ordenActual
     * @return list<array{campo: string, dir: string}>
     */
    public static function togglePrimario(array $ordenActual, string $campo, array $camposOrdenables): array
    {
        if (! isset($camposOrdenables[$campo])) {
            return $ordenActual;
        }

        $actual = $ordenActual[0] ?? null;
        if ($actual && ($actual['campo'] ?? '') === $campo) {
            $nuevaDir = self::normalizarDir((string) ($actual['dir'] ?? self::DIR_ASC)) === self::DIR_ASC
                ? self::DIR_DESC
                : self::DIR_ASC;

            return array_merge(
                [['campo' => $campo, 'dir' => $nuevaDir]],
                array_slice($ordenActual, 1)
            );
        }

        return array_merge(
            [['campo' => $campo, 'dir' => self::DIR_ASC]],
            array_values(array_filter(
                $ordenActual,
                static fn (array $c): bool => ($c['campo'] ?? '') !== $campo
            ))
        );
    }

    /**
     * @param  list<array{campo: string, dir: string}>  $orden
     */
    public static function direccionDeCampo(array $orden, string $campo): ?string
    {
        foreach ($orden as $criterio) {
            if (($criterio['campo'] ?? '') === $campo) {
                return self::normalizarDir((string) ($criterio['dir'] ?? self::DIR_ASC));
            }
        }

        return null;
    }

    /**
     * @param  list<array{campo: string, dir: string}>  $orden
     */
    public static function indiceDeCampo(array $orden, string $campo): ?int
    {
        foreach ($orden as $i => $criterio) {
            if (($criterio['campo'] ?? '') === $campo) {
                return (int) $i;
            }
        }

        return null;
    }
}
