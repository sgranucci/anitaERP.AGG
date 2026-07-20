<?php

namespace App\Support\Sueldos\Formula;

/**
 * Evalúa un AST de fórmula usando un EntornoFormula, con soporte opcional de
 * rastreo (depurador). Es puro: sin BD ni framework (testeable en aislamiento).
 *
 * Funciones built-in (matemática/lógica): redondear, truncar, abs, min, max,
 * techo, piso, raiz, potencia, entre, si. El resto se delega al entorno
 * (concepto(), acum(), param(), antiguedad(), dias(), novedad()…).
 */
class Evaluador
{
    private EntornoFormula $entorno;

    private ?RastreadorFormula $rastreador;

    /** Tipos de nodo que se registran en el rastreo (los literales se omiten). */
    private const TRAZABLES = ['var', 'call', 'bin', 'un', 'ter'];

    public function __construct(EntornoFormula $entorno, ?RastreadorFormula $rastreador = null)
    {
        $this->entorno = $entorno;
        $this->rastreador = $rastreador;
    }

    /**
     * @param  array<string, mixed>  $ast
     * @return float|int|string|bool
     */
    public function evaluar(array $ast)
    {
        return $this->ev($ast);
    }

    /**
     * @param  array<string, mixed>  $n
     * @return float|int|string|bool
     */
    private function ev(array $n)
    {
        $traza = $this->rastreador !== null && in_array($n['t'], self::TRAZABLES, true);
        if ($traza) {
            $this->rastreador->entrar(Ast::aTexto($n), $n['t']);
        }

        $val = $this->evNodo($n);

        if ($traza) {
            $this->rastreador->salir($val);
        }

        return $val;
    }

    /**
     * @param  array<string, mixed>  $n
     * @return float|int|string|bool
     */
    private function evNodo(array $n)
    {
        switch ($n['t']) {
            case 'num':
                return (float) $n['v'];
            case 'txt':
                return (string) $n['v'];
            case 'var':
                return $this->resolverVariable((string) $n['nombre']);
            case 'un':
                return $this->evUnario($n);
            case 'bin':
                return $this->evBinario($n);
            case 'ter':
                return $this->esVerdadero($this->ev($n['cond'])) ? $this->ev($n['a']) : $this->ev($n['b']);
            case 'call':
                return $this->evLlamada($n);
        }

        throw FormulaException::evaluacion("nodo desconocido '{$n['t']}'");
    }

    /**
     * @param  array<string, mixed>  $n
     * @return float|bool
     */
    private function evUnario(array $n)
    {
        if ($n['op'] === '-') {
            return -$this->aNumero($this->ev($n['e']));
        }

        // '!'
        return ! $this->esVerdadero($this->ev($n['e']));
    }

    /**
     * @param  array<string, mixed>  $n
     * @return float|bool
     */
    private function evBinario(array $n)
    {
        $op = $n['op'];

        // Short-circuit lógico
        if ($op === '&&') {
            return $this->esVerdadero($this->ev($n['a'])) ? $this->esVerdadero($this->ev($n['b'])) : false;
        }
        if ($op === '||') {
            return $this->esVerdadero($this->ev($n['a'])) ? true : $this->esVerdadero($this->ev($n['b']));
        }

        $a = $this->ev($n['a']);
        $b = $this->ev($n['b']);

        switch ($op) {
            case '+': return $this->aNumero($a) + $this->aNumero($b);
            case '-': return $this->aNumero($a) - $this->aNumero($b);
            case '*': return $this->aNumero($a) * $this->aNumero($b);
            case '/':
                $d = $this->aNumero($b);
                if ($d == 0.0) {
                    throw FormulaException::evaluacion('división por cero');
                }

                return $this->aNumero($a) / $d;
            case '%':
                $d = $this->aNumero($b);
                if ($d == 0.0) {
                    throw FormulaException::evaluacion('módulo por cero');
                }

                return fmod($this->aNumero($a), $d);
            case '^': return pow($this->aNumero($a), $this->aNumero($b));
            case '==': return $this->iguales($a, $b);
            case '!=': return ! $this->iguales($a, $b);
            case '<': return $this->aNumero($a) < $this->aNumero($b);
            case '>': return $this->aNumero($a) > $this->aNumero($b);
            case '<=': return $this->aNumero($a) <= $this->aNumero($b);
            case '>=': return $this->aNumero($a) >= $this->aNumero($b);
        }

        throw FormulaException::evaluacion("operador desconocido '{$op}'");
    }

