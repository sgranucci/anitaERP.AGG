<?php

namespace App\Repositories\Configuracion;

interface Cotizacion_MonedaRepositoryInterface 
{
    public function create(array $data, $id);
    public function createDirecto(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($fecha);
}

