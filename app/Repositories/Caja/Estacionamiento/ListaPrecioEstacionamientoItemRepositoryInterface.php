<?php

namespace App\Repositories\Caja\Estacionamiento;

interface ListaPrecioEstacionamientoItemRepositoryInterface
{
    public function syncFromRequest(array $data, int $listaPrecioEstacionamientoId): void;
}
