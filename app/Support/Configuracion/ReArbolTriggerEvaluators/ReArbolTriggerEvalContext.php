<?php

namespace App\Support\Configuracion\ReArbolTriggerEvaluators;

use App\Models\Compras\Requisicion;
use App\Models\Configuracion\Arbolaprobacion;

/**
 * Contexto de evaluación de un trigger RE (totales ya resueltos como el árbol).
 */
final class ReArbolTriggerEvalContext
{
    public function __construct(
        public readonly Arbolaprobacion $arbol,
        public readonly Requisicion $requisicion,
        public readonly int $centrocostoArbol,
        public readonly float $monto,
        public readonly int $monedaId,
        public readonly string $fechaYmd,
    ) {}
}
