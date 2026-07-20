<?php

namespace App\Support\Sueldos\Formula;

use RuntimeException;

/**
 * Error de sintaxis o de evaluación de una fórmula de liquidación.
 */
class FormulaException extends RuntimeException
{
    public static function sintaxis(string $mensaje, int $pos = -1): self
    {
        return new self($pos >= 0 ? "Fórmula inválida (pos {$pos}): {$mensaje}" : "Fórmula inválida: {$mensaje}");
    }

    public static function evaluacion(string $mensaje): self
    {
        return new self("Error al evaluar fórmula: {$mensaje}");
    }
}
