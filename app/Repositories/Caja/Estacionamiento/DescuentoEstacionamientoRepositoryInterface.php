<?php

namespace App\Repositories\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\DescuentoEstacionamiento;
use App\Repositories\Caja\RepositoryInterface;

interface DescuentoEstacionamientoRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function findPorCodigo(string $codigo): ?DescuentoEstacionamiento;

    public function consultaDescuento(string $consulta): string;
}
