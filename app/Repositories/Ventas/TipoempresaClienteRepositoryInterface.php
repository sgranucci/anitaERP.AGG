<?php

namespace App\Repositories\Ventas;

interface TipoempresaClienteRepositoryInterface extends RepositoryInterface
{
    public function findPorCodigo($codigo);

    public function findPorId($id);
}
