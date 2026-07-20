<?php

namespace App\Support\Sueldos\Formula;

/**
 * Parser recursivo-descendente. Convierte la lista de tokens en un AST.
 *
 * Precedencia (menor a mayor):
 *   ternario  ? :
 *   ||
 *   &&
 *   == !=
 *   < > <= >=
 *   + -
 *   * / %
 *   ^            (asociativo a derecha)
 *   unario  - !
 *   primario (número, texto, variable, llamada, paréntesis)
 */
class Parser
{
    /** @var array<int, array{t: string, v: string, pos: int}> */
    private array $tokens = [];

    private int $pos = 0;

    /**
     * @return array<string, mixed>
     */
    public function parsear(string $formula): array
    {
        $this->tokens = (new Lexer)->tokenizar($formula);
        $this->pos = 0;

        if ($this->actual()['t'] === Lexer::FIN) {
            throw FormulaException::sintaxis('fórmula vacía');
        }

        $nodo = $this->ternario();

        if ($this->actual()['t'] !== Lexer::FIN) {
            $tk = $this->actual();
            throw FormulaException::sintaxis("token inesperado '{$tk['v']}'", $tk['pos']);
        }

        return $nodo;
    }

    /**
     * @return array<string, mixed>
     */
    private function ternario(): array
    {
        $cond = $this->logicoOr();
        if ($this->esOp('?')) {
            $this->avanzar();
            $a = $this->ternario();
            $this->esperarOp(':');
            $b = $this->ternario();

            return ['t' => 'ter', 'cond' => $cond, 'a' => $a, 'b' => $b];
        }

        return $cond;
    }

    private function logicoOr(): array
    {
        $izq = $this->logicoAnd();
        while ($this->esOp('||')) {
            $this->avanzar();
            $der = $this->logicoAnd();
            $izq = ['t' => 'bin', 'op' => '||', 'a' => $izq, 'b' => $der];
        }

        return $izq;
    }

    private function logicoAnd(): array
    {
        $izq = $this->igualdad();
        while ($this->esOp('&&')) {
            $this->avanzar();
            $der = $this->igualdad();
            $izq = ['t' => 'bin', 'op' => '&&', 'a' => $izq, 'b' => $der];
        }

        return $izq;
    }

    private function igualdad(): array
    {
        $izq = $this->comparacion();
        while ($this->esOp('==') || $this->esOp('!=')) {
            $op = $this->actual()['v'];
            $this->avanzar();
            $der = $this->comparacion();
            $izq = ['t' => 'bin', 'op' => $op, 'a' => $izq, 'b' => $der];
        }

        return $izq;
    }

    private function comparacion(): array
    {
        $izq = $this->suma();
        while ($this->esOp('<') || $this->esOp('>') || $this->esOp('<=') || $this->esOp('>=')) {
            $op = $this->actual()['v'];
            $this->avanzar();
            $der = $this->suma();
            $izq = ['t' => 'bin', 'op' => $op, 'a' => $izq, 'b' => $der];
        }

        return $izq;
    }

    private function suma(): array
    {
        $izq = $this->producto();
        while ($this->esOp('+') || $this->esOp('-')) {
            $op = $this->actual()['v'];
            $this->avanzar();
            $der = $this->producto();
            $izq = ['t' => 'bin', 'op' => $op, 'a' => $izq, 'b' => $der];
        }

        return $izq;
    }

    private function producto(): array
    {
        $izq = $this->potencia();
        while ($this->esOp('*') || $this->esOp('/') || $this->esOp('%')) {
            $op = $this->actual()['v'];
            $this->avanzar();
            $der = $this->potencia();
            $izq = ['t' => 'bin', 'op' => $op, 'a' => $izq, 'b' => $der];
        }

        return $izq;
    }

    private function potencia(): array
    {
        $base = $this->unario();
        if ($this->esOp('^')) {
            $this->avanzar();
            $exp = $this->potencia(); // asociativo a derecha

            return ['t' => 'bin', 'op' => '^', 'a' => $base, 'b' => $exp];
        }

        return $base;
    }

    private function unario(): array
    {
        if ($this->esOp('-') || $this->esOp('!')) {
            $op = $this->actual()['v'];
            $this->avanzar();

            return ['t' => 'un', 'op' => $op, 'e' => $this->unario()];
        }

        return $this->primario();
    }

    private function primario(): array
    {
        $tk = $this->actual();

        if ($tk['t'] === Lexer::NUM) {
            $this->avanzar();

            return ['t' => 'num', 'v' => (float) $tk['v']];
        }

        if ($tk['t'] === Lexer::TXT) {
            $this->avanzar();

            return ['t' => 'txt', 'v' => $tk['v']];
        }

        if ($tk['t'] === Lexer::PA) {
            $this->avanzar();
            $nodo = $this->ternario();
            $this->esperar(Lexer::PC, ')');

            return $nodo;
        }

        if ($tk['t'] === Lexer::ID) {
            $nombre = $tk['v'];
            $this->avanzar();

            // Llamada a función
            if ($this->actual()['t'] === Lexer::PA) {
                $this->avanzar();
                $args = [];
                if ($this->actual()['t'] !== Lexer::PC) {
                    $args[] = $this->ternario();
                    while ($this->actual()['t'] === Lexer::COMA) {
                        $this->avanzar();
                        $args[] = $this->ternario();
                    }
                }
                $this->esperar(Lexer::PC, ')');

                return ['t' => 'call', 'nombre' => $nombre, 'args' => $args];
            }

            // Variable con ruta (empleado.sueldo_basico)
            $ruta = $nombre;
            while ($this->actual()['t'] === Lexer::PUNTO) {
                $this->avanzar();
                $sig = $this->actual();
                if ($sig['t'] !== Lexer::ID) {
                    throw FormulaException::sintaxis('se esperaba nombre luego de "."', $sig['pos']);
                }
                $ruta .= '.'.$sig['v'];
                $this->avanzar();
            }

            // Palabras clave literales
            $bajo = strtolower($ruta);
            if ($bajo === 'verdadero' || $bajo === 'true') {
                return ['t' => 'num', 'v' => 1.0];
            }
            if ($bajo === 'falso' || $bajo === 'false') {
                return ['t' => 'num', 'v' => 0.0];
            }

            return ['t' => 'var', 'nombre' => $ruta];
        }

        throw FormulaException::sintaxis("token inesperado '{$tk['v']}'", $tk['pos']);
    }

    // --- helpers de tokens ---

    /**
     * @return array{t: string, v: string, pos: int}
     */
    private function actual(): array
    {
        return $this->tokens[$this->pos];
    }

    private function avanzar(): void
    {
        $this->pos++;
    }

    private function esOp(string $op): bool
    {
        $tk = $this->actual();

        return $tk['t'] === Lexer::OP && $tk['v'] === $op;
    }

    private function esperarOp(string $op): void
    {
        if (! $this->esOp($op)) {
            $tk = $this->actual();
            throw FormulaException::sintaxis("se esperaba '{$op}'", $tk['pos']);
        }
        $this->avanzar();
    }

    private function esperar(string $tipo, string $lexema): void
    {
        $tk = $this->actual();
        if ($tk['t'] !== $tipo) {
            throw FormulaException::sintaxis("se esperaba '{$lexema}'", $tk['pos']);
        }
        $this->avanzar();
    }
}
