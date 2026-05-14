<?php

namespace App\Repositories\Stock;

interface Formula_Articulo_ArchivoRepositoryInterface
{
    public function create($request, int $id);

    public function update($request, int $id);
}
