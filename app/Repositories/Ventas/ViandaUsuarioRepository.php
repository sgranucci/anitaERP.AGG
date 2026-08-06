<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\ViandaUsuario;
use App\Models\Ventas\ViandaConsumo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Ventas\ViandaUsuarioListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ViandaUsuarioRepository implements ViandaUsuarioRepositoryInterface
{
    public function __construct(
        private ViandaUsuario $model,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return LengthAwarePaginator|Collection<int, ViandaUsuario>
     */
    public function leeUsuarios($filtros, bool $paginar)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = ViandaUsuarioListadoFiltros::filtrosVacios();
            $filtros['valor'] = $texto;
            $filtros['busqueda'] = $texto;
        } elseif (! is_array($filtros)) {
            $filtros = ViandaUsuarioListadoFiltros::filtrosVacios();
        }

        if (! isset($filtros['empresas_asignadas']) || $filtros['empresas_asignadas'] === []) {
            $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        }

        $query = $this->model->newQuery()
            ->select('vianda_usuario.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'vianda_usuario.empresa_id')
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'vianda_usuario.centrocosto_id')
            ->leftJoin('vianda_tipo_menu', 'vianda_tipo_menu.id', '=', 'vianda_usuario.vianda_tipo_menu_id')
            ->with(['empresa', 'centrocosto', 'tipoMenu']);

        ViandaUsuarioListadoFiltros::aplicarScopeEmpresasAsignadas($query, $filtros);
        ViandaUsuarioListadoFiltros::aplicarFiltrosDirectos($query, $filtros);
        ViandaUsuarioListadoFiltros::aplicar($query, $filtros);

        $query->orderBy('vianda_usuario.nombre')
            ->orderBy('vianda_usuario.codigo_usuario');

        return $paginar
            ? $query->paginate(10)->appends(ViandaUsuarioListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function existeRegistro(): bool
    {
        return $this->model->newQuery()->exists();
    }

    public function create(array $data)
    {
        return $this->model->create($this->filtrarCabecera($data))
            ->load(['empresa', 'centrocosto', 'tipoMenu']);
    }

    public function update(array $data, $id)
    {
        $registro = $this->model->findOrFail($id);

        $cabecera = $this->filtrarCabecera($data);
        if (! array_key_exists('password', $cabecera) || trim((string) $cabecera['password']) === '') {
            unset($cabecera['password']);
        }

        $registro->update($cabecera);
        $registro = $registro->fresh(['empresa', 'centrocosto', 'tipoMenu']);

        $centrocostoId = (int) ($registro->centrocosto_id ?? 0);
        if ($centrocostoId > 0) {
            ViandaConsumo::query()
                ->where('vianda_usuario_id', (int) $registro->id)
                ->where(function ($q) {
                    $q->whereNull('centrocosto_id')->orWhere('centrocosto_id', 0);
                })
                ->update(['centrocosto_id' => $centrocostoId]);
        }

        return $registro;
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        return $this->model->with(['empresa', 'centrocosto', 'tipoMenu'])->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->with(['empresa', 'centrocosto', 'tipoMenu'])->findOrFail($id);
    }

    private function filtrarCabecera(array $data): array
    {
        return collect($data)->only([
            'codigo_usuario',
            'empresa_id',
            'nombre',
            'password',
            'centrocosto_id',
            'tipo_usuario',
            'vianda_tipo_menu_id',
            'estado',
        ])->all();
    }
}
