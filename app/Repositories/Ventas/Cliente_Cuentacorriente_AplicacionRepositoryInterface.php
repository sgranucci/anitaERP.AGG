<?php

namespace App\Repositories\Ventas;

interface Cliente_Cuentacorriente_AplicacionRepositoryInterface 
{

    public function create(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($id);
    public function buscaPorCuentaCorrienteCobranza($cliente_cuentacorriente_id, $cobranza_id);
    
}

