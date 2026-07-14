<?php

namespace App\Repositories\Ventas;

interface Remito_ArticuloRepositoryInterface
{
    public function all();

    public function create(array $data);

    public function update($data, $id);

    public function delete($id);

    public function deleteporremito($remito_id);

    public function find($id);

    public function findOrFail($id);

    public function findPorRemitoId($remito_id);
}
