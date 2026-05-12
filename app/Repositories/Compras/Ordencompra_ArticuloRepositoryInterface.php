<?php

namespace App\Repositories\Compras;

interface Ordencompra_ArticuloRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function syncFromRequest(array $data, int $ordencompra_id): void;
}
