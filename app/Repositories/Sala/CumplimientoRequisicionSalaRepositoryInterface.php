<?php

namespace App\Repositories\Sala;

use App\Models\Sala\CumplimientoRequisicionSala;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CumplimientoRequisicionSalaRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function leeCumplimientos($filtros, bool $paginar = true): LengthAwarePaginator|Collection;

    public function findConDetalle(int $id): ?CumplimientoRequisicionSala;

    /** @return list<CumplimientoRequisicionSala> */
    public function listarPorRequisicion(int $requisicionSalaId): array;

    public function siguienteNumero(): int;
}
