<?php

namespace App\Repositories\Compras;

use Illuminate\Http\Request;

interface Ordencompra_ArchivoRepositoryInterface
{
    public function create(Request $request, int $id);

    public function update(Request $request, int $id);
}
