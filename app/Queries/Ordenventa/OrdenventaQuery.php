<?php

namespace App\Queries\Ordenventa;

use App\Models\Ordenventa\Ordenventa;
use App\Models\Ordenventa\Ordenventa_Estado;
use App\Models\Ordenventa\Ordenventa_Cuota;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Carbon\Carbon;
use Auth;
use DB;

class OrdenventaQuery implements OrdenventaQueryInterface
{
    protected $ordenventaModel;
    protected $empresaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ordenventa $ordenventamodel,
                                EmpresaRepositoryInterface $empresarepository)
    {
        $this->ordenventaModel = $ordenventamodel;
        $this->empresaRepository = $empresarepository;
    }

    public function first()
    {
        return $this->ordenventaModel->first();
    }

    public function all()
    {
        return $this->ordenventaModel->get();
    }

    public function allQuery(array $campos)
    {
        return $this->ordenventaModel->select($campos)->get();
    }

    public function leeOrdenventa($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        // lee usuario para setear filtros
        $usuario_id = Auth::user()->id;
        $empresas = $this->empresaRepository->traeEmpresasAsignadas();

        $select = [ 'ordenventa.id as id',
                    'ordenventa.fecha as fecha',
                    'ordenventa.numeroordenventa as numeroordenventa',
                    'empresa.nombre as nombreempresa',
                    'ordenventa.tratamiento as tratamiento',
                    'centrocosto.nombre as nombrecentrocosto',
                    'ordenventa.comentario as comentario',
                    'ordenventa.nombrecliente as nombrecliente',
                    'ordenventa.monto as monto',
                    'moneda.abreviatura as abreviaturamoneda',
                    'ordenventa.estado as estado',
                    'usuario.nombre as nombreusuario',
                    'ordenventa.detalle as detalle'
                    ];

        $ordenventas = $this->ordenventaModel->select($select)
                                ->join('empresa', 'empresa.id', '=', 'ordenventa.empresa_id')
                                ->join('centrocosto', 'centrocosto.id', '=', 'ordenventa.centrocosto_id')
                                ->join('moneda', 'moneda.id', '=', 'ordenventa.moneda_id')
                                ->join('usuario', 'usuario.id', '=', 'ordenventa.creousuario_id');

        $columns[] = ['columna' => 'empresa.nombre', 
                    'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'ordenventa.tratamiento',
                    'clausula' => 'LIKE'];
        $columns[] = ['columna' => 'centrocosto.nombre',
                    'clausula' => 'LIKE']; 
        $columns[] = ['columna' => 'ordenventa.comentario',
                    'clausula' => 'LIKE']; 
        $columns[] = ['columna' => 'ordenventa.detalle',
                    'clausula' => 'LIKE'];                     
        $columns[] = ['columna' => 'ordenventa.nombrecliente',
                    'clausula' => 'LIKE'];    
        $columns[] = ['columna' => 'centrocosto.nombre',
                    'clausula' => 'LIKE'];                           
        $columns[] = ['columna' => 'usuario.nombre',
                    'clausula' => 'LIKE'];            
        $columns[] = ['columna' => 'ordenventa.estado',
                    'clausula' => 'LIKE'];                                                            
        $columns[] = ['columna' => 'moneda.abreviatura',
                    'clausula' => 'LIKE'];

        $primerChar = substr($busqueda,0,1);
        if (!ctype_alpha($primerChar))
        {
            $columns[] = ['columna' => 'ordenventa.fecha',
                    'clausula' => '='];
        }

        $columns[] = ['columna' => 'ordenventa.numeroordenventa',
                    'clausula' => '='];                    
        $count = count($columns);

        $ordenventas->where('deleted_at', null);
        $ordenventas->whereIn('empresa_id', $empresas);

        $ordenventas->where(function ($query) use ($count, $busqueda, $columns, $usuario_id) {

                        			for ($i = 0; $i < $count; $i++)
                                    {
                                        if ($columns[$i]['clausula'] == 'LIKE')
                            			    $query->orWhere($columns[$i]['columna'], "LIKE", '%'. $busqueda . '%');
                                        else
                                            $query->orWhere($columns[$i]['columna'], $columns[$i]['clausula'], $busqueda);
                                    }
                            });

        // Ordena desc. por ID
        $ordenventas->orderBy('id', 'desc');

        if (isset($flPaginando))
        {
            if ($flPaginando)
                $ordenventas = $ordenventas->paginate(10);
            else
                $ordenventas = $ordenventas->get();
        }
        else
            $ordenventas = $ordenventas->get();

        return $ordenventas;
    }

}

