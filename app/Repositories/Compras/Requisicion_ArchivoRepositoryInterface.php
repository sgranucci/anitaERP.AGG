<?php

namespace App\Repositories\Compras;

interface Requisicion_ArchivoRepositoryInterface
{
    public function create($request, $id);
    public function update($request, $id);
}
