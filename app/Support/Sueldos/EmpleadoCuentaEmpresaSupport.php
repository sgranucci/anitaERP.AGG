<?php

declare(strict_types=1);

namespace App\Support\Sueldos;

/**
 * Anita l-board, Informe de nómina: si el mismo legajo está en varias empresas,
 * la persona cuenta en emp_cuenta_empresa. Dentro de esa empresa, si hubiera
 * más de una fila, se queda la de mayor sueldo (en C es el concepto liquidado
 * del mes; acá el sueldo básico) con la misma tolerancia de 100: una empresa
 * posterior pisa a la anterior si no cobra más de 100 por encima.
 *
 * Sin el dato cargado se mantiene el criterio anterior: alta más antigua y,
 * si empatan, la empresa de menor id.
 */
final class EmpleadoCuentaEmpresaSupport
{
    public const TOLERANCIA_SUELDO = 100.0;

    /**
     * @param  list<object>  $filas
     */
    public static function elegir(array $filas): ?object
    {
        if ($filas === []) {
            return null;
        }
        if (count($filas) === 1) {
            return $filas[0];
        }

        $cuentaId = self::cuentaConsensuada($filas);
        if ($cuentaId === null) {
            return self::altaMasAntigua($filas);
        }

        $enSuEmpresa = array_values(array_filter(
            $filas,
            static fn (object $fila): bool => (int) ($fila->empresa_id ?? 0) === $cuentaId
        ));
        if ($enSuEmpresa !== []) {
            return self::mejorSueldo($enSuEmpresa);
        }

        $declaranEsaCuenta = array_values(array_filter(
            $filas,
            static fn (object $fila): bool => (int) ($fila->cuenta_empresa_id ?? 0) === $cuentaId
        ));

        return self::mejorSueldo($declaranEsaCuenta !== [] ? $declaranEsaCuenta : $filas);
    }

    /**
     * @param  list<object>  $filas
     */
    private static function cuentaConsensuada(array $filas): ?int
    {
        $votos = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila->cuenta_empresa_id ?? 0);
            if ($id <= 0) {
                continue;
            }
            $votos[$id] = ($votos[$id] ?? 0) + 1;
        }
        if ($votos === []) {
            return null;
        }

        uksort($votos, static function (int $a, int $b) use ($votos): int {
            return [$votos[$b], $a] <=> [$votos[$a], $b];
        });

        return (int) array_key_first($votos);
    }

    /**
     * @param  list<object>  $filas
     */
    private static function mejorSueldo(array $filas): ?object
    {
        if ($filas === []) {
            return null;
        }

        usort($filas, static function (object $a, object $b): int {
            return [(int) ($a->empresa_id ?? 0), (int) ($a->id ?? 0)]
                <=> [(int) ($b->empresa_id ?? 0), (int) ($b->id ?? 0)];
        });

        $mejor = null;
        $mejorSueldo = 0.0;
        foreach ($filas as $fila) {
            $sueldo = (float) ($fila->sueldo_basico ?? 0);
            if ($mejor === null || ($sueldo + self::TOLERANCIA_SUELDO) > $mejorSueldo) {
                $mejor = $fila;
                $mejorSueldo = $sueldo;
            }
        }

        return $mejor;
    }

    /**
     * @param  list<object>  $filas
     */
    private static function altaMasAntigua(array $filas): ?object
    {
        if ($filas === []) {
            return null;
        }

        usort($filas, static function (object $a, object $b): int {
            return [self::ingreso($a), (int) ($a->empresa_id ?? 0), (int) ($a->id ?? 0)]
                <=> [self::ingreso($b), (int) ($b->empresa_id ?? 0), (int) ($b->id ?? 0)];
        });

        return $filas[0];
    }

    private static function ingreso(object $fila): string
    {
        $valor = $fila->fecha_ingreso ?? null;
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }
        $texto = trim((string) $valor);

        return $texto === '' ? '9999-99-99' : substr($texto, 0, 10);
    }
}
