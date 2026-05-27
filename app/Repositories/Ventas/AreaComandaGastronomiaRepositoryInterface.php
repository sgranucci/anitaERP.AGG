<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\AreaComandaGastronomia;

interface AreaComandaGastronomiaRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function findPorCodigo(string $codigo, int $empresaId): ?AreaComandaGastronomia;
}
