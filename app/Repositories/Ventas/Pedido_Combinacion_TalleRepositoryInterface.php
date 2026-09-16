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
	public function findporpedido_combinacion($pedido_combinacion_id);
	/**
	 * Sincroniza medidas por talle_id (update/insert/delete) sin recrear todo.
	 * @param  array<int, object|array>  $medidas
	 * @param  array<string, mixed>|null  $contextoOt
	 * @return array{total_pares: float, grabo: bool, talles: \Illuminate\Support\Collection}
	 */
	public function sincronizarMedidas(int $pedidoCombinacionId, array $medidas, ?array $contextoOt = null): array;
    public function sincronizarConAnita();

}

