<?php

namespace App\Support\Sueldos\Formula\Anita;

use App\Support\Sueldos\Formula\FormulaException;

/**
 * Tokenizador de una expresión Anita (una línea de habformula, sin el `:=`).
 *
 * Anita normaliza a MAYÚSCULAS y descarta espacios y ';'. Los identificadores
 * incluyen '_' (variables temporales _V1, __persistente). El separador decimal
 * es '.'; la coma separa argumentos.
 */
class AnitaLexer
{
    public const NUM = 'NUM';

    public const ID = 'ID';

    public const OP = 'OP';

    public const PA = 'PA';

    public const PC = 'PC';

    public const COMA = 'COMA';

    public const FIN = 'FIN';

    /** Operadores de dos caracteres de Anita. */
    private const OP2 = ['<=', '>=', '<>'];

    /** Operadores de un caracter. */
    private const OP1 = ['+', '-', '*', '/', '^', '<', '>', '='];

    /**
     * @return array<int, array{t: string, v: string, pos: int}>
     */
    public function tokenizar(string $entrada): array
    {
        $tokens = [];
        $len = strlen($entrada);
        $i = 0;

        while ($i < $len) {
            $c = $entrada[$i];

            if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r" || $c === ';') {
                $i++;

                continue;
            }

            // Comentario Anita: '#' anula el resto (la línea devuelve 0)
            if ($c === '#') {
                break;
            }

            // Número
            if (ctype_digit($c) || ($c === '.' && $i + 1 < $len && ctype_digit($entrada[$i + 1]))) {
                $ini = $i;
                $vistoPunto = false;
                while ($i < $len) {
                    $d = $entrada[$i];
                    if (ctype_digit($d)) {
                        $i++;

                        continue;
                    }
                    if ($d === '.' && ! $vistoPunto && $i + 1 < $len && ctype_digit($entrada[$i + 1])) {
                        $vistoPunto = true;
                        $i++;

                        continue;
                    }
                    break;
                }
                $tokens[] = ['t' => self::NUM, 'v' => substr($entrada, $ini, $i - $ini), 'pos' => $ini];

                continue;
            }

            // Identificador (letras, dígitos, _)
            if (ctype_alpha($c) || $c === '_') {
                $ini = $i;
                while ($i < $len && (ctype_alnum($entrada[$i]) || $entrada[$i] === '_')) {
                    $i++;
                }
                $tokens[] = ['t' => self::ID, 'v' => substr($entrada, $ini, $i - $ini), 'pos' => $ini];

                continue;
            }

            if ($c === '(') {
                $tokens[] = ['t' => self::PA, 'v' => '(', 'pos' => $i++];

                continue;
            }
            if ($c === ')') {
                $tokens[] = ['t' => self::PC, 'v' => ')', 'pos' => $i++];

                continue;
            }
            if ($c === ',') {
                $tokens[] = ['t' => self::COMA, 'v' => ',', 'pos' => $i++];

                continue;
            }

            if ($i + 1 < $len) {
                $par = substr($entrada, $i, 2);
                if (in_array($par, self::OP2, true)) {
                    $tokens[] = ['t' => self::OP, 'v' => $par, 'pos' => $i];
                    $i += 2;

                    continue;
                }
            }

            if (in_array($c, self::OP1, true)) {
                $tokens[] = ['t' => self::OP, 'v' => $c, 'pos' => $i++];

                continue;
            }

            throw FormulaException::sintaxis("caracter Anita inesperado '{$c}'", $i);
        }

        $tokens[] = ['t' => self::FIN, 'v' => '', 'pos' => $len];

        return $tokens;
    }
}
