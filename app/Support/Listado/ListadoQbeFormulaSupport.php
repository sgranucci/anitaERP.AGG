<?php

declare(strict_types=1);

namespace App\Support\Listado;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Expresiones QBE seguras (estilo NetSuite Formula / Dynamics).
 *
 * Gramática:
 *   expr := LENGTH(arg) | UPPER(arg) | LOWER(arg) | TRIM(arg)
 *         | CONCAT(arg (, arg)+) | {campo} | 'literal' | número
 *
 * Solo campos de la whitelist del listado. Sin SQL libre ni identificadores sueltos.
 */
final class ListadoQbeFormulaSupport
{
    public const CAMPO_KEY = '__formula__';

    public const MAX_LEN = 180;

    public const MAX_DEPTH = 4;

    public const FN_LENGTH = 'LENGTH';

    public const FN_UPPER = 'UPPER';

    public const FN_LOWER = 'LOWER';

    public const FN_TRIM = 'TRIM';

    public const FN_CONCAT = 'CONCAT';

    /** @var array<string, string> */
    private const OPS_TEXTO = [
        'contiene', 'no_contiene', 'empieza', 'termina', 'igual', 'distinto',
        'mayor', 'mayor_igual', 'menor', 'menor_igual', 'entre', 'vacio',
    ];

    /** @var array<string, string> */
    private const OPS_ENTERO = [
        'igual', 'mayor', 'mayor_igual', 'menor', 'menor_igual', 'entre', 'vacio',
    ];

