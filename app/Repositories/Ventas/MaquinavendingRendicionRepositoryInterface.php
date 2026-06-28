<?php

namespace App\Repositories\Ventas;

interface MaquinavendingRendicionRepositoryInterface
{
    public function leeRendiciones(array $filtros, bool $paginar);

    public function findOrFail(int $id);

    public function create(array $data);
}
