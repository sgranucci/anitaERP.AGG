<?php

namespace App\Repositories\Presupuesto;

interface Capex_Partida_MontoRepositoryInterface 
{
    public function create(array $data);
    public function createUnique(array $data);
    public function update(array $data);
    public function find($id);
    public function findOrFail($id);
    public function findPorCapex_Partida($capex_partida_id);
    public function delete($capex_partida_id);
}

