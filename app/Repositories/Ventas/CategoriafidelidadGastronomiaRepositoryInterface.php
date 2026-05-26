<?php

namespace App\Repositories\Ventas;

interface CategoriafidelidadGastronomiaRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function existeRegistro(): bool;

    public function findPorCodigo(string $codigo): ?\App\Models\Ventas\CategoriafidelidadGastronomia;
}
