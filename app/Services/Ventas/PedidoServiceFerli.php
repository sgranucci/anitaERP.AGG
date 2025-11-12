<?php
namespace App\Services\Ventas;

use App\Repositories\Ventas\PedidoRepositoryInterface;
use App\Repositories\Ventas\Pedido_CombinacionRepositoryInterface;
use App\Repositories\Ventas\Pedido_ArticuloRepositoryInterface;
use App\Repositories\Ventas\Pedido_Combinacion_TalleRepositoryInterface;
use App\Repositories\Ventas\Pedido_Combinacion_EstadoRepositoryInterface;
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
	protected $pedido_combinacionRepository;
	protected $pedido_articuloRepository;
	protected $pedido_combinacion_talleRepository;
	protected $pedido_combinacion_estadoRepository;
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
    							Pedido_CombinacionRepositoryInterface $pedidocombinacionrepository,
								Pedido_ArticuloRepositoryInterface $pedidoarticulorepository,
    							Pedido_Combinacion_TalleRepositoryInterface $pedidocombinaciontallerepository,
								Pedido_Combinacion_EstadoRepositoryInterface $pedidocombinacionestadorepository,
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
        $this->pedido_combinacionRepository = $pedidocombinacionrepository;
		$this->pedido_articuloRepository = $pedidoarticulorepository;
        $this->pedido_combinacion_talleRepository = $pedidocombinaciontallerepository;
		$this->pedido_combinacion_estadoRepository = $pedidocombinacionestadorepository;
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
		ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '2400');

        //$hay_pedidos = $this->pedidoQuery->first();

		//if (!$hay_pedidos)
		//{
		//	$this->pedidoRepository->sincronizarConAnita();
		//	$this->pedido_combinacionRepository->sincronizarConAnita();
		//	$this->pedido_combinacion_talleRepository->sincronizarConAnita();
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
            foreach($pedido->pedido_combinaciones as $item)
            {
                $pares += $item->cantidad;
                if ($item->ot_id == 0 || $item->ot_id == null)
                    $qPendiente++;
				else
				{
                    $qProduccion++;

					$factura = $this->ordentrabajoService->buscaTareaOt($item->ot_id, config("consprod.TAREA_FACTURADA"));
					if ($factura > 0)
						$qFacturado++;
				}
				// Lee estado del pedido
				$estadoPedido = $this->pedido_combinacion_estadoRepository->traeEstado($item->id);

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

	public function leePedidosPorEstadoPaginando($busqueda)
	{
		ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '2400');

		$pedidos = $this->pedidoQuery->allPedidoIndexPaginando($busqueda);

		return $pedidos;
	}

	public function leePedidosProduccion($cliente_id)
	{
        //$hay_pedidos = $this->pedidoQuery->first();

		//if (!$hay_pedidos)
		//{
			//$this->pedidoRepository->sincronizarConAnita();
			//$this->pedido_combinacionRepository->sincronizarConAnita();
			//$this->pedido_combinacion_talleRepository->sincronizarConAnita();
		//}
		return $this->pedidoQuery->allProduccionIndex($cliente_id);
	}

	/* Lee pedidos pendientes para generacion de OT por articulo / combinacion */
	public function leePedidosPendientesOt($request)
	{
		return $this->pedidoQuery->allPendienteOt($request->articulo_id, $request->combinacion_id);
	}

	// Genera datos para reporte general de pedidos
	public function generaDatosRepGeneralPedidos($tipolistado, $estado, $mventa_id,
												$desdefecha, $hastafecha, 
												$desdevendedor_id, $hastavendedor_id,
												$desdecliente_id, $hastacliente_id,
												$desdearticulo_id, $hastaarticulo_id,
												$desdelinea_id, $hastalinea_id,
												$desdefondo_id, $hastafondo_id)
	{
		ini_set('memory_limit', '512M');

		$data = $this->pedidoQuery->findPorRangoFecha($tipolistado, $mventa_id, $desdefecha, $hastafecha,
									$desdevendedor_id, $hastavendedor_id,
									$desdecliente_id, $hastacliente_id,
									$desdearticulo_id, $hastaarticulo_id,
									$desdelinea_id, $hastalinea_id,
									$desdefondo_id, $hastafondo_id);
		// Arma datos para listado
		switch($tipolistado)
		{
			case 'CLIENTE':
			case 'ARTICULO':
			case 'LINEA':
			case 'VENDEDOR':
			case 'FONDO':
				$datas = [];
				$medidas = [];
				$anterId = 0;
				foreach($data as $pedido)
				{
					if ($anterId != $pedido['pedido_combinacion_id'])
					{
						if ($anterId != 0)
						{
							$datas[] = ['numeropedido' => $numeropedido, 
								'estadopedido' => $estadopedido,
								'numeroot' => $numeroot,
								'fecha' => $fecha,
								'nombrevendedor' => $nombrevendedor,
								'nombrecliente' => $nombrecliente,
								'codigocliente' => $codigocliente,
								'estadocliente' => $estadocliente,
								'nombrearticulo' => $nombrearticulo,
								'sku' => $sku,
								'combinacion' => $combinacion,
								'nombrefondo' => $nombrefondo,
								'cliente_id' => $cliente_id,
								'vendedor_id' => $vendedor_id,
								'linea_id' => $linea_id,
								'nombrelinea' => $nombrelinea,
								'fondo_id' => $fondo_id,
								'colorfondo_id' => $colorfondo_id,
								'nombrecolorfondo' => $nombrecolorfondo,
								'articulo_id' => $articulo_id,
								'medidas' => $medidas
							];
						}

						$anterId = $pedido['pedido_combinacion_id'];
						$medidas = [];

						$numeropedido = $pedido['pedido_id'];
						if ($pedido['estado'] == 'A')
							$estadopedido = 'ANULADO';
						else
							$estadopedido = $pedido['codigoot'] != '' ? 'EN PRODUCCION' : 'PENDIENTE';

						// Busca tareas para definir el estado real
						if ($pedido['codigoot'] != '' && $estadopedido != 'ANULADO')
						{
							$this->ordentrabajoService->traeEstadoOt($pedido['ordentrabajo_id'], $pedido['pedido_combinacion_id'], 
																	$nombretarea);

							if ($nombretarea != '')
								$estadopedido = $nombretarea;
						}

						$numeroot = $pedido['codigoot'];
						$fecha = $pedido['fecha'];
						$nombrevendedor = $pedido['nombrevendedor'];
						$nombrecliente = $pedido['nombrecliente'];
						$codigocliente = $pedido['codigocliente'];
						$estadocliente = $pedido['estadocliente'];
						$nombrearticulo = $pedido['nombrearticulo'];
						$combinacion = $pedido['nombrecombinacion'];
						$nombrefondo = $pedido['nombrefondo'];
						$cliente_id = $pedido['cliente_id'];
						$vendedor_id = $pedido['vendedor_id'];
						$linea_id = $pedido['linea_id'];
						$nombrelinea = $pedido['nombrelinea'];
						$fondo_id = $pedido['fondo_id'];
						$articulo_id = $pedido['articulo_id'];
						$sku = $pedido['sku'];
						$colorfondo_id = $pedido['colorfondo_id'];
						$nombrecolorfondo = $pedido['nombrecolorfondo'];
					}
					
					$medidas[] = ['medida' => $pedido['nombretalle'], 'cantidad' => $pedido['cantidadportalle']];
				}
				if ($anterId != 0)
				{
					$datas[] = ['numeropedido' => $numeropedido, 
								'estadopedido' => $estadopedido,
								'numeroot' => $numeroot,
								'fecha' => $fecha,
								'nombrevendedor' => $nombrevendedor,
								'nombrecliente' => $nombrecliente,
								'codigocliente' => $codigocliente,
								'estadocliente' => $estadocliente,
								'nombrearticulo' => $nombrearticulo,
								'sku' => $sku,
								'combinacion' => $combinacion,
								'nombrefondo' => $nombrefondo,
								'cliente_id' => $cliente_id,
								'vendedor_id' => $vendedor_id,
								'linea_id' => $linea_id,
								'nombrelinea' => $nombrelinea,
								'fondo_id' => $fondo_id,
								'colorfondo_id' => $colorfondo_id,
								'nombrecolorfondo' => $nombrecolorfondo,
								'articulo_id' => $articulo_id,
								'medidas' => $medidas
							];		
				}
				break;
		}
		// Filtra por estado
		$dataFiltrado = [];
		foreach ($datas as $item)
		{
			$cc = false;
			switch($estado)
			{
				case 'PENDIENTES':
					if ($item['numeroot'] == '' || $item['numeroot'] == '0')
						$cc = true;
					break;
				case 'EN PRODUCCION':
					if ($item['numeroot'] != '' && $item['numeroot'] != '0' && $item['estadopedido'] != 'FACTURADA' &&
						$item['estadopedido'] != 'TERMINADA')
						$cc = true;
					break;
				case 'TERMINADOS':
					if ($item['estadopedido'] == 'TERMINADA')
						$cc = true;
					break;
				case 'FACTURADOS':
					if ($item['estadopedido'] == 'FACTURADA')
						$cc = true;
					break;
				case 'ANULADOS':
					if ($item['estadopedido'] == 'ANULADO')
						$cc = true;
					break;
				default:
					$cc = true;
			}
			if ($cc)
				$dataFiltrado[] = $item;
		}
		return(['data' => $dataFiltrado]);
	}

	public function generaDatosRepPedido($desdeFecha, $hastaFecha, $desdeVendedor_id, $hastaVendedor_id)
	{
		$data = $this->pedidoQuery->findPorPedido($desdeFecha, $hastaFecha,
								$desdeVendedor_id, $hastaVendedor_id);

		return($data);
	}
	// Genera datos para reporte de consumo de materiales

	public function generaDatosRepConsumoMateriales($tipolistado, $estado,
												$tipocapellada, $tipoavio,
												$desdefecha, $hastafecha, 
												$desdecliente_id, $hastacliente_id,
												$desdearticulo_id, $hastaarticulo_id,
												$desdelinea_id, $hastalinea_id,
												$desdecolor_id, $hastacolor_id,
												$desdematerialcapellada_id, $hastamaterialcapellada_id,
												$desdematerialavio_id, $hastamaterialavio_id)
	{
		switch($tipolistado)
		{
		case 'CAPELLADA':
			$data = $this->pedidoQuery->findPorMaterialCapellada($tipolistado, 
								$tipocapellada, 
								$desdefecha, $hastafecha,
								$desdecliente_id, $hastacliente_id,
								$desdearticulo_id, $hastaarticulo_id,
								$desdelinea_id, $hastalinea_id,
								$desdecolor_id, $hastacolor_id,
								$desdematerialcapellada_id, $hastamaterialcapellada_id);
			break;
		case 'AVIO':
			$data = $this->pedidoQuery->findPorMaterialAvio($tipolistado, 
								$tipoavio, 
								$desdefecha, $hastafecha,
								$desdecliente_id, $hastacliente_id,
								$desdearticulo_id, $hastaarticulo_id,
								$desdelinea_id, $hastalinea_id,
								$desdecolor_id, $hastacolor_id,
								$desdematerialavio_id, $hastamaterialavio_id);
			break;
		}
		$datas = [];
		$medidas = [];
		$anterId = 0;
		$anterMaterialId = 0;
		$anterColor = '';
		$anterTipo = 'A';
		foreach($data as $pedido)
		{
			if ($anterId != $pedido['pedido_combinacion_id'] || 
				$anterMaterialId != $pedido['materialcapellada_id'] ||
				ord($anterTipo) != ord($pedido['tipo']) ||
				$anterColor != $pedido['nombrecolor'])
			{
				if ($anterId != 0)
				{
					$datas[] = ['numeropedido' => $numeropedido, 
						'estadopedido' => $estadopedido,
						'numeroot' => $numeroot,
						'fecha' => $fecha,
						'nombrevendedor' => $nombrevendedor,
						'nombrecliente' => $nombrecliente,
						'codigocliente' => $codigocliente,
						'nombrearticulo' => $nombrearticulo,
						'sku' => $sku,
						'combinacion' => $combinacion,
						'nombrefondo' => $nombrefondo,
						'cliente_id' => $cliente_id,
						'vendedor_id' => $vendedor_id,
						'linea_id' => $linea_id,
						'fondo_id' => $fondo_id,
						'articulo_id' => $articulo_id,
						'nombrematerialcapellada' => $nombrematerialcapellada,
						'materialcapellada_id' => $materialcapellada_id,
						'nombrematerialavio' => $nombrematerialavio,
						'materialavio_id' => $materialavio_id,
						'nombrecolor' => $nombrecolor,
						'tipo' => $tipo,
						'medidas' => $medidas
					];
				}

				$anterId = $pedido['pedido_combinacion_id'];
				$anterMaterialId = $pedido['materialcapellada_id'];
				$anterColor = $pedido['nombrecolor'];
				$anterTipo = $pedido['tipo'];

				$medidas = [];

				$numeropedido = $pedido['pedido_id'];
				$estadopedido = $pedido['codigoot'] != '' ? 'EN PRODUCCION' : 'PENDIENTE';

				$factura = $this->ordentrabajoService->otFacturada(0, $pedido['ot_id'], $pedido['pedido_combinacion_id']);

				if ($factura['numerofactura'] != -1 && $factura['numerofactura'] != -2 && $factura['numerofactura'] != -3)
					$estadopedido = "FACTURADA";
				else
				{
					// Busca tareas para definir el estado real
					if ($pedido['codigoot'] != '')
					{
						$this->ordentrabajoService->traeEstadoOt($pedido['ordentrabajo_id'], $pedido['pedido_combinacion_id'], 
																$nombretarea);

						if ($nombretarea != '')
							$estadopedido = $nombretarea;
					}
				}

				$numeroot = $pedido['codigoot'];
				$fecha = $pedido['fecha'];
				$nombrevendedor = $pedido['nombrevendedor'];
				$nombrecliente = $pedido['nombrecliente'];
				$codigocliente = $pedido['codigocliente'];
				$nombrearticulo = $pedido['nombrearticulo'];
				$combinacion = $pedido['nombrecombinacion'];
				$nombrefondo = $pedido['nombrefondo'];
				$cliente_id = $pedido['cliente_id'];
				$vendedor_id = $pedido['vendedor_id'];
				$linea_id = $pedido['linea_id'];
				$fondo_id = $pedido['fondo_id'];
				$articulo_id = $pedido['articulo_id'];
				$sku = $pedido['sku'];
				if ($tipolistado == 'CAPELLADA')
				{
					$nombrematerialcapellada = $pedido['nombrematerialcapellada'];
					$materialcapellada_id = $pedido['materialcapellada_id'];
				}
				else
					$nombrematerialcapellada = $materialcapellada_id = '';

				if ($tipolistado == 'AVIO')
				{ 
					$nombrematerialavio = $pedido['nombrematerialavio'];
					$materialavio_id = $pedido['materialavio_id'];
				}
				else	
					$nombrematerialavio = $materialavio_id = '';

				$nombrecolor = $pedido['nombrecolor'];
				$tipo = $pedido['tipo'];
			}
			$consumo = 0;
			// Calcula el consumo
			calculaConsumo($consumo, $pedido['nombretalle'], $pedido['cantidadportalle'], 
							$pedido['consumo1'], $pedido['consumo2'], 
							$pedido['consumo3'], $pedido['consumo4']);

			
			$medidas[] = ['medida' => $pedido['nombretalle'], 'cantidad' => $pedido['cantidadportalle'], 
							'consumo' => $consumo];
		}
		if ($anterId != 0)
		{
			$datas[] = ['numeropedido' => $numeropedido, 
						'estadopedido' => $estadopedido,
						'numeroot' => $numeroot,
						'fecha' => $fecha,
						'nombrevendedor' => $nombrevendedor,
						'nombrecliente' => $nombrecliente,
						'codigocliente' => $codigocliente,
						'nombrearticulo' => $nombrearticulo,
						'sku' => $sku,
						'combinacion' => $combinacion,
						'nombrefondo' => $nombrefondo,
						'cliente_id' => $cliente_id,
						'vendedor_id' => $vendedor_id,
						'linea_id' => $linea_id,
						'fondo_id' => $fondo_id,
						'articulo_id' => $articulo_id,
						'nombrematerialcapellada' => $nombrematerialcapellada,
						'materialcapellada_id' => $materialcapellada_id,
						'nombrematerialavio' => $nombrematerialavio,
						'materialavio_id' => $materialavio_id,
						'nombrecolor' => $nombrecolor,
						'tipo' => $tipo,
						'medidas' => $medidas
					];		
		}

		// Filtra por estado
		$dataFiltrado = [];
		foreach ($datas as $item)
		{
			$cc = false;
			switch($estado)
			{
				case 'PENDIENTES':
					if ($item['numeroot'] == '' || $item['numeroot'] == '0' || $item['numeroot'] == '-1')
						$cc = true;
					break;
				case 'EN PRODUCCION':
					if ($item['numeroot'] != '' && $item['numeroot'] != '0' && $item['numeroot'] != '-1')
						$cc = true;
					break;
				case 'TERMINADOS':
					if ($item['estadopedido'] == 'TERMINADA')
						$cc = true;
					break;
				case 'FACTURADOS':
					if ($item['estadopedido'] == 'FACTURADA')
						$cc = true;
					break;
				default:
					$cc = true;
			}
			if ($cc)
				$dataFiltrado[] = $item;
		}

		return(['data' => $dataFiltrado]);
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
		foreach($pedido->pedido_combinaciones as $pedidoitem)
		{
			$articulo = Articulo::where('id',$pedidoitem->articulo_id)->first();

			if ($articulo && in_array($pedidoitem->id, $itemsId))
			{
			  	foreach($pedidoitem->pedido_combinacion_talles as $item)
				{
					$talle = Talle::find($item->talle_id);

					$precio = $this->precioService->
                                        asignaPrecio($articulo->id, $talle->id, Carbon::now());

					if ($descuentoLinea > 0)
						$precioArticulo = $precio[0]['precio'] * (1 - ($descuentoLinea / 100));
					else
						$precioArticulo = $precio[0]['precio'];										

                    for ($i = 0, $flEncontro = false; $i < count($tblImpuesto); $i++)
                    {
                    	if ($tblImpuesto[$i]['precio'] == $precioArticulo &&
                    		$tblImpuesto[$i]['sku'] == $articulo->sku &&
                    		$tblImpuesto[$i]['combinacion_id'] == $pedidoitem->combinacion_id)
                    	{
                    		$flEncontro = true;
                    		break;
                    	}
                    }
                    if (!$flEncontro)
                   	{
						$tblImpuesto[] = ["sku" => $articulo->sku,
								"descripcion" => $articulo->descripcion,
				  				"combinacion_id" => $pedidoitem->combinacion_id,
				  				"cantidad" => $item->cantidad,
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
	  	$pedido_combinacion = $this->pedido_combinacionRepository->findOrFail($id);

		// Si el pedido estaba en stock borra el movimiento
		$flBorraStock = false;
		if ($pedido_combinacion->cliente_id == config("consprod.CLIENTE_STOCK"))
			$flBorraStock = true;

		$orden = 0;
		if ($pedido_combinacion)
		{
		  	// Trae numero de item para grabar en Anita
		  	$orden = $pedido_combinacion->numeroitem;

		  	$data = [];
		  	if ($pedido_combinacion->estado == 'A')
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
				$pedido = $this->pedido_combinacionRepository->updatePorId($data, $id);
			
				if ($pedido)
				{
					// Si tiene cliente actualiza la OT
					if ($cliente_id)
					{
						// Actualiza la OT
						$ot = $this->ordentrabajoQuery->leeOrdenTrabajoPorCodigo($codigoot);

						if ($ot)
						{
							// Lee los items
							$pedido_combinacion_talle = $this->pedido_combinacion_talleRepository
															->findporpedido_combinacion($id);
							foreach($pedido_combinacion_talle as $itemPedido)
							{
								foreach ($ot->ordentrabajo_combinacion_talles as $item)
								{
									if ($item->pedido_combinacion_talle_id == $itemPedido->id)
										// Actualiza el nuevo cliente
										$this->ordentrabajo_combinacion_talleRepository->update(
																['cliente_id' => $cliente_id], $item->id);
								}
							}
							if ($cliente_id == config("consprod.CLIENTE_STOCK"))
							{
								$articulo = Articulo::where('id', $pedido_combinacion->articulo_id)->first();
								
								// Lee la combinacion
								$combinacion = Combinacion::find($pedido_combinacion->combinacion_id);

								// Por ahora queda en deposito 1 al reasignar a cliente stock
								if ($articulo && $combinacion)
									$this->generaMovimientoStock(Carbon::now(), $pedido_combinacion, $ot, 
										$articulo, $combinacion, 0, 1);
							}
							if ($flBorraStock)
								$this->articulo_movimientoService->deletePorPedido_combinacionId($pedido_combinacion->id);
						}
					}
					// Graba estado
					$pedido_combinacion_estado = $this->pedido_combinacion_estadoRepository->create([
						'pedido_combinacion_id' => $pedido_combinacion->id,
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

		$data['estado'] = '0';
		$data['tipo'] = 'PED';
		$data['letra'] = $cliente->condicionivas->letra;
		$data['sucursal'] = $data['mventa_id'];
		$data['usuario_id'] = Auth::user()->id;
		$data['descuentointegrado'] = ' ';

		if (!array_key_exists('leyenda',$data))
			$data['leyenda'] = ' ';

		DB::beginTransaction();

		try 
		{
			if ($funcion == 'create')
			{
				$id = $this->pedidoRepository->all()->last()->id;

				$data['codigo'] = $id + 1;
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
				$data['pedido_id'] = ($funcion == 'update' ? $id : $pedido->id);
				// Borra los registros de movimientos antes de grabar nuevamente
				$ot_stock_id = [];
				$deposito_ids = [];
				if ($funcion == 'update')
				{
					foreach($data['ids'] as $pedido_combinacion_id)
					{
						// Si no tiene referencia verifica si existe igual
						$ped = $this->pedido_combinacionRepository->findPorPedidoCombinacionId($pedido_combinacion_id);
						if (count($ped) > 0)
						{
							if ($ped[0]->ordentrabajo_stock_id != '')
							{
								$ot_stock_id[] = $ped[0]->ordentrabajo_stock_id;
								$deposito_ids[] = $ped[0]->deposito_id;
							}
							else
							{
								$ot_stock_id[] = 0;
								$deposito_ids[] = 0;
							}
						}
						else
						{
							$ot_stock_id[] = 0;
							$deposito_ids[] = 0;
						}
					}
					//$this->pedido_combinacionRepository->deleteporpedido($data['pedido_id']);
					// si es update busca si hay registros borrados
					$pedido_combinacion = $this->pedido_combinacionRepository->findPorPedidoId($id);
					foreach($pedido_combinacion as $idPedidoCombinacion)
					{
						// Busca si no existe el id en el array que devuelve el blade
						$flEncontro = false;
						foreach ($data['ids'] as $idActual)
						{
							if ($idActual == $idPedidoCombinacion->id)
								$flEncontro = true;
						}
						if (!$flEncontro)
						{
							if ($idPedidoCombinacion->ot_id > 0)
							{
								// Borra la orden de trabajo
								$this->ordentrabajoRepository->deletePorCodigo($idPedidoCombinacion->ot_id);
							}
							// Borra el item
							$this->pedido_combinacionRepository->delete($idPedidoCombinacion->id);
						}
					}
				}
				$articulos = $data['articulos_id'];
				$combinaciones = $data['combinaciones_id'];
				$modulos = $data['modulos_id'];
				$numeroitems = $data['items'];
				$cantidades = $data['cantidades'];
				$precios = $data['precios'];
				$listaprecios = $data['listasprecios_id'];
				$incluyeimpuestos = $data['incluyeimpuestos'];
				$monedas = $data['monedas_id'];
				$descuentos = $data['descuentos'];
				$loteids = $data['loteids'];
				$medidas = $data['medidas'];
				$observaciones = $data['observaciones'];
				$ids = $data['ids'];
				$ot_ids = $data['ot_ids'];
				$ot_stock_ids = $ot_stock_id;
				for ($i_comb = 0; $i_comb < count($articulos); $i_comb++) 
				{
					if ($articulos[$i_comb] != '') 
					{
						// Lee el articulo
						$articulo = Articulo::select('id','categoria_id','subcategoria_id','linea_id','impuesto_id')->
									where('id',$articulos[$i_comb])->first();
						$categoria_id = $subcategoria_id = $linea_id = NULL;
						$categoria_codigo = ' ';
						if ($articulo)
						{
							$categoria_id = $articulo->categoria_id;
							$subcategoria_id = $articulo->subcategoria_id;
							$linea_id = $articulo->linea_id;

							if ($articulo->impuesto_id == NULL)
							{
								DB::rollback();
								return ['error' => 'El Artículo '.$articulo->sku.' no tiene impuestos cargados'];
							}

							$categoria = Categoria::where('id' , $articulo->categoria_id)->first();
							if ($categoria)
								$categoria_codigo = $categoria->codigo;
						}
						$ordentrabajo = '';
						if ($funcion == 'create' || $ids[$i_comb] < 1 || $ids[$i_comb] == null)
						{
							$ot_ids[$i_comb] = -1;
							$data['nro_orden'] = -1;
							// Guarda item
							$pedido_combinacion = $this->pedido_combinacionRepository->create(
								$data,
								$data['pedido_id'],
								$articulos[$i_comb],
								$combinaciones[$i_comb],
								$numeroitems[$i_comb],
								$modulos[$i_comb],
								str_replace(',','',$cantidades[$i_comb]),
								str_replace(',','',$precios[$i_comb]),
								($listaprecios[$i_comb] == 0 ? 1 : $listaprecios[$i_comb]),
								($incluyeimpuestos[$i_comb] == null || $incluyeimpuestos[$i_comb] == 'NaN' ? 'N' : $incluyeimpuestos[$i_comb]),
								($monedas[$i_comb] == null || $monedas[$i_comb] == 'NaN' ? '1' : $monedas[$i_comb]),
								$descuentos[$i_comb],
								$categoria_id,
								$subcategoria_id,
								$linea_id,
								$ot_ids[$i_comb],
								$observaciones[$i_comb],
								$medidas[$i_comb],
								$loteids[$i_comb] == 0 ? null : $loteids[$i_comb],
								$funcion
								);
							if ($ids[$i_comb] < 1 || $ids[$i_comb] == null)
								$ids[$i_comb] = $pedido_combinacion->id;
						}
						else
						{
							// Lee la OT
							if (!isset($ot_ids[$i_comb]))
								$ot_ids[$i_comb] = 0;

							if ($ot_ids[$i_comb] > 0)
							{
								$ordentrabajo = $this->ordentrabajoQuery->leeOrdenTrabajo($ot_ids[$i_comb]);
								// pasa nro. de orden para anita
								if ($ordentrabajo)
									$data['nro_orden'] = $ordentrabajo->codigo ?? -1;
								else
									$data['nro_orden'] = -1;
							}
							else
								$data['nro_orden'] = -1;

							if (!isset($precios[$i_comb]))
								$precios[$i_comb] = 0;
							if (!isset($cantidades[$i_comb]))
							{
								$cantidades[$i_comb] = 0;

								// Abre medidas de cada item
								$jtalles = json_decode($medidas[$i_comb]);
								if ($jtalles != null)
								{
									foreach ($jtalles as $value)
									{
										if ($value->cantidad > 0)
										{
											$cantidades[$i_comb] += $value->cantidad;
											$precios[$i_comb] = $value->precio;
										}
									}
								}
							}
							if (!isset($numeroitems[$i_comb]))
								$numeroitems[$i_comb] = 0;

							if (!isset($observaciones[$i_comb]))
								$observaciones[$i_comb] = '';

							// Actualiza el item
							$pedido_combinacion = $this->pedido_combinacionRepository->update(
								$data['pedido_id'],
								$articulos[$i_comb],
								$combinaciones[$i_comb],
								$numeroitems[$i_comb],
								$modulos[$i_comb],
								str_replace(',','',$cantidades[$i_comb]),
								str_replace(',','',$precios[$i_comb]),
								($listaprecios[$i_comb] == 0 ? 1 : $listaprecios[$i_comb]),
								($incluyeimpuestos[$i_comb] == null ? 'N' : $incluyeimpuestos[$i_comb]),
								($monedas[$i_comb] == null ? '1' : $monedas[$i_comb]),
								$descuentos[$i_comb],
								$categoria_id,
								$subcategoria_id,
								$linea_id,
								$ot_ids[$i_comb],
								$observaciones[$i_comb],
								$loteids[$i_comb] == 0 ? null : $loteids[$i_comb],
								$ids[$i_comb]);

								// si devolvio true lee nuevamente el registro
							if ($pedido_combinacion)
							{
								$pedido_combinacion = $this->pedido_combinacionRepository->find($ids[$i_comb]);
							}
						}
						$clienteot_id = $data['cliente_id'];
						if ($funcion == 'update' && $ot_ids[$i_comb] > 0)
						{
							// Busca el pedido
							$pedido_combinacion_talle = $this->pedido_combinacion_talleRepository
															->findporpedido_combinacion($ids[$i_comb]);
															
							// Antes de borrar debe traer si tiene otro cliente la OT por reasignacion
							if (count($pedido_combinacion_talle) > 0)
							{
								$ordentrabajo_combinacion_talle = $this->ordentrabajo_combinacion_talleRepository
															->findPorPedidoCombinacionTalleId($pedido_combinacion_talle[0]->id);
								
								if (count($ordentrabajo_combinacion_talle) > 0)
									$clienteot_id = $ordentrabajo_combinacion_talle[0]->cliente_id;
							}
						}
						// Lee la OT
						$ordentrabajo = $this->ordentrabajoQuery->leeOrdenTrabajo($ot_ids[$i_comb]);

						// Lee la combinacion
						$combinacion = Combinacion::find($combinaciones[$i_comb]);
						// Si actualiza borra los items
						if ($funcion == 'update' )
							$this->pedido_combinacion_talleRepository->
									deleteporpedido_combinacion($ids[$i_comb]);
						// Abre medidas de cada item
						$jtalles = json_decode($medidas[$i_comb]);
						$flGraboMedidas = false;
						if ($jtalles != null)
						{
							foreach ($jtalles as $value)
							{
								// Guarda apertura de talles
								if ($value->cantidad > 0)
								{
									$flGraboMedidas = true;

									$pedido_combinacion_talle = $this->pedido_combinacion_talleRepository
																	->create(
																				$ids[$i_comb], 
																				$value->talle_id, 
																				$value->cantidad, 
																				$value->precio
																				);
									// Guarda ot
									if ($ot_ids[$i_comb] > 0 && $funcion == 'update') 
									{
										$talle = Talle::find($value->talle_id);
										if ($talle)
											$medida = $talle->nombre;
										else
											$medida = '';
										$dataErp = array(
													'ordentrabajo_id' => $ordentrabajo->id,
													'pedido_combinacion_talle_id' => $pedido_combinacion_talle->id,
													'cliente_id' => $clienteot_id,
													'estado' => $data['estado'],
													'usuario_id' => $data['usuario_id'],
													'nro_orden' => $ordentrabajo->codigo ?? -1,
													'articulo' => str_pad($articulo->sku, 13, "0", STR_PAD_LEFT),
													'nro_renglon' => $numeroitems[$i_comb],
													'color' => $combinacion->codigo,
													'medida' => $medida,
													'cantidad' => $value->cantidad,
													'forro' => ' ',
													'cliente' => str_pad($cliente->codigo, 6, "0", STR_PAD_LEFT),
													'fecha' => $ordentrabajo->fecha ?? 0,
													'agrupacion' => str_pad($categoria_codigo, 4, "0", STR_PAD_LEFT),
													'estado' => $ordentrabajo->estado ?? '',
													'cantfact' => 0,
													'aplique' => 0,
													'ordentrabajo_stock_id' => $ot_stock_ids[$i_comb],
													'deposito_id' => $deposito_ids[$i_comb]
													);
										$this->ordentrabajo_combinacion_talleRepository->create($dataErp);
									}
								}
							}
						}
						if (!$flGraboMedidas)
						{
							$item = $i_comb + 1;
							throw new Exception('El item '.$item.' no tiene talles');
						}

						// Graba stock si el cliente es el correspondiente
						if (!isset($ot_stock_ids[$i_comb]))
							$ot_stock_ids[$i_comb] = 0;
						if (($ot_stock_ids[$i_comb] > 0 || $clienteot_id == config("consprod.CLIENTE_STOCK")) &&
							$ordentrabajo != null)
						{
							if ($ot_stock_ids[$i_comb] > 0)
								$this->generaMovimientoStock($data['fecha'], $pedido_combinacion, 
														$ordentrabajo, $articulo, $combinacion,
														$ot_stock_ids[$i_comb], $deposito_ids[$i_comb]);
							else
								$this->generaMovimientoStock($data['fecha'], $pedido_combinacion, 
														$ordentrabajo, $articulo, $combinacion, 0, 0);
						}
					}
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
			if ($funcion == 'update' && $data['pedido_combinacion_id'] > 0)
			{
				// Lee Ot para sacar datos de stock
				$ordentrabajo_combinacion_talle = $this->ordentrabajo_combinacion_talleRepository
													   ->findPorOrdenTrabajoId($data['ordentrabajo_id']);

				if ($ordentrabajo_combinacion_talle)
					$ordentrabajo_stock_id = $ordentrabajo_combinacion_talle[0]->ordentrabajo_stock_id;
			
				$this->pedido_combinacion_talleRepository->deleteporpedido_combinacion($data['pedido_combinacion_id']);
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
					$pedido_combinacion_talle = $this->pedido_combinacion_talleRepository->create(
																$data['pedido_combinacion_id'], 
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
									'pedido_combinacion_talle_id' => $pedido_combinacion_talle->id,
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
							$dataStk['pedido_combinacion_talle_id'] = $pedido_combinacion_talle->id;
							$dataStk['talle_id'] = $value->talle_id;
							$dataStk['cantidad'] = $value->cantidad;
							$dataStk['precio'] = $value->precio;
		
							$this->articulo_movimientoService->guardaArticuloMovimientoTalle($data['pedido_combinacion_id'], $dataStk);
						}
					}
				}
			}

			// Actualiza cantidad de pares en pedido_combinacion
			$this->pedido_combinacionRepository->update(['cantidad' => $totPares], $data['pedido_combinacion_id']);
			
			// Actualiza cantidad de pares en articulo_movimiento
			if ($ordentrabajo_stock_id != 0 || $data['cliente_id'] == config("consprod.CLIENTE_STOCK"))
				$this->articulo_movimientoService->guardaArticuloMovimientoPorPedidoCombinacionId($data['pedido_combinacion_id'], ['cantidad' => $totPares]);
			
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

        	$pedido_combinacion = $this->pedido_combinacionRepository->deleteporpedido($id, $tipo, $letra, $sucursal, $nro);

			$fl_borro = true;
		}

		return ($fl_borro);
	}

	// Cierre de pedidos pendientes por fecha 

	public function cierrePedido($data)
	{
		// Trae pedidos por fecha
		$pedidos_combinacion = $this->pedido_combinacionRepository->leePedidosSinOtPorFecha($data['hastafecha']);

		$motivocierrepedido_id = $data['motivocierrepedido_id'];

		foreach($pedidos_combinacion as $pedido)
		{
			// Trae estado
			$estado = $this->pedido_combinacion_estadoRepository->traeEstado($pedido->id);
			if ($estado ? $estado->estado != 'A' : true)
			{
			  	$nuevoestado = 'A';
			  	$estado = 'anulado';
			
				$data = ['estado' => $nuevoestado];

				DB::beginTransaction();
				try {
					$this->pedido_combinacionRepository->updatePorId($data, $pedido->id);
					
					// Graba estado
					$pedido_combinacion_estado = $this->pedido_combinacion_estadoRepository->create([
						'pedido_combinacion_id' => $pedido->id,
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

	private function generaMovimientoStock($fecha, $pedido_combinacion, $ordentrabajo, $articulo, $combinacion,
											$ordentrabajo_stock_id, $deposito_id)
	{
		$dataArticuloMovimiento = [
			'fecha' => $fecha,
			'fechajornada' => $fecha,
			'tipotransaccion_id' => $ordentrabajo_stock_id > 0? 
									config("consprod.TIPOTRANSACCION_CONSUME_OT") :
									config("consprod.TIPOTRANSACCION_ALTA_PRODUCCION"),
			'pedido_combinacion_id' => $pedido_combinacion->id,
			'ordentrabajo_id' => $ordentrabajo ? $ordentrabajo->id : 0,
			'lote' => $ordentrabajo_stock_id > 0 ? $ordentrabajo_stock_id : $ordentrabajo->codigo,
			'articulo_id' => $articulo->id,
			'combinacion_id' => $combinacion->id,
			'modulo_id' => $pedido_combinacion->modulo_id,
			'concepto' => $ordentrabajo_stock_id > 0 ? 'Consumo de OT' : 'Alta de produccion',
			'cantidad' => $pedido_combinacion->cantidad,
			'precio' => $pedido_combinacion->precio,
			'costo' => 0,
			'descuento' => $pedido_combinacion->descuento,
			'descuentointegrado' => $pedido_combinacion->descuentointegrado,
			'moneda_id' => $pedido_combinacion->moneda_id,
			'incluyeimpuesto' => $pedido_combinacion->incluyeimpuesto,
			'listaprecio_id' => $pedido_combinacion->listaprecio_id,
			'deposito_id' => $deposito_id
		];
		$pedido_combinacion_talle = $this->pedido_combinacion_talleRepository->findporpedido_combinacion($pedido_combinacion->id);

		$this->articulo_movimientoService->deletePorPedido_combinacionId($pedido_combinacion->id);

		$articulo_movimiento = $this->articulo_movimientoService->
								guardaArticuloMovimiento("create", 
								$dataArticuloMovimiento, $pedido_combinacion_talle);
	}

	public function estadoPedido($pedido_id, $funcion = null)
	{
		// Lee el pedido
		$pedido = $this->pedidoQuery->leePedidoporId($pedido_id);

		$qPendiente = 0;
		$qProduccion = 0;
		$qFacturado = 0;
		$qAnulado = 0;
		foreach($pedido[0]->pedido_combinaciones as $item)
		{
			if ($item->ot_id == 0 || $item->ot_id == null)
				$qPendiente++;
			else
			{
				$qProduccion++;

				$factura = $this->ordentrabajoService->buscaTareaOt($item->ot_id, config("consprod.TAREA_FACTURADA"));
				if ($factura > 0)
					$qFacturado++;
			}
			// Lee estado del pedido
			$estadoPedido = $this->pedido_combinacion_estadoRepository->traeEstado($item->id);

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