    /**
     * @param  array<string, mixed>  $n
     * @return float|int|string|bool
     */
    private function evLlamada(array $n)
    {
        $nombre = strtolower((string) $n['nombre']);

        // si(cond, a, b): short-circuit de las ramas
        if ($nombre === 'si' || $nombre === 'if') {
            if (count($n['args']) !== 3) {
                throw FormulaException::evaluacion('si(cond, a, b) requiere 3 argumentos');
            }

            return $this->esVerdadero($this->ev($n['args'][0]))
                ? $this->ev($n['args'][1])
                : $this->ev($n['args'][2]);
        }

        // Resto: argumentos evaluados con anticipación
        $args = array_map(fn ($a) => $this->ev($a), $n['args']);

        $builtin = $this->funcionBuiltin($nombre, $args);
        if ($builtin !== null) {
            return $builtin['valor'];
        }

        if ($this->entorno->existeFuncion($nombre)) {
            $r = $this->entorno->funcion($nombre, $args);
            if ($this->rastreador !== null) {
                $this->rastreador->anotar('función de dominio');
            }

            return $r ?? 0.0;
        }

        throw FormulaException::evaluacion("función desconocida '{$n['nombre']}'");
    }

    /**
     * @param  array<int, mixed>  $args
     * @return array{valor: float|int|string|bool}|null
     */
    private function funcionBuiltin(string $nombre, array $args): ?array
    {
        switch ($nombre) {
            case 'redondear':
                $dec = isset($args[1]) ? (int) $this->aNumero($args[1]) : 2;

                return ['valor' => round($this->aNumero($args[0]), $dec)];
            case 'truncar':
                $dec = isset($args[1]) ? (int) $this->aNumero($args[1]) : 0;
                $f = 10 ** $dec;

                return ['valor' => ($this->aNumero($args[0]) >= 0 ? floor($this->aNumero($args[0]) * $f) : ceil($this->aNumero($args[0]) * $f)) / $f];
            case 'abs':
                return ['valor' => abs($this->aNumero($args[0]))];
            case 'techo':
                return ['valor' => (float) ceil($this->aNumero($args[0]))];
            case 'piso':
                return ['valor' => (float) floor($this->aNumero($args[0]))];
            case 'raiz':
                return ['valor' => sqrt($this->aNumero($args[0]))];
            case 'potencia':
                return ['valor' => pow($this->aNumero($args[0]), $this->aNumero($args[1] ?? 2))];
            case 'min':
                return ['valor' => min(array_map(fn ($v) => $this->aNumero($v), $args))];
            case 'max':
                return ['valor' => max(array_map(fn ($v) => $this->aNumero($v), $args))];
            case 'entre':
                // entre(x, min, max) => acota x al rango [min, max]
                $x = $this->aNumero($args[0]);
                $lo = $this->aNumero($args[1]);
                $hi = $this->aNumero($args[2]);

                return ['valor' => max($lo, min($hi, $x))];
        }

        return null;
    }

    /**
     * @return float|int|string|bool
     */
    private function resolverVariable(string $ruta)
    {
        $v = $this->entorno->variable($ruta);
        if ($v === null) {
            throw FormulaException::evaluacion("variable no definida '{$ruta}'");
        }

        return $v;
    }

    /**
     * @param  mixed  $v
     */
    private function aNumero($v): float
    {
        if (is_bool($v)) {
            return $v ? 1.0 : 0.0;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        if (is_string($v) && is_numeric(str_replace(',', '.', $v))) {
            return (float) str_replace(',', '.', $v);
        }

        throw FormulaException::evaluacion("valor no numérico '".(is_scalar($v) ? $v : gettype($v))."'");
    }

    /**
     * @param  mixed  $v
     */
    private function esVerdadero($v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v != 0.0;
        }
        if (is_string($v)) {
            return $v !== '' && $v !== '0';
        }

        return false;
    }

    /**
     * @param  mixed  $a
     * @param  mixed  $b
     */
    private function iguales($a, $b): bool
    {
        $aNum = is_bool($a) || is_int($a) || is_float($a) || (is_string($a) && is_numeric($a));
        $bNum = is_bool($b) || is_int($b) || is_float($b) || (is_string($b) && is_numeric($b));

        if ($aNum && $bNum) {
            return abs($this->aNumero($a) - $this->aNumero($b)) < 1e-9;
        }

        return (string) $a === (string) $b;
    }
}
