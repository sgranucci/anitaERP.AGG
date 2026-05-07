<?php

namespace App\Repositories\Compras;

interface Listaprecio_Proveedor_ArchivoRepositoryInterface
{
    public function create($request, $listaprecio_proveedor_id);

    public function update($request, $listaprecio_proveedor_id);
}
