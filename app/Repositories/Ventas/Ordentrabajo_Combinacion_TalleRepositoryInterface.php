<?php

namespace App\Repositories\Ventas;

interface Ordentrabajo_Combinacion_TalleRepositoryInterface 
{

    public function all();
	public function create($data);
    public function update(array $data, $id);
    public function delete($id, $nro_orden);
    public function find($id);
    public function findOrFail($id);
    public function deleteporordentrabajo($ordentrabajo_id);
    public function findPorOrdenTrabajoId($ordentrabajo_id);
    public function findPorPedidoCombinacionTalleId($pedido_combinacion_talle_id);
    public function findPorOrdentrabajoStockId($ordentrabajostock_id);
    public function sincronizarConAnita();

}

