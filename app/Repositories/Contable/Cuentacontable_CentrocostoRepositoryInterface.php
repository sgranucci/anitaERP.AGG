<?php

namespace App\Repositories\Contable;

interface Cuentacontable_CentrocostoRepositoryInterface 
{

    public function create(array $data, $id);
    public function createUnRegistro(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($Cuentacontable_id, $codigo);
    public function leeCuentacontable_Centrocosto($Cuentacontable_id);
}

