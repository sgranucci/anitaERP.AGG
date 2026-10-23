<?php

declare(strict_types=1);

namespace App\Support\Stock;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Saldo del depósito de entrega para el listado de artículos.
 *
 * Una fila por artículo: suma de articulo_saldo_deposito en depositoentrega_id.
 * Sin depósito de entrega el valor queda nulo. Con depósito y sin movimientos, cero.
 */
final class ArticuloListadoSaldoSupport
{
    public const CLAVE = 'saldo';

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function debeAplicar(array $filtros): bool
    {
        foreach ((array) ($filtros['_columnas'] ?? []) as $key) {
            if ((string) $key === self::CLAVE) {
                return true;
            }
        }
        if (is_string($filtros['columnas'] ?? null) && in_array(self::CLAVE, array_map('trim', explode(',', (string) $filtros['columnas'])), true)) {
            return true;
        }
        foreach (['sort', 'agrupar'] as $bloque) {
            foreach ((array) ($filtros[$bloque] ?? []) as $fila) {
                $campo = is_array($fila) ? (string) ($fila['campo'] ?? '') : (string) $fila;
                if ($campo === self::CLAVE) {
                    return true;
                }
            }
        }
        $qbe = is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : [];
        foreach ((array) ($qbe['grupos'] ?? []) as $grupo) {
            foreach ((array) ($grupo['criterios'] ?? []) as $criterio) {
                if ((string) ($criterio['campo'] ?? '') === self::CLAVE) {
                    return true;
                }
            }
        }
        if ((string) ($filtros['campo'] ?? '') === self::CLAVE && ($filtros['modo'] ?? '') === 'campo') {
            return true;
        }
        $graficos = is_array($filtros['graficos'] ?? null) ? $filtros['graficos'] : [];
        if ($graficos === [] && is_array($filtros['grafico'] ?? null)) {
            $graficos = [$filtros['grafico']];
        }
        foreach ($graficos as $grafico) {
            if (! is_array($grafico)) {
                continue;
            }
            if ((string) ($grafico['medida'] ?? '') === self::CLAVE || (string) ($grafico['dimension'] ?? '') === self::CLAVE) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    public static function aplicar(Builder $query): void
    {
        $query->leftJoinSub(self::subconsulta(), 'saldo_dep', 'saldo_dep.articulo_id', '=', 'articulo.id');
        $query->addSelect('saldo_dep.saldo as saldo');
    }

    /** @return list<array{key: string, column: string, label: string}> */
    public static function medidas(): array
    {
        return [
            ['key' => self::CLAVE, 'column' => 'saldo_dep.saldo', 'label' => 'Saldo dep.'],
        ];
    }

    private static function subconsulta(): QueryBuilder
    {
        return DB::table('articulo as art_dep')
            ->leftJoin('articulo_saldo_deposito as asd', function ($join): void {
                $join->on('asd.articulo_id', '=', 'art_dep.id')
                    ->on('asd.deposito_id', '=', 'art_dep.depositoentrega_id');
            })
            ->where('art_dep.depositoentrega_id', '>', 0)
            ->groupBy('art_dep.id')
            ->select('art_dep.id as articulo_id')
            ->selectRaw('COALESCE(SUM(asd.cantidad), 0) as saldo');
    }
}
