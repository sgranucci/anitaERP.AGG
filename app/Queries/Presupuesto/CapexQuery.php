<?php

namespace App\Queries\Presupuesto;

use App\Support\Presupuesto\CapexListadoFiltros;
use App\Models\Presupuesto\Capex;
use App\Models\Presupuesto\Capex_Estado;
use App\Models\Presupuesto\Capex_Partida;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Carbon\Carbon;
use Auth;
use DB;

class CapexQuery implements CapexQueryInterface
{
    protected $capexModel;
    protected $empresaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Capex $capexmodel,
                                EmpresaRepositoryInterface $empresarepository)
    {
        $this->capexModel = $capexmodel;
        $this->empresaRepository = $empresarepository;
    }

    public function first()
    {
        return $this->capexModel->first();
    }

    public function all()
    {
        return $this->capexModel->get();
    }

    public function allQuery(array $campos)
    {
        return $this->capexModel->select($campos)->get();
    }

    public function leeCapex($filtros, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => CapexListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = CapexListadoFiltros::filtrosVacios();
        }

        $empresas = $this->empresaRepository->traeEmpresasAsignadas();

        $select = [ 'capex.id as id',
                    'capex.codigoproyecto as codigoproyecto',
                    'capex.codigo as codigo',
                    'capex.presupuesto_id as presupuesto_id',
                    'presupuesto.nombre as nombrepresupuesto',
                    'empresa.nombre as nombreempresa',
                    'centrocosto.nombre as nombrecentrocosto',
                    'capex.nombre as nombre',
                    'capex.detalle as detalle',
                    'capex.estado as estado',
                    'usuario.nombre as nombreusuario'
                    ];

        $capexs = $this->capexModel->select($select)
                                ->join('empresa', 'empresa.id', '=', 'capex.empresa_id')
                                ->join('centrocosto', 'centrocosto.id', '=', 'capex.centrocosto_id')
                                ->join('presupuesto', 'presupuesto.id', '=', 'capex.presupuesto_id')
                                ->join('usuario', 'usuario.id', '=', 'capex.creousuario_id')
                                ->with('capex_partidas');

        $capexs->whereIn('empresa_id', $empresas);

        if (CapexListadoFiltros::tieneCriteriosAplicados($filtros)) {
            CapexListadoFiltros::aplicar($capexs, $filtros);
        }

        $capexs->orderBy('id', 'desc');

        if (isset($flPaginando)) {
            if ($flPaginando) {
                $capexs = $capexs->paginate(10);
            } else {
                $capexs = $capexs->get();
            }
        } else {
            $capexs = $capexs->get();
        }

        return $capexs;
    }

}

