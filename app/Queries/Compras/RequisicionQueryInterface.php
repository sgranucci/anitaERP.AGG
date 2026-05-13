<?php

namespace App\Queries\Compras;

interface RequisicionQueryInterface
{
    public function leeRequisicion($busqueda, $flPaginando = null, $withArticulos = false);

    /**
     * Indica si la requisición existe y el usuario actual puede verla en el listado (empresa, oficina, centro de costo).
     */
    public function requisicionAccesiblePorUsuario(int $id): bool;
}
