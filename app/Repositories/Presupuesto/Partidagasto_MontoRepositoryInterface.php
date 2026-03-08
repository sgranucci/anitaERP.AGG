<?php

namespace App\Repositories\Presupuesto;

interface Partidagasto_MontoRepositoryInterface 
{
    public function create(array $data);
    public function createUnique(array $data);
    public function update(array $data);
    public function find($id);
    public function findOrFail($id);
    public function findPorPartidagasto($capex_partida_id);
    public function delete($capex_partida_id);
}

