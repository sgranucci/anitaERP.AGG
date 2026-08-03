<?php

declare(strict_types=1);

namespace App\Repositories\Caja;

use App\Models\Caja\Remesa;

interface RemesaRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, Remesa>
     */
    public function leeRemesa($filtros, bool $paginar = true);

    public function find($id): ?Remesa;

    public function findOrFail($id): Remesa;

    public function nextNumero(int $empresaId): int;
}
