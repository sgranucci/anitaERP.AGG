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
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Moneda;
use App\Services\Stock\PrecioService;
use App\Services\Stock\Articulo_MovimientoService;
use App\Services\Configuracion\ImpuestoService;
use App\Services\Configuracion\CotizacionService;
use App\Services\Ventas\FacturaelectronicaService;
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
								Actividad_ArcaRepositoryInterface $actividad_arcaRepository
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

		$this->coeficienteCliente = 0;
		$this->coeficienteExtraCliente = 0;
		$this->flDivide = false;
		$this->flGrabaComprobanteDividido = false;
		$this->tasaImpuesto = 0;
		$this->puntoVentaDivision_id = 0;
		$this->numeroComprobanteDivision = 0;
		$this->numeroRemito = 0;
    }

	public function leePaginando($busqueda)
    {
        return $this->ventaRepository->leePaginando($busqueda);
    }

	public function leeSinPaginar($busqueda)
    {
        return $this->ventaRepository->leeSinPaginar($busqueda);
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

			// Lee lugar de entrega
		if ($pedido->lugarentrega == null && $pedido->cliente_entrega_id > 0)
		{
			$cliente_entrega = $this->cliente_entregaRepository->find($pedido->cliente_entrega_id);

			if ($cliente_entrega)
				$pedido->lugarentrega = $cliente_entrega->nombre;
		}

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
				if ($this->flDivide)
				{
					// Graba en Villafranca
					if ($this->flGrabaComprobanteDividido)
					{
						if ($this->coeficienteExtraCliente != 0)
							$precioUnitario = $pedido_articulo->precio * $this->coeficienteExtraCliente;

						$kilo = $pedido_articulo->pesada * $this->coeficienteCliente / 100.;
						$pieza = $pedido_articulo->pieza * $this->coeficienteCliente / 100.;
						$caja = $pedido_articulo->caja * $this->coeficienteCliente / 100.;
					}
					else // Deja el resto para grabar en Bierzo
					{
						$coeficiente = ((100. - $this->coeficienteCliente)/100.);

						$kilo = $pedido_articulo->pesada * $coeficiente;
						$pieza = $pedido_articulo->pieza * $coeficiente;
						$caja = $pedido_articulo->caja * $coeficiente;
					}
				}
				else
				{
					$kilo = $pedido_articulo->pesada;
					$pieza = $pedido_articulo->pieza;
					$caja = $pedido_articulo->caja;
				}

				if ($this->descuentoLinea != 0)
					$precioConDescuento = $precioUnitario * (1. - ($this->descuentoLinea / 100.));
				else
					$precioConDescuento = $precioUnitario;

				$dataFactura[] = ["cantidad" => $kilo,
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
				$totKilo += $pedido_articulo->pesada;
			}
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
		// Calcula impuestos
		$conceptosTotales = $this->impuestoService->calculaImpuestoVenta($dataFactura, $datosCliente, $fechaFactura, 
																			$this->flGrabaComprobanteDividido);

		// Arma total de comprobante
		$totalComprobante = $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Total', 'importe');

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
		// Controla si divide factura
		if ($pedido->transportes->tipoexpreso == '3' && 
			($tipotransaccion->codigo == '001' || $tipotransaccion->codigo == '201'))
		{
			$this->coeficienteExtraCliente = $cliente->coeficienteextra;

			if (isset($cliente->coeficientes))
			{
				$this->flDivide = true;
				$this->flGrabaComprobanteDividido = false;
				$this->coeficienteCliente = $cliente->coeficientes->porcentajedivision;
				$this->tasaImpuesto = $cliente->coeficientes->tasa;

				// Si no es toda divida genera factura por el resto en el Bierzo
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

					$numeroremito = $this->ventaRepository->traeUltimoNumeroRemito(config('facturacion.TIPO_REMITO'),
						config('facturacion.LETRA_REMITO'), $puntoventaremito->codigo);

					// Genera remito
					$retorno1 = Self::generaUnRemito($data, $cliente, $pedido, config('facturacion.TIPO_REMITO'), 
						config('facturacion.LETRA_REMITO'), $puntoventaremito->codigo, $numeroremito, $puntoventaremito->empresas->codigo);
				}

				$this->flGrabaComprobanteDividido = true;

				// Graba comprobante dividido
				$retorno2 = Self::generaUnaFacturaPorPedido($data, $cliente, $pedido);

				$retorno = [$retorno1, $retorno2];
			}
		}
		else
		{
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
		$this->codigoCuentaContable = $cliente->cuentascontables->codigo;

		if (isset($cliente->condicionventa_id))
			$condicionventa_id = $cliente->condicionventa_id;
		else
			$condicionventa_id = null;

		// Lee el tipo de transaccion
		$tipotransaccion = $this->tipotransaccionRepository->find($tipoTransaccion_id);

		// Recalcula la factura
		$calculoFactura = Self::calculaFacturaPorPedido($data);

		$dataFactura = $calculoFactura['datosfactura'];
		$conceptosTotales = $calculoFactura['conceptostotales'];
		$datosCliente = $calculoFactura['datoscliente'];
		$totalComprobante = $calculoFactura['totalcomprobante'];
		$moneda_id = $calculoFactura['datosfactura'][0]['moneda_id'];
		$centrocosto_id = null;

		$cotizacion = $this->cotizacionService->calculaCotizacionVenta($fechaFactura, $moneda_id);

		// Lee lugar de entrega
		if ($pedido->lugarentrega == null && $pedido->cliente_entrega_id > 0)
		{
			$cliente_entrega = $this->cliente_entregaRepository->find($pedido->cliente_entrega_id);

			if ($cliente_entrega)
				$pedido->lugarentrega = $cliente_entrega->nombre;
		}

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
				$venta = $this->ventaRepository->traeUltimoComprobanteVenta($tipoTransaccion_id, $puntoventa_id);
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
							'gravado' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Gravado al', 'importe'),
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
							'pais' => $cliente->paises->codigo,
							'nombrecliente' => $cliente->nombre,
							'domicilio' => $cliente->domicilio,
							'formapago' => $cliente->condicionventas->nombre,
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
						'provincia_id' => $cliente->provincia_id,
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
					// Verifica si ya existe en anita
					$ventaAnita = Self::buscaVentaAnita(substr($venta['codigo'], 0, 3), $letra, $puntoventa->codigo, $venta['numerocomprobante']);
					// Si existe retorna con error
					if ($ventaAnita == $venta['numerocomprobante'])
					{
						throw new Exception('La factura '.substr($venta['codigo'], 0, 3).' '.$letra.' '.$puntoventa->codigo.'-'.$venta['numerocomprobante'].' ya existe en ANITA');
					}

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
							'precio' => $item['precio'],
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

						$articulo_movimiento = $this->articulo_movimientoService->
										guardaArticuloMovimiento('create',
										$dataArticuloMovimiento, null);
					}
					
					// Graba contabilidad
					Self::grabaAsientoContable($asientoContable, $empresa_id, $fechaFactura, $vta->id, $detalleContable, $centrocosto_id,
											$moneda_id, $cotizacion, $signo, $cliente->cuentacontable_id,
											substr($venta['codigo'],0,3), $letra, $puntoventa->codigo, $venta['numerocomprobante']);
					
					// Marca Pedido como facturado
					$pedido = $this->pedidoRepository->update(['estadopedido' => 'Facturado'], $pedido_id);

					if ($puntoventa->modofacturacion != 'M' || $this->flGrabaComprobanteDividido)
					{
						// Graba anita por pedido
						$anita = self::grabaAnita($puntoventa->codigo, $letra, $puntoventaremito->codigo, $numeroremito,
									$venta, $dataCAE, $conceptosTotales, $cuentaCorriente, $dataFactura, $signo,
									$codigoTipoTransaccion, $pedido_id,
									true, 0, 0, $referenciaFactura);

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

					return ['factura' => substr($venta['codigo'],0,3).' '.$letra.' '.$puntoventa->codigo.'-'.$venta['numerocomprobante']];
				} catch (\Exception $e) {
					DB::rollback();

					// Borra factura de anita
					if ($venta['codigo'] ?? '')
						self::borraAnita(substr($venta['codigo'], 0, 3), $letra, 
											$puntoventa->codigo, $venta['numerocomprobante'], $empresa->codigo);

					dd($e->getMessage());

					return ['error' => $e->getMessage()];
				}
			}
		}
		else
			return 'Error con punto de venta asignado';
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
		$this->codigoCuentaContable = $cliente->cuentascontables->codigo;

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
					$venta = $this->ventaRepository->traeUltimoComprobanteVenta($tipoTransaccion_id, $puntoventa_id);
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
							'gravado' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Gravado al', 'importe'),
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
							'pais' => $cliente->paises->codigo,
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
					// Verifica si ya existe en anita
					$ventaAnita = Self::buscaVentaAnita(substr($venta['codigo'], 0, 3), $letra, $puntoventa->codigo, $venta['numerocomprobante']);
					// Si existe retorna con error
					if ($ventaAnita == $venta['numerocomprobante'])
					{
						throw new Exception('El comprobante '.$venta['numerocomprobante'].' ya existe en ANITA');
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

						// Agrega referencia a la OV
						$dataEmision = [
							'venta_id' => $vta->id,
							'numeroitem' => ++$numeroItem, 
							'detalle' => 'CC '.$codigoCentrocosto.' OV '.$numeroOrdenventa,
							'cantidad' => 0, 
							'precio' => 0
						];
						$venta_emision = $this->venta_emisionRepository->create($dataEmision);			
					}
					// Graba contabilidad
					Self::grabaAsientoContable($asientoContable, $puntoventa->empresa_id, $fechaFactura, $vta->id, 
											$detalleContable, $centrocosto_id,
											$moneda_id, $cotizacion, $signo, $cliente->cuentacontable_id,
											substr($venta['codigo'],0,3), $letra, $puntoventa->codigo, $venta['numerocomprobante']);

					// Marca Orden de venta como facturada
					$ordenventa_cuota_id = 0;
					$ordenventa = $this->ordenventaService->marcaOrdenVentaFacturada($ordenventa_id, 
						substr($venta['codigo'],0,3), $letra, $puntoventa->codigo, $venta['numerocomprobante'], $vta->id,
						$ordenventa_cuota_id);

					if ($puntoventa->modofacturacion != 'M')
					{
						// Graba anita factura por orden de venta
						$anita = self::grabaAnita($puntoventa->codigo, $letra, 0, 0,
									$venta, $dataCAE, $conceptosTotales, $cuentacorriente, $dataFactura, $signo,
									$codigoTipoTransaccion, null,
									true, $numeroOrdenventa, $codigoCentrocosto, '');

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

	// Genera factura general

	public function generaComprobanteGeneral(array $data)
	{
		// Guarda tipo de transaccion y punto de venta en cache
		Cache::forever(generaKey('tipotransaccion'), $data['tipotransaccion_id']);
		Cache::forever(generaKey('puntoventa'), $data['puntoventa_id']);

		$tipoTransaccion_id = $data['tipotransaccion_id'];
		$venta_id = $data['venta_id'];
		$cliente_id = $data['cliente_id'];
		$this->descuentoPie = $data['descuentopie'];
		$this->descuentoLinea = $data['descuentolinea'];
		$this->descuentoImportePie = $data['descuentoimportepie'];
		$fechaFactura = $data['fecha'];
		$actividad_arca_id = $data['actividad_arca_id'];

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

		// Arma array de conceptos totales segun lo calculado en el front
		$conceptosTotales = [];
		if (isset($data['conceptototales']))
		{
			for ($i = 0; $i < count($data['conceptototales']); $i++)
			{
				$concepto = $data['conceptototales'][$i];
				$baseimponible = $data['baseimponibles'][$i];
				$tasa = $data['tasatotales'][$i];
				$importe = str_replace(',', '', $data['montototales'][$i]);
				$impuesto_id = $data['impuestototal_ids'][$i];
				$provincia_id = $data['provincia_ids'][$i];

				if ($baseimponible == null)
					$baseimponible = 0;

				if ($tasa == null)
					$tasa = 0;

				// Lee el impuesto
				$impuesto = $this->impuestoRepository->findPorId($impuesto_id);
				$codigoarca = $codigo = null;
				if ($impuesto)
				{
					$codigoarca = $impuesto->codigoarca;
					$codigo = $impuesto->codigo;
				}

				// Lee la provincia para sacar la jurisdiccion
				$provincia = $this->provinciaRepository->findPorId($provincia_id);

				$jurisdiccion = null;
				if ($provincia)
					$jurisdiccion = $provincia->jurisdiccion;

				$conceptosTotales[] = [
					'concepto' => $concepto,
					'baseimponible' => $baseimponible,
					'tasa' => $tasa,
					'importe' => $importe,
					'impuesto_id' => $impuesto_id,
					'codigo' => $codigo,
					'codigoarca' => $codigoarca,
					'provincia_id' => $provincia_id,
					'jurisdiccion' => $jurisdiccion
				];
			}
		}

		// Trae el cliente 
		$cliente = $this->clienteQuery->traeClienteporId($cliente_id);
		if (!$cliente)
			return ['error' => 'Cliente inexistente'];
		
		$this->cuentacontable_id = $cliente->cuentacontable_id;
		$this->codigoCuentaContable = $cliente->cuentascontables->codigo;

		if (isset($data['condicionventa_id']))
			$condicionventa_id = $data['condicionventa_id'];
		else
		{
			if (isset($cliente->condicionventa_id))
				$condicionventa_id = $cliente->condicionventa_id;
			else
				$condicionventa_id = null;
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

		// Arma factura
		$cantItem = count($data['items']);

		$dataFactura = [];
		for ($i = 0; $i < $cantItem; $i++)
		{
			// Trae el articulo
			if ($data['articulo_ids'][$i] > 0)
			{
				$articulo = $this->articuloQuery->traeArticuloPorId($data['articulo_ids'][$i]);

				if (!$articulo)
					return ['error' => 'Artículo inexistente'];
			}

			$dataFactura[] = [
				"precio" => str_replace(',', '', $data['precios'][$i]),
				"cantidad" => str_replace(',', '', $data['cantidades'][$i]),
				"descuento" => $this->descuentoLinea,
				"descuentointegrado" => '',
				"descuentofinal" => $this->descuentoPie,
				"descuentointegradofinal" => '',
				"incluyeimpuesto" => $data['incluyeimpuestos'][$i],
				"impuesto_id" => $data['impuesto_ids'][$i],
				'moneda_id' => $data['monedas_id'][$i],
				'ordenventa_id' => $ordenventa_id,
				'detalle' => $data['descripcionarticulos'][$i],
				'cuentacontable_id' => $articulo->cuentacontableventa_id ?? 0
			];
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
					$venta = $this->ventaRepository->traeUltimoComprobanteVenta($tipoTransaccion_id, $puntoventa_id);
					if ($venta)
						$numero = $venta->numerocomprobante;
					else	
						$numero = 0;
					break;
			}			
			$centrocosto_id = 0;
			if ($numero != -1)
			{
				// Arma asiento
				if ($signo == -1)
				{
					$asientoContable = [];
					foreach ($factura->asientos[0]->asiento_movimientos as $movimiento)
					{
						$asientoContable[] = ['empresa_id' => $factura->asientos[0]->empresa_id,
											'cuentacontable_id' => $movimiento->cuentacontable_id,
											'monto' => $movimiento->monto*-1
											];

						$centrocosto_id = $movimiento->centrocosto_id;
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

					$dataCAE = [
							'codigoempresa' => $empresa->codigo,
							'tipodoc' => $cliente->tipodocumentos->codigoexterno,
							'numerodocumento' => $cliente->numerodocumento,
							'condicioniva_id' => $cliente->condicioniva_id,
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
							'cotizacion' => ($codigoMoneda == 'PES' ? 1. : $cotizacion),
							'tributos' => $tributos,
							'impuestos' => $impuestos,
							'comprobantesasociados' => $comprobantesAsociados,
							'fechaasignaciondesde' => date('Ymd', strtotime($fechaAsignacion)),
							'fechaasignacionhasta' => date('Ymd', strtotime($fechaFactura)),
							'pais' => $cliente->paises->codigo,
							'nombrecliente' => $cliente->nombre,
							'domicilio' => $cliente->domicilio,
							'formapago' => $cliente->condicionventas->nombre ?? 'CONTADO',
							'formapagoexportacion' => null,
							'incoterms' => null,
							'numeroordenventa' => '',
							'items' => $dataFactura
					];
				}
				$graba = Self::grabaFacturaERP($empresa, $codigoTipoTransaccion, $tipotransaccion, $fechaFactura,  
									$cliente, $totalComprobante, $moneda_id, $cotizacion, $leyenda,  
									$letra, $puntoventa, $numero, $ordenventa_id, $conceptosTotales, $cuentacorriente,
									$dataFactura, $asientoContable, $detalleContable, $signo, $centrocosto_id, $codigoCentrocosto,
									$dataCAE, $venta_id, $referenciaFactura, $actividad_arca_id);
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
						  "numerodocumento" => $cliente->numerodocumento,
						  "retieneiva" => $cliente->retieneiva,
						  "condicioniibb" => $cliente->condicioniibb,
						  "provincia" => $cliente->provincia_id,
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
				$venta = $this->ventaRepository->traeUltimoComprobanteVenta($tipoTransaccion_id, $puntoventa_id);
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
						'provincia_id' => $cliente->provincia_id,
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
						$anita = self::grabaAnita($puntoventa->codigo, $letra, $puntoventaremito->codigo, $numeroremito,
									$venta, $dataCAE, $conceptosTotales, $cuentacorriente, $dataFactura, $signo,
									$codigoTipoTransaccion, null,
									true, 0, 0, '');

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
									$dataCAE, $venta_id, $referenciaFactura, $actividad_arca_id)
	{
		$numeroOrdenventa = 0;

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

			// Verifica si ya existe en anita
			$ventaAnita = Self::buscaVentaAnita(substr($venta['codigo'], 0, 3), $letra, $puntoventa->codigo, $venta['numerocomprobante']);
			// Si existe retorna con error
			if ($ventaAnita == $venta['numerocomprobante'])
			{
				throw new Exception('El comprobante '.$venta['numerocomprobante'].' ya existe en ANITA');
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
			// Arma tabla de emision del comprobante
			$numeroItem = 0;
			$dataArticuloMovimiento = [];
			foreach($dataFactura as $itemEmision)
			{
				if (isset($itemEmision['articulo_id']))
				{
					$dataArticuloMovimiento = [
						'fecha' => $fechaFactura,
						'fechajornada' => $fechaFactura,
						'tipotransaccion_id' => $tipoTransaccion_id,
						'venta_id' => $vta->id,
						'pedido_combinacion_id' => $itemEmision['pedido_combinacion_id'],
						'ordentrabajo_id' => $itemEmision['ordentrabajo_id'],
						'lote' => 0,
						'articulo_id' => $itemEmision['articulo_id'],
						'combinacion_id' => $itemEmision['combinacion_id'],
						'codigocombinacion' => $itemEmision['codigocombinacion'],
						'modulo_id' => $itemEmision['modulo_id'],
						'concepto' => $tipotransaccion->nombre,
						'cantidad' => $itemEmision['cantidad'],
						'precio' => $itemEmision['precio'],
						'costo' => 0,
						'despacho' => $itemEmision['despacho'],
						'loteimportacion_id' => $itemEmision['loteimportacion_id'],
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

				if (isset($itemEmision['articulo_id']))
				{				
					$dataTalle = [];
					$articulo_movimiento = $this->articulo_movimientoService->
								guardaArticuloMovimiento('create',
								$dataArticuloMovimiento, $dataTalle);
				}
			}
			// Graba contabilidad
			Self::grabaAsientoContable($asientoContable, $puntoventa->empresa_id, $fechaFactura, $vta->id, 
									$detalleContable, $centrocosto_id,
									$moneda_id, $cotizacion, $signo, $cliente->cuentacontable_id,
									substr($venta['codigo'],0,3), $letra, $puntoventa->codigo, $venta['numerocomprobante']);

			if ($puntoventa->modofacturacion != 'M')
			{
				// Graba anita
				$anita = self::grabaAnita($puntoventa->codigo, $letra, 0, 0,
							$venta, $dataCAE, $conceptosTotales, $cuentacorriente, $dataFactura, $signo,
							$codigoTipoTransaccion, null,
							true, $numeroOrdenventa, $codigoCentrocosto, $referenciaFactura);

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

			// Si tiene orden de venta y es nota de credito anula marca en OV
			if ($ordenventa_id > 0 && $signo == -1)
			{
				// Marca Orden de venta como facturada
				$ordenventa = $this->ordenventaService->anulaMarcaOrdenVentaFacturada($ordenventa_id, 
					substr($venta['codigo'],0,3), $letra, $puntoventa->codigo, $venta['numerocomprobante'], $vta->id);
			}

			DB::commit();

			return ['factura' => substr($venta['codigo'],0,3).' '.$letra.' '.$puntoventa->codigo.'-'.$venta['numerocomprobante'], 'error' => ''];
		} catch (\Exception $e) {
			DB::rollback();

			// Borra factura de anita
			if ($venta['codigo'] ?? '')
				self::borraAnita(substr($venta['codigo'], 0, 3), $letra, 
									$puntoventa->codigo, $venta['numerocomprobante'], $empresa->codigo);

			dd($e->getMessage());

			return ['error' => $e->getMessage()];
		}
	}

	// Graba factura en Anita
	public function grabaAnita($puntoventa, $letra, $puntoventaremito, $numeroremito, $venta, 
								$dataCAE, $conceptostotales, $cuentacorriente, $datatalle, $signo, 
								$codigoTipoTransaccion, $pedido_id,
								$flGrabaStock, $numeroOrdenventa, $codigoCentrocosto, $referenciaFactura, 
								$servidor = null, $ifx_server = null)
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
		foreach ($conceptostotales as $concepto)
		{
			if (array_key_exists('jurisdiccion', $concepto) && $concepto['jurisdiccion'] != null)
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

			if (strpos($concepto['concepto'], 'Abasto') !== false)
				$totalAbasto = $concepto['importe'];

			if (strpos($concepto['concepto'], 'Logistica') !== false)
				$totalLogistica = $concepto['importe'];			
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
							'".substr($venta['codigo'], 0, 3)."',
							'".$letra."', '".$puntoventa."', '".$venta['numerocomprobante']."',
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
							'".$condicionventa."',
							'".'0'."',
							'".$nombre."',
							'".$domicilio."',
							'".$nombreLocalidad."',
							'".$nombreProvincia."',
							'".$codigopostal."',
							'".$numerodocumento."',
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
							'".'0'."',
							'".$cliente->abastos->codigo."',
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
							'".$condicionventa."',
							'".'0'."',
							'".$nombre."',
							'".$domicilio."',
							'".$nombreLocalidad."',
							'".$nombreProvincia."',
							'".$codigopostal."',
							'".$numerodocumento."',
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
							'".'0'."'".(config('app.empresa') == 'AGG' ? ", '".$empresa."'":"")
					);

		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}

        $vta = $apiAnita->apiCall($data);
		if (strpos($vta, 'Error') !== false)
			return ['error' => 'Error venta', 'mensaje' => $vta];
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

					$venibr = $apiAnita->apiCall($data);

					if (strpos($venibr, 'Error') !== false)
						return ['error' => 'Error venibr', 'mensaje' => $venibr];
				}
			}
			// Graba vengrav
			if (strpos($concepto['concepto'], 'Iva') !== false)
			{
				// Graba venibr
				$apiAnita = new ApiAnita();

				$sobreTasa = 0;
				$data = array( 	'tabla' => 'vengrav', 
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
								"
						);
				if ($this->flGrabaComprobanteDividido)
				{
					$data['path_sistema'] = '/usr2/villafranca';
				}
						
				$vengrav = $apiAnita->apiCall($data);

				if (strpos($vengrav, 'Error') !== false)
					return ['error' => 'Error vengrav', 'mensaje' => $vengrav];	
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
				$puntoVentaReferencia = '';
				$numeroReferencia = '';
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
								'".'1'."',
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

			$climov = $apiAnita->apiCall($data);

			if (strpos($climov, 'Error') !== false)
				return ['error' => 'Error climov', 'mensaje' => $climov];				
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
							'".(config('app.empresa') == 'EL BIERZO' ? $this->cantidadBulto : $venta['condicionventa_id'])."',
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

		$comprob = $apiAnita->apiCall($data);
		
		if (strpos($comprob, 'Error') !== false)
			return ['error' => 'Error comprob', 'mensaje' => $comprob];				

		// Agrupa por medida / partida para anita
		$flGrabaStock = false;
		if ($numeroOrdenventa == 0)
		{
			$dataItem = [];
			foreach($datatalle as $item)
			{
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
					if (isset($item['sku']))
					{
						$flGrabaStock = true;
						$dataItem[] = [
							'partida' => 0,
							'cantidad' => $item['cantidad'],
							'pieza' => $item['pieza'],
							'caja' => $item['caja'],
							'precio' => $item['precio'],
							'impuesto_id' => $item['impuesto_id'],
							'incluyeimpuesto' => $item['incluyeimpuesto'],
							'pedido' => $pedido_id,
							'sku' => $item['sku'],
							'descripcion' => $item['descripcion'],
							'categoria' => $item['categoria'],
							'medida' => ''
						];
					}
					else
						$dataItem[] = [
							'partida' => 0,
							'cantidad' => $item['cantidad'],
							'pieza' => 0,
							'caja' => 0,
							'precio' => $item['precio'],
							'impuesto_id' => $item['impuesto_id'],
							'incluyeimpuesto' => $item['incluyeimpuesto'],
							'pedido' => 0,
							'sku' => 'texto',
							'descripcion' => $item['detalle']
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
								'".($this->descuentoLinea == null || $letra == 'E'? '0' : $this->descuentoLinea)."',
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

			$compaux = $apiAnita->apiCall($data);

			if (strpos($compaux, 'Error') !== false)
				return ['error' => 'Error compaux', 'mensaje' => $compaux];
				
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

				$stkmov = $apiAnita->apiCall($data);
				if (strpos($stkmov, 'Error') !== false)
					return ['error' => 'Error stkmov', 'mensaje' => $stkmov];				

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

					$stkvmed = $apiAnita->apiCall($data);
					if (strpos($stkvmed, 'Error') !== false)
						return ['error' => 'Error stkvmed', 'mensaje' => $stkvmed];				
				}
			}
		}
		// Graba leyenda de exportacion
		if (config('app.empresa') == 'Calzados Ferli')
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

					$compley = $apiAnita->apiCall($data);

					if (strpos($compley, 'Error') !== false)
						return ['error' => 'Error compley', 'mensaje' => $compley];			
				}
			}
		}

		// Numera la factura
		if ($this->ventaRepository->numeraAnita(substr($venta['codigo'], 0, 3), $letra, $puntoventa,
						($this->flGrabaComprobanteDividido ? '/usr2/villafranca' : null)) == 'Error')
			return ['error' => 'Error numerador comprobante', 'mensaje' => 'No pudo numerar comprobante'];

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
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}							
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (count($dataAnita) > 0)
			return $dataAnita[0]->ven_nro;
		
		return 0;
	}

	// Busca el ultimo numero de comprobante
	private function buscaUltimoNumeroComprobante($tipo, $letra, $puntoventa)
	{
		// Primero saca el tipo de comprobante
		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'tabla' => 't_comp', 
						'campos' => '
							tcomp_tipo_comp
						' ,
						'whereArmado' => " WHERE tcomp_clave = '".$tipo."'
						" );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}							
		$dataAnita = json_decode($apiAnita->apiCall($data));

		$tipoComp = '01';
		if ($dataAnita)
			$tipoComp = $dataAnita[0]->tcomp_tipo_comp;

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'tabla' => 'venta, t_comp', 
						'campos' => '
							max(ven_nro) as ultimonumero
						' ,
						'whereArmado' => " WHERE ven_tipo = tcomp_clave AND
												tcomp_tipo_comp = '".$tipoComp."' AND
												ven_letra = '".$letra."' AND
												ven_sucursal = '".$puntoventa->codigo."'
						" );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}							
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (count($dataAnita) > 0)
			return $dataAnita[0]->ultimonumero;
		
		return 0;
	}

	// Borra factura en Anita
	public function borraAnita($tipo, $letra, $puntoventa, $numero, $empresa)
	{
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'sistema' => 'ventas',
						'tabla' => 'venta', 
						'whereArmado' => " WHERE ven_tipo = '".$tipo."' AND
												ven_letra = '".$letra."' AND
												ven_sucursal = '".$puntoventa."' AND
												ven_nro = '".$numero."'
						" );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}						
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'venibr', 
						'sistema' => 'ventas',
						'whereArmado' => " WHERE veni_tipo = '".$tipo."' AND
												veni_letra = '".$letra."' AND
												veni_sucursal = '".$puntoventa."' AND
												veni_nro = '".$numero."'
						" );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}						
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'vengrav', 
						'sistema' => 'ventas',
						'whereArmado' => " WHERE veng_tipo = '".$tipo."' AND
												veng_letra = '".$letra."' AND
												veng_sucursal = '".$puntoventa."' AND
												veng_nro = '".$numero."'
						" );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}						
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'vencae', 
						'sistema' => 'ventas',
						'whereArmado' => " WHERE venc_tipo = '".$tipo."' AND
												venc_letra = '".$letra."' AND
												venc_sucursal = '".$puntoventa."' AND
												venc_nro = '".$numero."'
						" );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}						
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'climov', 
						'sistema' => 'ventas',
						'whereArmado' => " WHERE cliv_tipo = '".$tipo."' AND
												cliv_letra = '".$letra."' AND
												cliv_sucursal = '".$puntoventa."' AND
												cliv_nro = '".$numero."'
						" );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}						
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'comprob', 
						'sistema' => 'ventas',
						'whereArmado' => " WHERE comp_tipo = '".$tipo."' AND
												comp_letra = '".$letra."' AND
												comp_sucursal = '".$puntoventa."' AND
												comp_nro_fact = '".$numero."'
						" );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}						
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'compaux', 
						'sistema' => 'ventas',
						'whereArmado' => " WHERE compa_tipo = '".$tipo."' AND
												compa_letra = '".$letra."' AND
												compa_sucursal = '".$puntoventa."' AND
												compa_nro_fact = '".$numero."'
						" );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}						
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'compley', 
						'sistema' => 'ventas',
						'whereArmado' => " WHERE compl_tipo = '".$tipo."' AND
												compl_letra = '".$letra."' AND
												compl_sucursal = '".$puntoventa."' AND
												compl_nro = '".$numero."'
						" );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}						
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'stkmov', 
						'sistema' => 'ventas',
						'whereArmado' => " WHERE stkv_tipo = '".$tipo."' AND
												stkv_letra = '".$letra."' AND
												stkv_sucursal = '".$puntoventa."' AND
												stkv_nro = '".$numero."'
						" );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}						
        $apiAnita->apiCall($data);

		if (config('app.empresa') == 'Calzados Ferli')
		{
			$apiAnita = new ApiAnita();
			$data = array( 'acc' => 'delete', 
							'tabla' => 'stkvmed', 
							'sistema' => 'ventas',
							'whereArmado' => " WHERE stkvm_tipo = '".$tipo."' AND
													stkvm_letra = '".$letra."' AND
													stkvm_sucursal = '".$puntoventa."' AND
													stkvm_nro = '".$numero."'
							" );
			$apiAnita->apiCall($data);
		}

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'subdiario', 
						'sistema' => 'contab',
						'whereArmado' => " WHERE subd_sistema='V' AND subd_tipo = '".$tipo."' AND
												subd_letra = '".$letra."' AND
												subd_sucursal = '".$puntoventa."' AND
												subd_nro = '".$numero."'
						" );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}			
        $apiAnita->apiCall($data);

		$apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => 'ctamov', 
						'sistema' => 'contab',
						'whereArmado' => " WHERE ctav_empresa='".$empresa."' AND ctav_tipo = '".$tipo."' AND
												ctav_letra = '".$letra."' AND
												ctav_sucursal = '".$puntoventa."' AND
												ctav_nro = '".$numero."'
						" );
		if ($this->flGrabaComprobanteDividido)
		{
			$data['path_sistema'] = '/usr2/villafranca';
		}						
        $apiAnita->apiCall($data);		
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
		$vencae = $apiAnita->apiCall($data);

		if (strpos($vencae, 'Error') !== false)
			return 'Error';

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
		$dataAnita = json_decode($apiAnita->apiCall($data));

		$numeroOperacion = $dataAnita[0]->num_ult_numero + 1;

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
		$numerador = $apiAnita->apiCall($data);

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
			if ($conc['concepto'] == 'Subtotal')
				$subTotal = $conc['importe'];
		}

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
					if (strtoupper(config('app.empresa')) == "EL BIERZO")
						$cuentaVenta = config('facturacion.CUENTACONTABLE_VENTA');

					if (strtoupper(config('app.empresa')) == 'AGG')
						$cuentaVenta = config('ordenventa.CUENTAVENTA');

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
											$moneda_id, $cotizacion, $signo, $contrapartida_id, $tipo, $letra, $sucursal, $nro)
	{
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

			$tblItem[] = ["sku" => $sku,
					"detalle" => $detalle,
					"cantidad" => $ventaItem->cantidad,
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
		$conceptosTotales = $venta->venta_impuestos;

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

        return view('ventas.factura.editar', compact('data', 
			'mventa_query', 'modulo_query', 
			'listaprecio_query', 
			'tipotransaccion_query', 'tipotransacciondefault_id', 'puntoventa_query', 'puntoventadefault_id',
            'deposito_query', 'lote_query', 'cliente_query','vendedor_query', 'condicionventa_query',
            'transporte_query', 'formapago_query', 'incoterm_query', 'flGeneraNotaDeCredito', 'moneda_query',
			'actividad_arca_query', 'urlOrigen')); 
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
        $deposito_query = Depmae::all();
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

	// Solicita CAE o CAEA
	private function solicitaComprobanteARCA($empresa, $codigoTipoTransaccion, $tipoAnita, $letra, $puntoventa, $numeroComprobante, $fechaFactura, $dataCAE, $venta_id)
	{
		// Solicita CAE o CAEA
		$flGrabaCae = false;
		switch($puntoventa->modofacturacion)
		{
			case 'C':
			case 'E':
				$cae = $this->facturaelectronicaService->solicitaCAE(
					$empresa->nroinscripcion,
					$codigoTipoTransaccion,
					$puntoventa,
					$dataCAE);
				$flGrabaCae = true;

				//$cae = ['cae' => '74040779002259', 'fechavencimientocae' => '20240201'];
				
				if ($cae == 'Error')
					throw new Exception('No pudo asignar CAE');

				if ($cae['fechavencimientocae'] == 0)
					throw new Exception('No pudo asignar CAE');
				break;
			case 'A':
				if ($empresa->nroinscripcion)
				{
					$cae = $this->facturaelectronicaService->buscaCAEA($empresa->nroinscripcion, $fechaFactura);

					if (isset($cae['Error']))
						throw new Exception('No pudo asignar CAEA, no esta pedido para la quincena');
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

		if ($puntoventa->modofacturacion != 'M')
		{
			// Graba cae en Anita
			$vencae = Self::grabaVenCae($tipoAnita, $letra, $puntoventa->codigo, 
						$numeroComprobante, $cae['cae'], 
						date('Ymd', strtotime($cae['fechavencimientocae'])));

			if (isset($vencae['error']))
			{
				if ($vencae['error'] == 'Error')
					throw new Exception('No pudo grabar CAE en Anita '.$vencae['mensaje']);
			}
		}	

		return 'Success';
	}

	// Genera un Remito
	private function generaUnRemito($data, $cliente, $pedido, $tipoRemito, $letraRemito, $puntoVentaRemito, $numeroRemito, $codigoEmpresa,
									$totalNeto)
	{
		// Recalcula la factura
		$calculoFactura = Self::calculaFacturaPorPedido($data);

		$dataFactura = $calculoFactura['datosfactura'];

		$data['tipotransaccion_id'] = config('facturacion.TIPO_REMITO_ID');
		$data['lote'] = '';

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
		$totalCaja = $totalKilo = $totalPieza;

		foreach ($dataFactura as $item)
		{
			$articulos_id[] = $item['articulo_id'];
			$skus[] = $item['codigoarticulos'];
			$numeroitems[] = $item['items'];
			$cantidades[] = $item['cantidad'];
			$piezas[] = $item['pieza'];
			$cajas[] = $item['caja'];

	        // Si el precio tiene iva incluido lo netea
			if ($item['incluyeimpuesto'] == '1')
				$precio = $item['precio'] / (1 + ($tasa/100));
			else	
				$precio = $item['precio'];

			$precios[] = $precio;
			$listaprecios_id[] = $item['listaprecio_id'];
			$incluyeimpuestos[] = $item['incluyeimpuesto'];
			$monedas_id[] = $item['moneda_id'];
			$descuentos[] = $item['descuento'];

			$totalCaja += $item['caja'];
			$totalPieza += $item['pieza'];
			$totalKilo += $item['cantidad'];
		}

		// Carga variables para grabacion de movimiento de stock
		$data['articulos_id'] = $articulos_id;
		$data['skus'] = $skus;
		$data['combinaciones_id'] = null;
		$data['modulos_id'] = null;
		$data['items'] = $numeroitems;
		$data['cantidades'] = $cantidades;
		$data['precios'] = $precios;
		$data['listasprecios_id'] = $listaprecios_id;
		$data['incluyeimpuestos'] = $incluyeimpuestos;
		$data['monedas_id'] = $monedas_id;
		$data['descuentos'] = $descuentos;
		$data['loteids'] = null;
		$data['medidas'] = [];
		$data['fecha'] = $data['fechafactura'];
		$data['deposito_id'] = config('facturacion.DEPOSITO_VENTA_ID');
		$data['loteimportacion_id'] = null;
		$data['codigo'] = $tipoRemito.' '.$letraRemito.' '.$puntoVentaRemito.'-'.$numeroRemito;
		$data['letra'] = $letraRemito;
		$data['puntoventa'] = $puntoVentaRemito;
		$data['numerocomprobante'] = $numeroRemito;
		$data['item'] = $i;
		$data['codigocliente'] = $cliente->codigo;
		$data['codigotransporte'] = $pedido->transportes->codigo;
		$data['codigovendedor'] = $pedido->vendedores->codigo;
		$data['codigozonavta'] = $pedido->zonavtas->codigo;
		$data['codigoprovincia'] = $cliente->provincias->codigo;
		$data['codigosubzona'] = $cliente->subzonavtas->id;
		$data['codigocombinacion'] = '';
		$data['pedido'] = $pedido->codigo;
		$data['partida'] = 0;
		$data['empresa'] = $codigoEmpresa;
		$data['codigoabasto'] = $cliente->abastos->codigo;
		$data['totalseguro'] = $totalNeto;
		$data['totalneto'] = $totalNeto;
		$data['totalcaja'] = $totalCaja;
		$data['totalkilo'] = $totalKilo;
		$data['totalpieza'] = $totalPieza;
		$data['subzona'] = $cliente->subzona_id;
		$data['oblea'] = '';
		$data['cantidadmodificada'] = $totalKilo;
		$data['usuarioalta'] = Auth::user()->nombre;

		$this->movimientostockService->guardaMovimientoStock($data, 'create');
	}
}


