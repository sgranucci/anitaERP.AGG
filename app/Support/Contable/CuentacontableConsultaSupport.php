<?php

namespace App\Support\Contable;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtro de texto del modal de consulta de cuentas contables.
 *
 * - Varias palabras: todas deben aparecer en el nombre (AND).
 * - Singular/plural simple (POZOS ↔ POZO) para no perder cuentas hermanas.
 * - Código numérico / formateado (111010-001) también busca por codigo.
 * - Ordena por relevancia del nombre respecto al texto tipeado.
 */
class CuentacontableConsultaSupport
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function aplicarFiltroTexto(Builder $query, string $consulta): void
    {
        $consulta = trim($consulta);
        if ($consulta === '') {
            return;
        }

        $tokens = self::tokens($consulta);
        $consultaCodigo = preg_replace('/\D+/', '', $consulta) ?? '';

        $query->where(function (Builder $outer) use ($tokens, $consulta, $consultaCodigo) {
            $outer->where(function (Builder $porNombre) use ($tokens) {
                foreach ($tokens as $token) {
                    $variantes = self::variantesToken($token);
                    $porNombre->where(function (Builder $tokenQ) use ($variantes) {
                        foreach ($variantes as $variante) {
                            $like = '%'.CoincidenciaFlexibleTexto::escapeLike($variante).'%';
                            $tokenQ->orWhere('cuentacontable.nombre', 'LIKE', $like);
                        }
                    });
                }
            });

            $outer->orWhere('cuentacontable.codigo', 'LIKE', '%'.CoincidenciaFlexibleTexto::escapeLike($consulta).'%');

            if ($consultaCodigo !== '' && $consultaCodigo !== $consulta) {
                $outer->orWhere(
                    'cuentacontable.codigo',
                    'LIKE',
                    '%'.CoincidenciaFlexibleTexto::escapeLike($consultaCodigo).'%'
                );
            }

            if (ctype_digit($consulta)) {
                $outer->orWhere('cuentacontable.id', $consulta);
                $outer->orWhere('cuentacontable.codigo', 'LIKE', '%'.CoincidenciaFlexibleTexto::escapeLike($consulta).'%');
            }
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function ordenarPorRelevancia(Builder $query, string $consulta): void
    {
        $consulta = trim($consulta);
        if ($consulta === '') {
            $query->orderBy('cuentacontable.nombre');

            return;
        }

        $primerToken = self::tokens($consulta)[0] ?? $consulta;
        $likeExacto = CoincidenciaFlexibleTexto::escapeLike($consulta).'%';
        $likeToken = CoincidenciaFlexibleTexto::escapeLike($primerToken).'%';
        $likeContiene = '%'.CoincidenciaFlexibleTexto::escapeLike($consulta).'%';

        $query->orderByRaw(
            'CASE
                WHEN cuentacontable.nombre LIKE ? THEN 0
                WHEN cuentacontable.nombre LIKE ? THEN 1
                WHEN cuentacontable.nombre LIKE ? THEN 2
                ELSE 3
             END',
            [$likeExacto, $likeToken, $likeContiene]
        )->orderBy('cuentacontable.nombre');
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $consulta): array
    {
        $partes = preg_split('/\s+/u', trim($consulta)) ?: [];
        $tokens = [];
        foreach ($partes as $parte) {
            $parte = trim((string) $parte);
            if ($parte !== '') {
                $tokens[] = $parte;
            }
        }

        return $tokens !== [] ? $tokens : [trim($consulta)];
    }

    /**
     * Variantes de un token: el original y singular/plural simple en español.
     *
     * @return list<string>
     */
    public static function variantesToken(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return [];
        }

        $variantes = [$token];
        $lower = mb_strtolower($token, 'UTF-8');

        // POZOS → POZO ; PROGRESIVOS → PROGRESIVO
        if (mb_strlen($token) >= 4 && preg_match('/^(.+)s$/u', $lower, $m) && $m[1] !== '') {
            $variantes[] = self::preservarCaja($token, $m[1]);
        }

        // POZO → POZOS (si buscan el singular, también el plural)
        if (mb_strlen($token) >= 4 && ! str_ends_with($lower, 's')) {
            $variantes[] = self::preservarCaja($token, $lower.'s');
        }

        return array_values(array_unique($variantes));
    }

    private static function preservarCaja(string $original, string $nuevoLower): string
    {
        if (mb_strtoupper($original, 'UTF-8') === $original) {
            return mb_strtoupper($nuevoLower, 'UTF-8');
        }

        return $nuevoLower;
    }
}
