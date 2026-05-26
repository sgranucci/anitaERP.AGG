<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\CategoriafidelidadEntregaGastronomia;
use Carbon\Carbon;

class CategoriafidelidadEntregaGastronomiaRepository implements CategoriafidelidadEntregaGastronomiaRepositoryInterface
{
    public function __construct(
        private CategoriafidelidadEntregaGastronomia $model,
    ) {
    }

    public function findPorClaveAnita(string $documento, string $fechacanje, ?int $articuloId): ?CategoriafidelidadEntregaGastronomia
    {
        $query = $this->model->newQuery()
            ->where('documento', $documento)
            ->where('fechacanje', $fechacanje);

        if ($articuloId === null) {
            $query->whereNull('articulo_id');
        } else {
            $query->where('articulo_id', $articuloId);
        }

        return $query->first();
    }

    public function create(array $data): CategoriafidelidadEntregaGastronomia
    {
        return $this->model->create($data);
    }

    public function updatePorId(int $id, array $data): void
    {
        $this->model->where('id', $id)->update($data);
    }

    public function existeCanjeHoyPorDocumento(string $documento): bool
    {
        $documento = trim($documento);
        if ($documento === '') {
            return false;
        }

        return $this->model->newQuery()
            ->where('documento', $documento)
            ->whereDate('fechacanje', Carbon::today())
            ->exists();
    }
}
