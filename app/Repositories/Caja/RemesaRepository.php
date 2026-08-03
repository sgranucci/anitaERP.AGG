<?php

declare(strict_types=1);

namespace App\Repositories\Caja;

use App\Models\Caja\Remesa;
use App\Support\Caja\RemesaListadoFiltros;
use Illuminate\Database\Eloquent\Builder;

class RemesaRepository implements RemesaRepositoryInterface
{
    public function __construct(
        private readonly Remesa $model,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, Remesa>
     */
    public function leeRemesa($filtros, bool $paginar = true)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = RemesaListadoFiltros::filtrosVacios();
            $filtros['valor'] = $texto;
            $filtros['busqueda'] = $texto;
        } elseif (! is_array($filtros)) {
            $filtros = RemesaListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('remesa.*')
            ->join('empresa', 'empresa.id', '=', 'remesa.empresa_id')
            ->with(['empresa:id,nombre', 'usuario:id,nombre']);

        // Siempre aplica (empresa externa + panel / búsqueda).
        RemesaListadoFiltros::aplicar($query, $filtros);

        $query->orderByDesc('remesa.fecha')
            ->orderByDesc('remesa.id');

        return $paginar
            ? $query->paginate(10)->appends(RemesaListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function find($id): ?Remesa
    {
        return $this->queryDetalle()->find($id);
    }

    public function findOrFail($id): Remesa
    {
        return $this->queryDetalle()->findOrFail($id);
    }

    public function nextNumero(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 1;
        }

        $max = (int) ($this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->max('numero') ?? 0);

        return $max + 1;
    }

    /**
     * @return Builder<Remesa>
     */
    private function queryDetalle(): Builder
    {
        return $this->model->newQuery()->with([
            'lineas.cuentacaja',
            'empresa',
            'asiento',
            'cajaMovimiento',
            'usuario',
        ]);
    }
}
