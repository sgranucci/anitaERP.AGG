<?php

namespace App\Repositories\Ventas;

interface Cliente_AvisoRepositoryInterface
{
    public function create(array $data, $id);

    public function update(array $data, $id);

    public function findPorClienteId($cliente_id);
}
