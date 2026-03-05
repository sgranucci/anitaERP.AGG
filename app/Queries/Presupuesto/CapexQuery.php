<?php

namespace App\Queries\Presupuesto;

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

    public function leeCapex($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        // lee usuario para setear filtros
        $usuario_id = Auth::user()->id;
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
                                ->join('usuario', 'usuario.id', '=', 'capex.creousuario_id')->with('capex_partidas');

        $columns[] = ['columna' => 'capex.id', 
                    'clausula' => 'LIKE'];                                
        $columns[] = ['columna' => 'empresa.nombre', 
                    'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'capex.codigoproyecto',
                    'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'centrocosto.nombre',
                    'clausula' => 'LIKE']; 
        $columns[] = ['columna' => 'capex.nombre',
                    'clausula' => 'LIKE']; 
        $columns[] = ['columna' => 'capex.detalle',
                    'clausula' => 'LIKE'];                     
        $columns[] = ['columna' => 'usuario.nombre',
                    'clausula' => 'LIKE'];            
        $columns[] = ['columna' => 'capex.estado',
                    'clausula' => 'LIKE'];                                                            
        $columns[] = ['columna' => 'presupuesto.nombre',
                    'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'capex.codigo',
                    'clausula' => 'LIKE'];                    

        $count = count($columns);

        $capexs->whereIn('empresa_id', $empresas);

        $capexs->where(function ($query) use ($count, $busqueda, $columns, $usuario_id) {

                        			for ($i = 0; $i < $count; $i++)
                                    {
                                        if ($columns[$i]['clausula'] == 'LIKE')
                            			    $query->orWhere($columns[$i]['columna'], "LIKE", '%'. $busqueda . '%');
                                        else
                                            $query->orWhere($columns[$i]['columna'], $columns[$i]['clausula'], $busqueda);
                                    }
                            });

        // Ordena desc. por ID
        $capexs->orderBy('id', 'desc');

        if (isset($flPaginando))
        {
            if ($flPaginando)
                $capexs = $capexs->paginate(10);
            else
                $capexs = $capexs->get();
        }
        else
            $capexs = $capexs->get();

        return $capexs;
    }

}

