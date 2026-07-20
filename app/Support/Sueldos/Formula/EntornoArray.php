<?php

namespace App\Support\Sueldos\Formula;

/**
 * Entorno de fórmula respaldado por arrays. Útil para tests y para casos donde
 * el contexto ya está resuelto en memoria. En producción el contexto de
 * liquidación implementará EntornoFormula leyendo de los modelos.
 */
class EntornoArray implements EntornoFormula
{
    /** @var array<string, float|int|string|bool> */
    private array $variables;

    /** @var array<string, callable> */
    private array $funciones;

    /**
     * @param  array<string, float|int|string|bool>  $variables
     * @param  array<string, callable>  $funciones
     */
    public function __construct(array $variables = [], array $funciones = [])
    {
        $this->variables = $variables;
        $this->funciones = array_change_key_case($funciones, CASE_LOWER);
    }

    /**
     * @param  float|int|string|bool  $valor
     */
    public function setVariable(string $ruta, $valor): void
    {
        $this->variables[$ruta] = $valor;
    }

    public function setFuncion(string $nombre, callable $fn): void
    {
        $this->funciones[strtolower($nombre)] = $fn;
    }

    public function variable(string $ruta)
    {
        return $this->variables[$ruta] ?? null;
    }

    public function existeFuncion(string $nombre): bool
    {
        return isset($this->funciones[strtolower($nombre)]);
    }

    public function funcion(string $nombre, array $args)
    {
        $fn = $this->funciones[strtolower($nombre)];

        return $fn(...$args);
    }
}
