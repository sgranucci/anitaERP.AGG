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
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Models\Configuracion\Impuesto;
use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Categoria;
use App\Models\Stock\Linea;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\PercepcionNoCategorizadoSupport;
use App\Support\Configuracion\RegimenPercepcionSupport;
use setasign\Fpdi\Fpdi;
use App\Support\Ventas\CaiRemitoVigenteSupport;
use App\Support\Ventas\RemitoFormularioLeyendaSupport;
use App\Support\Ventas\RemitoValorAseguradoSupport;
use App\Support\Ventas\ClienteDespachoSupport;
use App\Support\Ventas\ClienteEntregaPedidoSupport;
use App\Support\Ventas\PedidoEstadoErpSupport;
use App\Support\Ventas\TransporteDepositoSupport;
use App\Support\Ventas\PedidoFacturaAnitaArchivosSupport;
use App\Support\Ventas\ComprobanteImpresionSesionUrlSupport;
use App\Support\Ventas\VillafrancaFacturacionSupport;
use App\Support\Ventas\PedidoFacturaAnitaDeferSupport;
use App\Support\Ventas\PedidoFacturacionExclusivaSupport;
use App\Support\Ventas\PedidoItemCierreFaltaStockSupport;
use App\Support\Ventas\UsuarioPreferenciaFacturacionSupport;
use App\Support\Ventas\ConceptoVentaMostradorSupport;
use App\Support\Ventas\ConceptoVentaPlantillaMotor;
use App\Support\Ventas\ConceptoVentaTagSupport;
use App\Support\Ventas\ContratoVentaEmisionSupport;
use App\Support\Ventas\ContratoVentaSupport;
use App\Models\Ventas\Contrato_Venta;
use App\Support\Ventas\GtinEan13Support;
use App\Support\Ventas\TipoComprobantePreviewSupport;
use App\Support\Ventas\VentaEmisionCajaPiezaSupport;
use App\Support\Stock\UnidadesCajaPiezaSupport;
use App\Support\Ventas\ArcaCaeaAnitaTipoAfipSupport;
use App\Support\Ventas\ClienteAnitaZonamultSupport;
use App\Support\Ventas\ClienteProvinciaIibbSupport;
use App\Support\Ventas\ElBierzoFacturaBPercepcionCabaSupport;
use App\Support\Ventas\FacturaAsientoDescuentoPieSupport;
use App\Support\Ventas\FacturaBTotalesImpresionSupport;
use App\Support\Ventas\FacturaPdfIdentificacionSupport;
use App\Support\Ventas\ElBierzoFacturacionCaeaSaltoSupport;
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
use App\Models\Stock\Unidadmedida;
use App\Models\Stock\Contrafuerte;
use App\Models\Stock\Articulo_Caja;
use App\Models\Stock\Caja;
use App\Models\Ventas\Ordentrabajo;
use App\Models\Ventas\Copiaot;
use App\Models\Ventas\Cobrador;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Zonavta;
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
use Illuminate\Support\Str;
use LynX39\LaraPdfMerger\Facades\PdfMerger;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;
use App;
use Auth;
use DB;
use App\ApiAnita;
use App\Support\Ventas\CaeaEmisionNumeracionSupport;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use App\Support\Ventas\GastronomiaEmisionProfiler;
use App\Support\Ventas\PedidoFacturacionProfiler;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Ventas\KandikoAnitaVentaTipoSupport;
use App\Support\Ventas\VentaNumeracionEmpresaSupport;
use App\Support\Ventas\NotaCreditoPercepcionIibbSupport;
use App\Support\Ventas\VentaNotaCreditoPrecioLiteralSupport;
use App\Support\Ventas\VentaImporteDosDecimalesSupport;
use App\Support\Ventas\VentaNumerocomprobanteUnicidadSupport;
use Exception;
use Illuminate\Database\QueryException;
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
	/** @var list<string> Tablas Anita ya presentes: no reinsertar (regrabación selectiva). */
	protected $anitaOmitirTablas = [];
	protected $tasaImpuesto;
	protected $puntoVentaDivision_id;
	protected $numeroComprobanteDivision;
	/** Reparto 101: reserva FAC A sucursal 1 en Anita Villafranca y emite en PV 00001. */
	protected $usaNumeradorVillafrancaPropio;
	/** @var int Número FAC Villafranca ya reservado (reparto 101) para no volver a numerar. */
	protected $numeroReservadoVillafrancaReparto101;
	protected $numeroRemito;
	protected $movimientoStockService;
	protected $flCalculaDesdeGeneracionFactura;
	/** @var int|null Facturación Bierzo desde remito existente (no gastro/estacionamiento). */
	protected $facturandoDesdeRemitoId;
	/** @var int|null Numeración remito ya emitida (no pedir MAX+1). */
	protected $numeroremitoFijoDesdeRemito;
	/** @var int FAC de Bierzo recién emitida; la VF de la división la apunta. */
	protected $ventaOrigenIdDivision;

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
		$this->usaNumeradorVillafrancaPropio = false;
		$this->numeroReservadoVillafrancaReparto101 = 0;
		$this->numeroRemito = 0;
		$this->flCalculaDesdeGeneracionFactura = false;
		$this->facturandoDesdeRemitoId = null;
		$this->numeroremitoFijoDesdeRemito = null;
		$this->ventaOrigenIdDivision = 0;
    }

	public function leePaginando($busqueda)
    {
        return $this->ventaRepository->leePaginando($busqueda);
    }

	public function leeSinPaginar($busqueda)
    {
        return $this->ventaRepository->leeSinPaginar($busqueda);
    }

	public function totalesIndexPorReparto($filtros)
	{
		return $this->ventaRepository->totalesIndexPorReparto($filtros);
	}

	/**
	 * @param  array<string, mixed>|string|null  $filtros
	 * @return list<int>
	 */
	public function idsIndexPorReparto($filtros, int $transporteId): array
	{
		return $this->ventaRepository->idsIndexPorReparto($filtros, $transporteId);
	}

	private function clienteTieneLugaresEntrega(int $clienteId): bool
	{
		return $this->cliente_entregaRepository->leeClienteEntrega($clienteId)->count() > 0;
	}

	private function sincronizarLugarEntregaPedido($pedido): void
	{
		if ((int) ($pedido->cliente_entrega_id ?? 0) <= 0) {
			return;
		}

		$cliente_entrega = $this->cliente_entregaRepository->find($pedido->cliente_entrega_id);
		if (! $cliente_entrega) {
			return;
		}

		$etiqueta = ClienteEntregaPedidoSupport::etiquetaEntrega($cliente_entrega);
		if (! ClienteEntregaPedidoSupport::nombreEsUsable($pedido->lugarentrega ?? null)
			&& $etiqueta !== '') {
			$pedido->lugarentrega = $etiqueta;
		}
	}

	/**
	 * Provincia de entrega para IIBB (salvo CABA/BA, que van por padrón).
	 * Lugar de entrega del documento; si no hay, domicilio del CRUD de cliente.
	 */
	private function provinciaPercepcionDesdePedido($cliente, $pedido): ?int
	{
		$entrega = null;
		if ($pedido && (int) ($pedido->cliente_entrega_id ?? 0) > 0) {
			$entrega = $this->cliente_entregaRepository->find($pedido->cliente_entrega_id);
		}

		return ClienteProvinciaIibbSupport::idParaPercepcionAdmin($cliente, $entrega);
	}

	private function resolverLugarEntregaPedido($cliente, $documento, array $data, bool $persistir): ?array
	{
		$error = ClienteEntregaPedidoSupport::resolverParaDocumento(
			$documento,
			(int) $cliente->id,
			array_key_exists('cliente_entrega_id', $data) ? ((int) $data['cliente_entrega_id'] ?: null) : null,
			array_key_exists('lugarentrega', $data) ? (string) $data['lugarentrega'] : null
		);
		if ($error !== null) {
			return $error;
		}

		if ($persistir) {
			ClienteEntregaPedidoSupport::persistirDocumento($documento);
		}

		$this->sincronizarLugarEntregaPedido($documento);

		return null;
	}

	// Calcula la factura por pedido

	public function calculaFacturaPorPedido(array $data)
	{
		// Recibe datos para facturar
		$pedido_articulo_ids = $data['pedido_articulo_ids'] ?? [];
		if (! is_array($pedido_articulo_ids)) {
			$pedido_articulo_ids = [];
		}
		if ($pedido_articulo_ids === []) {
			return ['error' => 'No se puede facturar: el pedido no tiene ítems pesados. Cargue la pesada de al menos un ítem.'];
		}

		$cliente_id = $data['cliente_id'];

		$this->descuentoPie = $data['descuentopie'];
		$this->descuentoLinea = 0;
		$this->descuentoImportePie = $data['descuentoimportepie'];
		$this->anularDescuentoPieSiVillafranca();
		$this->incoterm_id = $data['incoterm_id'];
		$fechaFactura = $data['fechafactura'];

		// Trae el cliente
		$cliente = $this->clienteQuery->traeClienteporId($cliente_id);

		if (!$cliente)
			return ['error' => 'Cliente inexistente'];

		if ($errorDespacho = $this->errorClienteDespachoNoFacturable($data, $cliente_id)) {
			return $errorDespacho;
		}
		
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

		$errorEntrega = $this->resolverLugarEntregaPedido($cliente, $pedido, $data, false);
		if ($errorEntrega) {
			return $errorEntrega;
		}

		// Lee los items a facturar
		$dataFactura = [];
		$totKilo = 0;

		for ($offItem = 0; $offItem < count($pedido_articulo_ids); $offItem++)
		{
			$pedido_articulo_id = $pedido_articulo_ids[$offItem];
		
			$pedido_articulo = $this->pedido_articuloRepository->find($pedido_articulo_id);

			if (! $pedido_articulo) {
				continue;
			}

			if (PedidoEstadoErpSupport::esItemPendienteFacturable($pedido_articulo->estado ?? null))
			{
				if ((float) $pedido_articulo->pesada <= 0) {
					continue;
				}

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

					$this->anularDescuentoPieSiVillafranca();
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
		$this->anularDescuentoPieSiVillafranca();
		if ($this->omiteDescuentoPieVillafranca()) {
			foreach ($dataFactura as $i => $item) {
				$dataFactura[$i]['descuentofinal'] = 0;
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
							"porcentajelogistica" => $cliente->porcentajelogistica,
							"empresa_id" => $data['empresa_id'] ?? null,
							];
		else
			$datosCliente = [ "condicioniva_id" => $cliente->condicioniva_id,
							"numerodocumento" => $cliente->numerodocumento,
							"retieneiva" => $cliente->retieneiva,
							"condicioniibb_id" => $cliente->condicioniibb_id,
							"provincia" => $provinciaPercepcion,
							"descuentoimportepie" => $this->descuentoImportePie,
							"id" => $cliente->id,
							"empresa_id" => $data['empresa_id'] ?? null,
							];
		// Calcula impuestos
		$conceptosTotales = $this->impuestoService->calculaImpuestoVenta($dataFactura, $datosCliente, $fechaFactura, 
																			$this->flGrabaComprobanteDividido);

		// Arma total de comprobante
		$totalComprobante = $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Total', 'importe');

		if ($dataFactura === []) {
			return ['error' => 'No se puede facturar: el pedido no tiene ítems pesados. Cargue la pesada de al menos un ítem.'];
		}

		if ($totalComprobante == 0.) {
			return ['error' => 'El total del comprobante es 0. Revise que los ítems del pedido tengan precio mayor a cero.'];
		}

		return $this->conSugerenciaTipoPreview(
			['datosfactura' => $dataFactura, 'datoscliente' => $datosCliente, 'totalcomprobante' => $totalComprobante,
				'conceptostotales' => $conceptosTotales],
			$cliente,
			(float) $totalComprobante,
			$letra
		);
	}

	public function generaFacturaPorPedido(array $data)
	{
		$profiler = PedidoFacturacionProfiler::iniciarSiConfigurado([
			'pedido_id' => (int) ($data['pedido_id'] ?? 0),
			'puntoventa_id' => (int) ($data['puntoventa_id'] ?? 0),
			'puntoventaremito_id' => (int) ($data['puntoventaremito_id'] ?? 0),
			'tipotransaccion_id' => (int) ($data['tipotransaccion_id'] ?? 0),
			'cliente_id' => (int) ($data['cliente_id'] ?? 0),
		]);

		try {
			UsuarioPreferenciaFacturacionSupport::guardar($data);
			PedidoFacturacionProfiler::etapa('preferencias_ok');

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

			if ($errorDespacho = $this->errorClienteDespachoNoFacturable($data, $cliente_id)) {
				return $errorDespacho;
			}

			// Lee el tipo de transaccion
			$tipotransaccion = $this->tipotransaccionRepository->find($tipoTransaccion_id);
			if ($errorNc = $this->errorNotaCreditoDesdePedidoORemito($tipotransaccion, 'pedido')) {
				return $errorNc;
			}

			// Trae el pedido
			$pedido_query = $this->pedidoQuery->leePedidoporId($data['pedido_id']);

			if (!$pedido_query)
				return ['error' => 'Pedido inexistente'];
			else
				$pedido = $pedido_query[0];

			if (PedidoEstadoErpSupport::esTransferido($pedido->estado ?? null, $pedido->estadopedido ?? null)) {
				return ['error' => 'El pedido ya fue transferido al despacho.'];
			}

			$errorEntrega = $this->resolverLugarEntregaPedido($cliente, $pedido, $data, true);
			if ($errorEntrega) {
				return $errorEntrega;
			}

			PedidoFacturacionProfiler::etapa('preflight_ok');

			return PedidoFacturacionExclusivaSupport::ejecutar((int) $pedido->id, function () use ($data, $cliente, $pedido, $tipotransaccion) {
				PedidoFacturacionProfiler::etapa('lock_ok');
				$retorno = $this->emitirFacturasDePedido($data, $cliente, $pedido, $tipotransaccion);
				PedidoFacturacionProfiler::etapa('emitir_fin');
				$retorno = $this->anexarUrlImpresionSesion($retorno, $data);
				PedidoFacturacionProfiler::etapa('impresion_url_ok');

				return $retorno;
			});
		} finally {
			PedidoFacturacionProfiler::finalizar($profiler);
		}
	}

	/**
	 * Emite 1 o 2 facturas del pedido (Bierzo / Villafranca). Debe correr bajo candado exclusivo.
	 *
	 * @param  array<string, mixed>  $data
	 * @return array<int|string, mixed>
	 */
	private function emitirFacturasDePedido(array $data, $cliente, $pedido, $tipotransaccion)
	{
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
				$this->usaNumeradorVillafrancaPropio = VillafrancaFacturacionSupport::esReparto101($pedido);

				if ($pedido->transportes->tipoexpreso == '4') // Reparto 101 con remito en bierzo
					$this->coeficienteCliente = 100.;
				else
					$this->coeficienteCliente = $cliente->coeficientes->porcentajedivision;
				$this->tasaImpuesto = $cliente->coeficientes->tasa;

				// Si no es toda dividida genera factura por el resto en el Bierzo
				if ($this->coeficienteCliente < 100)
				{
					$this->puntoVentaDivision_id = 0;
					$retorno1 = PedidoFacturaAnitaDeferSupport::tomarYProgramar(
						Self::generaUnaFacturaPorPedido($data, $cliente, $pedido)
					);
					if ($this->resultadoFacturaPedidoConError($retorno1)) {
						return [$retorno1];
					}

					$this->ventaOrigenIdDivision = (int) ($retorno1['venta_id'] ?? 0);

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

					if (VillafrancaFacturacionSupport::esReparto101($pedido)) {
						$refPendmae = $this->reservarReferenciaPendmaeVillafrancaReparto101($cliente);
						if (isset($refPendmae['error'])) {
							return [$refPendmae];
						}
						$data = VillafrancaFacturacionSupport::aplicarReferenciaPendmae($data, $refPendmae);
					}

					// Genera remito
					$retorno1 = Self::generaUnRemito($data, $cliente, $pedido, config('facturacion.TIPO_REMITO'), 
						config('facturacion.LETRA_REMITO'), $puntoventaremito->codigo, $puntoventaremito->empresas->codigo);
					if ($this->resultadoFacturaPedidoConError($retorno1)) {
						return [$retorno1];
					}

					// 101 (huérfana): PV 00001 + numerador sucursal 1. Resto 100%: PV 15.
					if (VillafrancaFacturacionSupport::esReparto101($pedido)) {
						$pv101 = VillafrancaFacturacionSupport::idPuntoVentaReparto101();
						if ($pv101 <= 0) {
							return [[
								'error' => 'Error punto de venta Villafranca',
								'mensaje' => 'No está configurado el punto de venta Villafranca sucursal 1 para el reparto 101.',
							]];
						}
						$this->puntoVentaDivision_id = $pv101;
					} else {
						$this->puntoVentaDivision_id = config('facturacion.PUNTOVENTA_DIVISION_ID');
					}
					$data['puntoventa_id'] = $this->puntoVentaDivision_id;						
				}

				$this->flGrabaComprobanteDividido = true;

				// Graba comprobante dividido (Villafranca / sucursal 15): se emite, pero no se muestra en el OK
				$retorno2 = PedidoFacturaAnitaDeferSupport::tomarYProgramar(
					Self::generaUnaFacturaPorPedido($data, $cliente, $pedido)
				);
				$retorno2 = $this->ocultarComprobanteDivididoEnMensaje($retorno2);

				$retorno = [$retorno1, $retorno2];
			}
		}

		if ($retorno === null) {
			$this->flGrabaComprobanteDividido = false;
			$this->flDivide = false;
			$this->usaNumeradorVillafrancaPropio = false;
			$this->numeroReservadoVillafrancaReparto101 = 0;
			$this->ventaOrigenIdDivision = 0;

			$retorno = [PedidoFacturaAnitaDeferSupport::tomarYProgramar(
				Self::generaUnaFacturaPorPedido($data, $cliente, $pedido)
			)];
		}

		return $retorno;
	}

	public function generaUnaFacturaPorPedido(array $data, $cliente, $pedido)
	{
		if (empty($data[ElBierzoFacturacionCaeaSaltoSupport::FLAG_INTERNO])) {
			$data[ElBierzoFacturacionCaeaSaltoSupport::FLAG_INTERNO] = true;

			return ElBierzoFacturacionCaeaSaltoSupport::ejecutarConReintento(
				$data,
				fn (array $dataSalto) => $this->generaUnaFacturaPorPedido($dataSalto, $cliente, $pedido)
			);
		}
		unset($data[ElBierzoFacturacionCaeaSaltoSupport::FLAG_INTERNO]);

		PedidoFacturacionProfiler::etapa('emision_una_factura_inicio');

		// Pedido: exige ítems pesados. Remito (Anita / por lo real): manda remito_articulo.kilo.
		$pedido_articulo_ids = $data['pedido_articulo_ids'] ?? [];
		if (! $this->facturandoDesdeRemitoId
			&& (! is_array($pedido_articulo_ids) || $pedido_articulo_ids === [])) {
			return ['error' => 'No se puede facturar: el pedido no tiene ítems pesados. Cargue la pesada de al menos un ítem.'];
		}

		$cliente_id = $data['cliente_id'];
		$puntoventa_id = $data['puntoventa_id'];
		$tipoTransaccion_id = $data['tipotransaccion_id'];
		$fechaFactura = $data['fechafactura'];
		$leyenda = $data['leyendafactura'];
		$actividad_arca_id = $data['actividad_arca_id'];
		$pedido_id = $data['pedido_id'];

		$deposito = $this->depositoIdDesdePayload($data, $cliente, $pedido);

		$this->descuentoPie = $data['descuentopie'];
		$this->descuentoLinea = 0;
		$this->descuentoImportePie = $data['descuentoimportepie'];
		$this->cantidadBulto = $this->normalizarCantidadBulto($data['cantidadbulto'] ?? 0);
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
		if ($errorNc = $this->errorNotaCreditoDesdePedidoORemito(
			$tipotransaccion,
			$this->facturandoDesdeRemitoId ? 'remito' : 'pedido'
		)) {
			return $errorNc;
		}

		// Recalcula la factura (pedido o remito Bierzo)
		PedidoFacturacionProfiler::etapa('calcula_factura_inicio');
		if ($this->facturandoDesdeRemitoId) {
			$calculoFactura = Self::calculaFacturaPorRemito($data);
		} else {
			$calculoFactura = Self::calculaFacturaPorPedido($data);
		}
		PedidoFacturacionProfiler::etapa('calcula_factura_fin');

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

		$this->aplicarTipotransaccionSegunClienteMonto(
			$data,
			$cliente,
			(float) $totalComprobante,
			(string) $letra,
			$tipoTransaccion_id
		);

		// Calcula vencimientos
		$cuentaCorriente = $this->calculaCondicionVenta($fechaFactura, 
														$totalComprobante, 
														$pedido->condicionventa_id);
		$cuentaCorriente = $this->aplicarVencimientoVillafrancaSiCorresponde($cuentaCorriente, $fechaFactura);

		// Saca letra del comprobante
		$condicioniva = $this->condicionivaRepository->find($cliente->condicioniva_id);
		$letra = 'Z';
		if ($condicioniva)
			$letra = $condicioniva->letra;

		// Lee punto de venta
		$puntoventa = $this->puntoventaRepository->find($puntoventa_id);
		$cuentaCorriente = $this->aplicarVencimientoVillafrancaSiCorresponde($cuentaCorriente, $fechaFactura, $puntoventa);

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

			$tipoAnita = $this->tipoAnitaSegunCodigoAfip($tipotransaccion, $codigoTipoTransaccion);
			$emiteRemito = $this->tipoEmiteRemito($tipotransaccion);

			// Numera factura con web service si es factura electronica
			$numeroReservadoVillafranca = false;
			if ($this->debeUsarNumeradorVillafrancaPropio()) {
				if ((int) $this->numeroReservadoVillafrancaReparto101 > 0) {
					$numero = (int) $this->numeroReservadoVillafrancaReparto101;
				} else {
					$numero = $this->reservarNumeroVillafrancaReparto101('FAC', $letra);
					if (is_array($numero)) {
						return $numero;
					}
					$this->numeroReservadoVillafrancaReparto101 = (int) $numero;
				}
				$numeroReservadoVillafranca = true;
			} else {
			PedidoFacturacionProfiler::etapa('numeracion_factura_inicio');
			$modoClienteFce = $this->hidratarContextoFceCliente($data, $cliente, $totalComprobante);
			$this->facturaelectronicaService->armaTipoTransaccion($letra, $modoClienteFce, $codigoTipoTransaccion,
																	$puntoventa, $totalComprobante);
			switch($puntoventa->modofacturacion)
			{
			case 'C':
			case 'E':
				$numero = $this->facturaelectronicaService
							->traeUltimoNumeroComprobante($empresa->nroinscripcion,
															$codigoTipoTransaccion,
															$puntoventa);
				break;
			case 'A':
				$numero = $this->ultimoNumeroBaseModoCaea(
					$data,
					$puntoventa,
					$tipotransaccion,
					$tipoTransaccion_id,
					$letra,
				);
				if (is_array($numero)) {
					return $numero;
				}
				break;
			case 'M':
				$numero = $this->ultimoNumeroBaseModoManual(
					$puntoventa,
					$tipotransaccion,
					$letra,
					$cliente,
					$totalComprobante,
				);
				break;
			}
			}
			PedidoFacturacionProfiler::etapa('numeracion_factura_fin');

			if ($numero != -1)
			{
				if (! $numeroReservadoVillafranca) {
					$numero++;
				}

				PedidoFacturacionProfiler::etapa('numeracion_remito_anita_inicio');
				// Remito solo con FAC/FCE. NC/ND no numeran ni persisten remito.
				$numeroremito = 0;
				if ($emiteRemito)
				{
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
					if ($this->numeroremitoFijoDesdeRemito !== null && (int) $this->numeroremitoFijoDesdeRemito > 0) {
						$numeroremito = (int) $this->numeroremitoFijoDesdeRemito;
					} elseif ($puntoventaremito && $puntoventa->modofacturacion != 'M')
						$numeroremito = $this->ventaRepository->traeUltimoNumeroRemito('REM','R',$puntoventaremito->codigo);
					else	
						$numeroremito = 0;
				}
				}

				// Villafranca (comprobante dividido) usa el mismo número que la FAC de Bierzo.
				if ($this->flGrabaComprobanteDividido && (int) $this->numeroComprobanteDivision > 0) {
					$numero = (int) $this->numeroComprobanteDivision;
				}

				PedidoFacturacionProfiler::etapa('numeracion_remito_anita_fin');

				if ($numeroremito === 'error') {
					return [
						'error' => 'Error numerador remito',
						'mensaje' => 'El punto de venta de remito no tiene numerador configurado en Anita.',
					];
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
							'logistica' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Total Logistica', 'importe'),
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
				PedidoFacturacionProfiler::etapa('arma_contabilidad_inicio');
				$asientoContable = Self::armaContabilidad($dataFactura, $conceptosTotales, $empresa->id, $totalComprobante);
				PedidoFacturacionProfiler::etapa('arma_contabilidad_fin');

				// Detalle asiento (ERP + ctamov): "FAC 1296 MORA CARLOS ARIEL" — solo factura por pedido Bierzo.
				// No tocar gastronomía/estacionamiento AGG (usan otros flujos / grabaFacturaERP).
				$nombreClienteAsiento = preg_replace('/[^A-Za-z0-9 ]/', '', (string) ($cliente->nombre ?? ''));
				$nombreClienteAsiento = trim(preg_replace('/\s+/', ' ', $nombreClienteAsiento) ?? '');
				$detalleContable = trim($tipoAnita.' '.$numero.' '.$nombreClienteAsiento);

				// Graba la factura
				PedidoFacturacionProfiler::etapa('tx_inicio');
				DB::beginTransaction();
				try 
				{
					$deferAnitaPedido = PedidoFacturaAnitaDeferSupport::debeDiferir();
					$omitirAnitaAsiento = false;
					$tipoAnita = $this->tipoAnitaSegunCodigoAfip($tipotransaccion, $codigoTipoTransaccion);

					$ventaPedidoId = $pedido_id ?: null;
					$ventaRemitoId = $this->facturandoDesdeRemitoId ?: null;
					// Si la cabecera es Remito, el FK pedido vive en remito.pedido_id
					if ($this->facturandoDesdeRemitoId && isset($pedido->pedido_id)) {
						$ventaPedidoId = $pedido->pedido_id ?: null;
					}

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
						'codigo_afip' => (int) preg_replace('/\D+/', '', (string) $codigoTipoTransaccion) ?: null,
						'nombre' => $cliente->nombre,
						'domicilio' => $cliente->domicilio,
						'localidad_id' => $cliente->localidad_id,
						'provincia_id' => $provinciaPercepcion,
						'pais_id' => $cliente->pais_id,
						'codigopostal' => $cliente->codigopostal,
						'email' => $cliente->email,
						'telefono' => $cliente->telefono,
						'nroinscripcion' => $cliente->numerodocumento ?? $cliente->nroinscripcion ?? null,
						'condicioniva_id' => $cliente->condicioniva_id,
						'puntoventaremito_id' => $emiteRemito ? $this->puntoventaremito_id : null,
            			'numeroremito' => $emiteRemito ? $numeroremito : 0,
						'cantidadbulto' => $this->cantidadBulto,
						'pedido_id' => $ventaPedidoId,
						'remito_id' => $emiteRemito ? $ventaRemitoId : null,
						'venta_origen_id' => VillafrancaFacturacionSupport::ventaOrigenIdParaGrabar(
							(int) $puntoventa->id,
							(int) $this->ventaOrigenIdDivision
						),
					];	

					// Graba venta
					$vta = $this->ventaRepository->create($venta);
					PedidoFacturacionProfiler::etapa('graba_venta_ok');

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
					PedidoFacturacionProfiler::etapa('items_stock_inicio');
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
					PedidoFacturacionProfiler::etapa('items_stock_fin');

					// C/E: no ctamov hasta CAE. Diferido Bierzo: ctamov con venta/vencae post-respuesta.
					$omitirAnitaAsiento = $deferAnitaPedido
						|| in_array((string) ($puntoventa->modofacturacion ?? ''), ['C', 'E'], true);
					PedidoFacturacionProfiler::etapa($omitirAnitaAsiento ? 'asiento_erp_sin_anita_inicio' : 'asiento_erp_anita_inicio');
					Self::grabaAsientoContable($asientoContable, $empresa_id, $fechaFactura, $vta->id, $detalleContable, $centrocosto_id,
											$moneda_id, $cotizacion, $signo, $cliente->cuentacontable_id,
											substr($venta['codigo'],0,3), $letra, $puntoventa->codigo, $venta['numerocomprobante'],
											$puntoventa->modofacturacion ?? null,
											isset($venta['fechajornada']) ? (string) $venta['fechajornada'] : null,
											$omitirAnitaAsiento);
					PedidoFacturacionProfiler::etapa($omitirAnitaAsiento ? 'asiento_erp_sin_anita_fin' : 'asiento_erp_anita_fin');
					
					PedidoFacturacionProfiler::etapa('remito_erp_inicio');
					// Remito ERP solo FAC/FCE (administración Bierzo; no gastronomía/estacionamiento/NC)
					if ($emiteRemito && $this->facturandoDesdeRemitoId) {
						app(\App\Services\Ventas\RemitoService::class)->marcarFacturado(
							(int) $this->facturandoDesdeRemitoId,
							(int) $vta->id
						);
					} elseif ($emiteRemito && (int) $numeroremito > 0 && $puntoventaremito) {
						$remitoService = app(\App\Services\Ventas\RemitoService::class);
						$remitoPendiente = \App\Models\Ventas\Remito::query()
							->where('pedido_id', $pedido_id)
							->where('estadoremito', \App\Support\Ventas\RemitoEstadosSupport::ESTADOREMITO_PENDIENTE)
							->whereNull('venta_id')
							->orderByDesc('id')
							->first();

						if ($remitoPendiente) {
							$remitoService->marcarFacturado((int) $remitoPendiente->id, (int) $vta->id);
						} else {
							$persistRemito = $remitoService->persistirDesdeFactura([
								'venta' => $vta,
								'pedido' => $pedido,
								'puntoventa_id' => $this->puntoventaremito_id,
								'numero' => $numeroremito,
								'items' => $dataFactura,
								'origen' => 'factura',
								'estadoremito' => \App\Support\Ventas\RemitoEstadosSupport::ESTADOREMITO_FACTURADO,
								'estado' => 'F',
								'venta_id' => $vta->id,
								'pedido_id' => $pedido_id,
								'sin_transaction' => true,
							]);
							if (! empty($persistRemito['error'])) {
								throw new Exception('Error grabando remito ERP: '.$persistRemito['error']);
							}
						}
					}
					PedidoFacturacionProfiler::etapa('remito_erp_fin');

					// Marca Pedido como facturado (si aplica)
					$pedidoIdMarcar = $ventaPedidoId ?: $pedido_id;
					if ($pedidoIdMarcar) {
						PedidoItemCierreFaltaStockSupport::cerrarItemsSinPesadaDelPedido((int) $pedidoIdMarcar);
						$this->pedidoRepository->update([
							'estado' => PedidoEstadoErpSupport::FACTURADO,
							'estadopedido' => 'Facturado',
						], $pedidoIdMarcar);
					}

					$anitaPendientePedido = null;
					$vencaePendientePedido = null;
					PedidoFacturacionProfiler::etapa($deferAnitaPedido ? 'anita_venta_diferida' : 'anita_venta_sincrona_inicio');

					if ($puntoventa->modofacturacion != 'M' || $this->flGrabaComprobanteDividido)
					{
						$anitaPedidoId = (int) ($pedidoIdMarcar ?: 0);
						$codigoPuntoventaRemito = $emiteRemito ? ($puntoventaremito->codigo ?? 0) : 0;
						$numeroremito = $emiteRemito ? $numeroremito : 0;

						if ($deferAnitaPedido) {
							$anitaPendientePedido = [
								'puntoventa_codigo' => $puntoventa->codigo,
								'letra' => $letra,
								'puntoventaremito_codigo' => $codigoPuntoventaRemito,
								'numeroremito' => $numeroremito,
								'venta' => $venta,
								'data_cae' => $dataCAE,
								'conceptos_totales' => $conceptosTotales,
								'cuentacorriente' => $cuentaCorriente,
								'data_factura' => $dataFactura,
								'signo' => $signo,
								'codigo_tipo_transaccion' => $codigoTipoTransaccion,
								'pedido_id' => $anitaPedidoId,
								'referencia_factura' => $referenciaFactura,
								'empresa_codigo' => $empresa->codigo,
								'modo_facturacion_puntoventa' => $puntoventa->modofacturacion ?? null,
								'path_sistema' => PedidoFacturaAnitaArchivosSupport::pathSistemaParaSucursal($puntoventa->codigo),
							];
						} else {
							$anita = $this->grabaAnitaConReintentoPorDuplicado($puntoventa->codigo, $letra, $codigoPuntoventaRemito, $numeroremito,
										$venta, $dataCAE, $conceptosTotales, $cuentaCorriente, $dataFactura, $signo,
										$codigoTipoTransaccion, $anitaPedidoId,
										true, 0, 0, $referenciaFactura, $empresa->codigo,
										null, null, false, false, false, false, $puntoventa->modofacturacion ?? null);

							if (isset($anita['error']))
							{
								if ($anita['error'] == 'Error')
									throw new Exception('Error en grabacion anita. '.$anita['mensaje']);

								if ($anita['error'] == 'Errvend')
									throw new Exception('No tiene vendedor asignado.');
							}
						}

						// ARCA síncrono: numera y valida CAE. vencae a Anita se difiere con la venta.
						PedidoFacturacionProfiler::etapa('arca_caea_inicio');
						Self::solicitaComprobanteARCA($empresa, $codigoTipoTransaccion, substr($venta['codigo'], 0, 3),
							$letra, $puntoventa, $venta['numerocomprobante'], $fechaFactura, $dataCAE, $vta->id,
							$deferAnitaPedido);
						PedidoFacturacionProfiler::etapa('arca_caea_fin');

						if ($omitirAnitaAsiento && ! $deferAnitaPedido) {
							$this->sincronizarCtamovAnitaDeVenta(
								(int) $vta->id,
								substr((string) $venta['codigo'], 0, 3),
								(string) $letra,
								(int) $puntoventa->codigo,
								(int) $venta['numerocomprobante'],
							);
						}

						if ($deferAnitaPedido) {
							$vencaePendientePedido = $this->armarVencaePendienteDesdeCaePendiente([
								'venta_id' => $vta->id,
								'tipo_anita' => substr($venta['codigo'], 0, 3),
								'letra' => $letra,
								'puntoventa' => $puntoventa,
								'numero_comprobante' => $venta['numerocomprobante'],
							]);
						}
					}
					PedidoFacturacionProfiler::etapa('tx_commit');
					DB::commit();

					$ok = $this->respuestaFacturaPedidoOk($venta['codigo'] ?? '');
					$ok['venta_id'] = (int) $vta->id;
					if ((int) ($vta->remito_id ?? 0) > 0) {
						$ok['remito_id'] = (int) $vta->remito_id;
					} elseif ($this->facturandoDesdeRemitoId) {
						$ok['remito_id'] = (int) $this->facturandoDesdeRemitoId;
					}
					if ($pedidoIdMarcar) {
						$ok['pedido_id'] = (int) $pedidoIdMarcar;
					}
					if ($deferAnitaPedido && ($anitaPendientePedido !== null || $vencaePendientePedido !== null)) {
						$ok['anita_pendiente'] = $anitaPendientePedido;
						$ok['vencae_pendiente'] = $vencaePendientePedido;
					}

					return $ok;
				} catch (\Throwable $e) {
					DB::rollback();

					Log::error('facturacion.pedido.emision_rollback', [
						'error' => $e->getMessage(),
						'remito_id' => $this->facturandoDesdeRemitoId,
						'pedido_id' => $pedido_id ?? null,
						'puntoventa_id' => $puntoventa->id ?? null,
						'codigo' => $venta['codigo'] ?? null,
						'numero' => $venta['numerocomprobante'] ?? null,
					]);

					// Con Anita diferida no se escribió Informix (salvo lectura del numerador remito).
					// Sin diferir, el asiento pudo haber dejado ctamov huérfano.
					if (! $deferAnitaPedido && ($venta['codigo'] ?? '') !== '') {
						try {
							self::borraAnita(
								substr((string) $venta['codigo'], 0, 3),
								$letra,
								$puntoventa->codigo,
								$venta['numerocomprobante'],
								$empresa->codigo,
								false,
								$puntoventa->modofacturacion ?? null,
							);
						} catch (\Throwable $cleanupEx) {
							Log::warning('facturacion.pedido.cleanup_anita_post_rollback', [
								'codigo' => $venta['codigo'] ?? null,
								'mensaje' => $cleanupEx->getMessage(),
								'origen' => $e->getMessage(),
							]);
						}
					}

					$msg = $e->getMessage();
					if ($e instanceof QueryException
						&& str_contains($msg, 'venta_codigo_afip_puntoventa_numerocomprobante_unique')) {
						$msg = 'Ya existe un comprobante con ese punto de venta, tipo AFIP y número. Reintente.';
					}

					return ['error' => $msg];
				}
			}

			return ['error' => 'No se pudo numerar el comprobante.'];
		}
		else
			return ['error' => 'Error con punto de venta asignado'];

		return ['error' => 'No se pudo generar la factura del pedido.'];
	}

	/**
	 * OK de factura de pedido. La de Villafranca (división / sucursal 15) se emite
	 * igual, pero el mensaje final solo debe mostrar la de El Bierzo.
	 */
	private function respuestaFacturaPedidoOk(string $codigo): array
	{
		$payload = ['factura' => $codigo];

		return $this->ocultarComprobanteDivididoEnMensaje($payload);
	}

	/**
	 * @param  array<int|string, mixed>  $retorno
	 * @return array<int|string, mixed>
	 */
	private function anexarUrlImpresionSesion($retorno, ?array $dataOrigen = null)
	{
		if (! is_array($retorno) || $retorno === []) {
			return $retorno;
		}
		if (isset($retorno['error']) && ! array_is_list($retorno)) {
			return $retorno;
		}

		$items = array_is_list($retorno) ? $retorno : [$retorno];
		$ventaId = 0;
		$remitoId = 0;
		$pedidoId = 0;
		foreach ($items as $item) {
			if (! is_array($item) || ! empty($item['error'])) {
				continue;
			}
			$itemVentaId = (int) ($item['venta_id'] ?? 0);
			if ($itemVentaId > 0 && empty($item['ocultar_mensaje']) && PedidoFacturaAnitaArchivosSupport::esVentaIdVisible($itemVentaId)) {
				$ventaId = $itemVentaId;
			}
			if ((int) ($item['remito_id'] ?? 0) > 0) {
				$remitoId = (int) $item['remito_id'];
			}
			if ((int) ($item['pedido_id'] ?? 0) > 0) {
				$pedidoId = (int) $item['pedido_id'];
			}
		}

		$retornoIndex = is_array($dataOrigen) ? (string) ($dataOrigen['retorno_index'] ?? '') : '';
		$url = ComprobanteImpresionSesionUrlSupport::postFacturacion($ventaId, $remitoId, $pedidoId, $retornoIndex);
		if ($url === null) {
			return $retorno;
		}

		foreach ($items as $i => $item) {
			if (! is_array($item) || ! empty($item['error']) || ! empty($item['ocultar_mensaje'])) {
				continue;
			}
			$items[$i]['impresion_url'] = $url;
			break;
		}

		return array_is_list($retorno) ? $items : $items[0];
	}

	private function resultadoFacturaPedidoConError($retorno): bool
	{
		return is_array($retorno) && ! empty($retorno['error']);
	}

	private function omiteDescuentoPieVillafranca(): bool
	{
		return (bool) $this->flGrabaComprobanteDividido && EntornoEmpresaSupport::esElBierzo();
	}

	private function anularDescuentoPieSiVillafranca(): void
	{
		if (! $this->omiteDescuentoPieVillafranca()) {
			return;
		}

		$this->descuentoPie = 0;
		$this->descuentoImportePie = 0;
	}

	private function activarGrabacionAnitaVillafrancaSiNotaCreditoDivision(int $puntoventaId, int $tipotransaccionId): void
	{
		if (! PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision($puntoventaId) || $tipotransaccionId <= 0) {
			return;
		}

		$tipo = $this->tipotransaccionRepository->find($tipotransaccionId);
		if (! $tipo || ($tipo->signo ?? 'S') === 'S') {
			return;
		}

		$this->flGrabaComprobanteDividido = true;
	}

	private function activarGrabacionAnitaVillafrancaSiSignoDivision(int $puntoventaId, $signo): void
	{
		if ((float) $signo >= 0 || ! PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision($puntoventaId)) {
			return;
		}

		$this->flGrabaComprobanteDividido = true;
	}

	private function debeReplicarAnitaVillafrancaAunqueModoManual($puntoventa): bool
	{
		if ($this->flGrabaComprobanteDividido) {
			return true;
		}

		return PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision((int) ($puntoventa->id ?? 0));
	}

	/**
	 * Path Anita de escritura. Manda la sucursal, no el flag de división:
	 * FAC A 8 / A 10 no van a /usr2/villafranca aunque flGrabaComprobanteDividido esté prendido.
	 */
	private function aplicarPathSistemaAnitaComprobante(array &$data, $sucursal): void
	{
		$path = PedidoFacturaAnitaArchivosSupport::pathSistemaParaSucursal($sucursal);
		if ($path !== null) {
			$data['path_sistema'] = $path;
		}
	}

	private function ocultarComprobanteDivididoEnMensaje($retorno)
	{
		if (! is_array($retorno) || ! empty($retorno['error'])) {
			return $retorno;
		}

		if ($this->flGrabaComprobanteDividido) {
			$retorno['ocultar_mensaje'] = true;
		}

		return $retorno;
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
				if ($cuota->venta_id == null &&
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
						  "provincia" => ClienteProvinciaIibbSupport::idParaPercepcionAdmin($cliente),
						  "descuentoimportepie" => $this->descuentoImportePie,
						  "id" => $cliente->id,
						  "empresa_id" => $empresa_id,
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
		UsuarioPreferenciaFacturacionSupport::guardar($data);

		// Recalcula factura
		$calculoFactura = Self::calculaFacturaPorOrdenventa($data);

		if (isset($calculoFactura['error'])) {
			return ['error' => $calculoFactura['error']];
		}

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
		$cuentacorriente = $this->aplicarVencimientoVillafrancaSiCorresponde($cuentacorriente, $fechaFactura, $puntoventa);

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

			$tipoAnita = $this->tipoAnitaSegunCodigoAfip($tipotransaccion, $codigoTipoTransaccion);

			$modoClienteFce = $this->hidratarContextoFceCliente($data, $cliente, $totalComprobante);
			$this->facturaelectronicaService->armaTipoTransaccion($letra, $modoClienteFce, $codigoTipoTransaccion,
																	$puntoventa, $totalComprobante);
			$tipoAnita = $this->tipoAnitaSegunCodigoAfip($tipotransaccion, $codigoTipoTransaccion);

			switch($puntoventa->modofacturacion)
			{
				case 'C':
				case 'E':
					$numero = $this->facturaelectronicaService
								->traeUltimoNumeroComprobante($empresa->nroinscripcion,
																$codigoTipoTransaccion,
																$puntoventa);
					break;
				case 'A':
					$numero = $this->ultimoNumeroBaseModoCaea(
						$data,
						$puntoventa,
						$tipotransaccion,
						$tipoTransaccion_id,
						$letra,
					);
					if (is_array($numero)) {
						return $numero;
					}
					
					break;
				case 'M':
					$numero = $this->ultimoNumeroBaseModoManual(
						$puntoventa,
						$tipotransaccion,
						$letra,
						$cliente,
						$totalComprobante,
					);
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
							'logistica' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Total Logistica', 'importe'),
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
					$tipoAnita = $this->tipoAnitaSegunCodigoAfip($tipotransaccion, $codigoTipoTransaccion);
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
						'transporte_id' => TransporteDepositoSupport::transporteIdDesdeFactura($data, $cliente) ?: null,
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
						$detalleEmision = trim((string) ($itemEmision['detalle'] ?? ''));
						if ($detalleEmision === '') {
							$detalleEmision = trim((string) ($itemEmision['detalleconcepto'] ?? ''));
						}
						$dataEmision = [
							'venta_id' => $vta->id,
							'numeroitem' => ++$numeroItem, 
							'lotestock' => 0,
							'detalle' => $detalleEmision,
							'cantidad' => abs($itemEmision['cantidad']), 
							'precio' => $itemEmision['precio'], 
							'impuesto_id' => $itemEmision['impuesto_id'],
							'incluyeimpuesto' => $itemEmision['incluyeimpuesto'], 
							'moneda_id' => $itemEmision['moneda_id'], 
							'descuento' => $itemEmision['descuento'], 
							'descuentointegrado' => $itemEmision['descuentointegrado']
						];
						$dataEmision = $this->anexarConceptoEnEmision($dataEmision, $itemEmision);
						$venta_emision = $this->venta_emisionRepository->create($dataEmision);
						ContratoVentaEmisionSupport::persistirTrasCrearEmision($venta_emision, $itemEmision, (int) $vta->id);
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
											$puntoventa->modofacturacion ?? null,
											isset($venta['fechajornada']) ? (string) $venta['fechajornada'] : null);

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
		$descuentosPct = $data['descuentos'] ?? [];
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
			} elseif (isset($descuentosPct[$i]) && $descuentosPct[$i] !== '' && $descuentosPct[$i] !== null) {
				$descPct = (float) str_replace(',', '', (string) $descuentosPct[$i]);
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
		VentaNotaCreditoPrecioLiteralSupport::aplicarPreciosFacturaOrigen($data);

		UsuarioPreferenciaFacturacionSupport::guardar($data);

		// Recibe datos para facturar
		$cliente_id = $data['cliente_id'];
		$puntoventa_id = $data['puntoventa_id'];
		$moneda_id = $data['moneda_id'];
		$this->descuentoPie = $data['descuentopie'];
		$this->descuentoLinea = 0;
		$this->descuentoImportePie = $data['descuentoimportepie'];
		$fechaFactura = $data['fechafactura'];
		$this->activarGrabacionAnitaVillafrancaSiNotaCreditoDivision(
			(int) $puntoventa_id,
			(int) ($data['tipotransaccion_id'] ?? 0)
		);

		// Trae el cliente
		$cliente = $this->clienteQuery->traeClienteporId($cliente_id);

		if (!$cliente)
			return ['error' => 'Cliente inexistente'];

		if ($errorDespacho = $this->errorClienteDespachoNoFacturable($data, $cliente_id)) {
			return $errorDespacho;
		}
		
		if (! isset($data['arca_receptor']) && $cliente->numerodocumento == null)
			return ['error' => 'No tiene Documento'];
			
		// Saca letra del comprobante
		$condicioniva = $this->condicionivaRepository->find($cliente->condicioniva_id);
		$letra = 'Z';
		if ($condicioniva)
			$letra = $condicioniva->letra;

		// Letra B: no IIBB ni perc. IVA 3 % (RG 5329). La perc. RG 2126 de no categorizado sí va.
		// El Bierzo: CABA 901 se cobra igual en letra B (padrón o tasa de descarte).
		$omitirPercepcionesYaPedido = ! empty($data['omitir_percepciones']);
		if (strtoupper((string) $letra) === 'B') {
			$data['omitir_percepciones'] = true;
			if (PercepcionNoCategorizadoSupport::aplicarAunqueSeOmitanOtras($omitirPercepcionesYaPedido, $condicioniva)) {
				$data['aplicar_percepcion_no_categorizado'] = true;
			}
			if (ElBierzoFacturaBPercepcionCabaSupport::correspondePorLetra($letra)) {
				$data[ElBierzoFacturaBPercepcionCabaSupport::FLAG] = true;
			}
		}

		// Lee punto de venta
		$puntoventa = $this->puntoventaRepository->find($puntoventa_id);

		$empresa_id = $puntoventa->empresa_id;

		$tipotransaccionCalculo = null;
		$tipoTransaccionCalculoId = (int) ($data['tipotransaccion_id'] ?? 0);
		if ($tipoTransaccionCalculoId > 0) {
			try {
				$tipotransaccionCalculo = $this->tipotransaccionRepository->find($tipoTransaccionCalculoId);
			} catch (\Throwable $e) {
				$tipotransaccionCalculo = null;
			}
		}
		$conceptoVentaIdsInput = $data['concepto_venta_ids'] ?? [];
		if (! is_array($conceptoVentaIdsInput)) {
			$conceptoVentaIdsInput = [];
		}
		$contratoVentaIdsInput = $data['contrato_venta_ids'] ?? [];
		if (! is_array($contratoVentaIdsInput)) {
			$contratoVentaIdsInput = [];
		}
		$conceptoTagJsonInput = $data['concepto_tag_json'] ?? [];
		if (! is_array($conceptoTagJsonInput)) {
			$conceptoTagJsonInput = [];
		}
		$conceptoPeriodoDesdeInput = $data['concepto_periodo_desde'] ?? [];
		if (! is_array($conceptoPeriodoDesdeInput)) {
			$conceptoPeriodoDesdeInput = [];
		}
		$conceptoPeriodoHastaInput = $data['concepto_periodo_hasta'] ?? [];
		if (! is_array($conceptoPeriodoHastaInput)) {
			$conceptoPeriodoHastaInput = [];
		}
		$conceptoVentaCabeceraId = (int) ($data['concepto_venta_id'] ?? 0);
		$esPosMostrador = $this->esEmisionPos($data);

		// Lee los items a facturar
		$dataFactura = [];
		$totCantidad = 0;

		$articulos = $data['articulo_ids'] ?? [];
		if (! is_array($articulos)) {
			$articulos = [$articulos];
		}
		$codigosArticulo = $data['codigoarticulos'] ?? [];
		$descripciones = $data['descripcionarticulos'];
		$cantidades = $data['cantidades'];
		$precios = $data['precios'];
		$cajasInput = $data['cajas'] ?? [];
		$piezasInput = $data['piezas'] ?? [];
		$listaprecioGlobalId = isset($data['listaprecio_id']) ? (int) $data['listaprecio_id'] : null;
		$listaspreciosIds = $data['listasprecios_id'] ?? null;
		$incluyeimpuestosInput = $data['incluyeimpuestos'] ?? null;
		$impuestosIdsInput = $data['impuesto_ids'] ?? null;
		$leyendasLineaInput = $data['leyendas_linea'] ?? [];
		if (! is_array($leyendasLineaInput)) {
			$leyendasLineaInput = [];
		}
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
			$articuloIdLinea = (int) ($articulos[$offItem] ?? 0);
			$conceptoIdInputLinea = (int) ($conceptoVentaIdsInput[$offItem] ?? 0);
			if ($articuloIdLinea <= 0 && $conceptoIdInputLinea <= 0) {
				$skuLinea = trim((string) ($codigosArticulo[$offItem] ?? ''));
				if ($skuLinea !== '') {
					$articuloPorSku = $this->articuloQuery->traeArticuloPorSku($skuLinea);
					$articuloIdLinea = (int) ($articuloPorSku->id ?? 0);
				}
			}

			// Trae el articulo
			if ($articuloIdLinea > 0)
			{
				$articulo = $this->articuloQuery->traeArticuloPorId($articuloIdLinea);

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
				$conceptoVentaIdLinea = null;
				$codigoMtxLinea = '';
				$unidadesMtxLinea = 1;
				$centrocostoConceptoId = null;
				$precioCatalogo = 0.0;
				$contratoVentaIdLinea = null;
				$tagValoresLinea = [];
				$periodoDesdeLinea = '';
				$periodoHastaLinea = '';
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

				$conceptoVentaIdLinea = null;
				$codigoMtxLinea = '';
				$unidadesMtxLinea = 1;
				$centrocostoConceptoId = null;
				$precioCatalogo = 0.0;
				$contratoVentaIdLinea = null;
				$tagValoresLinea = [];
				$periodoDesdeLinea = '';
				$periodoHastaLinea = '';

				if (! $esPosMostrador) {
					$conceptoIdLinea = (int) ($conceptoVentaIdsInput[$offItem] ?? 0);
					if ($conceptoIdLinea <= 0) {
						$conceptoIdLinea = $conceptoVentaCabeceraId;
					}
					if ($conceptoIdLinea <= 0
						&& $tipotransaccionCalculo
						&& $tipotransaccionCalculo->usaConceptoVentaEnFacturador()) {
						$conceptoIdLinea = (int) ($tipotransaccionCalculo->concepto_venta_id ?? 0);
					}
					$resueltoConcepto = ConceptoVentaMostradorSupport::resolverLinea(
						$conceptoIdLinea,
						(int) $empresa_id,
						(int) ($tipotransaccionCalculo->id ?? 0) ?: null,
						is_string($fechaFactura) ? substr($fechaFactura, 0, 10) : null,
					);
					if ($resueltoConcepto === null
						&& ConceptoVentaMostradorSupport::obligatorioSinArticulo($puntoventa->webservice ?? null)) {
						return ['error' => ConceptoVentaMostradorSupport::mensajeObligatorioSinArticulo()];
					}
					if ($resueltoConcepto !== null) {
						$conceptoVentaIdLinea = $resueltoConcepto['concepto_venta_id'];
						if (trim((string) $descripcion) === '' && $resueltoConcepto['descripcion'] !== '') {
							$descripcion = $resueltoConcepto['descripcion'];
						}
						$codigoUnidadMedida = $resueltoConcepto['unidadmedida_codigo'];
						$impuestoFormLinea = 0;
						if (is_array($impuestosIdsInput) && isset($impuestosIdsInput[$offItem]) && (string) $impuestosIdsInput[$offItem] !== '') {
							$impuestoFormLinea = (int) $impuestosIdsInput[$offItem];
						}
						if ($impuestoFormLinea > 0) {
							$impuesto_id = $impuestoFormLinea;
						} elseif ($resueltoConcepto['impuesto_id']) {
							$impuesto_id = $resueltoConcepto['impuesto_id'];
						} else {
							return ['error' => 'Indicá la alícuota de IVA del renglón (Exento, 10,5% o 21%).'];
						}
						if ($resueltoConcepto['cuentacontable_id']) {
							$cuentaContable_id = $resueltoConcepto['cuentacontable_id'];
						}
						$codigoMtxLinea = $resueltoConcepto['codigo_gtin'];
						$unidadesMtxLinea = $resueltoConcepto['unidades_mtx'];
						$sku = $resueltoConcepto['codigo'];
						$centrocostoConceptoId = $resueltoConcepto['centrocosto_id'] ?? null;
						$precioCatalogo = (float) ($resueltoConcepto['precio'] ?? 0);
						if (ConceptoVentaMostradorSupport::obligatorioSinArticulo($puntoventa->webservice ?? null)
							&& ! GtinEan13Support::esAceptable($codigoMtxLinea)) {
							return ['error' => ConceptoVentaMostradorSupport::mensajeGtinInvalido($resueltoConcepto)];
						}

						$contratoVentaIdLinea = (int) ($contratoVentaIdsInput[$offItem] ?? 0);
						$tagValoresLinea = [];
						$rawTagJson = $conceptoTagJsonInput[$offItem] ?? '';
						if (is_string($rawTagJson) && $rawTagJson !== '') {
							$decodedTags = json_decode($rawTagJson, true);
							if (is_array($decodedTags)) {
								foreach ($decodedTags as $tk => $tv) {
									$tagValoresLinea[ConceptoVentaPlantillaMotor::normalizarClave((string) $tk)] = trim((string) $tv);
								}
							}
						}
						$periodoDesdeLinea = substr(trim((string) ($conceptoPeriodoDesdeInput[$offItem] ?? '')), 0, 10);
						$periodoHastaLinea = substr(trim((string) ($conceptoPeriodoHastaInput[$offItem] ?? '')), 0, 10);

						$contratoModel = null;
						if ($contratoVentaIdLinea > 0) {
							$contratoModel = Contrato_Venta::query()
								->with(['datos', 'cliente', 'empresa', 'conceptoVenta.tags'])
								->find($contratoVentaIdLinea);
							if ($contratoModel) {
								foreach (ContratoVentaSupport::datosFijosComoValores($contratoModel) as $ck => $cv) {
									if (! isset($tagValoresLinea[$ck]) || $tagValoresLinea[$ck] === '') {
										$tagValoresLinea[$ck] = $cv;
									}
								}
								if ($periodoDesdeLinea === '' || $periodoHastaLinea === '') {
									$per = ContratoVentaSupport::periodoParaFecha(
										is_string($fechaFactura) ? substr($fechaFactura, 0, 10) : date('Y-m-d'),
										(string) $contratoModel->periodicidad
									);
									$periodoDesdeLinea = $per['desde'];
									$periodoHastaLinea = $per['hasta'];
									if (! isset($tagValoresLinea['periodo']) || $tagValoresLinea['periodo'] === '') {
										$tagValoresLinea['periodo'] = ContratoVentaSupport::valorPeriodoTag($per);
									}
								}
								if ($contratoModel->precio !== null && (float) $contratoModel->precio > 0) {
									$precioCatalogo = (float) $contratoModel->precio;
								}
							}
						}

						$sistemaVals = ConceptoVentaPlantillaMotor::valoresSistema([
							'cliente_nombre' => $cliente->nombre ?? '',
							'cliente_documento' => $cliente->numerodocumento ?? '',
							'fecha_factura' => is_string($fechaFactura) ? substr($fechaFactura, 0, 10) : date('Y-m-d'),
							'empresa_nombre' => (string) ($puntoventa->empresas->nombre
								?? \App\Models\Configuracion\Empresa::query()->whereKey($empresa_id)->value('nombre')
								?? ''),
							'codigo_concepto' => $resueltoConcepto['codigo'] ?? '',
							'nombre_concepto' => $resueltoConcepto['descripcion'] ?? '',
						]);
						foreach ($sistemaVals as $sk => $sv) {
							if ($sv !== '' && (! isset($tagValoresLinea[$sk]) || $tagValoresLinea[$sk] === '')) {
								$tagValoresLinea[$sk] = $sv;
							}
						}

						$conceptoFull = \App\Models\Ventas\Concepto_Venta::query()
							->with(['tags'])
							->find($conceptoVentaIdLinea);
						$plantillaConcepto = trim((string) ($conceptoFull->descripcion ?? $resueltoConcepto['descripcion'] ?? ''));
						$metasTags = ConceptoVentaTagSupport::metasDesdeTagsApi(
							ConceptoVentaTagSupport::tagsDesdeConcepto($conceptoFull)
						);
						// Si el detalle aún tiene tags/condicionales, renderizar con valores.
						if (ConceptoVentaPlantillaMotor::tieneTagsSinResolver((string) $descripcion)
							|| ConceptoVentaPlantillaMotor::extraerClaves($plantillaConcepto) !== []) {
							$basePlantilla = ConceptoVentaPlantillaMotor::tieneTagsSinResolver((string) $descripcion)
								? (string) $descripcion
								: $plantillaConcepto;
							$resueltoTxt = ConceptoVentaPlantillaMotor::resolver($basePlantilla, $tagValoresLinea, $metasTags);
							$descripcion = $resueltoTxt['texto'];
							$tagValoresLinea = $resueltoTxt['valores'];
						}

						if (ConceptoVentaTagSupport::tieneTagsSinResolver((string) $descripcion)) {
							return ['error' => ConceptoVentaTagSupport::mensajeTagsPendientes((string) $descripcion)];
						}
					}
				}
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
			if ((float) str_replace(',', '', (string) $precioUnitario) == 0.0 && $precioCatalogo > 0) {
				$precioUnitario = $precioCatalogo;
			}

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

			$piezaLinea = (float) str_replace(',', '', (string) ($piezasInput[$offItem] ?? 0));
			$cajaLinea = (float) str_replace(',', '', (string) ($cajasInput[$offItem] ?? 0));
			$cantidadLinea = (float) str_replace(',', '', (string) $cantidad);
			if (facturaUsaLayoutItemsPedido() && $articulo_id && $cantidadLinea != 0.0
				&& $piezaLinea == 0.0 && $cajaLinea == 0.0) {
				$pesoLinea = (float) ($articulo->peso ?? 0);
				$unidadesEnvase = (float) ($articulo->unidadesxenvase ?? 0);
				if ($pesoLinea != 0.0) {
					$piezaLinea = $cantidadLinea / $pesoLinea;
					$cajaLinea = $unidadesEnvase != 0.0 ? $piezaLinea / $unidadesEnvase : 0.0;
				}
			}

			$leyendaLinea = $articulo_id
				? trim((string) ($leyendasLineaInput[$offItem] ?? ''))
				: '';
			$detalleLinea = $descripcion;
			if ($leyendaLinea !== '') {
				$detalleLinea = $leyendaLinea;
			}

			$dataFactura[] = ["cantidad" => $cantidadLinea,
				"pieza" => $piezaLinea,
				"caja" => $cajaLinea,
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
				"detalle" => $detalleLinea,
				"leyenda_linea" => $leyendaLinea,
				"codigounidadmedida" => $codigoUnidadMedida,
				'categoria' => $codigoCategoria,
				'moneda_id' => $moneda_id,
				'listaprecio_id' => $listaprecio_id,
				'cuentacontable_id' => $cuentaContable_id,
				'impuesto_interno_coeficiente' => $impuestoInternoCoeficiente,
				'omitir_stkmov_anita' => $omitirStkmovAnita,
				'concepto_venta_id' => $conceptoVentaIdLinea,
				'contrato_venta_id' => $contratoVentaIdLinea,
				'tag_valores' => $tagValoresLinea,
				'periodo_desde' => $periodoDesdeLinea,
				'periodo_hasta' => $periodoHastaLinea,
				'codigo_mtx' => $codigoMtxLinea,
				'unidades_mtx' => $unidadesMtxLinea,
				'centrocosto_id' => $centrocostoConceptoId,
			];
			$totCantidad += $cantidad;
		}
		// Arma datos del cliente
		$provinciaDatosCliente = $this->esEmisionPos($data)
			? ($cliente->provincia_id ? (int) $cliente->provincia_id : null)
			: ClienteProvinciaIibbSupport::idParaPercepcionAdmin($cliente);

		if (strtoupper(config('app.empresa') == "EL BIERZO"))
			$datosCliente = [ "condicioniva_id" => $cliente->condicioniva_id,
							"numerodocumento" => $cliente->numerodocumento,
							"retieneiva" => $cliente->retieneiva,
							"condicioniibb_id" => $cliente->condicioniibb_id,
							"provincia" => $provinciaDatosCliente,
							"descuentoimportepie" => $this->descuentoImportePie,
							"id" => $cliente->id,
							"abasto_id" => $cliente->abasto_id,
							"porcentajelogistica" => $cliente->porcentajelogistica,
							"empresa_id" => $data['empresa_id'] ?? null,
							];
		else
			$datosCliente = [ "condicioniva_id" => $cliente->condicioniva_id,
							"numerodocumento" => $cliente->numerodocumento,
							"retieneiva" => $cliente->retieneiva,
							"condicioniibb_id" => $cliente->condicioniibb_id,
							"provincia" => $provinciaDatosCliente,
							"descuentoimportepie" => $this->descuentoImportePie,
							"id" => $cliente->id,
							"empresa_id" => $data['empresa_id'] ?? null,
							];
		if (! empty($data['omitir_percepciones'])) {
			$datosCliente['omitir_percepciones'] = true;
			$datosCliente['condicioniibb_id'] = (int) config('gastronomia.consumidor_final_condicioniibb_id', 4);
			$datosCliente['retieneiva'] = 'N';
			$datosCliente['provincia'] = null;
			if (! empty($data['venta_receptor']['numerodocumento'])) {
				$datosCliente['numerodocumento'] = $data['venta_receptor']['numerodocumento'];
			}
			if (! empty($data['aplicar_percepcion_no_categorizado'])) {
				$datosCliente['aplicar_percepcion_no_categorizado'] = true;
			}
		}
		if (! empty($data[ElBierzoFacturaBPercepcionCabaSupport::FLAG])) {
			$datosCliente[ElBierzoFacturaBPercepcionCabaSupport::FLAG] = true;
			// Restaura IIBB real: el overwrite a "No Retiene" dejaría CABA sin tasa.
			$datosCliente['condicioniibb_id'] = $cliente->condicioniibb_id;
			$datosCliente['provincia'] = $this->esEmisionPos($data)
				? ($cliente->provincia_id ? (int) $cliente->provincia_id : null)
				: ClienteProvinciaIibbSupport::idParaPercepcionAdmin($cliente);
		}
		NotaCreditoPercepcionIibbSupport::anexarOrigenSiCorresponde(
			$datosCliente,
			$data,
			! $this->esEmisionPos($data)
		);
		// Calcula impuestos
		$conceptosTotales = $this->impuestoService->calculaImpuestoVenta($dataFactura, $datosCliente, $fechaFactura, 
																			$this->flGrabaComprobanteDividido);

		// Arma total de comprobante
		$totalComprobante = $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Total', 'importe');

		return $this->conSugerenciaTipoPreview(
			['datosfactura' => $dataFactura, 'datoscliente' => $datosCliente, 'totalcomprobante' => $totalComprobante,
				'conceptostotales' => $conceptosTotales],
			$cliente,
			(float) $totalComprobante,
			$letra
		);
	}

	// Genera factura general

	public function generaComprobanteGeneral(array $data)
	{
		if (empty($data[ElBierzoFacturacionCaeaSaltoSupport::FLAG_INTERNO])) {
			$data[ElBierzoFacturacionCaeaSaltoSupport::FLAG_INTERNO] = true;

			return ElBierzoFacturacionCaeaSaltoSupport::ejecutarConReintento(
				$data,
				fn (array $dataSalto) => $this->generaComprobanteGeneral($dataSalto)
			);
		}
		unset($data[ElBierzoFacturacionCaeaSaltoSupport::FLAG_INTERNO]);

		$data = $this->normalizaItemsFacturaGeneralDesdePedido($data);

		UsuarioPreferenciaFacturacionSupport::guardar($data);

		$this->activarGrabacionAnitaVillafrancaSiNotaCreditoDivision(
			(int) ($data['puntoventa_id'] ?? 0),
			(int) ($data['tipotransaccion_id'] ?? 0)
		);

		// Recalcula factura
		$calculoFactura = Self::calculaFacturaGeneral($data);

		if (isset($calculoFactura['error'])) {
			return ['error' => $calculoFactura['error']];
		}

		$puntoventa_id = $data['puntoventa_id'];
		$tipoTransaccion_id = $data['tipotransaccion_id'];
		$fechaFactura = $data['fechafactura'] ?? ($data['fecha'] ?? null);
		$leyenda = $data['leyendafactura'] ?? '';
		$actividad_arca_id = $data['actividad_arca_id'] ?? null;

		$dataFactura = $calculoFactura['datosfactura'];
		$conceptosTotales = $calculoFactura['conceptostotales'];
		$datosCliente = $calculoFactura['datoscliente'];
		$totalComprobante = $calculoFactura['totalcomprobante'];

		if (isset($data['venta_id']))
			$venta_id = $data['venta_id'];
		else	
			$venta_id = 0;

		$cliente_id = $data['cliente_id'];
		$this->descuentoPie = $data['descuentopie'] ?? 0;
		$this->descuentoLinea = $data['descuentolinea'] ?? 0;
		$this->descuentoImportePie = $data['descuentoimportepie'] ?? 0;

		if (isset($data['fecha']))
			$fechaFactura = $data['fecha'];
		else
			$fechaFactura = $data['fechafactura'];

		$tipotransaccion = $this->tipotransaccionRepository->find($tipoTransaccion_id);

		$codigoTipoTransaccion = $tipotransaccion->codigo;
		$this->nombreTipoTransaccion = $tipotransaccion->nombre;
		$signo = $tipotransaccion->signo == 'S' ? 1. : -1.;

		$tipoAnita = $this->tipoAnitaSegunCodigoAfip($tipotransaccion, $codigoTipoTransaccion);

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

		if ($errorDespacho = $this->errorClienteDespachoNoFacturable($data, $cliente_id)) {
			return $errorDespacho;
		}

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

		$this->aplicarTipotransaccionSegunClienteMonto(
			$data,
			$cliente,
			(float) $totalComprobante,
			(string) $letra,
			$tipoTransaccion_id
		);

		// Calcula vencimientos
		$cuentacorriente = $this->calculaCondicionVenta($fechaFactura, 
														$totalComprobante, 
														$condicionventa_id);

		// Lee punto de venta
		$puntoventa = $this->puntoventaRepository->find($puntoventa_id);
		$cuentacorriente = $this->aplicarVencimientoVillafrancaSiCorresponde($cuentacorriente, $fechaFactura, $puntoventa);

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

			$tipoAnita = $this->tipoAnitaSegunCodigoAfip($tipotransaccion, $codigoTipoTransaccion);

			$modoClienteFce = $this->hidratarContextoFceCliente($data, $cliente, $totalComprobante);
			$this->facturaelectronicaService->armaTipoTransaccion($letra, $modoClienteFce, $codigoTipoTransaccion,
																	$puntoventa, $totalComprobante);
			$tipoAnita = $this->tipoAnitaSegunCodigoAfip($tipotransaccion, $codigoTipoTransaccion);

			$reservaCaeaErr = $this->aplicarReservaNumeracionCaeaEnData($data, $puntoventa, $tipotransaccion, $letra);
			if ($reservaCaeaErr !== null) {
				return $reservaCaeaErr;
			}
			$this->propagarOmitirNumeraAnitaFinEnOpcionesEmision($data);

			$numeroForzado = (int) ($data['numerocomprobante_forzado'] ?? 0);
			$opcionesEmisionNumeracion = is_array($data['opciones_emision'] ?? null) ? $data['opciones_emision'] : [];
			switch($puntoventa->modofacturacion)
			{
				case 'C':
				case 'E':
					if ($numeroForzado > 0) {
						$numero = $numeroForzado - 1;
					} else {
						PedidoFacturacionProfiler::etapa('arca_ultimo_numero_inicio');
						$numero = $this->facturaelectronicaService
									->traeUltimoNumeroComprobante(
										$empresa->nroinscripcion,
										$codigoTipoTransaccion,
										$puntoventa,
										$opcionesEmisionNumeracion,
									);
						PedidoFacturacionProfiler::etapa('arca_ultimo_numero_fin');
					}
					break;
				case 'A':
					if ($numeroForzado > 0) {
						$numero = $numeroForzado - 1;
					} else {
						$numero = VentaNumeracionEmpresaSupport::maxNumerocomprobanteErpDesdeTipotransaccion(
							$puntoventa_id,
							$tipotransaccion->codigo,
							$letra,
							(int) ($puntoventa->empresa_id ?? 0) ?: null,
							$cliente->modoFacturacion ?? null,
							$totalComprobante,
						);
					}
					break;
				case 'M':
					if ($numeroForzado > 0) {
						$numero = $numeroForzado - 1;
					} else {
						$numero = $this->ultimoNumeroBaseModoManual(
							$puntoventa,
							$tipotransaccion,
							$letra,
							$cliente,
							$totalComprobante,
						);
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
					$asientoFactura = $factura->asientos;
					if ($asientoFactura) {
						$asientoContable = $this->asientoInvertidoDesdeFactura(
							$asientoFactura,
							(float) $totalComprobante,
							(int) $empresa->id
						);
						$ultimoMov = $asientoFactura->asiento_movimientos->last();
						if ($ultimoMov) {
							$centrocosto_id = $ultimoMov->centrocosto_id;
						}
						if ($asientoContable === []) {
							$asientoContable = Self::armaContabilidad($dataFactura, $conceptosTotales, $empresa->id, $totalComprobante);
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

				// Procesa Factura electronica (o NC Villafranca: Anita usa el mismo payload).
				if ($puntoventa->modofacturacion != 'M' || $this->flGrabaComprobanteDividido)
				{
					// Arma tributos
					$tributos = [];
					$totalTributo = 0;
					$this->facturaelectronicaService->armaTributo($conceptosTotales, $tributos, $totalTributo);

					// Arma impuestos
					$impuestos = [];
					$this->facturaelectronicaService->armaImpuesto($conceptosTotales, $impuestos);

					// NC/ND: asociar la factura origen (ARCA MTXCA/WSFE exige comprobante o rango).
					$comprobantesAsociados = $this->armaComprobantesAsociadosDesdeFactura($factura);

					[$fechaAsignacionDesdeYmd, $fechaAsignacionHastaYmd] = $this->resolverFechasAsignacionPeriodoAsoc(
						$data,
						$fechaFactura,
					);
					
					// Lee moneda
					$moneda = Moneda::find($moneda_id);
					$codigoMoneda = 'PES';
					if ($moneda) {
						$codigoMoneda = $moneda->abreviatura ?: 'PES';
					}

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
							'logistica' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Total Logistica', 'importe'),
							'tributo' => $totalTributo,
							'fechavencimiento' => date('Ymd', strtotime($cuentacorriente[0]['fechavencimiento'])),
							'moneda' => $codigoMoneda,
							'cotizacion' => ($codigoMoneda == 'PES' ? 1. : $cotizacion),
							'tributos' => $tributos,
							'impuestos' => $impuestos,
							'comprobantesasociados' => $comprobantesAsociados,
							'fechaasignaciondesde' => $fechaAsignacionDesdeYmd,
							'fechaasignacionhasta' => $fechaAsignacionHastaYmd,
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
				$opcionesEmision = $data['opciones_emision'] ?? [];
				if (! is_array($opcionesEmision)) {
					$opcionesEmision = [];
				}
				// POS gastronomía / estacionamiento / canje: no resolver depósito ni reparto.
				if (! $this->esEmisionPos($data, $opcionesEmision)) {
					if (empty($opcionesEmision['deposito_id'])) {
						$opcionesEmision['deposito_id'] = $this->depositoIdDesdePayload($data, $clienteGraba);
					}
					if (empty($opcionesEmision['transporte_id'])) {
						$opcionesEmision['transporte_id'] = TransporteDepositoSupport::transporteIdDesdeFactura($data, $clienteGraba);
					}
				}
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
		UsuarioPreferenciaFacturacionSupport::guardar($data);

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

		$deposito = $this->depositoIdDesdePayload($data);

		$this->descuentoPie = $data['descuentopie'];
		$this->descuentoLinea = $data['descuentolinea'];
		$this->descuentoImportePie = $data['descuentoimportepie'];
		$this->cantidadBulto = $this->normalizarCantidadBulto($data['cantidadbulto'] ?? 0);
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
							$errorEntrega = $this->resolverLugarEntregaPedido($cliente, $pedido, [], true);
							if ($errorEntrega) {
								return $errorEntrega;
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
		$this->sincronizarLugarEntregaPedido($pedido);
		$provinciaPercepcion = $this->provinciaPercepcionDesdePedido($cliente, $pedido);

		// Lee punto de venta (empresa jurídica = sucursal elegida)
		$puntoventa = $this->puntoventaRepository->find($puntoventa_id);

		// Arma datos del cliente
		$datosCliente = [ "condicioniva_id" => $cliente->condicioniva_id,
						  "numerodocumento" => $cliente->numerodocumento,
						  "retieneiva" => $cliente->retieneiva,
						  "condicioniibb_id" => $cliente->condicioniibb_id,
						  "provincia" => $provinciaPercepcion,
						  "descuentoimportepie" => $this->descuentoImportePie,
						  "id" => $cliente->id,
						  "empresa_id" => $puntoventa->empresa_id ?? null,
						];

		// Calcula impuestos
		$conceptosTotales = $this->impuestoService->calculaImpuestoVenta($dataFactura, $datosCliente, $fechaFactura);

		// Arma total de comprobante
		$totalComprobante = $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Total', 'importe');

		// Calcula vencimientos
		$cuentacorriente = $this->calculaCondicionVenta($fechaFactura, 
														$totalComprobante, 
														$pedido->condicionventa_id);
		$cuentacorriente = $this->aplicarVencimientoVillafrancaSiCorresponde($cuentacorriente, $fechaFactura, $puntoventa);
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
			$emiteRemito = $this->tipoEmiteRemito($tipotransaccion);
			// Numera factura con web service si es factura electronica
			if ($puntoventa->modofacturacion != 'M')
			{
				$modoClienteFce = $this->hidratarContextoFceCliente($data, $cliente, $totalComprobante);
				$this->facturaelectronicaService->armaTipoTransaccion($letra, $modoClienteFce, $codigoTipoTransaccion,
																		$puntoventa, $totalComprobante);

				$numero = $this->facturaelectronicaService
							->traeUltimoNumeroComprobante($empresa->nroinscripcion,
															$codigoTipoTransaccion,
															$puntoventa);

				//$numero = 74405;
			}
			else // Numera manualmente: max+1 por tipo, letra y punto de venta
			{
				$numero = $this->ultimoNumeroBaseModoManual(
					$puntoventa,
					$tipotransaccion,
					$letra,
					$cliente,
					$totalComprobante,
				);
			}

			if ($numero != -1)
			{
				$numero++;

				// Remito solo con FAC/FCE
				if ($emiteRemito && $puntoventaremito && $puntoventa->modofacturacion != 'M')
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

					[$fechaAsignacionDesdeYmd, $fechaAsignacionHastaYmd] = $this->resolverFechasAsignacionPeriodoAsoc(
						$data,
						$fechaFactura,
					);
					
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
							'logistica' => $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Total Logistica', 'importe'),
							'tributo' => $totalTributo,
							'fechavencimiento' => date('Ymd', strtotime($cuentacorriente[0]['fechavencimiento'])),
							'moneda' => $codigoMoneda,
							'cotizacion' => 1,
							'tributos' => $tributos,
							'impuestos' => $impuestos,
							'comprobantesasociados' => $comprobantesAsociados,
							'fechaasignaciondesde' => $fechaAsignacionDesdeYmd,
							'fechaasignacionhasta' => $fechaAsignacionHastaYmd,
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
					$tipoAnita = $this->tipoAnitaSegunCodigoAfip($tipotransaccion, $codigoTipoTransaccion);

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
						'puntoventaremito_id' => $emiteRemito ? $this->puntoventaremito_id : null,
            			'numeroremito' => $emiteRemito ? $numeroremito : 0,
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
						$anita = $this->grabaAnitaConReintentoPorDuplicado($puntoventa->codigo, $letra, $emiteRemito ? $puntoventaremito->codigo : 0, $emiteRemito ? $numeroremito : 0,
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
		$depositoIdEmision = (int) (is_array($opcionesEmision) ? ($opcionesEmision['deposito_id'] ?? 0) : 0);
		$transporteIdEmision = (int) (is_array($opcionesEmision) ? ($opcionesEmision['transporte_id'] ?? 0) : 0);
		if ($transporteIdEmision <= 0) {
			$transporteIdEmision = (int) ($cliente->transporte_id ?? 0);
		}

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
		$this->activarGrabacionAnitaVillafrancaSiSignoDivision((int) ($puntoventa->id ?? 0), $signo);
		// CAE (C/E): el número lo asigna ARCA; Anita no debe avanzar compemis.
		// CAEA AGG: el ERP ya numeró. El Bierzo CAEA: Anita sigue vivo, numeraAnita al cierre.
		if (
			! $omitirNumeraAnitaFin
			&& in_array((string) ($puntoventa->modofacturacion ?? ''), ['C', 'E'], true)
		) {
			$omitirNumeraAnitaFin = true;
		}
		if (
			! $omitirNumeraAnitaFin
			&& ($puntoventa->modofacturacion ?? '') === 'A'
			&& ! EntornoEmpresaSupport::esElBierzo()
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
			$tipoAnita = $this->tipoAnitaSegunCodigoAfip($tipotransaccion, $codigoTipoTransaccion);

			// Prioridad: opciones_emision (gastronomía/proceso cierre) → no pisar con fechafactura CAEA.
			$fechaJornada = $fechaFactura;
			if (is_array($opcionesEmision) && ! empty($opcionesEmision['fechajornada'])) {
				$fechaJornada = (string) $opcionesEmision['fechajornada'];
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
				'transporte_id' => $transporteIdEmision > 0 ? $transporteIdEmision : null,
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
				'venta_origen_id' => $this->ventaOrigenIdParaNcDividida($puntoventa, (int) $venta_id),
			];	

			// Graba venta
			$intentoCreateVenta = 0;
			while (true) {
				try {
					$vta = $this->ventaRepository->create($venta);
					break;
				} catch (QueryException $e) {
					$modoPv = (string) ($puntoventa->modofacturacion ?? '');
					$puedeRenumerarErp = $modoPv === 'A'
						|| ($modoPv === 'M' && EntornoEmpresaSupport::esElBierzo());
					if (
						$intentoCreateVenta > 0
						|| ! $puedeRenumerarErp
						|| ! VentaNumerocomprobanteUnicidadSupport::esViolacionNumerocomprobante($e)
					) {
						throw $e;
					}

					$numero = VentaNumeracionEmpresaSupport::maxNumerocomprobanteErpDesdeTipotransaccion(
						(int) $puntoventa->id,
						$tipotransaccion->codigo,
						$letra,
						(int) ($puntoventa->empresa_id ?? 0) ?: null,
						$cliente->modoFacturacion ?? null,
						abs((float) $totalComprobante),
					) + 1;

					$venta['numerocomprobante'] = $numero;
					$venta['codigo'] = $tipoAnita.' '.$letra.'-'
						.str_pad($puntoventa->codigo, config('facturacion.DIGITOS_SUCURSAL'), '0', STR_PAD_LEFT).'-'
						.str_pad((string) $numero, config('facturacion.DIGITOS_COMPROBANTE'), '0', STR_PAD_LEFT);

					if (is_array($dataCAE)) {
						$dataCAE['numerocomprobante'] = $numero;
					}

					$intentoCreateVenta++;
					Log::warning('facturacion.grabaFacturaERP.numeracion_duplicada_reintento', [
						'puntoventa_id' => $puntoventa->id,
						'numerocomprobante' => $numero,
					]);
				}
			}

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
					// Caja/pieza: solo El Bierzo (pedido/remito/mostrador). Gastronomía y estacionamiento no.
					if (EntornoEmpresaSupport::esElBierzo()
						&& UnidadesCajaPiezaSupport::articuloMovimientoTieneColumnas()) {
						$unidades = UnidadesCajaPiezaSupport::extraerDeLinea($itemEmision);
						$dataArticuloMovimiento['caja'] = $unidades['caja'];
						$dataArticuloMovimiento['pieza'] = $unidades['pieza'];
					}
					if ($depositoIdEmision > 0) {
						$dataArticuloMovimiento['deposito_id'] = $depositoIdEmision;
					}
				}

				$dataEmision = VentaEmisionCajaPiezaSupport::filtrarPayload([
					'venta_id' => $vta->id,
					'numeroitem' => ++$numeroItem, 
					'lotestock' => 0,
					'detalle' => $itemEmision['detalle'] ?? $itemEmision['descripcion'],
					'cantidad' => abs($itemEmision['cantidad']),
					'pieza' => $itemEmision['pieza'] ?? 0,
					'caja' => $itemEmision['caja'] ?? 0,
					'precio' => $itemEmision['precio'],
					'impuesto_id' => $itemEmision['impuesto_id'],
					'incluyeimpuesto' => $itemEmision['incluyeimpuesto'], 
					'moneda_id' => $itemEmision['moneda_id'], 
					'descuento' => $itemEmision['descuento'], 
					'descuentointegrado' => $itemEmision['descuentointegrado'],
				]);
				if ($depositoIdEmision > 0) {
					$dataEmision['deposito_id'] = $depositoIdEmision;
				}
				if (! empty($itemEmision['articulo_id'])) {
					$dataEmision['articulo_id'] = $itemEmision['articulo_id'];
				}
				$dataEmision = $this->anexarConceptoEnEmision($dataEmision, $itemEmision);
				$venta_emision = $this->venta_emisionRepository->create($dataEmision);
				ContratoVentaEmisionSupport::persistirTrasCrearEmision($venta_emision, $itemEmision, (int) $vta->id);

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
			$omitirAnitaAsientoMostrador = ! $transaccionExterna
				&& PedidoFacturaAnitaDeferSupport::debeDiferir();
			Self::grabaAsientoContable($asientoContable, $puntoventa->empresa_id, $fechaFactura, $vta->id, 
									$detalleContable, $centrocosto_id,
									$moneda_id, $cotizacion, $signo, $cliente->cuentacontable_id,
									substr($venta['codigo'],0,3), $letra, $puntoventa->codigo, $venta['numerocomprobante'],
									$puntoventa->modofacturacion ?? null,
									isset($venta['fechajornada']) ? (string) $venta['fechajornada'] : null,
									$omitirAnitaAsientoMostrador);
			}

			$ret = [
				'factura' => substr($venta['codigo'], 0, 3).' '.$letra.' '.$puntoventa->codigo.'-'.$venta['numerocomprobante'],
				'error' => '',
				'venta_id' => $vta->id,
			];

			if ($puntoventa->modofacturacion != 'M' || $this->flGrabaComprobanteDividido)
			{
				$deferAnitaMostrador = ! $transaccionExterna
					&& PedidoFacturaAnitaDeferSupport::debeDiferir();
				$deferAnitaTrasCommit = false;
				$modoMinimoAnita = is_array($opcionesEmision)
					&& ! empty($opcionesEmision['anita_modo_minimo']);

				if (! $omitirSincronizacionAnita) {
					$deferAnitaTrasCommit = $transaccionExterna
						&& $modoMinimoAnita
						&& $this->anitaTrasCommitAlFacturarHabilitado($opcionesEmision);
					$deferAnitaAhora = $deferAnitaTrasCommit || $deferAnitaMostrador;

					if ($deferAnitaAhora) {
						$ret['anita_pendiente'] = [
							'puntoventa_codigo' => $puntoventa->codigo,
							'letra' => $letra,
							'puntoventaremito_codigo' => 0,
							'numeroremito' => 0,
							'venta' => $venta,
							'data_cae' => $dataCAE,
							'conceptos_totales' => $conceptosTotales,
							'cuentacorriente' => $cuentacorriente,
							'data_factura' => $dataFactura,
							'signo' => $signo,
							'codigo_tipo_transaccion' => $codigoTipoTransaccion,
							'pedido_id' => 0,
							'numero_orden_venta' => $numeroOrdenventa,
							'codigo_centrocosto' => $codigoCentrocosto,
							'referencia_factura' => $referenciaFactura,
							'empresa_codigo' => $empresa->codigo,
							'modo_minimo_anita' => $modoMinimoAnita,
							'omitir_cuenta_corriente_anita' => $omitirCuentaCorriente,
							'omitir_stkmov_anita' => $omitirStkmovAnita,
							'omitir_numera_anita_fin' => $omitirNumeraAnitaFin,
							'modo_facturacion_puntoventa' => $puntoventa->modofacturacion ?? null,
							'path_sistema' => PedidoFacturaAnitaArchivosSupport::pathSistemaParaSucursal($puntoventa->codigo),
						];
					} else {
						$replicacionAnitaIntentada = true;
						PedidoFacturacionProfiler::etapa('anita_graba_inicio');
						// Graba anita
						$anita = $this->grabaAnitaConReintentoPorDuplicado($puntoventa->codigo, $letra, 0, 0,
									$venta, $dataCAE, $conceptosTotales, $cuentacorriente, $dataFactura, $signo,
									$codigoTipoTransaccion, null,
									true, $numeroOrdenventa, $codigoCentrocosto, $referenciaFactura,
									$empresa->codigo, null, null, $modoMinimoAnita, $omitirCuentaCorriente,
									$omitirStkmovAnita, $omitirNumeraAnitaFin, $puntoventa->modofacturacion ?? null);

						PedidoFacturacionProfiler::etapa('anita_graba_fin');

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

				if ($puntoventa->modofacturacion != 'M') {
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
						$deferVencaeAnita = $deferAnitaTrasCommit || $deferAnitaMostrador;
						// Solicita CAE/CAEA en ARCA (último paso del flujo estándar).
						Self::solicitaComprobanteARCA($empresa, $codigoTipoTransaccion, substr($venta['codigo'], 0, 3),
							$letra, $puntoventa, $venta['numerocomprobante'], $fechaFactura, $dataCAE, $vta->id,
							$deferVencaeAnita);
						if ($deferVencaeAnita) {
							$vencaePendiente = $this->armarVencaePendienteDesdeCaePendiente([
								'venta_id' => $vta->id,
								'tipo_anita' => substr($venta['codigo'], 0, 3),
								'letra' => $letra,
								'puntoventa' => $puntoventa,
								'numero_comprobante' => $venta['numerocomprobante'],
							]);
							if ($vencaePendiente !== null) {
								$ret['vencae_pendiente'] = $vencaePendiente;
							}
						}
					}
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

			if (! $transaccionExterna && PedidoFacturaAnitaDeferSupport::debeDiferir()
				&& (! empty($ret['anita_pendiente']) || ! empty($ret['vencae_pendiente']))) {
				PedidoFacturaAnitaDeferSupport::programar(
					(int) ($ret['venta_id'] ?? 0),
					is_array($ret['anita_pendiente'] ?? null) ? $ret['anita_pendiente'] : null,
					is_array($ret['vencae_pendiente'] ?? null) ? $ret['vencae_pendiente'] : null,
					'mostrador',
				);
				unset($ret['anita_pendiente'], $ret['vencae_pendiente']);
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

		if (! $puntoventa) {
			return;
		}
		if (PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision((int) ($puntoventa->id ?? 0))) {
			$this->flGrabaComprobanteDividido = true;
		}
		if (($puntoventa->modofacturacion ?? '') === 'M' && ! $this->debeReplicarAnitaVillafrancaAunqueModoManual($puntoventa)) {
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
		if (! $puntoventa) {
			return;
		}
		if (PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision((int) $puntoventa->id)) {
			$this->flGrabaComprobanteDividido = true;
		}
		if (($puntoventa->modofacturacion ?? '') === 'M' && ! $this->debeReplicarAnitaVillafrancaAunqueModoManual($puntoventa)) {
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
		\App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport::aplicarCortesiaMinimaEnPayloadAnita($venta, $dataCAE);

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
		$totalPercNoCateg = PercepcionNoCategorizadoSupport::importeDesdeConceptos($conceptostotales);
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

		$vendedor = $this->codigoVendedorAnitaParaGraba($venta, $cliente);
		$cobrador = $this->codigoCobradorAnitaParaGraba($cliente);
		$codigoZonavta = Zonavta::codigoAnitaDesdeId($zonavta_id ? (int) $zonavta_id : null);
		$codigoSubzona = (int) ($subzonavta_id ?: 0);
		// POS gastronomía: domicilio. Este método es solo modo mínimo gastro.
		$codigoZonamult = ClienteAnitaZonamultSupport::codigoDesdeProvinciaId(
			$provincia_id ? (int) $provincia_id : null
		);
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
			$totalPercNoCateg,
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
			$codigoZonavta,
			$codigoSubzona,
			$codigoZonamult,
			$vendedor,
			$cobrador,
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
		if ($modoMinimoAnita) {
			\App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport::aplicarCortesiaMinimaEnPayloadAnita($venta, $dataCAE);
		}

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
		$totalPercNoCateg = PercepcionNoCategorizadoSupport::importeDesdeConceptos($conceptostotales);
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
		$vendedor = $this->codigoVendedorAnitaParaGraba($venta, $cliente);
		$cobrador = $this->codigoCobradorAnitaParaGraba($cliente);
		$codigoZonavta = Zonavta::codigoAnitaDesdeId($zonavta_id ? (int) $zonavta_id : null);
		$codigoSubzona = (int) ($subzonavta_id ?: 0);
		$entregaAnita = null;
		if (! $modoMinimoAnita && (int) ($venta['cliente_entrega_id'] ?? 0) > 0) {
			$entregaAnita = $this->cliente_entregaRepository->find($venta['cliente_entrega_id']);
		}
		$codigoZonamult = ClienteProvinciaIibbSupport::codigoZonamultParaAnita(
			$cliente,
			$modoMinimoAnita,
			$entregaAnita
		);
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
							'".$totalPercNoCateg."',
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
							'".$codigoZonavta."',
							'".$codigoSubzona."',
							'".$codigoZonamult."',
							'".$vendedor."',
							'".$cobrador."',
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
							'".$codigoZonavta."',
							'".$codigoSubzona."',
							'".$codigoZonamult."',
							'".$vendedor."',
							'".$cobrador."',
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

		$this->aplicarPathSistemaAnitaComprobante($data, $puntoventa);

		if (! $this->debeOmitirTablaAnita('venta')) {
			$errVta = $this->apiCallAnitaEscritura($apiAnita, $data, 'venta insert');
			if ($errVta !== null) {
				return $errVta;
			}
		}

		// vengrav: gastronomía modo mínimo y facturación Ventas completa
		foreach ($conceptostotales as $concepto) {
			if ($this->debeOmitirTablaAnita('vengrav')) {
				break;
			}
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
			$this->aplicarPathSistemaAnitaComprobante($dataVengrav, $puntoventa);

			$errVengrav = $this->apiCallAnitaEscritura($apiAnitaVengrav, $dataVengrav, 'vengrav insert');
			if ($errVengrav !== null) {
				return $errVengrav;
			}
		}

		if (! $modoMinimoAnita) {
		// Graba venibr
		foreach ($conceptostotales as $concepto)
		{
			if ($this->debeOmitirTablaAnita('venibr')) {
				break;
			}
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

					$this->aplicarPathSistemaAnitaComprobante($data, $puntoventa);

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
			$nroCuota++;
			$fechaVencimiento = $cuota['fechavencimiento'];
			if ($this->debeOmitirTablaAnita('climov')) {
				continue;
			}
			$apiAnita = new ApiAnita();

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
			$this->aplicarPathSistemaAnitaComprobante($data, $puntoventa);

			$errClimov = $this->apiCallAnitaEscritura($apiAnita, $data, 'climov insert');
			if ($errClimov !== null) {
				return $errClimov;
			}
		}
	
		$leyenda = str_replace(
			["'", '\\'],
			[' ', ''],
			RemitoFormularioLeyendaSupport::paraCompLeyenda((string) ($venta['leyenda'] ?? ''))
		);

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
							'".(config('app.empresa') == 'EL BIERZO' ? $this->cantidadBultoParaAnita($venta) : $condicionventa)."',
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
		$this->aplicarPathSistemaAnitaComprobante($data, $puntoventa);

		if (! $this->debeOmitirTablaAnita('comprob')) {
			$errComprob = $this->apiCallAnitaEscritura($apiAnita, $data, 'comprob insert');
			if ($errComprob !== null) {
				return $errComprob;
			}
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
							'descripcion' => trim($item['descripcion'].(! empty($item['leyenda_linea']) ? ' '.$item['leyenda_linea'] : '')),
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
			$this->aplicarPathSistemaAnitaComprobante($data, $puntoventa);

			if (! $modoMinimoAnita && ! $this->debeOmitirTablaAnita('compaux')) {
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
			if ($flGrabaStock && ! $omitirStkmovAnita && empty($medida['omitir_stkmov_anita']) && ! $this->debeOmitirTablaAnita('stkmov'))
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
								'".$codigoZonavta."',
								'".$codigoZonamult."',
								'".$codigoSubzona."',
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

				$this->aplicarPathSistemaAnitaComprobante($data, $puntoventa);

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
									'".$codigoZonavta."',
									'".$codigoZonamult."',
									'".$codigoSubzona."',
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

					$this->aplicarPathSistemaAnitaComprobante($data, $puntoventa);

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
					
					$this->aplicarPathSistemaAnitaComprobante($data, $puntoventa);

					$errCompley = $this->apiCallAnitaEscritura($apiAnita, $data, 'compley insert');
					if ($errCompley !== null) {
						return $errCompley;
					}			
				}
			}
		}

		// Omitido: numeración CAEA en ERP, reserva POS, o PV electrónico (C/E) con numeración ARCA/CAE.
		if (
			! $omitirNumeraAnitaFin
			&& in_array((string) ($modoFacturacionPuntoventa ?? ''), ['A', 'C', 'E'], true)
		) {
			$omitirNumeraAnitaFin = true;
		}
		// Villafranca: Reparto 101 ya reservó FAC A 1; dividido copia el número de Bierzo.
		if (! $omitirNumeraAnitaFin && $this->flGrabaComprobanteDividido) {
			$omitirNumeraAnitaFin = true;
		}
		if (! $omitirNumeraAnitaFin && ! $this->debeOmitirTablaAnita('venta')) {
			$resultadoNumera = $this->ventaRepository->numeraAnita(
				substr($venta['codigo'], 0, 3),
				$letra,
				$puntoventa,
				PedidoFacturaAnitaArchivosSupport::pathSistemaParaSucursal($puntoventa),
			);
			// El número ya se eligió ANTES de grabar (ERP + MAX/numerador). Esto solo
			// avanza compemis. Si no hay fila (NCD Villafranca suc. 15), AGG dejaba 0 y seguía.
			if (is_string($resultadoNumera) && stripos($resultadoNumera, 'Error') === 0) {
				return ['error' => 'Error numerador comprobante', 'mensaje' => 'No pudo numerar comprobante: '.$resultadoNumera];
			}
			if (! is_int($resultadoNumera) || $resultadoNumera <= 0) {
				Log::warning('facturacion.anita.numerador_ausente_post_grabacion', [
					'tipo' => substr((string) ($venta['codigo'] ?? ''), 0, 3),
					'letra' => $letra,
					'sucursal' => $puntoventa,
					'numero' => $venta['numerocomprobante'] ?? null,
					'resultado' => $resultadoNumera,
				]);
			}
		}

		// Numera el remito. En réplica diferida (omitirNumeraAnitaFin) no se corta la
		// grabación: el número ya está en el ERP; un fallo acá dejaba venta sin vencae.
		if (isset($puntoventaremito) && ! $this->flGrabaComprobanteDividido)
		{
			$resultadoNumeradorRemito = $this->ventaRepository->numeraAnita('REM', 'R', $puntoventaremito);
			$falloRemito = (EntornoEmpresaSupport::esElBierzo()
					&& (! is_int($resultadoNumeradorRemito) || $resultadoNumeradorRemito <= 0))
				|| (! EntornoEmpresaSupport::esElBierzo() && $resultadoNumeradorRemito == 'Error');
			if ($falloRemito) {
				if ($omitirNumeraAnitaFin) {
					Log::warning('facturacion.anita.remito_numerador_omitido_post_venta', [
						'codigo' => $venta['codigo'] ?? null,
						'puntoventaremito' => $puntoventaremito,
						'numeroremito' => $numeroremito,
						'resultado' => $resultadoNumeradorRemito,
					]);
				} else {
					return ['error' => 'Error numerador remito', 'mensaje' => 'No pudo numerar remito'];
				}
			}
		}

		return ['Success'];
	}

	/**
	 * PeriodoAsoc ARCA: por defecto 1º del mes → fecha factura.
	 * Permite override explícito (lote saneamiento: FchDesde=FchHasta=Ymd jornada).
	 *
	 * @param  array<string, mixed>  $data
	 * @return array{0:string,1:string}  Ymd, Ymd
	 */
	private function resolverFechasAsignacionPeriodoAsoc(array $data, string $fechaFactura): array
	{
		$desde = $data['fechaasignaciondesde'] ?? null;
		$hasta = $data['fechaasignacionhasta'] ?? null;

		$norm = static function ($v): ?string {
			if ($v === null || $v === '') {
				return null;
			}
			$s = trim((string) $v);
			if (preg_match('/^\d{8}$/', $s)) {
				return $s;
			}
			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
				return str_replace('-', '', $s);
			}

			return null;
		};

		$desdeYmd = $norm($desde);
		$hastaYmd = $norm($hasta);
		if ($desdeYmd !== null && $hastaYmd !== null) {
			return [$desdeYmd, $hastaYmd];
		}

		$fechaAsignacion = Carbon::parse($fechaFactura);
		$fechaAsignacion->modify('first day of this month');

		return [
			date('Ymd', strtotime($fechaAsignacion)),
			date('Ymd', strtotime($fechaFactura)),
		];
	}

	private function debeUsarNumeradorVillafrancaPropio(): bool
	{
		return (bool) $this->usaNumeradorVillafrancaPropio && (bool) $this->flGrabaComprobanteDividido;
	}

	private function ventaOrigenIdParaNcDividida($puntoventa, int $ventaAplicadaId): ?int
	{
		$puntoventaId = (int) ($puntoventa->id ?? 0);
		$desdeDivision = VillafrancaFacturacionSupport::ventaOrigenIdParaGrabar(
			$puntoventaId,
			(int) $this->ventaOrigenIdDivision
		);
		if ($desdeDivision) {
			return $desdeDivision;
		}
		if ($ventaAplicadaId <= 0 || ! PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision($puntoventaId)) {
			return null;
		}

		return VillafrancaFacturacionSupport::heredarOrigenIdDesdeVenta(
			Venta::query()->find($ventaAplicadaId)
		);
	}

	/**
	 * Reserva FAC Villafranca (reparto 101) y arma penm_ref_* del remito en pendmae.
	 * Sucursal de referencia = PV de emisión (00001), igual al numerador Anita.
	 *
	 * @return array{tipo: string, letra: string, sucursal: int, nro: int}|array{error: string, mensaje?: string}
	 */
	private function reservarReferenciaPendmaeVillafrancaReparto101($cliente): array
	{
		$letra = 'A';
		$condicioniva = $this->condicionivaRepository->find($cliente->condicioniva_id ?? 0);
		if ($condicioniva && trim((string) ($condicioniva->letra ?? '')) !== '') {
			$letra = strtoupper(substr(trim((string) $condicioniva->letra), 0, 1));
		}

		$numero = $this->reservarNumeroVillafrancaReparto101('FAC', $letra);
		if (is_array($numero)) {
			return $numero;
		}

		$this->numeroReservadoVillafrancaReparto101 = (int) $numero;

		$puntoventaEmision = $this->puntoventaRepository->find(
			VillafrancaFacturacionSupport::idPuntoVentaReparto101()
		);
		$sucursalEmision = (int) ($puntoventaEmision->codigo ?? 0);
		if ($sucursalEmision <= 0) {
			return [
				'error' => 'Error punto de venta Villafranca',
				'mensaje' => 'No se pudo resolver la sucursal de emisión de Villafranca para pendmae.',
			];
		}

		return VillafrancaFacturacionSupport::referenciaPendmaeDesdeFactura(
			'FAC',
			$letra,
			$sucursalEmision,
			(int) $numero
		);
	}

	/**
	 * Reserva el siguiente número en compemis FAC + letra sucursal 1 de Villafranca.
	 *
	 * @return int|array{error:string,mensaje:string}
	 */
	private function reservarNumeroVillafrancaReparto101(string $tipo, string $letra)
	{
		$tipoAnita = strtoupper(trim($tipo)) !== '' ? strtoupper(trim($tipo)) : 'FAC';
		$letraAnita = strtoupper(trim($letra)) !== '' ? strtoupper(trim($letra)) : 'A';
		$sucursal = VillafrancaFacturacionSupport::sucursalNumeradorPropio();
		$path = VillafrancaFacturacionSupport::pathSistema();

		$numero = $this->ventaRepository->numeraAnita($tipoAnita, $letraAnita, $sucursal, $path);
		if (! is_int($numero) || $numero <= 0) {
			$detalle = is_string($numero) ? $numero : 'sin número';

			return [
				'error' => 'Error numerador Villafranca',
				'mensaje' => 'No se pudo reservar '.$tipoAnita.' '.$letraAnita
					.' sucursal '.$sucursal.' en Anita Villafranca: '.$detalle,
			];
		}

		return $numero;
	}

	/**
	 * @param  list<array{fechavencimiento:mixed,total:mixed}>  $cuotas
	 * @return list<array{fechavencimiento:mixed,total:mixed}>
	 */
	private function aplicarVencimientoVillafrancaSiCorresponde(array $cuotas, $fechaFactura, $puntoventa = null): array
	{
		if (! VillafrancaFacturacionSupport::debeForzarVencimientoFechaFactura(
			(bool) $this->flGrabaComprobanteDividido,
			$puntoventa
		)) {
			return $cuotas;
		}

		return VillafrancaFacturacionSupport::aplicarVencimientoFechaFactura($cuotas, $fechaFactura);
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

	/**
	 * ven_vendedor / stkv_vendedor: código Anita del vendedor de la factura (o del cliente).
	 * No usar el id ERP ni el default 1 (Casa) cuando hay vendedor asignado.
	 */
	private function codigoVendedorAnitaParaGraba(array $venta, $cliente): int
	{
		$vendedorId = (int) ($venta['vendedor_id'] ?? 0);
		if ($vendedorId <= 0 && $cliente) {
			$vendedorId = (int) ($cliente->vendedor_id ?? 0);
		}

		$codigo = Vendedor::codigoAnitaDesdeId($vendedorId);

		return $codigo > 0 ? $codigo : 1;
	}

	/**
	 * ven_cobrador: código Anita del cobrador del cliente. 0 si no tiene (no inventar Casa).
	 */
	private function codigoCobradorAnitaParaGraba($cliente): int
	{
		if (! $cliente) {
			return 0;
		}

		return Cobrador::codigoAnitaDesdeId((int) ($cliente->cobrador_id ?? 0));
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
		$this->aplicarPathSistemaAnitaComprobante($data, $puntoventa);							
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
	 * Reserva numeración CAEA en ERP (con lock) si el PV es mod A y aún no hay número forzado.
	 *
	 * @param  array<string, mixed>  $data
	 * @return array{error:string}|null
	 */
	private function aplicarReservaNumeracionCaeaEnData(array &$data, object $puntoventa, object $tipotransaccion, string $letra): ?array
	{
		if (($puntoventa->modofacturacion ?? '') !== 'A') {
			return null;
		}

		if (! empty($data['numerocomprobante_forzado'])) {
			return null;
		}

		$error = CaeaEmisionNumeracionSupport::aplicarReservaNumeracionAlPayload(
			$data,
			$puntoventa,
			$tipotransaccion,
			$letra,
		);

		if ($error !== null) {
			return ['error' => $error];
		}

		return null;
	}

	/**
	 * @param  array<string, mixed>  $data
	 */
	private function propagarOmitirNumeraAnitaFinEnOpcionesEmision(array &$data): void
	{
		if (empty($data['_omitir_numera_anita_fin'])) {
			return;
		}

		if (! is_array($data['opciones_emision'] ?? null)) {
			$data['opciones_emision'] = [];
		}

		$data['opciones_emision']['omitir_numera_anita_fin'] = true;
		unset($data['_omitir_numera_anita_fin']);
	}

	/**
	 * @param  array<string, mixed>  $data
	 * @return int|array{error:string}
	 */
	/**
	 * @param  array<string, mixed>  $payload
	 * @return array<string, mixed>
	 */
	private function conSugerenciaTipoPreview(array $payload, object $cliente, float $totalComprobante, string $letra): array
	{
		return array_merge(
			$payload,
			TipoComprobantePreviewSupport::desdeCliente($cliente, $totalComprobante, $letra)
		);
	}

	/**
	 * Rearma FAC/FCE según cliente receptor MiPyME y monto. No toca NC/ND.
	 *
	 * @param  array<string, mixed>  $data
	 */
	private function aplicarTipotransaccionSegunClienteMonto(
		array &$data,
		object $cliente,
		float $totalComprobante,
		string $letra,
		int|string|null &$tipoTransaccionId
	): void {
		$actual = (int) ($tipoTransaccionId ?: ($data['tipotransaccion_id'] ?? 0));
		$resuelto = TipoComprobantePreviewSupport::resolverTipotransaccionId(
			$actual,
			$cliente,
			$totalComprobante,
			$letra
		);
		if ($resuelto <= 0) {
			return;
		}

		$data['tipotransaccion_id'] = $resuelto;
		$tipoTransaccionId = $resuelto;
	}

	/**
	 * Deja en $data el modo FCE del cliente y el total, para numeración CAEA y ARCA.
	 */
	private function hidratarContextoFceCliente(array &$data, object $cliente, $totalComprobante): ?string
	{
		$modo = trim((string) ($cliente->modofacturacion ?? $cliente->modoFacturacion ?? ''));
		$data['modofacturacion_cliente'] = $modo !== '' ? $modo : null;
		$data['total_comprobante'] = (float) $totalComprobante;

		return $data['modofacturacion_cliente'];
	}

	private function tipoAnitaSegunCodigoAfip(object $tipotransaccion, $codigoTipoTransaccion): string
	{
		$codigo = (int) preg_replace('/\D+/', '', (string) $codigoTipoTransaccion);

		return $codigo >= 200
			? substr((string) ($tipotransaccion->abreviatura ?? 'F'), 0, 1).'CE'
			: (string) ($tipotransaccion->abreviatura ?? 'FAC');
	}

	private function ultimoNumeroBaseModoCaea(
		array &$data,
		object $puntoventa,
		object $tipotransaccion,
		int $tipoTransaccionId,
		string $letra,
	) {
		$reservaErr = $this->aplicarReservaNumeracionCaeaEnData($data, $puntoventa, $tipotransaccion, $letra);
		if ($reservaErr !== null) {
			return $reservaErr;
		}

		$numeroForzado = (int) ($data['numerocomprobante_forzado'] ?? 0);
		if ($numeroForzado > 0) {
			return $numeroForzado - 1;
		}

		$ultimoErp = VentaNumeracionEmpresaSupport::maxNumerocomprobanteErpDesdeTipotransaccion(
			(int) ($puntoventa->id ?? 0),
			$tipotransaccion->codigo ?? 0,
			$letra,
			(int) ($puntoventa->empresa_id ?? 0) ?: null,
			$data['modofacturacion_cliente'] ?? null,
			isset($data['total_comprobante']) ? (float) $data['total_comprobante'] : null,
		);

		$codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision(
			$tipotransaccion->codigo ?? 0,
			$letra,
			$data['modofacturacion_cliente'] ?? null,
			isset($data['total_comprobante']) ? (float) $data['total_comprobante'] : null,
		);

		return CaeaEmisionNumeracionSupport::aplicarPisoCaea(
			(int) ($puntoventa->id ?? 0),
			$ultimoErp,
			$codigoAfip,
		);
	}

	/**
	 * PV modo M. El Bierzo: max(ERP ARCA+letra+PV, Anita tipo+letra+sucursal); el caller suma 1.
	 * AGG y resto: último comprobante del tipotransaccion_id en el PV (sin cambiar).
	 */
	private function ultimoNumeroBaseModoManual(
		object $puntoventa,
		object $tipotransaccion,
		string $letra,
		?object $cliente = null,
		$totalComprobante = null,
	): int {
		if (! EntornoEmpresaSupport::esElBierzo()) {
			$venta = $this->ventaRepository->traeUltimoComprobanteVenta(
				(int) ($tipotransaccion->id ?? 0),
				(int) ($puntoventa->id ?? 0),
				(int) ($puntoventa->empresa_id ?? 0) ?: null,
			);

			return $venta ? (int) $venta->numerocomprobante : 0;
		}

		$ultimoErp = VentaNumeracionEmpresaSupport::maxNumerocomprobanteErpDesdeTipotransaccion(
			(int) ($puntoventa->id ?? 0),
			$tipotransaccion->codigo ?? 0,
			$letra,
			(int) ($puntoventa->empresa_id ?? 0) ?: null,
			is_object($cliente) ? ($cliente->modoFacturacion ?? null) : null,
			$totalComprobante !== null ? (float) $totalComprobante : null,
		);

		$tipoAnita = ((string) ($tipotransaccion->codigo ?? '')) >= '200'
			? substr((string) ($tipotransaccion->abreviatura ?? ''), 0, 1).'CE'
			: (string) ($tipotransaccion->abreviatura ?? 'FAC');
		$path = PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision((int) ($puntoventa->id ?? 0))
			? PedidoFacturaAnitaArchivosSupport::PATH_VILLAFRANCA
			: null;

		$ultimoAnita = $this->ventaRepository->maxNumeroComprobanteAnitaBridge(
			$tipoAnita,
			$letra,
			(string) ($puntoventa->codigo ?? ''),
			$path,
		);

		return max($ultimoErp, $ultimoAnita);
	}

	/**
	 * Último número CAEA en ERP (sin consultar Anita).
	 */
	public function ultimoNumerocomprobanteAnitaCaea(object $puntoventa, object $tipotransaccion, string $letraComprobante): int
	{
		if (($puntoventa->modofacturacion ?? '') !== 'A') {
			return 0;
		}

		return VentaNumeracionEmpresaSupport::maxNumerocomprobanteErpDesdeTipotransaccion(
			(int) ($puntoventa->id ?? 0),
			$tipotransaccion->codigo ?? 0,
			$letraComprobante,
			(int) ($puntoventa->empresa_id ?? 0) ?: null,
		);
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
		$this->aplicarPathSistemaAnitaComprobante($data, $puntoventa);							
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
	/**
	 * Replica en Informix una factura de pedido diferida (El Bierzo, post-respuesta).
	 *
	 * @param  array<string, mixed>  $anitaPendiente
	 */
	/**
	 * @param  list<string>  $omitirTablas
	 */
	public function ejecutarAnitaPendientePedidoBierzo(array $anitaPendiente, array $omitirTablas = []): void
	{
		$venta = $anitaPendiente['venta'] ?? null;
		if (! is_array($venta)) {
			throw new \InvalidArgumentException('anita_pendiente de pedido sin datos de venta.');
		}

		$this->cantidadBulto = $this->cantidadBultoParaAnita($venta);

		$path = PedidoFacturaAnitaArchivosSupport::pathSistema($anitaPendiente);
		$this->prepararGrabacionPedidoAnitaDiferida($path, $omitirTablas);

		try {
			$anita = $this->grabaAnita(
				$anitaPendiente['puntoventa_codigo'] ?? 0,
				(string) ($anitaPendiente['letra'] ?? ''),
				$anitaPendiente['puntoventaremito_codigo'] ?? 0,
				$anitaPendiente['numeroremito'] ?? 0,
				$venta,
				$anitaPendiente['data_cae'] ?? [],
				$anitaPendiente['conceptos_totales'] ?? [],
				$anitaPendiente['cuentacorriente'] ?? [],
				$anitaPendiente['data_factura'] ?? [],
				$anitaPendiente['signo'] ?? 1.,
				$anitaPendiente['codigo_tipo_transaccion'] ?? '',
				(int) ($anitaPendiente['pedido_id'] ?? 0),
				true,
				(int) ($anitaPendiente['numero_orden_venta'] ?? 0),
				(int) ($anitaPendiente['codigo_centrocosto'] ?? 0),
				(string) ($anitaPendiente['referencia_factura'] ?? ''),
				null,
				null,
				! empty($anitaPendiente['modo_minimo_anita']),
				! empty($anitaPendiente['omitir_cuenta_corriente_anita']),
				! empty($anitaPendiente['omitir_stkmov_anita']),
				array_key_exists('omitir_numera_anita_fin', $anitaPendiente)
					? ! empty($anitaPendiente['omitir_numera_anita_fin'])
					: true,
				$anitaPendiente['modo_facturacion_puntoventa'] ?? null,
			);
		} finally {
			$this->anitaOmitirTablas = [];
		}

		if (is_array($anita) && isset($anita['error'])) {
			if ($this->esResultadoGrabaAnitaDuplicado($anita)) {
				return;
			}
			if ($anita['error'] === 'Errvend') {
				throw new \RuntimeException('Error en grabación Anita: el cliente no tiene vendedor asignado.');
			}

			$detalle = trim((string) ($anita['mensaje'] ?? $anita['error'] ?? 'Error desconocido'));

			throw new \RuntimeException('Error en grabación Anita: '.$detalle);
		}
	}

	/**
	 * @param  list<string>  $omitirTablas
	 */
	public function prepararGrabacionPedidoAnitaDiferida(?string $pathSistema, array $omitirTablas = []): void
	{
		$this->flGrabaComprobanteDividido = $pathSistema === PedidoFacturaAnitaArchivosSupport::PATH_VILLAFRANCA;
		$this->anitaOmitirTablas = $omitirTablas;
	}

	public function resetearGrabacionPedidoAnitaDiferida(): void
	{
		$this->anitaOmitirTablas = [];
		$this->flGrabaComprobanteDividido = false;
	}

	private function debeOmitirTablaAnita(string $tabla): bool
	{
		return in_array($tabla, $this->anitaOmitirTablas, true);
	}

	public function ejecutarAnitaPendienteGastronomia(array $anitaPendiente): void
	{
		$venta = $anitaPendiente['venta'] ?? null;
		if (! is_array($venta)) {
			throw new \InvalidArgumentException('anita_pendiente sin datos de venta.');
		}

		$dataCae = $anitaPendiente['data_cae'] ?? [];
		if (! is_array($dataCae)) {
			$dataCae = [];
		}
		if (! empty($anitaPendiente['modo_minimo_anita'])) {
			\App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport::aplicarCortesiaMinimaEnPayloadAnita(
				$venta,
				$dataCae,
				\App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport::esCortesiaMinima((float) ($venta['total'] ?? 0))
					|| abs((float) ($venta['total'] ?? 0)) <= 0.001,
			);
			$anitaPendiente['venta'] = $venta;
			$anitaPendiente['data_cae'] = $dataCae;
		}

		PedidoFacturacionProfiler::etapa('anita_graba_inicio');
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
		PedidoFacturacionProfiler::etapa('anita_graba_fin');

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

		$this->aplicarPathSistemaAnitaComprobante($data, $puntoventa);

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
		$this->aplicarPathSistemaAnitaComprobante($data, $puntoventa);							
		$errVencae = $this->apiCallAnitaEscritura($apiAnita, $data, 'vencae insert');
		if ($errVencae !== null) {
			if ($this->esErrorDuplicadoComprobanteEnAnita((string) ($errVencae['mensaje'] ?? ''))) {
				return 'Success';
			}

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

	/**
	 * Comprobantes asociados ARCA desde una venta origen (NC/ND).
	 *
	 * @return list<array{tipo: int, ptovta: int, nro: int}>
	 */
	private function armaComprobantesAsociadosDesdeFactura($factura): array
	{
		if (! is_object($factura)) {
			return [];
		}

		$codigo = trim((string) ($factura->codigo ?? ''));
		if ($codigo === '' || ! preg_match('/^([A-Z]{3})\s+([A-Z])-(\d+)-(\d+)$/i', $codigo, $m)) {
			return [];
		}

		$tipoAfip = ArcaCaeaAnitaTipoAfipSupport::tipoAfipDesdeAnita((string) $m[1], (string) $m[2]);
		if ($tipoAfip <= 0) {
			return [];
		}

		return [[
			'tipo' => $tipoAfip,
			'ptovta' => (int) $m[3],
			'nro' => (int) $m[4],
		]];
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
		$this->aplicarPathSistemaAnitaComprobante($data, $puntoventa);					
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
		$this->aplicarPathSistemaAnitaComprobante($data, $puntoventa);								
		$errNumerador = $this->apiCallAnitaEscritura($apiAnita, $data, 'numerador update');
		if ($errNumerador !== null) {
			throw new Exception($errNumerador['mensaje']);
		}

		return $numeroOperacion;
	}

	/**
	 * Invierte el asiento de la factura origen para la NC.
	 * Si las piernas tienen el mismo absoluto (caso típico 2 cuentas), usa el total
	 * del comprobante al centavo: round(suma cruda) puede dar .39 y el total .38.
	 *
	 * @return list<array{empresa_id:int, cuentacontable_id:mixed, monto:float}>
	 */
	private function asientoInvertidoDesdeFactura($asientoFactura, float $totalComprobante, int $empresaId): array
	{
		$movimientos = $asientoFactura->asiento_movimientos ?? collect();
		if ($movimientos->isEmpty()) {
			return [];
		}

		$totalAbs = VentaImporteDosDecimalesSupport::redondear(abs($totalComprobante));
		$absMontos = [];
		foreach ($movimientos as $movimiento) {
			$abs = abs((float) $movimiento->monto);
			if ($abs > 0.009) {
				$absMontos[] = $abs;
			}
		}
		$mismoAbsoluto = $absMontos !== []
			&& (max($absMontos) - min($absMontos)) < 0.02;

		$asientoContable = [];
		$empresaAsiento = (int) ($asientoFactura->empresa_id ?? $empresaId);
		foreach ($movimientos as $movimiento) {
			$montoFac = (float) $movimiento->monto;
			if (abs($montoFac) < 0.009) {
				continue;
			}
			$signoFac = $montoFac >= 0 ? 1. : -1.;
			$montoNc = $mismoAbsoluto
				? (-1 * $signoFac * $totalAbs)
				: VentaImporteDosDecimalesSupport::redondear($montoFac * -1);

			$asientoContable[] = [
				'empresa_id' => $empresaAsiento,
				'cuentacontable_id' => $movimiento->cuentacontable_id,
				'monto' => $montoNc,
			];
		}

		return $asientoContable;
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
				$monto = VentaImporteDosDecimalesSupport::redondear($item['cantidad'] * $item['precio']);

				if ($monto != 0)
				{
					if ($item['cuentacontable_id'] > 0)
					{
						$ccItem = (int) ($item['centrocosto_id'] ?? 0);
						for ($i = 0, $flEncontro = false; $i < count($asientoContable); $i++)
						{
							$ccAsiento = (int) ($asientoContable[$i]['centrocosto_id'] ?? 0);
							if ($asientoContable[$i]['cuentacontable_id'] == $item['cuentacontable_id']
								&& $ccAsiento === $ccItem)
							{
								$flEncontro = true;
								break;
							}
						}
						if (!$flEncontro)
							$asientoContable[] = [	
												'empresa_id' => $empresa_id,
												'cuentacontable_id' => $item['cuentacontable_id'],
												'centrocosto_id' => $ccItem > 0 ? $ccItem : null,
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

		// Pie: IVA/perc. ya van sobre gravado neto. Ventas venía en subtotal (renglón).
		$asientoContable = FacturaAsientoDescuentoPieSupport::netearLineasVenta(
			$asientoContable,
			$conceptostotales
		);

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
				
				// RG 2126: no usar el bloque IVA (el concepto no dice "IVA").
				if (PercepcionNoCategorizadoSupport::esConcepto((string) $conc['concepto'])) {
					$cuenta = (int) (RegimenPercepcionSupport::cuentaContableId(
						RegimenPercepcionSupport::CODIGO_PNC,
						(int) $empresa_id
					) ?? 0);
					if (! $cuenta) {
						$codigoCuenta = RegimenPercepcionSupport::codigoCuentaFallback(RegimenPercepcionSupport::CODIGO_PNC);
						if ($codigoCuenta !== '') {
							$cuentacontable = $this->cuentacontableRepository->findPorCodigo($empresa_id, $codigoCuenta);
							$cuenta = $cuentacontable ? $cuentacontable->id : 0;
						}
					}
					if (! $cuenta) {
						throw new Exception('Falta la cuenta contable del régimen PNC (percepción no categorizado) para esta empresa.');
					}
				}

				// Perc. IVA RI (PIVA / RG 5329): grilla del régimen, config como fallback.
				if (RegimenPercepcionSupport::esConceptoPiva((string) $conc['concepto'])) {
					$cuenta = (int) (RegimenPercepcionSupport::cuentaContableId(
						RegimenPercepcionSupport::CODIGO_PIVA,
						(int) $empresa_id
					) ?? 0);
					if (! $cuenta) {
						$codigoCuenta = RegimenPercepcionSupport::codigoCuentaFallback(RegimenPercepcionSupport::CODIGO_PIVA);
						if ($codigoCuenta !== '') {
							$cuentacontable = $this->cuentacontableRepository->findPorCodigo($empresa_id, $codigoCuenta);
							$cuenta = $cuentacontable ? $cuentacontable->id : 0;
						}
					}
					if (! $cuenta) {
						throw new Exception('Falta la cuenta contable del régimen PIVA (percepción IVA RG 5329) para esta empresa.');
					}
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

	/**
	 * El Bierzo: cliente DESPACHO no factura (ventas / pedido / remito). POS gastronomía no aplica.
	 *
	 * @return array{error: string}|null
	 */
	private function errorClienteDespachoNoFacturable(array $data, mixed $clienteId): ?array
	{
		if ($this->esEmisionPos($data)) {
			return null;
		}

		return ClienteDespachoSupport::errorNoFacturable((int) $clienteId);
	}

	private function tipoEmiteRemito($tipotransaccion): bool
	{
		return $tipotransaccion instanceof Tipotransaccion
			&& $tipotransaccion->correspondeRemito();
	}

	/**
	 * Pedido y remito solo emiten factura (operación V). La NC se genera desde el comprobante de venta.
	 *
	 * @return array{error: string}|null
	 */
	private function errorNotaCreditoDesdePedidoORemito($tipotransaccion, string $origen): ?array
	{
		if (! $tipotransaccion) {
			return ['error' => 'Tipo de transacción inexistente'];
		}

		if (! $tipotransaccion->esNotaCredito()) {
			return null;
		}

		$desde = $origen === 'remito' ? 'un remito' : 'un pedido';

		return ['error' => 'No se puede generar una nota de crédito desde '.$desde.'. Use un tipo de factura.'];
	}

	/**
	 * Persiste el concepto en la línea de venta_emision (mismo patrón que ordenventa_concepto).
	 *
	 * @param  array<string, mixed>  $dataEmision
	 * @param  array<string, mixed>  $itemEmision
	 * @return array<string, mixed>
	 */
	private function anexarConceptoEnEmision(array $dataEmision, array $itemEmision): array
	{
		if (! empty($itemEmision['concepto_venta_id'])) {
			$dataEmision['concepto_venta_id'] = (int) $itemEmision['concepto_venta_id'];
		}
		if (! empty($itemEmision['contrato_venta_id'])) {
			$dataEmision['contrato_venta_id'] = (int) $itemEmision['contrato_venta_id'];
		}
		if (! empty($itemEmision['concepto_ordenventa_id'])) {
			$dataEmision['concepto_ordenventa_id'] = (int) $itemEmision['concepto_ordenventa_id'];
		}

		return $dataEmision;
	}

	/**
	 * POS gastronomía, estacionamiento o canje: no usa depósito ni transporte de reparto.
	 *
	 * @param  array<string, mixed>  $data
	 * @param  array<string, mixed>|null  $opcionesEmision
	 */
	private function esEmisionPos(array $data, ?array $opcionesEmision = null): bool
	{
		$op = is_array($opcionesEmision)
			? $opcionesEmision
			: (is_array($data['opciones_emision'] ?? null) ? $data['opciones_emision'] : []);

		return ! empty($op['omitir_movimiento_stock'])
			|| ! empty($op['emision_pos_arca'])
			|| ! empty($op['origen_estacionamiento']);
	}

	/**
	 * Depósito de stock: formulario, si no el del reparto (factura o pedido), si no default de ventas.
	 * Emisión POS: solo el depósito explícito del payload (0 = no resolver por reparto).
	 */
	private function depositoIdDesdePayload(array $data, mixed $cliente = null, mixed $pedido = null): int
	{
		$id = (int) ($data['deposito_id'] ?? $data['deposito'] ?? 0);
		if ($id > 0) {
			return $id;
		}
		if ($this->esEmisionPos($data)) {
			return 0;
		}

		$empresaId = (int) ($data['empresa_id'] ?? 0);
		if ($empresaId <= 0) {
			$pvId = (int) ($data['puntoventa_id'] ?? 0);
			if ($pvId > 0) {
				$empresaId = (int) ($this->puntoventaRepository->find($pvId)->empresa_id ?? 0);
			}
		}

		$transporteId = (int) (is_object($pedido) ? ($pedido->transporte_id ?? 0) : 0);
		if ($transporteId <= 0) {
			$transporteId = TransporteDepositoSupport::transporteIdDesdeFactura($data, $cliente);
		}

		return TransporteDepositoSupport::depositoId($transporteId, $empresaId);
	}

	/**
	 * Asiento ya completo (NC que copia el de la FAC con monto invertido):
	 * hay Debe y Haber en el mismo payload. No aplicar signo del comprobante
	 * ni agregar contrapartida de deudores.
	 */
	private function asientoContableTieneMontosCruzados(array $asientocontable): bool
	{
		$hayPositivo = false;
		$hayNegativo = false;
		foreach ($asientocontable as $imputacion) {
			$monto = (float) ($imputacion['monto'] ?? 0);
			if ($monto > 0.009) {
				$hayPositivo = true;
			} elseif ($monto < -0.009) {
				$hayNegativo = true;
			}
			if ($hayPositivo && $hayNegativo) {
				return true;
			}
		}

		return false;
	}

	private function grabaAsientoContable($asientocontable, $empresa_id, $fecha, $venta_id, $observacion, $centrocosto_id,
											$moneda_id, $cotizacion, $signo, $contrapartida_id, $tipo, $letra, $sucursal, $nro,
											?string $modoFacturacionPv = null, ?string $fechaJornada = null, bool $omitirAnita = false)
	{
		$opcionesCierre = ['modofacturacion_pv' => $modoFacturacionPv];
		if ($fechaJornada !== null && trim($fechaJornada) !== '') {
			$opcionesCierre['fechajornada'] = $fechaJornada;
		}

		PeriodoContableCierreSupport::assertOperacionPermitida(
			(int) $empresa_id,
			(string) $fecha,
			PeriodoContableCierreSupport::ALCANCE_FACTURACION,
			null,
			$opcionesCierre
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
		$data['modofacturacion_pv'] = $modoFacturacionPv;
		$data['alcance_cierre_contable'] = PeriodoContableCierreSupport::ALCANCE_FACTURACION;
		if ($fechaJornada !== null && trim($fechaJornada) !== '') {
			$data['fechajornada'] = $fechaJornada;
		}
		if ($omitirAnita) {
			// CAE pendiente: no escribir ctamov hasta autorizar (rollback MySQL no limpia Informix).
			$data['omitir_anita'] = true;
		}

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
		$asientoYaInvertido = $this->asientoContableTieneMontosCruzados($asientocontable);
		foreach ($asientocontable as $imputacion)
		{
			$monto = (float) ($imputacion['monto'] ?? 0);
			if (abs($monto) < 0.009) {
				continue;
			}

			$cuentacontable_ids[] = $imputacion['cuentacontable_id'];

			if ($asientoYaInvertido) {
				if ($monto > 0) {
					$debes[] = $monto;
					$haberes[] = '';
				} else {
					$debes[] = '';
					$haberes[] = abs($monto);
				}
			} elseif ($signo < 0) {
				$debes[] = $monto;
				$haberes[] = '';
				$totalMonto += $monto;
			} elseif ($signo > 0) {
				$debes[] = '';
				$haberes[] = $monto;
				$totalMonto -= $monto;
			}
			
			$ccImputacion = (int) ($imputacion['centrocosto_id'] ?? 0);
			$centrocosto_ids[] = $ccImputacion > 0 ? $ccImputacion : $centrocosto_id;
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

		$data['path_sistema'] = PedidoFacturaAnitaArchivosSupport::pathSistemaParaSucursal($sucursal);

		$data['modofacturacion_pv'] = $modoFacturacionPv;

		$asiento = $this->asientoRepository->create($data);

		$asiento_movimiento = null;

		// Graba los movimientos del asiento en ERP
		for ($i = 0; $i < count($data['cuentacontable_ids']); $i++)
		{
			$debeLin = $data['debes'][$i] ?? '';
			$haberLin = $data['haberes'][$i] ?? '';
			$tieneDebe = is_numeric($debeLin) && abs((float) $debeLin) > 0.009;
			$tieneHaber = is_numeric($haberLin) && abs((float) $haberLin) > 0.009;
			if (! $tieneDebe && ! $tieneHaber) {
				continue;
			}

			$asientoContable = [];
			$asientoContable['asiento_id'] = $asiento->id;
			$asientoContable['cuentacontable_id'] = $data['cuentacontable_ids'][$i];
			$asientoContable['moneda_id'] = $data['moneda_ids'][$i];
			$asientoContable['centrocosto_id'] = $data['centrocosto_ids'][$i];

			if ($tieneHaber) {
				$asientoContable['monto'] = -abs((float) $haberLin);
			}
			if ($tieneDebe) {
				$asientoContable['monto'] = (float) $debeLin;
			}

			$asientoContable['cotizacion'] = $data['cotizaciones'][$i];
			$asientoContable['observacion'] = $data['observaciones'][$i];

			$asiento_movimiento = $this->asiento_movimientoRepository->createunique($asientoContable);
		}
		return $asiento_movimiento;
	}

	/**
	 * Replica ctamov del asiento ERP (pedido / mostrador diferido).
	 *
	 * @param  array<string, mixed>  $anitaPendiente
	 */
	public function sincronizarCtamovAnitaPendientePedido(int $ventaId, array $anitaPendiente): void
	{
		$venta = is_array($anitaPendiente['venta'] ?? null) ? $anitaPendiente['venta'] : [];
		$codigo = (string) ($venta['codigo'] ?? '');
		$tipo = substr($codigo, 0, 3);
		$letra = (string) ($anitaPendiente['letra'] ?? '');
		$sucursal = (int) ($anitaPendiente['puntoventa_codigo'] ?? 0);
		$nro = (int) ($venta['numerocomprobante'] ?? 0);
		if ($tipo === '' || $nro <= 0) {
			throw new Exception('ctamov diferido: faltan tipo o número de comprobante.');
		}

		$prevDivision = $this->flGrabaComprobanteDividido;
		$path = PedidoFacturaAnitaArchivosSupport::pathSistema($anitaPendiente);
		$this->flGrabaComprobanteDividido = $path === PedidoFacturaAnitaArchivosSupport::PATH_VILLAFRANCA;

		try {
			PedidoFacturacionProfiler::etapa('defer_ctamov_inicio');
			$this->sincronizarCtamovAnitaDeVenta($ventaId, $tipo, $letra, $sucursal, $nro);
			PedidoFacturacionProfiler::etapa('defer_ctamov_fin');
		} finally {
			$this->flGrabaComprobanteDividido = $prevDivision;
		}
	}

	/**
	 * Tras CAE OK: escribe ctamov en Anita para el asiento ERP de la venta
	 * (creado con omitir_anita mientras ARCA no había autorizado).
	 */
	private function sincronizarCtamovAnitaDeVenta(
		int $ventaId,
		string $tipo,
		string $letra,
		int $sucursal,
		int $nro,
	): void {
		if ($ventaId <= 0) {
			return;
		}

		$asiento = \App\Models\Contable\Asiento::query()
			->where('venta_id', $ventaId)
			->orderByDesc('id')
			->first();

		if (! $asiento) {
			throw new Exception('No se encontró asiento ERP de la venta '.$ventaId.' para sincronizar Anita.');
		}

		$payload = $this->asientoRepository->armarPayloadAnitaDesdeModelo($asiento);
		$payload['tipo'] = $tipo;
		$payload['letra'] = $letra;
		$payload['sucursal'] = $sucursal;
		$payload['nro'] = $nro;
		$payload['path_sistema'] = PedidoFacturaAnitaArchivosSupport::pathSistemaParaSucursal($sucursal);

		$this->asientoRepository->sincronizarCtamovAnita($payload);
	}

	// Lista factura de ventas
	public function listaUnaFactura($id)
	{
		$ruta = $this->generarPdfFacturaArchivo($id);

		return response()->download($ruta);
	}

	public function generarPdfFacturaArchivo($id, string $copiaLeyenda = 'ORIGINAL', bool $facturaPdfOmitirHojaRemito = false, bool $facturaPdfSoloHojaRemito = false): string
	{
		$ctx = $this->prepararContextoPdfFactura((int) $id);

		return $this->renderPdfFacturaDesdeContexto($ctx, $copiaLeyenda, $facturaPdfOmitirHojaRemito, $facturaPdfSoloHojaRemito);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function prepararContextoPdfFactura(int $id): array
	{
		ini_set('memory_limit', '512M');

		$venta = $this->ventaRepository->find($id);
		$venta->loadMissing([
			'gastronomiaEmision',
			'venta_emisiones.articulos',
			'venta_emisiones.conceptoVenta',
			'clientes.tipodocumentos',
			'clientes.condicioniibbs',
			'condicionventas',
			'clientes.condicionventas',
			'clientes.localidades',
			'clientes.provincias',
			'clientes.paises',
			'transportes',
			'remitos.puntoventas',
			'remitos.remito_articulos',
			'puntoventaremito',
		]);

		$identificacionPdf = FacturaPdfIdentificacionSupport::desdeVenta($venta);
		$letra = $identificacionPdf['letra'];
		$codigoTipoTransaccion = $identificacionPdf['codigo_afip'];
		$nombreTipoComprobanteImpresion = $identificacionPdf['nombre'];
		$codigoTipoTransaccionPad = $identificacionPdf['codigo_afip_pad'];

		$cliente = $this->clienteQuery->traeClienteporId($venta->cliente_id);
		$tblItem = [];
		$flConDescuento = false;
		foreach ($venta->venta_emisiones as $ventaItem) {
			$descuentoLinea = $ventaItem->descuento;
			$precioSinDescuento = $ventaItem->precio;
			$precio = $ventaItem->precio;
			if ($ventaItem->descuentointegrado) {
				foreach (explode('+', $ventaItem->descuentointegrado) as $descuento) {
					$precio *= (1 - ($descuento / 100));
				}
			}
			$precioArticulo = $descuentoLinea > 0 ? $precio * (1 - ($descuentoLinea / 100)) : $precio;
			$articuloItem = $ventaItem->articulos;
			$conceptoItem = $ventaItem->conceptoVenta;
			$leyendaItem = '';
			if ($articuloItem) {
				$sku = $articuloItem->sku;
				$detalle = $articuloItem->descripcion;
				$detalleEmision = trim((string) ($ventaItem->detalle ?? ''));
				if ($detalleEmision !== '' && $detalleEmision !== trim((string) $detalle)) {
					$leyendaItem = $detalleEmision;
				}
			} else {
				$sku = $conceptoItem->codigo ?? '';
				$detalle = trim((string) ($ventaItem->detalle ?? ''));
				if ($detalle === '' && $conceptoItem) {
					$detalle = trim((string) ($conceptoItem->descripcion ?: $conceptoItem->nombre));
				}
			}
			$kiloDescuento = 0;
			$cantidad = $ventaItem->cantidad;
			if (config('app.empresa') == 'EL BIERZO' && $ventaItem->descuento != 0) {
				$cantidad = round($ventaItem->cantidad * (1. - ($ventaItem->descuento / 100.)), 1);
				$kiloDescuento = $ventaItem->cantidad - $cantidad;
				$flConDescuento = true;
			}
			$tblItem[] = [
				'sku' => $sku,
				'detalle' => $detalle,
				'leyenda' => $leyendaItem,
				'cantidad' => $cantidad,
				'kilodescuento' => $kiloDescuento,
				'caja' => $ventaItem->caja,
				'pieza' => $ventaItem->pieza,
				'precio' => $precioArticulo,
				'preciosindescuento' => $precioSinDescuento,
				'descuento' => $ventaItem->descuento,
				'descuentointegrado' => $ventaItem->descuentointegrado,
				'incluyeimpuesto' => $ventaItem->incluyeimpuesto,
				'moneda_id' => $ventaItem->moneda_id,
				'impuesto_id' => $ventaItem->impuesto_id,
				'id' => $ventaItem->id,
			];
		}

		$conceptosTotales = \App\Support\Ventas\GastronomiaVentaDisplaySupport::aplicarEtiquetaDescuentoEnConceptosTotales(
			$venta,
			$venta->venta_impuestos,
		);
		$cotizacion = $venta->moneda_id == 1 ? 1 : $venta->cotizacion;
		$tipoCodAut = $venta->puntoventas->modofacturacion === 'C' ? 'E' : 'A';
		$datos_cmp = [
			'ver' => 1,
			'fecha' => $venta->fecha,
			'cuit' => intval(str_replace('-', '', $venta->puntoventas->empresas->nroinscripcion)),
			'ptoVta' => intval($venta->puntoventas->codigo),
			'tipoCmp' => $codigoTipoTransaccion,
			'nroCmp' => $venta->numerocomprobante,
			'importe' => floatval(number_format($venta->total, 2, '.', '')),
			'moneda' => $venta->monedas->abreviatura,
			'ctz' => floatval($cotizacion),
			'tipoDocRec' => intval($venta->clientes->tipodocumentos->codigoexterno),
			'nroDocRec' => intval(str_replace('-', '', $venta->clientes->numerodocumento)),
			'tipoCodAut' => $tipoCodAut,
			'codAut' => intval($venta->cae),
		];
		if (config('app.empresa') == 'EL BIERZO') {
			$conceptosTotales = array_values(array_filter(
				$conceptosTotales,
				static function ($fila) {
					$concepto = (string) ($fila['concepto'] ?? '');
					if ($concepto === 'Subtotal' || $concepto === 'Total') {
						return true;
					}
					if (str_starts_with($concepto, 'Descuento') && (float) ($fila['importe'] ?? 0) == 0.0) {
						return false;
					}

					return true;
				}
			));
			// Logística antes del gravado, sin tasa, IVA fusionado, Gravado = mercadería + logística.
			$conceptosTotales = \App\Support\Ventas\LogisticaBierzoSupport::prepararConceptosTotalesParaImpresion(
				$conceptosTotales
			);
		}

		$qrPng = QrCode::encoding('UTF-8')->format('png')->size(500)->margin(10)->generate(
			'https://www.arca.gob.ar/fe/qr/?p='.base64_encode(json_encode($datos_cmp))
		);
		$logo = EmpresaLogoArchivo::dataUriDesdeNombre($venta->puntoventas->empresas->nombre ?? null);
		$nombreCliente = preg_replace('/[^\w\-]+/', '_', (string) optional($venta->clientes)->nombre);
		$pathDir = storage_path('pdf/ventas');
		if (! is_dir($pathDir)) {
			mkdir($pathDir, 0775, true);
		}

		$lineasTotalesLetraB = $letra === 'B'
			? FacturaBTotalesImpresionSupport::lineas($conceptosTotales)
			: [];

		return [
			'venta' => $venta,
			'conceptosTotales' => $conceptosTotales,
			'lineasTotalesLetraB' => $lineasTotalesLetraB,
			'tblItem' => $tblItem,
			'caiRemito' => CaiRemitoVigenteSupport::paraVenta($venta),
			'letra' => $letra,
			'codigoTipoTransaccion' => $codigoTipoTransaccion,
			'codigoTipoTransaccionPad' => $codigoTipoTransaccionPad,
			'nombreTipoComprobanteImpresion' => $nombreTipoComprobanteImpresion,
			'qrDataUri' => 'data:image/png;base64,'.base64_encode((string) $qrPng),
			'logoEmpresaDataUri' => $logo['uri'] ?? null,
			'pathDir' => $pathDir,
			'nombreBase' => $venta->codigo.'-'.$nombreCliente,
			'cliente' => $cliente,
		];
	}

	public function renderPdfFacturaDesdeContexto(
		array $ctx,
		string $copiaLeyenda = 'ORIGINAL',
		bool $facturaPdfOmitirHojaRemito = false,
		bool $facturaPdfSoloHojaRemito = false
	): string {
		$html = $this->htmlFacturaDesdeContexto($ctx, $copiaLeyenda, $facturaPdfOmitirHojaRemito, $facturaPdfSoloHojaRemito, false);

		return $this->guardarHtmlDompdf($html, $this->rutaArchivoFactura($ctx, $copiaLeyenda, $facturaPdfSoloHojaRemito));
	}

	/**
	 * Un solo DomPDF para todas las copias de factura/remito. Si cada trabajo cabe en la misma cantidad de páginas, las separa.
	 *
	 * @param  array<int, array{leyenda: string, omitir_remito: bool, solo_remito: bool}>  $trabajos
	 * @return array<int, string>
	 */
	public function renderPdfFacturaLote(array $ctx, array $trabajos): array
	{
		if ($trabajos === []) {
			return [];
		}

		$grupos = [];
		foreach ($trabajos as $idx => $t) {
			$clave = ((int) ! empty($t['omitir_remito'])).'-'.((int) ! empty($t['solo_remito']));
			$grupos[$clave][$idx] = $t;
		}

		$rutas = [];
		foreach ($grupos as $grupo) {
			foreach ($this->renderPdfFacturaLoteHomogeneo($ctx, $grupo) as $idx => $ruta) {
				$rutas[$idx] = $ruta;
			}
		}

		return $rutas;
	}

	/**
	 * Un solo DomPDF para copias del mismo tipo (misma cantidad de páginas).
	 *
	 * @param  array<int, array{leyenda: string, omitir_remito: bool, solo_remito: bool}>  $trabajos
	 * @return array<int, string>
	 */
	private function renderPdfFacturaLoteHomogeneo(array $ctx, array $trabajos): array
	{
		if ($trabajos === []) {
			return [];
		}
		if (count($trabajos) === 1) {
			$t = reset($trabajos);
			$idx = key($trabajos);

			return [$idx => $this->renderPdfFacturaDesdeContexto($ctx, $t['leyenda'], $t['omitir_remito'], $t['solo_remito'])];
		}

		$fragmentos = [];
		$primero = true;
		foreach ($trabajos as $t) {
			$fragmentos[] = $this->htmlFacturaDesdeContexto(
				$ctx,
				$t['leyenda'],
				$t['omitir_remito'],
				$t['solo_remito'],
				true,
				! $primero
			);
			$primero = false;
		}
		$html = view('exports.ventas.formulariofactura_envelope', ['cuerpo' => implode('', $fragmentos)])->render();
		$combinado = $ctx['pathDir'].'/lote-'.$this->nombreArchivoSeguro($ctx['nombreBase']).'-'.uniqid('', true).'.pdf';
		$this->guardarHtmlDompdf($html, $combinado);

		$paginas = $this->contarPaginasPdf($combinado);
		$cantidad = count($trabajos);
		$rutas = [];
		if ($paginas > 0 && $paginas % $cantidad === 0) {
			$porTrabajo = intdiv($paginas, $cantidad);
			$desde = 1;
			foreach ($trabajos as $idx => $t) {
				$destino = $this->rutaArchivoFactura($ctx, $t['leyenda'], $t['solo_remito']);
				$this->extraerPaginasPdf($combinado, $desde, $desde + $porTrabajo - 1, $destino);
				$rutas[$idx] = $destino;
				$desde += $porTrabajo;
			}
			@unlink($combinado);

			return $rutas;
		}

		@unlink($combinado);
		foreach ($trabajos as $idx => $t) {
			$rutas[$idx] = $this->renderPdfFacturaDesdeContexto($ctx, $t['leyenda'], $t['omitir_remito'], $t['solo_remito']);
		}

		return $rutas;
	}

	private function htmlFacturaDesdeContexto(
		array $ctx,
		string $copiaLeyenda,
		bool $omitirRemito,
		bool $soloRemito,
		bool $sinEnvelope,
		bool $saltoAntes = false
	): string {
		if ($soloRemito) {
			$omitirRemito = false;
		}

		return view('exports.ventas.formulariofactura', [
			'venta' => $ctx['venta'],
			'conceptosTotales' => $ctx['conceptosTotales'],
			'lineasTotalesLetraB' => $ctx['lineasTotalesLetraB'] ?? [],
			'tblItem' => $ctx['tblItem'],
			'caiRemito' => $ctx['caiRemito'] ?? null,
			'output_file' => '',
			'qrDataUri' => $ctx['qrDataUri'],
			'logoEmpresaDataUri' => $ctx['logoEmpresaDataUri'],
			'letra' => $ctx['letra'],
			'codigoTipoTransaccion' => $ctx['codigoTipoTransaccion'],
			'codigoTipoTransaccionPad' => $ctx['codigoTipoTransaccionPad'] ?? str_pad((string) $ctx['codigoTipoTransaccion'], 3, '0', STR_PAD_LEFT),
			'nombreTipoComprobanteImpresion' => $ctx['nombreTipoComprobanteImpresion'] ?? null,
			'copiaLeyenda' => $copiaLeyenda,
			'facturaPdfOmitirHojaRemito' => $omitirRemito,
			'facturaPdfSoloHojaRemito' => $soloRemito,
			'facturaPdfSinEnvelope' => $sinEnvelope,
			'facturaPdfSaltoAntes' => $saltoAntes,
		])->render();
	}

	private function rutaArchivoFactura(array $ctx, string $copiaLeyenda, bool $soloRemito): string
	{
		$leyendaArchivo = preg_replace('/[^\w\-]+/', '_', $copiaLeyenda) ?: 'ORIGINAL';
		$prefijo = $soloRemito ? 'REMITO-' : '';

		return $ctx['pathDir'].'/venta-'.$this->nombreArchivoSeguro($ctx['nombreBase']).'-'.$prefijo.$leyendaArchivo.'.pdf';
	}

	private function nombreArchivoSeguro(string $nombre): string
	{
		return preg_replace('/[^\w.\-]+/', '_', $nombre) ?: 'comprobante';
	}

	private function guardarHtmlDompdf(string $html, string $destino): string
	{
		$dir = dirname($destino);
		if (! is_dir($dir)) {
			mkdir($dir, 0775, true);
		}
		$pdf = App::make('dompdf.wrapper');
		$pdf->setOptions([
			'isRemoteEnabled' => false,
			'isHtml5ParserEnabled' => true,
		]);
		$pdf->setPaper('a4', 'portrait');
		$pdf->loadHTML($html)->save($destino);

		return $destino;
	}

	private function contarPaginasPdf(string $ruta): int
	{
		$fpdi = new Fpdi;
		return $fpdi->setSourceFile($ruta);
	}

	private function extraerPaginasPdf(string $origen, int $desde, int $hasta, string $destino): void
	{
		$fpdi = new Fpdi;
		$fpdi->setSourceFile($origen);
		for ($i = $desde; $i <= $hasta; $i++) {
			$template = $fpdi->importPage($i);
			$size = $fpdi->getTemplateSize($template);
			$ancho = (float) ($size['width'] ?? $size['w'] ?? 0);
			$alto = (float) ($size['height'] ?? $size['h'] ?? 0);
			$fpdi->AddPage($ancho > $alto ? 'L' : 'P', [$ancho, $alto]);
			$fpdi->useTemplate($template);
		}
		$fpdi->Output($destino, 'F');
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

		$prefsFacturacion = UsuarioPreferenciaFacturacionSupport::leer();
		$tipotransacciondefault_id = $prefsFacturacion['tipotransaccion_id'];
        $puntoventadefault_id = $prefsFacturacion['puntoventa_id'];
        $puntoventaremitodefault_id = $prefsFacturacion['puntoventaremito_id'];

        $urlOrigen = request()->headers->get('referer');
        $consultaFacturasDia = request()->query('origen') === 'gastronomia_facturas_dia';

		$layoutItemsPedido = facturaUsaLayoutItemsPedido();
		$descuentoventa_query = collect();
		$unidadmedida_query = [];
		$impuesto_query = Impuesto::soloNacionales()->orderBy('valor')->orderBy('nombre')->get();
		if ($layoutItemsPedido) {
			$descuentoventa_query = $this->descuentoventaRepository->all();
			$unidadmedida_query = Unidadmedida::all()->toArray();
			array_splice($unidadmedida_query, 1, 1);
		}

        return view('ventas.factura.editar', compact('data', 
			'mventa_query', 'modulo_query', 
			'listaprecio_query', 
			'tipotransaccion_query', 'tipotransacciondefault_id', 'puntoventa_query', 'puntoventadefault_id',
            'puntoventaremitodefault_id',
            'deposito_query', 'lote_query', 'cliente_query','vendedor_query', 'condicionventa_query',
            'transporte_query', 'formapago_query', 'incoterm_query', 'flGeneraNotaDeCredito', 'moneda_query',
			'actividad_arca_query', 'urlOrigen', 'consultaFacturasDia',
			'layoutItemsPedido', 'descuentoventa_query', 'unidadmedida_query', 'impuesto_query')); 
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

        if (method_exists($tipotransaccion_query, 'load')) {
            try {
                $tipotransaccion_query->load('conceptoVenta:id,codigo,nombre,descripcion,impuesto_id');
            } catch (\Throwable $e) {
            }
        }

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
	public function completarSolicitudCaePendiente(
		array $caePendiente,
		bool $deferVencaeAnita = false,
		?array $caeRecuperadoArca = null,
		bool $omitirVencaeAnita = false,
	): ?array
	{
		if ($caeRecuperadoArca !== null) {
			return $this->aplicarCaeRecuperadoArca($caePendiente, $caeRecuperadoArca, $deferVencaeAnita, $omitirVencaeAnita);
		}

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
			$omitirVencaeAnita,
		);

		if ($omitirVencaeAnita || ! $deferVencaeAnita) {
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
		PedidoFacturacionProfiler::etapa('anita_vencae_inicio');

		$resultado = $this->grabaVenCae(
			(string) ($vencaePendiente['tipo_anita'] ?? ''),
			(string) ($vencaePendiente['letra'] ?? ''),
			$vencaePendiente['puntoventa_codigo'] ?? 0,
			$vencaePendiente['numero_comprobante'] ?? 0,
			(string) ($vencaePendiente['cae'] ?? ''),
			(string) ($vencaePendiente['fechavencimientocae'] ?? ''),
		);

		PedidoFacturacionProfiler::etapa('anita_vencae_fin');

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
		bool $omitirVencaeAnita = false,
	) {
		// Solicita CAE o CAEA
		$flGrabaCae = false;
		switch($puntoventa->modofacturacion)
		{
			case 'C':
			case 'E':
				try {
					PedidoFacturacionProfiler::etapa('arca_solicita_cae_inicio');
					$cae = $this->facturaelectronicaService->solicitaCAE(
						$empresa->nroinscripcion,
						$codigoTipoTransaccion,
						$puntoventa,
						$dataCAE,
						$opcionesEmisionArca,
					);
					PedidoFacturacionProfiler::etapa('arca_solicita_cae_fin');
				} catch (\Throwable $e) {
					PedidoFacturacionProfiler::etapa('arca_solicita_cae_fin');
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
					PedidoFacturacionProfiler::etapa('busca_caea_local_inicio');
					$cae = $this->facturaelectronicaService->buscaCAEA($empresa->nroinscripcion, $fechaFactura);
					PedidoFacturacionProfiler::etapa('busca_caea_local_fin');

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

		if ($puntoventa->modofacturacion != 'M' && ! $deferVencaeAnita && ! $omitirVencaeAnita)
		{
			PedidoFacturacionProfiler::etapa('anita_vencae_inicio');
			// Graba cae en Anita
			$vencae = Self::grabaVenCae($tipoAnita, $letra, $puntoventa->codigo,
						$numeroComprobante, $cae['cae'],
						date('Ymd', strtotime($cae['fechavencimientocae'])));

			if ($vencae === 'Error') {
				throw new Exception('No pudo grabar CAE en Anita (vencae).');
			}
			PedidoFacturacionProfiler::etapa('anita_vencae_fin');
		}

		return 'Success';
	}

	/**
	 * Aplica CAE ya autorizado en ARCA (recuperación tras rollback ERP) sin volver a solicitar FECAESolicitar.
	 *
	 * @param  array<string, mixed>  $caePendiente
	 * @param  array{cae:string,fechavencimientocae:string}  $caeRecuperadoArca
	 * @return array<string, mixed>|null
	 */
	private function aplicarCaeRecuperadoArca(
		array $caePendiente,
		array $caeRecuperadoArca,
		bool $deferVencaeAnita,
		bool $omitirVencaeAnita = false,
	): ?array
	{
		$ventaId = (int) ($caePendiente['venta_id'] ?? 0);
		if ($ventaId <= 0) {
			throw new Exception('Recuperación CAE: venta_id inválido.');
		}

		$cae = trim((string) ($caeRecuperadoArca['cae'] ?? ''));
		$fechaVto = $this->normalizarFechaVencimientoCae((string) ($caeRecuperadoArca['fechavencimientocae'] ?? ''));
		if ($cae === '' || $fechaVto === '') {
			throw new Exception('Recuperación CAE: faltan cae o fechavencimientocae.');
		}

		$puntoventa = $caePendiente['puntoventa'] ?? null;
		$tipoAnita = (string) ($caePendiente['tipo_anita'] ?? '');
		$letra = (string) ($caePendiente['letra'] ?? 'B');
		$numeroComprobante = (int) ($caePendiente['numero_comprobante'] ?? 0);

		$this->ventaRepository->update([
			'cae' => $cae,
			'fechavencimientocae' => $fechaVto,
		], $ventaId);

		if ($puntoventa !== null && ($puntoventa->modofacturacion ?? '') !== 'M' && ! $deferVencaeAnita && ! $omitirVencaeAnita) {
			PedidoFacturacionProfiler::etapa('anita_vencae_inicio');
			$vencae = Self::grabaVenCae(
				$tipoAnita,
				$letra,
				$puntoventa->codigo,
				$numeroComprobante,
				$cae,
				date('Ymd', strtotime($fechaVto)),
			);
			PedidoFacturacionProfiler::etapa('anita_vencae_fin');

			if ($vencae === 'Error') {
				throw new Exception('No pudo grabar CAE en Anita (vencae) en recuperación ARCA.');
			}
		}

		if ($deferVencaeAnita && ! $omitirVencaeAnita) {
			return $this->armarVencaePendienteDesdeCaePendiente($caePendiente);
		}

		return null;
	}

	private function normalizarFechaVencimientoCae(string $raw): string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return '';
		}

		if (preg_match('/^\d{8}$/', $raw)) {
			$dt = \DateTime::createFromFormat('Ymd', $raw);

			return $dt ? $dt->format('Y-m-d') : '';
		}

		if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
			return substr($raw, 0, 10);
		}

		return $raw;
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
		if ($numeroRemito === 'error') {
			return [
				'error' => 'El punto de venta de remito no tiene numerador configurado en Anita.',
			];
		}

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
			{
				$tasa = (float) (Impuesto::find($item['impuesto_id'])->valor ?? 0);
				$precio = $item['preciosindescuento'] / (1 + ($tasa / 100));
			}
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
		$empresaIdRemito = (int) Empresa::query()->where('codigo', $codigoEmpresa)->value('id');
		$data['deposito_id'] = TransporteDepositoSupport::depositoId((int) ($pedido->transporte_id ?? 0), $empresaIdRemito);
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
		$data['totalseguro'] = RemitoValorAseguradoSupport::desdeNeto($totalNeto);
		$data['totalneto'] = $totalNeto;
		$data['totalcaja'] = $totalCaja;
		$data['totalkilo'] = $totalKilo;
		$data['totalpieza'] = $totalPieza;
		$data['subzona'] = $cliente->subzona_id;
		$data['oblea'] = '';
		$data['cantidadmodificada'] = $totalKilo;
		$data['usuarioalta'] = $this->nombreUsuarioAnita();
		$data['omitir_stkmov_anita'] = true;
		$data['omitir_validacion_saldo'] = true;

		$this->movimientoStockService->guardaMovimientoStock($data, 'create');

		// Este flujo (reparto 101) crea el REM físico antes de la factura dividida.
		// La factura de Villa omite la numeración del remito, por lo que se reserva acá.
		if (EntornoEmpresaSupport::esElBierzo()) {
			$resultadoNumeradorRemito = $this->ventaRepository->numeraAnita(
				config('facturacion.TIPO_REMITO'),
				config('facturacion.LETRA_REMITO'),
				$puntoVentaRemito,
			);
			if (! is_int($resultadoNumeradorRemito) || $resultadoNumeradorRemito !== (int) $numeroRemito) {
				return [
					'error' => 'No pudo actualizar el numerador del remito en Anita.',
				];
			}
		}

		// Remito ERP solo (flujo pedido Bierzo / reparto 101). No gastronomía ni estacionamiento.
		$persistRemito = app(\App\Services\Ventas\RemitoService::class)->persistirDesdeFactura([
			'venta' => (object) [
				'id' => null,
				'fecha' => $data['fechafactura'] ?? date('Y-m-d'),
				'cliente_id' => $cliente->id,
				'condicionventa_id' => $pedido->condicionventa_id,
				'vendedor_id' => $pedido->vendedor_id,
				'transporte_id' => $pedido->transporte_id,
				'cliente_entrega_id' => $pedido->cliente_entrega_id,
				'lugarentrega' => $pedido->lugarentrega,
				'leyenda' => $pedido->leyenda,
				'descuento' => $pedido->descuento,
				'moneda_id' => $monedas_id[0] ?? 1,
			],
			'pedido' => $pedido,
			'puntoventa_id' => $this->puntoventaremito_id,
			'numero' => $numeroRemito,
			'items' => $dataFactura,
			'origen' => 'pedido',
			'estadoremito' => 'Pendiente',
			'estado' => 'P',
			'venta_id' => null,
			'pedido_id' => $pedido->id,
			'sin_transaction' => true,
		]);
		if (! empty($persistRemito['error'])) {
			return ['error' => 'Error grabando remito ERP: '.$persistRemito['error']];
		}

		return [
			'factura' => $data['codigo'],
			'remito_id' => $persistRemito['id'] ?? null,
			'pedido_id' => (int) ($pedido->id ?? 0),
		];
	}

	/**
	 * Facturación desde remito Bierzo (cantidad = remito_articulo.kilo).
	 * Aislado de gastronomía y estacionamiento.
	 * Facturable: sin venta_id y estadoremito no Facturado/Suspendido/Anulado.
	 */
	public function calculaFacturaPorRemito(array $data)
	{
		$cliente_id = $data['cliente_id'];
		$fechaFactura = $data['fechafactura'];
		$this->descuentoPie = $data['descuentopie'] ?? 0;
		$this->descuentoLinea = $data['descuentolinea'] ?? 0;
		$this->descuentoImportePie = $data['descuentoimportepie'] ?? 0;
		$this->numeroDespacho = '';

		$cliente = $this->clienteQuery->traeClienteporId($cliente_id);
		if (! $cliente) {
			return ['error' => 'Cliente inexistente'];
		}
		if ($errorDespacho = $this->errorClienteDespachoNoFacturable($data, $cliente_id)) {
			return $errorDespacho;
		}
		if ($cliente->numerodocumento == null) {
			return ['error' => 'No tiene Documento'];
		}

		$remito = app(\App\Services\Ventas\RemitoService::class)->leeRemito($data['remito_id'] ?? 0);
		if (! $remito) {
			return ['error' => 'Remito inexistente'];
		}

		$motivo = \App\Support\Ventas\RemitoEstadosSupport::motivoNoFacturable($remito);
		if ($motivo !== null) {
			return ['error' => $motivo];
		}

		if (VillafrancaFacturacionSupport::esReparto101($remito)) {
			$this->flGrabaComprobanteDividido = true;
			if ((float) $this->coeficienteExtraCliente <= 0) {
				$this->coeficienteExtraCliente = VillafrancaFacturacionSupport::coeficienteReparto101();
			}
		}

		$remitoArticuloRepo = app(\App\Repositories\Ventas\Remito_ArticuloRepositoryInterface::class);
		$remito_articulo_ids = $data['remito_articulo_ids'] ?? [];
		if ($remito_articulo_ids === []) {
			$remito_articulo_ids = $remitoArticuloRepo->findPorRemitoId($remito->id)->pluck('id')->all();
		}

		$dataFactura = [];
		$totKilo = 0;

		foreach ($remito_articulo_ids as $remito_articulo_id) {
			$linea = $remitoArticuloRepo->find($remito_articulo_id);
			if (! $linea || (int) $linea->remito_id !== (int) $remito->id) {
				continue;
			}
			if (! \App\Support\Ventas\RemitoEstadosSupport::lineaPendienteDeFacturar($linea)) {
				continue;
			}

			$articulo = $this->articuloQuery->traeArticuloPorId($linea->articulo_id);
			if (! $articulo) {
				return ['error' => 'Artículo inexistente en remito'];
			}
			if ((float) $linea->kilo == 0.0) {
				return ['error' => 'Artículo '.$articulo->sku.' sin kilos'];
			}

			$this->descuentoLinea = 0.;
			if ($linea->descuentoventa_id > 0) {
				$descuentoventa = $this->descuentoventaRepository->find($linea->descuentoventa_id);
				if ($descuentoventa) {
					$this->descuentoLinea = $descuentoventa->porcentajedescuento;
				}
			}

			$precioUnitario = $linea->precio;
			if (VillafrancaFacturacionSupport::esReparto101($remito)
				&& (float) $this->coeficienteExtraCliente > 0) {
				$precioUnitario = $linea->precio * $this->coeficienteExtraCliente;
			}
			$kilo = (float) $linea->kilo;
			$pieza = (float) $linea->pieza;
			$caja = (float) $linea->caja;

			if ($this->descuentoLinea != 0) {
				$precioConDescuento = round($precioUnitario * (1. - ($this->descuentoLinea / 100.)), 2);
			} else {
				$precioConDescuento = $precioUnitario;
			}

			$kiloDescuento = $kilo;
			if (config('app.empresa') == 'EL BIERZO' && $this->descuentoLinea != 0) {
				$kiloDescuento = round($kilo * (1. - ($this->descuentoLinea / 100.)), 1);
			}

			$codigoCategoria = '';
			$categoria = Categoria::find($articulo->categoria_id);
			if ($categoria) {
				$codigoCategoria = $categoria->codigo;
			}

			$dataFactura[] = [
				'cantidad' => $kilo,
				'kilodescuento' => $kiloDescuento,
				'pieza' => $pieza,
				'caja' => $caja,
				'preciosindescuento' => $precioUnitario,
				'precio' => $precioConDescuento,
				'descuento' => $this->descuentoLinea,
				'descuentointegrado' => '',
				'descuentofinal' => $this->descuentoPie,
				'descuentointegradofinal' => '',
				'incluyeimpuesto' => $linea->incluyeimpuesto,
				'impuesto_id' => $articulo->impuesto_id,
				'articulo_id' => $articulo->id,
				'sku' => $articulo->sku,
				'descripcion' => $articulo->descripcion,
				'codigounidadmedida' => $articulo->unidadesdemedidas->codigo ?? 1,
				'categoria' => $codigoCategoria,
				'moneda_id' => $linea->moneda_id,
				'listaprecio_id' => $linea->listaprecio_id,
				'despacho' => $this->numeroDespacho,
				'loteimportacion_id' => null,
				'pedido_articulo_id' => $linea->pedido_articulo_id,
				'remito_articulo_id' => $linea->id,
				'cuentacontable_id' => $articulo->cuentacontableventa_id,
			];
			$totKilo += $kilo;
		}

		$provinciaPercepcion = $this->provinciaPercepcionDesdePedido($cliente, $remito);
		$datosCliente = [
			'condicioniva_id' => $cliente->condicioniva_id,
			'numerodocumento' => $cliente->numerodocumento,
			'retieneiva' => $cliente->retieneiva,
			'condicioniibb_id' => $cliente->condicioniibb_id,
			'provincia' => $provinciaPercepcion,
			'descuentoimportepie' => $this->descuentoImportePie,
			'id' => $cliente->id,
			'abasto_id' => $cliente->abasto_id,
			'porcentajelogistica' => $cliente->porcentajelogistica,
			'empresa_id' => $data['empresa_id'] ?? $remito->empresa_id ?? null,
		];

		$conceptosTotales = $this->impuestoService->calculaImpuestoVenta(
			$dataFactura,
			$datosCliente,
			$fechaFactura,
			(bool) $this->flGrabaComprobanteDividido
		);
		$totalComprobante = $this->impuestoService->buscaValor($conceptosTotales, 'concepto', 'Total', 'importe');

		if ($dataFactura === []) {
			return ['error' => 'No hay ítems pendientes para facturar del remito.'];
		}
		if ($totalComprobante == 0.) {
			return ['error' => 'El total del comprobante es 0. Revise precios del remito.'];
		}

		$letraRemito = (string) (optional($cliente->condicionivas)->letra ?? 'A');

		return $this->conSugerenciaTipoPreview(
			[
				'datosfactura' => $dataFactura,
				'datoscliente' => $datosCliente,
				'totalcomprobante' => $totalComprobante,
				'conceptostotales' => $conceptosTotales,
			],
			$cliente,
			(float) $totalComprobante,
			$letraRemito
		);
	}

	/**
	 * Emite factura desde cualquier remito Bierzo sin factura asociada
	 * (manual, F5, desde pedido). Usa el número de remito ya grabado.
	 * No gastronomía / estacionamiento.
	 */
	public function generaFacturaPorRemito(array $data)
	{
		$profiler = PedidoFacturacionProfiler::iniciarSiConfigurado([
			'remito_id' => (int) ($data['remito_id'] ?? 0),
			'pedido_id' => (int) ($data['pedido_id'] ?? 0),
			'puntoventa_id' => (int) ($data['puntoventa_id'] ?? 0),
			'puntoventaremito_id' => (int) ($data['puntoventaremito_id'] ?? 0),
			'tipotransaccion_id' => (int) ($data['tipotransaccion_id'] ?? 0),
			'cliente_id' => (int) ($data['cliente_id'] ?? 0),
		]);

		try {
			return $this->generaFacturaPorRemitoInterno($data);
		} finally {
			PedidoFacturacionProfiler::finalizar($profiler);
		}
	}

	/**
	 * @param  array<string, mixed>  $data
	 * @return array<int|string, mixed>
	 */
	private function generaFacturaPorRemitoInterno(array $data)
	{
		UsuarioPreferenciaFacturacionSupport::guardar($data);

		$remito = app(\App\Services\Ventas\RemitoService::class)->leeRemito($data['remito_id'] ?? 0);
		if (! $remito) {
			return ['error' => 'Remito inexistente'];
		}

		$motivo = \App\Support\Ventas\RemitoEstadosSupport::motivoNoFacturable($remito);
		if ($motivo !== null) {
			return ['error' => $motivo];
		}

		$cliente_id = $data['cliente_id'] ?? $remito->cliente_id;
		$cliente = $this->clienteQuery->traeClienteporId($cliente_id);
		if (! $cliente) {
			return ['error' => 'Cliente inexistente'];
		}
		if ($errorDespacho = $this->errorClienteDespachoNoFacturable($data, $cliente_id)) {
			return $errorDespacho;
		}

		$errorEntrega = $this->resolverLugarEntregaPedido($cliente, $remito, $data, true);
		if ($errorEntrega) {
			return $errorEntrega;
		}

		$this->coeficienteCliente = 0;
		$this->coeficienteExtraCliente = 0;
		$this->flCalculaDesdeGeneracionFactura = true;
		$this->flDivide = false;
		$this->flGrabaComprobanteDividido = false;
		$this->usaNumeradorVillafrancaPropio = false;
		$this->numeroReservadoVillafrancaReparto101 = 0;
		$this->facturandoDesdeRemitoId = (int) $remito->id;
		$this->numeroremitoFijoDesdeRemito = (int) $remito->numero;

		if (VillafrancaFacturacionSupport::esReparto101($remito)) {
			$pv101 = VillafrancaFacturacionSupport::idPuntoVentaReparto101();
			if ($pv101 <= 0) {
				return [
					'error' => 'Error punto de venta Villafranca',
					'mensaje' => 'No está configurado el punto de venta Villafranca sucursal 1 para el reparto 101.',
				];
			}
			$this->usaNumeradorVillafrancaPropio = true;
			$this->flGrabaComprobanteDividido = true;
			$this->flDivide = true;
			$this->coeficienteCliente = 100.;
			$this->coeficienteExtraCliente = VillafrancaFacturacionSupport::coeficienteReparto101();
			$this->puntoVentaDivision_id = $pv101;
			$data['puntoventa_id'] = $pv101;
		}

		$data['cliente_id'] = $cliente_id;
		$data['remito_id'] = $remito->id;
		$data['pedido_id'] = $remito->pedido_id ?: 0;
		// Remito por lo real / importado de Anita: no hay pesada de pedido.
		$data['pedido_articulo_ids'] = is_array($data['pedido_articulo_ids'] ?? null)
			? $data['pedido_articulo_ids']
			: [];
		if (empty($data['puntoventaremito_id']) && $remito->puntoventa_id) {
			$data['puntoventaremito_id'] = $remito->puntoventa_id;
		}
		$this->puntoventaremito_id = (int) ($data['puntoventaremito_id'] ?? $remito->puntoventa_id ?? 0);

		$emitir = function () use ($data, $cliente, $remito) {
			try {
				// $remito actúa como cabecera (mismos campos que pedido: cond/vendedor/transporte/entrega)
				$retorno = PedidoFacturaAnitaDeferSupport::tomarYProgramar(
					$this->generaUnaFacturaPorPedido($data, $cliente, $remito),
					'remito',
				);
			} finally {
				$this->facturandoDesdeRemitoId = null;
				$this->numeroremitoFijoDesdeRemito = null;
			}

			return [$retorno];
		};

		$pedidoId = (int) ($remito->pedido_id ?: 0);
		if ($pedidoId > 0) {
			return $this->anexarUrlImpresionSesion(
				PedidoFacturacionExclusivaSupport::ejecutar($pedidoId, $emitir)
			);
		}

		return $this->anexarUrlImpresionSesion($emitir());
	}

	/**
	 * Bultos a Anita (El Bierzo: comprob.comp_cond_vta).
	 * En réplica diferida $this->cantidadBulto está vacío: manda venta.cantidadbulto.
	 *
	 * @param  array<string, mixed>  $venta
	 */
	private function cantidadBultoParaAnita(array $venta): int
	{
		if (array_key_exists('cantidadbulto', $venta) && $venta['cantidadbulto'] !== null && $venta['cantidadbulto'] !== '') {
			return $this->normalizarCantidadBulto($venta['cantidadbulto']);
		}

		return $this->normalizarCantidadBulto($this->cantidadBulto ?? 0);
	}

	private function normalizarCantidadBulto(mixed $valor): int
	{
		if ($valor === null || $valor === '') {
			return 0;
		}

		return max(0, (int) $valor);
	}
}


