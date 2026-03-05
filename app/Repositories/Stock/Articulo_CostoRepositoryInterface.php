<?php

namespace App\Repositories\Stock;

interface Articulo_CostoRepositoryInterface 
{

    public function all();
    public function create(array $data);
    public function deletePorArticulo($articulo_id);
    public function sincronizarConAnita();
    public function findPorArticuloTarea($articulo_id, $tarea_id);
}

