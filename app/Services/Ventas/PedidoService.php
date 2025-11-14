<?php
namespace App\Services\Ventas;

use App\Repositories\Ventas\PedidoRepositoryInterface;
use App\Repositories\Ventas\Pedido_CombinacionRepositoryInterface;
use App\Repositories\Ventas\Pedido_ArticuloRepositoryInterface;
use App\Repositories\Ventas\Pedido_Articulo_EstadoRepositoryInterface;
use App\Repositories\Ventas\Pedido_Articulo_CajaRepositoryInterface;
use App\Repositories\Ventas\Ordentrabajo_Combinacion_TalleRepositoryInterface;
use App\Repositories\Ventas\Ordentrabajo_TareaRepositoryInterface;
use App\Repositories\Ventas\OrdentrabajoRepositoryInterface;
use App\Services\Configuracion\ImpuestoService;
use App\Services\Stock\Articulo_MovimientoService;
use App\Services\Stock\PrecioService;
use App\Queries\Ventas\PedidoQueryInterface;
use App\Queries\Ventas\ClienteQueryInterface;
use App\Queries\Ventas\OrdentrabajoQueryInterface;
use App\Models\Stock\Articulo;
use App\Models\Stock\Mventa;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Categoria;
use App\Models\Stock\Talle;
use LynX39\LaraPdfMerger\Facades\PdfMerger;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App;
use PDF;
use Auth;
use Exception;

class PedidoService 
{
	protected $pedidoRepository;
	protected $pedido_articuloRepository;
	protected $pedido_articulo_talleRepository;
	protected $pedido_articulo_estadoRepository;
	protected $pedido_articulo_cajaRepository;
	protected $ordentrabajo_combinacion_talleRepository;
	protected $ordentrabajo_tareaRepository;
    protected $ordentrabajoQuery;
	protected $ordentrabajoRepository;
	protected $ordentrabajoService;
	protected $pedidoQuery;
	protected $clienteQuery;
	protected $impuestoService;
	protected $articulo_movimientoService;
	protected $precioService;

    public function __construct(PedidoRepositoryInterface $pedidorepository,
								Pedido_ArticuloRepositoryInterface $pedidoarticulorepository,
								Pedido_Articulo_EstadoRepositoryInterface $pedidoarticuloestadorepository,
								Pedido_Articulo_CajaRepositoryInterface $pedidoarticulocajarepository,
    							Ordentrabajo_Combinacion_TalleRepositoryInterface $ordentrabajocombinaciontallerepository,
								Ordentrabajo_TareaRepositoryInterface $ordentrabajotarearepository,
								OrdentrabajoRepositoryInterface $ordentrabajorepository,
								OrdentrabajoQueryInterface $ordentrabajoquery,
								PedidoQueryInterface $pedidoquery,
								PrecioService $precioservice,
								ClienteQueryInterface $clientequery,
								ImpuestoService $impuestoservice,
								OrdentrabajoService $ordentrabajoservice,
								Articulo_MovimientoService $articulo_movimientoservice
								)
    {
        $this->pedidoRepository = $pedidorepository;
		$this->pedido_articuloRepository = $pedidoarticulorepository;
		$this->pedido_articulo_estadoRepository = $pedidoarticuloestadorepository;
		$this->pedido_articulo_cajaRepository = $pedidoarticulocajarepository;
        $this->ordentrabajo_combinacion_talleRepository = $ordentrabajocombinaciontallerepository;
		$this->ordentrabajo_tareaRepository = $ordentrabajotarearepository;
		$this->ordentrabajoRepository = $ordentrabajorepository;
		$this->ordentrabajoQuery = $ordentrabajoquery;
        $this->pedidoQuery = $pedidoquery;
        $this->clienteQuery = $clientequery;
        $this->impuestoService = $impuestoservice;
		$this->articulo_movimientoService = $articulo_movimientoservice;
		$this->ordentrabajoService = $ordentrabajoservice;
		$this->precioService = $precioservice;
    }

	public function leePedido($id)
	{
        $pedido = $this->pedidoRepository->find($id);

        return $pedido;
	}

