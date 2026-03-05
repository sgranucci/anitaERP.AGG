<?php

namespace App\Repositories\Ventas;

interface Pedido_Combinacion_EstadoRepositoryInterface 
{
    public function all();
	public function create($data);
    public function delete($id);
    public function find($id);
    public function findOrFail($id);
    public function traeEstado($pedido_combinacion_id);
}

