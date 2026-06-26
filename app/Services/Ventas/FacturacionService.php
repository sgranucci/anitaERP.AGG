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
use App\Repositories\Ventas\Pedido_ArticuloRepositoryInterface;
use App\Repositories\Ventas\Pedido_Combinacion_TalleRepositoryInterface;
use App\Repositories\Ventas\PedidoRepositoryInterface;
use App\Repositories\Ventas\OrdentrabajoRepositoryInterface;
use App\Repositories\Ventas\Ordentrabajo_Combinacion_TalleRepositoryInterface;
use App\Repositories\Ventas\Ordentrabajo_TareaRepositoryInterface;
use App\Repositories\Ventas\PuntoventaRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Repositories\Stock\Tipotransaccion_StockRepositoryInterface;
use App\Repositories\Ventas\VentaRepositoryInterface;
use App\Repositories\Ventas\Venta_EmisionRepositoryInterface;
use App\Repositories\Ventas\Venta_ImpuestoRepositoryInterface;
use App\Repositories\Ventas\Venta_ExportacionRepositoryInterface;
use App\Repositories\Ventas\Cliente_CuentacorrienteRepositoryInterface;
use App\Repositories\Ventas\Cliente_Cuentacorriente_AplicacionRepositoryInterface;
use App\Repositories\Ventas\Cliente_EntregaRepositoryInterface;
use App\Repositories\Ventas\TransporteRepositoryInterface;
use App\Repositories\Ventas\IncotermRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Repositories\Ventas\DescuentoventaRepositoryInterface;
use App\Services\Ordenventa\OrdenventaService;
use App\Repositories\Produccion\TareaRepositoryInterface;
use App\Repositories\Configuracion\CondicionivaRepositoryInterface;
use App\Repositories\Configuracion\Provincia_CuentacontableiibbRepositoryInterface;
use App\Repositories\Configuracion\Impuesto_CuentacontableRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Configuracion\ImpuestoRepositoryInterface;
use App\Repositories\Configuracion\ProvinciaRepositoryInterface;
use App\Repositories\Configuracion\Actividad_ArcaRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Stock\LoteRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
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
use App\Models\Stock\Mventa;
use App\Models\Stock\Depmae;
use App\Models\Stock\Modulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Contrafuerte;
use App\Models\Stock\Articulo_Caja;
use App\Models\Stock\Caja;
use App\Models\Ventas\Ordentrabajo;
use App\Models\Ventas\Copiaot;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\Condicionventacuota;
use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Moneda;
use App\Services\Stock\PrecioService;
use App\Services\Stock\Articulo_MovimientoService;
use App\Services\Configuracion\ImpuestoService;
use App\Services\Configuracion\CotizacionService;
use App\Services\Ventas\FacturaelectronicaService;
use App\Services\Stock\MovimientoStockService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LynX39\LaraPdfMerger\Facades\PdfMerger;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;
use App;
use Auth;
use DB;
use App\ApiAnita;
use App\Support\Ventas\GastronomiaEmisionProfiler;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Ventas\KandikoAnitaVentaTipoSupport;
use Exception;
use PDF;

class FacturacionService 
{
	protected $ordentrabajoQuery;
	protected $ordentrabajoRepository;
	protected $ordentrabajo_combinacion_talleRepository;
	protected $ordentrabajo_tareaRepository;
	protected $tareaRepository;
	protected $loteRepository;
	protected $pedido_combinacionRepository;
	protected $pedido_articuloRepository;
	protected $pedido_combinacion_talleRepository;
	protected $pedidoRepository;
	protected $ordenventaService;
	protected $cliente_cuentacorrienteRepository;
	protected $cliente_cuentacorriente_aplicacionRepository;
	protected $cliente_entregaRepository;
	protected $puntoventaRepository;
	protected $tipotransaccionRepository;
	protected $tipotransaccionStockRepository;
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
	protected $cotizacionService;
	protected $descuentoventaRepository;
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
	protected $provincia_cuentacontableiibbRepository;
	protected $impuesto_cuentacontableRepository;
	protected $cuentacontableRepository;
	protected $asientoRepository;
	protected $asiento_movimientoRepository;
	protected $tipoasientoRepository;
	protected $monedaRepository;
	protected $impuestoRepository;
	protected $provinciaRepository;
	protected $actividad_arcaRepository;
	protected $coeficienteCliente;
	protected $coeficienteExtraCliente;
	protected $flDivide;
	protected $flGrabaComprobanteDividido;
	protected $tasaImpuesto;
	protected $puntoVentaDivision_id;
	protected $numeroComprobanteDivision;
	protected $numeroRemito;
	protected $movimientoStockService;
	protected $flCalculaDesdeGeneracionFactura;

    public function __construct(
								OrdentrabajoQueryInterface $ordentrabajoquery,
								OrdentrabajoRepositoryInterface $ordentrabajorepository,
								Ordentrabajo_Combinacion_TalleRepositoryInterface $ordentrabajocombinaciontallerepository,
								Ordentrabajo_TareaRepositoryInterface $ordentrabajotarearepository,
								TareaRepositoryInterface $tarearepository,
								TipotransaccionRepositoryInterface $tipotransaccionrepository,
								Tipotransaccion_StockRepositoryInterface $tipotransaccionstockrepository,
								CondicionivaRepositoryInterface $condicionivarepository,
								PuntoventaRepositoryInterface $puntoventarepository,
								PedidoQueryInterface $pedidoquery,
								ClienteQueryInterface $clientequery,
								Cliente_ComisionQueryInterface $clientecomisionquery,
								ArticuloQueryInterface $articuloquery,
								ImpuestoService $impuestoservice,
								CotizacionService $cotizacionservice,
								DescuentoventaRepositoryInterface $descuentoventaRepository,
								Pedido_ArticuloRepositoryInterface $pedido_articulorepository,
    							Pedido_CombinacionRepositoryInterface $pedidocombinacionrepository,
    							Pedido_Combinacion_TalleRepositoryInterface $pedidocombinaciontallerepository,
								PedidoRepositoryInterface $pedidoRepository,
								OrdenventaService $ordenventaService,
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
								Cliente_Cuentacorriente_AplicacionRepositoryInterface $cliente_cuentacorriente_aplicacionrepository,
								LoteRepositoryInterface $loterepository,
								Cliente_EntregaRepositoryInterface $cliente_entregarepository,
								Provincia_CuentacontableiibbRepositoryInterface $provincia_cuentacontableiibbRepository,
								Impuesto_CuentacontableRepositoryInterface $impuesto_cuentacontableRepository,
								CuentacontableRepositoryInterface $cuentacontableRepository,
								AsientoRepositoryInterface $asientorepository,
								Asiento_MovimientoRepositoryInterface $asiento_movimientorepository,	
								TipoasientoRepositoryInterface $tipoasientorepository,						
								ImpuestoRepositoryInterface $impuestorepository,
								MonedaRepositoryInterface $monedarepository,
								ProvinciaRepositoryInterface $provinciaRepository,
								MovimientoStockService $movimientoStockservice,
								Actividad_ArcaRepositoryInterface $actividad_arcaRepository
								)
    {
        $this->ordentrabajoQuery = $ordentrabajoquery;
        $this->ordentrabajoRepository = $ordentrabajorepository;
        $this->ordentrabajo_combinacion_talleRepository = $ordentrabajocombinaciontallerepository;
        $this->ordentrabajo_tareaRepository = $ordentrabajotarearepository;
		$this->tareaRepository = $tarearepository;
		$this->tipotransaccionRepository = $tipotransaccionrepository;
		$this->tipotransaccionStockRepository = $tipotransaccionstockrepository;
		$this->condicionivaRepository = $condicionivarepository;
		$this->puntoventaRepository = $puntoventarepository;
        $this->pedidoQuery = $pedidoquery;
        $this->clienteQuery = $clientequery;
        $this->cliente_comisionQuery = $clientecomisionquery;
        $this->articuloQuery = $articuloquery;
		$this->precioService = $precioservice;
		$this->descuentoventaRepository = $descuentoventaRepository;
		$this->pedido_articuloRepository = $pedido_articulorepository;
        $this->pedido_combinacionRepository = $pedidocombinacionrepository;
		$this->pedidoRepository = $pedidoRepository;
		$this->ordenventaService = $ordenventaService;
		$this->impuestoService = $impuestoservice;
		$this->cotizacionService = $cotizacionservice;
		$this->facturaelectronicaService = $facturaelectronicaservice;
		$this->articulo_movimientoService = $articulo_movimientoservice;
        $this->pedido_combinacion_talleRepository = $pedidocombinaciontallerepository;
		$this->ventaRepository = $ventarepository;
		$this->venta_emisionRepository = $venta_emisionrepository;
		$this->venta_impuestoRepository = $venta_impuestorepository;
		$this->venta_exportacionRepository = $venta_exportacionrepository;
		$this->cliente_cuentacorrienteRepository = $cliente_cuentacorrienterepository;
		$this->cliente_cuentacorriente_aplicacionRepository = $cliente_cuentacorriente_aplicacionrepository;
		$this->transporteRepository = $transporterepository;
		$this->incotermRepository = $incotermrepository;
		$this->formapagoRepository = $formapagorepository;
		$this->loteRepository = $loterepository;
		$this->cliente_entregaRepository = $cliente_entregarepository;
		$this->provincia_cuentacontableiibbRepository = $provincia_cuentacontableiibbRepository;
		$this->impuesto_cuentacontableRepository = $impuesto_cuentacontableRepository;
		$this->cuentacontableRepository = $cuentacontableRepository;
		$this->asientoRepository = $asientorepository;
		$this->asiento_movimientoRepository = $asiento_movimientorepository;	
		$this->tipoasientoRepository = $tipoasientorepository;	
		$this->monedaRepository = $monedarepository;	
		$this->impuestoRepository = $impuestorepository;
		$this->provinciaRepository = $provinciaRepository;
		$this->actividad_arcaRepository = $actividad_arcaRepository;
		$this->movimientoStockService = $movimientoStockservice;

		$this->coeficienteCliente = 0;
		$this->coeficienteExtraCliente = 0;
		$this->flDivide = false;
		$this->flGrabaComprobanteDividido = false;
		$this->tasaImpuesto = 0;
		$this->puntoVentaDivision_id = 0;
		$this->numeroComprobanteDivision = 0;
		$this->numeroRemito = 0;
		$this->flCalculaDesdeGeneracionFactura = false;
    }

	public function leePaginando($busqueda)
    {
        return $this->ventaRepository->leePaginando($busqueda);
    }

	public function leeSinPaginar($busqueda)
    {
        return $this->ventaRepository->leeSinPaginar($busqueda);
    }

	private function clienteTieneLugaresEntrega(int $clienteId): bool
	{
		return $this->cliente_entregaRepository->leeClienteEntrega($clienteId)->count() > 0;
	}

	private function sincronizarLugarEntregaPedido($pedido): void
	{
		if ($pedido->lugarentrega == null && (int) ($pedido->cliente_entrega_id ?? 0) > 0) {
			$cliente_entrega = $this->cliente_entregaRepository->find($pedido->cliente_entrega_id);

			if ($cliente_entrega) {
				$pedido->lugarentrega = $cliente_entrega->nombre;
			}
		}
	}

	private function provinciaPercepcionDesdePedido($cliente, $pedido): ?int
	{
		if ($pedido && (int) ($pedido->cliente_entrega_id ?? 0) > 0) {
			$clienteEntrega = $this->cliente_entregaRepository->find($pedido->cliente_entrega_id);

			if ($clienteEntrega && $clienteEntrega->provincia_id) {
				return (int) $clienteEntrega->provincia_id;
			}
		}

		return $cliente->provincia_id;
	}

	private function validarLugarEntregaPedido($cliente, $pedido): ?array
	{
		return \App\Support\Ventas\ClienteEntregaPedidoSupport::validarPedido($cliente, $pedido);
	}

	// Calcula la factura por pedido

	public function calculaFacturaPorPedido(array $data)
	{
		// Recibe datos para facturar
		$pedido_articulo_ids = $data['pedido_articulo_ids'];

		$cliente_id = $data['cliente_id'];

		$this->descuentoPie = $data['descuentopie'];
		$this->descuentoLinea = 0;
		$this->descuentoImportePie = $data['descuentoimportepie'];
		$this->incoterm_id = $data['incoterm_id'];
		$fechaFactura = $data['fechafactura'];

		// Trae el cliente
		$cliente = $this->clienteQuery->traeClienteporId($cliente_id);

		if (!$cliente)
			return ['error' => 'Cliente inexistente'];
		
		if ($cliente->numerodocumento == null)
			return ['error' => 'No tiene Documento'];
			
		// Saca letra del comprobante
		$condicioniva = $this->condicionivaRepository->find($cliente->condicioniva_id);
		$letra = 'Z';
		if ($condicioniva)
			$letra = $condicioniva->letra;

		// Trae el pedido
		$pedido_query = $this->pedidoQuery->leePedidoporId($data['pedido_id']);

		if (!$pedido_query)
			return ['error' => 'Pedido inexistente'];
		else	
			$pedido = $pedido_query[0];

		$errorEntrega = $this->validarLugarEntregaPedido($cliente, $pedido);
		if ($errorEntrega) {
			return $errorEntrega;
		}

		$this->sincronizarLugarEntregaPedido($pedido);

		// Lee los items a facturar
		$dataFactura = [];
		$totKilo = 0;

		for ($offItem = 0; $offItem < count($pedido_articulo_ids); $offItem++)
		{
			$pedido_articulo_id = $pedido_articulo_ids[$offItem];
		
			$pedido_articulo = $this->pedido_articuloRepository->find($pedido_articulo_id);

			if ($pedido_articulo->estado == 'P')
			{
				// Trae el articulo
				$articulo = $this->articuloQuery->traeArticuloPorId($pedido_articulo->articulo_id);

				if (!$articulo)
					return ['error' => 'Artículo inexistente'];

				$numeroItem = (int) ($pedido_articulo->numeroitem ?? ($offItem + 1));
				$errorListaprecio = \App\Support\Ventas\ArticuloListaprecioLineaVentasSupport::validarPedidoArticuloPersistido(
					$pedido_articulo->listaprecio_id,
					$numeroItem,
					trim((string) ($articulo->sku ?? '')) !== '' ? (string) $articulo->sku : (string) $articulo->descripcion,
				);
				if ($errorListaprecio !== null) {
					return $errorListaprecio;
				}

				if ($pedido_articulo->pesada == 0)
					return ['error' => 'Artículo '.$articulo->sku.' sin pesar'];
				
				$moneda_id = $pedido_articulo->moneda_id;
				
				// Trae la categoria
				$categoria = Categoria::find($articulo->categoria_id);
				$codigoCategoria = '';
				if ($categoria)
					$codigoCategoria = $categoria->codigo;

				// Lee el descuento 
				$this->descuentoLinea = 0.;
				if ($pedido_articulo->descuentoventa_id > 0)
				{
					$descuentoventa = $this->descuentoventaRepository->find($pedido_articulo->descuentoventa_id);

					if ($descuentoventa)
						$this->descuentoLinea = $descuentoventa->porcentajedescuento;
				}

				$precioUnitario = $pedido_articulo->precio;

				// Si esta calculando factura para la pre-factura y es reparto 101 calcula dividiendo
				if (config('app.empresa') == "EL BIERZO" &&
					!$this->flCalculaDesdeGeneracionFactura &&
					$pedido->transportes->tipoexpreso == '4')
				{
					$this->flDivide = true;
					$this->flGrabaComprobanteDividido = true;
					$this->coeficienteCliente = 100.;

					if ($pedido->transportes->tipoexpreso == '4') // Genera solo remito
						$this->coeficienteExtraCliente = config('facturacion.COEFICIENTE_EXTRA_REPARTO_101');
					else
						$this->coeficienteExtraCliente = $cliente->coeficienteextra;
				}

				if ($this->flDivide)
				{
					$decimal = config('facturacion.DECIMAL_CANTIDAD');

					$coeficienteDivision = $this->coeficienteCliente;

					// Si el articulo no se divide cambia el coeficiente
					if ($articulo->divide == 'NO DIVIDE')
						$coeficienteDivision = 0;

					// Graba en Villafranca
					if ($this->flGrabaComprobanteDividido)
					{
						if ($this->coeficienteExtraCliente != 0)
							$precioUnitario = $pedido_articulo->precio * $this->coeficienteExtraCliente;

						$kilo = round($pedido_articulo->pesada * $coeficienteDivision / 100., $decimal);
						$pieza = round($pedido_articulo->pieza * $coeficienteDivision / 100., $decimal);
						$caja = round($pedido_articulo->caja * $coeficienteDivision / 100., $decimal);
					}
					else // Deja el resto para grabar en Bierzo
					{
						$coeficiente = ((100. - $coeficienteDivision)/100.);

						$kilo = round($pedido_articulo->pesada * $coeficiente, $decimal);
						$pieza = round($pedido_articulo->pieza * $coeficiente, $decimal);
						$caja = round($pedido_articulo->caja * $coeficiente, $decimal);
					}
				}
				else
				{
					$kilo = $pedido_articulo->pesada;
					$pieza = $pedido_articulo->pieza;
					$caja = $pedido_articulo->caja;
				}

				if ($this->descuentoLinea != 0)
					$precioConDescuento = round($precioUnitario * (1. - ($this->descuentoLinea / 100.)), 2);
				else
					$precioConDescuento = $precioUnitario;

				if ($kilo != 0)
				{
					// Calcula los kilos sin descuento y el descuento
					$kiloDescuento = $kilo;
					if (config('app.empresa') == 'EL BIERZO')
					{
						if ($this->descuentoLinea != 0)
						{
							$kiloDescuento = round($kilo * (1. - ($this->descuentoLinea / 100.)), 1);	
						}
					}

					$dataFactura[] = ["cantidad" => $kilo,
						"kilodescuento" => $kiloDescuento,
						"pieza" => $pieza,
						"caja" => $caja,
						"preciosindescuento" => $precioUnitario,
						"precio" => $precioConDescuento,
						"descuento" => $this->descuentoLinea,
						"descuentointegrado" => '',
						"descuentofinal" => $this->descuentoPie,
						"descuentointegradofinal" => '',
						"incluyeimpuesto" => $pedido_articulo->incluyeimpuesto,
						"impuesto_id" => $articulo->impuesto_id,
						"articulo_id" => $articulo->id,
						"sku" => $articulo->sku,
						"descripcion" => $articulo->descripcion,
						"codigounidadmedida" => $articulo->unidadesdemedidas->codigo ?? 1,
						'categoria' => $codigoCategoria,
						'moneda_id' => $moneda_id,
						'listaprecio_id' => $pedido_articulo->listaprecio_id,
						'despacho' => $this->numeroDespacho,
						'loteimportacion_id' => null,
						'pedido_articulo_id' => $pedido_articulo_id,
						'cuentacontable_id' => $articulo->cuentacontableventa_id,
					];
					$totKilo += $kilo;
				}
			}
		}
		// Arma datos del cliente
		$provinciaPercepcion = $this->provinciaPercepcionDesdePedido($cliente, $pedido);
		if (strtoupper(config('app.empresa') == "EL BIERZO"))
			$datosCliente = [ "condicioniva_id" => $cliente->condicioniva_id,
							"numerodocumento" => $cliente->numerodocumento,
							"retieneiva" => $cliente->retieneiva,
							"condicioniibb_id" => $cliente->condicioniibb_id,
							"provincia" => $provinciaPercepcion,
							"descuentoimportepie" => $this->descuentoImportePie,
							"id" => $cliente->id,
							"abasto_id" => $cliente->abasto_id,
							"porcentajelogistica" => $cliente->porcentajelogistica
							];
		else
			$datosCliente = [ "condicioniva_id" => $cliente->condicioniva_id,
							"numerodocumento" => $cliente->numerodocumento,
							"retieneiva" => $cliente->retieneiva,
							"condicioniibb_id" => $cliente->condicioniibb_id,
							"provincia" => $provinciaPercepcion,
							"descuentoimportepie" => $this->descuentoImportePie,
							"id" => $cliente->id
							];
		// Calcula impuestos
		$conceptosTotales = $this->impuestoService->calculaImpuestoVenta($dataFactura, $datosCliente, $fechaFactura, 
																			$this->flGrabaComprobanteDividido);

		// Arma total de comprobante
		$totalComprobante = $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Total', 'importe');

		if ($dataFactura === []) {
			return ['error' => 'No hay ítems para facturar: verifique que estén en estado pendiente y con pesada cargada.'];
		}

		if ($totalComprobante == 0.) {
			return ['error' => 'El total del comprobante es 0. Revise que los ítems del pedido tengan precio mayor a cero.'];
		}

		return ['datosfactura' => $dataFactura, 'datoscliente' => $datosCliente, 'totalcomprobante' => $totalComprobante,
				'conceptostotales' => $conceptosTotales];
	}

	public function generaFacturaPorPedido(array $data)
	{
		// Guarda tipo de transaccion y punto de venta en cache
		Cache::forever(generaKey('tipotransaccion'), $data['tipotransaccion_id']);
		Cache::forever(generaKey('puntoventa'), $data['puntoventa_id']);
		Cache::forever(generaKey('puntoventaremito'), $data['puntoventaremito_id']);

		$cliente_id = $data['cliente_id'];
		$tipoTransaccion_id = $data['tipotransaccion_id'];
		$puntoventa_id = $data['puntoventa_id'];
		$this->coeficienteCliente = 0;
		$this->coeficienteExtraCliente = 0;
		$this->flCalculaDesdeGeneracionFactura = true;

		// Trae el cliente 
		$cliente = $this->clienteQuery->traeClienteporId($cliente_id);
		if (!$cliente)
			return ['error' => 'Cliente inexistente'];

		// Lee el tipo de transaccion
		$tipotransaccion = $this->tipotransaccionRepository->find($tipoTransaccion_id);

		// Trae el pedido
		$pedido_query = $this->pedidoQuery->leePedidoporId($data['pedido_id']);

		if (!$pedido_query)
			return ['error' => 'Pedido inexistente'];
		else	
			$pedido = $pedido_query[0];

		$errorEntrega = $this->validarLugarEntregaPedido($cliente, $pedido);
		if ($errorEntrega) {
			return $errorEntrega;
		}

		$retorno = null;

		// Controla si divide factura
		if (($pedido->transportes->tipoexpreso == '4' || $pedido->transportes->tipoexpreso == '3') && 
			($tipotransaccion->codigo == '001' || $tipotransaccion->codigo == '201'))
		{
			if ($pedido->transportes->tipoexpreso == '4') // Genera solo remito
				$this->coeficienteExtraCliente = config('facturacion.COEFICIENTE_EXTRA_REPARTO_101');
			else
				$this->coeficienteExtraCliente = $cliente->coeficienteextra;

			if (isset($cliente->coeficientes))
			{
				$this->flDivide = true;
				$this->flGrabaComprobanteDividido = false;

				if ($pedido->transportes->tipoexpreso == '4') // Reparto 101 con remito en bierzo
					$this->coeficienteCliente = 100.;
				else
					$this->coeficienteCliente = $cliente->coeficientes->porcentajedivision;
				$this->tasaImpuesto = $cliente->coeficientes->tasa;

				// Si no es toda dividida genera factura por el resto en el Bierzo
				if ($this->coeficienteCliente < 100)
				{
					$this->puntoVentaDivision_id = 0;
					$retorno1 = Self::generaUnaFacturaPorPedido($data, $cliente, $pedido);

					// Cambia punto de venta de Villa
					$this->puntoVentaDivision_id = config('facturacion.PUNTOVENTA_DIVISION_ID');
					$data['puntoventa_id'] = $this->puntoVentaDivision_id;
				}
				elseif ($this->coeficienteCliente == 100)
				{
					// Cambia punto de venta si es solo Villa al 100%
					$this->puntoVentaDivision_id = config('facturacion.PUNTOVENTA_DIVISION_LOCAL_ID');
					$data['puntoventa_id'] = $this->puntoVentaDivision_id;
					$this->puntoventaremito_id = $data['puntoventaremito_id'];

					// Lee punto de venta del remito
					$puntoventaremito = null;
					if ($this->puntoventaremito_id >= 1)
						$puntoventaremito = $this->puntoventaRepository->find($this->puntoventaremito_id);

					// Genera remito
					$retorno1 = Self::generaUnRemito($data, $cliente, $pedido, config('facturacion.TIPO_REMITO'), 
						config('facturacion.LETRA_REMITO'), $puntoventaremito->codigo, $puntoventaremito->empresas->codigo);

					// Cambia punto de venta de Villa
					$this->puntoVentaDivision_id = config('facturacion.PUNTOVENTA_DIVISION_ID');
					$data['puntoventa_id'] = $this->puntoVentaDivision_id;						
				}

				$this->flGrabaComprobanteDividido = true;

				// Graba comprobante dividido
				$retorno2 = Self::generaUnaFacturaPorPedido($data, $cliente, $pedido);

				$retorno = [$retorno1, $retorno2];
			}
		}

		if ($retorno === null) {
			$this->flGrabaComprobanteDividido = false;
			$this->flDivide = false;

			$retorno = [Self::generaUnaFacturaPorPedido($data, $cliente, $pedido)];
		}

		return $retorno;
	}

