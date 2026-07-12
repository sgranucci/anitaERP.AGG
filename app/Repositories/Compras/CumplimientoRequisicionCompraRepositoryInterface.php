<?php

namespace App\Repositories\Compras;

use App\Models\Compras\CumplimientoRequisicionCompra;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CumplimientoRequisicionCompraRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return LengthAwarePaginator|Collection
     */
    public function leeCumplimientos($filtros, bool $paginar = true);

    public function findConDetalle(int $id): ?CumplimientoRequisicionCompra;

    /** @return list<CumplimientoRequisicionCompra> */
    public function listarPorRequisicion(int $requisicionId): array;

    public function siguienteNumero(): int;
}
