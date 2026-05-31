<?php

namespace App\Queries\Presupuesto;

use App\Support\Presupuesto\PartidagastoListadoFiltros;
use App\Models\Presupuesto\Partidagasto;
use App\Models\Presupuesto\Partidagasto_Estado;
use App\Models\Presupuesto\Partidagasto_Partida;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Carbon\Carbon;
use Auth;
use DB;

class PartidagastoQuery implements PartidagastoQueryInterface
{
    protected $partidagastoModel;
    protected $empresaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Partidagasto $partidagastomodel,
                                EmpresaRepositoryInterface $empresarepository)
    {
        $this->partidagastoModel = $partidagastomodel;
        $this->empresaRepository = $empresarepository;
    }

    public function first()
    {
        return $this->partidagastoModel->first();
    }

    public function all()
    {
        return $this->partidagastoModel->get();
    }

    public function allQuery(array $campos)
    {
        return $this->partidagastoModel->select($campos)->get();
    }

    public function leePartidagasto($filtros, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => PartidagastoListadoFiltros::MODO_TODOS,
                'campo' => 'detalle',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = PartidagastoListadoFiltros::filtrosVacios();
        }

        $empresas = $this->empresaRepository->traeEmpresasAsignadas();

        $select = [ 'partidagasto.id as id',
                    'partidagasto.presupuesto_id as presupuesto_id',
                    'partidagasto.presupuesto_escenario_id as escenario_id',
                    'partidagasto.codigo as codigopartida',
                    'empresa.nombre as nombreempresa',
                    'presupuesto.nombre as nombrepresupuesto',
                    'presupuesto_escenario.nombre as nombreescenario',
                    'centrocosto.nombre as nombrecentrocosto',
                    'articulo.descripcion as descripcionarticulo',
                    'proveedor.nombre as nombreproveedor',
                    'moneda.abreviatura as abreviaturamoneda',
                    'cuentacontable.codigo as codigocuentacontable',
                    'cuentacontable.nombre as nombrecuentacontable',
                    'partidagasto.detalle as detalle',
                    'partidagasto.estado as estado',
                    'usuario.nombre as nombreusuario'
                    ];

        $partidagastos = $this->partidagastoModel->select($select)
                                ->join('empresa', 'empresa.id', '=', 'partidagasto.empresa_id')
                                ->join('centrocosto', 'centrocosto.id', '=', 'partidagasto.centrocosto_id')
                                ->join('presupuesto', 'presupuesto.id', '=', 'partidagasto.presupuesto_id')
                                ->join('presupuesto_escenario', 'presupuesto_escenario.id', '=', 'partidagasto.presupuesto_escenario_id')
                                ->join('moneda', 'moneda.id', '=', 'partidagasto.moneda_id')
                                ->leftjoin('proveedor', 'proveedor.id', '=', 'partidagasto.proveedor_id')
                                ->leftjoin('articulo', 'articulo.id', '=', 'partidagasto.articulo_id')
                                ->leftjoin('cuentacontable', 'cuentacontable.id', '=', 'partidagasto.cuentacontable_id')
                                ->join('usuario', 'usuario.id', '=', 'partidagasto.creousuario_id')
                                ->with('partidagasto_montos');

        $partidagastos->whereIn('partidagasto.empresa_id', $empresas);

        if (PartidagastoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            PartidagastoListadoFiltros::aplicar($partidagastos, $filtros);
        }

        $partidagastos->orderBy('partidagasto.id', 'desc');

        if (isset($flPaginando)) {
            if ($flPaginando) {
                $partidagastos = $partidagastos->paginate(10);
            } else {
                $partidagastos = $partidagastos->get();
            }
        } else {
            $partidagastos = $partidagastos->get();
        }

        return $partidagastos;
    }

}

