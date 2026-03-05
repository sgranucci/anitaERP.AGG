<?php

namespace App\Repositories\Contable;

use App\Http\Requests\ValidacionAsiento;

interface Asiento_ArchivoRepositoryInterface 
{

    public function create(ValidacionAsiento $request, $id);
    public function update(ValidacionAsiento $request, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($cliente_id, $codigo);

}

