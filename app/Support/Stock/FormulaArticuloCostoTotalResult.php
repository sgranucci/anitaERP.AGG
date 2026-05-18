<?php

namespace App\Support\Stock;

/**
 * Resultado del costo total estimado de una fórmula (última compra × cantidades).
 */
final class FormulaArticuloCostoTotalResult
{
    /**
     * @param  list<string>  $advertencias
     */
    public function __construct(
        public readonly float $total,
        public readonly bool $completo,
        public readonly array $advertencias = [],
        public readonly float $cantidadUnidad = 1.0,
    ) {}

    public static function vacio(float $cantidadUnidad = 1.0): self
    {
        return new self(0.0, true, [], $cantidadUnidad);
    }

    public function totalPorUnidadFormula(): float
    {
        if ($this->cantidadUnidad <= 0) {
            return $this->total;
        }

        return $this->total / $this->cantidadUnidad;
    }

    public function combinar(self $otro): self
    {
        return new self(
            $this->total + $otro->total,
            $this->completo && $otro->completo,
            array_values(array_unique(array_merge($this->advertencias, $otro->advertencias))),
            $this->cantidadUnidad,
        );
    }
}
