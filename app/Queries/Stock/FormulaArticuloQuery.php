<?php

namespace App\Queries\Stock;

use App\Models\Stock\Formula_Articulo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;

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

        $empresas = $this->empresaRepository->traeEmpresasAsignadas();
        $conOpcionales = in_array($conOpcionales, ['si', 'no'], true) ? $conOpcionales : null;

        $q = $this->model->query()
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

        $columns = [
            ['columna' => 'formula_articulo.id', 'clausula' => 'LIKE'],
            ['columna' => 'formula_articulo.codigo', 'clausula' => 'LIKE'],
            ['columna' => 'art_padre.sku', 'clausula' => 'LIKE'],
            ['columna' => 'art_padre.descripcion', 'clausula' => 'LIKE'],
            ['columna' => 'formula_articulo.detalle', 'clausula' => 'LIKE'],
            ['columna' => 'formula_articulo.estado', 'clausula' => 'LIKE'],
            ['columna' => 'empresa.nombre', 'clausula' => 'LIKE'],
            ['columna' => 'usuario.usuario', 'clausula' => 'LIKE'],
        ];

        if ($busqueda) {
            $busqueda = trim((string) $busqueda);
            $q->where(function ($query) use ($busqueda, $columns) {
                foreach ($columns as $col) {
                    $query->orWhere($col['columna'], 'LIKE', '%'.$busqueda.'%');
                }
            });
        }

        if ($conOpcionales === 'si') {
            $q->whereExists(function ($sub) {
                $sub->select(\DB::raw(1))
                    ->from('formula_articulo_hijo')
                    ->whereColumn('formula_articulo_hijo.formula_articulo_id', 'formula_articulo.id')
                    ->where('formula_articulo_hijo.esopcional', 1);
            });
        } elseif ($conOpcionales === 'no') {
            $q->whereNotExists(function ($sub) {
                $sub->select(\DB::raw(1))
                    ->from('formula_articulo_hijo')
                    ->whereColumn('formula_articulo_hijo.formula_articulo_id', 'formula_articulo.id')
                    ->where('formula_articulo_hijo.esopcional', 1);
            });
        }

        $q->orderBy('formula_articulo.id', 'desc');

        if ($withHijos) {
            $q->with([
                'articulos',
                'formula_articulo_hijos.articulos',
                'formula_articulo_hijos.formula_hija.articulos',
                'formula_articulo_hijos.depositos',
            ]);
        }

        if ($flPaginando) {
            return $q->paginate(10);
        }

        return $q->get();
    }
}
