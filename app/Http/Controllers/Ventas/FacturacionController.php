<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Support\Ventas\UsuarioPreferenciaFacturacionSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use App\Services\Ventas\FacturacionService;
use App\Services\Ventas\FacturacionServiceFerli;
use App\Support\Stock\MovimientoStockFerliSupport;
use App\Queries\Ventas\ClienteQueryInterface;
use App\Repositories\Ventas\TipotransaccionRepository;
use App\Repositories\Ventas\TransporteRepositoryInterface;
use App\Repositories\Ventas\PuntoventaRepository;
use App\Repositories\Stock\LoteRepositoryInterface;
use App\Repositories\Ventas\PuntoventaRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Repositories\Ventas\IncotermRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Configuracion\Actividad_ArcaRepositoryInterface;
use App\Repositories\Ventas\DescuentoventaRepositoryInterface;
use App\Models\Stock\Mventa;
use App\Models\Stock\Unidadmedida;
use App\Models\Configuracion\Impuesto;
use App\Models\Stock\Depmae;
use App\Models\Stock\Modulo;
use App\Models\Stock\Listaprecio;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Condicionventa;
use App\Exports\Ventas\FacturaExport;
use App\Models\Ventas\Venta;
use App\Services\Ventas\ComprobanteImpresionSesionService;
use App\Support\Ventas\ArcaApocClienteOperacionValidacionSupport;
use App\Support\Ventas\FacturaListadoFiltros;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;

class FacturacionController extends Controller
{
	private $facturacionService;
    private $tipotransaccionRepository;
    private $puntoventaRepository;
    private $loteRepository;
    private $clienteQuery;
    private $incotermRepository;
	private $formpagoRepository;
    private $transporteRepository;
    private $monedaRepository;
    private $actividad_arcaRepository;
    private $descuentoventaRepository;
    private $empresaRepository;

