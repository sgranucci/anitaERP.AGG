<?php

namespace App\Repositories\Configuracion;

interface Arbolaprobacion_NivelRepositoryInterface 
{

    public function create(array $data, $id);
    public function createUnique(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($arbolaprobacion_id);
    
}

