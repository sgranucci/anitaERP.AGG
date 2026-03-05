<?php

namespace App\Repositories\Ventas;

interface Pedido_Articulo_CajaRepositoryInterface 
{
    public function all();
	public function create($data);
    public function delete($id);
    public function find($id);
    public function findOrFail($id);
    public function findPorPedidoId($id);
    public function deletePorPedidoId($id);
}

