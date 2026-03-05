<?php

namespace App\Repositories\Stock;

interface ArticuloRepositoryInterface 
{

    public function all();
    public function create(array $data);
    public function leeArticulo($busqueda, $flPaginando = null);
    public function findPorSku($sku);

}