    public function __construct(FacturacionService $facturacionservice,
                                LoteRepositoryInterface $loterepository,
                                ClienteQueryInterface $clientequery,
                                TipotransaccionRepository $tipotransaccionRepository,
                                PuntoventaRepository $puntoventaRepository,
                                IncotermRepositoryInterface $incotermrepository,
								FormapagoRepositoryInterface $formpagorepository,
                                TransporteRepositoryInterface $transporterepository,
                                MonedaRepositoryInterface $monedarepository,
                                Actividad_ArcaRepositoryInterface $actividad_arcarepository,
                                DescuentoventaRepositoryInterface $descuentoventarepository,
                                EmpresaRepositoryInterface $empresarepository)
    {
        $this->middleware('auth');

        $this->facturacionService = $facturacionservice;
        $this->tipotransaccionRepository = $tipotransaccionRepository;
        $this->puntoventaRepository = $puntoventaRepository;
        $this->loteRepository = $loterepository;
        $this->clienteQuery = $clientequery;
        $this->incotermRepository = $incotermrepository;
		$this->formapagoRepository = $formpagorepository;
        $this->transporteRepository = $transporterepository;
        $this->monedaRepository = $monedarepository;
        $this->actividad_arcaRepository = $actividad_arcarepository;
        $this->descuentoventaRepository = $descuentoventarepository;
        $this->empresaRepository = $empresarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-factura');

        $filtros = $this->filtrosListado($request);

		$ventas = $this->facturacionService->leePaginando($filtros);
        $totalesPorReparto = FacturaListadoFiltros::esOrdenReparto($filtros)
            ? $this->facturacionService->totalesIndexPorReparto($filtros)
            : collect();

        $datas = [
            'ventas' => $ventas,
            'totalesPorReparto' => $totalesPorReparto,
            'busqueda' => $filtros['busqueda'],
            'filtros' => $filtros,
            'filtrosQuery' => FacturaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => FacturaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ];

        return view('ventas.factura.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-factura'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->filtrosListado($request, $busqueda);

		$ventas = $this->facturacionService->leeSinPaginar($filtros);
        $totalesPorReparto = FacturaListadoFiltros::esOrdenReparto($filtros)
            ? $this->facturacionService->totalesIndexPorReparto($filtros)
            : collect();

        switch($formato)
        {
        case 'PDF':
            $view =  \View::make('ventas.factura.listado', [
                        'ventas' => $ventas,
                        'filtros' => $filtros,
                        'totalesPorReparto' => $totalesPorReparto,
                    ])->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_factura';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new FacturaExport($this->facturacionService))
                        ->parametros($filtros)
                        ->download('factura.xlsx');
            break;

        case 'CSV':
            return (new FacturaExport($this->facturacionService))
                        ->parametros($filtros, true)
                        ->download('factura.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        return redirect()->route('factura', FacturaListadoFiltros::paraQueryString($filtros));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-factura');

        $this->armarTablasVista($deposito_query, $cliente_query,
                                $condicionventa_query, $vendedor_query, $transporte_query,
                                $formapago_query, $incoterm_query,
                                $mventa_query, $modulo_query, 
                                $listaprecio_query, 
                                $tipotransaccion_query, $puntoventa_query, $lote_query, $moneda_query,
                                $actividad_arca_query);

        $prefsFacturacion = UsuarioPreferenciaFacturacionSupport::leer();
        $tipotransacciondefault_id = $prefsFacturacion['tipotransaccion_id'];
        $puntoventadefault_id = $prefsFacturacion['puntoventa_id'];
        $puntoventaremitodefault_id = $prefsFacturacion['puntoventaremito_id'];

        $data = new \stdClass();
        $layoutItemsPedido = facturaUsaLayoutItemsPedido();
        $descuentoventa_query = collect();
        $unidadmedida_query = [];
        $impuesto_query = Impuesto::soloNacionales()->orderBy('valor')->orderBy('nombre')->get();

        if ($layoutItemsPedido) {
            $descuentoventa_query = $this->descuentoventaRepository->all();
            $unidadmedida_query = Unidadmedida::all()->toArray();
            array_splice($unidadmedida_query, 1, 1);
        }

        return view('ventas.factura.crear', compact(
            'data',
            'mventa_query', 'modulo_query', 'listaprecio_query',
            'tipotransaccion_query', 'tipotransacciondefault_id', 'puntoventa_query', 'puntoventadefault_id',
            'puntoventaremitodefault_id',
            'deposito_query', 'lote_query', 'cliente_query', 'vendedor_query', 'condicionventa_query',
            'transporte_query', 'formapago_query', 'incoterm_query', 'moneda_query', 'actividad_arca_query',
            'layoutItemsPedido', 'descuentoventa_query', 'unidadmedida_query', 'impuesto_query'));
    }

    public function preferencias(Request $request): JsonResponse
    {
        if (
            ! can('crear-factura', false)
            && ! can('editar-pedidos', false)
            && ! can('crear-remitos', false)
            && ! can('editar-remitos', false)
        ) {
            can('crear-factura');
        }

        UsuarioPreferenciaFacturacionSupport::guardar($request->only([
            'tipotransaccion_id',
            'puntoventa_id',
            'puntoventaremito_id',
        ]));

        return response()->json(['ok' => true]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(Request $request)
    {
		try
		{
            if ($bloqueo = $this->bloqueoClienteApocOperacion($request)) {
                return $bloqueo;
            }

            if (config('app.empresa') == 'CALZADOS FERLI')
                $data = $this->facturacionService->generaFacturaPorItemOt($request->all());
            else
                $data = $this->facturacionService->generaComprobanteGeneral($request->all());

			if (is_array($data) && ! empty($data['error'])) {
				return $this->responderComprobanteMostrador($request, false, $data['error']);
			}

			if (is_array($data) && ! empty($data['factura'])) {
				$mensaje = 'Comprobante '.$data['factura'].' generado con éxito';
				if (! empty($data['aviso_caea'])) {
					$mensaje .= ' '.$data['aviso_caea'];
				}

				return $this->responderComprobanteMostrador(
					$request,
					true,
					null,
					(string) $data['factura'],
					isset($data['venta_id']) ? (int) $data['venta_id'] : null,
					isset($data['aviso_caea']) ? (string) $data['aviso_caea'] : null,
					url('ventas/factura'),
					$mensaje,
				);
			}

			return $this->responderComprobanteMostrador($request, false, 'No se pudo generar el comprobante');
		} catch (\Exception $e)
		{
			return $this->responderComprobanteMostrador($request, false, $e->getMessage());
		}
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-factura');

        return $this->facturacionService->editaUnaFactura($id);
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(Request $request, $id)
    {
        can('actualizar-factura');

        return redirect('venta/factura')->with('mensaje', 'Factura actualizada con éxito');       
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        
    }

    public function facturarItemOt(Request $request)
    {
        if (MovimientoStockFerliSupport::esCalzadosFerli()) {
            return app(FacturacionServiceFerli::class)->generaFacturaPorItemOt($request->all());
        }

        return $this->facturacionService->generaFacturaPorItemOt($request->all());
    }

    /*
	 * Arma tablas de select para enviar a vista
	 */
	private function armarTablasVista(&$deposito_query, &$cliente_query,
                &$condicionventa_query, &$vendedor_query, &$transporte_query,
                &$formapago_query, &$incoterm_query,
                &$mventa_query, &$modulo_query, &$listaprecio_query, 
                &$tipotransaccion_query, &$puntoventa_query, &$lote_query, &$moneda_query,
                &$actividad_arca_query)
    {
        $mventa_query = Mventa::all();
        $tipotransaccion_query = $this->tipotransaccionRepository->all(['V', 'C'], ['A']);
        if (method_exists($tipotransaccion_query, 'load')) {
            try {
                $tipotransaccion_query->load('conceptoVenta:id,codigo,nombre,descripcion,impuesto_id');
            } catch (\Throwable $e) {
                // Columna/tabla concepto_venta aún no migrada.
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

    public function calculaFacturaPorPedido(Request $request)
    {
        if (MovimientoStockFerliSupport::esCalzadosFerli()) {
            return response()->json(['error' => 'La facturación Ferli es por OT y combinación.'], 422);
        }

        return $this->facturacionService->calculaFacturaPorPedido($request->all());
    }

    public function facturarPorPedido(Request $request)
    {
        if (MovimientoStockFerliSupport::esCalzadosFerli()) {
            return response()->json(['error' => 'La facturación Ferli es por OT y combinación.'], 422);
        }

        return $this->facturacionService->generaFacturaPorPedido($request->all());
    }

    public function calculaFacturaPorOrdenventa(Request $request)
    {
        return $this->facturacionService->calculaFacturaPorOrdenventa($request->all());
    }    

    public function facturarPorOrdenventa(Request $request)
    {
        return $this->facturacionService->generaFacturaPorOrdenventa($request->all());
    }

    public function calculaFacturaPorRemito(Request $request)
    {
        return $this->facturacionService->calculaFacturaPorRemito($request->all());
    }

    public function facturarPorRemito(Request $request)
    {
        return $this->facturacionService->generaFacturaPorRemito($request->all());
    }

    // Lista una factura de ventas (sesión con envío a impresora)
    public function listaUnaFactura($id)
    {
        return $this->irASesionImpresionFactura($id, 'impresora');
    }

    public function listaUnaFacturaPdf($id)
    {
        return $this->irASesionImpresionFactura($id, 'pdf');
    }

    public function listaUnaFacturaCopias($id)
    {
        return $this->irASesionImpresionFactura($id, 'copias');
    }

    private function irASesionImpresionFactura($id, string $destino)
    {
        $venta = Venta::query()->with(['gastronomiaEmision', 'estacionamientoEmision'])->find($id);
        if ($venta && ($venta->gastronomiaEmision || $venta->estacionamientoEmision)) {
            return $this->facturacionService->listaUnaFactura($id);
        }

        $params = ['id' => $id];
        if ($destino === 'impresora') {
            $params['auto'] = 1;
            $params['enviar_impresora'] = 1;
        } elseif ($destino === 'pdf') {
            $params['pdf'] = 1;
            $params['enviar_impresora'] = 0;
        } else {
            $params['elegir'] = 1;
            $params['enviar_impresora'] = 1;
        }
        $retorno = (string) request()->query('retorno', '');
        if ($retorno !== '') {
            $params['retorno'] = $retorno;
        }

        return redirect()->route('sesion_impresion_factura', $params);
    }

    public function generaNotaDeCredito($id)
    {
        can('generar-nota-de-credito');

        return $this->facturacionService->editaUnaFactura($id, true);
    }

    public function calculaFacturaGeneral(Request $request)
    {
        return $this->facturacionService->calculaFacturaGeneral($request->all());
    }    

    // Graba el comprobante
    public function grabaComprobante(Request $request)
    {
        if ($bloqueo = $this->bloqueoClienteApocOperacion($request)) {
            return $bloqueo;
        }

        try {
            $comprobante = $this->facturacionService->generaComprobanteGeneral($request->all());
        } catch (\Throwable $e) {
            Log::error('ventas.factura.grabacomprobante.excepcion', [
                'error' => $e->getMessage(),
                'venta_id' => $request->input('venta_id'),
                'tipotransaccion_id' => $request->input('tipotransaccion_id'),
                'puntoventa_id' => $request->input('puntoventa_id'),
            ]);

            return $this->responderComprobanteMostrador(
                $request,
                false,
                $e->getMessage() !== '' ? $e->getMessage() : 'No se pudo generar el comprobante.',
            );
        }

        if (! empty($comprobante['error'])) {
            $detalle = trim((string) ($comprobante['error'] ?? ''));
            $mensaje = trim((string) ($comprobante['mensaje'] ?? ''));
            if ($mensaje !== '' && ! str_contains($detalle, $mensaje)) {
                $detalle = trim($detalle.': '.$mensaje);
            }
            Log::warning('ventas.factura.grabacomprobante.error', [
                'error' => $comprobante['error'] ?? null,
                'mensaje' => $comprobante['mensaje'] ?? null,
                'venta_id' => $request->input('venta_id'),
                'tipotransaccion_id' => $request->input('tipotransaccion_id'),
                'puntoventa_id' => $request->input('puntoventa_id'),
            ]);

            return $this->responderComprobanteMostrador(
                $request,
                false,
                $detalle !== '' ? $detalle : 'No se pudo generar el comprobante.',
            );
        }

        try {
            app(ComprobanteImpresionSesionService::class)
                ->dispararAlGrabarVenta((int) ($comprobante['venta_id'] ?? 0));
        } catch (\Throwable $e) {
            Log::warning('ventas.factura.impresion_al_grabar', [
                'venta_id' => $comprobante['venta_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        $mensaje = 'Comprobante '.$comprobante['factura'].' generado con éxito';
        if (! empty($comprobante['aviso_caea'])) {
            $mensaje .= ' '.$comprobante['aviso_caea'];
        }

        return $this->responderComprobanteMostrador(
            $request,
            true,
            null,
            (string) ($comprobante['factura'] ?? ''),
            isset($comprobante['venta_id']) ? (int) $comprobante['venta_id'] : null,
            isset($comprobante['aviso_caea']) ? (string) $comprobante['aviso_caea'] : null,
            url('ventas/factura'),
            $mensaje,
        );
    }

    /**
     * Red de seguridad server-side: bloquea facturación admin si el cliente está en APOC.
     * No afecta gastronomía ni estacionamiento (no pasan por este controller).
     */
    private function bloqueoClienteApocOperacion(Request $request)
    {
        $clienteId = (int) $request->input('cliente_id');
        if ($clienteId <= 0) {
            return null;
        }

        $cliente = $this->clienteQuery->traeClienteporId($clienteId);
        if (! $cliente) {
            return null;
        }

        $bloqueo = ArcaApocClienteOperacionValidacionSupport::bloqueoOperacion($cliente);
        if ($bloqueo === null) {
            return null;
        }

        return $this->responderComprobanteMostrador($request, false, (string) $bloqueo['error']);
    }

    private function requestQuiereJsonOverlay(Request $request): bool
    {
        return $request->ajax()
            || $request->wantsJson()
            || $request->boolean('ajax_overlay');
    }

    /**
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    private function responderComprobanteMostrador(
        Request $request,
        bool $ok,
        ?string $error = null,
        ?string $factura = null,
        ?int $ventaId = null,
        ?string $avisoCaea = null,
        ?string $redirect = null,
        ?string $mensajeFlash = null,
    ) {
        if ($this->requestQuiereJsonOverlay($request)) {
            if ($ok) {
                return response()->json([[
                    'factura' => $factura,
                    'venta_id' => $ventaId,
                    'aviso_caea' => $avisoCaea,
                    'redirect' => $redirect,
                ]]);
            }

            return response()->json([[
                'error' => $error !== null && $error !== '' ? $error : 'No se pudo generar el comprobante.',
            ]], 422);
        }

        if ($ok) {
            $mensaje = $mensajeFlash
                ?: ('Comprobante '.$factura.' generado con éxito');

            if ($redirect) {
                return redirect($redirect)->with('mensaje', $mensaje);
            }

            return redirect()->back()->with('mensaje', $mensaje);
        }

        return back()->withInput()->with('errores', [
            $error !== null && $error !== '' ? $error : 'No se pudo generar el comprobante.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        return FacturaListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            $this->empresaDefaultListado()
        );
    }

    /**
     * Empresa 1 si el usuario la tiene asignada; si no, la primera asignada.
     */
    private function empresaDefaultListado(): ?int
    {
        $empresas = $this->empresaRepository->allFiltrado();
        $preferida = FacturaListadoFiltros::EMPRESA_ID_DEFAULT;
        if ($empresas->contains(static fn ($emp) => (int) $emp->id === $preferida)) {
            return $preferida;
        }
        $primera = $empresas->first();

        return $primera ? (int) $primera->id : null;
    }

}

