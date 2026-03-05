<?php

namespace App\Repositories\Caja;

interface Cobranza_ArchivoRepositoryInterface 
{

    public function create(Request $request, $id);
    public function update(Request $request, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($cobranza_id, $codigo);

}

