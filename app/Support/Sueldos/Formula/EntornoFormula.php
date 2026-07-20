<?php

namespace App\Support\Sueldos\Formula;

/**
 * Entorno de resolución de una fórmula: provee el valor de las variables
 * (rutas con punto, ej. "empleado.sueldo_basico") y de las funciones de
 * dominio que no son built-in del evaluador (ej. concepto(), acum(), param()).
 *
 * Mantiene el evaluador desacoplado de Eloquent/BD: en producción lo implementa
 * el contexto de liquidación; en tests, un entorno de arrays.
 */
interface EntornoFormula
{
    /**
     * Devuelve el valor de una variable por su ruta (ej. "periodo.dias").
     * Debe retornar float|int|string|bool|null.
     */
    public function variable(string $ruta);

    public function existeFuncion(string $nombre): bool;

    /**
     * Ejecuta una función de dominio con sus argumentos ya evaluados.
     *
     * @param  array<int, mixed>  $args
     * @return float|int|string|bool|null
     */
    public function funcion(string $nombre, array $args);
}
