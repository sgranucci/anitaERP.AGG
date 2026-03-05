<?php

namespace App\Repositories\Ventas;

interface VendedorRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorId($id);
    public function findPorCodigo($codigo);
	public function leeVendedor($busqueda, $flPaginando = null);

}

