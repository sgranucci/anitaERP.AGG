<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Requisicion_Presupuesto_Archivo;
use Illuminate\Support\Collection;

class Requisicion_Presupuesto_ArchivoRepository implements Requisicion_Presupuesto_ArchivoRepositoryInterface
{
    protected $model;

    public function __construct(Requisicion_Presupuesto_Archivo $requisicion_presupuesto_archivo)
    {
        $this->model = $requisicion_presupuesto_archivo;
    }

    public function listarPorPresupuesto(int $requisicionPresupuestoId): Collection
    {
        return $this->model->query()
            ->where('requisicion_presupuesto_id', $requisicionPresupuestoId)
            ->get();
    }

    public function findPorPresupuestoYId(int $requisicionPresupuestoId, int $archivoId): ?Requisicion_Presupuesto_Archivo
    {
        return $this->model->query()
            ->where('id', $archivoId)
            ->where('requisicion_presupuesto_id', $requisicionPresupuestoId)
            ->first();
    }

    public function create(array $data): Requisicion_Presupuesto_Archivo
    {
        return $this->model->create($data);
    }

    public function deleteArchivo(Requisicion_Presupuesto_Archivo $archivo): bool
    {
        return (bool) $archivo->delete();
    }

    public function deletePorPresupuesto(int $requisicionPresupuestoId): void
    {
        $this->model->query()->where('requisicion_presupuesto_id', $requisicionPresupuestoId)->delete();
    }
}
