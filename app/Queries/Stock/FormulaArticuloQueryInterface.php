<?php

namespace App\Queries\Stock;

interface FormulaArticuloQueryInterface
{
    public function first();

    public function leeFormulaArticulo($busqueda, $flPaginando = null, $withHijos = false, ?string $conOpcionales = null);

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function leeListado(array $filtros, bool $paginar, bool $withHijos = true);

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function cortesListado(array $filtros): array;
}
