<?php

namespace App\Repositories\Compras;

interface OrdencompraRepositoryInterface
{
    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findOrFail($id);

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function listadoIndex(?string $busqueda, ?int $sectorUsuarioId);

    /**
     * Siguiente número de orden de compra (único global, sin filtrar por empresa).
     */
    public function proximoNumeroOrdencompra(): int;
}
