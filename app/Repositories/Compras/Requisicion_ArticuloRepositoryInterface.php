<?php

namespace App\Repositories\Compras;

interface Requisicion_ArticuloRepositoryInterface
{
    public function syncFromRequest(array $data, $requisicion_id);
}
