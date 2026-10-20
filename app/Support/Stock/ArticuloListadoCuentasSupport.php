<?php

declare(strict_types=1);

namespace App\Support\Stock;

use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Cuentas contables del artículo en el listado, una columna por tipo de imputación.
 *
 * articulo_cuentacontable es N filas (empresa × tipo). El join se agrupa por artículo
 * para no duplicar la grilla. Si el código coincide entre empresas queda un solo texto;
 * si difiere, se listan los distintos separados por « | ».
 *
 * El join se aplica solo si la vista, el orden o el corte piden alguna de estas columnas.
 */
final class ArticuloListadoCuentasSupport
{
    /** @var array<string, string> alias de columna => tipoimputacion */
    public const TIPOS = [
        'cuentaventa' => 'VENTAS',
        'cuentacompra' => 'COMPRAS',
        'cuentagasto' => 'GASTOS',
    ];

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $columnasVisibles
     * @return array<string, mixed>
     */
    public static function marcarColumnas(array $filtros, array $columnasVisibles): array
    {
        $filtros['_columnas'] = array_values($columnasVisibles);

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function debeAplicar(array $filtros): bool
    {
        $keys = array_keys(self::TIPOS);
        foreach ((array) ($filtros['_columnas'] ?? []) as $key) {
            if (in_array((string) $key, $keys, true)) {
                return true;
            }
        }
        foreach (['sort', 'agrupar'] as $bloque) {
            foreach ((array) ($filtros[$bloque] ?? []) as $fila) {
                $campo = is_array($fila) ? (string) ($fila['campo'] ?? '') : '';
                if (in_array($campo, $keys, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    public static function aplicar(Builder $query, ?int $empresaId = null): void
    {
        $query->leftJoinSub(
            self::subconsulta($empresaId),
            'cta_art',
            'cta_art.articulo_id',
            '=',
            'articulo.id'
        );
        $query->addSelect(self::columnasSelect());
    }

    /** @return list<string> */
    public static function columnasSelect(): array
    {
        $out = [];
        foreach (array_keys(self::TIPOS) as $alias) {
            $out[] = 'cta_art.'.$alias.' as '.$alias;
        }

        return $out;
    }

    public static function columnaSql(string $alias): string
    {
        return 'cta_art.'.$alias;
    }

    private static function subconsulta(?int $empresaId): QueryBuilder
    {
        $etiqueta = self::expresionEtiqueta();
        $query = DB::table('articulo_cuentacontable as acc')
            ->join('cuentacontable as cc', 'cc.id', '=', 'acc.cuentacontable_id')
            ->whereIn('acc.tipoimputacion', array_values(self::TIPOS))
            ->where('acc.cuentacontable_id', '>', 0)
            ->groupBy('acc.articulo_id')
            ->select('acc.articulo_id');

        foreach (self::TIPOS as $alias => $tipo) {
            $caso = "CASE WHEN acc.tipoimputacion = '".$tipo."' THEN ".$etiqueta.' END';
            $query->selectRaw(SqlDialectSupport::textosDistintos($caso).' as '.$alias);
        }

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('acc.empresa_id', $empresaId);
        }

        return $query;
    }

    private static function expresionEtiqueta(): string
    {
        return SqlDialectSupport::concatenar(
            SqlDialectSupport::textoOVacio('cc.codigo'),
            "' — '",
            SqlDialectSupport::textoOVacio('cc.nombre')
        );
    }
}
