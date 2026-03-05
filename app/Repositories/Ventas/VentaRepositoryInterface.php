<?php

namespace App\Repositories\Ventas;

interface VentaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function leePaginando($busqueda);
    public function leeSinPaginar($busqueda);
    public function findOrFail($id);
    public function find($id);
    public function delete($id);
    public function update(array $data, $id);
    public function create(array $data);
    public function traeUltimoComprobanteVenta($tipotransaccion_id, $puntoventa_id);
}

