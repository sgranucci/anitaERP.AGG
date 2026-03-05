<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Ordentrabajo_Combinacion_Talle;
use App\Queries\Ventas\OrdentrabajoQueryInterface;
use App\Queries\Ventas\PedidoQueryInterface;
use App\Queries\Ventas\Pedido_CombinacionQueryInterface;
use App\Queries\Ventas\ClienteQueryInterface;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Repositories\Ventas\OrdentrabajoRepositoryInterface;
use App\Repositories\Ventas\Pedido_Combinacion_TalleRepositoryInterface;
use App\Models\Stock\Talle;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;
use DB;

class Ordentrabajo_Combinacion_TalleRepository implements Ordentrabajo_Combinacion_TalleRepositoryInterface
{
    protected $model;
    protected $ordentrabajoRepository;
    protected $ordentrabajoQuery;
    protected $articuloQuery;
	protected $pedidoQuery;
	protected $clienteQuery;
	protected $pedido_combinacionQuery;
	protected $pedido_combinacion_talleRepository;
    protected $keyField = 'codigo';
    protected $tableAnita = 'ordtmov';
    protected $keyFieldAnita = 'ordtv_nro_orden';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ordentrabajo_Combinacion_Talle $ordentrabajo_combinacion_talle,
    							OrdentrabajoRepositoryInterface $ordentrabajorepository,
								ClienteQueryInterface $clientequery,
								PedidoQueryInterface $pedidoquery,
								ArticuloQueryInterface $articuloquery,
								OrdentrabajoQueryInterface $ordentrabajoquery,
								Pedido_CombinacionQueryInterface $pedidocombinacionquery,
								Pedido_Combinacion_TalleRepositoryInterface $pedidocombinaciontallerepository
								)
    {
        $this->model = $ordentrabajo_combinacion_talle;
		$this->clienteQuery = $clientequery;
		$this->pedidoQuery = $pedidoquery;
		$this->articuloQuery = $articuloquery;
		$this->ordentrabajoQuery = $ordentrabajoquery;
		$this->pedido_combinacionQuery = $pedidocombinacionquery;
		$this->pedido_combinacion_talleRepository = $pedidocombinaciontallerepository;
		$this->ordentrabajoRepository = $ordentrabajorepository;
    }

    public function all()
    {
        return $this->model->get();
    }

	public function create($data)
	{
		$dataErp = array(
						'ordentrabajo_id' => $data['ordentrabajo_id'],
						'pedido_combinacion_talle_id' => $data['pedido_combinacion_talle_id'],
            			'cliente_id' => $data['cliente_id'],
            			'estado' => $data['estado'],
						'ordentrabajo_stock_id' => $data['ordentrabajo_stock_id'],
						'usuario_id' => $data['usuario_id']
						);
        $ordentrabajo_combinacion_talle = $this->model->create($dataErp);

		return $ordentrabajo_combinacion_talle;
    }

	
    public function update(array $data, $id)
    {
        $ordentrabajo_combinacion_talle = $this->model->findOrFail($id)
            ->update($data);

		return $ordentrabajo_combinacion_talle;
    }

    public function delete($id, $nro_orden)
    {
        $ordentrabajo_combinacion_talle = $this->model->destroy($id);

		return $ordentrabajo_combinacion_talle;
    }

    public function deleteporordentrabajo($ordentrabajo_id)
    {
    	$ordentrabajo_combinacion_talle = $this->model->where('ordentrabajo_id', $ordentrabajo_id)->delete();

        $ordentrabajo = $this->ordentrabajoQuery->leeOrdenTrabajo($ordentrabajo_id);

		return $ordentrabajo_combinacion_talle;
    }

    public function find($id)
    {
        if (null == $ordentrabajo_combinacion_talle = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ordentrabajo_combinacion_talle;
    }

    public function findOrFail($id)
    {
        if (null == $ordentrabajo_combinacion_talle = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ordentrabajo_combinacion_talle;
    }

    public function findPorOrdenTrabajoId($ordentrabajo_id)
    {
		$ordentrabajo_combinacion_talle = $this->model->with('pedido_combinacion_talles')->
												where('ordentrabajo_id',$ordentrabajo_id)->get();

		return $ordentrabajo_combinacion_talle;
    }

	public function findPorPedidoCombinacionTalleId($pedido_combinacion_talle_id)
    {
		$ordentrabajo_combinacion_talle = $this->model->with('pedido_combinacion_talles')->where('pedido_combinacion_talle_id',$pedido_combinacion_talle_id)->get();

		return $ordentrabajo_combinacion_talle;
    }

	public function findPorOrdentrabajoStockId($ordentrabajo_stock_id)
    {
		$ordentrabajo_combinacion_talle = $this->model->with('pedido_combinacion_talles')
											->where('ordentrabajo_stock_id',$ordentrabajo_stock_id)->get();

		return $ordentrabajo_combinacion_talle;
    }

	public function sincronizarConAnita()
	{
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "ordtv_nro_orden", 
            			'whereArmado' => " WHERE ordtv_fecha>20211000 ",
						'tabla' => "ordtmov" );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        foreach ($dataAnita as $value) {
        	$this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
        }
    }

    private function traerRegistroDeAnita($nro){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => "ordtmov,ordtmae",
            'campos' => '
					ordtv_nro_orden,
					ordtv_articulo,
					ordtv_linea,
					ordtv_color,
					ordtv_medida,
					ordtv_cantidad,
					ordtv_forro,
					ordtv_cantentr,
					ordtv_cliente,
					ordtv_fecha,
					ordtv_agrupacion,
					ordtv_cantfact,
					ordtv_aplique,
					ordtm_tipo,
					ordtm_letra,
					ordtm_sucursal,
					ordtm_nro,
					ordtm_nro_renglon,
					ordtm_estado
			',
            'whereArmado' => " WHERE ordtv_nro_orden = '".$nro."' AND ordtv_nro_orden=ordtm_nro_orden "
        );
        $data = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($data) > 0) 
		{
			$i = 0;
        	while ($i < count($data))
			{
			  	$codigo = $data[$i]->ordtm_tipo.'-'.$data[$i]->ordtm_letra.'-'.
						str_pad($data[$i]->ordtm_sucursal, 5, "0", STR_PAD_LEFT).'-'.
						str_pad($data[$i]->ordtm_nro, 8, "0", STR_PAD_LEFT);
        		$pedido = $this->pedidoQuery->leePedidoporCodigo($codigo);
				if ($pedido)
					$pedido_id = $pedido->id;
				else
					return;

        		$ordentrabajo = $this->ordentrabajoQuery->leeOrdenTrabajoPorCodigo($data[$i]->ordtv_nro_orden);
				if ($ordentrabajo)
					$ordentrabajo_id = $ordentrabajo->id;
				else
				  	$ordentrabajo_id = 1;

				$articulo = $this->articuloQuery->traeArticuloPorSku(ltrim($data[$i]->ordtv_articulo));
				if ($articulo)
					$articulo_id = $articulo->id;

        		$pedido_combinacion = $this->pedido_combinacionQuery->leePedido_CombinacionporNumeroItem($pedido_id, $data[$i]->ordtm_nro_renglon);
				if ($pedido_combinacion)
					$pedido_combinacion_id = $pedido_combinacion->id;

				$pedido_combinacion_talle = $this->pedido_combinacion_talleRepository->findporpedido_combinacion_medida($pedido_combinacion_id, $data[$i]->ordtv_medida);
				if ($pedido_combinacion_talle)
					$pedido_combinacion_talle_id = $pedido_combinacion_talle->id;
				else
					$pedido_combinacion_talle_id = 1;

        		$cliente = $this->clienteQuery->traeClienteporCodigo(ltrim($data[$i]->ordtv_cliente));
				if ($cliente)
					$cliente_id = $cliente->id;
				else
				  	$cliente_id = 1;

				$arr_campos = [
					"ordentrabajo_id" => $ordentrabajo_id,
					"pedido_combinacion_talle_id" => $pedido_combinacion_talle_id,
					"cliente_id" => $cliente_id,
					"estado" => $data[$i]->ordtm_estado,
					"usuario_id" => $usuario_id
            		];
		
            	$this->model->create($arr_campos);
				$i++;
        	}
		}
    }

	private function guardarAnita($request) {
		return 0;
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'campos' => ' 
					ordtv_nro_orden,
					ordtv_articulo,
					ordtv_linea,
					ordtv_color,
					ordtv_medida,
					ordtv_cantidad,
					ordtv_forro,
					ordtv_cantentr,
					ordtv_cliente,
					ordtv_fecha,
					ordtv_agrupacion,
					ordtv_cantfact,
					ordtv_aplique
				',
            'valores' => " 
				'".$request['nro_orden']."', 
				'".$request['articulo']."',
				'".$request['nro_renglon']."',
				'".$request['color']."',
				'".$request['medida']."',
				'".$request['cantidad']."',
				'".$request['forro']."',
				'".$request['cantidad']."',
				'".$request['cliente']."',
				'".date('Ymd', strtotime($request['fecha']))."',
				'".$request['agrupacion']."',
				'".$request['cantfact']."',
				'".$request['aplique']."' "
        );
        return $apiAnita->apiCall($data);
	}

	private function actualizarAnita($request, $id) {
		return 0;
        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'valores' => " 
					ordtv_nro_orden 	= '".$id."',
					ordtv_articulo 		= '".$request['articulo']."',
					ordtv_linea 		= '".$request['linea']."',
					ordtv_color 		= '".$request['color']."',
					ordtv_medida 		= '".$request['medida']."',
					ordtv_cantidad 		= '".$request['cantidad']."',
					ordtv_forro 		= '".$request['forro']."',
					ordtv_cantentr 		= '".$request['cantentr']."',
					ordtv_cliente 		= '".$request['cliente']."',
					ordtv_fecha 		= '".date('Ymd', strtotime($request['fecha']))."',
					ordtv_agrupacion 	= '".$request['agrupacion']."',
					ordtv_cantfact 		= '".$request['cantfact']."',
					ordtv_aplique 		= '".$request['aplique']."' "
					,
				'whereArmado' => " WHERE ordtv_nro_orden = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	private function eliminarAnita($id) {
		return 0;
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE ordtv_nro_orden = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}

