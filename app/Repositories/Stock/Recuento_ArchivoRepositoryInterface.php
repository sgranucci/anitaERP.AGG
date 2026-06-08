<?php

namespace App\Repositories\Stock;

interface Recuento_ArchivoRepositoryInterface
{
    public function create($request, int $id);

    public function update($request, int $id);
}
