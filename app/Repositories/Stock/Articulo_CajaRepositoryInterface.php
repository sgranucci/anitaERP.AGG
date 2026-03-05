<?php

namespace App\Repositories\Stock;

interface Articulo_CajaRepositoryInterface 
{

    public function all();
    public function create(array $data);
    public function deletePorArticulo($articulo_id, $sku);
    public function sincronizarConAnita();

}

