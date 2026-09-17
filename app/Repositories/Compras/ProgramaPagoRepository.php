<?php

namespace App\Repositories\Compras;

use App\Models\Compras\ProgramaPago;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProgramaPagoRepository implements ProgramaPagoRepositoryInterface
{
    public function __construct(
        private ProgramaPago $model,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function create(array $data): ProgramaPago
    {
        return $this->model->create($data);
    }

    public function update(array $data, int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->update($data);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->destroy($id);
    }

    public function find(int $id): ProgramaPago
    {
        $row = $this->model->with([
            'empresas',
            'usuarios',
            'lineas.proveedores',
            'lineas.asignaciones',
        ])->find($id);

        if ($row === null) {
            throw new ModelNotFoundException('Programa de pago no encontrado.');
        }

        return $row;
    }

    public function findOrFail(int $id): ProgramaPago
    {
        return $this->find($id);
    }

    public function leeProgramaPago(array $filtros = [], bool $flPaginando = true): LengthAwarePaginator|Collection
    {
        $query = $this->model->query()
            ->with(['empresas', 'usuarios'])
            ->withCount('lineas')
            ->orderByDesc('fecha_base')
            ->orderByDesc('id');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'programa_pago.empresa_id');

        if (! empty($filtros['empresa_id'])) {
            $query->where('empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['estado'])) {
            $query->where('estado', (string) $filtros['estado']);
        }
        if (! empty($filtros['valor'])) {
            $like = '%'.$filtros['valor'].'%';
            $query->where(function ($q) use ($like) {
                $q->where('titulo', 'like', $like)
                    ->orWhere('detalle', 'like', $like)
                    ->orWhere('id', 'like', $like);
            });
        }

        return $flPaginando ? $query->paginate(10) : $query->get();
    }
}
