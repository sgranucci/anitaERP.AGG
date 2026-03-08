<?php

namespace App\Queries\Presupuesto;

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

    public function leePartidagasto($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        // lee usuario para setear filtros
        $usuario_id = Auth::user()->id;
        $empresas = $this->empresaRepository->traeEmpresasAsignadas();

        $select = [ 'partidagasto.id as id',
                    'partidagasto.presupuesto_id as presupuesto_id',
                    'partidagasto.presupuesto_escenario_id as escenario_id',
                    'empresa.nombre as nombreempresa',
                    'presupuesto.nombre as nombrepresupuesto',
                    'presupuesto_escenario.nombre as nombreescenario',
                    'centrocosto.nombre as nombrecentrocosto',
                    'articulo.descripcion as descripcionarticulo',
                    'proveedor.nombre as nombreproveedor',
                    'moneda.abreviatura as abreviaturamoneda',
                    'cuentacontable.nombre as nombrecuentacontable',
                    'partidagasto.detalle as detalle',
                    'partidagasto.estado as estado',
                    'usuario.nombre as nombreusuario'
                    ];

        $partidagastos = $this->partidagastoModel->select($select)
                                ->join('empresa', 'empresa.id', '=', 'partidagasto.empresa_id')
                                ->join('centrocosto', 'centrocosto.id', '=', 'partidagasto.centrocosto_id')
                                ->join('presupuesto', 'presupuesto.id', '=', 'partidagasto.presupuesto_id')
                                ->join('presupuesto_escenario', 'presupuesto_escenario_id', '=', 'partidagasto.presupuesto_escenario_id')
                                ->join('moneda', 'moneda.id', '=', 'partidagasto.moneda_id')
                                ->join('proveedor', 'proveedor.id', '=', 'partidagasto.proveedor_id')
                                ->join('articulo', 'articulo.id', '=', 'partidagasto.articulo_id')
                                ->join('cuentacontable', 'cuentacontable.id', '=', 'partidagasto.cuentacontable_id')
                                ->join('usuario', 'usuario.id', '=', 'partidagasto.creousuario_id')->with('partidagasto_partidas');

        $columns[] = ['columna' => 'partidagasto.id', 
                    'clausula' => 'LIKE'];                                
        $columns[] = ['columna' => 'empresa.nombre', 
                    'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'centrocosto.nombre',
                    'clausula' => 'LIKE']; 
        $columns[] = ['columna' => 'partidagasto.detalle',
                    'clausula' => 'LIKE'];                     
        $columns[] = ['columna' => 'usuario.nombre',
                    'clausula' => 'LIKE'];            
        $columns[] = ['columna' => 'partidagasto.estado',
                    'clausula' => 'LIKE'];                                                            
        $columns[] = ['columna' => 'presupuesto.nombre',
                    'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'presupuesto_escenario.nombre',
                    'clausula' => 'LIKE'];                    
        $columns[] = ['columna' => 'proveedor.nombre',
                    'clausula' => 'LIKE'];   
        $columns[] = ['columna' => 'articulo.descripcion',
                    'clausula' => 'LIKE'];       
        $columns[] = ['columna' => 'moneda.abreviatura',
                    'clausula' => 'LIKE'];                                       
        $count = count($columns);

        $partidagastos->whereIn('empresa_id', $empresas);

        $partidagastos->where(function ($query) use ($count, $busqueda, $columns, $usuario_id) {

                        			for ($i = 0; $i < $count; $i++)
                                    {
                                        if ($columns[$i]['clausula'] == 'LIKE')
                            			    $query->orWhere($columns[$i]['columna'], "LIKE", '%'. $busqueda . '%');
                                        else
                                            $query->orWhere($columns[$i]['columna'], $columns[$i]['clausula'], $busqueda);
                                    }
                            });

        // Ordena desc. por ID
        $partidagastos->orderBy('id', 'desc');

        if (isset($flPaginando))
        {
            if ($flPaginando)
                $partidagastos = $partidagastos->paginate(10);
            else
                $partidagastos = $partidagastos->get();
        }
        else
            $partidagastos = $partidagastos->get();

        return $partidagastos;
    }

}

