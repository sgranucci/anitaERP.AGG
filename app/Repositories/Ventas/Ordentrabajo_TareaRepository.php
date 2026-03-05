<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Ordentrabajo_Tarea;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;
use DB;

class Ordentrabajo_TareaRepository implements Ordentrabajo_TareaRepositoryInterface
{
    protected $model;
    protected $keyField = 'codigo';
    protected $tableAnita = 'ordttar';
    protected $keyFieldAnita = 'ordtt_nro_orden';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ordentrabajo_Tarea $ordentrabajo_tarea)
    {
        $this->model = $ordentrabajo_tarea;
    }

    public function all()
    {
        return $this->model->get();
    }

	public function create($data)
	{
        $ordentrabajo_tarea = $this->model->create($data);

		return $ordentrabajo_tarea;
    }

    public function update(array $data, $id)
    {
        $ordentrabajo_tarea = $this->model->findOrFail($id)
            ->update($data);

		return $ordentrabajo_tarea;
    }

    public function delete($id, $nro_orden = null)
    {
    	$ordentrabajo_tarea = $this->model->destroy($id);

		return $ordentrabajo_tarea;
    }

    public function deleteporordentrabajo($ordentrabajo_id, $nro_orden)
    {
    	$ordentrabajo_tarea = $this->model->where('ordentrabajo_id', $ordentrabajo_id)->delete();

		return $ordentrabajo_tarea;
    }

    public function find($id)
    {
        if (null == $ordentrabajo_tarea = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ordentrabajo_tarea;
    }

    public function findOrFail($id)
    {
        if (null == $ordentrabajo_tarea = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ordentrabajo_tarea;
    }

    public function findPorOrdentrabajoId($id, $tarea_id = null)
    {
    	$ordentrabajo_tarea = $this->model->where('ordentrabajo_id', $id)->with("tareas");

		if ($tarea_id)
			$ordentrabajo_tarea = $ordentrabajo_tarea->where('tarea_id', $tarea_id)->orderBy('desdefecha')->get();
		else
			$ordentrabajo_tarea = $ordentrabajo_tarea->orderBy('desdefecha')->get();

		return $ordentrabajo_tarea;
    }

	// Trae datos por rango de fechas y filtra por ordenes de trabajo opcional

	public function findPorRangoFecha($desdefecha, $hastafecha, $ordenestrabajo = null)
    {
		$ordentrabajo_tarea = $this->model->whereBetween('desdefecha', [$desdefecha, $hastafecha])
								->leftJoin('ordentrabajo', 'ordentrabajo.id', 'ordentrabajo_tarea.ordentrabajo_id')
								->with("tareas")
								->with("empleados")
								->with("usuarios")
								->with("ordenestrabajo")
								->with("pedidos_combinacion");

		$arrayOrdenesTrabajo = explode(',', $ordenestrabajo);

		if ($ordenestrabajo != null)
			$ordentrabajo_tarea = $ordentrabajo_tarea
										->whereIn('ordentrabajo.codigo', $arrayOrdenesTrabajo)
										->orderBy('ordentrabajo.id')->get();
		else
			$ordentrabajo_tarea = $ordentrabajo_tarea->orderBy('ordentrabajo_id')->get();
		return $ordentrabajo_tarea;
    }

	public function findReporteEstadoOt($desdefecha, $hastafecha, $ordenestrabajo = null)
    {
		$ordentrabajo_tarea = $this->model->select('ordentrabajo_tarea.id as id',
													'ordentrabajo_tarea.ordentrabajo_id as ordentrabajo_id',
													'ordentrabajo_tarea.tarea_id as tarea_id',
													'ordentrabajo_tarea.empleado_id as empleado_id',
													'ordentrabajo_tarea.pedido_combinacion_id as pedido_combinacion_id',
													'ordentrabajo_tarea.desdefecha as desdefecha',
													'ordentrabajo_tarea.hastafecha as hastafecha',
													'ordentrabajo_tarea.estado as estado',
													'ordentrabajo_tarea.costo as costo',
													'ordentrabajo.codigo as codigo',
													DB::raw('sum(pedido_combinacion_talle.cantidad) as pares'))
								->whereBetween('desdefecha', [$desdefecha, $hastafecha])
								->leftJoin('ordentrabajo', 'ordentrabajo.id', 'ordentrabajo_tarea.ordentrabajo_id')
								->leftJoin('ordentrabajo_combinacion_talle', 'ordentrabajo_combinacion_talle.ordentrabajo_id', 'ordentrabajo_tarea.ordentrabajo_id')
								->leftJoin('pedido_combinacion_talle', 'pedido_combinacion_talle.id', 'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id')
								->with("tareas")
								->with("empleados")
								->with("usuarios")
								->with("ordenestrabajo")
								->groupBy('ordentrabajo_tarea.id');

		$arrayOrdenesTrabajo = explode(',', $ordenestrabajo);

		if ($ordenestrabajo != null)
			$ordentrabajo_tarea = $ordentrabajo_tarea
										->whereIn('ordentrabajo.codigo', $arrayOrdenesTrabajo)
										->orderBy('ordentrabajo.id')->get();
		else
			$ordentrabajo_tarea = $ordentrabajo_tarea->orderBy('ordentrabajo_id')->get();
		return $ordentrabajo_tarea;
    }


	public function agrupaPorFechaTarea($desdefecha, $hastafecha, $apertura, $ordenestrabajo = null)
    {
		switch($apertura)
		{
			case "DIARIA":
				$ordentrabajo_tarea = $this->model->select(
					'ordentrabajo_tarea.tarea_id as tarea_id',
					'tarea.nombre as nombre',
					'ordentrabajo_tarea.hastafecha as periodo',
					'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id as pedido_combinacion_talle_id',
					DB::raw('sum(pedido_combinacion_talle.cantidad) as pares'))
					->leftJoin('tarea', 'tarea.id', 'ordentrabajo_tarea.tarea_id')
					->leftJoin('ordentrabajo_combinacion_talle', 'ordentrabajo_combinacion_talle.ordentrabajo_id', 'ordentrabajo_tarea.ordentrabajo_id')
					->leftJoin('pedido_combinacion_talle', 'pedido_combinacion_talle.id', 'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id')
					->whereBetween('ordentrabajo_tarea.hastafecha', [$desdefecha, $hastafecha])
					->groupBy('ordentrabajo_tarea.hastafecha', 'ordentrabajo_tarea.tarea_id')
					->orderBy('periodo');
				break;
			case 'SEMANAL':
				$ordentrabajo_tarea = $this->model->select(
					'ordentrabajo_tarea.tarea_id as tarea_id',
					'tarea.nombre as nombre',
					DB::raw("DATE_FORMAT(ordentrabajo_tarea.hastafecha,'%Y') as year"),
					DB::raw("Week(ordentrabajo_tarea.hastafecha,'%M %Y') as periodo"),
					DB::raw('sum(pedido_combinacion_talle.cantidad) as pares'))
					->leftJoin('tarea', 'tarea.id', 'ordentrabajo_tarea.tarea_id')
					->leftJoin('ordentrabajo_combinacion_talle', 'ordentrabajo_combinacion_talle.ordentrabajo_id', 'ordentrabajo_tarea.ordentrabajo_id')
					->leftJoin('pedido_combinacion_talle', 'pedido_combinacion_talle.id', 'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id')
					->whereBetween('ordentrabajo_tarea.hastafecha', [$desdefecha, $hastafecha])
					->groupBy('periodo', 'ordentrabajo_tarea.tarea_id')
					->orderBy('periodo');
				break;
			case "MENSUAL":
				$ordentrabajo_tarea = $this->model->select(
					'ordentrabajo_tarea.tarea_id as tarea_id',
					'tarea.nombre as nombre',
					DB::raw("DATE_FORMAT(ordentrabajo_tarea.hastafecha,'%M %Y') as periodo"),
					DB::raw('sum(pedido_combinacion_talle.cantidad) as pares'))
					->leftJoin('tarea', 'tarea.id', 'ordentrabajo_tarea.tarea_id')
					->leftJoin('ordentrabajo_combinacion_talle', 'ordentrabajo_combinacion_talle.ordentrabajo_id', 'ordentrabajo_tarea.ordentrabajo_id')
					->leftJoin('pedido_combinacion_talle', 'pedido_combinacion_talle.id', 'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id')
					->whereBetween('ordentrabajo_tarea.hastafecha', [$desdefecha, $hastafecha])
					->groupBy('periodo', 'ordentrabajo_tarea.tarea_id')
					->orderBy('periodo');
				break;
		}
		$arrayOrdenesTrabajo = explode(',', $ordenestrabajo);								
		if ($ordenestrabajo)
			$ordentrabajo_tarea = $ordentrabajo_tarea->whereIn('ordentrabajo_id', function($query) use($arrayOrdenesTrabajo)
			{
				$query->select('id')
						->from('ordentrabajo')
						->whereIn('ordentrabajo.codigo', $arrayOrdenesTrabajo);
			})->orderby('ordentrabajo_id')->get();
		else
			$ordentrabajo_tarea = $ordentrabajo_tarea->get();

		return $ordentrabajo_tarea;
	}
    
	// Lee tareas por rangos
    public function findTareaPorRangos($estadoot, $desdefecha, $hastafecha,
                                    $desdetarea_id, $hastatarea_id,
                                    $desdecliente_id, $hastacliente_id,
                                    $desdearticulo, $hastaarticulo,
                                    $desdeempleado_id, $hastaempleado_id)
    {
        $data = $this->model
					->select(
						'ordentrabajo_tarea.empleado_id as numerolegajo',
						'empleado.nombre as nombreempleado',
						'ordentrabajo.codigo as numeroot',
						'ordentrabajo_tarea.tarea_id as tarea_id',
						'tarea.nombre as nombretarea',
						'articulo.sku as sku',
						'articulo.descripcion as nombrearticulo',
						'combinacion.nombre as nombrecombinacion',
						'pedido.codigo as numeropedido',
						'ordentrabajo_tarea.desdefecha as desdefecha',
						'ordentrabajo_tarea.hastafecha as hastafecha',
						'articulo_costo.costo as costoporpar',
						'pedido_combinacion_talle.pedido_combinacion_id',
						'pedido_combinacion.cantidad as cantidad')
						->join('tarea', 'tarea.id', 'ordentrabajo_tarea.tarea_id')
						->leftjoin('empleado', 'empleado.id', 'ordentrabajo_tarea.empleado_id')
						->join('ordentrabajo', 'ordentrabajo.id', 'ordentrabajo_tarea.ordentrabajo_id')
						->join('ordentrabajo_combinacion_talle', 'ordentrabajo_combinacion_talle.ordentrabajo_id', 'ordentrabajo_tarea.ordentrabajo_id')
						->join('pedido_combinacion_talle', 'pedido_combinacion_talle.id', 'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id')
						->join('pedido_combinacion', 'pedido_combinacion.id', 'pedido_combinacion_talle.pedido_combinacion_id')
						->join('pedido', 'pedido.id', 'pedido_combinacion.pedido_id')
						->join('articulo', 'articulo.id', 'pedido_combinacion.articulo_id')
						->leftJoin('articulo_costo', function($join)
						{
							$join->on('articulo_costo.articulo_id', 'pedido_combinacion.articulo_id');
							$join->on('articulo_costo.tarea_id', 'ordentrabajo_tarea.tarea_id');
						})
						->join('combinacion', 'combinacion.id', 'pedido_combinacion.combinacion_id')
						->whereBetween('pedido.cliente_id', [$desdecliente_id, $hastacliente_id])
                        ->whereBetween('ordentrabajo_tarea.tarea_id', [$desdetarea_id, $hastatarea_id])
						->whereBetween('ordentrabajo_tarea.empleado_id', [$desdeempleado_id, $hastaempleado_id])
						->groupBy('pedido_combinacion_talle.pedido_combinacion_id')
						->orderBy('numerolegajo')
						->orderBy('tarea_id')
						->orderBy('numeroot');
						
		switch($estadoot)
		{
		case 'CUMPLIDA':
			$data = $data->whereBetween('ordentrabajo_tarea.hastafecha', [$desdefecha, $hastafecha]);
			break;
		case 'PENDIENTE':
			$data = $data->whereBetween('ordentrabajo_tarea.desdefecha', [$desdefecha, $hastafecha])
					->where('ordentrabajo_tarea.hastafecha', '=', null);
			break;
		default:
			$data = $data->whereBetween('ordentrabajo_tarea.hastafecha', [$desdefecha, $hastafecha]);
		}

		if ($desdearticulo != '' && $hastaarticulo != '')
			$data = $data->whereBetween('articulo.descripcion', [$desdearticulo, $hastaarticulo]);

		if ($desdeempleado_id != '' && $hastaempleado_id != '')
			$data = $data->whereBetween('ordentrabajo_tarea.empleado_id', [$desdeempleado_id, $hastaempleado_id]);
		return $data->get();
    }

    public function sincronizarConAnita()
	{
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "ordtt_nro_orden", 
            			'whereArmado' => " WHERE ordtt_fecha>20211000 ",
						'tabla' => "ordttar" );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        foreach ($dataAnita as $value) {
        	$this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
        }
    }

    private function traerRegistroDeAnita($nro){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
					ordtt_nro_orden,
					ordtt_tarea,
					ordtt_fecha_ini,
					ordtt_fecha_fin,
					ordtt_empresa,
					ordtt_legajo,
					ordtt_articulo,
					ordtt_cantidad,
					ordtt_costo
			',
            'whereArmado' => " WHERE ordtt_nro_orden = '".$nro."' "
        );
        $data = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($data) > 0) 
		{
			$i = 0;
        	while ($i < count($data))
			{
        		$ordentrabajo = $this->ordentrabajoRepository->leeOrdentrabajoporCodigo($data[$i]->ordtt_nro_orden);
				if ($ordentrabajo)
					$ordentrabajo_id = $ordentrabajo->id;
				else
					return;

				$arr_campos = [
					"ordentrabajo_id" => $ordentrabajo_id,
					"tarea_id" => $data[$i]->ordtt_tarea,
					"empleado_id" => $talle_id,
					"desdefecha" => $data[$i]->ordtt_fecha_ini,
					"hastafecha" => $data[$i]->ordtt_fecha_fin,
					"estado" => ' ',
					"usuario_id" => $usuario_id,
            		];
		
            	$this->model->create($arr_campos);
				$i++;
        	}
		}
    }

	private function guardarAnita($request) {
		return 0;
        $apiAnita = new ApiAnita();

		$desdefecha = date('Ymd', strtotime($request['desdefecha']));
		$hastafecha = date('Ymd', strtotime($request['hastafecha']));

		//if ($request['hastafecha'] != 0)
		//else
			//$hastafecha = 0;

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'campos' => ' 
					ordtt_nro_orden,
					ordtt_tarea,
					ordtt_fecha_ini,
					ordtt_fecha_fin,
					ordtt_empresa,
					ordtt_legajo,
					ordtt_articulo,
					ordtt_cantidad,
					ordtt_costo
				',
            'valores' => " 
				'".$request['nro_orden']."', 
				'".$request['tarea_id']."',
				'".$desdefecha."',
				'".$hastafecha."',
				'".'1'."',
				'".$request['empleado_id']."',
				'".$request['articulo']."',
				'".$request['cantidad']."',
				'".$request['costo']."' "
        );
        return $apiAnita->apiCall($data);
	}

	private function actualizarAnita($request, $id) {
		return 0;
        $apiAnita = new ApiAnita();

		$desdefecha = date('Ymd', strtotime($request['desdefecha']));
		$hastafecha = date('Ymd', strtotime($request['hastafecha']));

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'valores' => " 
					ordtt_nro_orden 	= '".$id."',
					ordtt_tarea    		= '".$request['tarea_id']."',
					ordtt_fecha_ini 	= '".$desdefecha."',
					ordtt_fecha_fin 	= '".$hastafecha."',
					ordtt_empresa 		= '".'1'."',
					ordtt_legajo 		= '".$request['empleado_id']."',
					ordtt_articulo 		= '".$request['articulo']."',
					ordtt_cantidad 		= '".$request['cantidad']."',
					ordtt_costo   		= '".$request['costo']."' "
					,
				'whereArmado' => " WHERE ordtt_nro_orden = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	private function eliminarAnita($id) {
		return 0;
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE ordtt_nro_orden = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}

