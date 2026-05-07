<?php

namespace App\Repositories\Compras;

interface Listaprecio_ProveedorRepositoryInterface
{
    public function create(array $data);

    public function update(array $data, $id);

    public function find($id);

    public function delete($id);

    public function sincronizarConAnita();
}
