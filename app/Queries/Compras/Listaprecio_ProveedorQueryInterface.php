<?php

namespace App\Queries\Compras;

interface Listaprecio_ProveedorQueryInterface
{
    public function leeListas($busqueda, $flPaginando = null);
}
