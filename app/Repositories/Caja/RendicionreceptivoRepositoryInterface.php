<?php

namespace App\Repositories\Caja;

interface RendicionreceptivoRepositoryInterface extends RepositoryInterface
{
    public function all();
	public function leeRendicionreceptivo($busqueda, $flPaginando = null);
}

