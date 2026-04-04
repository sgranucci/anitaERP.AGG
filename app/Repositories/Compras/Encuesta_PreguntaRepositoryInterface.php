<?php

namespace App\Repositories\Compras;

interface Encuesta_PreguntaRepositoryInterface 
{

    public function create(array $data, $id);
    public function createUnique(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($arbolaprobacion_id);
    
}

