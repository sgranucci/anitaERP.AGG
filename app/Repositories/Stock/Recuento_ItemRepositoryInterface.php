<?php

namespace App\Repositories\Stock;

interface Recuento_ItemRepositoryInterface
{
    public function syncFromRequest(array $data, int $recuentoId, int $depositoId): void;
}