	public function leePedidosPorEstado($cliente_id, $estado)
	{
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        //$hay_pedidos = $this->pedidoQuery->first();

		//if (!$hay_pedidos)
		//{
		//	$this->pedidoRepository->sincronizarConAnita();
		//	$this->pedido_articuloRepository->sincronizarConAnita();
		//	$this->pedido_articulo_talleRepository->sincronizarConAnita();
		//}
		$pedidos = $this->pedidoQuery->allPedidoIndex($cliente_id, $estado);
		$datas = [];
        foreach($pedidos as $pedido)
        {
            $pares = 0;
            $qPendiente = 0;
            $qProduccion = 0;
            $qFacturado = 0;
			$qAnulado = 0;
            foreach($pedido->pedido_articulos as $item)
            {
                $pares += $item->cantidad;
                $qPendiente++;

				$estadoPedido = $this->pedido_articulo_estadoRepository->traeEstado($item->id);

				if ($estadoPedido)
				{
					if($estadoPedido->estado == 'A')
						$qAnulado++;
				}
            }
			// Determina el estado
			$estadoPedido = "Pendiente";
			if ($qPendiente > 0 && $qProduccion > 0)
				$estadoPedido = "Pendiente/parcial";
			if ($qPendiente == 0 && $qProduccion > 0)
				$estadoPedido = "En proceso";
			if ($qFacturado == $qProduccion && $qFacturado > 0)
				$estadoPedido = "Facturado";
			else
			{
				if ($qFacturado > 0)
					$estadoPedido .= " y facturado parcial";
			}
			if ($qAnulado > 0)
			{
				if ($estadoPedido != "Pendiente")
					$estadoPedido .= "/Anulado";
				else
					$estadoPedido = "Anulado";
			}
			if ($estado == 'P' ? $qPendiente > 0 || ($qProduccion > 0 && $qFacturado < $qProduccion) : 
				($estado == 'E' ? $qProduccion > 0: ($estado == 'F' ? $qFacturado > 0 : 
				($estado == 'A' ? $qAnulado > 0 : false))))
			{
				$datas[] = ['id' => $pedido->id,
						'fecha' => $pedido->fecha,
						'nombrecliente' => $pedido->clientes->nombre,
						'codigo' => $pedido->codigo,
						'nombremarca' => $pedido->mventas->nombre,
						'pares' => $pares,
						'estado' => $estadoPedido
				];
			}
        }
		return $datas;
	}

	public function leePedidosPorEstadoSinPaginar($busqueda)
	{
		ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '2400');

		$pedidos = $this->pedidoQuery->allPedidoIndexSinPaginar($busqueda);

