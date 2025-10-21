<?php

namespace App\Repositories\Uif;

interface Cliente_Archivo_UifRepositoryInterface 
{

    public function create(Request $request, $id);
    public function createUnique($id, $file);
    public function update(Request $request, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($cliente_uif_id);

}

