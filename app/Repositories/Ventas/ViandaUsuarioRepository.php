<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\ViandaUsuario;
use App\Support\Ventas\ViandaUsuarioListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ViandaUsuarioRepository implements ViandaUsuarioRepositoryInterface
{
    public function __construct(
        private ViandaUsuario $model,
    ) {
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return LengthAwarePaginator|Collection<int, ViandaUsuario>
     */
    public function leeUsuarios($filtros, bool $paginar)
    {
        if (is_string($filtros)) {
            $filtros = ['busqueda' => $filtros !== '' ? $filtros : null];
        }

        $query = $this->model->with(['centrocosto', 'tipoMenu'])
            ->orderBy('nombre')
            ->orderBy('codigo_usuario');

        if (is_array($filtros) && ViandaUsuarioListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ViandaUsuarioListadoFiltros::aplicar($query, $filtros);
        }

        return $paginar ? $query->paginate(25) : $query->get();
    }

    public function existeRegistro(): bool
    {
        return $this->model->newQuery()->exists();
    }

    public function create(array $data)
    {
        return $this->model->create($this->filtrarCabecera($data))
            ->load(['centrocosto', 'tipoMenu']);
    }

    public function update(array $data, $id)
    {
        $registro = $this->model->findOrFail($id);
        $registro->update($this->filtrarCabecera($data));

        return $registro->fresh(['centrocosto', 'tipoMenu']);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        return $this->model->with(['centrocosto', 'tipoMenu'])->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->with(['centrocosto', 'tipoMenu'])->findOrFail($id);
    }

    private function filtrarCabecera(array $data): array
    {
        return collect($data)->only([
            'codigo_usuario',
            'nombre',
            'password',
            'centrocosto_id',
            'tipo_usuario',
            'vianda_tipo_menu_id',
            'estado',
        ])->all();
    }
}
