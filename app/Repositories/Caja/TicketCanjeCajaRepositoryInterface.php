<?php

namespace App\Repositories\Caja;

use App\Models\Caja\TicketCanjeCaja;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TicketCanjeCajaRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator|Collection
     */
    public function leeTickets(array $filtros, bool $paginar = true);

    public function create(array $data): TicketCanjeCaja;

    public function find(int $id): ?TicketCanjeCaja;

    public function findOrFail(int $id): TicketCanjeCaja;

    public function siguienteMovimientoId(int $empresaId): int;

    /**
     * @return Collection<int, TicketCanjeCaja>
     */
    public function listarPorMovimiento(int $empresaId, int $movimientoId): Collection;
}