	public function generaUnaFacturaPorPedido(array $data, $cliente, $pedido)
	{
		// Recibe datos para facturar
		$pedido_articulo_ids = $data['pedido_articulo_ids'];

		$cliente_id = $data['cliente_id'];
		$puntoventa_id = $data['puntoventa_id'];
		$tipoTransaccion_id = $data['tipotransaccion_id'];
		$fechaFactura = $data['fechafactura'];
		$leyenda = $data['leyendafactura'];
		$actividad_arca_id = $data['actividad_arca_id'];
		$pedido_id = $data['pedido_id'];

		if (isset($data['deposito']))
			$deposito = $data['deposito'];
		else
			$deposito = 1;

		$this->descuentoPie = $data['descuentopie'];
		$this->descuentoLinea = 0;
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

		$this->cuentacontable_id = $cliente->cuentacontable_id;
		$this->codigoCuentaContable = $cliente->cuentascontables?->codigo ?? '';

		if (isset($cliente->condicionventa_id))
			$condicionventa_id = $cliente->condicionventa_id;
		else
			$condicionventa_id = null;

		// Lee el tipo de transaccion
		$tipotransaccion = $this->tipotransaccionRepository->find($tipoTransaccion_id);

		// Recalcula la factura
		$calculoFactura = Self::calculaFacturaPorPedido($data);

		if (! is_array($calculoFactura) || isset($calculoFactura['error'])) {
			return ['error' => $calculoFactura['error'] ?? 'No se pudo calcular la factura del pedido.'];
		}

		if (empty($calculoFactura['datosfactura'])) {
			return ['error' => 'No hay datos para facturar el pedido.'];
		}

		$dataFactura = $calculoFactura['datosfactura'];
		$conceptosTotales = $calculoFactura['conceptostotales'];
		$datosCliente = $calculoFactura['datoscliente'];
		$totalComprobante = $calculoFactura['totalcomprobante'];
		$moneda_id = $calculoFactura['datosfactura'][0]['moneda_id'];
		$centrocosto_id = null;

		if ($totalComprobante == 0.)
			return ['error' => 'Factura en 0'];

		$cotizacion = $this->cotizacionService->calculaCotizacionVenta($fechaFactura, $moneda_id);

		$this->sincronizarLugarEntregaPedido($pedido);
		$provinciaPercepcion = $this->provinciaPercepcionDesdePedido($cliente, $pedido);

		// Saca letra del comprobante
		$condicioniva = $this->condicionivaRepository->find($cliente->condicioniva_id);
		$letra = 'Z';
		if ($condicioniva)
			$letra = $condicioniva->letra;

		// Calcula vencimientos
		$cuentaCorriente = $this->calculaCondicionVenta($fechaFactura, 
														$totalComprobante, 
														$pedido->condicionventa_id);

		// Saca letra del comprobante
		$condicioniva = $this->condicionivaRepository->find($cliente->condicioniva_id);
		$letra = 'Z';
		if ($condicioniva)
			$letra = $condicioniva->letra;

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
			$empresa_id = $puntoventa->empresa_id;

			// Lee el tipo de transaccion
			$tipotransaccion = $this->tipotransaccionRepository->find($tipoTransaccion_id);

			// Pide numero de factura
			$codigoTipoTransaccion = $tipotransaccion->codigo;
			$this->nombreTipoTransaccion = $tipotransaccion->nombre;
			$signo = $tipotransaccion->signo == 'S' ? 1. : -1.;

			if ($codigoTipoTransaccion >= '200')
				$tipoAnita = substr($tipotransaccion->abreviatura,0,1)+"CE";
			else
				$tipoAnita = $tipotransaccion->abreviatura;

			// Numera factura con web service si es factura electronica
			switch($puntoventa->modofacturacion)
			{
			case 'C':
			case 'E':
				$this->facturaelectronicaService->armaTipoTransaccion($letra, $cliente->modoFacturacion, $codigoTipoTransaccion,
																		$puntoventa, $totalComprobante);

				$numero = $this->facturaelectronicaService
							->traeUltimoNumeroComprobante($empresa->nroinscripcion,
															$codigoTipoTransaccion,
															$puntoventa);
				break;
			case 'A':
				$numero = Self::buscaUltimoNumeroComprobante($tipoAnita, $letra, $puntoventa);
				break;
			case 'M':
				$venta = $this->ventaRepository->traeUltimoComprobanteVenta(
					$tipoTransaccion_id,
					$puntoventa_id,
					(int) ($puntoventa->empresa_id ?? 0) ?: null,
				);
				if ($venta)
					$numero = $venta->numerocomprobante;
				else	
					$numero = 0;
				break;
			}

			if ($numero != -1)
			{
				$numero++;

				// Pide numero de remito
				if ($this->flDivide)
				{
					if (!$this->flGrabaComprobanteDividido)
					{
						// Guarda numero del comprobante
						$this->numeroComprobanteDivision = $numero;

						if ($puntoventaremito && $puntoventa->modofacturacion != 'M')
							$numeroremito = $this->ventaRepository->traeUltimoNumeroRemito('REM','R',$puntoventaremito->codigo);
						else	
							$numeroremito = 0;

						$this->numeroRemito = $numeroremito;					
					}
					else
					{
						// Si hay punto de venta de division (villa) es porque graba al 100% en Villa generando factura y remito
						if ($this->puntoVentaDivision_id != 0)
						{
							// Genera numero de Remito							
							if ($puntoventaremito && $puntoventa->modofacturacion != 'M')
								$numeroremito = $this->ventaRepository->traeUltimoNumeroRemito('REM','R',$puntoventaremito->codigo);
							else	
								$numeroremito = 0;

						}
						else // Mantiene numeros de Bierzo
						{
							$numeroremito = $this->numeroRemito;
							$numero = $this->numeroComprobanteDivision;
						}
					}
				}
				else
				{
					if ($puntoventaremito && $puntoventa->modofacturacion != 'M')
						$numeroremito = $this->ventaRepository->traeUltimoNumeroRemito('REM','R',$puntoventaremito->codigo);
					else	
						$numeroremito = 0;
				}

				// Procesa Factura electronica
				if ($puntoventa->modofacturacion != 'M' || $this->flGrabaComprobanteDividido)
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
							'codigoempresa' => $empresa->codigo,
							'tipodoc' => $cliente->tipodocumentos->codigoexterno,
							'numerodocumento' => $cliente->numerodocumento,
							'condicioniva_id' => $cliente->condicioniva_id,
							'numerocomprobante' => $numero,
							'fechacomprobante' => date('Ymd', strtotime($fechaFactura)),
							'total' => $totalComprobante,
							'nogravado' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'No Gravado', 'importe'),
							'gravado' => \App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport::gravadoDesdeConceptosTotales($conceptosTotales, abs((float) $totalComprobante)),
							'exento' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Exento', 'importe'),
							'iva' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Iva ', 'importe'),
							'tributo' => $totalTributo,
							'fechavencimiento' => date('Ymd', strtotime($cuentaCorriente[0]['fechavencimiento'])),
							'moneda' => $codigoMoneda,
							'cotizacion' => 1,
							'tributos' => $tributos,
							'impuestos' => $impuestos,
							'comprobantesasociados' => $comprobantesAsociados,
							'fechaasignaciondesde' => date('Ymd', strtotime($fechaAsignacion)),
							'fechaasignacionhasta' => date('Ymd', strtotime($fechaFactura)),
							'pais' => $cliente->paises?->codigo ?? '',
							'nombrecliente' => $cliente->nombre,
							'domicilio' => $cliente->domicilio,
							'formapago' => $cliente->condicionventas->nombre ?? '',
							'formapagoexportacion' => $this->formaPagoExportacion,
							'incoterms' => $this->abreviaturaIncoterm,
							'items' => $dataFactura
					];
				}
				$asientoContable = Self::armaContabilidad($dataFactura, $conceptosTotales, $empresa->id, $totalComprobante);

				// Arma detalle
				$detalleContable = $tipoAnita." ".$letra." ".$puntoventa->codigo." ".$numero." PEDIDO: ".$pedido_id;

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
						'empresa_id' => $empresa_id,
						'tipotransaccion_id' => $tipoTransaccion_id,
						'puntoventa_id' => $puntoventa->id,
						'numerocomprobante' => $numero,
						'actividad_arca_id' => $actividad_arca_id,
						'cliente_id' => $cliente->id,
						'condicionventa_id' => $pedido->condicionventa_id,
						'vendedor_id' => $pedido->vendedor_id,
						'transporte_id' => $pedido->transporte_id,
						'total' => $totalComprobante * $signo,
						'moneda_id' => $moneda_id,
						'cotizacion' => $cotizacion,
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
						'provincia_id' => $provinciaPercepcion,
						'pais_id' => $cliente->pais_id,
						'codigopostal' => $cliente->codigopostal,
						'email' => $cliente->email,
						'telefono' => $cliente->telefono,
						'numerodocumento' => $cliente->numerodocumento,
						'condicioniva_id' => $cliente->condicioniva_id,
						'puntoventaremito_id' => $this->puntoventaremito_id,
            			'numeroremito' => $numeroremito,
						'cantidadbulto' => $this->cantidadBulto,
						'pedido_id' => $pedido->id
					];	

					// Graba venta
					$vta = $this->ventaRepository->create($venta);

					$referenciaFactura = $vta->codigo;

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
									'venta_id' => $vta->id,
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
					foreach($cuentaCorriente as $cuota)
					{
						$data = [
							'fecha' => $fechaFactura,
							'fechavencimiento' => $cuota['fechavencimiento'],
							'cliente_id' => $cliente->id,
							'total' => $cuota['total'] * $signo,
							'moneda_id' => $moneda_id,
							'cotizacion' => $cotizacion,
							'venta_id' => $vta->id,
							'cobranza_id' => null,
							'empresa_id' => $empresa_id
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
							'tipotransaccion_id' => $tipoTransaccion_id,
							'venta_id' => $vta->id,
							'pedido_articulo_id' => $item['pedido_articulo_id'],
							'ordentrabajo_id' => null,
							'lote' => 0,
							'articulo_id' => $item['articulo_id'],
							'detalle' => $item['descripcion'], 
							'combinacion_id' => null,
							'codigocombinacion' => null,
							'modulo_id' => null,
							'concepto' => $tipotransaccion->nombre,
							'cantidad' => $item['cantidad'],
							'pieza' => $item['pieza'] ?? 0,
							'caja' => $item['caja'] ?? 0,
							'precio' => $item['preciosindescuento'],
							'impuesto_id' => $item['impuesto_id'],
							'costo' => 0,
							'despacho' => $item['despacho'],
							'loteimportacion_id' => $item['loteimportacion_id'],
							'descuento' => $item['descuento'],
							'descuentointegrado' => $item['descuentointegrado'],
							'deposito_id' => $deposito,
							'moneda_id' => $item['moneda_id'],
							'incluyeimpuesto' => $item['incluyeimpuesto'],
							'listaprecio_id' => $item['listaprecio_id'],
							'talle_id' => null,
						];

						$venta_emision = $this->venta_emisionRepository->create($dataArticuloMovimiento);

						$dataFirmado = \App\Support\Ventas\TipotransaccionOperacionStockSupport::firmarPayloadDesdeTipotransaccion(
							$dataArticuloMovimiento,
							$tipotransaccion
						);
						if ($dataFirmado !== null) {
							$articulo_movimiento = $this->articulo_movimientoService->
											guardaArticuloMovimiento('create',
											$dataFirmado, null);
						}
					}
					
					// Graba contabilidad
					Self::grabaAsientoContable($asientoContable, $empresa_id, $fechaFactura, $vta->id, $detalleContable, $centrocosto_id,
											$moneda_id, $cotizacion, $signo, $cliente->cuentacontable_id,
											substr($venta['codigo'],0,3), $letra, $puntoventa->codigo, $venta['numerocomprobante'],
											$puntoventa->modofacturacion ?? null);
					
					// Marca Pedido como facturado
					$pedido = $this->pedidoRepository->update(['estadopedido' => 'Facturado'], $pedido_id);

					if ($puntoventa->modofacturacion != 'M' || $this->flGrabaComprobanteDividido)
					{
						// Graba anita por pedido
						$anita = $this->grabaAnitaConReintentoPorDuplicado($puntoventa->codigo, $letra, $puntoventaremito->codigo, $numeroremito,
									$venta, $dataCAE, $conceptosTotales, $cuentaCorriente, $dataFactura, $signo,
									$codigoTipoTransaccion, $pedido_id,
									true, 0, 0, $referenciaFactura, $empresa->codigo,
									null, null, false, false, false, false, $puntoventa->modofacturacion ?? null);

						if (isset($anita['error']))
						{
							if ($anita['error'] == 'Error')
								throw new Exception('Error en grabacion anita. '.$anita['mensaje']);

							if ($anita['error'] == 'Errvend')
								throw new Exception('No tiene vendedor asignado.');
						}

						// Solicita generacion comprobante ARCA
						Self::solicitaComprobanteARCA($empresa, $codigoTipoTransaccion, substr($venta['codigo'], 0, 3), 
							$letra, $puntoventa, $venta['numerocomprobante'], $fechaFactura, $dataCAE, $vta->id);
					}
					DB::commit();

					return ['factura' => $venta['codigo']];
				} catch (\Exception $e) {
					DB::rollback();

					// Borra factura de anita
					if ($venta['codigo'] ?? '')
						self::borraAnita(substr($venta['codigo'], 0, 3), $letra, 
											$puntoventa->codigo, $venta['numerocomprobante'], $empresa->codigo);

					return ['error' => $e->getMessage()];
				}
			}

			return ['error' => 'No se pudo numerar el comprobante.'];
		}
		else
			return ['error' => 'Error con punto de venta asignado'];

		return ['error' => 'No se pudo generar la factura del pedido.'];
	}

	// Calcula la factura por orden de venta

	public function calculaFacturaPorOrdenventa(array $data)
	{
		// Recibe datos para facturar
		$ordenventa_id = $data['ordenventa_id'];

		$cliente_id = $data['cliente_id'];
		$fechaFactura = $data['fechafactura'];
		$puntoventa_id = $data['puntoventa_id'];
		$this->descuentoPie = $data['descuentopie'];
		$this->descuentoLinea = $data['descuentolinea'];
		$this->descuentoImportePie = $data['descuentoimportepie'];

		// Lee punto de venta
		$puntoventa = $this->puntoventaRepository->find($puntoventa_id);

		$empresa_id = null;
		if ($puntoventa)
			$empresa_id = $puntoventa->empresa_id;

		// Trae el cliente
		$cliente = $this->clienteQuery->traeClienteporId($cliente_id);

		if (!$cliente)
			return ['error' => 'Cliente inexistente'];
		
		if ($cliente->numerodocumento == null)
			return ['error' => 'No tiene Documento'];
			
		// Saca letra del comprobante
		$condicioniva = $this->condicionivaRepository->find($cliente->condicioniva_id);
		$letra = 'Z';
		if ($condicioniva)
			$letra = $condicioniva->letra;

		// Trae la orden de venta
		$ordenventa = $this->ordenventaService->leeOrdenVenta($ordenventa_id);

		if (!$ordenventa)
			return ['error' => 'Orden de venta inexistente'];

		// Lee los items a facturar
		$dataFactura = [];

		// Lee la primer cuota si es que tiene
		$precioUnitario = $ordenventa->monto;
		$ordenventa_cuota_id = 0;
		$cantidadCuota = 0;
		$numeroCuota = 0;
		if (isset($ordenventa->ordenventa_cuotas))
		{
			foreach ($ordenventa->ordenventa_cuotas as $cuota)
			{
				$cantidadCuota++;
				if ($cuota->venta_id == null && $cuota->deleted_at == null &&
					$ordenventa_cuota_id == 0)
				{
					$precioUnitario = $cuota->montofactura;
					$ordenventa_cuota_id = $cuota->id;
					$numeroCuota = $cantidadCuota;
				}
			}
		}

		// Calcula coeficiente de cuota
		$coeficienteCuota = 1;
		if ($ordenventa->monto != 0)
			$coeficienteCuota = $precioUnitario / $ordenventa->monto;

		$moneda_id = $ordenventa->moneda_id;
		$impuesto_id = config('ordenventa.IMPUESTO_ID');
		$incluyeimpuesto = config('ordenventa.INCLUYEIMPUESTO');

		foreach($ordenventa->ordenventa_conceptos as $concepto)
		{
			// Saca la cuenta contable del concepto
			$cuentacontable_id = null;
			foreach ($concepto->concepto_ordenventas->concepto_cuentacontable_ordenventas as $cuenta)
			{
				if ($empresa_id == $cuenta->empresa_id)
					$cuentacontable_id = $cuenta->cuentacontable_id;
			}

			$dataFactura[] = [
				"precio" => $concepto->monto * $coeficienteCuota,
				"preciosindescuento" => $concepto->monto * $coeficienteCuota,
				"cantidad" => $concepto->cantidad,
				"descuento" => $this->descuentoLinea,
				"descuentointegrado" => '',
				"descuentofinal" => $this->descuentoPie,
				"descuentointegradofinal" => '',
				"incluyeimpuesto" => $incluyeimpuesto,
				"impuesto_id" => $impuesto_id,
				'moneda_id' => $moneda_id,
				'ordenventa_id' => $ordenventa_id,
				'concepto_ordenventa_id' => $concepto->concepto_ordenventa_id,
				'detalleconcepto' => $concepto->concepto_ordenventas->nombre,
				'detalle' => $concepto->detalle,
				'cuentacontable_id' => $cuentacontable_id
			];
		}

		// Arma datos del cliente
		$datosCliente = [ "condicioniva_id" => $cliente->condicioniva_id,
						  "numerodocumento" => $cliente->numerodocumento,
						  "retieneiva" => $cliente->retieneiva,
						  "condicioniibb_id" => $cliente->condicioniibb_id,
						  "provincia" => $cliente->provincia_id,
						  "descuentoimportepie" => $this->descuentoImportePie,
						  "id" => $cliente->id
						];

		// Calcula impuestos
		$conceptosTotales = $this->impuestoService->calculaImpuestoVenta($dataFactura, $datosCliente, $fechaFactura);

		// Arma total de comprobante
		$totalComprobante = $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Total', 'importe');

		return ['datosfactura' => $dataFactura, 'datoscliente' => $datosCliente, 'totalcomprobante' => $totalComprobante,
				'conceptostotales' => $conceptosTotales, 'ordenventa_cuota_id' => $ordenventa_cuota_id, 'cantidadcuota' => $cantidadCuota,
				'numerocuota' => $numeroCuota];
	}

	// Factura orden de venta

	public function generaFacturaPorOrdenventa(array $data)
	{
		// Guarda tipo de transaccion y punto de venta en cache
		Cache::forever(generaKey('tipotransaccion'), $data['tipotransaccion_id']);
		Cache::forever(generaKey('puntoventa'), $data['puntoventa_id']);

		// Recalcula factura
		$calculoFactura = Self::calculaFacturaPorOrdenventa($data);

		// Recibe datos para facturar
		$numeroOrdenventa = $data['numeroordenventa'];
		$codigoCentrocosto = $data['codigocentrocosto'];
		$centrocosto_id = $data['centrocosto_id'];
		$ordenventa_id = $data['ordenventa_id'];
		$cliente_id = $data['cliente_id'];
		$puntoventa_id = $data['puntoventa_id'];
		$tipoTransaccion_id = $data['tipotransaccion_id'];
		$fechaFactura = $data['fechafactura'];
		$leyenda = $data['leyendafactura'];
		$actividad_arca_id = $data['actividad_arca_id'];

		$dataFactura = $calculoFactura['datosfactura'];
		$conceptosTotales = $calculoFactura['conceptostotales'];
		$datosCliente = $calculoFactura['datoscliente'];
		$totalComprobante = $calculoFactura['totalcomprobante'];

		$this->descuentoPie = $data['descuentopie'];
		$this->descuentoLinea = $data['descuentolinea'];
		$this->descuentoImportePie = $data['descuentoimportepie'];

		$moneda_id = $calculoFactura['datosfactura'][0]['moneda_id'];

		if (isset($data['cotizacion']))
			$cotizacion = $data['cotizacion'];
		else
		{
			$cot = $this->cotizacionService->leeCotizacionDiaria($fechaFactura, $moneda_id);

			$cotizacion = 0;
			if ($cot)
				$cotizacion = $cot['cotizacionventa'];
		}

		if ($cotizacion == 0)
			$cotizacion = 1.;

		// Trae el cliente 
		$cliente = $this->clienteQuery->traeClienteporId($cliente_id);
		if (!$cliente)
			return ['error' => 'Cliente inexistente'];
		
		$this->cuentacontable_id = $cliente->cuentacontable_id;
		$this->codigoCuentaContable = $cliente->cuentascontables?->codigo ?? '';

		if (isset($cliente->condicionventa_id))
			$condicionventa_id = $cliente->condicionventa_id;
		else
			$condicionventa_id = null;

		// Saca letra del comprobante
		$condicioniva = $this->condicionivaRepository->find($cliente->condicioniva_id);
		$letra = 'Z';
		if ($condicioniva)
			$letra = $condicioniva->letra;

		// Calcula vencimientos
		$cuentacorriente = $this->calculaCondicionVenta($fechaFactura, 
														$totalComprobante, 
														$condicionventa_id);

		// Lee punto de venta
		$puntoventa = $this->puntoventaRepository->find($puntoventa_id);

		if ($puntoventa)
		{
			// Lee empresa
			$empresa = Empresa::find($puntoventa->empresa_id);

			// Lee el tipo de transaccion
			$tipotransaccion = $this->tipotransaccionRepository->find($tipoTransaccion_id);

			// Pide numero de factura
			$codigoTipoTransaccion = $tipotransaccion->codigo;
			$this->nombreTipoTransaccion = $tipotransaccion->nombre;
			$signo = $tipotransaccion->signo == 'S' ? 1. : -1.;

			if ($codigoTipoTransaccion >= '200')
				$tipoAnita = substr($tipotransaccion->abreviatura,0,1)+"CE";
			else
				$tipoAnita = $tipotransaccion->abreviatura;

			switch($puntoventa->modofacturacion)
			{
				case 'C':
				case 'E':
					$this->facturaelectronicaService->armaTipoTransaccion($letra, $cliente->modoFacturacion, $codigoTipoTransaccion,
																			$puntoventa, $totalComprobante);

					$numero = $this->facturaelectronicaService
								->traeUltimoNumeroComprobante($empresa->nroinscripcion,
																$codigoTipoTransaccion,
																$puntoventa);
					break;
				case 'A':
					$numero = Self::buscaUltimoNumeroComprobante($tipoAnita, $letra, $puntoventa);
					
					break;
				case 'M':
					$venta = $this->ventaRepository->traeUltimoComprobanteVenta(
						$tipoTransaccion_id,
						$puntoventa_id,
						(int) ($puntoventa->empresa_id ?? 0) ?: null,
					);
					if ($venta)
						$numero = $venta->numerocomprobante;
					else	
						$numero = 0;
					break;
			}			
			if ($numero != -1)
			{
				// Arma asiento
				$asientoContable = Self::armaContabilidad($dataFactura, $conceptosTotales, $empresa->id, $totalComprobante);
				$numero++;

				// Arma detalle
				$detalleContable = $tipoAnita." ".$letra." ".$puntoventa->codigo." ".$numero." OV: ".$numeroOrdenventa;

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
						$codigoMoneda = $moneda->abreviatura;

					$dataCAE = [
							'codigoempresa' => $empresa->codigo,
							'tipodoc' => $cliente->tipodocumentos->codigoexterno,
							'numerodocumento' => $cliente->numerodocumento,
							'condicioniva_id' => $cliente->condicioniva_id,
							'numerocomprobante' => $numero,
							'fechacomprobante' => date('Ymd', strtotime($fechaFactura)),
							'total' => $totalComprobante,
							'nogravado' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'No Gravado', 'importe'),
							'gravado' => \App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport::gravadoDesdeConceptosTotales($conceptosTotales, abs((float) $totalComprobante)),
							'exento' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Exento', 'importe'),
							'iva' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Iva ', 'importe'),
							'tributo' => $totalTributo,
							'fechavencimiento' => date('Ymd', strtotime($cuentacorriente[0]['fechavencimiento'])),
							'moneda' => $codigoMoneda,
							'cotizacion' => ($codigoMoneda == 'PES' ? 1. : $cotizacion),
							'tributos' => $tributos,
							'impuestos' => $impuestos,
							'comprobantesasociados' => $comprobantesAsociados,
							'fechaasignaciondesde' => date('Ymd', strtotime($fechaAsignacion)),
							'fechaasignacionhasta' => date('Ymd', strtotime($fechaFactura)),
							'pais' => $cliente->paises?->codigo ?? '',
							'nombrecliente' => $cliente->nombre,
							'domicilio' => $cliente->domicilio,
							'formapago' => $cliente->condicionventas->nombre ?? 'CONTADO',
							'formapagoexportacion' => null,
							'incoterms' => null,
							'numeroordenventa' => $numeroOrdenventa,
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
						'tipotransaccion_id' => $tipoTransaccion_id,
						'puntoventa_id' => $puntoventa->id,
						'numerocomprobante' => $numero,
						'actividad_arca_id' => $actividad_arca_id,
						'cliente_id' => $cliente->id,
						'condicionventa_id' => $cliente->condicionventa_id,
						'vendedor_id' => $cliente->vendedor_id,
						'transporte_id' => $cliente->transporte_id,
						'total' => $totalComprobante * $signo,
						'moneda_id' => $moneda_id,
						'cotizacion' => $cotizacion,
						'estado' => ' ',
						'usuario_id' => Auth::id(),
						'leyenda' => $leyenda,
						'descuento' => $this->descuentoPie,
						'descuentointegrado' => ' ',
						'lugarentrega' => $cliente->lugarentrega,
						'cliente_entrega_id' => null,
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
						'numerodocumento' => $cliente->numerodocumento,
						'condicioniva_id' => $cliente->condicioniva_id,
						'puntoventaremito_id' => null,
            			'numeroremito' => 0,
						'cantidadbulto' => 1,
						'ordenventa_id' => $ordenventa_id,
						'empresa_id' => 1
					];	

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
									'venta_id' => $vta->id,
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
							'cotizacion' => $cotizacion,
							'venta_id' => $vta->id,
							'cobranza_id' => null,
							'empresa_id' => 1
						];
						$this->cliente_cuentacorrienteRepository->create($data);
					}
					// Arma tabla de emision del comprobante
					$numeroItem = 0;
					foreach($dataFactura as $itemEmision)
					{
						$dataEmision = [
							'venta_id' => $vta->id,
							'numeroitem' => ++$numeroItem, 
							'lotestock' => 0,
							'detalle' => $itemEmision['detalle'],
							'cantidad' => abs($itemEmision['cantidad']), 
							'precio' => $itemEmision['precio'], 
							'impuesto_id' => $itemEmision['impuesto_id'],
							'incluyeimpuesto' => $itemEmision['incluyeimpuesto'], 
							'moneda_id' => $itemEmision['moneda_id'], 
							'descuento' => $itemEmision['descuento'], 
							'descuentointegrado' => $itemEmision['descuentointegrado']
						];
						$venta_emision = $this->venta_emisionRepository->create($dataEmision);
					}
					// Agrega referencia a la OV
					$dataEmision = [
						'venta_id' => $vta->id,
						'numeroitem' => ++$numeroItem, 
						'detalle' => 'CC '.$codigoCentrocosto.' OV '.$numeroOrdenventa,
						'cantidad' => 0, 
						'precio' => 0
					];
					$venta_emision = $this->venta_emisionRepository->create($dataEmision);			
					// Graba contabilidad
					Self::grabaAsientoContable($asientoContable, $puntoventa->empresa_id, $fechaFactura, $vta->id, 
											$detalleContable, $centrocosto_id,
											$moneda_id, $cotizacion, $signo, $cliente->cuentacontable_id,
											substr($venta['codigo'],0,3), $letra, $puntoventa->codigo, $venta['numerocomprobante'],
											$puntoventa->modofacturacion ?? null);

					// Marca Orden de venta como facturada
					$ordenventa_cuota_id = 0;
					$ordenventa = $this->ordenventaService->marcaOrdenVentaFacturada($ordenventa_id, 
						substr($venta['codigo'],0,3), $letra, $puntoventa->codigo, $venta['numerocomprobante'], $vta->id,
						$ordenventa_cuota_id);

					if ($puntoventa->modofacturacion != 'M')
					{
						// Graba anita factura por orden de venta
						$anita = $this->grabaAnitaConReintentoPorDuplicado($puntoventa->codigo, $letra, 0, 0,
									$venta, $dataCAE, $conceptosTotales, $cuentacorriente, $dataFactura, $signo,
									$codigoTipoTransaccion, null,
									true, $numeroOrdenventa, $codigoCentrocosto, '', $empresa->codigo,
									null, null, false, false, false, false, $puntoventa->modofacturacion ?? null);

						if (isset($anita['error']))
						{
							if ($anita['error'] == 'Error')
								throw new Exception('Error en grabacion anita. '.$anita['mensaje']);

							if ($anita['error'] == 'Errvend')
								throw new Exception('No tiene vendedor asignado.');
						}

						// Solicita generacion comprobante ARCA
						Self::solicitaComprobanteARCA($empresa, $codigoTipoTransaccion, substr($venta['codigo'], 0, 3), 
							$letra, $puntoventa, $venta['numerocomprobante'], $fechaFactura, $dataCAE, $vta->id);
					}
					DB::commit();
					
					return ['factura' => substr($venta['codigo'],0,3).' '.$letra.' '.$puntoventa->codigo.'-'.$venta['numerocomprobante'], 'error' => ''];
				} catch (\Exception $e) {
					DB::rollback();

					// Borra factura de anita
					if ($venta['codigo'] ?? '')
						self::borraAnita(substr($venta['codigo'], 0, 3), $letra, 
											$puntoventa->codigo, $venta['numerocomprobante'], $empresa->codigo);

					return ['error' => $e->getMessage()];
				}
			}
		}
		else
			return 'Error con punto de venta asignado';
	}

	/**
	 * Convierte kilos y descuentoventa_ids (grilla estilo pedido en factura directa) al formato de calculaFacturaGeneral.
	 */
	protected function normalizaItemsFacturaGeneralDesdePedido(array $data): array
	{
		if (! facturaUsaLayoutItemsPedido()) {
			return $data;
		}

		$kilos = $data['kilos'] ?? null;
		if (! is_array($kilos)) {
			return $data;
		}

		$descuentoventaIds = $data['descuentoventa_ids'] ?? [];
		$cantidades = [];
		$descuentosLinea = [];

		foreach ($kilos as $i => $kilo) {
			$cantidades[] = $kilo;

			$descPct = 0.;
			if (! empty($descuentoventaIds[$i])) {
				$descuentoventa = $this->descuentoventaRepository->find((int) $descuentoventaIds[$i]);
				if ($descuentoventa) {
					$descPct = (float) $descuentoventa->porcentajedescuento;
				}
			}
			$descuentosLinea[$i] = $descPct;
		}

		$data['cantidades'] = $cantidades;
		$data['_descuentos_linea_item'] = $descuentosLinea;

		return $data;
	}

	// Calcula factura general

	public function calculaFacturaGeneral($data)
	{
		$data = $this->normalizaItemsFacturaGeneralDesdePedido($data);

		// Guarda tipo de transaccion y punto de venta en cache
		Cache::forever(generaKey('tipotransaccion'), $data['tipotransaccion_id']);
		Cache::forever(generaKey('puntoventa'), $data['puntoventa_id']);

		// Recibe datos para facturar
		$cliente_id = $data['cliente_id'];
		$puntoventa_id = $data['puntoventa_id'];
		$moneda_id = $data['moneda_id'];
		$this->descuentoPie = $data['descuentopie'];
		$this->descuentoLinea = 0;
		$this->descuentoImportePie = $data['descuentoimportepie'];
		$fechaFactura = $data['fechafactura'];

		// Trae el cliente
		$cliente = $this->clienteQuery->traeClienteporId($cliente_id);

		if (!$cliente)
			return ['error' => 'Cliente inexistente'];
		
		if (! isset($data['arca_receptor']) && $cliente->numerodocumento == null)
			return ['error' => 'No tiene Documento'];
			
		// Saca letra del comprobante
		$condicioniva = $this->condicionivaRepository->find($cliente->condicioniva_id);
		$letra = 'Z';
		if ($condicioniva)
			$letra = $condicioniva->letra;

		// Lee punto de venta
		$puntoventa = $this->puntoventaRepository->find($puntoventa_id);

		$empresa_id = $puntoventa->empresa_id;

		// Lee los items a facturar
		$dataFactura = [];
		$totCantidad = 0;

		$articulos = $data['articulo_ids'];
		$descripciones = $data['descripcionarticulos'];
		$cantidades = $data['cantidades'];
		$precios = $data['precios'];
		$listaprecioGlobalId = isset($data['listaprecio_id']) ? (int) $data['listaprecio_id'] : null;
		$listaspreciosIds = $data['listasprecios_id'] ?? null;
		$incluyeimpuestosInput = $data['incluyeimpuestos'] ?? null;
		$impuestosIdsInput = $data['impuesto_ids'] ?? null;
		$incluyePorLista = [];

		// Selección de opcionales por índice de item (orden_opcional => articulo_id).
		// Habilita el cálculo de impuesto interno cuando el insumo elegido es del tipo configurado.
		$opcionalesPorItem = $data['opcionales_por_item'] ?? [];

		// Renglones a los que NO debe escribírseles stkmov en Anita (p. ej. opcionales
		// gastronomía $0: aparecen en compaux como detalle visual, pero el stock real
		// se descuenta vía formula expansion en el depósito de insumos).
		$omitirStkmovAnitaPorItem = $data['omitir_stkmov_anita_por_item'] ?? [];

		for ($offItem = 0; $offItem < count($cantidades); $offItem++)
		{
			// Trae el articulo
			if ($articulos[$offItem] > 0)
			{
				$articulo = $this->articuloQuery->traeArticuloPorId($articulos[$offItem]);

				if (!$articulo)
					return ['error' => 'Artículo inexistente'];

				// Trae la categoria
				$categoria = Categoria::find($articulo->categoria_id);
				$codigoCategoria = '';
				if ($categoria)
					$codigoCategoria = $categoria->codigo;

				$sku = $articulo->sku;
				$articulo_id = $articulo->id;
				$descripcion = $articulo->descripcion;
				$codigoUnidadMedida = $articulo->unidadesdemedidas?->codigo ?? 1;
				$impuesto_id = $articulo->impuesto_id;
				$cuentaContable_id = $articulo->cuentacontableventa_id;
			}
			else
			{
				$articulo_id = null;
				$codigoCategoria = '';
				$descripcion = $descripciones[$offItem];
				$codigoUnidadMedida = 1;
				$sku = '';
				$impuesto_id = (int) config('gastronomia.impuesto_exento_id', 1);

				$cuentaVenta = config('facturacion.CUENTACONTABLE_VENTA');

				$cuentacontable = $this->cuentacontableRepository->findPorCodigo($empresa_id, $cuentaVenta);

				$cuentaContable_id = null;
				if ($cuentacontable)
					$cuentaContable_id = $cuentacontable->id;
			}

			$listaprecio_id = 1;
			if (is_array($listaspreciosIds) && isset($listaspreciosIds[$offItem]) && (string) $listaspreciosIds[$offItem] !== '') {
				$listaprecio_id = (int) $listaspreciosIds[$offItem];
			} elseif ($listaprecioGlobalId) {
				$listaprecio_id = $listaprecioGlobalId;
			}

			if ($articulo_id && ! \App\Support\Ventas\ArticuloListaprecioLineaVentasSupport::listaprecioIdValido($listaprecio_id)) {
				$etiqueta = $sku ?? (string) ($articulos[$offItem] ?? $offItem + 1);

				return [
					'error' => \App\Support\Ventas\ArticuloListaprecioLineaVentasSupport::mensajeError($offItem + 1, $etiqueta),
				];
			}

			if (is_array($impuestosIdsInput) && isset($impuestosIdsInput[$offItem]) && (string) $impuestosIdsInput[$offItem] !== '') {
				$impuesto_id = (int) $impuestosIdsInput[$offItem];
			}

			if (is_array($incluyeimpuestosInput) && isset($incluyeimpuestosInput[$offItem]) && (string) $incluyeimpuestosInput[$offItem] !== '') {
				$incluyeImpuesto = $incluyeimpuestosInput[$offItem];
			} else {
				if (! array_key_exists($listaprecio_id, $incluyePorLista)) {
					$listaPrecio = Listaprecio::find($listaprecio_id);
					$incluyePorLista[$listaprecio_id] = $listaPrecio ? $listaPrecio->incluyeimpuesto : '1';
				}
				$incluyeImpuesto = $incluyePorLista[$listaprecio_id];
			}

			// Lee el descuento por línea (grilla pedido) o descuento global
			$descuentosLineaItem = $data['_descuentos_linea_item'] ?? null;
			if (is_array($descuentosLineaItem) && array_key_exists($offItem, $descuentosLineaItem)) {
				$this->descuentoLinea = (float) $descuentosLineaItem[$offItem];
			} else {
				$this->descuentoLinea = (float) ($data['descuentolinea'] ?? 0);
			}

			$precioUnitario = $precios[$offItem];
			$cantidad = $cantidades[$offItem];

			if ($this->descuentoLinea != 0)
				$precioConDescuento = $precioUnitario * (1. - ($this->descuentoLinea / 100.));
			else
				$precioConDescuento = $precioUnitario;

			// Calcula coeficiente de impuesto interno aplicable al renglón (cigarrillos):
			// expande fórmula respetando los opcionales elegidos y suma los coeficientes de
			// los insumos con tipoarticulo configurado (default: CIGARRILLO).
			$impuestoInternoCoeficiente = 0.;
			if ($articulo_id) {
				$opcionalesItem = is_array($opcionalesPorItem) && isset($opcionalesPorItem[$offItem]) && is_array($opcionalesPorItem[$offItem])
					? $opcionalesPorItem[$offItem]
					: [];
				$impuestoInternoCoeficiente = $this->impuestoService->coeficienteImpuestoInternoArticulo(
					(int) $articulo_id,
					$opcionalesItem,
					(int) $empresa_id,
					(string) $fechaFactura,
				);
			}

			$omitirStkmovAnita = is_array($omitirStkmovAnitaPorItem)
				&& ! empty($omitirStkmovAnitaPorItem[$offItem]);

			$dataFactura[] = ["cantidad" => (float) str_replace(",","",$cantidad),
				"preciosindescuento" => (float) str_replace(",","",$precioUnitario),
				"precio" => (float) str_replace(",","",$precioConDescuento),
				"descuento" => $this->descuentoLinea,
				"descuentointegrado" => '',
				"descuentofinal" => $this->descuentoPie,
				"descuentointegradofinal" => '',
				"incluyeimpuesto" => $incluyeImpuesto,
				"impuesto_id" => $impuesto_id,
				"articulo_id" => $articulo_id,
				"sku" => $sku,
				"descripcion" => $descripcion,
				"codigounidadmedida" => $codigoUnidadMedida,
				'categoria' => $codigoCategoria,
				'moneda_id' => $moneda_id,
				'listaprecio_id' => $listaprecio_id,
				'cuentacontable_id' => $cuentaContable_id,
				'impuesto_interno_coeficiente' => $impuestoInternoCoeficiente,
				'omitir_stkmov_anita' => $omitirStkmovAnita,
			];
			$totCantidad += $cantidad;
		}
		// Arma datos del cliente
		if (strtoupper(config('app.empresa') == "EL BIERZO"))
			$datosCliente = [ "condicioniva_id" => $cliente->condicioniva_id,
							"numerodocumento" => $cliente->numerodocumento,
							"retieneiva" => $cliente->retieneiva,
							"condicioniibb_id" => $cliente->condicioniibb_id,
							"provincia" => $cliente->provincia_id,
							"descuentoimportepie" => $this->descuentoImportePie,
							"id" => $cliente->id,
							"abasto_id" => $cliente->abasto_id,
							"porcentajelogistica" => $cliente->porcentajelogistica
							];
		else
			$datosCliente = [ "condicioniva_id" => $cliente->condicioniva_id,
							"numerodocumento" => $cliente->numerodocumento,
							"retieneiva" => $cliente->retieneiva,
							"condicioniibb_id" => $cliente->condicioniibb_id,
							"provincia" => $cliente->provincia_id,
							"descuentoimportepie" => $this->descuentoImportePie,
							"id" => $cliente->id
							];
		if (! empty($data['omitir_percepciones'])) {
			$datosCliente['omitir_percepciones'] = true;
			$datosCliente['condicioniibb_id'] = (int) config('gastronomia.consumidor_final_condicioniibb_id', 4);
			$datosCliente['retieneiva'] = 'N';
			$datosCliente['provincia'] = null;
			if (! empty($data['venta_receptor']['numerodocumento'])) {
				$datosCliente['numerodocumento'] = $data['venta_receptor']['numerodocumento'];
			}
		}
		// Calcula impuestos
		$conceptosTotales = $this->impuestoService->calculaImpuestoVenta($dataFactura, $datosCliente, $fechaFactura, 
																			$this->flGrabaComprobanteDividido);

		// Arma total de comprobante
		$totalComprobante = $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Total', 'importe');

		return ['datosfactura' => $dataFactura, 'datoscliente' => $datosCliente, 'totalcomprobante' => $totalComprobante,
				'conceptostotales' => $conceptosTotales];
	}

	// Genera factura general

	public function generaComprobanteGeneral(array $data)
	{
		$data = $this->normalizaItemsFacturaGeneralDesdePedido($data);

		// Guarda tipo de transaccion y punto de venta en cache
		Cache::forever(generaKey('tipotransaccion'), $data['tipotransaccion_id']);
		Cache::forever(generaKey('puntoventa'), $data['puntoventa_id']);

		// Recalcula factura
		$calculoFactura = Self::calculaFacturaGeneral($data);

		if (isset($calculoFactura['error'])) {
			return ['error' => $calculoFactura['error']];
		}

		$puntoventa_id = $data['puntoventa_id'];
		$tipoTransaccion_id = $data['tipotransaccion_id'];
		$fechaFactura = $data['fechafactura'];
		$leyenda = $data['leyendafactura'];
		$actividad_arca_id = $data['actividad_arca_id'];

		$dataFactura = $calculoFactura['datosfactura'];
		$conceptosTotales = $calculoFactura['conceptostotales'];
		$datosCliente = $calculoFactura['datoscliente'];
		$totalComprobante = $calculoFactura['totalcomprobante'];

		if (isset($data['venta_id']))
			$venta_id = $data['venta_id'];
		else	
			$venta_id = 0;

		$cliente_id = $data['cliente_id'];
		$this->descuentoPie = $data['descuentopie'];
		$this->descuentoLinea = $data['descuentolinea'];
		$this->descuentoImportePie = $data['descuentoimportepie'];

		if (isset($data['fecha']))
			$fechaFactura = $data['fecha'];
		else
			$fechaFactura = $data['fechafactura'];

		$tipotransaccion = $this->tipotransaccionRepository->find($tipoTransaccion_id);

		$codigoTipoTransaccion = $tipotransaccion->codigo;
		$this->nombreTipoTransaccion = $tipotransaccion->nombre;
		$signo = $tipotransaccion->signo == 'S' ? 1. : -1.;

		if ($codigoTipoTransaccion >= '200')
			$tipoAnita = substr($tipotransaccion->abreviatura,0,1)+"CE";
		else
			$tipoAnita = $tipotransaccion->abreviatura;

		// Verifica si es nota de credito o factura
		$factura = null;
		$ordenventa_id = null;
		$referenciaFactura = null;
		if ($signo < 0 && $venta_id > 0)
		{
			$factura = Self::leeFactura($venta_id);

			if ($factura)
			{
				// Lee el numero de orden de venta para anular 
				if ($factura->ordenventa_id)
					$ordenventa_id = $factura->ordenventa_id;

				$referenciaFactura = $factura->codigo;
			}
		}

		// Arma total de comprobante
		$totalComprobante = $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Total', 'importe');

		// Recibe datos para facturar
		$codigoCentrocosto = 0;
		$puntoventa_id = $data['puntoventa_id'];
		$leyenda = $data['leyendafactura'] ?? '';
		$moneda_id = $data['moneda_id'];

		if (isset($data['cotizacion']))
			$cotizacion = $data['cotizacion'];
		else
		{
			$cot = $this->cotizacionService->leeCotizacionDiaria($fechaFactura, $moneda_id);

			$cotizacion = 0;
			if ($cot)
				$cotizacion = $cot['cotizacionventa'];
		}

		if ($cotizacion == 0)
			$cotizacion = 1.;

		// Trae el cliente 
		$cliente = $this->clienteQuery->traeClienteporId($cliente_id);
		if (!$cliente)
			return ['error' => 'Cliente inexistente'];

		$clienteGraba = clone $cliente;
		if (! empty($data['venta_receptor']) && is_array($data['venta_receptor'])) {
			$vr = $data['venta_receptor'];
			if (isset($vr['nombre'])) {
				$clienteGraba->nombre = $vr['nombre'];
			}
			if (isset($vr['numerodocumento'])) {
				$clienteGraba->numerodocumento = $vr['numerodocumento'];
			}
			if (array_key_exists('domicilio', $vr)) {
				$clienteGraba->domicilio = $vr['domicilio'];
			}
		}
		
		$this->cuentacontable_id = $cliente->cuentacontable_id;
		$this->codigoCuentaContable = $cliente->cuentascontables?->codigo ?? '';

		if (isset($cliente->condicionventa_id))
			$condicionventa_id = $cliente->condicionventa_id;
		else
			$condicionventa_id = null;

		// Saca letra del comprobante
		$condicioniva = $this->condicionivaRepository->find($cliente->condicioniva_id);
		$letra = 'Z';
		if ($condicioniva)
			$letra = $condicioniva->letra;

		// Calcula vencimientos
		$cuentacorriente = $this->calculaCondicionVenta($fechaFactura, 
														$totalComprobante, 
														$condicionventa_id);

		// Lee punto de venta
		$puntoventa = $this->puntoventaRepository->find($puntoventa_id);

		if ($puntoventa)
		{
			// Lee empresa
			$empresa = Empresa::find($puntoventa->empresa_id);

			// Lee el tipo de transaccion
			$tipotransaccion = $this->tipotransaccionRepository->find($tipoTransaccion_id);

			// Pide numero de factura
			$codigoTipoTransaccion = $tipotransaccion->codigo;
			$this->nombreTipoTransaccion = $tipotransaccion->nombre;
			$signo = $tipotransaccion->signo == 'S' ? 1. : -1.;

			if ($codigoTipoTransaccion >= '200')
				$tipoAnita = substr($tipotransaccion->abreviatura,0,1)+"CE";
			else
				$tipoAnita = $tipotransaccion->abreviatura;

			$numeroForzado = (int) ($data['numerocomprobante_forzado'] ?? 0);
			$opcionesEmisionNumeracion = is_array($data['opciones_emision'] ?? null) ? $data['opciones_emision'] : [];
			switch($puntoventa->modofacturacion)
			{
				case 'C':
				case 'E':
					$this->facturaelectronicaService->armaTipoTransaccion($letra, $cliente->modoFacturacion, $codigoTipoTransaccion,
																			$puntoventa, $totalComprobante);

					if ($numeroForzado > 0) {
						$numero = $numeroForzado - 1;
					} else {
						GastronomiaEmisionProfiler::activo()?->marcar('arca_ultimo_numero_inicio');
						$numero = $this->facturaelectronicaService
									->traeUltimoNumeroComprobante(
										$empresa->nroinscripcion,
										$codigoTipoTransaccion,
										$puntoventa,
										$opcionesEmisionNumeracion,
									);
						GastronomiaEmisionProfiler::activo()?->marcar('arca_ultimo_numero_fin');
					}
					break;
				case 'A':
					if ($numeroForzado > 0) {
						$numero = $numeroForzado - 1;
					} elseif (! empty($opcionesEmisionNumeracion['anita_modo_minimo'])) {
						GastronomiaEmisionProfiler::activo()?->marcar('erp_ultimo_numero_inicio');
						$ultimaVenta = $this->ventaRepository->traeUltimoComprobanteVenta(
							$tipoTransaccion_id,
							$puntoventa_id,
							(int) ($puntoventa->empresa_id ?? 0) ?: null,
						);
						$numero = $ultimaVenta ? (int) $ultimaVenta->numerocomprobante : 0;
						GastronomiaEmisionProfiler::activo()?->marcar('erp_ultimo_numero_fin');
					} else {
						GastronomiaEmisionProfiler::activo()?->marcar('anita_ultimo_numero_inicio');
						$numero = Self::buscaUltimoNumeroComprobante($tipoAnita, $letra, $puntoventa);
						GastronomiaEmisionProfiler::activo()?->marcar('anita_ultimo_numero_fin');
					}
					break;
				case 'M':
					if ($numeroForzado > 0) {
						$numero = $numeroForzado - 1;
					} else {
						$venta = $this->ventaRepository->traeUltimoComprobanteVenta(
							$tipoTransaccion_id,
							$puntoventa_id,
							(int) ($puntoventa->empresa_id ?? 0) ?: null,
						);
						if ($venta) {
							$numero = $venta->numerocomprobante;
						} else {
							$numero = 0;
						}
					}
					break;
			}			
			$centrocosto_id = null;
			if ($numero != -1)
			{
				// Arma asiento
				if ($signo == -1 && isset($factura))
				{
					$factura->loadMissing(['asientos.asiento_movimientos']);
					if ($factura->asientos->isNotEmpty()) {
						$asientoContable = [];
						foreach ($factura->asientos[0]->asiento_movimientos as $movimiento)
						{
							$asientoContable[] = ['empresa_id' => $factura->asientos[0]->empresa_id,
												'cuentacontable_id' => $movimiento->cuentacontable_id,
												'monto' => $movimiento->monto*-1
												];

							$centrocosto_id = $movimiento->centrocosto_id;
						}
					} else {
						$asientoContable = Self::armaContabilidad($dataFactura, $conceptosTotales, $empresa->id, $totalComprobante);
					}
				}
				else
					$asientoContable = Self::armaContabilidad($dataFactura, $conceptosTotales, $empresa->id, $totalComprobante);

				$numero++;

				// Arma detalle
				$detalleContable = $tipoAnita." ".$letra." ".$puntoventa->codigo." ".$numero;

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
						$codigoMoneda = $moneda->abreviatura;

					$arcaTipodoc = $cliente->tipodocumentos->codigoexterno;
					$arcaNumerodoc = $cliente->numerodocumento;
					$arcaNombre = $cliente->nombre;
					$arcaDomicilio = $cliente->domicilio;
					if (! empty($data['arca_receptor']) && is_array($data['arca_receptor'])) {
						$ar = $data['arca_receptor'];
						if (isset($ar['tipodoc'])) {
							$arcaTipodoc = $ar['tipodoc'];
						}
						if (isset($ar['numerodocumento'])) {
							$arcaNumerodoc = $ar['numerodocumento'];
						}
						if (isset($ar['nombre'])) {
							$arcaNombre = $ar['nombre'];
						}
						if (array_key_exists('domicilio', $ar)) {
							$arcaDomicilio = $ar['domicilio'];
						}
					}

					$dataCAE = [
							'codigoempresa' => $empresa->codigo,
							'tipodoc' => $arcaTipodoc,
							'numerodocumento' => $arcaNumerodoc,
							'condicioniva_id' => $cliente->condicioniva_id,
							'numerocomprobante' => $numero,
							'fechacomprobante' => date('Ymd', strtotime($fechaFactura)),
							'total' => $totalComprobante,
							'nogravado' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'No Gravado', 'importe'),
							'gravado' => \App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport::gravadoDesdeConceptosTotales($conceptosTotales, abs((float) $totalComprobante)),
							'exento' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Exento', 'importe'),
							'iva' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Iva ', 'importe'),
							'tributo' => $totalTributo,
							'fechavencimiento' => date('Ymd', strtotime($cuentacorriente[0]['fechavencimiento'])),
							'moneda' => $codigoMoneda,
							'cotizacion' => ($codigoMoneda == 'PES' ? 1. : $cotizacion),
							'tributos' => $tributos,
							'impuestos' => $impuestos,
							'comprobantesasociados' => $comprobantesAsociados,
							'fechaasignaciondesde' => date('Ymd', strtotime($fechaAsignacion)),
							'fechaasignacionhasta' => date('Ymd', strtotime($fechaFactura)),
							'pais' => $cliente->paises?->codigo ?? '',
							'nombrecliente' => $arcaNombre,
							'domicilio' => $arcaDomicilio,
							'formapago' => $cliente->condicionventas->nombre ?? 'CONTADO',
							'formapagoexportacion' => null,
							'incoterms' => null,
							'numeroordenventa' => '',
							'items' => $dataFactura
					];
				}
				$opcionesEmision = $data['opciones_emision'] ?? null;
				$graba = Self::grabaFacturaERP($empresa, $codigoTipoTransaccion, $tipotransaccion, $fechaFactura,  
									$clienteGraba, $totalComprobante, $moneda_id, $cotizacion, $leyenda,  
									$letra, $puntoventa, $numero, $ordenventa_id, $conceptosTotales, $cuentacorriente,
									$dataFactura, $asientoContable, $detalleContable, $signo, $centrocosto_id, $codigoCentrocosto,
									$dataCAE, $venta_id, $referenciaFactura, $actividad_arca_id, $opcionesEmision);
			}
			else
				$graba = ['error' => 'No pudo numerar comprobate'];
		}
		else
			$graba = ['error' => 'No pudo leer punto de venta'];

		return $graba;
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
		$tipoTransaccion_id = $data['tipotransaccion_id'];
		$fechaFactura = $data['fechafactura'];
		$leyenda = $data['leyendafactura'];

		$actividad_arca_id = null;
		if (isset($data['actividad_arca_id']))
			$actividad_arca_id = $data['actividad_arca_id'];

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

						if ($cliente->numerodocumento == null)
							return ['error' => 'No tiene CUIT'];
							
						$this->cuentacontable_id = $cliente->cuentacontable_id;
						$this->codigoCuentaContable = $cliente->cuentascontables?->codigo ?? '';

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
							$errorEntrega = $this->validarLugarEntregaPedido($cliente, $pedido);
							if ($errorEntrega) {
								return $errorEntrega;
							}

							$this->sincronizarLugarEntregaPedido($pedido);
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
		$this->sincronizarLugarEntregaPedido($pedido);
		$provinciaPercepcion = $this->provinciaPercepcionDesdePedido($cliente, $pedido);

		// Arma datos del cliente
		$datosCliente = [ "condicioniva_id" => $cliente->condicioniva_id,
						  "numerodocumento" => $cliente->numerodocumento,
						  "retieneiva" => $cliente->retieneiva,
						  "condicioniibb" => $cliente->condicioniibb,
						  "provincia" => $provinciaPercepcion,
						  "descuentoimportepie" => $this->descuentoImportePie,
						  "id" => $cliente->id
						];

		// Calcula impuestos
		$conceptosTotales = $this->impuestoService->calculaImpuestoVenta($dataFactura, $datosCliente, $fechaFactura);

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
			$tipotransaccion = $this->tipotransaccionRepository->find($tipoTransaccion_id);

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
				$venta = $this->ventaRepository->traeUltimoComprobanteVenta(
					$tipoTransaccion_id,
					$puntoventa_id,
					(int) ($puntoventa->empresa_id ?? 0) ?: null,
				);
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
							'codigoempresa' => 1,
							'tipodoc' => 80,
							'numerodocumento' => $cliente->numerodocumento,
							'numerocomprobante' => $numero,
							'fechacomprobante' => date('Ymd', strtotime($fechaFactura)),
							'total' => $totalComprobante,
							'nogravado' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'No Gravado', 'importe'),
							'gravado' => \App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport::gravadoDesdeConceptosTotales($conceptosTotales, abs((float) $totalComprobante)),
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
							'pais' => $cliente->paises?->codigo ?? '',
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
						'tipotransaccion_id' => $tipoTransaccion_id,
						'puntoventa_id' => $puntoventa->id,
						'numerocomprobante' => $numero,
						'actividad_arca_id' => $actividad_arca_id,
						'cliente_id' => $cliente->id,
						'condicionventa_id' => $pedido->condicionventa_id,
						'vendedor_id' => $pedido->vendedor_id,
						'transporte_id' => $pedido->transporte_id,
						'total' => $totalComprobante * $signo,
						'moneda_id' => $moneda_id,
						'cotizacion' => 1,
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
						'provincia_id' => $provinciaPercepcion,
						'pais_id' => $cliente->pais_id,
						'codigopostal' => $cliente->codigopostal,
						'email' => $cliente->email,
						'telefono' => $cliente->telefono,
						'numerodocumento' => $cliente->numerodocumento,
						'condicioniva_id' => $cliente->condicioniva_id,
						'puntoventaremito_id' => $this->puntoventaremito_id,
            			'numeroremito' => $numeroremito,
						'cantidadbulto' => $this->cantidadBulto
					];	

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
							'cotizacion' => 1,
							'venta_id' => $vta->id,
							'cobranza_id' => null,
							'empresa_id' => $puntoventa->empresa_id
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
							'tipotransaccion_id' => $tipoTransaccion_id,
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
						// Graba anita
						$anita = $this->grabaAnitaConReintentoPorDuplicado($puntoventa->codigo, $letra, $puntoventaremito->codigo, $numeroremito,
									$venta, $dataCAE, $conceptosTotales, $cuentacorriente, $dataFactura, $signo,
									$codigoTipoTransaccion, null,
									true, 0, 0, '', $empresa->codigo,
									null, null, false, false, false, false, $puntoventa->modofacturacion ?? null);

						if (isset($anita['error']))
						{
							if ($anita['error'] == 'Error')
								throw new Exception('Error en grabacion anita. '.$anita['mensaje']);

							if ($anita['error'] == 'Errvend')
								throw new Exception('No tiene vendedor asignado.');
						}

						// Solicita generacion comprobante ARCA
						Self::solicitaComprobanteARCA($empresa, $codigoTipoTransaccion, substr($venta['codigo'], 0, 3), 
							$letra, $puntoventa, $venta['numerocomprobante'], $fechaFactura, $dataCAE, $vta->id);
					}
					DB::commit();

					return ['factura' => $numero, 'error' => ''];
				} catch (\Exception $e) {
					DB::rollback();

					// Borra factura de anita
					if ($venta['codigo'] ?? '')
						self::borraAnita(substr($venta['codigo'], 0, 3), $letra, 
											$puntoventa->codigo, $venta['numerocomprobante'], 1);

					return ['error' => $e->getMessage()];
				}
			}
		}
		else
			return 'Error con punto de venta asignado';
	}

	public function grabaFacturaERP($empresa, $codigoTipoTransaccion, $tipotransaccion, $fechaFactura,  
									$cliente, $totalComprobante, $moneda_id, $cotizacion, $leyenda,  
									$letra, $puntoventa, $numero, $ordenventa_id, $conceptosTotales, $cuentacorriente,
									$dataFactura, $asientoContable, $detalleContable, $signo, $centrocosto_id, $codigoCentrocosto,
									$dataCAE, $venta_id, $referenciaFactura, $actividad_arca_id, $opcionesEmision = null)
	{
		$omitirMovimientoStock = (is_array($opcionesEmision) && ! empty($opcionesEmision['omitir_movimiento_stock']))
			|| ! \App\Support\Ventas\TipotransaccionOperacionStockSupport::afectaStock(
				$tipotransaccion->operacionstock ?? \App\Support\Ventas\TipotransaccionOperacionStockSupport::SIN_OPERACION
			);
		$omitirContabilidad = is_array($opcionesEmision) && ! empty($opcionesEmision['omitir_contabilidad']);
		$omitirCuentaCorriente = is_array($opcionesEmision) && ! empty($opcionesEmision['omitir_cuenta_corriente']);
		$omitirSolicitudArcaCae = is_array($opcionesEmision) && ! empty($opcionesEmision['omitir_solicitud_arca_cae']);
		$omitirSincronizacionAnita = is_array($opcionesEmision) && ! empty($opcionesEmision['omitir_sincronizacion_anita']);
		$omitirStkmovAnita = is_array($opcionesEmision) && ! empty($opcionesEmision['omitir_stkmov_anita']);
		$omitirNumeraAnitaFin = is_array($opcionesEmision) && ! empty($opcionesEmision['omitir_numera_anita_fin']);
		// CAE (PV C/E): el número fiscal lo asigna ARCA; la réplica Anita no debe avanzar compemis/numerador al cierre.
		if (
			! $omitirNumeraAnitaFin
			&& in_array((string) ($puntoventa->modofacturacion ?? ''), ['C', 'E'], true)
		) {
			$omitirNumeraAnitaFin = true;
		}
		$transaccionExterna = DB::transactionLevel() > 0;

		$numeroOrdenventa = 0;

		// Graba la factura (participa en transacción externa si ya hay una abierta, ej. gastronomía).
		if (! $transaccionExterna) {
			DB::beginTransaction();
		}
		$venta = [];
		$replicacionAnitaIntentada = false;

		try 
		{
			if ($codigoTipoTransaccion >= '200')
				$tipoAnita = substr($tipotransaccion->abreviatura,0,1)+"CE";
			else
				$tipoAnita = $tipotransaccion->abreviatura;

			$fechaJornada = $fechaFactura;
			if (is_array($opcionesEmision) && ! empty($opcionesEmision['fechajornada'])) {
				$fechaJornada = $opcionesEmision['fechajornada'];
			}

			$venta = ['fecha' => $fechaFactura,
				'fechajornada' => $fechaJornada,
				'empresa_id' => $puntoventa->empresa_id,
				'tipotransaccion_id' => $tipotransaccion->id,
				'puntoventa_id' => $puntoventa->id,
				'numerocomprobante' => $numero,
				'actividad_arca_id' => $actividad_arca_id,
				'cliente_id' => $cliente->id,
				'condicionventa_id' => $cliente->condicionventa_id,
				'vendedor_id' => $cliente->vendedor_id,
				'transporte_id' => $cliente->transporte_id,
				'total' => $totalComprobante * $signo,
				'moneda_id' => $moneda_id,
				'cotizacion' => $cotizacion,
				'estado' => ' ',
				'usuario_id' => Auth::id(),
				'leyenda' => $leyenda,
				'descuento' => $this->descuentoPie,
				'descuentointegrado' => ' ',
				'lugarentrega' => $cliente->lugarentrega,
				'cliente_entrega_id' => null,
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
				'numerodocumento' => $cliente->numerodocumento,
				'condicioniva_id' => $cliente->condicioniva_id,
				'puntoventaremito_id' => null,
				'numeroremito' => 0,
				'cantidadbulto' => 1,
				'ordenventa_id' => $ordenventa_id
			];	

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
							'venta_id' => $vta->id,
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
			if (! $omitirCuentaCorriente) {
			foreach($cuentacorriente as $cuota)
			{
				$data = [
					'fecha' => $fechaFactura,
					'fechavencimiento' => $cuota['fechavencimiento'],
					'cliente_id' => $cliente->id,
					'total' => $cuota['total'] * $signo,
					'moneda_id' => $moneda_id,
					'cotizacion' => $cotizacion,
					'venta_id' => $vta->id,
					'cobranza_id' => null,
					'empresa_id' => $puntoventa->empresa_id
				];
				$cliente_cuentacorriente = $this->cliente_cuentacorrienteRepository->create($data);

				// Graba aplicacion del comprobante que esta generando
				if ($venta_id > 0)
				{
					// Graba aplicacion del comprobante al que aplica
					// Busca cuentacorriente de factura aplicada
					$cliente_cuentacorriente_venta = $this->cliente_cuentacorrienteRepository->findPorVenta($venta_id);

					$data = [
						'fecha' => $fechaFactura,
						'cliente_cuentacorriente_id' => $cliente_cuentacorriente->id,
						'total' => $cuota['total'] * $signo * -1,
						'moneda_id' => $moneda_id,
						'cotizacion' => $cotizacion,
						'ventaaplicado_id' => $venta_id,
						'comprobanteaplicado' => $referenciaFactura,
						'cobranza_id' => null,
						'empresa_id' => $puntoventa->empresa_id,
						'cliente_cuentacorriente_aplicado_id' => $cliente_cuentacorriente_venta[0]->id // Apunta a factura que aplica
					];

					$cliente_cuentacorriente_aplicacion = $this->cliente_cuentacorriente_aplicacionRepository->create($data);

					// Graba aplicacion del comprobante al que aplica (factura)
					if ($cliente_cuentacorriente_venta)
					{
						$data = [
							'fecha' => $fechaFactura,
							'cliente_cuentacorriente_id' => $cliente_cuentacorriente_venta[0]->id,
							'total' => $cuota['total'] * $signo,
							'moneda_id' => $moneda_id,
							'cotizacion' => $cotizacion,
							'ventaaplicado_id' => $vta->id,
							'comprobanteaplicado' => $vta->codigo,
							'cobranza_id' => null,
							'empresa_id' => $puntoventa->empresa_id,
							'cliente_cuentacorriente_aplicado_id' => $cliente_cuentacorriente->id // Apunta a nota de credito que aplica
						];
						$cliente_cuentacorriente_aplicacion = $this->cliente_cuentacorriente_aplicacionRepository->create($data);
					}
					else
						throw new Exception('No pudo aplicar nota de crédito');
				}
			}
			}
			// Arma tabla de emision del comprobante
			$numeroItem = 0;
			$dataArticuloMovimiento = [];
			foreach($dataFactura as $itemEmision)
			{
				if (! $omitirMovimientoStock && isset($itemEmision['articulo_id']))
				{
					$dataArticuloMovimiento = [
						'fecha' => $fechaFactura,
						'fechajornada' => $fechaFactura,
						'tipotransaccion_id' => $tipotransaccion->id,
						'venta_id' => $vta->id,
						'pedido_combinacion_id' => $itemEmision['pedido_combinacion_id'] ?? null,
						'ordentrabajo_id' => $itemEmision['ordentrabajo_id'] ?? null,
						'lote' => 0,
						'articulo_id' => $itemEmision['articulo_id'],
						'combinacion_id' => $itemEmision['combinacion_id'] ?? null,
						'codigocombinacion' => $itemEmision['codigocombinacion'] ?? null,
						'modulo_id' => $itemEmision['modulo_id'] ?? null,
						'concepto' => $tipotransaccion->nombre,
						'cantidad' => $itemEmision['cantidad'],
						'precio' => $itemEmision['precio'],
						'costo' => 0,
						'despacho' => $itemEmision['despacho'] ?? null,
						'loteimportacion_id' => $itemEmision['loteimportacion_id'] ?? null,
						'descuento' => $itemEmision['descuento'],
						'descuentointegrado' => $itemEmision['descuentointegrado'],
						'moneda_id' => $itemEmision['moneda_id'],
						'incluyeimpuesto' => $itemEmision['incluyeimpuesto'],
						'listaprecio_id' => $itemEmision['listaprecio_id'],
					];
				}

				$dataEmision = [
					'venta_id' => $vta->id,
					'numeroitem' => ++$numeroItem, 
					'lotestock' => 0,
					'detalle' => $itemEmision['detalle'] ?? $itemEmision['descripcion'],
					'cantidad' => abs($itemEmision['cantidad']), 
					'precio' => $itemEmision['precio'], 
					'impuesto_id' => $itemEmision['impuesto_id'],
					'incluyeimpuesto' => $itemEmision['incluyeimpuesto'], 
					'moneda_id' => $itemEmision['moneda_id'], 
					'descuento' => $itemEmision['descuento'], 
					'descuentointegrado' => $itemEmision['descuentointegrado'],
				];
				if (! empty($itemEmision['articulo_id'])) {
					$dataEmision['articulo_id'] = $itemEmision['articulo_id'];
				}
				$venta_emision = $this->venta_emisionRepository->create($dataEmision);

				if (! $omitirMovimientoStock && isset($itemEmision['articulo_id']) && $dataArticuloMovimiento !== [])
				{
					$dataTalle = [];
					$dataFirmado = \App\Support\Ventas\TipotransaccionOperacionStockSupport::firmarPayloadMovimiento(
						$dataArticuloMovimiento,
						$tipotransaccion->operacionstock
					);
					$articulo_movimiento = $this->articulo_movimientoService->
								guardaArticuloMovimiento('create',
								$dataFirmado, $dataTalle);
				}
			}
			// Graba contabilidad
			if (! $omitirContabilidad) {
			Self::grabaAsientoContable($asientoContable, $puntoventa->empresa_id, $fechaFactura, $vta->id, 
									$detalleContable, $centrocosto_id,
									$moneda_id, $cotizacion, $signo, $cliente->cuentacontable_id,
									substr($venta['codigo'],0,3), $letra, $puntoventa->codigo, $venta['numerocomprobante'],
									$puntoventa->modofacturacion ?? null);
			}

			$ret = [
				'factura' => substr($venta['codigo'], 0, 3).' '.$letra.' '.$puntoventa->codigo.'-'.$venta['numerocomprobante'],
				'error' => '',
				'venta_id' => $vta->id,
			];

			if ($puntoventa->modofacturacion != 'M')
			{
				if (! $omitirSincronizacionAnita) {
					$modoMinimoAnita = is_array($opcionesEmision)
						&& ! empty($opcionesEmision['anita_modo_minimo']);
					$deferAnitaTrasCommit = $transaccionExterna
						&& $modoMinimoAnita
						&& $this->anitaTrasCommitAlFacturarHabilitado($opcionesEmision);

					if ($deferAnitaTrasCommit) {
						$ret['anita_pendiente'] = [
							'puntoventa_codigo' => $puntoventa->codigo,
							'letra' => $letra,
							'venta' => $venta,
							'data_cae' => $dataCAE,
							'conceptos_totales' => $conceptosTotales,
							'cuentacorriente' => $cuentacorriente,
							'data_factura' => $dataFactura,
							'signo' => $signo,
							'codigo_tipo_transaccion' => $codigoTipoTransaccion,
							'numero_orden_venta' => $numeroOrdenventa,
							'codigo_centrocosto' => $codigoCentrocosto,
							'referencia_factura' => $referenciaFactura,
							'empresa_codigo' => $empresa->codigo,
							'modo_minimo_anita' => true,
							'omitir_cuenta_corriente_anita' => $omitirCuentaCorriente,
							'omitir_stkmov_anita' => $omitirStkmovAnita,
							'omitir_numera_anita_fin' => $omitirNumeraAnitaFin,
							'modo_facturacion_puntoventa' => $puntoventa->modofacturacion ?? null,
						];
					} else {
						$replicacionAnitaIntentada = true;
						GastronomiaEmisionProfiler::activo()?->marcar('anita_graba_inicio');
						// Graba anita
						$anita = $this->grabaAnitaConReintentoPorDuplicado($puntoventa->codigo, $letra, 0, 0,
									$venta, $dataCAE, $conceptosTotales, $cuentacorriente, $dataFactura, $signo,
									$codigoTipoTransaccion, null,
									true, $numeroOrdenventa, $codigoCentrocosto, $referenciaFactura,
									$empresa->codigo, null, null, $modoMinimoAnita, $omitirCuentaCorriente,
									$omitirStkmovAnita, $omitirNumeraAnitaFin, $puntoventa->modofacturacion ?? null);

						GastronomiaEmisionProfiler::activo()?->marcar('anita_graba_fin');

						if (isset($anita['error']))
						{
							if ($anita['error'] === 'Errvend') {
								throw new Exception('Error en grabación Anita: el cliente no tiene vendedor asignado.');
							}

							$detalle = trim((string) ($anita['mensaje'] ?? $anita['error'] ?? 'Error desconocido'));
							throw new Exception('Error en grabación Anita: '.$detalle);
						}
					}
				}

				if ($omitirSolicitudArcaCae) {
					$ret['cae_pendiente'] = [
						'empresa' => $empresa,
						'codigo_tipo_transaccion' => $codigoTipoTransaccion,
						'tipo_anita' => substr($venta['codigo'], 0, 3),
						'letra' => $letra,
						'puntoventa' => $puntoventa,
						'numero_comprobante' => $numero,
						'fecha_factura' => $fechaFactura,
						'data_cae' => $dataCAE,
						'venta_id' => $vta->id,
						'opciones_emision_arca' => is_array($opcionesEmision) ? $opcionesEmision : [],
					];
				} else {
					// Solicita CAE/CAEA en ARCA (último paso del flujo estándar).
					Self::solicitaComprobanteARCA($empresa, $codigoTipoTransaccion, substr($venta['codigo'], 0, 3),
						$letra, $puntoventa, $venta['numerocomprobante'], $fechaFactura, $dataCAE, $vta->id);
				}
			}

			// Si tiene orden de venta y es nota de credito anula marca en OV
			if ($ordenventa_id > 0 && $signo == -1)
			{
				// Marca Orden de venta como facturada
				$ordenventa = $this->ordenventaService->anulaMarcaOrdenVentaFacturada($ordenventa_id, 
					substr($venta['codigo'],0,3), $letra, $puntoventa->codigo, $venta['numerocomprobante'], $vta->id);
			}

			if (! $transaccionExterna) {
				DB::commit();
			}

			return $ret;
		} catch (\Exception $e) {
			$omitirContabilidadAnita = $omitirContabilidad
				|| (is_array($opcionesEmision) && ! empty($opcionesEmision['anita_modo_minimo']));
			$this->revertirVentaEnAnitaSiGrabada(
				$venta,
				$letra,
				$puntoventa,
				$empresa,
				$omitirSincronizacionAnita,
				$replicacionAnitaIntentada,
				$omitirContabilidadAnita,
			);

			if (! $transaccionExterna) {
				DB::rollback();

				return ['error' => $e->getMessage()];
			}

			throw $e;
		}
	}

	/**
	 * Elimina en Informix (Anita) un comprobante ya replicado, p. ej. si falla un paso posterior en gastronomía.
	 *
	 * @param  array<string, mixed>|null  $ventaArray  Datos de venta armados en grabaFacturaERP.
	 */
	public function revertirVentaEnAnitaSiGrabada(
		?array $ventaArray,
		string $letra,
		$puntoventa,
		$empresa,
		bool $omitirSincronizacionAnita = false,
		bool $replicacionAnitaIntentada = false,
		bool $omitirContabilidadAnita = false,
	): void {
		if ($omitirSincronizacionAnita || ! $replicacionAnitaIntentada || ! is_array($ventaArray)) {
			return;
		}

		if (! $puntoventa || ($puntoventa->modofacturacion ?? '') === 'M') {
			return;
		}

		$codigo = trim((string) ($ventaArray['codigo'] ?? ''));
		if ($codigo === '') {
			return;
		}

		self::borraAnita(
			substr($codigo, 0, 3),
			$letra,
			$puntoventa->codigo,
			$ventaArray['numerocomprobante'],
			$empresa->codigo,
			$omitirContabilidadAnita,
			$puntoventa->modofacturacion ?? null,
		);
	}

	/**
	 * Elimina en Anita el comprobante asociado a una venta del ERP (rollback tras emisión gastronomía).
	 */
	public function borraAnitaDesdeVenta(\App\Models\Ventas\Venta $venta, bool $omitirContabilidadAnita = true): void
	{
		$codigo = trim((string) ($venta->codigo ?? ''));
		if ($codigo === '') {
			return;
		}

		$puntoventa = $this->puntoventaRepository->find($venta->puntoventa_id);
		if (! $puntoventa || ($puntoventa->modofacturacion ?? '') === 'M') {
			return;
		}

		$empresa = Empresa::query()->find($puntoventa->empresa_id);
		if (! $empresa) {
			return;
		}

		$letra = 'Z';
		if ($venta->condicioniva_id) {
			$condicioniva = $this->condicionivaRepository->find($venta->condicioniva_id);
			if ($condicioniva) {
				$letra = (string) $condicioniva->letra;
			}
		}

		self::borraAnita(
			substr($codigo, 0, 3),
			$letra,
			$puntoventa->codigo,
			$venta->numerocomprobante,
			$empresa->codigo,
			$omitirContabilidadAnita,
			$puntoventa->modofacturacion ?? null,
		);
	}

	/**
	 * Replica en Informix un comprobante gastronomía ya grabado en el ERP (backfill / reparación).
	 *
	 * @param  array<string, mixed>  $ventaArray
	 * @param  array<string, mixed>  $dataCAE
	 * @param  list<array<string, mixed>>  $conceptosTotales
	 * @param  list<array<string, mixed>>  $dataFactura
	 * @return array{error: string, mensaje?: string}|string
	 */
	public function replicarVentaGastronomiaEnAnita(
		array $ventaArray,
		array $dataCAE,
		array $conceptosTotales,
		array $dataFactura,
		$puntoventa,
		$empresa,
		string $letra,
		string $codigoTipoTransaccion,
		float $signo,
		float $descuentoPie = 0.,
		bool $modoMinimoAnita = true,
		bool $sinCuentaCorrienteAnita = true,
		bool $omitirStkmovAnita = false,
	) {
		$this->descuentoPie = $descuentoPie;

		$fechaVencimiento = $ventaArray['fecha'] ?? date('Y-m-d');
		$cuentacorriente = [[
			'fechavencimiento' => $fechaVencimiento,
			'total' => abs((float) ($ventaArray['total'] ?? 0)),
		]];

		return $this->grabaAnitaConReintentoPorDuplicado(
			$puntoventa->codigo,
			$letra,
			0,
			0,
			$ventaArray,
			$dataCAE,
			$conceptosTotales,
			$cuentacorriente,
			$dataFactura,
			$signo,
			$codigoTipoTransaccion,
			null,
			true,
			0,
			0,
			'',
			$empresa->codigo,
			null,
			null,
			$modoMinimoAnita,
			$sinCuentaCorrienteAnita,
			$omitirStkmovAnita,
			false,
			$puntoventa->modofacturacion ?? null,
		);
	}

	/**
	 * Valores en orden de columnas Informix para LOAD FROM (modo mínimo gastronomía / AGG).
	 * Misma semántica que grabaAnita() para venta + vengrav + vencae.
	 *
	 * @param  array<string, mixed>  $venta
	 * @param  array<string, mixed>  $dataCAE
	 * @param  list<array<string, mixed>>  $conceptostotales
	 * @return array{
	 *   venta: list<int|float|string>,
	 *   vengrav: list<list<int|float|string>>,
	 *   vencae: ?list<int|float|string>
	 * }
	 */
	public function construirFilasUnlGastronomiaModoMinimo(
		$puntoventaCodigo,
		string $letra,
		array $venta,
		array $dataCAE,
		array $conceptostotales,
		string $codigoTipoTransaccion,
		string $empresaCodigo,
		?string $modoFacturacionPuntoventa = null,
		bool $sinCuentaCorrienteAnita = true,
		?string $cae = null,
		?string $fechavencimientocae = null,
	): array {
		$cliente = $this->clienteQuery->traeClienteporId($venta['cliente_id']);
		$codigoCliente = '';
		$zonavta_id = $provincia_id = $subzonavta_id = 0;
		$codigopostal = '';
		$numerodocumento = '';
		$nombre = '';
		$domicilio = '';
		if ($cliente) {
			$codigoCliente = $cliente->codigo;
			$zonavta_id = $cliente->zonavta_id;
			$provincia_id = $cliente->provincia_id;
			$subzonavta_id = $cliente->subzonavta_id;
			$codigopostal = $cliente->codigopostal;
			$numerodocumento = $cliente->numerodocumento;
			$nombre = $cliente->nombre;
			$domicilio = $cliente->domicilio;
		} else {
			if (isset($venta['nombrecliente'])) {
				$nombre = $venta['nombrecliente'];
			}
			if (isset($venta['documentocliente'])) {
				$domicilio = $numerodocumento = $venta['documentocliente'];
			}
		}

		$totalIngBruto2 = $totalIngBruto1 = $totalPercepcionIva = 0;
		$totalDescuento = $porcentajeDescuento = 0;
		$totalImpuestoInterno = 0;
		foreach ($conceptostotales as $concepto) {
			if (array_key_exists('jurisdiccion', $concepto) && $concepto['jurisdiccion'] != null) {
				if ($concepto['jurisdiccion'] == '902') {
					$totalIngBruto1 += $concepto['importe'];
				} else {
					$totalIngBruto2 += $concepto['importe'];
				}
			}
			if (strpos($concepto['concepto'], 'Percepcion IVA') !== false) {
				$totalPercepcionIva += $concepto['importe'];
			}
			if (strpos($concepto['concepto'], 'Descuento Gral') !== false) {
				$totalDescuento += $concepto['importe'];
				$porcentajeDescuento = $concepto['tasa'];
			}
			if (strpos($concepto['concepto'], 'Impuesto Interno') !== false) {
				$totalImpuestoInterno += $concepto['importe'];
			}
		}

		$vendedor = 1;
		$empresa = $dataCAE['codigoempresa'];
		$tipoErpVenta = substr((string) ($venta['codigo'] ?? ''), 0, 3);
		$tipoVentaAnita = KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge(
			$tipoErpVenta,
			(string) $puntoventaCodigo,
			$empresa,
			$modoFacturacionPuntoventa,
		);
		$exento = $dataCAE['exento'] + $dataCAE['nogravado'];

		$nombreLocalidad = isset($cliente->localidades->nombre) ? $cliente->localidades->nombre : '';
		$nombreProvincia = isset($cliente->provincias->nombre) ? $cliente->provincias->nombre : '';

		$condicionventa = $venta['condicionventa_id'] != '' ? $venta['condicionventa_id'] : 0;

		$condicionivaId = null;
		if (! empty($venta['condicioniva_id'])) {
			$condicionivaId = (int) $venta['condicioniva_id'];
		} elseif ($cliente && isset($cliente->condicioniva_id)) {
			$condicionivaId = (int) $cliente->condicioniva_id;
		}
		$venCondIvaCli = $this->codigoCondicionIvaAnitaDesdeErpId($condicionivaId, $letra);
		$venCtaCte = $sinCuentaCorrienteAnita ? 'N' : 'S';

		$filaVenta = [
			str_pad($codigoCliente, 6, '0', STR_PAD_LEFT),
			$tipoVentaAnita,
			$letra,
			$puntoventaCodigo,
			$venta['numerocomprobante'],
			date('Ymd', strtotime($venta['fecha'])),
			date('Ymd', strtotime($venta['fechajornada'])),
			$exento,
			$dataCAE['gravado'],
			0,
			0,
			$totalImpuestoInterno,
			0,
			$totalIngBruto2,
			0,
			0,
			$dataCAE['iva'],
			$totalPercepcionIva,
			abs($venta['total']),
			abs($totalDescuento),
			$porcentajeDescuento,
			0,
			$venta['moneda_id'],
			$venta['cotizacion'],
			0,
			0,
			0,
			$zonavta_id == null ? 0 : $cliente->zonavta_id,
			$provincia_id == null ? 0 : $cliente->provincia_id,
			$subzonavta_id == null ? 0 : $cliente->subzonavta_id,
			$vendedor,
			0,
			$condicionventa,
			0,
			$nombre,
			$domicilio,
			$nombreLocalidad,
			$nombreProvincia,
			$codigopostal,
			$numerodocumento,
			$venCondIvaCli,
			$venCtaCte,
			$this->nombreUsuarioAnitaParaGraba($venta),
			'ERP',
			date_format(Carbon::now(), 'Ymd'),
			' ',
			0,
			0,
			0,
			$totalIngBruto1,
			0,
		];

		if (config('app.empresa') == 'AGG') {
			$filaVenta[] = $empresaCodigo;
		}

		$filasVengrav = [];
		foreach ($conceptostotales as $concepto) {
			if (strpos($concepto['concepto'], 'Iva') === false) {
				continue;
			}
			$filasVengrav[] = [
				$tipoVentaAnita,
				$letra,
				$puntoventaCodigo,
				$venta['numerocomprobante'],
				$concepto['codigo'],
				$concepto['baseimponible'],
				$concepto['importe'],
				0,
				$concepto['tasa'],
			];
		}

		$filaVencae = null;
		$caeTrim = trim((string) ($cae ?? ''));
		if ($caeTrim !== '' && $fechavencimientocae !== null && trim((string) $fechavencimientocae) !== '') {
			$filaVencae = [
				$tipoVentaAnita,
				$letra,
				$puntoventaCodigo,
				$venta['numerocomprobante'],
				$caeTrim,
				date('Ymd', strtotime((string) $fechavencimientocae)),
			];
		}

		return [
			'venta' => $filaVenta,
			'vengrav' => $filasVengrav,
			'vencae' => $filaVencae,
		];
	}

	/**
	 * Escritura Anita con validación de respuesta; devuelve array de error o null si OK.
	 *
	 * @return array{error: string, mensaje: string}|null
	 */
	private function apiCallAnitaEscritura(ApiAnita $apiAnita, array $payload, string $contexto): ?array
	{
		try {
			$apiAnita->apiCallEscritura($payload, $contexto, 'facturacion.anita_bridge.fallo');

			return null;
		} catch (\RuntimeException $e) {
			return ['error' => 'Error', 'mensaje' => $e->getMessage()];
		}
	}

	/**
	 * Código ven_cond_iva_cli / clim_cond_iva en Informix (mismo criterio que ClienteRepository::setCamposAnita).
	 */
	private function codigoCondicionIvaAnitaDesdeErpId(?int $condicionivaId, string $letra = ''): string
	{
		switch ((string) ($condicionivaId ?? '')) {
			case '1':
				return '0';
			case '3':
				return '3';
			case '2':
			case '5':
				return '4';
			case '4':
				return '5';
			case '6':
				return '6';
			case '7':
				return '8';
			default:
				return $letra === 'A' ? '0' : '3';
		}
	}

	/**
	 * ven_usuario / usuarioalta en Anita: sesión HTTP o usuario_id de la venta (cola, CLI).
	 */
	private function nombreUsuarioAnita(?int $usuarioId = null): string
	{
		$usuario = Auth::user();
		if ($usuario !== null) {
			$nombre = trim((string) ($usuario->nombre ?? ''));
			if ($nombre !== '') {
				return $nombre;
			}
		}

		if ($usuarioId !== null && $usuarioId > 0) {
			$nombre = trim((string) (Usuario::query()->whereKey($usuarioId)->value('nombre') ?? ''));
			if ($nombre !== '') {
				return $nombre;
			}
		}

		return 'ERP';
	}

	/**
	 * @param  array<string, mixed>  $venta
	 */
	private function nombreUsuarioAnitaParaGraba(array $venta): string
	{
		$usuarioId = isset($venta['usuario_id']) ? (int) $venta['usuario_id'] : null;

		return $this->nombreUsuarioAnita($usuarioId > 0 ? $usuarioId : null);
	}

	// Graba factura en Anita
	public function grabaAnita($puntoventa, $letra, $puntoventaremito, $numeroremito, $venta, 
								$dataCAE, $conceptostotales, $cuentacorriente, $datatalle, $signo, 
								$codigoTipoTransaccion, $pedido_id,
								$flGrabaStock, $numeroOrdenventa, $codigoCentrocosto, $referenciaFactura, 
								$servidor = null, $ifx_server = null, bool $modoMinimoAnita = false,
								bool $sinCuentaCorrienteAnita = false, bool $omitirStkmovAnita = false,
								bool $omitirNumeraAnitaFin = false, ?string $modoFacturacionPuntoventa = null)
	{
		if ($numeroOrdenventa > 0)
			$detalle = $dataCAE['items'][0]['detalle'];

		// Lee el cliente
		$cliente = $this->clienteQuery->traeClienteporId($venta['cliente_id']);
		$codigoCliente = '';
		$zonavta_id = $provincia_id = $subzonavta_id = 0;
		$codigopostal = '';
		$numerodocumento = '';
		$nombre = "";
		$domicilio = "";
		if ($cliente)
		{
			$codigoCliente = $cliente->codigo;
			$zonavta_id = $cliente->zonavta_id;
			$provincia_id = $cliente->provincia_id;
			$subzonavta_id = $cliente->subzonavta_id;
			$codigopostal = $cliente->codigopostal;
			$numerodocumento = $cliente->numerodocumento;
			$nombre = $cliente->nombre;
			$domicilio = $cliente->domicilio;
		}
		else
		{
			if (isset($venta['nombrecliente']))
				$nombre = $venta['nombrecliente'];
		
			if (isset($venta['documentocliente']))
				$domicilio = $numerodocumento = $venta['documentocliente'];
		}

		// Calcula totales para venta
		$totalIngBruto2 = $totalIngBruto1 = $totalPercepcionIva = 0;
		$totalDescuento = $porcentajeDescuento = 0;
		$totalAbasto = $totalLogistica = 0;
		$totalImpuestoInterno = 0;
		foreach ($conceptostotales as $concepto)
		{
			if (array_key_exists('jurisdiccion', $concepto) && $concepto['jurisdiccion'] != null)
			{
				if ($concepto['jurisdiccion'] == '902')
					$totalIngBruto1 += $concepto['importe'];
				else
					$totalIngBruto2 += $concepto['importe'];
			}
			if (strpos($concepto['concepto'], 'Percepcion IVA') !== false)
				$totalPercepcionIva += $concepto['importe'];

			if (strpos($concepto['concepto'], 'Descuento Gral') !== false)
			{
				$totalDescuento += $concepto['importe'];
				$porcentajeDescuento = $concepto['tasa'];
			}

			if (strpos($concepto['concepto'], 'Abasto') !== false)
				$totalAbasto = $concepto['importe'];

			if (strpos($concepto['concepto'], 'Logistica') !== false)
				$totalLogistica = $concepto['importe'];

			// Impuesto interno (régimen transparencia fiscal): viaja a Anita en ven_imp_interno.
			if (strpos($concepto['concepto'], 'Impuesto Interno') !== false)
				$totalImpuestoInterno += $concepto['importe'];
		}
		// Lee comisiones
		$vendedor = 1;
		if (config('app.empresa') == 'Calzados Ferli')
		{
			if ($codigoCliente != '')
			{
				$vendedor = Self::leeVendedor(str_pad($codigoCliente, 6, "0", STR_PAD_LEFT), $this->mventa_id);

				if ($vendedor == 0)
					return 'Errvend';
			}
		}

		// Graba venta
        $apiAnita = new ApiAnita();

		$empresa = $dataCAE['codigoempresa'];
		$tipoErpVenta = substr((string) ($venta['codigo'] ?? ''), 0, 3);
		$tipoVentaAnita = KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge(
			$tipoErpVenta,
			(string) $puntoventa,
			$empresa,
			$modoFacturacionPuntoventa,
		);
		$exento = $dataCAE['exento'] + $dataCAE['nogravado'];

		if (!isset($cliente->localidades->nombre))
			$nombreLocalidad = '';
		else
			$nombreLocalidad = $cliente->localidades->nombre;	

		if (!isset($cliente->provincias->nombre))
			$nombreProvincia = '';
		else
			$nombreProvincia = $cliente->provincias->nombre;	

		if ($venta['condicionventa_id'] != '')
			$condicionventa = $venta['condicionventa_id'];
		else
			$condicionventa = 0;

		// Lee el transporte
		$codigoTransporte = 0;
		if ($venta['transporte_id'] != null)
		{
			$transporte = $this->transporteRepository->find($venta['transporte_id']);

			if ($transporte)
				$codigoTransporte = $transporte->codigo;
		}
			
		$codigoAbasto = 0;
		if (isset($cliente->abastos->codigo))
			$codigoAbasto = $cliente->abastos->codigo;

		$condicionivaId = null;
		if (! empty($venta['condicioniva_id'])) {
			$condicionivaId = (int) $venta['condicioniva_id'];
		} elseif ($cliente && isset($cliente->condicioniva_id)) {
			$condicionivaId = (int) $cliente->condicioniva_id;
		}
		$venCondIvaCli = $this->codigoCondicionIvaAnitaDesdeErpId($condicionivaId, $letra);
		$venCtaCte = $sinCuentaCorrienteAnita ? 'N' : 'S';

		if (strtoupper(config('app.empresa') == 'EL BIERZO'))
			$data = array( 	'tabla' => 'venta', 
						'acc' => 'insert',
            			'campos' => ' 
							ven_cliente, ven_tipo, ven_letra, ven_sucursal, ven_nro, ven_fecha, ven_fecha_vto,        
							ven_exento, ven_gravado, ven_gravado_ot, ven_tasa_iva_ot, ven_imp_interno,      
							ven_no_inscripto, ven_sellado, ven_porc_sellado, ven_perc_no_categ, ven_impuesto1,   
							ven_percepcion_iva, ven_monto, ven_monto_desc, ven_porc_desc, ven_monto_anul,    
							ven_cod_mon, ven_cotizacion, ven_fecha_cobro, ven_t_ult_cobro, ven_t_cobrado,     
							ven_zonavta, ven_subzona, ven_zonamult, ven_vendedor, ven_cobrador, ven_cond_venta,       
							ven_comision_ven, ven_nombre_cliente, ven_direccion_cli, ven_localidad_cli,    
							ven_provincia_cli, ven_cod_postal_cli, ven_cuit_cli, ven_cond_iva_cli,     
							ven_cta_cte, ven_usuario, ven_terminal, ven_fe_ult_act, ven_fl_imprimio,      
							ven_cedio_a, ven_fecha_cesion, ven_nro_cesion, ven_perc_ing_bruto, 
							ven_cod_entrega, ven_cod_abasto, ven_tot_abasto, ven_corte_fresco, ven_transporte, ven_logistica, 
							ven_empresa, ven_tasa_ibr1, ven_tasa_ibr2, ven_tipo_comp',
            			'valores' => " 
							'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
							'".$tipoVentaAnita."',
							'".$letra."', '".$puntoventa."', '".$venta['numerocomprobante']."',
							'".date('Ymd', strtotime($venta['fecha']))."',
							'".date('Ymd', strtotime($venta['fechajornada']))."',
							'".$exento."',
							'".$dataCAE['gravado']."',
							'".'0'."',
							'".'0'."',
							'".$totalImpuestoInterno."',
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
							'".$venta['cotizacion']."',
							'".'0'."',
							'".'0'."',
							'".'0'."',
							'".($zonavta_id == null ? '0' : $cliente->zonavta_id)."',
							'".($provincia_id == null ? '0' : $cliente->provincia_id)."',
							'".($subzonavta_id == null ? '0' : $cliente->subzonavta_id)."',
							'".$vendedor."',
							'".'0'."',
							'".$condicionventa."',
							'".'0'."',
							'".$nombre."',
							'".$domicilio."',
							'".$nombreLocalidad."',
							'".$nombreProvincia."',
							'".$codigopostal."',
							'".$numerodocumento."',
							'".$venCondIvaCli."',
							'".$venCtaCte."',
							'".$this->nombreUsuarioAnitaParaGraba($venta)."',
							'".'ERP'."',
							'".date_format(Carbon::now(), 'Ymd')."',
							'".' '."',
							'".'0'."',
							'".'0'."',
							'".'0'."',
							'".$totalIngBruto1."',
							'".'0'."',
							'".$codigoAbasto."',
							'".$totalAbasto."',
							'".'0'."',
							'".$codigoTransporte."',
							'".$totalLogistica."',
							'".$empresa."',
							'".'0'."',
							'".'0'."',
							'".substr($codigoTipoTransaccion,-2)."'"
					);
		else
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
							ven_cod_entrega'.(config('app.empresa') == 'AGG' ? ', ven_empresa' : ''),
            			'valores' => " 
							'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
							'".$tipoVentaAnita."',
							'".$letra."',
							'".$puntoventa."',
							'".$venta['numerocomprobante']."',
							'".date('Ymd', strtotime($venta['fecha']))."',
							'".date('Ymd', strtotime($venta['fechajornada']))."',
							'".$exento."',
							'".$dataCAE['gravado']."',
							'".'0'."',
							'".'0'."',
							'".$totalImpuestoInterno."',
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
							'".$venta['cotizacion']."',
							'".'0'."',
							'".'0'."',
							'".'0'."',
							'".($zonavta_id == null ? '0' : $cliente->zonavta_id)."',
							'".($provincia_id == null ? '0' : $cliente->provincia_id)."',
							'".($subzonavta_id == null ? '0' : $cliente->subzonavta_id)."',
							'".$vendedor."',
							'".'0'."',
							'".$condicionventa."',
							'".'0'."',
							'".$nombre."',
							'".$domicilio."',
							'".$nombreLocalidad."',
							'".$nombreProvincia."',
							'".$codigopostal."',
							'".$numerodocumento."',
							'".$venCondIvaCli."',
							'".$venCtaCte."',
							'".$this->nombreUsuarioAnitaParaGraba($venta)."',
							'".'ERP'."',
							'".date_format(Carbon::now(), 'Ymd')."',
							'".' '."',
							'".'0'."',
							'".'0'."',
							'".'0'."',
							'".$totalIngBruto1."',
							'".'0'."'".(config('app.empresa') == 'AGG' ? ", '".$empresa."'":"")
					);

		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}

		$errVta = $this->apiCallAnitaEscritura($apiAnita, $data, 'venta insert');
		if ($errVta !== null) {
			return $errVta;
		}

		// vengrav: gastronomía modo mínimo y facturación Ventas completa
		foreach ($conceptostotales as $concepto) {
			if (strpos($concepto['concepto'], 'Iva') === false) {
				continue;
			}

			$apiAnitaVengrav = new ApiAnita();
			$sobreTasa = 0;
			$dataVengrav = [
				'tabla' => 'vengrav',
				'acc' => 'insert',
				'campos' => '
									veng_tipo, veng_letra, veng_sucursal, veng_nro,
									veng_codigo_tasa, veng_gravado, veng_impuesto, veng_sobretasa, veng_tasa ',
				'valores' => "
									'".substr($venta['codigo'], 0, 3)."',
									'".$letra."',
									'".$puntoventa."',
									'".$venta['numerocomprobante']."',
									'".$concepto['codigo']."',
									'".$concepto['baseimponible']."',
									'".$concepto['importe']."',
									'".$sobreTasa."',
									'".$concepto['tasa']."'
								",
			];
			if ($this->flGrabaComprobanteDividido) {
				$dataVengrav['path_sistema'] = '/usr2/villafranca';
			}

			$errVengrav = $this->apiCallAnitaEscritura($apiAnitaVengrav, $dataVengrav, 'vengrav insert');
			if ($errVengrav !== null) {
				return $errVengrav;
			}
		}

		if (! $modoMinimoAnita) {
		// Graba venibr
		foreach ($conceptostotales as $concepto)
		{
			if (array_key_exists('jurisdiccion', $concepto))
			{
				if ($concepto['jurisdiccion'] > 0)
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

					if ($this->flGrabaComprobanteDividido)
					{
						$data['path_sistema'] = '/usr2/villafranca';
					}

					$errVenibr = $this->apiCallAnitaEscritura($apiAnita, $data, 'venibr insert');
					if ($errVenibr !== null) {
						return $errVenibr;
					}
				}
			}
		}
		
		// Graba climov
		$nroCuota = 0;
		$fechaVencimiento = 0;
		foreach($cuentacorriente as $cuota)
		{
			$apiAnita = new ApiAnita();
			$nroCuota++;

			if ($referenciaFactura != '')
			{
				$partes = explode(" ", $referenciaFactura);
				$tipoReferencia = $partes[0];

				$resto = explode("-", $partes[1]);
				$letraReferencia = $resto[0];
				$puntoVentaReferencia = $resto[1];
				$numeroReferencia = $resto[2];
			}
			else
			{
				$tipoReferencia = '';
				$letraReferencia = '';
				$puntoVentaReferencia = 0;
				$numeroReferencia = 0;
				if ($numeroOrdenventa > 0)
				{
					$tipoReferencia = 'OV';
					$letraReferencia = ' ';
					$puntoVentaReferencia = $codigoCentrocosto;
					$numeroReferencia = $numeroOrdenventa;
				}
			}
			$fechaVencimiento = $cuota['fechavencimiento'];

			$data = array( 	'tabla' => 'climov', 
							'acc' => 'insert',
							'campos' => ' 
								cliv_cliente, cliv_tipo, cliv_letra, cliv_sucursal, cliv_nro, cliv_ref_tipo,
								cliv_ref_letra, cliv_ref_sucursal, cliv_ref_nro, cliv_fecha, cliv_fecha_vto,
								cliv_monto, cliv_cod_mon, cliv_cotizacion, cliv_nro_cuota, cliv_t_cobrado,
								cliv_fecha_cobro, cliv_cedio_a, cliv_estado '.(config('app.empresa') == 'AGG' ? ', cliv_empresa' : ''),
							'valores' => "
								'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
								'".substr($venta['codigo'], 0, 3)."',
								'".$letra."',
								'".$puntoventa."',
								'".$venta['numerocomprobante']."',
								'".$tipoReferencia."', 
								'".$letraReferencia."', 
								'".$puntoVentaReferencia."', 
								'".$numeroReferencia."',
								'".date('Ymd', strtotime($venta['fecha']))."',
								'".date('Ymd', strtotime($cuota['fechavencimiento']))."',
								'".$cuota['total']."',
								'".$venta['moneda_id']."',
								'".$venta['cotizacion']."',
								'".$nroCuota."',
								'".'0'."',
								'".'0'."',
								'".'0'."',
								'".'I'."'".(config('app.empresa') == 'AGG' ? ", '".$empresa."'" : "")
					);
			if ($this->flGrabaComprobanteDividido)
			{
				$data['path_sistema'] = '/usr2/villafranca';
			}

			$errClimov = $this->apiCallAnitaEscritura($apiAnita, $data, 'climov insert');
			if ($errClimov !== null) {
				return $errClimov;
			}
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
							comp_no_insc, comp_exento, comp_gravado, comp_dto_integrado'.
							(config('app.empresa') == 'Calzados Ferli' ? ', comp_cond_vta_exp, comp_fpago_exp, 
							comp_merc_exp, comp_moneda_exp, comp_sucursal_rem, ' : '').' '.
							(config('app.empresa') == 'AGG' ? ', comp_empresa' : '').
							(config('app.empresa') == 'EL BIERZO' ? 
							', comp_estado, comp_cod_remito, comp_cod_aut_cre, comp_fecha_vto' : ''),
						'valores' => "
							'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
							'".substr($venta['codigo'], 0, 3)."',
							'".$letra."',
							'".$puntoventa."',
							'".$venta['numerocomprobante']."',
							'".(isset($pedido_id) ? $pedido_id : $numeroOrdenventa)."',
							'".$numeroremito."',
							'".date('Ymd', strtotime($venta['fecha']))."',
							'".(config('app.empresa') == 'EL BIERZO' ? $codigoTransporte : '0')."',
							'".(config('app.empresa') == 'EL BIERZO' ? $this->cantidadBulto : $condicionventa)."',
							'".$lugarEntrega."',
							'".$porcentajeDescuento."',
							'".$codigoTransporte."',
							'".(config('app.empresa') == 'EL BIERZO' ? $puntoventaremito : '0')."',
							'".$leyenda."',
							'".$venta['total']."',
							'".$dataCAE['iva']."',
							'".'0'."',
							'".$exento."',
							'".$dataCAE['gravado']."',
							'".$venta['descuentointegrado']."' ".
							(config('app.empresa') == 'Calzados Ferli' ? 
							",'".$this->condicionVentaExportacion."',
							'".$this->formaPagoExportacion."',
							'".$this->mercaderiaExportacion."',
							'".$this->monedaExportacion."',
							'".$puntoventaremito."', " : "")." ".
							(config('app.empresa') == 'AGG' ? ", '".$empresa."'" : "").
							(config('app.empresa') == 'EL BIERZO' ? 
							", '".' '."',
							'".' '."',
							'".' '."',
							'".date('Ymd', strtotime($fechaVencimiento))."'" : "")." "
					);
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}

		$errComprob = $this->apiCallAnitaEscritura($apiAnita, $data, 'comprob insert');
		if ($errComprob !== null) {
			return $errComprob;
		}
		}

		// Agrupa por medida / partida para anita
		$flGrabaStock = false;
		if ($numeroOrdenventa == 0)
		{
			$dataItem = [];
			foreach($datatalle as $item)
			{
				if (isset($item['preciosindescuento']))
					$precio = $item['preciosindescuento'];
				else
					$precio = $item['precio'];

				if (isset($item['sku']) && isset($item['medidas']))
				{
					$flGrabaStock = true;

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
								'descuento' => $medida['descuento'],
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
				else
				{
					if (isset($item['articulo_id']))
					{
						$flGrabaStock = true;
						$dataItem[] = [
							'partida' => 0,
							'cantidad' => $item['cantidad'],
							'pieza' => $item['pieza'] ?? 0,
							'caja' => $item['caja'] ?? 0,
							'precio' => $precio,
							'descuento' => $item['descuento'],
							'impuesto_id' => $item['impuesto_id'],
							'incluyeimpuesto' => $item['incluyeimpuesto'],
							// Sin pedido (gastronomía u otros flujos sin OV): se manda 0 para que stkv_pedido no quede null.
							'pedido' => $pedido_id ?? 0,
							'sku' => $item['sku'],
							'descripcion' => $item['descripcion'],
							'categoria' => $item['categoria'],
							'medida' => '',
							// Banderín para omitir stkmov en Anita (p. ej. opcionales gastronomía $0:
							// se muestran en compaux pero el stock se descuenta vía formula → depo de insumos).
							'omitir_stkmov_anita' => (bool) ($item['omitir_stkmov_anita'] ?? false),
						];
					}
					else
						$dataItem[] = [
							'partida' => 0,
							'cantidad' => $item['cantidad'],
							'pieza' => 0,
							'caja' => 0,
							'precio' => $precio,
							'descuento' => $item['descuento'],
							'impuesto_id' => $item['impuesto_id'],
							'incluyeimpuesto' => $item['incluyeimpuesto'],
							'pedido' => 0,
							'sku' => 'texto',
							'descripcion' => $item['detalle'] ?? $item['descripcion'],
							'omitir_stkmov_anita' => (bool) ($item['omitir_stkmov_anita'] ?? false),
						];
				}
			}
		}
		else
		{
			$flGrabaStock = false;

			// Separar detalle de OV en lineas de 30 caracteres
			$partes = explode("\n", wordwrap($detalle, 30, " "));
			$flPrimerItem = true;
			foreach($partes as $parte)
			{
				$parteFiltrada = str_replace(["\r", "\n"], "", $parte);

				if ($flPrimerItem)
				{
					$precio = $dataCAE['gravado']+$exento;

					$dataItem[] = [
						'partida' => 0,
						'cantidad' => 1,
						'precio' => $precio,
						'descuento' => 0,
						'impuesto_id' => $datatalle[0]['impuesto_id'],
						'incluyeimpuesto' => $datatalle[0]['incluyeimpuesto'],
						'pedido' => 0,
						'sku' => 'texto',
						'descripcion' => $parteFiltrada
					];
					$flPrimerItem = false;
				}
				else
					$dataItem[] = [
						'partida' => 0,
						'cantidad' => 0,
						'precio' => 0,
						'descuento' => 0,
						'impuesto_id' => 0,
						'incluyeimpuesto' => ' ',
						'pedido' => 0,
						'sku' => 'texto',
						'descripcion' => $parteFiltrada
					];
			}
			$dataItem[] = [
				'partida' => 0,
				'cantidad' => 0,
				'precio' => 0,
				'descuento' => 0,
				'impuesto_id' => 0,
				'incluyeimpuesto' => ' ',
				'pedido' => 0,
				'sku' => 'texto',
				'descripcion' => 'CC '.$codigoCentrocosto.' OV '.$numeroOrdenventa
			];			
		}
		// Graba compaux
		$orden = 0;
		foreach($dataItem as $medida)
		{
			$orden++;

			$apiAnita = new ApiAnita();
			
			$tipo_iva = '0';
			if ($medida['impuesto_id'] > 0)
				$tipo_iva = $medida['impuesto_id'];

			$data = array( 	'tabla' => 'compaux', 
							'acc' => 'insert',
							'campos' => ' 
								compa_cliente, compa_tipo, compa_letra, compa_sucursal, compa_nro_fact, 
								compa_orden, compa_articulo, compa_cantidad, compa_precio, compa_desc, compa_dto,
								compa_deposito, compa_tipo_iva, compa_referencia, compa_fecha, compa_incl_imp '.
								(config('app.empresa') == 'Calzados Ferli' ? ', compa_despacho' : '').
								(config('app.empresa') == 'EL BIERZO' ? ', compa_pieza' : ''),
							'valores' => "
								'".str_pad($codigoCliente, 6, "0", STR_PAD_LEFT)."', 
								'".substr($venta['codigo'], 0, 3)."',
								'".$letra."',
								'".$puntoventa."',
								'".$venta['numerocomprobante']."',
								'".$orden."',
								'".($medida['sku'] == 'texto' ? 'texto' : str_pad($medida['sku'], 13, "0", STR_PAD_LEFT))."', 
								'".$medida['cantidad']."', 
								'".$medida['precio']."', 
								'".$medida['descripcion']."', 
								'".$medida['descuento']."',
								'".'1'."',
								'".$medida['impuesto_id']."', 
								'".'0'."',
								'".date('Ymd', strtotime($venta['fecha']))."',
								'".($medida['incluyeimpuesto'] == '2' ? 'N' : 'S')."' ".
								(config('app.empresa') == 'Calzados Ferli' ? ", '".$medida['despacho']."'" : "").
								(config('app.empresa') == 'EL BIERZO' ? ", '".$medida['pieza']."'" : "")
					);
			if ($this->flGrabaComprobanteDividido)
			{
				$data['path_sistema'] = '/usr2/villafranca';
			}

			if (! $modoMinimoAnita) {
			$errCompaux = $this->apiCallAnitaEscritura($apiAnita, $data, 'compaux insert');
			if ($errCompaux !== null) {
				return $errCompaux;
			}
			}
				
			// Graba stkmov
			$apiAnita = new ApiAnita();

			if ($this->flDivide && $this->flGrabaComprobanteDividido)
				$tasa = $this->tasaImpuesto;
			else
			{
				// Lee tasa impuesto del item
				$impuesto = Impuesto::find($medida['impuesto_id']);

				$tasa = 1;
				if ($impuesto)
					$tasa = $impuesto->valor;
			}

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

			// Omitir stkmov para renglones marcados (p. ej. opcionales gastronomía $0:
			// se preservan en compaux como detalle visible, pero el stock se descuenta
			// vía formula expansion en deposito de insumos por separado).
			if ($flGrabaStock && ! $omitirStkmovAnita && empty($medida['omitir_stkmov_anita']))
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
								stkv_cod_umd, stkv_unidad_xenv, stkv_cod_umd_alter'.
								(config('app.empresa') == 'Calzados Ferli' ? ', stkv_cant_unidad, stkv_color' : '').
								(config('app.empresa') == 'EL BIERZO' ? ', stkv_expreso, stkv_cant_unidad' : '').
								(config('app.empresa') == 'AGG' ? ', stkv_cant_unidad, stkv_empresa' : ''),
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
								'".$medida['descuento']."',
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
								'".substr((string) ($medida['pedido'] ?? '0'),-8)."',
								'".$this->nombreUsuarioAnitaParaGraba($venta)."',
								'".'ERP'."',
								'".date_format(Carbon::now(), 'Ymd')."',
								'".'0'."',
								'".'0'."',
								'".(config('app.empresa') == 'EL BIERZO' ? $medida['caja'] : '0')."',
								'".'0'."'".
								(config('app.empresa') == 'Calzados Ferli' ? 
								",'".'0'."',
								'".$medida['codigocombinacion']."'" :
								""
								).
								(config('app.empresa') == 'EL BIERZO' ? 
								",'".$codigoTransporte."',
								'".$medida['pieza']."'" :
								""
								).
								(config('app.empresa') == 'AGG' ? 
								",'".$medida['cantidad']."',
								'".$empresa."'" :
								""
								)
					);

				if ($servidor != null)
				{
					$data['servidor'] = $servidor;
					$data['ifx_server'] = $ifx_server;
				}

				if ($this->flGrabaComprobanteDividido)
				{
					$data['path_sistema'] = '/usr2/villafranca';
				}

				$errStkmov = $this->apiCallAnitaEscritura($apiAnita, $data, 'stkmov insert');
				if ($errStkmov !== null) {
					return $errStkmov;
				}				

				if (config('app.empresa') == 'Calzados Ferli')
				{
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

					if ($this->flGrabaComprobanteDividido)
					{
						$data['path_sistema'] = '/usr2/villafranca';
					}

					$errStkvmed = $this->apiCallAnitaEscritura($apiAnita, $data, 'stkvmed insert');
					if ($errStkvmed !== null) {
						return $errStkvmed;
					}				
				}
			}
		}
		// Graba leyenda de exportacion
		if (! $modoMinimoAnita && config('app.empresa') == 'Calzados Ferli')
		{
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
					
					if ($this->flGrabaComprobanteDividido)
					{
						$data['path_sistema'] = '/usr2/villafranca';
					}

					$errCompley = $this->apiCallAnitaEscritura($apiAnita, $data, 'compley insert');
					if ($errCompley !== null) {
						return $errCompley;
					}			
				}
			}
		}

		// Omitido: reserva CAEA gastronomía, o PV electrónico (C/E) con numeración ARCA/CAE.
		if (
			! $omitirNumeraAnitaFin
			&& in_array((string) ($modoFacturacionPuntoventa ?? ''), ['C', 'E'], true)
		) {
			$omitirNumeraAnitaFin = true;
		}
		if (! $omitirNumeraAnitaFin) {
			$resultadoNumera = $this->ventaRepository->numeraAnita(
				substr($venta['codigo'], 0, 3),
				$letra,
				$puntoventa,
				($this->flGrabaComprobanteDividido ? '/usr2/villafranca' : null),
			);
			if (! is_int($resultadoNumera) || $resultadoNumera <= 0) {
				$detalle = is_string($resultadoNumera) ? $resultadoNumera : 'respuesta inválida del numerador';

				return ['error' => 'Error numerador comprobante', 'mensaje' => 'No pudo numerar comprobante: '.$detalle];
			}
		}

		// Numera el remito
		if (isset($puntoventaremito) && !$this->flGrabaComprobanteDividido)
		{
			if ($this->ventaRepository->numeraAnita('REM', 'R', $puntoventaremito) == 'Error')
				return ['error' => 'Error numerador remito', 'mensaje' => 'No pudo numerar remito'];
		}

		return ['Success'];
	}

	private function calculaCondicionVenta($fecha, $total, $condicionventa_id) : array
	{
		$condicionventa = Condicionventa::with('condicionventacuotas')->where('id', $condicionventa_id)->first();

		$cuotas = [];
		if ($condicionventa_id)
		{
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
		}
		else
		{
			$cuotas[] = [
						'fechavencimiento' => $fecha,
						'total' => $total
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
        $fila = ApiAnita::primeraFilaLista($apiAnita->apiCall($data));

		if ($fila !== null && isset($fila->clico_vendedor)) {
			return $fila->clico_vendedor;
		}

		return 0;
	}

	private function esErrorDuplicadoComprobanteEnAnita(?string $mensaje): bool
	{
		if ($mensaje === null || $mensaje === '') {
			return false;
		}

		$m = mb_strtolower($mensaje);

		return str_contains($m, 'ya existe')
			|| str_contains($m, 'duplicad')
			|| str_contains($m, 'unique')
			|| str_contains($m, 'duplicate');
	}

	/**
	 * @param  array{error: string, mensaje?: string}|string  $resultado
	 */
	private function esResultadoGrabaAnitaDuplicado($resultado): bool
	{
		if (! is_array($resultado) || ! isset($resultado['error'])) {
			return false;
		}

		return $this->esErrorDuplicadoComprobanteEnAnita(
			(string) ($resultado['mensaje'] ?? $resultado['error'] ?? ''),
		);
	}

	/**
	 * Tras fallo de grabaAnita: liberar solo si el comprobante quedó en Informix sin cerrar el flujo (huérfano).
	 *
	 * @param  array{error: string, mensaje?: string}|string  $resultadoGrabaAnita
	 */
	private function debeLiberarComprobanteHuerfanoEnAnita(
		$resultadoGrabaAnita,
		array $venta,
		string $letra,
		$puntoventa,
		$empresaCodigo = null,
		?string $modoFacturacionPuntoventa = null,
	): bool {
		if (! is_array($resultadoGrabaAnita) || ! isset($resultadoGrabaAnita['error'])) {
			return false;
		}

		if ($resultadoGrabaAnita['error'] === 'Errvend') {
			return false;
		}

		$tipo = substr((string) ($venta['codigo'] ?? ''), 0, 3);
		$numero = $venta['numerocomprobante'] ?? null;
		if ($tipo === '' || $numero === null) {
			return false;
		}

		if ($this->esResultadoGrabaAnitaDuplicado($resultadoGrabaAnita)) {
			return true;
		}

		return (int) self::buscaVentaAnita($tipo, $letra, $puntoventa, $numero, $empresaCodigo, $modoFacturacionPuntoventa) === (int) $numero;
	}

	/**
	 * Graba en Anita; si el número ya existe (huérfano tras rollback), lo borra y reintenta una vez.
	 * No consulta Anita antes de grabar (evita latencia en cada factura).
	 *
	 * @return array{error: string, mensaje?: string}|string
	 */
	private function grabaAnitaConReintentoPorDuplicado(
		$puntoventa,
		string $letra,
		$puntoventaremito,
		$numeroremito,
		array $venta,
		$dataCAE,
		$conceptostotales,
		$cuentacorriente,
		$datatalle,
		$signo,
		$codigoTipoTransaccion,
		$pedido_id,
		$flGrabaStock,
		$numeroOrdenventa,
		$codigoCentrocosto,
		$referenciaFactura,
		$empresaCodigo,
		$servidor = null,
		$ifx_server = null,
		bool $modoMinimoAnita = false,
		bool $sinCuentaCorrienteAnita = false,
		bool $omitirStkmovAnita = false,
		bool $omitirNumeraAnitaFin = false,
		?string $modoFacturacionPuntoventa = null,
	) {
		$anita = self::grabaAnita(
			$puntoventa,
			$letra,
			$puntoventaremito,
			$numeroremito,
			$venta,
			$dataCAE,
			$conceptostotales,
			$cuentacorriente,
			$datatalle,
			$signo,
			$codigoTipoTransaccion,
			$pedido_id,
			$flGrabaStock,
			$numeroOrdenventa,
			$codigoCentrocosto,
			$referenciaFactura,
			$servidor,
			$ifx_server,
			$modoMinimoAnita,
			$sinCuentaCorrienteAnita,
			$omitirStkmovAnita,
			$omitirNumeraAnitaFin,
			$modoFacturacionPuntoventa,
		);

		if (! $this->debeLiberarComprobanteHuerfanoEnAnita($anita, $venta, $letra, $puntoventa, $empresaCodigo, $modoFacturacionPuntoventa)) {
			return $anita;
		}

		$tipo = substr((string) ($venta['codigo'] ?? ''), 0, 3);
		$numero = $venta['numerocomprobante'];

		self::borraAnita($tipo, $letra, $puntoventa, $numero, $empresaCodigo, $modoMinimoAnita, $modoFacturacionPuntoventa);

		return self::grabaAnita(
			$puntoventa,
			$letra,
			$puntoventaremito,
			$numeroremito,
			$venta,
			$dataCAE,
			$conceptostotales,
			$cuentacorriente,
			$datatalle,
			$signo,
			$codigoTipoTransaccion,
			$pedido_id,
			$flGrabaStock,
			$numeroOrdenventa,
			$codigoCentrocosto,
			$referenciaFactura,
			$servidor,
			$ifx_server,
			$modoMinimoAnita,
			$sinCuentaCorrienteAnita,
			$omitirStkmovAnita,
			$omitirNumeraAnitaFin,
			$modoFacturacionPuntoventa,
		);
	}

	// Busca si existe la factura
	private function buscaVentaAnita($tipo, $letra, $puntoventa, $numero, $empresaCodigo = null, ?string $modoFacturacionPuntoventa = null)
	{
		$tipoVenta = KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge(
			(string) $tipo,
			(string) $puntoventa,
			$empresaCodigo,
			$modoFacturacionPuntoventa,
		);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'tabla' => 'venta', 
						'campos' => '
							ven_nro
						' ,
						'whereArmado' => " WHERE ven_tipo = '".$tipoVenta."' AND
												ven_letra = '".$letra."' AND
												ven_sucursal = '".$puntoventa."' AND
												ven_nro = '".$numero."'
						" );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}							
		$raw = $apiAnita->apiCallEscritura($data);
		$err = ApiAnita::extraerMensajeError($raw);
		if ($err !== null) {
			throw new Exception('Error en grabación Anita (consulta comprobante): '.$err);
		}

		// Sin filas = comprobante no existe aún en Informix (caso esperado antes de grabar).
		$fila = ApiAnita::primeraFilaLista((string) $raw);
		if ($fila !== null && isset($fila->ven_nro)) {
			return $fila->ven_nro;
		}

		return 0;
	}

	/**
	 * Último número de comprobante en Anita (PV mod A / CAEA) para numeración multi-lote.
	 */
	public function ultimoNumerocomprobanteAnitaCaea(object $puntoventa, object $tipotransaccion, string $letraComprobante): int
	{
		if (($puntoventa->modofacturacion ?? '') !== 'A') {
			return 0;
		}

		$codigoTipoTransaccion = (string) ($tipotransaccion->codigo ?? '');
		if ($codigoTipoTransaccion >= '200') {
			$tipoAnita = substr((string) ($tipotransaccion->abreviatura ?? ''), 0, 1).'CE';
		} else {
			$tipoAnita = (string) ($tipotransaccion->abreviatura ?? '');
		}

		$letra = trim($letraComprobante) !== '' ? $letraComprobante : 'B';

		return max(0, (int) $this->buscaUltimoNumeroComprobante($tipoAnita, $letra, $puntoventa));
	}

	// Busca el ultimo numero de comprobante (Anita venta, acotado por tipo bridge de la empresa).
	private function buscaUltimoNumeroComprobante($tipo, $letra, $puntoventa, ?string $empresaCodigo = null, ?string $modoFacturacion = null)
	{
		$sucursal = is_object($puntoventa) ? (string) ($puntoventa->codigo ?? '') : (string) $puntoventa;
		$modo = $modoFacturacion ?? (is_object($puntoventa) ? ($puntoventa->modofacturacion ?? null) : null);

		if ($empresaCodigo === null && is_object($puntoventa) && ! empty($puntoventa->empresa_id)) {
			$empresaCodigo = Empresa::query()->whereKey((int) $puntoventa->empresa_id)->value('codigo');
		}

		$tipoVenta = KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge(
			(string) $tipo,
			$sucursal,
			$empresaCodigo,
			$modo,
		);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'tabla' => 'venta', 
						'campos' => '
							max(ven_nro) as ultimonumero
						' ,
						'whereArmado' => " WHERE ven_tipo = '".$tipoVenta."' AND
												ven_letra = '".$letra."' AND
												ven_sucursal = '".$sucursal."'
						" );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}							
		$filaUltimo = ApiAnita::primeraFilaLista($apiAnita->apiCall($data));

		if ($filaUltimo !== null && isset($filaUltimo->ultimonumero)) {
			return $filaUltimo->ultimonumero;
		}

		return 0;
	}

	/**
	 * @param  array<string, mixed>|null  $opcionesEmision
	 */
	private function anitaTrasCommitAlFacturarHabilitado(?array $opcionesEmision): bool
	{
		$configKey = (is_array($opcionesEmision) && ! empty($opcionesEmision['origen_estacionamiento']))
			? 'estacionamiento.anita_tras_commit_al_facturar'
			: 'gastronomia.anita_tras_commit_al_facturar';

		return filter_var(config($configKey, true), FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * Replica en Informix una venta gastronomía diferida (post-commit de emitir-factura).
	 *
	 * @param  array<string, mixed>  $anitaPendiente
	 */
	public function ejecutarAnitaPendienteGastronomia(array $anitaPendiente): void
	{
		$venta = $anitaPendiente['venta'] ?? null;
		if (! is_array($venta)) {
			throw new \InvalidArgumentException('anita_pendiente sin datos de venta.');
		}

		GastronomiaEmisionProfiler::activo()?->marcar('anita_graba_inicio');
		$anita = $this->grabaAnitaConReintentoPorDuplicado(
			$anitaPendiente['puntoventa_codigo'] ?? 0,
			(string) ($anitaPendiente['letra'] ?? ''),
			0,
			0,
			$venta,
			$anitaPendiente['data_cae'] ?? [],
			$anitaPendiente['conceptos_totales'] ?? [],
			$anitaPendiente['cuentacorriente'] ?? [],
			$anitaPendiente['data_factura'] ?? [],
			$anitaPendiente['signo'] ?? 1.,
			$anitaPendiente['codigo_tipo_transaccion'] ?? '',
			null,
			true,
			(int) ($anitaPendiente['numero_orden_venta'] ?? 0),
			(int) ($anitaPendiente['codigo_centrocosto'] ?? 0),
			(string) ($anitaPendiente['referencia_factura'] ?? ''),
			$anitaPendiente['empresa_codigo'] ?? '',
			null,
			null,
			! empty($anitaPendiente['modo_minimo_anita']),
			! empty($anitaPendiente['omitir_cuenta_corriente_anita']),
			! empty($anitaPendiente['omitir_stkmov_anita']),
			! empty($anitaPendiente['omitir_numera_anita_fin']),
			$anitaPendiente['modo_facturacion_puntoventa'] ?? null,
		);
		GastronomiaEmisionProfiler::activo()?->marcar('anita_graba_fin');

		if (is_array($anita) && isset($anita['error'])) {
			if ($anita['error'] === 'Errvend') {
				throw new \RuntimeException('Error en grabación Anita: el cliente no tiene vendedor asignado.');
			}

			$detalle = trim((string) ($anita['mensaje'] ?? $anita['error'] ?? 'Error desconocido'));

			throw new \RuntimeException('Error en grabación Anita: '.$detalle);
		}
	}

	/**
	 * Borra factura en Anita (ventas +, opcionalmente, contab).
	 * Detalle (stkmov, vengrav, …) antes que cabecera (venta). Cada DELETE es best-effort:
	 * un fallo en una tabla no impide borrar el resto (evita huérfanos tras error 239).
	 *
	 * @param  bool  $omitirContabilidadAnita  true en gastronomía modo mínimo: no toca subdiario/ctamov (nunca se insertaron).
	 */
	public function borraAnita($tipo, $letra, $puntoventa, $numero, $empresa, bool $omitirContabilidadAnita = false, ?string $modoFacturacionPuntoventa = null)
	{
		$tipo = (string) $tipo;
		$letra = (string) $letra;
		$puntoventa = (string) $puntoventa;
		$numero = (string) $numero;

		if (config('app.empresa') == 'Calzados Ferli') {
			$this->borraAnitaDeleteSeguro('stkvmed', 'stkvmed delete', 'ventas', "
				WHERE stkvm_tipo = '".$tipo."' AND
					stkvm_letra = '".$letra."' AND
					stkvm_sucursal = '".$puntoventa."' AND
					stkvm_nro = '".$numero."'
			");
		}

		$this->borraAnitaDeleteSeguro('stkmov', 'stkmov delete', 'ventas', "
			WHERE stkv_tipo = '".$tipo."' AND
				stkv_letra = '".$letra."' AND
				stkv_sucursal = '".$puntoventa."' AND
				stkv_nro = '".$numero."'
		");

		if (config('app.empresa') == 'Calzados Ferli') {
			$this->borraAnitaDeleteSeguro('compley', 'compley delete', 'ventas', "
				WHERE compl_tipo = '".$tipo."' AND
					compl_letra = '".$letra."' AND
					compl_sucursal = '".$puntoventa."' AND
					compl_nro = '".$numero."'
			");
		}

		$this->borraAnitaDeleteSeguro('compaux', 'compaux delete', 'ventas', "
			WHERE compa_tipo = '".$tipo."' AND
				compa_letra = '".$letra."' AND
				compa_sucursal = '".$puntoventa."' AND
				compa_nro_fact = '".$numero."'
		");

		$this->borraAnitaDeleteSeguro('comprob', 'comprob delete', 'ventas', "
			WHERE comp_tipo = '".$tipo."' AND
				comp_letra = '".$letra."' AND
				comp_sucursal = '".$puntoventa."' AND
				comp_nro_fact = '".$numero."'
		");

		$this->borraAnitaDeleteSeguro('climov', 'climov delete', 'ventas', "
			WHERE cliv_tipo = '".$tipo."' AND
				cliv_letra = '".$letra."' AND
				cliv_sucursal = '".$puntoventa."' AND
				cliv_nro = '".$numero."'
		");

		$this->borraAnitaDeleteSeguro('venibr', 'venibr delete', 'ventas', "
			WHERE veni_tipo = '".$tipo."' AND
				veni_letra = '".$letra."' AND
				veni_sucursal = '".$puntoventa."' AND
				veni_nro = '".$numero."'
		");

		$this->borraAnitaDeleteSeguro('vengrav', 'vengrav delete', 'ventas', "
			WHERE veng_tipo = '".$tipo."' AND
				veng_letra = '".$letra."' AND
				veng_sucursal = '".$puntoventa."' AND
				veng_nro = '".$numero."'
		");

		$this->borraAnitaDeleteSeguro('vencae', 'vencae delete', 'ventas', "
			WHERE venc_tipo = '".$tipo."' AND
				venc_letra = '".$letra."' AND
				venc_sucursal = '".$puntoventa."' AND
				venc_nro = '".$numero."'
		");

		$tipoVentaAnita = KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge($tipo, $puntoventa, $empresa, $modoFacturacionPuntoventa);

		$this->borraAnitaDeleteSeguro('venta', 'venta delete', 'ventas', "
			WHERE ven_tipo = '".$tipoVentaAnita."' AND
				ven_letra = '".$letra."' AND
				ven_sucursal = '".$puntoventa."' AND
				ven_nro = '".$numero."'
		");

		if (! $omitirContabilidadAnita) {
			$this->borraAnitaDeleteSeguro('subdiario', 'subdiario delete', 'contab', "
				WHERE subd_sistema='V' AND subd_tipo = '".$tipo."' AND
					subd_letra = '".$letra."' AND
					subd_sucursal = '".$puntoventa."' AND
					subd_nro = '".$numero."'
			");

			$this->borraAnitaDeleteSeguro('ctamov', 'ctamov delete', 'contab', "
				WHERE ctav_empresa='".$empresa."' AND ctav_tipo = '".$tipo."' AND
					ctav_letra = '".$letra."' AND
					ctav_sucursal = '".$puntoventa."' AND
					ctav_nro = '".$numero."'
			");
		}
	}

	/**
	 * DELETE en Informix por comprobante; registra warning y continúa si falla (p. ej. tabla vacía o 239).
	 */
	private function borraAnitaDeleteSeguro(
		string $tabla,
		string $contexto,
		string $sistema,
		string $whereArmado,
	): void {
		$apiAnita = new ApiAnita();
		$data = [
			'acc' => 'delete',
			'sistema' => $sistema,
			'tabla' => $tabla,
			'whereArmado' => $whereArmado,
		];

		if ($this->flGrabaComprobanteDividido) {
			$data['path_sistema'] = '/usr2/villafranca';
		}

		try {
			$apiAnita->apiCallEscritura($data, $contexto, 'facturacion.anita_bridge.fallo');
		} catch (\Throwable $e) {
			Log::warning('facturacion.anita_bridge.borra_omitido', [
				'contexto' => $contexto,
				'tabla' => $tabla,
				'msg' => $e->getMessage(),
			]);
		}
	}

	public function grabaVenCae($tipo, $letra, $puntoventa, $numerocomprobante, $cae, $fechavencimientocae)
	{
		// Graba cae en anita
		$apiAnita = new ApiAnita();

		if (config('app.empresa') == 'AGG')
			$data = array( 	'tabla' => 'vencae', 	
						'acc' => 'insert',
						'campos' => ' 
							venc_tipo, venc_letra, venc_sucursal, venc_nro, venc_nro_cae, venc_fecha_vto',
						'valores' => "
							'".$tipo."',
							'".$letra."',
							'".$puntoventa."',
							'".$numerocomprobante."',
							'".$cae."',
							'".$fechavencimientocae."'
						"
				);
		else
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
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}							
		$errVencae = $this->apiCallAnitaEscritura($apiAnita, $data, 'vencae insert');
		if ($errVencae !== null) {
			return 'Error';
		}

		return 'Success';
	}

	public function leeFactura($id)
	{
		// Lee venta
		return $this->ventaRepository->find($id);

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
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}					
		$fila = ApiAnita::primeraFilaLista($apiAnita->apiCall($data));
		if ($fila === null || ! isset($fila->num_ult_numero)) {
			throw new Exception('No pudo leer numerador de operación en Anita');
		}

		$numeroOperacion = (int) $fila->num_ult_numero + 1;

		// Actualiza numero
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 
					'tabla' => 'numerador', 
					'valores' => 
						" num_ult_numero = '".$numeroOperacion."' ", 
					'whereArmado' => " WHERE num_clave = '500' " );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}								
		$errNumerador = $this->apiCallAnitaEscritura($apiAnita, $data, 'numerador update');
		if ($errNumerador !== null) {
			throw new Exception($errNumerador['mensaje']);
		}

		return $numeroOperacion;
	}

	public function armaContabilidad($dataFactura, $conceptostotales, $empresa_id, $total)
	{
		$asientoContable = [];

		// Saca el subtotal
		$subTotal = 0;
		foreach ($conceptostotales as $conc)
		{
			// Graba solo los importes distintos a 0
			if (str_contains($conc['concepto'], 'Exento') ||
				str_contains($conc['concepto'], 'Gravado'))
				$subTotal = $conc['importe'];
		}

		if (strtoupper(config('app.empresa')) == "EL BIERZO")
			$cuentaVenta = config('facturacion.CUENTACONTABLE_VENTA');

		if (strtoupper(config('app.empresa')) == 'AGG')
		{
			if (isset($dataFactura[0]['cuentacontable_id']))
				$cuentaVenta = config('facturacion.CUENTACONTABLE_VENTA');
			else
				$cuentaVenta = config('ordenventa.CUENTAVENTA');
		}
		if (config('facturacion.USA_DETRACCION') == 'S')
		{
			$cuentacontable = $this->cuentacontableRepository->findPorCodigo($empresa_id, $cuentaVenta);

			if ($cuentacontable)
			{
				for ($i = 0, $flEncontro = false; $i < count($asientoContable); $i++)
				{
					if ($asientoContable[$i]['cuentacontable_id'] == $cuentacontable->id)
					{
						$flEncontro = true;
						break;
					}
				}
				if (!$flEncontro)						
					$asientoContable[] = [	
										'empresa_id' => $empresa_id,
										'cuentacontable_id' => $cuentacontable->id,
										'monto' => $subTotal
									];
				else
					$asientoContable[$i]['monto'] += $subTotal;
			}
		}
		else
		{
			foreach ($dataFactura as $item)
			{
				$monto = $item['cantidad'] * $item['precio'];

				if ($monto != 0)
				{
					if ($item['cuentacontable_id'] > 0)
					{
						for ($i = 0, $flEncontro = false; $i < count($asientoContable); $i++)
						{
							if ($asientoContable[$i]['cuentacontable_id'] == $item['cuentacontable_id'])
							{
								$flEncontro = true;
								break;
							}
						}
						if (!$flEncontro)
							$asientoContable[] = [	
												'empresa_id' => $empresa_id,
												'cuentacontable_id' => $item['cuentacontable_id'],
												'monto' => $monto
											];			
						else
							$asientoContable[$i]['monto'] += $monto;
					}
					else
					{
						$cuentacontable = $this->cuentacontableRepository->findPorCodigo($empresa_id, $cuentaVenta);

						$cuentacontable_id = 0;
						
						if ($cuentacontable)
						{
							for ($i = 0, $flEncontro = false; $i < count($asientoContable); $i++)
							{
								if ($asientoContable[$i]['cuentacontable_id'] == $item['cuentacontable_id'])
								{
									$flEncontro = true;
									break;
								}
							}
							if (!$flEncontro)						
								$asientoContable[] = [	
													'empresa_id' => $empresa_id,
													'cuentacontable_id' => $cuentacontable->id,
													'monto' => $monto
												];
							else
								$asientoContable[$i]['monto'] += $monto;
						}
					}
				}
			}
		}
		// Barre por cada concepto para grabar asiento contable
		foreach ($conceptostotales as $conc)
		{
			// Graba solo los importes distintos a 0
			if ($conc['importe'] != 0 && $conc['concepto'] !== 'Subtotal')
			{
				$total = $conc['importe'];
				$cuenta = '';

				// Ingresos brutos
				if (strpos($conc['concepto'], 'Perc.') !== false &&
					array_key_exists('provincia_id', $conc))
				{
					$cuentacontable = $this->provincia_cuentacontableiibbRepository->leePorProvincia($conc['provincia_id'], $empresa_id);

					if (isset($cuentacontable[0]))
						$cuenta = $cuentacontable[0]->cuentacontable_id;
				}
				
				// Percepcion iva
				if (strpos($conc['concepto'], 'IVA') !== false)
				{
					$cuenta = config('facturacion.CUENTACONTABLE_PERCEPCION_IVA');

					$cuentacontable = $this->cuentacontableRepository->findPorCodigo($empresa_id, $cuenta);

					$cuenta = 0;
					if ($cuentacontable)
						$cuenta = $cuentacontable->id;
				}
				
				// Iva
				if (strpos($conc['concepto'], 'Iva') !== false)
				{
					$cuentacontable = $this->impuesto_cuentacontableRepository->leePorImpuesto($conc['impuesto_id'], $empresa_id);

					if (isset($cuentacontable[0]))
						$cuenta = $cuentacontable[0]->cuentacontable_id;	
					else
						throw new Exception('Error en cuenta contable de impuesto '.$conc['impuesto_id']);			
				}

				// Agrega total de logistica
				if (strtoupper(config('app.empresa')) == 'EL BIERZO')
				{
					if (strpos($conc['concepto'], 'Logistica') !== false)
					{
						$cuenta = config('facturacion.CUENTACONTABLE_LOGISTICA');	

						$cuentacontable = $this->cuentacontableRepository->findPorCodigo($empresa_id, $cuenta);

						$cuenta = 0;
						if ($cuentacontable)
							$cuenta = $cuentacontable->id;
					}
				}

				if ($cuenta != '')
					$asientoContable[] = ['empresa_id' => $empresa_id,
									'cuentacontable_id' => $cuenta,
									'monto' => $total];
			}
		}
		return $asientoContable;
	}

	private function grabaAsientoContable($asientocontable, $empresa_id, $fecha, $venta_id, $observacion, $centrocosto_id,
											$moneda_id, $cotizacion, $signo, $contrapartida_id, $tipo, $letra, $sucursal, $nro,
											?string $modoFacturacionPv = null)
	{
		PeriodoContableCierreSupport::assertOperacionPermitida(
			(int) $empresa_id,
			(string) $fecha,
			PeriodoContableCierreSupport::ALCANCE_FACTURACION,
			null,
			['modofacturacion_pv' => $modoFacturacionPv]
		);

		// Busca tipo de asiento de ventas
		$tipoasiento = $this->tipoasientoRepository->findPorAbreviatura('VTA');

		if ($tipoasiento)
			$data['tipoasiento_id'] = $tipoasiento->id;
		else
			throw new Exception('Error en grabacion, no existe tipo de asiento de ventas');

		$data['empresa_id'] = $empresa_id;
		$data['fecha'] = $fecha;
		$data['venta_id'] = $venta_id;
		$data['observacion'] = $observacion;

		$data['tipo'] = $tipo;
		$data['letra'] = $letra;
		$data['sucursal'] = $sucursal;
		$data['nro'] = $nro;

		// Arma tablas para grabar en anita
		$centrocosto_ids = [];
		$cuentacontable_ids = [];
		$debes = [];
		$haberes = [];
		$cuentacontable_ids = [];
		$observaciones = [];
		$moneda_ids = [];
		$cotizaciones = [];
		$totalMonto = 0;
		foreach ($asientocontable as $imputacion)
		{
			$cuentacontable_ids[] = $imputacion['cuentacontable_id'];

			if ($signo < 0 && $imputacion['monto'] != 0)
			{
				$debes[] = $imputacion['monto'];
				$haberes[] = '';

				$totalMonto += $imputacion['monto'];
			}
			elseif ($signo > 0 && $imputacion['monto'] != 0)
			{
				$debes[] = '';
				$haberes[] = $imputacion['monto'];

				$totalMonto -= $imputacion['monto'];
			}
			
			$centrocosto_ids[] = $centrocosto_id;
			$observaciones[] = $observacion;
			$moneda_ids[] = $moneda_id;
			$cotizaciones[] = $cotizacion;
		}
		// Agrega contrapartida
		if (abs($totalMonto) > 0.009)
		{
			// Busca cuenta contable por ID
			$cuentacontable = $this->cuentacontableRepository->find($contrapartida_id);
		
			// Con la empresa busca la cuenta real para tomar el id que corresponde hasta mejorar abm de clientes
			if ($cuentacontable)
			{
				$cuentacontablereal = $this->cuentacontableRepository->findPorCodigo($cuentacontable->empresa_id, $cuentacontable->codigo);

				$cuentacontable_ids[] = $cuentacontablereal->id;
			}
			else
			{
				$cuentacontablereal = $this->cuentacontableRepository->findPorCodigo($empresa_id, config('cliente.DEUDORES_POR_VENTAS'));

				$cuentacontable_ids[] = $cuentacontablereal->id;				
			}

			if ($totalMonto < 0)
			{
				$debes[] = abs($totalMonto);
				$haberes[] = '';
			}
			else
			{
				$debes[] = '';
				$haberes[] = abs($totalMonto);
			}

			$centrocosto_ids[] = $centrocosto_id;
			$observaciones[] = $observacion;
			$moneda_ids[] = $moneda_id;
			$cotizaciones[] = $cotizacion;
		}

		// Carga en arrays de funcion de grabacion de Anita
		$data['cuentacontable_ids'] = $cuentacontable_ids;
		$data['debes'] = $debes;
		$data['haberes'] = $haberes;
		$data['centrocosto_ids'] = $centrocosto_ids;
		$data['observaciones'] = $observaciones;
		$data['moneda_ids'] = $moneda_ids;
		$data['cotizaciones'] = $cotizaciones;

		if ($this->flGrabaComprobanteDividido)
			$data['path_sistema'] = '/usr2/villafranca';
		else
			$data['path_sistema'] = null;

		$data['modofacturacion_pv'] = $modoFacturacionPv;

		$asiento = $this->asientoRepository->create($data);

		$totalMonto = 0;

		// Graba los movimientos del asiento en ERP
		for ($i = 0; $i < count($data['cuentacontable_ids']); $i++)
		{
			$asientoContable = [];
			if ($imputacion['monto'] != 0)
			{
				// Arma el asiento contable
				$asientoContable['asiento_id'] = $asiento->id;
				$asientoContable['cuentacontable_id'] = $data['cuentacontable_ids'][$i];
				$asientoContable['moneda_id'] = $data['moneda_ids'][$i];
				$asientoContable['centrocosto_id'] = $data['centrocosto_ids'][$i];

				if (isset($data['haberes'][$i]) && is_numeric($data['haberes'][$i]))
					$asientoContable['monto'] = -$data['haberes'][$i];

				if (isset($data['debes'][$i]) && is_numeric($data['debes'][$i]))
					$asientoContable['monto'] = $data['debes'][$i];

				$asientoContable['cotizacion'] = $data['cotizaciones'][$i];
				$asientoContable['observacion'] = $data['observaciones'][$i];

				$asiento_movimiento = $this->asiento_movimientoRepository->createunique($asientoContable);
			}
		}
		return $asiento_movimiento;
	}

	// Lista factura de ventas
	public function listaUnaFactura($id)
	{
	  	ini_set('memory_limit', '512M');

		//$pdfMerger = PDFMerger::init();

		$venta = $this->ventaRepository->find($id);
		$venta->loadMissing([
			'gastronomiaEmision',
			'clientes.tipodocumentos',
			'clientes.condicioniibbs',
			'clientes.condicionventas',
			'clientes.localidades',
			'clientes.provincias',
			'clientes.paises',
			'transportes',
		]);

		$codigoTipoTransaccion = intval($venta->tipotransacciones->codigo);
		
		// Saca letra
		$codigoComprobante = explode(" ", $venta->codigo);
		$letra = substr($codigoComprobante[1], 0, 1);

		if ($letra == 'B')
			$codigoTipoTransaccion += 5;

		$nombre_pdf = 'venta-'.$venta->codigo.'-'.$venta->clientes->nombre;

		// Arma tablas para calculo de impuestos
		// Lee el cliente
		$cliente = $this->clienteQuery->traeClienteporId($venta->cliente_id);

		$tblItem = [];
		$flConDescuento = false;
		foreach($venta->venta_emisiones as $ventaItem)
		{
			$descuentoLinea = $ventaItem->descuento;

			$precioSinDescuento = $ventaItem->precio;

			// Aplica descuento integrado de linea
			$precio = $ventaItem->precio;
			if ($ventaItem->descuentointegrado)
			{
				$descuentoSeparado = explode("+", $ventaItem->descuentointegrado);

				foreach ($descuentoSeparado as $descuento)
					$precio *= (1 - ($descuento / 100));
			}

			if ($descuentoLinea > 0)
				$precioArticulo = $precio * (1 - ($descuentoLinea / 100));
			else
				$precioArticulo = $precio;		
			
			if (isset($ventaItem->articulos))
			{
				$sku = $ventaItem->articulos->sku;
				$detalle = $ventaItem->articulos->descripcion;
			}
			else
			{
				$sku = '';
				$detalle = $ventaItem->detalle;
			}

			// Calcula los kilos sin descuento y el descuento
			$kiloDescuento = 0;
			$cantidad = $ventaItem->cantidad;
			if (config('app.empresa') == 'EL BIERZO')
			{
				if ($ventaItem->descuento != 0)
				{
					$cantidad = round($ventaItem->cantidad * (1. - ($ventaItem->descuento / 100.)), 1);	
					$kiloDescuento = $ventaItem->cantidad - $cantidad;

					$flConDescuento = true;
				}
			}

			$tblItem[] = ["sku" => $sku,
					"detalle" => $detalle,
					"cantidad" => $cantidad,
					"kilodescuento" => $kiloDescuento,
					"caja" => $ventaItem->caja,
					"pieza" => $ventaItem->pieza,
					"precio" => $precioArticulo,
					"preciosindescuento" => $precioSinDescuento,
					"descuento" => $ventaItem->descuento,
					"descuentointegrado" => $ventaItem->descuentointegrado,
					"incluyeimpuesto" => $ventaItem->incluyeimpuesto,
					"moneda_id" => $ventaItem->moneda_id,
					"impuesto_id" => $ventaItem->impuesto_id,
					"id" => $ventaItem->id
					];
		}
		// Arma datos del cliente
		$datosCliente = [ "condicioniva_id" => $cliente->condicioniva_id,
						  "nroinscripcion" => $cliente->nroinscripcion,
						  "retieneiva" => $cliente->retieneiva,
						  "condicioniibb" => $cliente->condicioniibb,
						  "provincia" => $cliente->provincias->nombre??'',
						  "localidad" => $cliente->localidades->nombre??'',
						  "codigopostal" => $cliente->codigopostal,
						  "id" => $cliente->id
						];

		// Calcula impuestos
		$conceptosTotales = \App\Support\Ventas\GastronomiaVentaDisplaySupport::aplicarEtiquetaDescuentoEnConceptosTotales(
			$venta,
			$venta->venta_impuestos,
		);

		if ($venta->moneda_id == 1)
			$cotizacion = 1;
		else
			$cotizacion = $venta->cotizacion;

		$version = 1;

		switch($venta->puntoventas->modofacturacion)
		{
			case 'C':
				$tipoCodAut = 'E';
				break;
			default:
				$tipoCodAut = 'A';
				break;
		}
        $datos_cmp = [
            "ver" => $version,
            "fecha" => $venta->fecha,
            "cuit" => intval(str_replace("-", "", $venta->puntoventas->empresas->nroinscripcion)),
            "ptoVta" => intval($venta->puntoventas->codigo),
            "tipoCmp" => intval($venta->tipotransacciones->codigo),
            "nroCmp" => $venta->numerocomprobante,
            "importe" => floatval(number_format($venta->total,2,'.','')),
            "moneda" => $venta->monedas->abreviatura,
            "ctz" => floatval($cotizacion),
            "tipoDocRec" => intval($venta->clientes->tipodocumentos->codigoexterno),
            "nroDocRec" => intval(str_replace("-", "", $venta->clientes->numerodocumento)),
            "tipoCodAut" => $tipoCodAut,
            "codAut" => intval($venta->cae),
		];
		if (config('app.empresa') == "EL BIERZO")
		{
			unset($conceptosTotales[0]);

			if ($flConDescuento)
				unset($conceptosTotales[1]);
		}

		$datosJson_cmp = json_encode($datos_cmp);
		$url = 'https://www.arca.gob.ar/fe/qr/?p='.base64_encode($datosJson_cmp);

		$qrCode = QrCode::encoding('UTF-8')->format('png')->size(500)->margin(10)->generate($url);
		$output_file = '/img/qr-code/img-' . time() . '.png';
		Storage::disk('public')->put($output_file, $qrCode);

		$view =  \View::make('exports.ventas.formulariofactura', compact('venta', 'conceptosTotales', 
																		'tblItem', 'output_file', 'letra',
																		'codigoTipoTransaccion'))
			    ->render();
		$path = storage_path('pdf/ventas');

        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');
        $pdf->download($nombre_pdf.'.pdf');

		Storage::disk('public')->delete($output_file, $qrCode);

		return response()->download($path.'/'.$nombre_pdf.'.pdf');
	}

	public function editaUnaFactura($id, $flGeneraNotaDeCredito = null)
	{
	   	$data = Self::leeFactura($id);

		if (isset($flGeneraNotaDeCredito))
			$data->fecha = Carbon::now();

		$this->armarTablasVista($deposito_query, $cliente_query,
                            $condicionventa_query, $vendedor_query, $transporte_query,
                            $formapago_query, $incoterm_query,
                            $mventa_query, $modulo_query, 
                            $listaprecio_query, 
                            $tipotransaccion_query, $puntoventa_query, $lote_query, $moneda_query,
							$actividad_arca_query, $flGeneraNotaDeCredito);

		$tipotransacciondefault_id = cache()->get(generaKey('tipotransaccion'));
        $puntoventadefault_id = cache()->get(generaKey('puntoventa'));

        $urlOrigen = request()->headers->get('referer');
        $consultaFacturasDia = request()->query('origen') === 'gastronomia_facturas_dia';

        return view('ventas.factura.editar', compact('data', 
			'mventa_query', 'modulo_query', 
			'listaprecio_query', 
			'tipotransaccion_query', 'tipotransacciondefault_id', 'puntoventa_query', 'puntoventadefault_id',
            'deposito_query', 'lote_query', 'cliente_query','vendedor_query', 'condicionventa_query',
            'transporte_query', 'formapago_query', 'incoterm_query', 'flGeneraNotaDeCredito', 'moneda_query',
			'actividad_arca_query', 'urlOrigen', 'consultaFacturasDia')); 
	}

	/*
	 * Arma tablas de select para enviar a vista
	 */
	public function armarTablasVista(&$deposito_query, &$cliente_query,
                &$condicionventa_query, &$vendedor_query, &$transporte_query,
                &$formapago_query, &$incoterm_query,
                &$mventa_query, &$modulo_query, &$listaprecio_query, 
                &$tipotransaccion_query, &$puntoventa_query, &$lote_query, &$moneda_query,
				&$actividad_arca_query,
				$flGeneraNotaDeCredito = null)
    {
        $mventa_query = Mventa::all();

		if ($flGeneraNotaDeCredito)
        	$tipotransaccion_query = $this->tipotransaccionRepository->all(['C'], ['A']);
		else
			$tipotransaccion_query = $this->tipotransaccionRepository->all(['V', 'C'], ['A']);

        $puntoventa_query = $this->puntoventaRepository->all();
        $deposito_query = Depmae::query()->paraUsuarioAutorizado()->orderBy('nombre')->get();
        $cliente_query = $this->clienteQuery->allQueryCargaPedido(['id','nombre','codigo']);
        $vendedor_query = Vendedor::all();
		$vendedor_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$vendedor_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
        $condicionventa_query = Condicionventa::all();
		$vendedor_query = Vendedor::orderBy('nombre','ASC')->get();
		$transporte_query = $this->transporteRepository->all();
        $formapago_query = $this->formapagoRepository->all();
		$incoterm_query = $this->incotermRepository->all();
		$moneda_query = $this->monedaRepository->all();
		$actividad_arca_query = $this->actividad_arcaRepository->all();
    
        $modulo_query = Modulo::all();
        $listaprecio_query = Listaprecio::all();
        $lote_query = $this->loteRepository->all();
    }

	/**
	 * Completa la solicitud de CAE/CAEA pendiente (gastronomía u otros flujos con omitir_solicitud_arca_cae).
	 *
	 * @param  array<string, mixed>  $caePendiente
	 * @return array<string, mixed>|null  Datos para grabar vencae en Anita post-respuesta (gastronomía).
	 */
	public function completarSolicitudCaePendiente(array $caePendiente, bool $deferVencaeAnita = false): ?array
	{
		$opcionesArca = is_array($caePendiente['opciones_emision_arca'] ?? null)
			? $caePendiente['opciones_emision_arca']
			: [];

		$this->solicitaComprobanteARCA(
			$caePendiente['empresa'],
			$caePendiente['codigo_tipo_transaccion'],
			$caePendiente['tipo_anita'],
			$caePendiente['letra'],
			$caePendiente['puntoventa'],
			$caePendiente['numero_comprobante'],
			$caePendiente['fecha_factura'],
			$caePendiente['data_cae'],
			$caePendiente['venta_id'],
			$deferVencaeAnita,
			$opcionesArca,
		);

		if (! $deferVencaeAnita) {
			return null;
		}

		return $this->armarVencaePendienteDesdeCaePendiente($caePendiente);
	}

	/**
	 * Graba CAE en tabla vencae de Informix (Anita) para emisión gastronomía diferida.
	 *
	 * @param  array<string, mixed>  $vencaePendiente
	 */
	public function ejecutarVencaePendienteGastronomia(array $vencaePendiente): void
	{
		GastronomiaEmisionProfiler::activo()?->marcar('anita_vencae_inicio');

		$resultado = $this->grabaVenCae(
			(string) ($vencaePendiente['tipo_anita'] ?? ''),
			(string) ($vencaePendiente['letra'] ?? ''),
			$vencaePendiente['puntoventa_codigo'] ?? 0,
			$vencaePendiente['numero_comprobante'] ?? 0,
			(string) ($vencaePendiente['cae'] ?? ''),
			(string) ($vencaePendiente['fechavencimientocae'] ?? ''),
		);

		GastronomiaEmisionProfiler::activo()?->marcar('anita_vencae_fin');

		if ($resultado === 'Error') {
			throw new \RuntimeException('No pudo grabar CAE en Anita (vencae).');
		}
	}

	/**
	 * @param  array<string, mixed>  $caePendiente
	 * @return array<string, mixed>|null
	 */
	private function armarVencaePendienteDesdeCaePendiente(array $caePendiente): ?array
	{
		$puntoventa = $caePendiente['puntoventa'] ?? null;
		if ($puntoventa !== null && (string) ($puntoventa->modofacturacion ?? '') === 'M') {
			return null;
		}

		$ventaId = (int) ($caePendiente['venta_id'] ?? 0);
		if ($ventaId <= 0) {
			return null;
		}

		$venta = $this->ventaRepository->find($ventaId);
		if ($venta === null || trim((string) ($venta->cae ?? '')) === '') {
			return null;
		}

		$fechaVto = $venta->fechavencimientocae ?? null;
		if ($fechaVto === null || $fechaVto === '') {
			return null;
		}

		return [
			'tipo_anita' => (string) ($caePendiente['tipo_anita'] ?? ''),
			'letra' => (string) ($caePendiente['letra'] ?? ''),
			'puntoventa_codigo' => $puntoventa !== null ? $puntoventa->codigo : 0,
			'numero_comprobante' => $caePendiente['numero_comprobante'] ?? 0,
			'cae' => (string) $venta->cae,
			'fechavencimientocae' => date('Ymd', strtotime((string) $fechaVto)),
		];
	}

	// Solicita CAE o CAEA
	private function solicitaComprobanteARCA(
		$empresa,
		$codigoTipoTransaccion,
		$tipoAnita,
		$letra,
		$puntoventa,
		$numeroComprobante,
		$fechaFactura,
		$dataCAE,
		$venta_id,
		bool $deferVencaeAnita = false,
		array $opcionesEmisionArca = [],
	) {
		// Solicita CAE o CAEA
		$flGrabaCae = false;
		switch($puntoventa->modofacturacion)
		{
			case 'C':
			case 'E':
				try {
					GastronomiaEmisionProfiler::activo()?->marcar('arca_solicita_cae_inicio');
					$cae = $this->facturaelectronicaService->solicitaCAE(
						$empresa->nroinscripcion,
						$codigoTipoTransaccion,
						$puntoventa,
						$dataCAE,
						$opcionesEmisionArca,
					);
					GastronomiaEmisionProfiler::activo()?->marcar('arca_solicita_cae_fin');
				} catch (\Throwable $e) {
					GastronomiaEmisionProfiler::activo()?->marcar('arca_solicita_cae_fin');
					$cae = ['Error' => $e->getMessage()];
				}
				$flGrabaCae = true;

				//$cae = ['cae' => '74040779002259', 'fechavencimientocae' => '20240201'];

				if (isset($cae['Error'])) {
					$msgError = (string) $cae['Error'];
					$wsPv = (string) ($puntoventa->webservice ?? '');
					if (\App\Support\Ventas\ArcaWsfeEmisionResiliencia::esFallaComunicacionSinRespuestaClara($msgError, $wsPv)) {
						$caeRecuperado = $this->recuperarCaeTrasFallaComunicacion(
							$empresa,
							$codigoTipoTransaccion,
							$puntoventa,
							(int) $numeroComprobante,
						);
						if ($caeRecuperado !== null) {
							$cae = $caeRecuperado;
						} else {
							throw new Exception(
								'No hubo respuesta de ARCA al solicitar el CAE del comprobante '.$numeroComprobante
								.'. Se consultó el último número autorizado y ARCA no confirma ese comprobante. Detalle: '.$msgError
							);
						}
					} else {
						throw new Exception('No pudo asignar CAE. '.$msgError);
					}
				}

				if (($cae['fechavencimientocae'] ?? 0) == 0) {
					throw new Exception('No pudo asignar CAE');
				}
				break;
			case 'A':
				if ($empresa->nroinscripcion)
				{
					// PV CAEA: numeración local (Anita) al emitir; CAEA vigente en arca_caea.
					// No se informa comprobante en ARCA en línea (informe quincenal aparte).
					$cae = $this->facturaelectronicaService->buscaCAEA($empresa->nroinscripcion, $fechaFactura);

					if (isset($cae['Error'])) {
						throw new Exception('No pudo asignar CAEA, no esta pedido para la quincena');
					}
				}
				else
					throw new Exception('No pudo asignar CAEA, no tiene CUIT cargado');
				$flGrabaCae = true;
				break;
		}
		if ($flGrabaCae)
			$this->ventaRepository->update([
										'cae' => $cae['cae'], 
										'fechavencimientocae' => $cae['fechavencimientocae']
										],
										$venta_id);

		if ($puntoventa->modofacturacion != 'M' && ! $deferVencaeAnita)
		{
			GastronomiaEmisionProfiler::activo()?->marcar('anita_vencae_inicio');
			// Graba cae en Anita
			$vencae = Self::grabaVenCae($tipoAnita, $letra, $puntoventa->codigo,
						$numeroComprobante, $cae['cae'],
						date('Ymd', strtotime($cae['fechavencimientocae'])));

			if ($vencae === 'Error') {
				throw new Exception('No pudo grabar CAE en Anita (vencae).');
			}
			GastronomiaEmisionProfiler::activo()?->marcar('anita_vencae_fin');
		}

		return 'Success';
	}

	/**
	 * Tras falla de comunicación al pedir CAE: consulta último comprobante autorizado y, si coincide, recupera CAE.
	 *
	 * @return array{cae:string,fechavencimientocae:string}|null
	 */
	private function recuperarCaeTrasFallaComunicacion($empresa, $codigoTipoTransaccion, $puntoventa, int $numeroComprobante): ?array
	{
		if (! in_array($puntoventa->modofacturacion, ['C', 'E'], true)) {
			return null;
		}

		$ultimo = $this->facturaelectronicaService->traeUltimoNumeroComprobante(
			$empresa->nroinscripcion,
			$codigoTipoTransaccion,
			$puntoventa,
		);

		if ($ultimo === -1 || (int) $ultimo < $numeroComprobante) {
			return null;
		}

		$consulta = $this->facturaelectronicaService->consultaCompEnviado(
			$empresa->nroinscripcion,
			$codigoTipoTransaccion,
			$puntoventa,
			$numeroComprobante,
		);

		if (! is_array($consulta) || empty($consulta['cae'])) {
			return null;
		}

		return [
			'cae' => (string) $consulta['cae'],
			'fechavencimientocae' => (string) ($consulta['fechavencimientocae'] ?? ''),
		];
	}

	// Genera un Remito
	private function generaUnRemito($data, $cliente, $pedido, $tipoRemito, $letraRemito, $puntoVentaRemito, $codigoEmpresa)
	{
		$anteriorFlDivide = $this->flDivide;
		$this->flDivide = false;

		// Recalcula la factura
		$calculoFactura = Self::calculaFacturaPorPedido($data);

		$this->flDivide = $anteriorFlDivide;
		$dataFactura = $calculoFactura['datosfactura'];

		$totalComprobante = $calculoFactura['totalcomprobante'];

		if ($totalComprobante == 0.)
			return ['error' => 'Factura en 0'];

		$data['tipotransaccion_stock_id'] = $this->tipotransaccionStockRepository
			->findIdPorAbreviatura(config('facturacion.TIPO_REMITO'));
		$data['lote'] = '';

		$numeroRemito = $this->ventaRepository->traeUltimoNumeroRemito(config('facturacion.TIPO_REMITO'),
						config('facturacion.LETRA_REMITO'), $puntoVentaRemito);

		$articulos_id = [];
		$skus = [];
		$numeroitems = [];
		$cantidades = [];
		$piezas = [];
		$cajas = [];
		$precios = [];
		$listaprecios_id = [];
		$incluyeimpuestos = [];
		$monedas_id = [];
		$descuentos = [];

		// Graba items
		$dataArticuloMovimiento = [];
		$totalCaja = $totalKilo = $totalPieza = 0;
		$totalNeto = 0.;
		$numeroItem = 0;

		foreach ($dataFactura as $item)
		{
			$numeroItem++;

			$articulos_id[] = $item['articulo_id'];
			$skus[] = $item['sku'];
			$numeroitems[] = $numeroItem;
			$cantidades[] = $item['cantidad'];
			$piezas[] = $item['pieza'];
			$cajas[] = $item['caja'];

	        // Si el precio tiene iva incluido lo netea
			if ($item['incluyeimpuesto'] == '1')
				$precio = $item['preciosindescuento'] / (1 + ($tasa/100));
			else	
				$precio = $item['preciosindescuento'];

			$precios[] = $precio;
			$listaprecios_id[] = $item['listaprecio_id'];
			$incluyeimpuestos[] = $item['incluyeimpuesto'];
			$monedas_id[] = $item['moneda_id'];
			$descuentos[] = $item['descuento'];

			$totalCaja += $item['caja'];
			$totalPieza += $item['pieza'];
			$totalKilo += $item['cantidad'];

			$totalNeto += ($item['cantidad'] * $item['preciosindescuento']);
		}
		// Carga variables para grabacion de movimiento de stock
		$data['articulos_id'] = $articulos_id;
		$data['skus'] = $skus;
		$data['combinaciones_id'] = null;
		$data['modulos_id'] = null;
		$data['items'] = $numeroitems;
		$data['cantidades'] = $cantidades;
		$data['piezas'] = $piezas;
		$data['cajas'] = $cajas;
		$data['precios'] = $precios;
		$data['listasprecios_id'] = $listaprecios_id;
		$data['incluyeimpuestos'] = $incluyeimpuestos;
		$data['monedas_id'] = $monedas_id;
		$data['descuentos'] = $descuentos;
		$data['loteids'] = null;
		$data['medidas'] = [];
		$data['fecha'] = $data['fechafactura'];
		$data['fechaentrega'] = $data['fechafactura'];
		$data['deposito_id'] = config('facturacion.DEPOSITO_VENTA_ID');
		$data['loteimportacion_id'] = null;
		$data['codigo'] = $tipoRemito.' '.$letraRemito.' '.$puntoVentaRemito.'-'.$numeroRemito;
		$data['letra'] = $letraRemito;
		$data['puntoventa'] = $puntoVentaRemito;
		$data['numerocomprobante'] = $numeroRemito;
		$data['item'] = 0;
		$data['tipofactura'] = 'PED';
		$data['letrafactura'] = 'X';
		$data['sucursalfactura'] = '1';
		$data['numerofactura'] = $pedido->codigo;
		$data['codigocliente'] = $cliente->codigo;
		$data['codigotransporte'] = $pedido->transportes->codigo;
		$data['codigovendedor'] = $pedido->vendedores->codigo;
		$data['codigozona'] = $pedido->zonavtas->codigo;
		$data['codigoprovincia'] = $cliente->provincias->codigo;
		$data['codigosubzona'] = $cliente->subzonavtas->id ?? '0';
		$data['condicionventa_id'] = $cliente->condicionventa_id ?? 0;
		$data['vendedor_id'] = $pedido->vendedor_id;
		$data['lugarentrega'] = $pedido->lugarentrega;
		$data['transporte_id'] = $pedido->transporte_id;
		$data['codigocombinacion'] = '';
		$data['pedido'] = $pedido->codigo;
		$data['partida'] = 0;
		$data['empresa'] = $codigoEmpresa;
		$data['codigoabasto'] = $cliente->abastos->codigo ?? 0;
		$data['totalseguro'] = $totalNeto;
		$data['totalneto'] = $totalNeto;
		$data['totalcaja'] = $totalCaja;
		$data['totalkilo'] = $totalKilo;
		$data['totalpieza'] = $totalPieza;
		$data['subzona'] = $cliente->subzona_id;
		$data['oblea'] = '';
		$data['cantidadmodificada'] = $totalKilo;
		$data['usuarioalta'] = $this->nombreUsuarioAnita();
		$data['omitir_stkmov_anita'] = true;

		$this->movimientoStockService->guardaMovimientoStock($data, 'create');

		return ['factura' => $data['codigo']];
	}
}