		return $pedidos;
	}

	public function leePedidosPorEstadoPaginando($busqueda, $estado, $reparto, $fechaentrega)
	{
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

		$pedidos = $this->pedidoQuery->allPedidoIndexPaginando($busqueda, $estado, $reparto, $fechaentrega);

		return $pedidos;
	}

	public function listarPedido($id)
	{
	  	ini_set('memory_limit', '512M');

		$pdfMerger = PDFMerger::init();

		$data = $this->pedidoQuery->leePedidoporId($id);
		$pedido = $data[0];
		$nombre_pdf = 'pedido-'.$id.'-'.$pedido->clientes->nombre;

		$view =  \View::make('exports.ventas.pedido', compact('pedido'))
			    ->render();
		$path = storage_path('pdf/pedido');

        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');
        $pdf->download($nombre_pdf.'.pdf');

		return response()->download($path.'/'.$nombre_pdf.'.pdf');

		// Por ahora queda sin hacer el merge
		//$pdfMerger->addPDF($path.'/'.$nombre_pdf.'.pdf', 'all');

		//$pdfMerger->merge();
		//$pdfMerger->save($path.'/pedido.pdf', "file");

		//return response()->download($path.'/pedido.pdf');
	}

	public function listarPreFactura($id, $items_id, $descuentoLinea = null)
	{
	  	ini_set('memory_limit', '512M');

		$pdfMerger = PDFMerger::init();

		$data = $this->pedidoQuery->leePedidoporId($id);
		$pedido = $data[0];
		$nombre_pdf = 'pedido-'.$id.'-'.$pedido->clientes->nombre;

		$itemsId = explode(",", $items_id);

		// Arma tablas para calculo de impuestos
		// Lee el cliente
		$cliente = $this->clienteQuery->traeClienteporId($pedido->cliente_id);

		if ($cliente)
		{
			// Asigna el descuento de cliente siempre
			if ($cliente->descuento != 0)
				$pedido->descuento = $cliente->descuento;
		}

		$tblImpuesto = [];
		foreach($pedido->pedido_articulos as $pedidoitem)
		{
			$articulo = Articulo::where('id',$pedidoitem->articulo_id)->first();

			if ($articulo && in_array($pedidoitem->id, $itemsId))
			{
				$precio = $this->precioService->
									asignaPrecio($articulo->id, 0, Carbon::now());

				if ($descuentoLinea > 0)
					$precioArticulo = $precio[0]['precio'] * (1 - ($descuentoLinea / 100));
				else
					$precioArticulo = $precio[0]['precio'];										

				for ($i = 0, $flEncontro = false; $i < count($tblImpuesto); $i++)
				{
					if ($tblImpuesto[$i]['precio'] == $precioArticulo &&
						$tblImpuesto[$i]['sku'] == $articulo->sku)
					{
						$flEncontro = true;
						break;
					}
				}
				if (!$flEncontro)
				{
					$tblImpuesto[] = ["sku" => $articulo->sku,
							"descripcion" => $articulo->descripcion,
							"caja" => $item->caja,
							"pieza" => $item->pieza,
							"kilo" => $item->kilo,
							"pesada" => $item->pesada,
							"precio" => $precioArticulo,
							"descuento" => $pedidoitem->descuento,
							"descuentointegrado" => $pedidoitem->descuentointegrado,
							"descuentofinal" => $pedido->descuento,
							"descuentointegradofinal" => $pedido->descuentointegrado,
							"incluyeimpuesto" => $precio[0]['incluyeimpuesto'],
							"impuesto_id" => $articulo->impuesto_id,
							"id" => $pedidoitem->id
							];
				}
				else
				{
					$tblImpuesto[$i]['cantidad'] += $item->cantidad;
				}
			}
		}
		// Arma datos del cliente
		$datosCliente = [ "condicioniva_id" => $cliente->condicioniva_id,
						  "nroinscripcion" => $cliente->nroinscripcion,
						  "retieneiva" => $cliente->retieneiva,
						  "condicioniibb" => $cliente->condicioniibb,
						  "provincia" => $cliente->provincia_id,
						];

		// Calcula impuestos
		$conceptosTotales = $this->impuestoService->calculaImpuestoVenta($tblImpuesto, $datosCliente);

		$view =  \View::make('exports.ventas.prefactura', compact('pedido', 'itemsId', 'conceptosTotales', 'tblImpuesto'))
			    ->render();
		$path = storage_path('pdf/pedido');

        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');
        $pdf->download($nombre_pdf.'.pdf');

		return response()->download($path.'/'.$nombre_pdf.'.pdf');
  	}

	// Anula item del pedido y reasigna la OT
	public function anularItemPedido($id, $codigoot, $motivocierrepedido_id, $cliente_id = null)
	{
	  	$pedido_articulo = $this->pedido_articuloRepository->findOrFail($id);

		// Si el pedido estaba en stock borra el movimiento
		$flBorraStock = false;
		if ($pedido_articulo->cliente_id == config("consprod.CLIENTE_STOCK"))
			$flBorraStock = true;

		$orden = 0;
		if ($pedido_articulo)
		{
		  	// Trae numero de item para grabar en Anita
		  	$orden = $pedido_articulo->numeroitem;

		  	$data = [];
		  	if ($pedido_articulo->estado == 'A')
			{
			  	$nuevoestado = ' ';
			  	$estado = 'recuperado';
			}
			else
			{
			  	$nuevoestado = 'A';
			  	$estado = 'anulado';
			}
			$data = ['estado' => $nuevoestado];

			DB::beginTransaction();
			try {
				$pedido = $this->pedido_articuloRepository->updatePorId($data, $id);
			
				if ($pedido)
				{
					// Graba estado
					$pedido_articulo_estado = $this->pedido_articulo_estadoRepository->create([
						'pedido_articulo_id' => $pedido_articulo->id,
						'motivocierrepedido_id' => $motivocierrepedido_id,
						'cliente_id' => $cliente_id,
						'estado' => $nuevoestado,
						'observacion' => $estado
					]);
				}
				DB::commit();
			} catch (\Exception $e) {
				DB::rollback();
				return $e->getMessage();
				$estado = 'error';
			}
			
			return(['retorno'=>$estado]);
		}
		else
			return(['retorno'=>'error']);
	}

	public function guardaPedido($data, $funcion, $id = null)
	{
	  	ini_set('memory_limit', '512M');

		$cliente = $this->clienteQuery->traeClienteporId($data['cliente_id']);

		if ($funcion == 'create')
		{	
			$data['estado'] = 'P';
			$data['estadopedido'] = 'Pendiente';
		}

		$data['tipo'] = 'PED';
		$data['letra'] = $cliente->condicionivas->letra;
		$data['sucursal'] = 1;
		$data['usuario_id'] = Auth::user()->id;
		$data['descuentointegrado'] = ' ';

		if (!array_key_exists('leyenda',$data))
			$data['leyenda'] = ' ';

		DB::beginTransaction();

		try 
		{
			if ($funcion == 'create')
			{
				$id = $this->pedidoRepository->all()->last();

				if ($id)
					$data['codigo'] = $id->id + 1;
				else
					$data['codigo'] = 1;
				$data['nro'] = 0;

				// Guarda maestro de pedidos 
				$pedido = $this->pedidoRepository->create($data);
			}
			else
			{
				$data['nro'] = substr($data['codigo'], 12, 8);

				// Actualiza maestro de pedidos
				$pedido = $this->pedidoRepository->update($data, $id);
			}
			
			// Guarda items
			if ($pedido)
			{
				if ($funcion == 'create')
					$id = $pedido->id;

				$data['pedido_id'] = $id;

				if ($funcion == 'update')
				{
					// Trae todos los id
					$pedido_articulo = $this->pedido_articuloRepository->findPorPedidoId($id)->toArray();
					$q_pedido_articulo = count($pedido_articulo);
				}
				// Graba articulos del pedido
				if (isset($data))
				{
					$articulos = $data['articulo_ids'];
					$unidadmedida_ids = $data['unidadmedida_ids'];
					$numeroitems = $data['items'];
					$cajas = $data['cajas'];
					$piezas = $data['piezas'];
					$kilos = $data['kilos'];
					$pesadas = $data['pesadas'];
					$precios = $data['precios'];
					$listaprecios = $data['listasprecios_id'];
					$incluyeimpuestos = $data['incluyeimpuestos'];
					$monedas = $data['monedas_id'];
					$descuentoventa_ids = $data['descuentoventaanterior_ids'];
					$descuentos = $data['descuentos'];
					$loteids = $data['loteids'];
					$observaciones = $data['observaciones'];
					$ids = $data['ids'];

					if ($funcion == 'update')
					{
						$_id = $pedido_articulo;

						// Borra los que sobran
						if ($q_pedido_articulo > count($articulos))
						{
							for ($d = count($articulos); $d < $q_pedido_articulo; $d++)
								$this->pedido_articuloRepository->find($_id[$d])->delete();
						}

						// Actualiza los que ya existian
						for ($i = 0; $i < $q_pedido_articulo && $i < count($articulos); $i++)
						{
							if ($i < count($articulos))
							{
								$pedido_articulo = $this->pedido_articuloRepository->update([
											"pedido_id" => $id,
											"articulo_id" => $articulos[$i],
											"unidadmedida_id" => $unidadmedida_ids[$i],
											"numeroitem" => $numeroitems[$i],
											"caja" => $cajas[$i],
											"pieza" => $piezas[$i],
											"kilo" => $kilos[$i],
											"pesada" => $pesadas[$i],
											"precio" => $precios[$i],
											"listaprecio_id" => $listaprecios[$i],
											"incluyeimpuesto" => $incluyeimpuestos[$i],
											"moneda_id" => $monedas[$i],
											"descuentoventa_id" => $descuentoventa_ids[$i],
											"descuento" => $descuentos[$i],
											"descuentointegrado" => '',
											"lote_id" => $loteids[$i],
											"observacion" => $observaciones[$i],
											"estado" => $data['estado']
											], $_id[$i]);
							}
						}
						if ($q_pedido_articulo > count($articulos))
							$i = $d; 
					}
					else
						$i = 0;

					for ($i_movimiento = $i; $i_movimiento < count($articulos); $i_movimiento++)
					{
						if ($articulos[$i_movimiento] != '') 
						{
							$pedido_articulo = $this->pedido_articuloRepository->create([
									"pedido_id" => $id,
									"articulo_id" => $articulos[$i_movimiento],
									"unidadmedida_id" => $unidadmedida_ids[$i_movimiento],
									"numeroitem" => $numeroitems[$i_movimiento],
									"caja" => $cajas[$i_movimiento],
									"pieza" => $piezas[$i_movimiento],
									"kilo" => $kilos[$i_movimiento],
									"pesada" => $pesadas[$i_movimiento],
									"precio" => $precios[$i_movimiento],
									"listaprecio_id" => $listaprecios[$i_movimiento],
									"incluyeimpuesto" => $incluyeimpuestos[$i_movimiento],
									"moneda_id" => $monedas[$i_movimiento],
									"descuentoventa_id" => $descuentoventa_ids[$i_movimiento],
									"descuento" => $descuentos[$i_movimiento],
									"descuentointegrado" => '',
									"lote_id" => $loteids[$i_movimiento],
									"observacion" => $observaciones[$i_movimiento],
									"estado" => $data['estado']			
								]);
						}
					}
				}
				else
				{
					$pedido_articulo = $this->pedido_articuloRepository->where('pedido_id', $id)->delete();
				}

				// Guarda pesada
				if ($funcion == 'update')
				{
					// Trae todos los id
					$pedido_articulo_caja = $this->pedido_articulo_cajaRepository->findPorPedidoId($id)->toArray();
					$q_pedido_articulo_caja = count($pedido_articulo_caja);
				}

				// Graba articulos del pedido
				if (isset($data['pedido_articulo_ids']))
				{
					$pedido_articulo_ids = $data['pedido_articulo_ids'];
					$numerocajapesadas = $data['numerocajapesadas'];
					$piezapesadas = $data['piezapesadas'];
					$kilopesadas = $data['kilopesadas'];
					$lotepesadas = $data['lotepesadas'];
					$fechavencimientopesadas = $data['fechavencimientopesadas'];
					$creousuariopesada_ids = $data['creousuariopesada_ids'];

					if ($funcion == 'update')
					{
						$_id = $pedido_articulo_caja;

						// Borra los que sobran
						if ($q_pedido_articulo_caja > count($pedido_articulo_ids))
						{
							for ($d = count($pedido_articulo_ids); $d < $q_pedido_articulo_caja; $d++)
								$this->pedido_articulo_cajaRepository->find($_id[$d])->delete();
						}

						// Actualiza los que ya existian
						for ($i = 0; $i < $q_pedido_articulo_caja && $i < count($pedido_articulo_ids); $i++)
						{
							if ($i < count($pedido_articulo_ids))
							{						
								$pedido_articulo_caja = $this->pedido_articulo_cajaRepository->update([
										"pedido_id" => $id,
										"pedido_articulo_id" => $pedido_articulo_ids[$i],
										"numerocaja" => $numerocajapesadas[$i],
										"pieza" => $piezapesadas[$i],
										"kilo" => $kilopesadas[$i],
										"lote" => $lotepesadas[$i],
										"fechavencimiento" => $fechavencimientopesadas[$i],
										"creousuario_id" => $creousuariopesada_ids[$i]
										], $_id[$i]);							
							}
						}
						if ($q_pedido_articulo_caja > count($pedido_articulo_ids))
							$i = $d; 
					}
					else
						$i = 0;

					for ($i_movimiento = $i; $i_movimiento < count($pedido_articulo_ids); $i_movimiento++)
					{
						if ($pedido_articulo_ids[$i_movimiento] != '') 
						{
							$pedido_articulo_caja = $this->pedido_articulo_cajaRepository->create([
									"pedido_id" => $id,
									"pedido_articulo_id" => $pedido_articulo_ids[$i_movimiento],
									"numerocaja" => $numerocajapesadas[$i_movimiento],
									"pieza" => $piezapesadas[$i_movimiento],
									"kilo" => $kilopesadas[$i_movimiento],
									"lote" => $lotepesadas[$i_movimiento],
									"fechavencimiento" => $fechavencimientopesadas[$i_movimiento],
									"creousuario_id" => $creousuariopesada_ids[$i_movimiento]		
								]);
						}
					}
				}
				else
				{
					$pedido_articulo_caja = $this->pedido_articulo_cajaRepository->deletePorPedidoId('pedido_id', $id);
				}
			}

			DB::commit();
		} catch (\Exception $e) 
		{
			DB::rollback();

			return ['error' => $e->getMessage()];
		}
		return ['id'=>$data['pedido_id'], 'codigo'=>$data['codigo']];
	}

	public function guardaItemPedido($data, $funcion, $id)
	{
		$data['usuario_id'] = Auth::user()->id;

		DB::beginTransaction();

		try 
		{
			$ordentrabajo_stock_id = 0;

			// Borra las medidas
			if ($funcion == 'update' && $data['pedido_articulo_id'] > 0)
			{
				// Lee Ot para sacar datos de stock
				$ordentrabajo_combinacion_talle = $this->ordentrabajo_combinacion_talleRepository
													   ->findPorOrdenTrabajoId($data['ordentrabajo_id']);

				if ($ordentrabajo_combinacion_talle)
					$ordentrabajo_stock_id = $ordentrabajo_combinacion_talle[0]->ordentrabajo_stock_id;
			
				$this->pedido_articulo_talleRepository->deleteporpedido_articulo($data['pedido_articulo_id']);
			}

			// Abre medidas de cada item
			$jtalles = json_decode($data['data']);
			$totPares = 0;
			foreach ($jtalles as $value)
			{
				// Guarda apertura de talles
				if ($value->cantidad ?? '' > 0)
				{
					$totPares += $value->cantidad;

					// Guarda pedido
					$pedido_articulo_talle = $this->pedido_articulo_talleRepository->create(
																$data['pedido_articulo_id'], 
																$value->talle_id, 
																$value->cantidad, 
																$value->precio
																);

					// Guarda ot
					if ($data['ordentrabajo_id'] > 0 && $funcion == 'update') 
					{
						$talle = Talle::find($value->talle_id);
						if ($talle)
							$medida = $talle->nombre;
						else
							$medida = '';

						$dataErp = array(
									'ordentrabajo_id' => $data['ordentrabajo_id'],
									'pedido_articulo_talle_id' => $pedido_articulo_talle->id,
									'cliente_id' => $data['cliente_id'],
									'usuario_id' => $data['usuario_id'],
									'ordentrabajo_stock_id' => $ordentrabajo_stock_id,
									'estado' => ''
								);

						$this->ordentrabajo_combinacion_talleRepository->create($dataErp);

						// Guarda cada talle en articulo_movimiento_talle
						if ($ordentrabajo_stock_id != 0 || $data['cliente_id'] == config("consprod.CLIENTE_STOCK"))
						{
							$dataStk = [];
							$dataStk['pedido_articulo_talle_id'] = $pedido_articulo_talle->id;
							$dataStk['talle_id'] = $value->talle_id;
							$dataStk['cantidad'] = $value->cantidad;
							$dataStk['precio'] = $value->precio;
		
							$this->articulo_movimientoService->guardaArticuloMovimientoTalle($data['pedido_articulo_id'], $dataStk);
						}
					}
				}
			}

			// Actualiza cantidad de pares en pedido_articulo
			$this->pedido_articuloRepository->update(['cantidad' => $totPares], $data['pedido_articulo_id']);
			
			// Actualiza cantidad de pares en articulo_movimiento
			if ($ordentrabajo_stock_id != 0 || $data['cliente_id'] == config("consprod.CLIENTE_STOCK"))
				$this->articulo_movimientoService->guardaArticuloMovimientoPorPedidoCombinacionId($data['pedido_articulo_id'], ['cantidad' => $totPares]);
			
			DB::commit();
		} catch (\Exception $e) 
		{
			DB::rollback();
			dd($e->getMessage());
			return $e->getMessage();
		}
	}

	public function borraPedido($id)
	{
		$fl_borro = false;

		$data = $this->pedidoQuery->leePedidoporId($id);

        if (($pedido = $this->pedidoRepository->delete($id)))
		{
			$tipo = substr($data[0]->codigo, 0, 3);
			$letra = substr($data[0]->codigo, 4, 1);
			$sucursal = substr($data[0]->codigo, 6, 5);
			$nro = substr($data[0]->codigo, 12, 8);

        	$pedido_articulo = $this->pedido_articuloRepository->deleteporpedido($id, $tipo, $letra, $sucursal, $nro);

			$fl_borro = true;
		}

		return ($fl_borro);
	}

	// Cierre de pedidos pendientes por fecha 

	public function cierrePedido($data)
	{
		// Trae pedidos por fecha
		$pedidos_combinacion = $this->pedido_articuloRepository->leePedidosSinOtPorFecha($data['hastafecha']);

		$motivocierrepedido_id = $data['motivocierrepedido_id'];

		foreach($pedidos_combinacion as $pedido)
		{
			// Trae estado
			$estado = $this->pedido_articulo_estadoRepository->traeEstado($pedido->id);
			if ($estado ? $estado->estado != 'A' : true)
			{
			  	$nuevoestado = 'A';
			  	$estado = 'anulado';
			
				$data = ['estado' => $nuevoestado];

				DB::beginTransaction();
				try {
					$this->pedido_articuloRepository->updatePorId($data, $pedido->id);
					
					// Graba estado
					$pedido_articulo_estado = $this->pedido_articulo_estadoRepository->create([
						'pedido_articulo_id' => $pedido->id,
						'motivocierrepedido_id' => $motivocierrepedido_id,
						'estado' => $nuevoestado,
						'observacion' => 'Cierre de pedido'
					]);

					DB::commit();
				} catch (\Exception $e) {
					DB::rollback();
					return $e->getMessage();
				}
			}
		}

		return 'correcto';
	}

	public function estadoPedido($pedido_id, $funcion = null)
	{
		// Lee el pedido
		$pedido = $this->pedidoQuery->leePedidoporId($pedido_id);

		$qPendiente = 0;
		$qProduccion = 0;
		$qFacturado = 0;
		$qAnulado = 0;
		foreach($pedido[0]->pedido_articulos as $item)
		{
			$qPendiente++;
			// Lee estado del pedido
			$estadoPedido = $this->pedido_articulo_estadoRepository->traeEstado($item->id);

			if ($estadoPedido)
			{
				if($estadoPedido->estado == 'A')
					$qAnulado++;
			}
		}
		// Determina el estado
		$estadoPedido = "Pendiente";
		if ($qPendiente > 0 && $qProduccion > 0)
			$estadoPedido = "Pendiente/parcial en produccion";
		if ($qPendiente == 0 && $qProduccion > 0)
			$estadoPedido = "En produccion";
		if ($qFacturado == $qProduccion && $qFacturado > 0)
			$estadoPedido = "Facturado";
		else
		{
			if ($qFacturado > 0)
				$estadoPedido .= " y facturado parcial";
		}
		if ($qAnulado > 0)
		{
			if ($estadoPedido != "Pendiente")
				$estadoPedido .= "/Anulado";
			else
				$estadoPedido = "Anulado";
		}

		if ($funcion == "update")
			$pedido = $this->pedidoRepository->update(['estadopedido' => $estadoPedido], $pedido_id);		

		return $estadoPedido;
	}	
}
