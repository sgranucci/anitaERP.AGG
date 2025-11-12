<?php
namespace App\Services\Ventas;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Queries\Ventas\OrdentrabajoQueryInterface;
use App\Queries\Ventas\ClienteQueryInterface;
use App\Queries\Ventas\Cliente_ComisionQueryInterface;
use App\Queries\Ventas\PedidoQueryInterface;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Repositories\Ventas\Pedido_CombinacionRepositoryInterface;
use App\Repositories\Ventas\Pedido_Combinacion_TalleRepositoryInterface;
use App\Repositories\Ventas\OrdentrabajoRepositoryInterface;
use App\Repositories\Ventas\Ordentrabajo_Combinacion_TalleRepositoryInterface;
use App\Repositories\Ventas\Ordentrabajo_TareaRepositoryInterface;
use App\Repositories\Ventas\PuntoventaRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Repositories\Ventas\VentaRepositoryInterface;
use App\Repositories\Ventas\Venta_EmisionRepositoryInterface;
use App\Repositories\Ventas\Venta_ImpuestoRepositoryInterface;
use App\Repositories\Ventas\Venta_ExportacionRepositoryInterface;
use App\Repositories\Ventas\Cliente_CuentacorrienteRepositoryInterface;
use App\Repositories\Ventas\Cliente_EntregaRepositoryInterface;
use App\Repositories\Ventas\TransporteRepositoryInterface;
use App\Repositories\Ventas\IncotermRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Repositories\Produccion\TareaRepositoryInterface;
use App\Repositories\Configuracion\CondicionivaRepositoryInterface;
use App\Repositories\Stock\LoteRepositoryInterface;
use App\Models\Configuracion\Impuesto;
use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Categoria;
use App\Models\Stock\Linea;
use App\Models\Stock\Talle;
use App\Models\Stock\Material;
use App\Models\Stock\Materialcapellada;
use App\Models\Stock\Materialavio;
use App\Models\Stock\Plvista;
use App\Models\Stock\Plarmado;
use App\Models\Stock\Serigrafia;
use App\Models\Stock\Capeart;
use App\Models\Stock\Avioart;
use App\Models\Stock\Puntera;
use App\Models\Stock\Contrafuerte;
use App\Models\Stock\Articulo_Caja;
use App\Models\Stock\Caja;
use App\Models\Ventas\Ordentrabajo;
use App\Models\Ventas\Copiaot;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\Condicionventacuota;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Moneda;
use App\Services\Stock\PrecioService;
use App\Services\Stock\Articulo_MovimientoService;
use App\Services\Configuracion\ImpuestoService;
use App\Services\Ventas\FacturaelectronicaService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use QrCode;
use App;
use Auth;
use DB;
use App\ApiAnita;
use Exception;

class FacturacionService 
{
	protected $ordentrabajoQuery;
	protected $ordentrabajoRepository;
	protected $ordentrabajo_combinacion_talleRepository;
	protected $ordentrabajo_tareaRepository;
	protected $tareaRepository;
	protected $loteRepository;
	protected $pedido_combinacionRepository;
	protected $pedido_combinacion_talleRepository;
	protected $cliente_cuentacorrienteRepository;
	protected $cliente_entregaRepository;
	protected $puntoventaRepository;
	protected $tipotransaccionRepository;
	protected $condicionivaRepository;
	protected $transporteRepository;
	protected $incotermRepository;
	protected $formapagoRepository;
	protected $pedidoQuery;
	protected $clienteQuery;
	protected $cliente_comisionQuery;
	protected $articuloQuery;
	protected $precioService;
	protected $impuestoService;
	protected $facturaelectronicaService;
	protected $articulo_movimientoService;
	protected $ventaRepository;
	protected $venta_emisionRepository;
	protected $venta_impuestoRepository;
	protected $venta_exportacionRepository;
	protected $tot_pares1, $tot_pares2, $tot_pares3, $tot_pares4;
	protected $mventa_id;
	protected $cantidadBulto, $puntoventaremito_id;
	protected $formapago_id, $mercaderiaExportacion, $leyendaExportacion, $incoterm_id, $abreviaturaIncoterm;
	protected $condicionVentaExportacion, $formaPagoExportacion, $monedaExportacion;
	protected $descuentoPie, $descuentoLinea, $descuentoImportePie;
	protected $numeroDespacho;
	protected $cuentacontable_id, $codigoCuentaContable, $nombreTipoTransaccion;

    public function __construct(
								OrdentrabajoQueryInterface $ordentrabajoquery,
								OrdentrabajoRepositoryInterface $ordentrabajorepository,
								Ordentrabajo_Combinacion_TalleRepositoryInterface $ordentrabajocombinaciontallerepository,
								Ordentrabajo_TareaRepositoryInterface $ordentrabajotarearepository,
								TareaRepositoryInterface $tarearepository,
								TipotransaccionRepositoryInterface $tipotransaccionrepository,
								CondicionivaRepositoryInterface $condicionivarepository,
								PuntoventaRepositoryInterface $puntoventarepository,
								PedidoQueryInterface $pedidoquery,
								ClienteQueryInterface $clientequery,
								Cliente_ComisionQueryInterface $clientecomisionquery,
								ArticuloQueryInterface $articuloquery,
								ImpuestoService $impuestoservice,
    							Pedido_CombinacionRepositoryInterface $pedidocombinacionrepository,
    							Pedido_Combinacion_TalleRepositoryInterface $pedidocombinaciontallerepository,
								PrecioService $precioservice,
								FacturaelectronicaService $facturaelectronicaservice,
								Articulo_MovimientoService $articulo_movimientoservice,
								VentaRepositoryInterface $ventarepository,
								Venta_EmisionRepositoryInterface $venta_emisionrepository,
								Venta_ImpuestoRepositoryInterface $venta_impuestorepository,
								Venta_ExportacionRepositoryInterface $venta_exportacionrepository,
								TransporteRepositoryInterface $transporterepository,
								IncotermRepositoryInterface $incotermrepository,
								FormapagoRepositoryInterface $formapagorepository,
								Cliente_CuentacorrienteRepositoryInterface $cliente_cuentacorrienterepository,
								LoteRepositoryInterface $loterepository,
								Cliente_EntregaRepositoryInterface $cliente_entregarepository
								)
    {
        $this->ordentrabajoQuery = $ordentrabajoquery;
        $this->ordentrabajoRepository = $ordentrabajorepository;
        $this->ordentrabajo_combinacion_talleRepository = $ordentrabajocombinaciontallerepository;
        $this->ordentrabajo_tareaRepository = $ordentrabajotarearepository;
		$this->tareaRepository = $tarearepository;
		$this->tipotransaccionRepository = $tipotransaccionrepository;
		$this->condicionivaRepository = $condicionivarepository;
		$this->puntoventaRepository = $puntoventarepository;
        $this->pedidoQuery = $pedidoquery;
        $this->clienteQuery = $clientequery;
        $this->cliente_comisionQuery = $clientecomisionquery;
        $this->articuloQuery = $articuloquery;
		$this->precioService = $precioservice;
        $this->pedido_combinacionRepository = $pedidocombinacionrepository;
		$this->impuestoService = $impuestoservice;
		$this->facturaelectronicaService = $facturaelectronicaservice;
		$this->articulo_movimientoService = $articulo_movimientoservice;
        $this->pedido_combinacion_talleRepository = $pedidocombinaciontallerepository;
		$this->ventaRepository = $ventarepository;
		$this->venta_emisionRepository = $venta_emisionrepository;
		$this->venta_impuestoRepository = $venta_impuestorepository;
		$this->venta_exportacionRepository = $venta_exportacionrepository;
		$this->cliente_cuentacorrienteRepository = $cliente_cuentacorrienterepository;
		$this->transporteRepository = $transporterepository;
		$this->incotermRepository = $incotermrepository;
		$this->formapagoRepository = $formapagorepository;
		$this->loteRepository = $loterepository;
		$this->cliente_entregaRepository = $cliente_entregarepository;
    }

	public function leePaginando($busqueda)
    {
        return $this->ventaRepository->leePaginando($busqueda);
    }

	public function leeSinPaginar($busqueda)
    {
        return $this->ventaRepository->leeSinPaginar($busqueda);
    }

	// Factura por item de OT

