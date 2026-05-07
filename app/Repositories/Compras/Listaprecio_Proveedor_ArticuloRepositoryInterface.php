<?php

namespace App\Repositories\Compras;

interface Listaprecio_Proveedor_ArticuloRepositoryInterface
{
    public function syncFromRequest(array $data, $listaprecio_proveedor_id, $usuario_id);

    public function createRow(array $data);
}
