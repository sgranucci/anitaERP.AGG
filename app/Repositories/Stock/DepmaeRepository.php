<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Depmae;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Stock\DepmaeListadoFiltros;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DepmaeRepository implements DepmaeRepositoryInterface
{
    public function __construct(
        private readonly Depmae $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function allFiltrado(): Collection
    {
        return $this->leeDepmae(DepmaeListadoFiltros::filtrosVacios(), false);
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|Collection<int, Depmae>
     */
    public function leeDepmae($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => DepmaeListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = DepmaeListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('depmae.*')
            ->join('empresa', 'empresa.id', '=', 'depmae.empresa_id')
            ->with('empresas:id,nombre');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'depmae.empresa_id');
        UsuarioDepositoAutorizado::aplicarFiltroQuery($query);

        if (DepmaeListadoFiltros::tieneCriteriosAplicados($filtros)) {
            DepmaeListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('depmae.nombre');

        return $paginar
            ? $query->paginate(10)->appends(DepmaeListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function listadoParaEmpresa(?int $empresaId): Collection
    {
        $query = $this->model->newQuery()
            ->select('id', 'nombre', 'codigo', 'tipodeposito', 'empresa_id')
            ->orderBy('nombre');

        if ($empresaId > 0) {
            $query->paraEmpresa($empresaId);
        } else {
            $this->aplicarFiltroEmpresas($query);
        }

        return UsuarioDepositoAutorizado::aplicarFiltroQuery($query)->get();
    }

    /**
     * @return Builder<Depmae>
     */
    public function queryFiltrado(): Builder
    {
        $query = $this->model->newQuery();
        $this->aplicarFiltroEmpresas($query);

        return UsuarioDepositoAutorizado::aplicarFiltroQuery($query);
    }

    /**
     * @param  Builder<Depmae>  $query
     */
    private function aplicarFiltroEmpresas(Builder $query, ?int $empresaId = null): void
    {
        if ($empresaId > 0) {
            $query->paraEmpresa($empresaId);

            return;
        }

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);
    }
}
