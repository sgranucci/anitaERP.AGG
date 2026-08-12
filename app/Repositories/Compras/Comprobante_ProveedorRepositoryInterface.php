<?php

namespace App\Repositories\Compras;

interface Comprobante_ProveedorRepositoryInterface
{
    public function all();

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    /**
     * @param  array|string|null  $filtros
     */
    public function leeComprobanteProveedor($filtros, bool $paginar = false);
}
