<?php

namespace App\Queries\Stock;

use App\Models\Stock\Formula_Articulo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoCortesSupport;
use App\Support\Stock\FormulaArticuloListadoColumnas;
use App\Support\Stock\FormulaArticuloListadoFiltros;
use Illuminate\Database\Eloquent\Builder;

class FormulaArticuloQuery implements FormulaArticuloQueryInterface
{
    public function __construct(
        protected Formula_Articulo $model,
        protected EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function first()
    {
        return $this->model->query()->first();
    }

    public function leeFormulaArticulo($busqueda, $flPaginando = null, $withHijos = false, ?string $conOpcionales = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = FormulaArticuloListadoFiltros::filtrosVacios();
        $filtros['con_opcionales'] = FormulaArticuloListadoFiltros::normalizarOpcionales($conOpcionales);
        $texto = trim((string) ($busqueda ?? ''));
        if ($texto !== '') {
            $filtros['modo'] = FormulaArticuloListadoFiltros::MODO_TODOS;
            $filtros['valor'] = $texto;
            $filtros['busqueda'] = $texto;
        }

        return $this->leeListado($filtros, (bool) $flPaginando, (bool) $withHijos);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function leeListado(array $filtros, bool $paginar, bool $withHijos = true)
    {
        $q = $this->queryBase();
        FormulaArticuloListadoFiltros::aplicar($q, $filtros);
        FormulaArticuloListadoFiltros::aplicarOrden($q, $filtros);
        if ($withHijos) {
            $this->cargarHijos($q);
        }

        if (! $paginar) {
            return $q->get();
        }

        $perPage = (int) ($filtros['_per_page'] ?? 10);
        if ($perPage < 1) {
            $perPage = 10;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        return $q->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function cortesListado(array $filtros): array
    {
        $campos = FormulaArticuloListadoFiltros::camposOrdenables();
        $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);
        $q = $this->queryBase();
        FormulaArticuloListadoFiltros::aplicar($q, $filtros);

        return ListadoCortesSupport::calcular(
            $q,
            $agrupar,
            $campos,
            'formula_articulo.id',
            [FormulaArticuloListadoColumnas::class, 'valorCelda'],
            null,
            [],
            FormulaArticuloListadoColumnas::MEDIDAS
        );
    }

    /**
     * @return Builder<Formula_Articulo>
     */
    private function queryBase(): Builder
    {
        $empresas = $this->empresaRepository->traeEmpresasAsignadas();

        return $this->model->query()
            ->select([
                'formula_articulo.id',
                'formula_articulo.articulo_id',
                'formula_articulo.codigo',
                'formula_articulo.detalle',
                'formula_articulo.cantidadunidad',
                'formula_articulo.estado',
                'formula_articulo.creousuario_id',
                'formula_articulo.created_at',
                'formula_articulo.updated_at',
                'art_padre.sku as articulo_sku',
                'art_padre.descripcion as articulo_descripcion',
                'empresa.nombre as nombreempresa',
                'usuario.nombre as nombreusuario',
            ])
            ->leftJoin('articulo as art_padre', 'art_padre.id', '=', 'formula_articulo.articulo_id')
            ->leftJoin('empresa', 'empresa.id', '=', 'art_padre.empresa_id')
            ->join('usuario', 'usuario.id', '=', 'formula_articulo.creousuario_id')
            ->where(function ($w) use ($empresas) {
                $w->whereNull('formula_articulo.articulo_id')
                    ->orWhereNull('art_padre.empresa_id')
                    ->orWhereIn('art_padre.empresa_id', $empresas);
            });
    }

    /**
     * @param  Builder<Formula_Articulo>  $q
     */
    private function cargarHijos(Builder $q): void
    {
        $q->with([
            'articulos',
            'formula_articulo_hijos.articulos',
            'formula_articulo_hijos.formula_hija.articulos',
            'formula_articulo_hijos.depositos',
        ]);
    }
}
