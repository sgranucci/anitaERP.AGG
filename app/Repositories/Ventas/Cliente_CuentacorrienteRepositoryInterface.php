<?php

namespace App\Repositories\Ventas;

interface Cliente_CuentacorrienteRepositoryInterface 
{

    public function create(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($id);
    
}

