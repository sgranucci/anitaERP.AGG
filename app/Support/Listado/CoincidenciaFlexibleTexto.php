<?php

namespace App\Support\Listado;

use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Eloquent\Builder;

/**
 * Coincidencia por prefijo + sufijo en SQL (tolera letras de más/menos: «bandfield» → Banfield).
 *
 * Con {@see LONGITUD_MINIMA_CORTA} usa pares de 2 letras (igual que el modal de clientes).
 */
class CoincidenciaFlexibleTexto
{
    public const LONGITUD_MINIMA_DEFAULT = 6;

    public const LONGITUD_MINIMA_ARTICULO = 5;

    /** Modal / index de clientes: parecido desde 2 letras (prefijo 2 + sufijo 2). */
    public const LONGITUD_MINIMA_CORTA = 2;

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
        $pares = self::prefijoYSufijo($valor, $longitudMinima);
        if ($pares === null) {
            return;
        }

        [$pref, $suf] = $pares;

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

    /**
     * @return array{0: string, 1: string}|null
     */
    private static function prefijoYSufijo(string $valor, int $longitudMinima): ?array
    {
        $len = mb_strlen($valor);
        if ($len < $longitudMinima) {
            return null;
        }

        if ($longitudMinima <= self::LONGITUD_MINIMA_CORTA) {
            if ($len < 4) {
                return null;
            }
            $pref = mb_strtolower(mb_substr($valor, 0, 2));
            $suf = mb_strtolower(mb_substr($valor, -2));
        } else {
            $pref = mb_strtolower(mb_substr($valor, 0, 3));
            $longitudSufijo = $len >= 8 ? 5 : 4;
            $suf = mb_strtolower(mb_substr($valor, -$longitudSufijo));
        }

        if ($pref === '' || $suf === '' || $pref === $suf) {
            return null;
        }

        return [$pref, $suf];
    }

    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
