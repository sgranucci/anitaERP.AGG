<?php

namespace App\Queries\Sala;

interface RequisicionSalaQueryInterface
{
    public function first();

    public function leeRequisicionSala($filtros, $flPaginando = null, $withArticulos = false);

    public function listadoExport($filtros);
}
