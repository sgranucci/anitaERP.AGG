<?php

namespace App\Repositories\Ventas;

interface Pedido_Combinacion_TalleRepositoryInterface 
{

    public function all();
	public function create($pedido_combinacion_id, $talle_id, $cantidad, $precio);
    public function delete($id);
    public function find($id);
    public function findOrFail($id);
	public function deleteporpedido_combinacion($id);
    public function sincronizarConAnita();

}

