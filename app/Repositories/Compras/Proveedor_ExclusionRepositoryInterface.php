<?php

namespace App\Repositories\Compras;

interface Proveedor_ExclusionRepositoryInterface 
{

    public function create(array $data, $id);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($proveedor_id, $codigo);
    public function leeProveedorExclusion($proveedor_id);
}

