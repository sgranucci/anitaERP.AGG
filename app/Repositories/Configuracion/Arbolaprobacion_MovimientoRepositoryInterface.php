<?php

namespace App\Repositories\Configuracion;

interface Arbolaprobacion_MovimientoRepositoryInterface 
{

    public function create(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($id);
    
}

