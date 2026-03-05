<?php

namespace App\Repositories\Compras;

interface Proveedor_FormapagoRepositoryInterface 
{

    public function create(array $data, $id);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($proveedor_id, $codigo);
    public function leeProveedorFormapago($proveedor_id);
}

