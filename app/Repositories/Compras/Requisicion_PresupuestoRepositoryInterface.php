<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Requisicion_Presupuesto;
use Illuminate\Support\Collection;

interface Requisicion_PresupuestoRepositoryInterface
{
    /**
     * Listado de cabeceras con relaciones para la pestaña / índice JSON.
     *
     * @return Collection<int, Requisicion_Presupuesto>
     */
    public function listarCabecerasPorRequisicion(int $requisicionId): Collection;

    public function findDetalle(int $requisicionId, int $presupuestoId): ?Requisicion_Presupuesto;

    public function findCabecera(int $requisicionId, int $presupuestoId): ?Requisicion_Presupuesto;

    public function create(array $data): Requisicion_Presupuesto;

    public function updateCabecera(Requisicion_Presupuesto $presupuesto, array $data): bool;

    public function deleteCabecera(Requisicion_Presupuesto $presupuesto): bool;

    /**
     * Si un presupuesto pasa a ELEGIDO, los demás ELEGIDO de la misma requisición pasan a ACTIVO.
     */
    public function demoteOtrosElegidos(int $requisicionId, int $exceptPresupuestoId, string $estadoElegido, string $estadoActivo): void;
}
