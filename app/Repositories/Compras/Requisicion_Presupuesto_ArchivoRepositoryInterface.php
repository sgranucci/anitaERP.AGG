<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Requisicion_Presupuesto_Archivo;
use Illuminate\Support\Collection;

interface Requisicion_Presupuesto_ArchivoRepositoryInterface
{
    /**
     * @return Collection<int, Requisicion_Presupuesto_Archivo>
     */
    public function listarPorPresupuesto(int $requisicionPresupuestoId): Collection;

    public function findPorPresupuestoYId(int $requisicionPresupuestoId, int $archivoId): ?Requisicion_Presupuesto_Archivo;

    public function create(array $data): Requisicion_Presupuesto_Archivo;

    public function deleteArchivo(Requisicion_Presupuesto_Archivo $archivo): bool;

    public function deletePorPresupuesto(int $requisicionPresupuestoId): void;
}
