<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Requisicion_Presupuesto_Articulo;

class Requisicion_Presupuesto_ArticuloRepository implements Requisicion_Presupuesto_ArticuloRepositoryInterface
{
    protected $model;

    public function __construct(Requisicion_Presupuesto_Articulo $requisicion_presupuesto_articulo)
    {
        $this->model = $requisicion_presupuesto_articulo;
    }

    public function deletePorPresupuesto(int $requisicionPresupuestoId): void
    {
        $this->model->query()->where('requisicion_presupuesto_id', $requisicionPresupuestoId)->delete();
    }

    public function create(array $data): Requisicion_Presupuesto_Articulo
    {
        return $this->model->create($data);
    }
}
