<?php

namespace App\Repositories\Stock;

interface ArticuloRepositoryInterface 
{

    public function all();
    public function create(array $data);
    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function leeArticulo($filtros, $flPaginando = null);

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function cortesArticulo(array $filtros);
    public function findPorSku($sku);
    public function leeColores();

}

