<?php

namespace App\Repositories\Presupuesto;

interface Partidagasto_MontoRepositoryInterface 
{
    public function create(array $data,$id);
    public function createUnique(array $data);
    public function update(array $data,$id);
    public function find($id);
    public function findOrFail($id);
    public function findPorPartidagasto($capex_partida_id);
    public function delete($capex_partida_id);
}

