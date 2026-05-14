<?php

namespace App\Repositories\Stock;

interface Formula_Articulo_HijoRepositoryInterface
{
    public function syncFromRequest(array $data, int $formula_articulo_id);
}
