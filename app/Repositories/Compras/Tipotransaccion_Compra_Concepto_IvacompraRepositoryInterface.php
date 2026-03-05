<?php

namespace App\Repositories\Compras;

interface Tipotransaccion_Compra_Concepto_IvacompraRepositoryInterface 
{

    public function create(array $data, $id);
    public function createUnRegistro(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($tipotransaccion_compra_id, $codigo);
    public function leeTipotransaccion_Compra_Concepto_Ivacompra($tipotransaccion_compra_id);
}

