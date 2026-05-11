<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Requisicion_Presupuesto_Articulo;

interface Requisicion_Presupuesto_ArticuloRepositoryInterface
{
    public function deletePorPresupuesto(int $requisicionPresupuestoId): void;

    public function create(array $data): Requisicion_Presupuesto_Articulo;
}
