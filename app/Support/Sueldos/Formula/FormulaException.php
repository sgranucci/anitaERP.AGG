<?php

namespace App\Support\Sueldos\Formula;

use RuntimeException;

/**
 * Error de sintaxis o de evaluación de una fórmula de liquidación.
 */
class FormulaException extends RuntimeException
{
    private int $posicion;

    public function __construct(string $message = '', int $posicion = -1, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->posicion = $posicion;
    }

    public function posicion(): int
    {
        return $this->posicion;
    }

    public static function sintaxis(string $mensaje, int $pos = -1): self
    {
        return new self(
            $pos >= 0 ? "Fórmula inválida (pos {$pos}): {$mensaje}" : "Fórmula inválida: {$mensaje}",
            $pos
        );
    }

    public static function evaluacion(string $mensaje, int $pos = -1): self
    {
        return new self('Error al evaluar fórmula: '.$mensaje, $pos);
    }

    /**
     * Extrae pos N de un mensaje ya formateado (errores viejos / flash).
     */
    public static function extraerPosicion(?string $mensaje): int
    {
        if ($mensaje === null || $mensaje === '') {
            return -1;
        }
        if (preg_match('/\(pos\s+(\d+)\)/i', $mensaje, $m)) {
            return (int) $m[1];
        }

        return -1;
    }
}