    /**
     * @param  array<string, array{column?: string, type?: string}>  $campos
     * @return array{sql: string, bindings: list<mixed>, type: string}|null
     */
    public static function compilar(string $formula, array $campos): ?array
    {
        $formula = trim($formula);
        if ($formula === '' || mb_strlen($formula) > self::MAX_LEN) {
            return null;
        }

        $parser = new class($formula, $campos)
        {
            private string $s;

            private int $i = 0;

            private int $len;

            /** @var array<string, array{column?: string, type?: string}> */
            private array $campos;

            /** @var list<mixed> */
            public array $bindings = [];

            public function __construct(string $s, array $campos)
            {
                $this->s = $s;
                $this->len = strlen($s);
                $this->campos = $campos;
            }

            /** @return array{sql: string, type: string}|null */
            public function parse(): ?array
            {
                $this->skipWs();
                $node = $this->parseExpr(0);
                $this->skipWs();
                if ($node === null || $this->i < $this->len) {
                    return null;
                }

                return $node;
            }

            /** @return array{sql: string, type: string}|null */
            private function parseExpr(int $depth): ?array
            {
                if ($depth > ListadoQbeFormulaSupport::MAX_DEPTH) {
                    return null;
                }
                $this->skipWs();
                if ($this->i >= $this->len) {
                    return null;
                }

                $ch = $this->s[$this->i];
                if ($ch === '{') {
                    return $this->parseField();
                }
                if ($ch === "'" || $ch === '"') {
                    return $this->parseString($ch);
                }
                if (ctype_digit($ch)) {
                    return $this->parseNumber();
                }
                if (ctype_alpha($ch) || $ch === '_') {
                    return $this->parseCall($depth);
                }

                return null;
            }

            /** @return array{sql: string, type: string}|null */
            private function parseField(): ?array
            {
                $this->i++; // {
                $start = $this->i;
                while ($this->i < $this->len && (ctype_alnum($this->s[$this->i]) || $this->s[$this->i] === '_')) {
                    $this->i++;
                }
                if ($this->i >= $this->len || $this->s[$this->i] !== '}') {
                    return null;
                }
                $key = substr($this->s, $start, $this->i - $start);
                $this->i++; // }
                if ($key === '' || ! isset($this->campos[$key]['column'])) {
                    return null;
                }
                $col = (string) $this->campos[$key]['column'];
                if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
                    return null;
                }
                $type = (string) ($this->campos[$key]['type'] ?? 'texto');

                // Texto portable para funciones de cadena
                $sql = $type === 'entero' || $type === 'booleano'
                    ? 'CAST('.$col.' AS CHAR)'
                    : 'COALESCE('.$col.", '')";

                return ['sql' => $sql, 'type' => 'texto'];
            }

            /** @return array{sql: string, type: string}|null */
            private function parseString(string $quote): ?array
            {
                $this->i++;
                $buf = '';
                while ($this->i < $this->len) {
                    $c = $this->s[$this->i];
                    if ($c === '\\' && $this->i + 1 < $this->len) {
                        $buf .= $this->s[$this->i + 1];
                        $this->i += 2;
                        continue;
                    }
                    if ($c === $quote) {
                        $this->i++;
                        $this->bindings[] = $buf;

                        return ['sql' => '?', 'type' => 'texto'];
                    }
                    $buf .= $c;
                    $this->i++;
                }

                return null;
            }

            /** @return array{sql: string, type: string}|null */
            private function parseNumber(): ?array
            {
                $start = $this->i;
                while ($this->i < $this->len && ctype_digit($this->s[$this->i])) {
                    $this->i++;
                }
                $num = substr($this->s, $start, $this->i - $start);

                return ['sql' => $num, 'type' => 'entero'];
            }

            /** @return array{sql: string, type: string}|null */
            private function parseCall(int $depth): ?array
            {
                $start = $this->i;
                while ($this->i < $this->len && (ctype_alnum($this->s[$this->i]) || $this->s[$this->i] === '_')) {
                    $this->i++;
                }
                $fn = strtoupper(substr($this->s, $start, $this->i - $start));
                $this->skipWs();
                if ($this->i >= $this->len || $this->s[$this->i] !== '(') {
                    return null;
                }
                $this->i++; // (
                $args = [];
                $this->skipWs();
                if ($this->i < $this->len && $this->s[$this->i] === ')') {
                    // CONCAT sin args no válido; LENGTH etc. tampoco
                    return null;
                }
                while (true) {
                    $arg = $this->parseExpr($depth + 1);
                    if ($arg === null) {
                        return null;
                    }
                    $args[] = $arg;
                    $this->skipWs();
                    if ($this->i < $this->len && $this->s[$this->i] === ',') {
                        $this->i++;
                        continue;
                    }
                    break;
                }
                $this->skipWs();
                if ($this->i >= $this->len || $this->s[$this->i] !== ')') {
                    return null;
                }
                $this->i++; // )

                return $this->buildFn($fn, $args);
            }

            /**
             * @param  list<array{sql: string, type: string}>  $args
             * @return array{sql: string, type: string}|null
             */
            private function buildFn(string $fn, array $args): ?array
            {
                $n = count($args);
                switch ($fn) {
                    case ListadoQbeFormulaSupport::FN_LENGTH:
                        if ($n !== 1) {
                            return null;
                        }

                        return [
                            'sql' => 'CHAR_LENGTH('.$args[0]['sql'].')',
                            'type' => 'entero',
                        ];
                    case ListadoQbeFormulaSupport::FN_UPPER:
                    case ListadoQbeFormulaSupport::FN_LOWER:
                    case ListadoQbeFormulaSupport::FN_TRIM:
                        if ($n !== 1) {
                            return null;
                        }

                        return [
                            'sql' => $fn.'('.$args[0]['sql'].')',
                            'type' => 'texto',
                        ];
                    case ListadoQbeFormulaSupport::FN_CONCAT:
                        if ($n < 2) {
                            return null;
                        }
                        $parts = array_map(static fn (array $a): string => $a['sql'], $args);

                        return [
                            'sql' => 'CONCAT('.implode(', ', $parts).')',
                            'type' => 'texto',
                        ];
                    default:
                        return null;
                }
            }

            private function skipWs(): void
            {
                while ($this->i < $this->len && ctype_space($this->s[$this->i])) {
                    $this->i++;
                }
            }
        };

        $node = $parser->parse();
        if ($node === null) {
            return null;
        }

