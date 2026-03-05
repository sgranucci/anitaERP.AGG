<?php

namespace App\Queries\Stock;

interface ArticuloQueryInterface
{
    public function first();
    public function all();
    public function allQuery(array $campos, $campoSort = null);
    public function allQueryConCombinacion(array $campos, $campoSort = null);
    public function traeArticulosActivos($articulo_ids = null);
    public function traeArticuloPorSku($sku);
    public function traeArticuloPorId($id);
}

