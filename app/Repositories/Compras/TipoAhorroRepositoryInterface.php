<?php

namespace App\Repositories\Compras;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TipoAhorroRepositoryInterface extends RepositoryInterface
{
    public function paginar(int $porPagina = 15): LengthAwarePaginator;

    /** Tipos activos para elegir en requisición / OC, ordenados por `orden` y nombre. */
    public function activos(): Collection;

    /** Cantidad de líneas de requisición u OC que usan el tipo. */
    public function lineasQueLoUsan(int $id): int;
}
