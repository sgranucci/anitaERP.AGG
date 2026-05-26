<?php

namespace App\Repositories\Ventas;

interface CategoriafidelidadEntregaGastronomiaRepositoryInterface
{
    public function findPorClaveAnita(string $documento, string $fechacanje, ?int $articuloId): ?\App\Models\Ventas\CategoriafidelidadEntregaGastronomia;

    public function create(array $data): \App\Models\Ventas\CategoriafidelidadEntregaGastronomia;

    public function updatePorId(int $id, array $data): void;

    public function existeCanjeHoyPorDocumento(string $documento): bool;
}
