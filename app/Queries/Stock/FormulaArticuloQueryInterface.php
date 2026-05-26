<?php

namespace App\Queries\Stock;

interface FormulaArticuloQueryInterface
{
    public function first();

    public function leeFormulaArticulo($busqueda, $flPaginando = null, $withHijos = false, ?string $conOpcionales = null);
}
