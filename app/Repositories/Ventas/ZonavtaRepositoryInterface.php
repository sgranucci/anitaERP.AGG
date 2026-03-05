<?php

namespace App\Repositories\Ventas;

interface ZonavtaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorId($id);
    public function findPorCodigo($codigo);
	public function leeZonavta($busqueda, $flPaginando = null);

}

