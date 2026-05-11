<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Requisicion_Presupuesto;
use Illuminate\Support\Collection;

class Requisicion_PresupuestoRepository implements Requisicion_PresupuestoRepositoryInterface
{
    protected $model;

    public function __construct(Requisicion_Presupuesto $requisicion_presupuesto)
    {
        $this->model = $requisicion_presupuesto;
    }

    public function listarCabecerasPorRequisicion(int $requisicionId): Collection
    {
        return $this->model->query()
            ->where('requisicion_id', $requisicionId)
            ->with([
                'proveedores',
                'condicionentregas',
                'condicioncompras',
                'condicionpagos',
                'requisicion_presupuesto_archivos',
            ])
            ->withCount('requisicion_presupuesto_articulos')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    public function findDetalle(int $requisicionId, int $presupuestoId): ?Requisicion_Presupuesto
    {
        return $this->model->query()
            ->where('requisicion_id', $requisicionId)
            ->where('id', $presupuestoId)
            ->with([
                'proveedores',
                'condicionentregas',
                'condicioncompras',
                'condicionpagos',
                'requisicion_presupuesto_articulos.requisicion_articulo.articulos',
                'requisicion_presupuesto_articulos.requisicion_articulo.monedas',
                'requisicion_presupuesto_archivos',
            ])
            ->withCount('requisicion_presupuesto_articulos')
            ->first();
    }

    public function findCabecera(int $requisicionId, int $presupuestoId): ?Requisicion_Presupuesto
    {
        return $this->model->query()
            ->where('requisicion_id', $requisicionId)
            ->where('id', $presupuestoId)
            ->first();
    }

    public function create(array $data): Requisicion_Presupuesto
    {
        return $this->model->create($data);
    }

    public function updateCabecera(Requisicion_Presupuesto $presupuesto, array $data): bool
    {
        return $presupuesto->update($data);
    }

    public function deleteCabecera(Requisicion_Presupuesto $presupuesto): bool
    {
        return (bool) $presupuesto->delete();
    }

    public function demoteOtrosElegidos(int $requisicionId, int $exceptPresupuestoId, string $estadoElegido, string $estadoActivo): void
    {
        $this->model->query()
            ->where('requisicion_id', $requisicionId)
            ->where('id', '<>', $exceptPresupuestoId)
            ->where('estado', $estadoElegido)
            ->update(['estado' => $estadoActivo]);
    }
}
