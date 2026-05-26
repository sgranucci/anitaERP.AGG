<?php

namespace App\Repositories\Ventas;

interface CategoriafidelidadArticuloGastronomiaRepositoryInterface
{
    public function syncFromRequest(array $data, int $categoriafidelidadId): void;

    public function reemplazarArticulos(int $categoriafidelidadId, array $articuloIds): void;
}
