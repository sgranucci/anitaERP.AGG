<?php

namespace App\Repositories\Uif;

interface Cliente_Premio_Archivo_UifRepositoryInterface 
{

    public function create(Request $request, $id);
    public function update(Request $request, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($cliente_uif_id);

}

