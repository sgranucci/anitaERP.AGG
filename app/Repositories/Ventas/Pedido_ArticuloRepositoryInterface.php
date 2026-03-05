<?php

namespace App\Repositories\Ventas;

interface Pedido_ArticuloRepositoryInterface
{
    public function all();
	public function create(array $data);
    public function update($data, $id);
    public function updatePorOtId($ot_id);
    public function updatePorId(array $data, $id);
    public function delete($id);
    public function deleteporpedido($pedido_id);
    public function find($id);
    public function findOrFail($id);
    public function findPorPedidoId($pedido_id);
    public function sincronizarConAnita();
	public function actualizarAnitaEstado($estado, $codigo, $orden);
	public function actualizarAnitaNroOt($nro_orden, $codigo, $orden);
}

