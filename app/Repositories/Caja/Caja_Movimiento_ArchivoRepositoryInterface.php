<?php

namespace App\Repositories\Caja;

interface Caja_Movimiento_ArchivoRepositoryInterface 
{

    public function create(Request $request, $id);
    public function update(Request $request, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($caja_movimiento_id, $codigo);

}

