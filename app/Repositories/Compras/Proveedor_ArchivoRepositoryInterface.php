<?php

namespace App\Repositories\Compras;

use App\Http\Requests\ValidacionProveedor;

interface Proveedor_ArchivoRepositoryInterface 
{

    public function create(ValidacionProveedor $request, $id);
    public function update(ValidacionProveedor $request, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($cliente_id, $codigo);
    public function sincronizarConAnita();

}

