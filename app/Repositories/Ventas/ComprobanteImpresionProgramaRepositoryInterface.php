<?php

namespace App\Repositories\Ventas;

interface ComprobanteImpresionProgramaRepositoryInterface extends RepositoryInterface
{
    public function leeProgramas($filtros, $flPaginando = null);
}
