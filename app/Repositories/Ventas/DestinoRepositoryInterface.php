<?php

declare(strict_types=1);

namespace App\Repositories\Ventas;

interface DestinoRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function leeDestino($filtros, bool $paginar = false);

    public function findPorCodigo(int $codigo): ?\App\Models\Ventas\Destino;
}
