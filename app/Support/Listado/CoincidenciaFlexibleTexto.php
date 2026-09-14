<?php

namespace App\Support\Listado;

use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Eloquent\Builder;

/**
 * Coincidencia por prefijo + sufijo en SQL (tolera letras de más/menos: «bandfield» → Banfield).
 *
 * Con {@see LONGITUD_MINIMA_CORTA} solo aplica pares de 2 letras en textos cortos (4–5);
 * a partir de 6 caracteres usa el mismo criterio que {@see LONGITUD_MINIMA_DEFAULT}.
 */
class CoincidenciaFlexibleTexto
{
    public const LONGITUD_MINIMA_DEFAULT = 6;

    public const LONGITUD_MINIMA_ARTICULO = 5;

    /** Textos muy cortos: parecido desde 2 letras (prefijo 2 + sufijo 2). */
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
        $pares = self::paresCandidatos($valor, $longitudMinima);
        if ($pares === []) {
            return;
        }

        $expr = SqlDialectSupport::lower($column);
        $callback = function ($w) use ($expr, $pares) {
            foreach ($pares as $i => [$pref, $suf]) {
                $method = $i === 0 ? 'where' : 'orWhere';
                $w->{$method}(function ($inner) use ($expr, $pref, $suf) {
                    $inner->whereRaw($expr.' LIKE ?', ['%'.self::escapeLike($pref).'%'])
                        ->whereRaw($expr.' LIKE ?', ['%'.self::escapeLike($suf).'%']);
                });
            }
        };

        if ($orWhere) {
            $q->orWhere($callback);
        } else {
            $q->where($callback);
        }
    }

    /**
     * Pares (prefijo, sufijo) a probar en OR.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function paresCandidatos(string $valor, int $longitudMinima): array
    {
        $len = mb_strlen($valor);
        if ($len < $longitudMinima) {
            return [];
        }

        $pares = [];

        // Modo corto: solo en textos de 4–5 caracteres (si se pide CORTA).
        if ($longitudMinima <= self::LONGITUD_MINIMA_CORTA && $len >= 4 && $len < self::LONGITUD_MINIMA_DEFAULT) {
            self::agregarPar($pares, mb_strtolower(mb_substr($valor, 0, 2)), mb_strtolower(mb_substr($valor, -2)));
        }

        // Criterio estándar desde 6 caracteres (también si pidieron CORTA con texto largo).
        if ($len >= self::LONGITUD_MINIMA_DEFAULT) {
            $pref = mb_strtolower(mb_substr($valor, 0, 3));
            $longitudSufijo = $len >= 8 ? 5 : 4;
            $suf = mb_strtolower(mb_substr($valor, -$longitudSufijo));
            self::agregarPar($pares, $pref, $suf);

            // Tipografía cerca del final (letra de más/menos): «batistela» → BATISTELLA.
            if ($len >= 8) {
                self::agregarPar(
                    $pares,
                    mb_strtolower(mb_substr($valor, 0, 4)),
                    mb_strtolower(mb_substr($valor, -2))
                );
            }
        }

        return $pares;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pares
     */
    private static function agregarPar(array &$pares, string $pref, string $suf): void
    {
        if ($pref === '' || $suf === '' || $pref === $suf) {
            return;
        }

        foreach ($pares as [$p, $s]) {
            if ($p === $pref && $s === $suf) {
                return;
            }
        }

        $pares[] = [$pref, $suf];
    }

    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
