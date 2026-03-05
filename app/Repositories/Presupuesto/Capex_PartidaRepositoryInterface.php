<?php

namespace App\Repositories\Presupuesto;

interface Capex_PartidaRepositoryInterface 
{
    public function create(array &$data, $id);
    public function createUnique(array $data);
    public function update(array &$data, $id);
    public function find($id);
    public function findOrFail($id);
    public function findPorCapex($capex_id);
    public function delete($capex_id, $codigo);
}

