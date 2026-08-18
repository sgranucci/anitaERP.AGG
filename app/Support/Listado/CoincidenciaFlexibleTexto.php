<?php

namespace App\Support\Listado;

use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Eloquent\Builder;

/**
 * Coincidencia por prefijo + sufijo en SQL (tolera letras de más/menos: «bandfield» → Banfield).
 */
class CoincidenciaFlexibleTexto
{
    public const LONGITUD_MINIMA_DEFAULT = 6;

    public const LONGITUD_MINIMA_ARTICULO = 5;

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $q
     */
    public static function aplicar(
        Builder $q,
        string $column,
        string $valor,
        bool $orWhere = true,
        int $longitudMinima = self::LONGITUD_MINIMA_DEFAULT
    ): void {
        if (mb_strlen($valor) < $longitudMinima) {
            return;
        }

        $pref = mb_strtolower(mb_substr($valor, 0, 3));
        $longitudSufijo = mb_strlen($valor) >= 8 ? 5 : 4;
        $suf = mb_strtolower(mb_substr($valor, -$longitudSufijo));

        if ($pref === '' || $suf === '' || $pref === $suf) {
            return;
        }

        $expr = SqlDialectSupport::lower($column);
        $callback = function ($w) use ($expr, $pref, $suf) {
            $w->whereRaw($expr.' LIKE ?', ['%'.self::escapeLike($pref).'%'])
                ->whereRaw($expr.' LIKE ?', ['%'.self::escapeLike($suf).'%']);
        };

        if ($orWhere) {
            $q->orWhere($callback);
        } else {
            $q->where($callback);
        }
    }

    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
