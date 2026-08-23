<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\RemitoListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRemito;
use App\Models\Configuracion\Moneda;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Unidadmedida;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\Vendedor;
use App\Queries\Ventas\ClienteQueryInterface;
use App\Repositories\Configuracion\Actividad_ArcaRepositoryInterface;
use App\Repositories\Stock\LoteRepositoryInterface;
use App\Repositories\Ventas\DescuentoventaRepositoryInterface;
use App\Repositories\Ventas\TransporteRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Repositories\Ventas\IncotermRepositoryInterface;
use App\Repositories\Ventas\MotivocierrepedidoRepositoryInterface;
use App\Repositories\Ventas\PuntoventaRepositoryInterface;
use App\Repositories\Ventas\TiposuspensionclienteRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Services\Ventas\RemitoListadoPdfService;
use App\Services\Ventas\RemitoService;
use App\Support\Ventas\RemitoListadoFiltros;
use App\Support\Ventas\UsuarioPreferenciaFacturacionSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RemitoController extends Controller
{
    private $remitoService;

    private $remitoListadoPdfService;

    private $clienteQuery;

    private $tiposuspensionclienteRepository;

    private $motivocierrepedidoRepository;

    private $loteRepository;

    private $puntoventaRepository;

    private $tipotransaccionRepository;

    private $incotermRepository;

    private $formapagoRepository;

    private $descuentoventaRepository;

    private $actividad_arcaRepository;

    public function __construct(
        RemitoService $remitoservice,
        RemitoListadoPdfService $remitoListadoPdfService,
        TransporteRepositoryInterface $transporterepository,
        TiposuspensionclienteRepositoryInterface $tiposuspensionclienteRepository,
        MotivocierrepedidoRepositoryInterface $motivocierrepedidoRepository,
        ClienteQueryInterface $clientequery,
        DescuentoventaRepositoryInterface $descuentoventarepository,
        LoteRepositoryInterface $loterepository,
        PuntoventaRepositoryInterface $puntoventarepository,
        TipotransaccionRepositoryInterface $tipotransaccionrepository,
        IncotermRepositoryInterface $incotermrepository,
        FormapagoRepositoryInterface $formpagorepository,
        Actividad_ArcaRepositoryInterface $actividad_arcarepository,
    ) {
        $this->remitoService = $remitoservice;
        $this->remitoListadoPdfService = $remitoListadoPdfService;
        $this->tiposuspencionclienteRepository = $tiposuspensionclienteRepository;
        $this->motivocierrepedidoRepository = $motivocierrepedidoRepository;
        $this->clienteQuery = $clientequery;
        $this->descuentoventaRepository = $descuentoventarepository;
        $this->loteRepository = $loterepository;
        $this->puntoventaRepository = $puntoventarepository;
        $this->tipotransaccionRepository = $tipotransaccionrepository;
        $this->incotermRepository = $incotermrepository;
        $this->formapagoRepository = $formpagorepository;
        $this->actividad_arcaRepository = $actividad_arcarepository;
    }

    public function index(Request $request)
    {
        can('listar-remitos');

        $filtros = RemitoListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = RemitoListadoFiltros::paraQueryString($filtros);
        $remitos = $this->remitoService->leeRemitosIndex($filtros, true);

        return view('ventas.remito.index', [
            'remitos' => $remitos,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => RemitoListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-remitos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = RemitoListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $subtitulo = RemitoListadoFiltros::subtituloFiltros($filtros);

        switch ($formato) {
            case 'PDF':
                $rutaPdf = $this->remitoListadoPdfService->generar($filtros, $subtitulo);

                return response()->download($rutaPdf, 'listado_remito.pdf')->deleteFileAfterSend(true);

            case 'EXCEL':
                return (new RemitoListadoExport($this->remitoService))
                    ->parametros($filtros)
                    ->download('remito.xlsx');

            case 'CSV':
                return (new RemitoListadoExport($this->remitoService))
                    ->parametros($filtros)
                    ->download('remito.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('remito', RemitoListadoFiltros::paraQueryString($filtros));
    }

    /**
     * @deprecated Migrado a RemitoListadoFiltros
     * @return array{filtros: array<string, mixed>, estado: string, reparto: array<int, mixed>|string, fechaEntrega: \Carbon\Carbon|string}
     */
    private function resolveFiltrosRemitoIndex(Request $request): array
    {
        if (session('filtrosRemitos') == null) {
            $filtros = [];
            if ($request->url() != $request->fullUrl()) {
                $url = urldecode($request->fullUrl());
                $components = parse_url($url);
                parse_str($components['query'] ?? '', $filtros);

                if ($filtros != '' && isset($filtros['filter_column'])) {
                    session(['filtrosRemitos' => $filtros]);
                } else {
                    $filtros = [];
                }
            }
        } else {
            $filtros = session('filtrosRemitos');
        }

        $estado = '';
        $reparto = '';
        $fechaEntrega = Carbon::now();

        if ($filtros != '' && isset($filtros['filter_column'])) {
            for ($ii = 0; $ii < count($filtros['filter_column']); $ii++) {
                if ($filtros['filter_column'][$ii]['type'] == '') {
                    continue;
                }

                if ($filtros['filter_column'][$ii]['value'] != '') {
                    if ($filtros['filter_column'][$ii]['column'] == 'estado' &&
                        $filtros['filter_column'][$ii]['type'] == '=') {
                        $estado = $filtros['filter_column'][$ii]['value'];
                    }

                    if ($filtros['filter_column'][$ii]['column'] == 'reparto') {
                        $reparto = $filtros['filter_column'][$ii]['value'];
                    }

                    if ($filtros['filter_column'][$ii]['column'] == 'fechaentrega' &&
                        $filtros['filter_column'][$ii]['value'][0] != '') {
                        $fechaEntrega = $filtros['filter_column'][$ii]['value'][0].'/'.
                                        $filtros['filter_column'][$ii]['value'][1];
                    }
                }
            }
        }

        return [
            'filtros' => $filtros,
            'estado' => $estado,
            'reparto' => $reparto,
            'fechaEntrega' => $fechaEntrega,
        ];
    }

    public function limpiafiltro(Request $request)
    {
        session()->forget('filtrosRemitos');

        return json_encode(['ok']);
    }

    public function listarRemitoPdf($id)
    {
        can('listar-remitos');

        return redirect()->route('sesion_impresion_remito', [
            'id' => $id,
            'auto' => 1,
            'solo_formulario' => 'REMITO',
        ]);
    }

    public function crear()
    {
        can('crear-remitos');

        $this->armarTablasVista(
            $cliente_query,
            $condicionventa_query,
            $vendedor_query,
            $listaprecio_query,
            $moneda_query,
            $tiposuspensioncliente_query,
            $motivocierrepedido_query,
            $lote_query,
            $puntoventa_query,
            $tipotransaccion_query,
            $formapago_query,
            $incoterm_query,
            $unidadmedida_query
        );

        $prefsFacturacion = UsuarioPreferenciaFacturacionSupport::leer();
        $puntoventadefault_id = $prefsFacturacion['puntoventa_id'];
        $puntoventaremitodefault_id = $prefsFacturacion['puntoventaremito_id'];
        $tipotransacciondefault_id = $prefsFacturacion['tipotransaccion_id'];
        $formapago_query = $this->formapagoRepository->all();
        $incoterm_query = $this->incotermRepository->all();
        $descuentoventa_query = $this->descuentoventaRepository->all();
        $actividad_arca_query = $this->actividad_arcaRepository->all();

        return view('ventas.remito.crear', compact(
            'cliente_query',
            'condicionventa_query',
            'vendedor_query',
            'listaprecio_query',
            'moneda_query',
            'tiposuspensioncliente_query',
            'motivocierrepedido_query',
            'lote_query',
            'puntoventa_query',
            'puntoventadefault_id',
            'tipotransaccion_query',
            'tipotransacciondefault_id',
            'puntoventaremitodefault_id',
            'formapago_query',
            'incoterm_query',
            'descuentoventa_query',
            'unidadmedida_query',
            'actividad_arca_query'
        ));
    }

    public function guardar(ValidacionRemito $request)
    {
        can('crear-remitos');

        $data = $request->all();
        $pvRemito = $request->filled('puntoventa_id')
            ? (int) $request->input('puntoventa_id')
            : (int) (UsuarioPreferenciaFacturacionSupport::leer()['puntoventaremito_id'] ?? 0);
        $data['puntoventa_id'] = $pvRemito > 0 ? $pvRemito : null;

        $resultado = $this->remitoService->guardaRemito($data, 'create');

        if (isset($resultado['error'])) {
            return back()->with('errores', [$resultado['error']]);
        }

        $mensaje = 'Remito '.$resultado['id'].' '.$resultado['codigo'].' creado con exito';

        return redirect('ventas/remito')->with('mensaje', $mensaje);
    }

    public function editar($id)
    {
        $soloConsulta = request()->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            if (! can('editar-remitos', false) && ! can('listar-remitos', false)) {
                can('listar-remitos');
            }
        } else {
            can('editar-remitos');
        }

        $remito = $this->remitoService->leeRemito($id);

        $this->armarTablasVista(
            $cliente_query,
            $condicionventa_query,
            $vendedor_query,
            $listaprecio_query,
            $moneda_query,
            $tiposuspensioncliente_query,
            $motivocierrepedido_query,
            $lote_query,
            $puntoventa_query,
            $tipotransaccion_query,
            $formapago_query,
            $incoterm_query,
            $unidadmedida_query,
            $remito
        );

        $flEncontro = false;
        foreach ($cliente_query as $cliente) {
            if ($cliente->id == $remito->cliente_id) {
                $flEncontro = true;
                break;
            }
        }
        if (! $flEncontro) {
            return back()->with('errores', ['Cliente '.$remito->clientes->nombre.' no activo']);
        }

        $prefsFacturacion = UsuarioPreferenciaFacturacionSupport::leer();
        $puntoventadefault_id = $prefsFacturacion['puntoventa_id'];
        $puntoventaremitodefault_id = $prefsFacturacion['puntoventaremito_id'];
        $tipotransacciondefault_id = $prefsFacturacion['tipotransaccion_id'];
        $descuentoventa_query = $this->descuentoventaRepository->all();
        $actividad_arca_query = $this->actividad_arcaRepository->all();

        $ocultarVolver = $soloConsulta;
        $puedeActualizarRemito = can('actualizar-remitos', false);

        return view('ventas.remito.editar', compact(
            'remito',
            'cliente_query',
            'condicionventa_query',
            'vendedor_query',
            'listaprecio_query',
            'moneda_query',
            'tiposuspensioncliente_query',
            'motivocierrepedido_query',
            'lote_query',
            'puntoventa_query',
            'puntoventadefault_id',
            'tipotransaccion_query',
            'descuentoventa_query',
            'tipotransacciondefault_id',
            'puntoventaremitodefault_id',
            'formapago_query',
            'incoterm_query',
            'unidadmedida_query',
            'actividad_arca_query',
            'soloConsulta',
            'ocultarVolver',
            'puedeActualizarRemito'
        ));
    }

    public function actualizar(Request $request, $id)
    {
        can('actualizar-remitos');

        $resultado = $this->remitoService->guardaRemito($request->all(), 'update', $id);

        if (isset($resultado['error'])) {
            if ($request->input('origen') === 'modal_consulta') {
                return redirect()
                    ->route('editar_remito', [
                        'id' => $id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ])
                    ->withInput()
                    ->with('errores', [$resultado['error']]);
            }

            return back()->with('errores', [$resultado['error']]);
        }

        $mensaje = 'Remito '.$request->codigo.' actualizado con exito';

        if ($request->input('origen') === 'modal_consulta') {
            return redirect()
                ->route('editar_remito', [
                    'id' => $id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ])
                ->with('mensaje', $mensaje);
        }

        return redirect('ventas/remito')->with('mensaje', $mensaje);
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-remitos');

        if ($this->remitoService->borraRemito($id)) {
            $mensaje = 'ok';
        } else {
            $mensaje = 'error';
        }

        if ($request->ajax()) {
            return response()->json(['mensaje' => $mensaje]);
        }

        return redirect('ventas/remito')->with('mensaje', $mensaje);
    }

    private function armarTablasVista(
        &$cliente_query,
        &$condicionventa_query,
        &$vendedor_query,
        &$listaprecio_query,
        &$moneda_query,
        &$tiposuspensioncliente_query,
        &$motivocierrepedido_query,
        &$lote_query,
        &$puntoventa_query,
        &$tipotransaccion_query,
        &$formapago_query,
        &$incoterm_query,
        &$unidadmedida_query,
        $remito = null
    ) {
        $cliente_query = $this->clienteQuery->allQueryCargaPedido(['id', 'nombre', 'codigo']);
        $tiposuspensioncliente_query = $this->tiposuspencionclienteRepository->all();
        $motivocierrepedido_query = $this->motivocierrepedidoRepository->all();
        $condicionventa_query = Condicionventa::all();
        $vendedor_query = Vendedor::orderBy('nombre', 'ASC')->get();
        $lote_query = $this->loteRepository->all();
        $puntoventa_query = $this->puntoventaRepository->all('A');
        $tipotransaccion_query = $this->tipotransaccionRepository->all(['V', 'C'], ['A']);
        $formapago_query = $this->formapagoRepository->all();
        $incoterm_query = $this->incotermRepository->all();
        $unidadmedida_query = Unidadmedida::all()->toarray();

        array_splice($unidadmedida_query, 1, 1);

        $listaprecio_query = Listaprecio::all();
        $moneda_query = Moneda::all();
    }

    /**
     * Genera remito desde pedido (Bierzo admin). No gastronomía/estacionamiento.
     */
    public function generarDesdePedido(Request $request)
    {
        can('crear-remitos');

        return response()->json($this->remitoService->crearDesdePedido($request->all()));
    }

    /**
     * F5: asigna kilos por reparto/porcentaje (algoritmo Anita Villafranca).
     */
    public function asignarKilos(Request $request)
    {
        can('crear-remitos');

        return response()->json($this->remitoService->asignarKilosVillafranca(
            (int) $request->input('transporte_id', 0),
            (float) $request->input('porcentaje', 0)
        ));
    }
}