        return [
            'sql' => $node['sql'],
            'bindings' => $parser->bindings,
            'type' => $node['type'],
        ];
    }

    /**
     * @param  array<string, array{column?: string, type?: string}>  $campos
     */
    public static function puedeCompilar(string $formula, array $campos): bool
    {
        return self::compilar($formula, $campos) !== null;
    }

    public static function inferirTipo(string $formula): string
    {
        $f = ltrim($formula);
        if (preg_match('/^LENGTH\s*\(/i', $f) === 1) {
            return 'entero';
        }
        if (preg_match('/^\d/', $f) === 1) {
            return 'entero';
        }

        return 'texto';
    }

    public static function normalizarOperador(string $op, string $tipo): string
    {
        $op = strtolower(trim($op));
        $ok = $tipo === 'entero' ? self::OPS_ENTERO : self::OPS_TEXTO;
        if (in_array($op, $ok, true)) {
            return $op;
        }

        return $tipo === 'entero' ? 'igual' : 'contiene';
    }

    /**
     * @param  array{sql: string, bindings: list<mixed>, type: string}  $compiled
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function aplicarComparacion(
        Builder $query,
        array $compiled,
        string $op,
        string $valor,
        string $valorHasta = '',
        string $boolean = 'and'
    ): void {
        if ($boolean === ListadoQbeSupport::LOGIC_OR) {
            $query->orWhere(function (Builder $q) use ($compiled, $op, $valor, $valorHasta) {
                self::aplicarComparacion($q, $compiled, $op, $valor, $valorHasta, ListadoQbeSupport::LOGIC_AND);
            });

            return;
        }

        $sql = $compiled['sql'];
        $base = $compiled['bindings'];
        $type = $compiled['type'];

        if ($op === 'vacio') {
            if ($type === 'entero') {
                $query->whereRaw('('.$sql.') IS NULL', $base);
            } else {
                $query->whereRaw('(('.$sql.") IS NULL OR (".$sql.") = '')", array_merge($base, $base));
            }

            return;
        }

        if ($type === 'entero') {
            self::aplicarEntero($query, $sql, $base, $op, $valor, $valorHasta);

            return;
        }

        self::aplicarTexto($query, $sql, $base, $op, $valor, $valorHasta);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<mixed>  $base
     */
    private static function aplicarEntero(
        Builder $query,
        string $sql,
        array $base,
        string $op,
        string $valor,
        string $valorHasta
    ): void {
        if ($op === 'entre') {
            $desde = filter_var($valor, FILTER_VALIDATE_INT);
            $hasta = filter_var($valorHasta, FILTER_VALIDATE_INT);
            if ($desde !== false) {
                $query->whereRaw('('.$sql.') >= ?', array_merge($base, [(int) $desde]));
            }
            if ($hasta !== false) {
                $query->whereRaw('('.$sql.') <= ?', array_merge($base, [(int) $hasta]));
            }

            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false && $valor !== '0') {
            return;
        }
        $n = (int) $valor;
        $cmp = match ($op) {
            'mayor' => '>',
            'mayor_igual' => '>=',
            'menor' => '<',
            'menor_igual' => '<=',
            default => '=',
        };
        $query->whereRaw('('.$sql.') '.$cmp.' ?', array_merge($base, [$n]));
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<mixed>  $base
     */
    private static function aplicarTexto(
        Builder $query,
        string $sql,
        array $base,
        string $op,
        string $valor,
        string $valorHasta = ''
    ): void {
        if ($op === 'entre') {
            $desde = trim($valor);
            $hasta = trim($valorHasta);
            if ($desde === '' && $hasta === '') {
                return;
            }
            if ($desde !== '') {
                $query->whereRaw('('.$sql.') >= ?', array_merge($base, [$desde]));
            }
            if ($hasta !== '') {
                $query->whereRaw('('.$sql.') <= ?', array_merge($base, [$hasta]));
            }

            return;
        }

        if ($valor === '' && $op !== 'vacio') {
            return;
        }

        $esc = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);

        switch ($op) {
            case 'empieza':
                $query->whereRaw('('.$sql.') LIKE ?', array_merge($base, [$esc.'%']));
                break;
            case 'termina':
                $query->whereRaw('('.$sql.') LIKE ?', array_merge($base, ['%'.$esc]));
                break;
            case 'igual':
                $query->whereRaw('('.$sql.') = ?', array_merge($base, [$valor]));
                break;
            case 'distinto':
                $query->whereRaw('(('.$sql.') IS NULL OR ('.$sql.') <> ?)', array_merge($base, $base, [$valor]));
                break;
            case 'mayor':
                $query->whereRaw('('.$sql.') > ?', array_merge($base, [$valor]));
                break;
            case 'mayor_igual':
                $query->whereRaw('('.$sql.') >= ?', array_merge($base, [$valor]));
                break;
            case 'menor':
                $query->whereRaw('('.$sql.') < ?', array_merge($base, [$valor]));
                break;
            case 'menor_igual':
                $query->whereRaw('('.$sql.') <= ?', array_merge($base, [$valor]));
                break;
            case 'no_contiene':
                $like = '%'.$esc.'%';
                $query->whereRaw(
                    '(('.$sql.") IS NULL OR (".$sql.") = '' OR (".$sql.') NOT LIKE ?)',
                    array_merge($base, $base, $base, [$like])
                );
                break;
            case 'contiene':
            default:
                $query->whereRaw('('.$sql.') LIKE ?', array_merge($base, ['%'.$esc.'%']));
                break;
        }
    }
}