	public function generaFacturaPorItemOt(array $data)
	{
		// Guarda tipo de transaccion y punto de venta en cache
		Cache::forever(generaKey('tipotransaccion'), $data['tipotransaccion_id']);
		Cache::forever(generaKey('puntoventa'), $data['puntoventa_id']);
		Cache::forever(generaKey('puntoventaremito'), $data['puntoventaremito_id']);

		// Recibe datos para facturar
		$pedidos_combinacion_id = $data['pedido_combinacion_id'];
		$ordenestrabajo_id = $data['ordentrabajo_id'];

		$puntoventa_id = $data['puntoventa_id'];
		$tipotransaccion_id = $data['tipotransaccion_id'];
		$fechaFactura = $data['fechafactura'];
		$leyenda = $data['leyendafactura'];

		if (isset($data['deposito']))
			$deposito = $data['deposito'];
		else
			$deposito = 1;

		$this->descuentoPie = $data['descuentopie'];
		$this->descuentoLinea = $data['descuentolinea'];
		$this->descuentoImportePie = $data['descuentoimportepie'];
		$this->cantidadBulto = $data['cantidadbulto'];
		$this->puntoventaremito_id = $data['puntoventaremito_id'];
		$this->formapago_id = $data['formapago_id'];
		$this->incoterm_id = $data['incoterm_id'];
		$this->mercaderiaExportacion = $data['mercaderia'];
		$this->leyendaExportacion = $data['leyendaexportacion'];
		$this->numeroDespacho = '';
		$this->condicionVentaExportacion = '';
		$this->formaPagoExportacion = '';
		$this->monedaExportacion = '';
		$this->abreviaturaIncoterm = '';
		// Arma variables de exportacion
		if ($this->incoterm_id >= 1)
		{
			$incoterm = $this->incotermRepository->find($this->incoterm_id);

			if ($incoterm)
			{
				$this->condicionVentaExportacion = $incoterm->nombre;
				$this->abreviaturaIncoterm = $incoterm->abreviatura;
			}
			
			$formapago = $this->formapagoRepository->find($this->formapago_id);

			if ($formapago)
				$this->formaPagoExportacion = $formapago->nombre;
		}

		// Lee los items a facturar
		$dataFactura = [];
		$totPares = 0;
		for ($offOt = 0; $offOt < count($ordenestrabajo_id); $offOt++)
		{
			$ordentrabajo_id = $ordenestrabajo_id[$offOt];
			$pedido_combinacion_id = $pedidos_combinacion_id[$offOt];
		
			// Lee ot
			$ot = $this->ordentrabajoQuery->leeOrdenTrabajo($ordentrabajo_id);

			$countItem = 0;
			foreach ($ot->ordentrabajo_combinacion_talles as $item)
			{
				// Selecciona items a facturar
				if ($pedido_combinacion_id == $item->pedido_combinacion_talles->pedidos_combinacion->id)
				{
					if ($countItem == 0)
					{
						$countItem++;

						// Trea el articulo
						$articulo = $this->articuloQuery->traeArticuloPorId($item->pedido_combinacion_talles->pedido_combinaciones->articulo_id);

						if (!$articulo)
							return ['error' => 'Artículo inexistente'];

						$combinacion_id = $item->pedido_combinacion_talles->pedido_combinaciones->combinacion_id;
						$moneda_id = $item->pedido_combinacion_talles->pedido_combinaciones->moneda_id;
						$this->mventa_id = $articulo->mventa_id;
						
						// Trae la combinacion
						$combinacion = Combinacion::find($combinacion_id);
						// Trae la categoria
						$categoria = Categoria::find($articulo->categoria_id);
						$codigoCategoria = '';
						if ($categoria)
							$codigoCategoria = $categoria->codigo;

						// Trae el cliente
						$cliente = $this->clienteQuery->traeClienteporId($item->cliente_id);

						if (!$cliente)
							return ['error' => 'Cliente inexistente'];

						if ($cliente->nroinscripcion == null)
							return ['error' => 'No tiene CUIT'];
							
						$this->cuentacontable_id = $cliente->cuentacontable_id;
						$this->codigoCuentaContable = $cliente->cuentascontables->codigo;
						// Saca letra del comprobante
						$condicioniva = $this->condicionivaRepository->find($cliente->condicioniva_id);
						$letra = 'Z';
						if ($condicioniva)
							$letra = $condicioniva->letra;

						// Trae el pedido
						$pedido_query = 
							$this->pedidoQuery->leePedidoporId($item->pedido_combinacion_talles->pedidos_combinacion->pedido_id);

						if (!$pedido_query)
							return ['error' => 'Pedido inexistente'];
						else	
							$pedido = $pedido_query[0];

						// Verifica si la OT fue recodificada para traer lugar de entrega y descuento del cliente
						if ($cliente->id != $pedido->cliente_id)
						{
							$cliente_entrega = $this->cliente_entregaRepository->leeClienteEntrega($cliente->id);

							if ($cliente_entrega)
								$pedido->lugarentrega = $cliente_entrega[0]->nombre;	
							
							$this->descuentoPie = $cliente->descuento;
						}
						else
						{
							if ($pedido->cliente_entrega_id == 0)
								return ['error' => 'No tiene lugar de entrega cargado'];

								// Lee lugar de entrega
							if ($pedido->lugarentrega == null && $pedido->cliente_entrega_id > 0)
							{
								$cliente_entrega = $this->cliente_entregaRepository->find($pedido->cliente_entrega_id);

								if ($cliente_entrega)
									$pedido->lugarentrega = $cliente_entrega->nombre;
							}
						}
						// Trae el lote
						$lotestock_id = $item->ordentrabajo_stock_id;

						// Trae el id del lote de importacion
						$loteimportacion_id = $this->articulo_movimientoService->buscaLoteImportacion($lotestock_id);

						$this->numeroDespacho = '';
						if ($loteimportacion_id > 0 && $loteimportacion_id != null)
						{
							$lote = $this->loteRepository->find($loteimportacion_id);

							if ($lote)
								$this->numeroDespacho = $lote->numerodespacho;
						}
					}

					// lee el talle 
					$talle = Talle::find($item->pedido_combinacion_talles->talle_id);

					if ($talle)
					{
						$precio = $this->precioService->
										asignaPrecio($articulo->id, $talle->id, $fechaFactura);

						if ($precio[0]['precio'] == 0)
						{
							$msg = "Articulo ".$articulo->sku.' '.$articulo->descripcion.' Linea '.$articulo->linea_id
									.' Talle '.$talle->nombre.' NO TIENE PRECIO';
							return ['error' => $msg];
						}
						if ($this->descuentoLinea != 0)
							$precioUnitario = $precio[0]['precio'] * (1. - ($this->descuentoLinea / 100.));
						else
							$precioUnitario = $precio[0]['precio'];

						for ($i = 0, $flEncontro = false; $i < count($dataFactura); $i++)
						{
							if ($dataFactura[$i]['precio'] == $precioUnitario &&
								$dataFactura[$i]['sku'] == $articulo->sku &&
								$dataFactura[$i]['combinacion_id'] == $combinacion_id)
							{
								$flEncontro = true;
								break;
							}
						}
						if (!$flEncontro)
						{
							$dataFactura[] = ["cantidad" => $item->pedido_combinacion_talles->cantidad,
								"precio" => $precioUnitario,
								"descuento" => $this->descuentoLinea,
								"descuentointegrado" => '',
								"descuentofinal" => $this->descuentoPie,
								"descuentointegradofinal" => '',
								"incluyeimpuesto" => $precio[0]['incluyeimpuesto'],
								"impuesto_id" => $articulo->impuesto_id,
								"articulo_id" => $articulo->id,
								"sku" => $articulo->sku,
								"descripcion" => $articulo->descripcion,
								"codigounidadmedida" => $articulo->unidadesdemedidas->codigo ?? 1,
								'categoria' => $codigoCategoria,
								"combinacion_id" => $combinacion_id,
								'codigocombinacion' => $combinacion->codigo,
								'modulo_id' => $item->pedido_combinacion_talles->pedidos_combinacion->modulo_id,
								'moneda_id' => $item->pedido_combinacion_talles->pedidos_combinacion->moneda_id,
								'listaprecio_id' => $item->pedido_combinacion_talles->pedidos_combinacion->listaprecio_id,
								'despacho' => $this->numeroDespacho,
								'loteimportacion_id' => $loteimportacion_id,
								'ordentrabajo_id' => $ordentrabajo_id,
								'pedido_combinacion_id' => $pedido_combinacion_id
							];
												
							for ($i = 0, $flEncontro = false; $i < count($dataFactura); $i++)
							{
								if ($dataFactura[$i]['precio'] == $precioUnitario &&
									$dataFactura[$i]['sku'] == $articulo->sku &&
									$dataFactura[$i]['combinacion_id'] == $combinacion_id)
								{
									$flEncontro = true;
									break;
								}
							}
							if ($flEncontro)
							{
								$dataFactura[$i]['medidas'][] = [
										'id' => $item->pedido_combinacion_talles->id,
										'talle' => $talle->id,
										'medida' => $talle->nombre,
										'cantidad' => $item->pedido_combinacion_talles->cantidad,
										'precio' => $precioUnitario,
										'pedido' => $pedido['codigo']
								];
							}
						}
						else
						{
							$dataFactura[$i]['cantidad'] += $item->pedido_combinacion_talles->cantidad;

							$dataFactura[$i]['medidas'][] = [
											'id' => $item->pedido_combinacion_talles->id,
											'talle' => $talle->id,
											'medida' => $talle->nombre,
											'cantidad' => $item->pedido_combinacion_talles->cantidad,
											'precio' => $precioUnitario,
											'pedido' => $pedido['codigo']
											];
						}
						
						$totPares += $item->pedido_combinacion_talles->cantidad;
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
						  "descuentoimportepie" => $this->descuentoImportePie
						];

		// Calcula impuestos
		$conceptosTotales = $this->impuestoService->calculaImpuestoVenta($dataFactura, $datosCliente);

		// Arma total de comprobante
		$totalComprobante = $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Total', 'importe');

		// Calcula vencimientos
		$cuentacorriente = $this->calculaCondicionVenta($fechaFactura, 
														$totalComprobante, 
														$pedido->condicionventa_id);

		// Lee punto de venta
		$puntoventa = $this->puntoventaRepository->find($puntoventa_id);
		// Lee punto de venta del remito
		$puntoventaremito = null;
		if ($this->puntoventaremito_id >= 1)
			$puntoventaremito = $this->puntoventaRepository->find($this->puntoventaremito_id);
		if ($puntoventa && ($puntoventa->modofacturacion != 'M' ? $puntoventaremito : true))
		{
			// Lee empresa
			$empresa = Empresa::find($puntoventa->empresa_id);

			// Lee el tipo de transaccion
			$tipotransaccion = $this->tipotransaccionRepository->find($tipotransaccion_id);

			// Pide numero de factura
			$codigoTipoTransaccion = $tipotransaccion->codigo;
			$this->nombreTipoTransaccion = $tipotransaccion->nombre;
			$signo = $tipotransaccion->signo == 'S' ? 1. : -1.;
			// Numera factura con web service si es factura electronica
			if ($puntoventa->modofacturacion != 'M')
			{
				$this->facturaelectronicaService->armaTipoTransaccion($letra, $cliente->modoFacturacion, $codigoTipoTransaccion,
																		$puntoventa, $totalComprobante);

				$numero = $this->facturaelectronicaService
							->traeUltimoNumeroComprobante($empresa->nroinscripcion,
															$codigoTipoTransaccion,
															$puntoventa);

				//$numero = 74405;
			}
			else // Numera manualmente
			{
				$venta = $this->ventaRepository->traeUltimoComprobanteVenta($tipotransaccion_id, $puntoventa_id);
				if ($venta)
					$numero = $venta->numerocomprobante;
				else	
					$numero = 0;
			}

			if ($numero != -1)
			{
				$numero++;

				// Pide numero de remito
				if ($puntoventaremito && $puntoventa->modofacturacion != 'M')
					$numeroremito = $this->ventaRepository->traeUltimoNumeroRemito('REM','R',$puntoventaremito->codigo);
				else	
					$numeroremito = 0;

				//$numeroremito = 74406;

				// Procesa Factura electronica
				if ($puntoventa->modofacturacion != 'M')
				{
					// Arma tributos
					$tributos = [];
					$this->facturaelectronicaService->armaTributo($conceptosTotales, $tributos, $totalTributo);

					// Arma impuestos
					$impuestos = [];
					$this->facturaelectronicaService->armaImpuesto($conceptosTotales, $impuestos);

					// Arma comprobantes asociados
					$comprobantesAsociados = [];

					$fechaAsignacion = Carbon::parse($fechaFactura);
					$fechaAsignacion->modify('first day of this month');
					
					// Lee moneda
					$moneda = Moneda::find($moneda_id);
					$codigomoneda = 'PES';
					if ($moneda)
					{
						$codigoMoneda = $moneda->codigo;

						if ($this->incoterm_id >= 1)
							$this->monedaExportacion = $moneda->nombre;
					}

					$dataCAE = [
							'tipodoc' => 80,
							'nroinscripcion' => $cliente->nroinscripcion,
							'numerocomprobante' => $numero,
							'fechacomprobante' => date('Ymd', strtotime($fechaFactura)),
							'total' => $totalComprobante,
							'nogravado' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'No Gravado', 'importe'),
							'gravado' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Gravado al', 'importe'),
							'exento' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Exento', 'importe'),
							'iva' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Iva ', 'importe'),
							'tributo' => $totalTributo,
							'fechavencimiento' => date('Ymd', strtotime($cuentacorriente[0]['fechavencimiento'])),
							'moneda' => $codigoMoneda,
							'cotizacion' => 1,
							'tributos' => $tributos,
							'impuestos' => $impuestos,
							'comprobantesasociados' => $comprobantesAsociados,
							'fechaasignaciondesde' => date('Ymd', strtotime($fechaAsignacion)),
							'fechaasignacionhasta' => date('Ymd', strtotime($fechaFactura)),
							'pais' => $cliente->paises->codigo,
							'nombrecliente' => $cliente->nombre,
							'domicilio' => $cliente->domicilio,
							'formapago' => $cliente->condicionventas->nombre,
							'formapagoexportacion' => $this->formaPagoExportacion,
							'incoterms' => $this->abreviaturaIncoterm,
							'items' => $dataFactura
					];
				}
				// Graba la factura
				DB::beginTransaction();
				try 
				{
					if ($codigoTipoTransaccion >= '200')
						$tipoAnita = substr($tipotransaccion->abreviatura,0,1)+"CE";
					else
						$tipoAnita = $tipotransaccion->abreviatura;

					$venta = ['fecha' => $fechaFactura,
						'fechajornada' => $fechaFactura,
						'empresa_id' => $puntoventa->empresa_id,
						'tipotransaccion_id' => $tipotransaccion_id,
						'puntoventa_id' => $puntoventa->id,
						'numerocomprobante' => $numero,
						'cliente_id' => $cliente->id,
						'condicionventa_id' => $pedido->condicionventa_id,
						'vendedor_id' => $pedido->vendedor_id,
						'transporte_id' => $pedido->transporte_id,
						'total' => $totalComprobante * $signo,
						'moneda_id' => $moneda_id,
						'estado' => ' ',
						'usuario_id' => Auth::id(),
						'leyenda' => $leyenda,
						'descuento' => $this->descuentoPie,
						'descuentointegrado' => ' ',
						'lugarentrega' => $pedido->lugarentrega,
						'cliente_entrega_id' => $pedido->cliente_entrega_id,
						'codigo' => $tipoAnita.' '.$letra.'-'.
										str_pad($puntoventa->codigo, config('facturacion.DIGITOS_SUCURSAL'), "0", STR_PAD_LEFT).'-'.
										str_pad($numero, config('facturacion.DIGITOS_COMPROBANTE'), "0", STR_PAD_LEFT),
						'nombre' => $cliente->nombre,
						'domicilio' => $cliente->domicilio,
						'localidad_id' => $cliente->localidad_id,
						'provincia_id' => $cliente->provincia_id,
						'pais_id' => $cliente->pais_id,
						'codigopostal' => $cliente->codigopostal,
						'email' => $cliente->email,
						'telefono' => $cliente->telefono,
						'nroinscripcion' => $cliente->nroinscripcion,
						'condicioniva_id' => $cliente->condicioniva_id,
						'puntoventaremito_id' => $this->puntoventaremito_id,
            			'numeroremito' => $numeroremito,
						'cantidadbulto' => $this->cantidadBulto
					];	
					// Verifica si ya existe en anita
					$ventaAnita = Self::buscaVentaAnita(substr($venta['codigo'], 0, 3), $letra, $puntoventa->codigo, $venta['numerocomprobante']);
					// Si existe retorna con error
					if ($ventaAnita == $venta['numerocomprobante'])
					{
						throw new Exception('La factura '.$venta['numerocomprobante'].' ya existe en ANITA');
					}
					// Graba venta
					$vta = $this->ventaRepository->create($venta);

					// Graba venta de exportacion si existen parametros
					if ($this->formapago_id >= 1)
					{
						$ventaExportacion = [
							'venta_id' => $vta->id,
							'incoterm_id' => $this->incoterm_id,
							'formapago_id' => $this->formapago_id,
							'mercaderia' => $this->mercaderiaExportacion,  
							'leyendaexportacion' => $this->leyendaExportacion
						];

						$vtaExportacion = $this->venta_exportacionRepository->create($ventaExportacion);
					}

					// Graba impuestos
					foreach($conceptosTotales as $conc)
					{
						// Graba solo los importes distintos a 0
						if ($conc['importe'] != 0)
						{
							if ($conc['impuesto_id'] ?? null)
								$impuesto = $conc['impuesto_id'] == 0 ? null : $conc['impuesto_id'];
							else	
								$impuesto = null;

							$data = [
									'concepto' => $conc['concepto'],
									'baseimponible' => $conc['baseimponible'] ?? 0,
									'tasa' => $conc['tasa'],
									'importe' => $conc['importe'],
									'provincia_id' => $conc['provincia_id'] ?? null,
									'impuesto_id' => $impuesto
							];
							$this->venta_impuestoRepository->create($data);
						}
					} 
					// Graba cuenta corriente
					foreach($cuentacorriente as $cuota)
					{
						$data = [
							'fecha' => $fechaFactura,
							'fechavencimiento' => $cuota['fechavencimiento'],
							'cliente_id' => $cliente->id,
							'total' => $cuota['total'] * $signo,
							'moneda_id' => $moneda_id,
							'venta_id' => $vta->id,
							'cobranza_id' => null
						];
						$this->cliente_cuentacorrienteRepository->create($data);
					}

					// Graba items
					$dataArticuloMovimiento = [];
					foreach ($dataFactura as $item)
					{
						$dataArticuloMovimiento = [
							'fecha' => $fechaFactura,
							'fechajornada' => $fechaFactura,
							'tipotransaccion_id' => $tipotransaccion_id,
							'venta_id' => $vta->id,
							'pedido_combinacion_id' => $item['pedido_combinacion_id'],
							'ordentrabajo_id' => $item['ordentrabajo_id'],
							'lote' => 0,
							'articulo_id' => $item['articulo_id'],
							'combinacion_id' => $item['combinacion_id'],
							'codigocombinacion' => $item['codigocombinacion'],
							'modulo_id' => $item['modulo_id'],
							'concepto' => $tipotransaccion->nombre,
							'cantidad' => $item['cantidad'],
							'precio' => $item['precio'],
							'costo' => 0,
							'despacho' => $item['despacho'],
							'loteimportacion_id' => $item['loteimportacion_id'],
							'descuento' => $item['descuento'],
							'descuentointegrado' => $item['descuentointegrado'],
							'moneda_id' => $item['moneda_id'],
							'incluyeimpuesto' => $item['incluyeimpuesto'],
							'listaprecio_id' => $item['listaprecio_id'],
						];

						$dataTalle = [];
						foreach($item['medidas'] as $medida)
						{
							$dataTalle[] = [
								'id' => $medida['id'],
								'talle_id' => $medida['talle'],
								'medida' => $medida['medida'], // Nombre del talle
								'cantidad' => $medida['cantidad']*($tipotransaccion->signo == 'S' ? 1 : -1),
								'precio' => $medida['precio'],
								'articulo' => $item['sku'],
								'categoria' => $item['categoria'],
								'impuesto_id' => $item['impuesto_id'],
								'incluyeimpuesto' => $item['incluyeimpuesto'],
								'pedido' => $medida['pedido'],
								'codigocombinacion' => $item['codigocombinacion']
							];
						}

						// Arma tabla de emision del comprobante
						$numeroItem = 0;
						foreach($dataTalle as $itemEmision)
						{
							$dataEmision = [
								'venta_id' => $vta->id,
								'numeroitem' => ++$numeroItem, 
								'pedido_combinacion_id' => $item['pedido_combinacion_id'], 
								'ordentrabajo_id' => $item['ordentrabajo_id'], 
								'lotestock' => 0,
								'articulo_id' => $item['articulo_id'],
								'combinacion_id' => $item['combinacion_id'],
								'detalle' => $item['descripcion'], 
								'modulo_id' => $item['modulo_id'], 
								'talle_id' => $itemEmision['talle_id'], 
								'cantidad' => abs($itemEmision['cantidad']), 
								'precio' => $itemEmision['precio'], 
								'impuesto_id' => $itemEmision['impuesto_id'],
								'incluyeimpuesto' => $itemEmision['incluyeimpuesto'], 
								'moneda_id' => $item['moneda_id'], 
								'descuento' => $item['descuento'], 
								'descuentointegrado' => $item['descuentointegrado'], 
								'deposito_id' => $deposito, 
								'loteimportacion_id' => ($item['loteimportacion_id'] == 0 ? null : $item['loteimportacion_id'])
							];
							$venta_emision = $this->venta_emisionRepository->create($dataEmision);
						}
						//$articulo_movimiento = $this->articulo_movimientoService->
										//guardaArticuloMovimiento('create',
										//$dataArticuloMovimiento, $dataTalle);
					}
					// Graba contabilidad

					// Marca OT como facturada
					for ($i = 0; $i < count($ordenestrabajo_id); $i++)
					{
						$ordentrabajo_id = $ordenestrabajo_id[$i];
						$pedido_combinacion_id = $pedidos_combinacion_id[$i];
					
						$data['ordentrabajo_id'] = $ordentrabajo_id;
						$data['tarea_id'] = config("consprod.TAREA_FACTURADA"); 
						$data['desdefecha'] = Carbon::now();
						$data['hastafecha'] = Carbon::now();
						$data['empleado_id'] = null;
						$data['pedido_combinacion_id'] = $pedido_combinacion_id;
						$data['estado'] = config("consprod.TAREA_ESTADO_FACTURADA");
						$data['costo'] = 0;
						$data['usuario_id'] = Auth::id();
						$data['venta_id'] = $vta->id;

						$ordentrabajo = $this->ordentrabajo_tareaRepository->create($data);
					}

					if ($puntoventa->modofacturacion != 'M')
					{
						$cuentaVenta = '411000001';

						// Graba anita
						$anita = self::grabaAnita($puntoventa->codigo, $letra, $puntoventaremito->codigo, $numeroremito,
									$venta, $dataCAE, $conceptosTotales, $cuentacorriente, $dataFactura, $signo,
									$cuentaVenta, $this->codigoCuentaContable, true);

						if ($anita == 'Error')
							throw new Exception('Error en grabacion anita.');

						if ($anita == 'Errvend')
							throw new Exception('No tiene vendedor asignado.');

						// Solicita CAE
						if ($puntoventa->modofacturacion != 'M')
						{
							$cae = $this->facturaelectronicaService->solicitaCAE(
								$empresa->nroinscripcion,
								$codigoTipoTransaccion,
								$puntoventa,
								$dataCAE);
							//$cae = ['cae' => '74040779002259', 'fechavencimientocae' => '20240201'];
							
							if ($cae == 'Error')
								throw new Exception('No pudo asignar CAE');
	
							if ($cae['fechavencimientocae'] == 0)
								throw new Exception('No pudo asignar CAE');
						}
						$this->ventaRepository->update([
														'cae' => $cae['cae'], 
														'fechavencimientocae' => $cae['fechavencimientocae']
														],
														$vta->id);
					}
					DB::commit();

					if ($puntoventa->modofacturacion != 'M')
					{
						// Graba cae en Anita
						$vencae = Self::grabaVenCae(substr($venta['codigo'], 0, 3), $letra, $puntoventa->codigo, 
									$venta['numerocomprobante'], $cae['cae'], 
									date('Ymd', strtotime($cae['fechavencimientocae'])));

						if (strpos($vencae, 'Error') !== false)
							return 'Error';
					}

					return ['factura' => $numero, 'error' => ''];
				} catch (\Exception $e) {
					DB::rollback();

					// Borra factura de anita
					if ($venta['codigo'] ?? '')
						self::borraAnita(substr($venta['codigo'], 0, 3), $letra, 
											$puntoventa->codigo, $venta['numerocomprobante']);

					return ['error' => $e->getMessage()];
				}
			}
		}
		else
			return 'Error con punto de venta asignado';
	}

	public function grabaFacturaERP()
	{
		// Graba la factura
		DB::beginTransaction();
		try 
		{
			if ($codigoTipoTransaccion >= '200')
				$tipoAnita = substr($tipotransaccion->abreviatura,0,1)+"CE";
			else
				$tipoAnita = $tipotransaccion->abreviatura;

			$venta = ['fecha' => $fechaFactura,
				'fechajornada' => $fechaFactura,
				'empresa_id' => $puntoventa->empresa_id,
				'tipotransaccion_id' => $tipotransaccion_id,
				'puntoventa_id' => $puntoventa->id,
				'numerocomprobante' => $numero,
				'cliente_id' => $cliente->id,
				'condicionventa_id' => $pedido->condicionventa_id,
				'vendedor_id' => $pedido->vendedor_id,
				'transporte_id' => $pedido->transporte_id,
				'total' => $totalComprobante * $signo,
				'moneda_id' => $moneda_id,
				'estado' => ' ',
				'usuario_id' => Auth::id(),
				'leyenda' => $leyenda,
				'descuento' => $this->descuentoPie,
				'descuentointegrado' => ' ',
				'lugarentrega' => $pedido->lugarentrega,
				'cliente_entrega_id' => $pedido->cliente_entrega_id,
				'codigo' => $tipoAnita.' '.$letra.'-'.
								str_pad($puntoventa->codigo, config('facturacion.DIGITOS_SUCURSAL'), "0", STR_PAD_LEFT).'-'.
								str_pad($numero, config('facturacion.DIGITOS_COMPROBANTE'), "0", STR_PAD_LEFT),
				'nombre' => $cliente->nombre,
				'domicilio' => $cliente->domicilio,
				'localidad_id' => $cliente->localidad_id,
				'provincia_id' => $cliente->provincia_id,
				'pais_id' => $cliente->pais_id,
				'codigopostal' => $cliente->codigopostal,
				'email' => $cliente->email,
				'telefono' => $cliente->telefono,
				'nroinscripcion' => $cliente->nroinscripcion,
				'condicioniva_id' => $cliente->condicioniva_id,
				'puntoventaremito_id' => $this->puntoventaremito_id,
				'numeroremito' => $numeroremito,
				'cantidadbulto' => $this->cantidadBulto
			];	
			// Verifica si ya existe en anita
			$ventaAnita = Self::buscaVentaAnita(substr($venta['codigo'], 0, 3), $letra, $puntoventa->codigo, $venta['numerocomprobante']);
			// Si existe retorna con error
			if ($ventaAnita == $venta['numerocomprobante'])
			{
				throw new Exception('La factura '.$venta['numerocomprobante'].' ya existe en ANITA');
			}
			// Graba venta
			$vta = $this->ventaRepository->create($venta);

			// Graba venta de exportacion si existen parametros
			if ($this->formapago_id >= 1)
			{
				$ventaExportacion = [
					'venta_id' => $vta->id,
					'incoterm_id' => $this->incoterm_id,
					'formapago_id' => $this->formapago_id,
					'mercaderia' => $this->mercaderiaExportacion,  
					'leyendaexportacion' => $this->leyendaExportacion
				];

				$vtaExportacion = $this->venta_exportacionRepository->create($ventaExportacion);
			}

			// Graba impuestos
			foreach($conceptosTotales as $conc)
			{
				// Graba solo los importes distintos a 0
				if ($conc['importe'] != 0)
				{
					if ($conc['impuesto_id'] ?? null)
						$impuesto = $conc['impuesto_id'] == 0 ? null : $conc['impuesto_id'];
					else	
						$impuesto = null;

					$data = [
							'concepto' => $conc['concepto'],
							'baseimponible' => $conc['baseimponible'] ?? 0,
							'tasa' => $conc['tasa'],
							'importe' => $conc['importe'],
							'provincia_id' => $conc['provincia_id'] ?? null,
							'impuesto_id' => $impuesto
					];
					$this->venta_impuestoRepository->create($data);
				}
			} 
			// Graba cuenta corriente
			foreach($cuentacorriente as $cuota)
			{
				$data = [
					'fecha' => $fechaFactura,
					'fechavencimiento' => $cuota['fechavencimiento'],
					'cliente_id' => $cliente->id,
					'total' => $cuota['total'] * $signo,
					'moneda_id' => $moneda_id,
					'venta_id' => $vta->id,
					'cobranza_id' => null
				];
				$this->cliente_cuentacorrienteRepository->create($data);
			}

			// Graba items
			$dataArticuloMovimiento = [];
			foreach ($dataFactura as $item)
			{
				$dataArticuloMovimiento = [
					'fecha' => $fechaFactura,
					'fechajornada' => $fechaFactura,
					'tipotransaccion_id' => $tipotransaccion_id,
					'venta_id' => $vta->id,
					'pedido_combinacion_id' => $item['pedido_combinacion_id'],
					'ordentrabajo_id' => $item['ordentrabajo_id'],
					'lote' => 0,
					'articulo_id' => $item['articulo_id'],
					'combinacion_id' => $item['combinacion_id'],
					'codigocombinacion' => $item['codigocombinacion'],
					'modulo_id' => $item['modulo_id'],
					'concepto' => $tipotransaccion->nombre,
					'cantidad' => $item['cantidad'],
					'precio' => $item['precio'],
					'costo' => 0,
					'despacho' => $item['despacho'],
					'loteimportacion_id' => $item['loteimportacion_id'],
					'descuento' => $item['descuento'],
					'descuentointegrado' => $item['descuentointegrado'],
					'moneda_id' => $item['moneda_id'],
					'incluyeimpuesto' => $item['incluyeimpuesto'],
					'listaprecio_id' => $item['listaprecio_id'],
				];

				$dataTalle = [];
				foreach($item['medidas'] as $medida)
				{
					$dataTalle[] = [
						'id' => $medida['id'],
						'talle_id' => $medida['talle'],
						'medida' => $medida['medida'], // Nombre del talle
						'cantidad' => $medida['cantidad']*($tipotransaccion->signo == 'S' ? 1 : -1),
						'precio' => $medida['precio'],
						'articulo' => $item['sku'],
						'categoria' => $item['categoria'],
						'impuesto_id' => $item['impuesto_id'],
						'incluyeimpuesto' => $item['incluyeimpuesto'],
						'pedido' => $medida['pedido'],
						'codigocombinacion' => $item['codigocombinacion']
					];
				}

				// Arma tabla de emision del comprobante
				$numeroItem = 0;
				foreach($dataTalle as $itemEmision)
				{
					$dataEmision = [
						'venta_id' => $vta->id,
						'numeroitem' => ++$numeroItem, 
						'pedido_combinacion_id' => $item['pedido_combinacion_id'], 
						'ordentrabajo_id' => $item['ordentrabajo_id'], 
						'lotestock' => 0,
						'articulo_id' => $item['articulo_id'],
						'combinacion_id' => $item['combinacion_id'],
						'detalle' => $item['descripcion'], 
						'modulo_id' => $item['modulo_id'], 
						'talle_id' => $itemEmision['talle_id'], 
						'cantidad' => abs($itemEmision['cantidad']), 
						'precio' => $itemEmision['precio'], 
						'impuesto_id' => $itemEmision['impuesto_id'],
						'incluyeimpuesto' => $itemEmision['incluyeimpuesto'], 
						'moneda_id' => $item['moneda_id'], 
						'descuento' => $item['descuento'], 
						'descuentointegrado' => $item['descuentointegrado'], 
						'deposito_id' => $deposito, 
						'loteimportacion_id' => ($item['loteimportacion_id'] == 0 ? null : $item['loteimportacion_id'])
					];
					$venta_emision = $this->venta_emisionRepository->create($dataEmision);
				}
				//$articulo_movimiento = $this->articulo_movimientoService->
								//guardaArticuloMovimiento('create',
								//$dataArticuloMovimiento, $dataTalle);
			}
			// Graba contabilidad

			// Marca OT como facturada
			for ($i = 0; $i < count($ordenestrabajo_id); $i++)
			{
				$ordentrabajo_id = $ordenestrabajo_id[$i];
				$pedido_combinacion_id = $pedidos_combinacion_id[$i];
			
				$data['ordentrabajo_id'] = $ordentrabajo_id;
				$data['tarea_id'] = config("consprod.TAREA_FACTURADA"); 
				$data['desdefecha'] = Carbon::now();
				$data['hastafecha'] = Carbon::now();
				$data['empleado_id'] = null;
				$data['pedido_combinacion_id'] = $pedido_combinacion_id;
				$data['estado'] = config("consprod.TAREA_ESTADO_FACTURADA");
				$data['costo'] = 0;
				$data['usuario_id'] = Auth::id();
				$data['venta_id'] = $vta->id;

				$ordentrabajo = $this->ordentrabajo_tareaRepository->create($data);
			}

			if ($puntoventa->modofacturacion != 'M')
			{
				$cuentaVenta = '411000001';

				// Graba anita
				$anita = self::grabaAnita($puntoventa->codigo, $letra, $puntoventaremito->codigo, $numeroremito,
							$venta, $dataCAE, $conceptosTotales, $cuentacorriente, $dataFactura, $signo,
							$cuentaVenta, $this->codigoCuentaContable, true);

				if ($anita == 'Error')
					throw new Exception('Error en grabacion anita.');

				if ($anita == 'Errvend')
					throw new Exception('No tiene vendedor asignado.');

				// Solicita CAE
				if ($puntoventa->modofacturacion != 'M')
				{
					$cae = $this->facturaelectronicaService->solicitaCAE(
						$empresa->nroinscripcion,
						$codigoTipoTransaccion,
						$puntoventa,
						$dataCAE);
					//$cae = ['cae' => '73300854793410', 'fechavencimientocae' => '20230806'];
					
					if ($cae == 'Error')
						throw new Exception('No pudo asignar CAE');

					if ($cae['fechavencimientocae'] == 0)
						throw new Exception('No pudo asignar CAE');
				}
				$this->ventaRepository->update([
												'cae' => $cae['cae'], 
												'fechavencimientocae' => $cae['fechavencimientocae']
												],
												$vta->id);
			}
			DB::commit();

			if ($puntoventa->modofacturacion != 'M')
			{
				$vencae = Self::grabaVenCae(substr($venta['codigo'], 0, 3), $letra, $puntoventa->codigo, 
									$venta['numerocomprobante'], $cae['cae'], 
									date('Ymd', strtotime($cae['fechavencimientocae'])));

				// Graba cae en anita
				//$apiAnita = new ApiAnita();

				//$data = array( 	'tabla' => 'vencae', 
				//				'acc' => 'insert',
				//				'campos' => ' 
				//					venc_tipo, venc_letra, venc_sucursal, venc_nro, venc_nro_cae, venc_fecha_vto,
				//					venc_nro_id, venc_nro_sec ',
				//				'valores' => "
				//					'".substr($venta['codigo'], 0, 3)."',
				//					'".$letra."',
				//					'".$puntoventa->codigo."',
				//					'".$venta['numerocomprobante']."',
				//					'".$cae['cae']."',
				//					'".date('Ymd', strtotime($cae['fechavencimientocae']))."',
				//					'".'1'."',
				//					'".'1'."'
				//				"
				//		);
				//$vencae = $apiAnita->apiCall($data);

				if (strpos($vencae, 'Error') !== false)
					return 'Error';
			}

			return ['factura' => $numero, 'error' => ''];
		} catch (\Exception $e) {
			DB::rollback();

			// Borra factura de anita
			if ($venta['codigo'] ?? '')
				self::borraAnita(substr($venta['codigo'], 0, 3), $letra, 
									$puntoventa->codigo, $venta['numerocomprobante']);

			return ['error' => $e->getMessage()];
		}
	}

	// Graba factura en Anita
	public function grabaAnita($puntoventa, $letra, $puntoventaremito, $numeroremito, $venta, 
								$dataCAE, $conceptostotales, $cuentacorriente, $datatalle, $signo, 
								$cuentaVenta, $contrapartida, $flGrabaStock, $servidor = null, $ifx_server = null)
	{
		// Lee el cliente
		$cliente = $this->clienteQuery->traeClienteporId($venta['cliente_id']);
		$codigoCliente = '';
		$zonavta_id = $provincia_id = $subzonavta_id = 0;
		$codigopostal = '';
		$nroinscripcion = '';
		$nombre = "";
		$domicilio = "";
		if ($cliente)
		{
			$codigoCliente = $cliente->codigo;
			$zonavta_id = $cliente->zonavta_id;
			$provincia_id = $cliente->provincia_id;
			$subzonavta_id = $cliente->subzonavta_id;
			$codigopostal = $cliente->codigopostal;
			$nroinscripcion = $cliente->nroinscripcion;
			$nombre = $cliente->nombre;
			$domicilio = $cliente->domicilio;
		}
		else
		{
			if (isset($venta['nombrecliente']))
				$nombre = $venta['nombrecliente'];
		
			if (isset($venta['documentocliente']))
				$domicilio = $nroinscripcion = $venta['documentocliente'];
		}

		// Calcula totales para venta
		$totalIngBruto2 = $totalIngBruto1 = $totalPercepcionIva = 0;
		$totalDescuento = $porcentajeDescuento = 0;
		foreach ($conceptostotales as $concepto)
		{
			if (array_key_exists('jurisdiccion', $concepto))
			{
				if ($concepto['jurisdiccion'] == '902')
					$totalIngBruto1 += $concepto['importe'];
				else
					$totalIngBruto2 += $concepto['importe'];
			}
			if ($concepto['concepto'] == 'Percepcion IVA')
				$totalPercepcionIva += $concepto['importe'];

			if (strpos($concepto['concepto'], 'Descuento') !== false)
			{
				$totalDescuento += $concepto['importe'];
				$porcentajeDescuento = $concepto['tasa'];
			}
		}
		// Lee comisiones
		if ($codigoCliente != '')
		{
			$vendedor = Self::leeVendedor(str_pad($codigoCliente, 6, "0", STR_PAD_LEFT), $this->mventa_id);

			if ($vendedor == 0)
				return 'Errvend';
		}

		else
			$vendedor = 1;
		
		// Graba venta
        $apiAnita = new ApiAnita();
		$exento = $dataCAE['exento'] + $dataCAE['nogravado'];

		if (!isset($cliente->localidades->nombre))
			$nombreLocalidad = '';
		else
			$nombreLocalidad = $cliente->localidades->nombre;	

		if (!isset($cliente->provincias->nombre))
			$nombreProvincia = '';
		else
			$nombreProvincia = $cliente->provincias->nombre;	

        $data = array( 	'tabla' => 'venta', 
						'acc' => 'insert',
            			'campos' => ' 
							ven_cliente, ven_tipo, ven_letra, ven_sucursal, ven_nro, ven_fecha, ven_fecha_vto,        
							ven_exento, ven_gravado, ven_gravado_ot, ven_tasa_iva_ot, ven_imp_interno,      
							ven_no_inscripto, ven_sellado, ven_porc_sellado, ven_flete, ven_impuesto1,   
							ven_percepcion_iva, ven_monto, ven_monto_desc, ven_porc_desc, ven_monto_anul,    
							ven_cod_mon, ven_cotizacion, ven_fecha_cobro, ven_t_ult_cobro, ven_t_cobrado,     
							ven_zonavta, ven_subzona, ven_zonamult, ven_vendedor, ven_cobrador, ven_cond_venta,       
							ven_comision_ven, ven_nombre_cliente, ven_direccion_cli, ven_localidad_cli,    
							ven_provincia_cli, ven_cod_postal_cli, ven_cuit_cli, ven_cond_iva_cli,     
							ven_cta_cte, ven_usuario, ven_terminal, ven_fe_ult_act, ven_fl_imprimio,      
							ven_cedio_a, ven_fecha_cesion, ven_nro_cesion, ven_perc_ing_bruto, 
							ven_cod_entrega      
						',
            			'valores' => " 
							'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
							'".substr($venta['codigo'], 0, 3)."',
							'".$letra."',
							'".$puntoventa."',
							'".$venta['numerocomprobante']."',
							'".date('Ymd', strtotime($venta['fecha']))."',
							'".date('Ymd', strtotime($venta['fechajornada']))."',
							'".$exento."',
							'".$dataCAE['gravado']."',
							'".'0'."',
							'".'0'."',
							'".'0'."',
							'".'0'."',
							'".$totalIngBruto2."',
							'".'0'."',
							'".'0'."',
							'".$dataCAE['iva']."',
							'".$totalPercepcionIva."',
							'".abs($venta['total'])."',
							'".abs($totalDescuento)."',
							'".$porcentajeDescuento."',
							'".'0'."',
							'".$venta['moneda_id']."',
							'".'1'."',
							'".'0'."',
							'".'0'."',
							'".'0'."',
							'".($zonavta_id == null ? '0' : $cliente->zonavta_id)."',
							'".($provincia_id == null ? '0' : $cliente->provincia_id)."',
							'".($subzonavta_id == null ? '0' : $cliente->subzonavta_id)."',
							'".$vendedor."',
							'".'0'."',
							'".$venta['condicionventa_id']."',
							'".'0'."',
							'".$nombre."',
							'".$domicilio."',
							'".$nombreLocalidad."',
							'".$nombreProvincia."',
							'".$codigopostal."',
							'".$nroinscripcion."',
							'".($letra == 'A' ? '1' : '4')."',
							'".'S'."',
							'".Auth::user()->nombre."',
							'".'ERP'."',
							'".date_format(Carbon::now(), 'Ymd')."',
							'".' '."',
							'".'0'."',
							'".'0'."',
							'".'0'."',
							'".$totalIngBruto1."',
							'".'0'."'
							"
					);
        $vta = $apiAnita->apiCall($data);

		if (strpos($vta, 'Error') !== false)
			return 'Error';

		// Graba venibr
		foreach ($conceptostotales as $concepto)
		{
			if (array_key_exists('jurisdiccion', $concepto))
			{
				// Graba venibr
				$apiAnita = new ApiAnita();

				$data = array( 	'tabla' => 'venibr', 
								'acc' => 'insert',
								'campos' => ' 
									veni_tipo, veni_letra, veni_sucursal, veni_nro, veni_provincia,
									veni_codigo_perc, veni_porcentaje, veni_importe ',
								'valores' => "
									'".substr($venta['codigo'], 0, 3)."',
									'".$letra."',
									'".$puntoventa."',
									'".$venta['numerocomprobante']."',
									'".$concepto['jurisdiccion']."',
									'".'I'."',
									'".$concepto['tasa']."',
									'".$concepto['importe']."'
								"
						);
						
				$venibr = $apiAnita->apiCall($data);

				if (strpos($venibr, 'Error') !== false)
					return 'Error';
			}
		}
		
		// Graba climov
		$nroCuota = 0;
		foreach($cuentacorriente as $cuota)
		{
			$apiAnita = new ApiAnita();
			$nroCuota++;

			$data = array( 	'tabla' => 'climov', 
							'acc' => 'insert',
							'campos' => ' 
								cliv_cliente, cliv_tipo, cliv_letra, cliv_sucursal, cliv_nro, cliv_ref_tipo,
								cliv_ref_letra, cliv_ref_sucursal, cliv_ref_nro, cliv_fecha, cliv_fecha_vto,
								cliv_monto, cliv_cod_mon, cliv_cotizacion, cliv_nro_cuota, cliv_t_cobrado,
								cliv_fecha_cobro, cliv_cedio_a, cliv_estado ',
							'valores' => "
								'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
								'".substr($venta['codigo'], 0, 3)."',
								'".$letra."',
								'".$puntoventa."',
								'".$venta['numerocomprobante']."',
								'".' '."',
								'".' '."',
								'".'0'."',
								'".'0'."',
								'".date('Ymd', strtotime($venta['fecha']))."',
								'".date('Ymd', strtotime($cuota['fechavencimiento']))."',
								'".$cuota['total']."',
								'".$venta['moneda_id']."',
								'".'1'."',
								'".$nroCuota."',
								'".'0'."',
								'".'0'."',
								'".'0'."',
								'".'I'."'
							"
					);
			$climov = $apiAnita->apiCall($data);

			if (strpos($climov, 'Error') !== false)
				return 'Error';
		}
		
		// Lee el transporte
		$codigoTransporte = 0;
		if ($venta['transporte_id'] != null)
		{
			$transporte = $this->transporteRepository->find($venta['transporte_id']);

			if ($transporte)
				$codigoTransporte = $transporte->codigo;
		}
		
		$leyenda = '';

		// Filtra lugar de entrega
		$lugarEntrega = preg_replace('([^A-Za-z0-9])', '', $venta['lugarentrega']);

		// Graba comprob
		$exento = $dataCAE['exento']+$dataCAE['nogravado'];
		$apiAnita = new ApiAnita();
		$data = array( 	'tabla' => 'comprob', 
						'acc' => 'insert',
						'campos' => ' 
							comp_cliente, comp_tipo, comp_letra, comp_sucursal, comp_nro_fact, comp_pedido,
							comp_remito, comp_fecha, comp_fevto, comp_cond_vta, comp_entrega, comp_dto,
							comp_transporte, comp_o_compra, comp_leyenda, comp_total, comp_iva,
							comp_no_insc, comp_exento, comp_gravado, comp_dto_integrado, comp_cond_vta_exp,
							comp_fpago_exp, comp_merc_exp, comp_moneda_exp, comp_sucursal_rem ',
						'valores' => "
							'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
							'".substr($venta['codigo'], 0, 3)."',
							'".$letra."',
							'".$puntoventa."',
							'".$venta['numerocomprobante']."',
							'".$this->cantidadBulto."',
							'".$venta['numerocomprobante']."',
							'".date('Ymd', strtotime($venta['fecha']))."',
							'".'0'."',
							'".$venta['condicionventa_id']."',
							'".$lugarEntrega."',
							'".$porcentajeDescuento."',
							'".$codigoTransporte."',
							'".'0'."',
							'".$leyenda."',
							'".$venta['total']."',
							'".$dataCAE['iva']."',
							'".'0'."',
							'".$exento."',
							'".$dataCAE['gravado']."',
							'".$venta['descuentointegrado']."',
							'".$this->condicionVentaExportacion."',
							'".$this->formaPagoExportacion."',
							'".$this->mercaderiaExportacion."',
							'".$this->monedaExportacion."',
							'".$puntoventaremito."'
						"
					);
		$comprob = $apiAnita->apiCall($data);

		if (strpos($comprob, 'Error') !== false)
			return 'Error';
			
		// Agrupa por medida / partida para anita
		$dataItem = [];
		foreach($datatalle as $item)
		{
			foreach ($item['medidas'] as $medida)
			{
				$partida = 1;
				if ($medida['medida'] >= config('consprod.DESDE_INTERVALO1') &&
					$medida['medida'] <= config('consprod.HASTA_INTERVALO1'))
					$partida = 1;
				if ($medida['medida'] >= config('consprod.DESDE_INTERVALO2') &&
					$medida['medida'] <= config('consprod.HASTA_INTERVALO2'))
					$partida = 2;
				if ($medida['medida'] >= config('consprod.DESDE_INTERVALO3') &&
					$medida['medida'] <= config('consprod.HASTA_INTERVALO3'))
					$partida = 3;
				if ($medida['medida'] >= config('consprod.DESDE_INTERVALO4') &&
					$medida['medida'] <= config('consprod.HASTA_INTERVALO4'))
					$partida = 4;
				
				for ($ii = 0, $flEncontro = false; $ii < count($dataItem); $ii++)
				{
					if ($dataItem[$ii]['partida'] == $partida &&
						$dataItem[$ii]['sku'] == $item['sku'] &&
						$dataItem[$ii]['codigocombinacion'] == $item['codigocombinacion'])
					{
						$flEncontro = true;
						break;
					}
				}
				
				if ($flEncontro)
					$dataItem[$ii]['cantidad'] += $medida['cantidad'];
				else
				{
					$dataItem[] = [
						'partida' => $partida,
						'cantidad' => $medida['cantidad'],
						'precio' => $medida['precio'],
						'impuesto_id' => $item['impuesto_id'],
						'incluyeimpuesto' => $item['incluyeimpuesto'],
						'pedido' => $medida['pedido'],
						'sku' => $item['sku'],
						'descripcion' => $item['descripcion'],
						'categoria' => $item['categoria'],
						'codigocombinacion' => $item['codigocombinacion'],
						'despacho' => $item['despacho'],
						'medida' => $medida['medida']
					];
				}
			}
		}
		// Graba compaux
		$orden = 0;
		foreach($dataItem as $medida)
		{
			$orden++;

			$apiAnita = new ApiAnita();
			
			$data = array( 	'tabla' => 'compaux', 
							'acc' => 'insert',
							'campos' => ' 
								compa_cliente, compa_tipo, compa_letra, compa_sucursal, compa_nro_fact, 
								compa_orden, compa_articulo, compa_cantidad, compa_precio, compa_desc, compa_dto,
								compa_deposito, compa_tipo_iva, compa_referencia, compa_fecha, compa_incl_imp,
								compa_despacho ',
							'valores' => "
								'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
								'".substr($venta['codigo'], 0, 3)."',
								'".$letra."',
								'".$puntoventa."',
								'".$venta['numerocomprobante']."',
								'".$orden."',
								'".str_pad($medida['sku'], 13, "0", STR_PAD_LEFT)."', 
								'".$medida['cantidad']."', 
								'".$medida['precio']."', 
								'".$medida['descripcion']."', 
								'".($this->descuentoLinea == null || $letra == 'E'? '0' : $this->descuentoLinea)."',
								'".'1'."',
								'".$medida['impuesto_id']."', 
								'".' '."',
								'".date('Ymd', strtotime($venta['fecha']))."',
								'".($medida['incluyeimpuesto'] == '2' ? 'N' : 'S')."',
								'".$medida['despacho']."'
								"
					);
			$compaux = $apiAnita->apiCall($data);
			
			if (strpos($compaux, 'Error') !== false)
				return 'Error';
				
			// Graba stkmov
			$apiAnita = new ApiAnita();

			// Lee tasa impuesto del item
			$impuesto = Impuesto::findOrFail($medida['impuesto_id']);

			$tasa = 1;
			if ($impuesto)
				$tasa = $impuesto->valor;

			// Si el precio tiene iva incluido lo netea
			if ($medida['incluyeimpuesto'] == '1')
				$precio = $medida['precio'] / (1 + ($tasa/100));
			else	
				$precio = $medida['precio'];

			if (isset($medida['deposito']))
				$deposito = $medida['deposito'];
			else	
				$deposito = 1;

			if ($ifx_server == 'IFX_SERVER_LOCAL')
				$deposito = 10;

			if ($flGrabaStock)
			{
				$data = array( 	'tabla' => 'stkmov', 
							'acc' => 'insert',
							'campos' => ' 
								stkv_articulo, stkv_agrupacion, stkv_fecha, 
								stkv_tipo, stkv_letra, stkv_sucursal, stkv_nro, 
								stkv_ref_tipo, stkv_ref_sucursal, stkv_ref_nro,
								stkv_deposito, stkv_cantidad, stkv_precio, stkv_cod_mon,
								stkv_cod_impuesto, stkv_descuento, stkv_dto_gral, stkv_comision,
								stkv_nro_orden, stkv_cli_pro, stkv_vendedor, stkv_zona_vta,
								stkv_zona_mult, stkv_subzona, stkv_comprador, stkv_partida, stkv_pedido,
								stkv_usuario, stkv_terminal, stkv_fe_ult_act, stkv_cod_entrega,
								stkv_cod_umd, stkv_unidad_xenv, stkv_cod_umd_alter, stkv_cant_unidad, 
								stkv_color
								 ',
							'valores' => "
								'".str_pad($medida['sku'], 13, "0", STR_PAD_LEFT)."',
								'".str_pad($medida['categoria'], 4, "0", STR_PAD_LEFT)."',
								'".date('Ymd', strtotime($venta['fecha']))."',
								'".substr($venta['codigo'], 0, 3)."',
								'".$letra."',
								'".$puntoventa."',
								'".$venta['numerocomprobante']."',
								'".' '."',
								'".'0'."',
								'".'0'."',
								'".$deposito."',
								'".$medida['cantidad']."',
								'".$precio."',
								'".$venta['moneda_id']."',
								'".$medida['impuesto_id']."', 
								'".($this->descuentoLinea == null || $letra == 'E'? 0 : $this->descuentoLinea)."',
								'".($this->descuentoPie == null ? 0 : $this->descuentoPie)."',
								'".'0'."',
								'".$orden."',
								'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
								'".$vendedor."',
								'".($zonavta_id == null ? '0' : $zonavta_id)."',
								'".($provincia_id == null ? '0' : $provincia_id)."',
								'".($subzonavta_id == null ? '0' : $subzonavta_id)."',
								'".'0'."',
								'".($ifx_server == 'IFX_SERVER_LOCAL' ? $medida['medida'] : $medida['partida'])."',
								'".substr($medida['pedido'],-8)."',
								'".Auth::user()->nombre."',
								'".'ERP'."',
								'".date_format(Carbon::now(), 'Ymd')."',
								'".'0'."',
								'".'0'."',
								'".'0'."',
								'".'0'."',
								'".'0'."',
								'".$medida['codigocombinacion']."'
								"
				);

				if ($servidor != null)
				{
					$data['servidor'] = $servidor;
					$data['ifx_server'] = $ifx_server;
				}

				$stkmov = $apiAnita->apiCall($data);
				if (strpos($stkmov, 'Error') !== false)
					return 'Error';

				// Graba stkvmed
				$data = array( 	'tabla' => 'stkvmed', 
							'acc' => 'insert',
							'campos' => ' 
								stkvm_articulo, stkvm_agrupacion, stkvm_fecha, 
								stkvm_tipo, stkvm_letra, stkvm_sucursal, stkvm_nro, 
								stkvm_nro_orden, stkvm_deposito, stkvm_cli_pro, stkvm_vendedor,
								stkvm_zona_vta, stkvm_zona_mult, stkvm_subzona_vta, stkvm_comprador,
								stkvm_partida, stkvm_medida, stkvm_marca, stkvm_linea, stkvm_cantidad,
								stkvm_color
								',
							'valores' => "
								'".str_pad($medida['sku'], 13, "0", STR_PAD_LEFT)."',
								'".str_pad($medida['categoria'], 4, "0", STR_PAD_LEFT)."',
								'".date('Ymd', strtotime($venta['fecha']))."',
								'".substr($venta['codigo'], 0, 3)."',
								'".$letra."',
								'".$puntoventa."',
								'".$venta['numerocomprobante']."',
								'".$orden."',
								'".$deposito."',
								'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."',
								'".$vendedor."',
								'".($zonavta_id == null ? '0' : $zonavta_id)."',
								'".($provincia_id == null ? '0' : $provincia_id)."',
								'".($subzonavta_id == null ? '0' : $subzonavta_id)."',
								'".'0'."',
								'".($ifx_server == 'IFX_SERVER_LOCAL' ? $medida['medida'] : $medida['partida'])."',
								'".$medida['medida']."',
								'".'0'."',
								'".'0'."',
								'".$medida['cantidad']."',
								'".$medida['codigocombinacion']."'
								"
				);
				if ($servidor != null)
				{
					$data['servidor'] = $servidor;
					$data['ifx_server'] = $ifx_server;
				}

				$stkvmed = $apiAnita->apiCall($data);
				if (strpos($stkvmed, 'Error') !== false)
					return 'Error';
			}
		}
		// Graba leyenda de exportacion
		if ($this->leyendaExportacion != '')
		{
			// Graba compaux
			$orden = 0;
			$leyendas = explode("\n", $this->leyendaExportacion);
			foreach($leyendas as $renglon)
			{
				$orden++;

				$apiAnita = new ApiAnita();
				
				$data = array( 	'tabla' => 'compley', 
								'acc' => 'insert',
								'campos' => ' 
									compl_tipo, compl_letra, compl_sucursal, compl_nro, 
									compl_orden, compl_leyenda ',
								'valores' => "
									'".substr($venta['codigo'], 0, 3)."',
									'".$letra."',
									'".$puntoventa."',
									'".$venta['numerocomprobante']."',
									'".$orden."',
									'".$renglon."'
									"
						);
				$compley = $apiAnita->apiCall($data);

				if (strpos($compley, 'Error') !== false)
					return 'Error';
			}
		}

		$totalVentaNeta = $dataCAE['exento']+$dataCAE['nogravado']+$dataCAE['gravado'];

		// Lee numero de operacion
		$numeroOperacion = Self::leeNumeroOperacionSubdiario();

		// Arma detalle
		$detalle = substr($venta['codigo'], 0, 3)." ".$letra." ".$puntoventa."-".
					$venta['numerocomprobante'];

		// Graba subdiario
		$apiAnita = new ApiAnita();
		$data = array( 	'tabla' => 'subdiario', 
						'acc' => 'insert',
						'campos' => ' 
									subd_sistema, subd_fecha, subd_tipo, subd_letra, subd_sucursal, subd_nro,
									subd_emisor, subd_tipo_mov, subd_cuenta, subd_contrapartida,
									subd_nro_operacion, subd_ref_tipo, subd_ref_letra, subd_ref_sucursal,
									subd_ref_nro, subd_ref_sistema, subd_importe, subd_cod_mon,
									subd_cotizacion, subd_desc_mov, subd_nro_asiento,
									subd_procesado, subd_ccosto_cta, subd_ccosto_con,
									subd_nro_interno
								',
						'valores' => "
									'".'V'."',
									'".date('Ymd', strtotime($venta['fecha']))."',
									'".substr($venta['codigo'], 0, 3)."',
									'".$letra."',
									'".$puntoventa."',
									'".$venta['numerocomprobante']."',
									'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."',
									'".($signo == -1 ? 'H' : 'D')."',
									'".$contrapartida."',
									'".$cuentaVenta."',
									'".$numeroOperacion."',
									'".substr($venta['codigo'], 0, 3)."',
									'".$letra."',
									'".$puntoventa."',
									'".$venta['numerocomprobante']."',
									'".'V'."',
									'".$totalVentaNeta."',
									'".$venta['moneda_id']."',
									'".'1'."',
									'".$detalle."',
									'".'0'."',
									'".' '."',
									'".'0'."',
									'".'0'."',
									'".'0'."'
									"
					);

		$subdiario = $apiAnita->apiCall($data);
		if (strpos($subdiario, 'Error') !== false)
			return 'Error';
			
		// Barre por cada impuesto para grabar asiento contable
		foreach ($conceptostotales as $conc)
		{
			// Graba solo los importes distintos a 0
			if ($conc['importe'] != 0)
			{
				$total = $conc['importe'];
				$cuenta = '';
				// Ingresos brutos
				if (strpos($conc['concepto'], 'Perc.') !== false &&
					array_key_exists('provincia_id', $conc))
				{
					if ($conc['provincia_id'] == 1)
						$cuenta = '213100016'; // CABA

					if ($conc['provincia_id'] == 2)
						$cuenta = '213100006'; // ARBA
				}
				
				// Percepcion iva
				if (strpos($conc['concepto'], 'IVA') !== false)
					$cuenta = '213100001';
				
				// Iva
				if (strpos($conc['concepto'], 'Iva') !== false)
					$cuenta = '213100001';

				// Arma detalle
				$detalle = substr($venta['codigo'], 0, 3)." ".$letra." ".$puntoventa."-".
							$venta['numerocomprobante'];
				
				if ($cuenta != '')
				{
					// Graba subdiario
					$apiAnita = new ApiAnita();

					$data = array( 	'tabla' => 'subdiario', 
							'acc' => 'insert',
							'campos' => ' 
										subd_sistema, subd_fecha, subd_tipo, subd_letra, subd_sucursal, subd_nro,
										subd_emisor, subd_tipo_mov, subd_cuenta, subd_contrapartida,
										subd_nro_operacion, subd_ref_tipo, subd_ref_letra, subd_ref_sucursal,
										subd_ref_nro, subd_ref_sistema, subd_importe, subd_cod_mon,
										subd_cotizacion, subd_desc_mov, subd_nro_asiento,
										subd_procesado, subd_ccosto_cta, subd_ccosto_con,
										subd_nro_interno
									',
							'valores' => "
							'".'V'."',
							'".date('Ymd', strtotime($venta['fecha']))."',
							'".substr($venta['codigo'], 0, 3)."',
							'".$letra."',
							'".$puntoventa."',
							'".$venta['numerocomprobante']."',
							'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."',
							'".($signo == -1 ? 'H' : 'D')."',
							'".$contrapartida."',
							'".$cuenta."',
							'".$numeroOperacion."',
							'".substr($venta['codigo'], 0, 3)."',
							'".$letra."',
							'".$puntoventa."',
							'".$venta['numerocomprobante']."',
							'".'V'."',
							'".$total."',
							'".$venta['moneda_id']."',
							'".'1'."',
							'".$detalle."',
							'".'0'."',
							'".' '."',
							'".'0'."',
							'".'0'."',
							'".'0'."'
							"
					);
					$subdiario = $apiAnita->apiCall($data);

					if (strpos($subdiario, 'Error') !== false)
						return 'Error';
				}
			}
		}

		// Numera la factura
		if ($this->ventaRepository->numeraAnita(substr($venta['codigo'], 0, 3), $letra, $puntoventa) == 'Error')
			return 'Error';

		// Numera el remito
		if ($this->ventaRepository->numeraAnita('REM', 'R', $puntoventaremito) == 'Error')
			return 'Error';

		return 'Success';
	}

	private function calculaCondicionVenta($fecha, $total, $condicionventa_id) : array
	{
		$condicionventa = Condicionventa::with('condicionventacuotas')->where('id', $condicionventa_id)->first();

		$cuotas = [];
		foreach($condicionventa->condicionventacuotas as $cuota)
		{
			switch($cuota->tipoplazo)
			{
			case 'D':
				$fechaVencimiento = date('Y-m-d', strtotime($fecha."+ ".$cuota->plazo." days"));
				break;
			case 'F':
				$fechaVencimiento = $cuota->fechavencimiento;
			case 'O':
			}
			$totalCuota = $total * $cuota->porcentaje / 100. * (1. + ($cuota->interes / 100));

			$cuotas[] = [
						'fechavencimiento' => $fechaVencimiento,
						'total' => $totalCuota
			];
		}
		return $cuotas;
	}

	private function leeVendedor($cliente, $marca)
	{
		$apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 
			'tabla' => 'clicomi',
            'campos' => '
                clico_cliente,
                clico_marca,
				clico_vendedor
            ' , 
            'whereArmado' => " WHERE clico_cliente='".$cliente."' and clico_marca = '".$marca."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		if (isset($dataAnita[0]))
			return $dataAnita[0]->clico_vendedor;
		
		return 0;
	}

	// Busca si existe la factura
	private function buscaVentaAnita($tipo, $letra, $puntoventa, $numero)
	{
		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'tabla' => 'venta', 
						'campos' => '
							ven_nro
						' ,
						'whereArmado' => " WHERE ven_tipo = '".$tipo."' AND
												ven_letra = '".$letra."' AND
												ven_sucursal = '".$puntoventa."' AND
												ven_nro = '".$numero."'
						" );
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (count($dataAnita) > 0)
			return $dataAnita[0]->ven_nro;
		
		return 0;
	}

	// Borra factura en Anita
	public function borraAnita($tipo, $letra, $puntoventa, $numero)
	{
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'venta', 
						'whereArmado' => " WHERE ven_tipo = '".$tipo."' AND
												ven_letra = '".$letra."' AND
												ven_sucursal = '".$puntoventa."' AND
												ven_nro = '".$numero."'
						" );
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'venibr', 
						'whereArmado' => " WHERE veni_tipo = '".$tipo."' AND
												veni_letra = '".$letra."' AND
												veni_sucursal = '".$puntoventa."' AND
												veni_nro = '".$numero."'
						" );
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'vencae', 
						'whereArmado' => " WHERE venc_tipo = '".$tipo."' AND
												venc_letra = '".$letra."' AND
												venc_sucursal = '".$puntoventa."' AND
												venc_nro = '".$numero."'
						" );
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'climov', 
						'whereArmado' => " WHERE cliv_tipo = '".$tipo."' AND
												cliv_letra = '".$letra."' AND
												cliv_sucursal = '".$puntoventa."' AND
												cliv_nro = '".$numero."'
						" );
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'comprob', 
						'whereArmado' => " WHERE comp_tipo = '".$tipo."' AND
												comp_letra = '".$letra."' AND
												comp_sucursal = '".$puntoventa."' AND
												comp_nro_fact = '".$numero."'
						" );
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'compaux', 
						'whereArmado' => " WHERE compa_tipo = '".$tipo."' AND
												compa_letra = '".$letra."' AND
												compa_sucursal = '".$puntoventa."' AND
												compa_nro_fact = '".$numero."'
						" );
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'compley', 
						'whereArmado' => " WHERE compl_tipo = '".$tipo."' AND
												compl_letra = '".$letra."' AND
												compl_sucursal = '".$puntoventa."' AND
												compl_nro = '".$numero."'
						" );
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'stkmov', 
						'whereArmado' => " WHERE stkv_tipo = '".$tipo."' AND
												stkv_letra = '".$letra."' AND
												stkv_sucursal = '".$puntoventa."' AND
												stkv_nro = '".$numero."'
						" );
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'stkvmed', 
						'whereArmado' => " WHERE stkvm_tipo = '".$tipo."' AND
												stkvm_letra = '".$letra."' AND
												stkvm_sucursal = '".$puntoventa."' AND
												stkvm_nro = '".$numero."'
						" );
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'subdiario', 
						'whereArmado' => " WHERE subd_sistema='V' AND subd_tipo = '".$tipo."' AND
												subd_letra = '".$letra."' AND
												subd_sucursal = '".$puntoventa."' AND
												subd_nro = '".$numero."'
						" );
        $apiAnita->apiCall($data);
	}

	public function grabaVenCae($tipo, $letra, $puntoventa, $numerocomprobante, $cae, $fechavencimientocae)
	{
		// Graba cae en anita
		$apiAnita = new ApiAnita();

		$data = array( 	'tabla' => 'vencae', 
						'acc' => 'insert',
						'campos' => ' 
							venc_tipo, venc_letra, venc_sucursal, venc_nro, venc_nro_cae, venc_fecha_vto,
							venc_nro_id, venc_nro_sec ',
						'valores' => "
							'".$tipo."',
							'".$letra."',
							'".$puntoventa."',
							'".$numerocomprobante."',
							'".$cae."',
							'".$fechavencimientocae."',
							'".'1'."',
							'".'1'."'
						"
				);
		$vencae = $apiAnita->apiCall($data);

		if (strpos($vencae, 'Error') !== false)
			return 'Error';

		return 'Success';
	}

	public function leeFactura($id)
	{
		// Lee venta
		$venta = $ventaRepository->find($id);

		// Lee items
	}

	public function leeNumeroOperacionSubdiario()
	{
		// Lee numero de operacion
		$apiAnita = new ApiAnita();
		$data = array( 
			'acc' => 'list', 
			'tabla' => 'numerador', 
			'campos' => '
				num_ult_numero
			' , 
			'whereArmado' => " WHERE num_clave='500' " 
		);
		$dataAnita = json_decode($apiAnita->apiCall($data));

		$numeroOperacion = $dataAnita[0]->num_ult_numero + 1;

		// Actualiza numero
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 
					'tabla' => 'numerador', 
					'valores' => 
						" num_ult_numero = '".$numeroOperacion."' ", 
					'whereArmado' => " WHERE num_clave = '500' " );
		$numerador = $apiAnita->apiCall($data);

		return $numeroOperacion;
	}
}
