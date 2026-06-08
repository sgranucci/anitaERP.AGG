<?php

namespace App\Repositories\Stock;

interface Articulo_ProveedorRepositoryInterface
{
    public function syncFromRequest(array $data, int $articuloId): void;
}
