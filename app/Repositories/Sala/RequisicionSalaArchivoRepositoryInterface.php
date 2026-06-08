<?php

namespace App\Repositories\Sala;

interface RequisicionSalaArchivoRepositoryInterface
{
    public function create($request, $id);

    public function update($request, $id);
}
