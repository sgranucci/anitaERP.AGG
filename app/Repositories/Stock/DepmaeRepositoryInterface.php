<?php

namespace App\Repositories\Stock;

use Illuminate\Support\Collection;

interface DepmaeRepositoryInterface
{
    public function allFiltrado(): Collection;

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, \App\Models\Stock\Depmae>
     */
    public function leeDepmae($filtros, bool $paginar = false);

    public function listadoParaEmpresa(?int $empresaId): Collection;

    public function queryFiltrado(): \Illuminate\Database\Eloquent\Builder;
}
