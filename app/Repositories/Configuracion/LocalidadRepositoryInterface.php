<?php

namespace App\Repositories\Configuracion;

interface LocalidadRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorId($id);
    public function findPorCodigo($codigo);
	public function leeLocalidad($busqueda, $flPaginando = null);

}

