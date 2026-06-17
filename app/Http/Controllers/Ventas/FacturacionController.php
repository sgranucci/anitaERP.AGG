<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Services\Ventas\FacturacionService;
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
use App\Models\Stock\Depmae;
use App\Models\Stock\Modulo;
use App\Models\Stock\Listaprecio;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Condicionventa;
use App\Exports\Ventas\FacturaExport;

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
                                DescuentoventaRepositoryInterface $descuentoventarepository)
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
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-factura');

        $busqueda = $request->busqueda;
        
		$ventas = $this->facturacionService->leePaginando($busqueda);

        $datas = ['ventas' => $ventas, 'busqueda' => $busqueda];

        return view('ventas.factura.index', $datas);
    }

    public function listar($formato = null, $busqueda = null)
    {
        can('listar-factura'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

		$ventas = $this->facturacionService->leeSinPaginar($busqueda);

        switch($formato)
        {
        case 'PDF':
            $view =  \View::make('ventas.factura.listado', compact('ventas'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_factura';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','portrait');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new FacturaExport($this->facturacionService))
                        ->parametros($busqueda)
                        ->download('factura.xlsx');
            break;

        case 'CSV':
            return (new FacturaExport($this->facturacionService))
                        ->parametros($busqueda)
                        ->download('factura.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['ventas' => $ventas, 'busqueda' => $busqueda];

        return view('ventas.factura.index', $datas);       
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

        $tipotransacciondefault_id = cache()->get(generaKey('tipotransaccion'));
        $puntoventadefault_id = cache()->get(generaKey('puntoventa'));

        if (! $puntoventadefault_id && config('facturacion.PUNTOVENTA_FACTURACION')) {
            $puntoventadefault_id = config('facturacion.PUNTOVENTA_FACTURACION');
        }

        $data = new \stdClass();
        $layoutItemsPedido = facturaUsaLayoutItemsPedido();
        $descuentoventa_query = collect();
        $unidadmedida_query = [];

        if ($layoutItemsPedido) {
            $descuentoventa_query = $this->descuentoventaRepository->all();
            $unidadmedida_query = Unidadmedida::all()->toArray();
            array_splice($unidadmedida_query, 1, 1);
        }

        return view('ventas.factura.crear', compact(
            'data',
            'mventa_query', 'modulo_query', 'listaprecio_query',
            'tipotransaccion_query', 'tipotransacciondefault_id', 'puntoventa_query', 'puntoventadefault_id',
            'deposito_query', 'lote_query', 'cliente_query', 'vendedor_query', 'condicionventa_query',
            'transporte_query', 'formapago_query', 'incoterm_query', 'moneda_query', 'actividad_arca_query',
            'layoutItemsPedido', 'descuentoventa_query', 'unidadmedida_query'));
    }

    public function preferencias(Request $request): JsonResponse
    {
        can('crear-factura');

        if ($request->filled('tipotransaccion_id')) {
            Cache::forever(generaKey('tipotransaccion'), (int) $request->input('tipotransaccion_id'));
        }

        if ($request->filled('puntoventa_id')) {
            Cache::forever(generaKey('puntoventa'), (int) $request->input('puntoventa_id'));
        }

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
            if (config('app.empresa') == 'CALZADOS FERLI')
                $data = $this->facturacionService->generaFacturaPorItemOt($request->all());
            else
                $data = $this->facturacionService->generaComprobanteGeneral($request->all());

			if (is_array($data) && ! empty($data['error'])) {
				return back()->withInput()->with('errores', [$data['error']]);
			}

			if (is_array($data) && ! empty($data['factura'])) {
				return redirect('ventas/factura')->with('mensaje', 'Comprobante '.$data['factura'].' generado con éxito');
			}

			return back()->withInput()->with('errores', ['No se pudo generar el comprobante']);
		} catch (\Exception $e)
		{
			return back()->withInput()->with('errores', [$e->getMessage()]);
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
        return $this->facturacionService->calculaFacturaPorPedido($request->all());
    }

    public function facturarPorPedido(Request $request)
    {
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

    // Lista una factura de ventas
    public function listaUnaFactura($id)
    {
		return $this->facturacionService->listaUnaFactura($id);
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
        $comprobante = $this->facturacionService->generaComprobanteGeneral($request->all());

        if (! empty($comprobante['error'])) {
            return redirect()->back()->withInput()->with('errores', [$comprobante['error']]);
        }

        return redirect()->back()->with('mensaje', 'Comprobante '.$comprobante['factura'].' generado con éxito');
    }

}

