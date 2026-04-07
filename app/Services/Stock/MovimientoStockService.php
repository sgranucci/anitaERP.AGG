<?php
namespace App\Services\Stock;

use App\Repositories\Stock\ArticuloRepositoryInterface;
use App\Repositories\Stock\MovimientoStockRepositoryInterface;
use App\Services\Stock\Articulo_MovimientoService;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Repositories\Ventas\PedidoRepositoryInterface;
use App\Repositories\Ventas\Pedido_ArticuloRepositoryInterface;
use App\Models\Stock\Talle;
use Auth;
use DB;

class MovimientoStockService 
{
	protected $movimientostockRepository;
	protected $tipotransaccionRepository;
	protected $articulo_movimientoService;
	protected $articuloRepository;
	protected $pedidoRepository;
	protected $pedido_articuloRepository;

    public function __construct(MovimientoStockRepositoryInterface $movimientostockrepository,
								ArticuloRepositoryInterface $articulorepository,
								Articulo_MovimientoService $articulo_movimientoservice,
								TipotransaccionRepositoryInterface $tipotransaccionrepository,
								PedidoRepositoryInterface $pedidoRepository,
								Pedido_ArticuloRepositoryInterface $pedido_articuloRepository
								)
    {
        $this->movimientostockRepository = $movimientostockrepository;
		$this->articuloRepository = $articulorepository;
		$this->articulo_movimientoService = $articulo_movimientoservice;
		$this->tipotransaccionRepository = $tipotransaccionrepository;
		$this->pedidoRepository = $pedidoRepository;
		$this->pedido_articuloRepository = $pedido_articuloRepository;
    }

	public function estadoEnum()
	{
		return $this->movimientostockRepository->estadoEnum(); 
	}

	public function all()
	{
        $movimientostock = $this->movimientostockRepository->all();

        return $movimientostock;
	}

	public function leeMovimientoStock($id)
	{
        $movimientostock = $this->movimientostockRepository->find($id);

		return $movimientostock;
	}

