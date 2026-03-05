<?php

namespace App\Repositories\Stock;

interface MovimientoStockRepositoryInterface 
{
    public function estadoEnum();
    public function all();
    public function create(array $data);
    public function update(array $data, $id);
    public function delete($id);
    public function find($id);
    public function deletePorId($id);
    public function latest($campo);
}

