<?php

namespace App\Repositories\Ordenventa;

interface Ordenventa_ArchivoRepositoryInterface 
{

    public function create(Request $request, $id);
    public function update(Request $request, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($ordenventa_id, $codigo);

}

