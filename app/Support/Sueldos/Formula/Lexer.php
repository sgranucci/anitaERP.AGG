<?php

namespace App\Support\Sueldos\Formula;

/**
 * Tokenizador del lenguaje de fórmulas de liquidación (sintaxis moderna).
 *
 * Tokens: NUM, TXT, ID, OP, PA '(', PC ')', COMA, PUNTO, FIN.
 */
class Lexer
{
    public const NUM = 'NUM';

    public const TXT = 'TXT';

    public const ID = 'ID';

    public const OP = 'OP';

    public const PA = 'PA';

    public const PC = 'PC';

    public const COMA = 'COMA';

    public const PUNTO = 'PUNTO';

    public const FIN = 'FIN';

    /** Operadores de dos caracteres. */
    private const OP2 = ['<=', '>=', '==', '!=', '&&', '||'];

    /** Operadores de un caracter. */
    private const OP1 = ['+', '-', '*', '/', '%', '^', '<', '>', '!', '?', ':'];

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

            // Espacios en blanco
            if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") {
                $i++;

                continue;
            }

            // Número (entero o decimal). El separador decimal es '.'; la coma
            // se reserva como separador de argumentos.
            if (ctype_digit($c) || ($c === '.' && $i + 1 < $len && ctype_digit($entrada[$i + 1]))) {
                $ini = $i;
                $vistoPunto = false;
                while ($i < $len) {
                    $d = $entrada[$i];
                    if (ctype_digit($d)) {
                        $i++;

                        continue;
                    }
                    // Solo punto decimal, y únicamente si le sigue un dígito.
                    if ($d === '.' && ! $vistoPunto && $i + 1 < $len && ctype_digit($entrada[$i + 1])) {
                        $vistoPunto = true;
                        $i++;

                        continue;
                    }
                    break;
                }
                $num = substr($entrada, $ini, $i - $ini);
                $tokens[] = ['t' => self::NUM, 'v' => $num, 'pos' => $ini];

                continue;
            }

            // Texto entre comillas dobles o simples
            if ($c === '"' || $c === "'") {
                $comilla = $c;
                $ini = $i;
                $i++;
                $buf = '';
                while ($i < $len && $entrada[$i] !== $comilla) {
                    if ($entrada[$i] === '\\' && $i + 1 < $len) {
                        $i++;
                    }
                    $buf .= $entrada[$i];
                    $i++;
                }
                if ($i >= $len) {
                    throw FormulaException::sintaxis('cadena sin cerrar', $ini);
                }
                $i++; // cierra comilla
                $tokens[] = ['t' => self::TXT, 'v' => $buf, 'pos' => $ini];

                continue;
            }

            // Identificador / palabra clave (letras, dígitos, _)
            if (ctype_alpha($c) || $c === '_') {
                $ini = $i;
                while ($i < $len && (ctype_alnum($entrada[$i]) || $entrada[$i] === '_')) {
                    $i++;
                }
                $tokens[] = ['t' => self::ID, 'v' => substr($entrada, $ini, $i - $ini), 'pos' => $ini];

                continue;
            }

            // Paréntesis, coma, punto
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
            if ($c === '.') {
                $tokens[] = ['t' => self::PUNTO, 'v' => '.', 'pos' => $i++];

                continue;
            }

            // Operadores de dos caracteres
            if ($i + 1 < $len) {
                $par = substr($entrada, $i, 2);
                if (in_array($par, self::OP2, true)) {
                    $tokens[] = ['t' => self::OP, 'v' => $par, 'pos' => $i];
                    $i += 2;

                    continue;
                }
            }

            // Operadores de un caracter
            if (in_array($c, self::OP1, true)) {
                $tokens[] = ['t' => self::OP, 'v' => $c, 'pos' => $i++];

                continue;
            }

            throw FormulaException::sintaxis("caracter inesperado '{$c}'", $i);
        }

        $tokens[] = ['t' => self::FIN, 'v' => '', 'pos' => $len];

        return $tokens;
    }
}