	public function guardaMovimientoStock($data, $funcion, $id = null)
	{
	  	ini_set('memory_limit', '512M');

		$estadoEnum = Self::estadoEnum();
		$data['estado'] = array_search('Activa', $estadoEnum);
		$data['usuario_id'] = Auth::user()->id;
		$data['descuentointegrado'] = ' ';

		if (!array_key_exists('leyenda',$data))
			$data['leyenda'] = ' ';
		DB::beginTransaction();
		try 
		{
			// Lee el tipo de transaccion
			$tipotransaccion = $this->tipotransaccionRepository->find($data['tipotransaccion_id']);

			if (!$tipotransaccion)
				throw new Exception('No puede leer tipo de transacción');

			if ($funcion == 'create')
			{
				// Si no existe el codigo numera secuencial
				if (!isset($data['codigo']))
				{
					$movimientostock = $this->movimientostockRepository->latest('id');

					if ($data['lote'] == 'LOTE DE ALTA')
					{
						$lote = 500000;
						if ($movimientostock)
							$lote = $movimientostock->id+500000;
						$data['lote'] = $lote + 1;
					}
					
					$id = 0;
					if ($movimientostock)
						$id = $movimientostock->id;

					$data['codigo'] = $id + 1;
				}

				// Guarda maestro de movimientostocks 
				$movimientostock = $this->movimientostockRepository->create($data);

				// Guarda remito en pendmae Anita
				if (strtoupper(config('app.empresa') == 'EL BIERZO') && substr($data['codigo'],0,3) == 'REM')
				{
					$this->pedidoRepository->guardarPedidoEnAnita($data);
				}
			}
			else
			{
				// Actualiza maestro de movimientostocks
				$movimientostock = $this->movimientostockRepository->update($data, $id);
			}

			// Guarda items
			if ($movimientostock)
			{
				$movimientostock_id = ($funcion == 'update' ? $id : $movimientostock->id);

				// Borra los registros de movimientos antes de grabar nuevamente
				if ($funcion == 'update')
				{
					$this->articulo_movimientoService->deletePorMovimientoStockId($movimientostock_id);
				}
				$articulos = $data['articulos_id'];
				$skus = [];
				if (isset($data['skus']))
					$skus = $data['skus'];
				$combinaciones = $data['combinaciones_id'] ?? [];
				$modulos = $data['modulos_id'];
				$numeroitems = $data['items'];
				$cantidades = $data['cantidades'];
				$cajas = $data['cajas'];
				$piezas = $data['piezas'];
				$precios = $data['precios'];
				$listaprecios = $data['listasprecios_id'];
				$incluyeimpuestos = $data['incluyeimpuestos'];
				$monedas = $data['monedas_id'];
				$descuentos = $data['descuentos'];
				$loteids = $data['loteids'];
				$medidas = $data['medidas'];

				// Graba items
				$dataArticuloMovimiento = [];
				for ($i = 0; $i < count($articulos); $i++)
				{
					$articulo = $this->articuloRepository->find($articulos[$i]);

					$codigoCategoria = ''; $sku = '';
					if ($articulo)
					{
						$sku = $articulo->sku;
						$codigoCategoria = $articulo->categorias->codigo;
					}
					$combinacion = null;
					if (isset($combinaciones[$i]))
						$combinacion = $combinaciones[$i];
					$modulo = null;
					if (isset($modulos[$i]))
						$modulo = $modulos[$i];
					$dataArticuloMovimiento = [
						'fecha' => $data['fecha'],
						'fechajornada' => $data['fecha'],
						'tipotransaccion_id' => $data['tipotransaccion_id'],
						'movimientostock_id' => $movimientostock_id,
						'deposito_id' => $data['deposito_id'],
						'venta_id' => null,
						'pedido_combinacion_id' => null,
						'ordentrabajo_id' => null,
						'lote' => $data['lote'],
						'articulo_id' => $articulos[$i],
						'sku' => $sku,
						'combinacion_id' => $combinacion,
						'modulo_id' => $modulo,
						'concepto' => $tipotransaccion->nombre,
						'cantidad' => $cantidades[$i],
						'caja' => $cajas[$i],
						'pieza' => $piezas[$i],
						'precio' => $precios[$i],
						'costo' => 0,
						'descuento' => $descuentos[$i],
						'descuentopie' => 0,
						'descuentointegrado' => null,
						'moneda_id' => $monedas[$i],
						'incluyeimpuesto' => $incluyeimpuestos[$i],
						'listaprecio_id' => $listaprecios[$i],
						'loteimportacion_id' => $data['loteimportacion_id'],
						'categoria' => $codigoCategoria,
						'codigo' => $data['codigo'],
						'letra' => $data['letra'],
						'puntoventa' => $data['puntoventa'],
						'numerocomprobante' => $data['numerocomprobante'],
						'item' => $i,
						'codigocliente' => $data['codigocliente'],
						'codigotransporte' => $data['codigotransporte'],
						'codigovendedor' => $data['codigovendedor'],
						'codigozonavta' => $data['codigozona'],
						'codigoprovincia' => $data['codigoprovincia'],
						'codigosubzona' => '',
						'codigocombinacion' => '',
						'pedido' => $data['pedido'],
						'partida' => 0,
						'empresa' => $data['empresa']
					];

					$dataTalle = [];
					if (isset($medidas))
					{
						if (count($medidas) > 0)
						{
							$jtalles = json_decode($medidas[$i]);
							foreach($jtalles as $medida)
							{
								$dataTalle[] = [
									'id' => null,
									'talle_id' => $medida->talle_id,
									'cantidad' => $medida->cantidad*($tipotransaccion->signo == 'S' ? 1 : -1),
									'precio' => $precios[$i],
								];
							}
						}
					}
					
					$articulo_movimiento = $this->articulo_movimientoService->
									guardaArticuloMovimiento('create',
									$dataArticuloMovimiento, $dataTalle);

					// Guarda remito en pendmae Anita
					//if (strtoupper(config('app.empresa') == 'EL BIERZO') && substr($data['codigo'],0,3) == 'REM')
					//{
				//		$this->pedido_articuloRepository->guardarPedidoEnAnita($dataArticuloMovimiento,
				//					$articulos[$i], 0, $i,
	  			//					0, $cantidades[$i], $precios[$i], $listaprecios[$i], $incluyeimpuestos[$i], 
	  			//					$monedas[$i], $descuentos[$i], '', $data['leyendafactura'], 'create');
				//	}									
				}
			}
			DB::commit();
		} catch (\Exception $e) 
		{
			DB::rollback();
			dd($e->getMessage());
			return $e->getMessage();
		}
		
		return ['id'=>$movimientostock_id, 'codigo'=>$data['codigo']];
	}

	public function borraMovimientoStock($id)
	{
		$movimientostock = $this->movimientostockRepository->deletePorId($id);

		$this->articulo_movimientoService->deletePorMovimientoStockId($id);

		return $movimientostock;
	}


}
