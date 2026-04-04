<?php

namespace App\Repositories\Compras;

interface Proveedor_EncuestaRepositoryInterface 
{

    public function create(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($proveedor_id, $codigo);
    public function leePorProveedor($proveedor_id, $busqueda);
}

