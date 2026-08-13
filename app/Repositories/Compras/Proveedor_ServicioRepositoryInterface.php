<?php

namespace App\Repositories\Compras;

interface Proveedor_ServicioRepositoryInterface
{
    public function create(array $data, $id);

    public function update(array $data, $id);

    public function delete($proveedor_id, $codigo);

    public function find($id);

    public function leeProveedorServicio($proveedor_id);

    public function findOrFail($id);
}
