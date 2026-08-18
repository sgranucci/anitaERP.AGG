<?php

namespace App\Support\Sueldos\ReporteDefinible;

use App\Support\Sueldos\Formula\EntornoFormula;
use App\Support\Sueldos\Formula\Evaluador;
use App\Support\Sueldos\Formula\Parser;
use Throwable;

/**
 * Evalúa fórmulas entre columnas y campos del empleado.
 */
final class ReporteSueldosDefinibleFormulaSupport
{
    /**
     * @return list<int>
     */
    public static function referencias(string $formula): array
    {
        preg_match_all('/\bC0*(\d+)\b/i', $formula, $matches);

        return array_values(array_unique(array_map('intval', $matches[1] ?? [])));
    }

    /**
     * @param  list<int>  $columnasDisponibles
     * @return list<string>
     */
    public static function validar(string $formula, array $columnasDisponibles, ?int $columnaActual = null): array
    {
        $formula = trim($formula);
        if ($formula === '') {
            return ['La fórmula es obligatoria.'];
        }

        $errores = [];
        try {
            $ast = (new Parser)->parsear($formula);
            $errores = self::validarAst($ast);
        } catch (Throwable $e) {
            if (! self::esAritmeticaSimple($formula)) {
                return ['La fórmula no es segura o tiene sintaxis inválida: '.$e->getMessage()];
            }
        }

        foreach (self::referencias($formula) as $referencia) {
            if ($columnaActual !== null && $referencia === $columnaActual) {
                $errores[] = 'La fórmula no puede referenciar su propia columna C'.$referencia.'.';
            } elseif (! in_array($referencia, $columnasDisponibles, true)) {
                $errores[] = 'La fórmula referencia la columna inexistente C'.$referencia.'.';
            }
        }

        return array_values(array_unique($errores));
    }

    /**
     * @param  array<int, string>  $formulas  [nro_columna => formula]
     */
    public static function tieneCiclo(array $formulas): bool
    {
        $visitando = [];
        $visitadas = [];
        $visitar = function (int $nro) use (&$visitar, &$visitando, &$visitadas, $formulas): bool {
            if (isset($visitando[$nro])) {
                return true;
            }
            if (isset($visitadas[$nro]) || ! isset($formulas[$nro])) {
                return false;
            }
            $visitando[$nro] = true;
            foreach (self::referencias($formulas[$nro]) as $dependencia) {
                if ($visitar($dependencia)) {
                    return true;
                }
            }
            unset($visitando[$nro]);
            $visitadas[$nro] = true;

            return false;
        };

        foreach (array_keys($formulas) as $nro) {
            if ($visitar((int) $nro)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Las claves enteras resuelven Cn; las claves de texto pueden ser campos
     * del empleado referenciados directamente por la fórmula.
     *
     * @param  array<int|string, float|int|string|bool|null>  $valoresPorNroColumna
     */
    public static function evaluar(string $formula, array $valoresPorNroColumna): float
    {
        $formula = trim($formula);
        if ($formula === '') {
            return 0.0;
        }

        try {
            $ast = (new Parser)->parsear($formula);
            $entorno = new class($valoresPorNroColumna) implements EntornoFormula
            {
                /** @param array<int|string, float|int|string|bool|null> $valores */
                public function __construct(private array $valores) {}

                public function variable(string $ruta)
                {
                    if (preg_match('/^C0*(\d+)$/i', $ruta, $m)) {
                        return $this->valores[(int) $m[1]] ?? 0.0;
                    }

                    return array_key_exists($ruta, $this->valores)
                        ? $this->valores[$ruta]
                        : 0.0;
                }

                public function existeFuncion(string $nombre): bool
                {
                    return false;
                }

                public function funcion(string $nombre, array $args)
                {
                    return null;
                }
            };
            $resultado = (new Evaluador($entorno))->evaluar($ast);
            $numero = self::aNumero($resultado);

            return is_finite($numero) ? round($numero, 2) : 0.0;
        } catch (Throwable) {
            if (! self::esAritmeticaSimple($formula)) {
                return 0.0;
            }
        }

        $expr = preg_replace_callback('/\bC0*(\d+)\b/i', function ($m) use ($valoresPorNroColumna) {
            $n = (int) $m[1];
            $v = $valoresPorNroColumna[$n]
                ?? $valoresPorNroColumna['C'.$n]
                ?? $valoresPorNroColumna['c'.$n]
                ?? 0;
            if (! is_numeric($v)) {
                $v = 0;
            }

            return (string) ((float) $v);
        }, $formula);

        if ($expr === null || ! self::esAritmeticaSimple($expr)) {
            return 0.0;
        }

        try {
            // phpcs:ignore
            $result = eval('return (float) ('.$expr.');');

            return is_finite((float) $result) ? round((float) $result, 2) : 0.0;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private static function esAritmeticaSimple(string $formula): bool
    {
        $sinReferencias = preg_replace('/\bC0*\d+\b/i', '1', trim($formula));

        return $sinReferencias !== null
            && $sinReferencias !== ''
            && preg_match('/^[0-9+\-*\/().\s]+$/', $sinReferencias) === 1;
    }

    /**
     * @param  array<string, mixed>  $nodo
     * @return list<string>
     */
    private static function validarAst(array $nodo): array
    {
        $errores = [];
        if (($nodo['t'] ?? null) === 'call') {
            $funciones = [
                'si', 'if', 'entre', 'redondear', 'truncar', 'abs', 'min', 'max',
                'techo', 'piso', 'raiz', 'potencia',
            ];
            $nombre = strtolower((string) ($nodo['nombre'] ?? ''));
            if (! in_array($nombre, $funciones, true)) {
                $errores[] = 'La función '.$nombre.' no está permitida en fórmulas de reportes.';
            }
        }

        foreach (['a', 'b', 'cond', 'e'] as $clave) {
            if (isset($nodo[$clave]) && is_array($nodo[$clave])) {
                $errores = array_merge($errores, self::validarAst($nodo[$clave]));
            }
        }
        foreach ((array) ($nodo['args'] ?? []) as $argumento) {
            if (is_array($argumento)) {
                $errores = array_merge($errores, self::validarAst($argumento));
            }
        }

        return $errores;
    }

    /**
     * @param mixed $valor
     */
    private static function aNumero($valor): float
    {
        if (is_bool($valor)) {
            return $valor ? 1.0 : 0.0;
        }
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }
        if (is_string($valor) && is_numeric(str_replace(',', '.', $valor))) {
            return (float) str_replace(',', '.', $valor);
        }

        return 0.0;
    }
}
