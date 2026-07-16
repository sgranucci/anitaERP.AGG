<?php

namespace App\Repositories\Caja;

use App\Models\Caja\TicketCanjeCaja;
use App\Support\Caja\TicketCanjeCajaListadoFiltros;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TicketCanjeCajaRepository implements TicketCanjeCajaRepositoryInterface
{
    public function __construct(
        private TicketCanjeCaja $model,
    ) {
    }

    public function leeTickets(array $filtros, bool $paginar = true)
    {
        $query = $this->model->newQuery()
            ->with(['empresa', 'usuario', 'cajero', 'clienteVip'])
            ->orderByDesc('id');

        if (! empty($filtros['empresa_id'])) {
            $query->where('empresa_id', (int) $filtros['empresa_id']);
        } elseif (! empty($filtros['empresas_asignadas']) && is_array($filtros['empresas_asignadas'])) {
            $query->whereIn('empresa_id', $filtros['empresas_asignadas']);
        }

        if (! empty($filtros['solo_propios']) && ! empty($filtros['usuario_id'])) {
            $query->where('usuario_id', (int) $filtros['usuario_id']);
        }

        TicketCanjeCajaListadoFiltros::aplicar($query, $filtros);

        return $paginar ? $query->paginate(10) : $query->get();
    }

    public function create(array $data): TicketCanjeCaja
    {
        return $this->model->create($data);
    }

    public function find(int $id): ?TicketCanjeCaja
    {
        return $this->model->with(['empresa', 'usuario', 'cajero', 'clienteVip'])->find($id);
    }

    public function findOrFail(int $id): TicketCanjeCaja
    {
        return $this->model->with(['empresa', 'usuario', 'cajero', 'clienteVip'])->findOrFail($id);
    }

    public function siguienteMovimientoId(int $empresaId): int
    {
        $max = (int) $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->lockForUpdate()
            ->max('movimiento_id');

        return $max + 1;
    }

    public function listarPorMovimiento(int $empresaId, int $movimientoId): Collection
    {
        return $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->where('movimiento_id', $movimientoId)
            ->orderBy('numero_ticket')
            ->get();
    }

    /**
     * Reserva el próximo movimiento_id de la empresa dentro de una transacción.
     */
    public function reservarMovimientoId(int $empresaId): int
    {
        return (int) DB::transaction(function () use ($empresaId) {
            return $this->siguienteMovimientoId($empresaId);
        });
    }
}
