<?php

namespace App\Repositories\Compras;

interface Proveedor_Encuesta_PreguntaRepositoryInterface 
{

    public function create(array $data, $id);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($id);
    public function leePorProveedorEncuesta_Pregunta($proveedor_encuesta_id);

}

