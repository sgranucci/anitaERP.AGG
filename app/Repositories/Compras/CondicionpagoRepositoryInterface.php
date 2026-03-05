<?php

namespace App\Repositories\Compras;

interface CondicionpagoRepositoryInterface extends RepositoryInterface
{

    public function all();
	public function findPorId($id);
    public function findPorCodigo($codigo);

}

