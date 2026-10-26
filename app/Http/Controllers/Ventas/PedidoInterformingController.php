<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\PedidoInterformingListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPedidoInterforming;
use App\Models\Configuracion\Moneda;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Unidadmedida;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\PedidoInterforming;
use App\Models\Ventas\Vendedor;
use App\Repositories\Configuracion\Actividad_ArcaRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Repositories\Ventas\IncotermRepositoryInterface;
use App\Repositories\Ventas\MotivocierrepedidoRepositoryInterface;
use App\Repositories\Ventas\PuntoventaRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Services\Ventas\PedidoInterformingPdfService;
use App\Services\Ventas\PedidoInterformingService;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoDisenadorPreviewSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoVistaMenuSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Ventas\InterformingFacturacionMaestrosSupport;
use App\Support\Ventas\PedidoEstadosInterforming;
use App\Support\Ventas\PedidoInterformingFacturacionSupport;
use App\Support\Ventas\PedidoInterformingListadoColumnas;
use App\Support\Ventas\PedidoInterformingListadoFiltros;
use App\Support\Ventas\PedidoInterformingListadoPreferenciasUsuario;
use App\Support\Ventas\PedidoInterformingNumeracionSupport;
use App\Support\Ventas\PedidoInterformingSupport;
use App\Support\Ventas\UsuarioPreferenciaFacturacionSupport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class PedidoInterformingController extends Controller
{
    private PedidoInterformingService $pedidoService;

    private PedidoInterformingPdfService $pedidoPdfService;

    private MotivocierrepedidoRepositoryInterface $motivocierrepedidoRepository;

    public function __construct(
        PedidoInterformingService $pedidoService,
        PedidoInterformingPdfService $pedidoPdfService,
        MotivocierrepedidoRepositoryInterface $motivocierrepedidoRepository,
        private readonly PuntoventaRepositoryInterface $puntoventaRepository,
        private readonly TipotransaccionRepositoryInterface $tipotransaccionRepository,
        private readonly FormapagoRepositoryInterface $formapagoRepository,
        private readonly IncotermRepositoryInterface $incotermRepository,
        private readonly Actividad_ArcaRepositoryInterface $actividadArcaRepository,
    ) {
        $this->pedidoService = $pedidoService;
        $this->pedidoPdfService = $pedidoPdfService;
        $this->motivocierrepedidoRepository = $motivocierrepedidoRepository;
    }

    public function index(Request $request)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('listar-pedidos');

        $armado = $this->armarListado($request);
        if ($armado instanceof \Illuminate\Http\RedirectResponse) {
            return $armado;
        }

        $puedeFacturarIndex = can('crear-factura', false) || can('editar-pedidos', false);
        $armado['puedeFacturarIndex'] = $puedeFacturarIndex;
        if ($puedeFacturarIndex) {
            $armado = array_merge($armado, $this->datosVistaFacturacionIndex());
        }

        return view(PedidoInterformingSupport::vista('index'), $armado);
    }

    public function pedidosVenta(Request $request)
    {
        $request->merge(['tipo' => PedidoInterformingNumeracionSupport::TIPO_VENTA]);

        return $this->index($request);
    }

    public function pedidosExportacion(Request $request)
    {
        $request->merge(['tipo' => PedidoInterformingNumeracionSupport::TIPO_EXPORTACION]);

        return $this->index($request);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('listar-pedidos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = PedidoInterformingListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->pedidoService->leePedidos($filtros, false);
                $view = \View::make(PedidoInterformingSupport::vista('listado'), [
                    'datas' => $datas,
                    'filtros' => $filtros,
                    'subtituloFiltros' => PedidoInterformingListadoFiltros::subtituloFiltros($filtros),
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombre_pdf = 'listado_pedido_interforming';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new PedidoInterformingListadoExport($this->pedidoService))
                    ->parametros($filtros)
                    ->download('pedido_interforming.xlsx');

            case 'CSV':
                return (new PedidoInterformingListadoExport($this->pedidoService))
                    ->parametros($filtros)
                    ->download('pedido_interforming.csv', Excel::CSV);
        }

        return redirect()->route('pedido', PedidoInterformingListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('crear-pedidos');

        $pedido = new PedidoInterforming([
            'fecha' => now()->toDateString(),
            'fechaentrega' => now()->toDateString(),
            'estadopedido' => PedidoEstadosInterforming::CAB_PENTREGAR,
            'tipo_comprobante' => 'PED',
            'letra_comprobante' => 'X',
            'sucursal_comprobante' => 1,
            'cotizacion' => 1,
        ]);
        $pedido->pedido_articulos = collect();

        return view(PedidoInterformingSupport::vista('crear'), $this->datosFormulario($pedido, 'crear'));
    }

    public function guardar(ValidacionPedidoInterforming $request)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('crear-pedidos');

        $data = $this->pedidoService->guardar($request->validated(), 'create');
        if (isset($data['error'])) {
            return back()->withInput()->with('errores', [$data['error']]);
        }

        return redirect()->route('pedido')->with(
            'mensaje',
            'Pedido '.$data['id'].' '.$data['codigo'].' creado con éxito'
        );
    }

    public function editar($id)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        $soloConsulta = request()->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            if (! can('editar-pedidos', false) && ! can('listar-pedidos', false)) {
                can('listar-pedidos');
            }
        } else {
            can('editar-pedidos');
        }

        $pedido = $this->pedidoService->leePedido((int) $id);
        if (! $pedido) {
            return redirect()->route('pedido')->with('errores', ['Pedido inexistente']);
        }

        $puedeActualizarPedido = can('actualizar-pedidos', false);
        $ocultarVolver = $soloConsulta;
        $mostrarFacturarPedido = PedidoInterformingFacturacionSupport::puedeFacturar($pedido);

        $vista = array_merge(
            $this->datosFormulario($pedido, 'editar'),
            compact('soloConsulta', 'puedeActualizarPedido', 'ocultarVolver', 'mostrarFacturarPedido')
        );

        if ($mostrarFacturarPedido) {
            $vista = array_merge($vista, $this->datosVistaFacturacionIndex());
        }

        return view(PedidoInterformingSupport::vista('editar'), $vista);
    }

    public function actualizar(ValidacionPedidoInterforming $request, $id)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('actualizar-pedidos');

        $payload = $request->validated();
        $payload['id'] = (int) $id;
        $data = $this->pedidoService->guardar($payload, 'update');
        if (isset($data['error'])) {
            return back()->withInput()->with('errores', [$data['error']]);
        }

        return redirect()->route('pedido')->with(
            'mensaje',
            'Pedido '.$data['id'].' actualizado con éxito'
        );
    }

    public function eliminar($id)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('borrar-pedidos');

        $data = $this->pedidoService->eliminar((int) $id);
        if (isset($data['error'])) {
            return back()->with('errores', [$data['error']]);
        }

        return redirect()->route('pedido')->with('mensaje', 'Pedido eliminado');
    }

    public function limpiafiltro(Request $request)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        session()->forget('filtrosPedidosInterforming');

        return response()->json(['ok' => true]);
    }

    public function listarPedidoPdf($id)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('listar-pedidos');

        return $this->pedidoPdfService->descargar((int) $id);
    }

    public function contextoFacturacion(int $id)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        if (! can('crear-factura', false) && ! can('editar-pedidos', false)) {
            can('editar-pedidos');
        }

        $pedido = $this->pedidoService->leePedido($id);
        if (! $pedido || ! PedidoInterformingFacturacionSupport::puedeFacturar($pedido)) {
            return response()->json([
                'error' => 'El pedido no se puede facturar (estado o cantidades).',
            ], 422);
        }

        return response()->json(PedidoInterformingFacturacionSupport::contextoFacturacion($pedido));
    }

    public function previewWorkbench(Request $request)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('listar-pedidos');

        $filtros = PedidoInterformingListadoFiltros::resolverDesdeRequest($request);
        $filtros['_per_page'] = ListadoDisenadorPreviewSupport::LIMITE_MUESTRA;
        $layout = PedidoInterformingListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
        $page = $this->pedidoService->leePedidos($filtros, true);
        $total = method_exists($page, 'total') ? (int) $page->total() : $page->count();
        $filas = method_exists($page, 'getCollection') ? $page->getCollection() : $page;
        $orden = ListadoOrdenamientoSupport::normalizar(
            $request->input('sort', $filtros['sort'] ?? []),
            PedidoInterformingListadoFiltros::camposOrdenables()
        );
        $agrupar = ListadoAgrupacionSupport::normalizar(
            $request->input('group', $filtros['agrupar'] ?? []),
            PedidoInterformingListadoFiltros::camposOrdenables()
        );
        $filtrosCortes = $filtros;
        $filtrosCortes['agrupar'] = $agrupar;
        $cortes = $agrupar !== [] ? $this->pedidoService->cortes($filtrosCortes) : ['activo' => false];

        return response()->json(ListadoDisenadorPreviewSupport::payload(
            $layout,
            $orden,
            $agrupar,
            $filas,
            static fn (object $row, string $key): string => PedidoInterformingListadoColumnas::valorCelda($row, $key),
            $total,
            $etiquetas,
            $cortes
        ));
    }

    public function guardarVistaListado(Request $request)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('listar-pedidos');

        $filtros = PedidoInterformingListadoFiltros::resolverDesdeRequest($request);
        $layout = PedidoInterformingListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $orden = $filtros['sort'] ?? [];
        $vista = ListadoVistaSupport::guardar(
            PedidoInterformingListadoColumnas::RECURSO,
            (int) auth()->id(),
            (string) $request->input('nombre', ''),
            [
                'modo' => $filtros['modo'],
                'qbe' => $filtros['qbe'] ?? [],
                'sort' => $orden,
                'orden' => $orden,
                'agrupar' => $filtros['agrupar'] ?? [],
            ],
            $layout,
            $request->boolean('es_default'),
            $request->boolean('compartida'),
            $request->filled('vista_id') ? (int) $request->input('vista_id') : null
        );
        if (! $vista) {
            return redirect()->route('pedido', PedidoInterformingListadoFiltros::paraQueryString($filtros))
                ->with('error', 'No se pudo guardar la vista.');
        }
        ListadoVistaMenuSupport::sincronizar($vista, $request->boolean('crear_en_menu'));
        $qs = PedidoInterformingListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs['vista_id'] = $vista->id;

        return redirect()->route('pedido', $qs)->with('mensaje', 'Vista «'.$vista->nombre.'» guardada.');
    }

    public function eliminarVistaListado(int $id)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('listar-pedidos');
        $ok = ListadoVistaSupport::eliminar($id, PedidoInterformingListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('pedido', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('listar-pedidos');
        $layout = PedidoInterformingListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $vistaId = $request->filled('vista_id') ? (int) $request->input('vista_id') : 0;
        if ($vistaId > 0 && $request->boolean('actualizar_vista')) {
            $vista = ListadoVistaSupport::findParaUsuario($vistaId, PedidoInterformingListadoColumnas::RECURSO, (int) auth()->id());
            if ($vista && (int) $vista->usuario_id === (int) auth()->id()) {
                $vista->columnas_json = $layout;
                $vista->save();
            }
        } else {
            PedidoInterformingListadoPreferenciasUsuario::persistirGrillaEstandar($layout);
        }
        $filtros = PedidoInterformingListadoFiltros::resolverDesdeRequest($request);
        $qs = PedidoInterformingListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs[$vistaId > 0 ? 'vista_id' : 'vista_estandar'] = $vistaId > 0 ? $vistaId : 1;

        return redirect()->route('pedido', $qs)->with('mensaje', 'Grilla actualizada.');
    }

    public function guardarEtiquetasListado(Request $request)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('listar-pedidos');
        $etiquetas = $request->input('etiquetas', []);
        if (! is_array($etiquetas)) {
            $etiquetas = [];
        }
        ListadoColumnaEtiquetaSupport::guardar(
            PedidoInterformingListadoColumnas::RECURSO,
            $etiquetas,
            array_keys(PedidoInterformingListadoColumnas::catalogoActivo())
        );

        return redirect()->route(
            'pedido',
            PedidoInterformingListadoFiltros::paraQueryString(PedidoInterformingListadoFiltros::resolverDesdeRequest($request))
        )->with('mensaje', 'Etiquetas actualizadas.');
    }

    /**
     * @return array<string, mixed>|\Illuminate\Http\RedirectResponse
     */
    private function armarListado(Request $request): array|\Illuminate\Http\RedirectResponse
    {
        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $vistas = ListadoVistaSupport::listarParaUsuario(PedidoInterformingListadoColumnas::RECURSO, $usuarioId);
        $vistaActiva = null;
        $forzarEstandar = $request->boolean('vista_estandar')
            || $request->input('vista_modo') === 'estandar';

        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                PedidoInterformingListadoColumnas::RECURSO,
                $usuarioId
            );
        } elseif (
            ! $forzarEstandar
            && ! $request->has('filtro_valor')
            && ! $request->has('qbe')
            && ! $request->boolean('limpiar_filtros')
            && ! $request->boolean('filtro_limpiar')
        ) {
            $vistaActiva = ListadoVistaSupport::defaultDelUsuario(PedidoInterformingListadoColumnas::RECURSO, $usuarioId)
                ?? ListadoVistaSupport::defaultDelRol(PedidoInterformingListadoColumnas::RECURSO, (int) session('rol_id'));
        }

        $filtrosRequest = ListadoVistaSupport::prepararQbeContraVista(
            PedidoInterformingListadoFiltros::resolverDesdeRequest($request),
            $request
        );
        $filtros = $filtrosRequest;
        if ($vistaActiva && is_array($vistaActiva->filtros_json)) {
            $filtros = PedidoInterformingListadoFiltros::fusionarDesdeVista($filtros, $vistaActiva->filtros_json);
        }
        unset($filtros['_qbe_explicito'], $filtros['_limpiar']);
        ListadoVistaSupport::recordarQbeSiEnvio($vistaActiva, $request, $filtros);
        if ($vistaActiva && ($request->exists('group') || $request->exists('sort'))) {
            if ($request->exists('group')) {
                $filtros['agrupar'] = $filtrosRequest['agrupar'] ?? [];
            }
            if ($request->exists('sort')) {
                $filtros['sort'] = $filtrosRequest['sort'] ?? [];
            }
            ListadoVistaSupport::recordarOrdenYAgrupar(
                $vistaActiva,
                $filtros['sort'] ?? [],
                $filtros['agrupar'] ?? []
            );
        }

        if ($request->boolean('quitar_orden')) {
            $filtros['sort'] = [];
            if ($vistaActiva) {
                ListadoVistaSupport::recordarOrdenYAgrupar(
                    $vistaActiva,
                    [],
                    $filtros['agrupar'] ?? []
                );
            }
            $params = PedidoInterformingListadoFiltros::paraQueryString($filtros);
            if ($vistaActiva) {
                $params['vista_id'] = $vistaActiva->id;
            } elseif ($forzarEstandar) {
                $params['vista_estandar'] = 1;
            }

            return redirect()->route('pedido', $params)->with('mensaje', 'Se quitó el orden de la grilla.');
        }

        $catalogo = PedidoInterformingListadoColumnas::catalogoActivo();
        $etiquetasInstalacion = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            PedidoInterformingListadoColumnas::RECURSO,
            $catalogo
        );
        if ($vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== []) {
            $grillaLayout = PedidoInterformingListadoPreferenciasUsuario::normalizarLayout($vistaActiva->columnas_json);
        } else {
            $grillaLayout = PedidoInterformingListadoPreferenciasUsuario::grillaEstandar();
        }
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);
        $datas = $this->pedidoService->leePedidos($filtros, true);
        $cortes = ['activo' => false];
        if (ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], PedidoInterformingListadoFiltros::camposOrdenables()) !== []) {
            $cortes = $this->pedidoService->cortes($filtros);
        }
        $camposFiltro = PedidoInterformingListadoFiltros::camposQbeDisponibles();
        foreach ($camposFiltro as $key => $meta) {
            $camposFiltro[$key]['label'] = $etiquetas[$key] ?? $etiquetasInstalacion[$key] ?? $meta['label'];
        }
        if ($vistaActiva) {
            $filtros['vista_id'] = $vistaActiva->id;
        } elseif ($forzarEstandar) {
            $filtros['vista_estandar'] = 1;
        }
        $filtros['columnas'] = implode(',', $columnasVisibles);

        return [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => PedidoInterformingListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => $camposFiltro,
            'columnasVisibles' => $columnasVisibles,
            'grillaLayout' => $grillaLayout,
            'catalogoColumnas' => $catalogo,
            'etiquetasColumnas' => $etiquetas,
            'etiquetasInstalacion' => $etiquetasInstalacion,
            'vistasListado' => $vistas,
            'vistaActiva' => $vistaActiva,
            'workbenchListo' => ListadoVistaSupport::tablasDisponibles(),
            'cortes' => $cortes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(PedidoInterforming $pedido, string $funcion): array
    {
        return [
            'pedido' => $pedido,
            'datos' => ['funcion' => $funcion],
            'vendedor_query' => Vendedor::orderBy('nombre')->get(),
            'condicionventa_query' => Condicionventa::orderBy('nombre')->get(),
            'moneda_query' => Moneda::orderBy('nombre')->get(),
            'listaprecio_query' => Listaprecio::orderBy('nombre')->get(),
            'unidadmedida_query' => Unidadmedida::orderBy('nombre')->get(),
            'motivocierrepedido_query' => $this->motivocierrepedidoRepository->all(),
            'estadosCabecera' => PedidoEstadosInterforming::etiquetasCabecera(),
            'estadosItem' => PedidoEstadosInterforming::etiquetasItem(),
        ];
    }

    /**
     * Datos del shell de facturación desde el index (mismo contrato que Bierzo).
     *
     * @return array<string, mixed>
     */
    private function datosVistaFacturacionIndex(): array
    {
        $prefs = UsuarioPreferenciaFacturacionSupport::leer();
        // Preferencias de usuario; si faltan, PV4+FAE (Anita suc export / b-fremito).
        $defaultsIf = InterformingFacturacionMaestrosSupport::defaultsParaPedido('PEX');

        return [
            'puntoventa_query' => $this->puntoventaRepository->all('A'),
            'tipotransaccion_query' => $this->tipotransaccionRepository->all(['V'], ['A']),
            'formapago_query' => $this->formapagoRepository->all(),
            'incoterm_query' => $this->incotermRepository->all(),
            'actividad_arca_query' => $this->actividadArcaRepository->all(),
            'moneda_query' => Moneda::orderBy('nombre')->get(),
            'puntoventadefault_id' => $prefs['puntoventa_id'] ?? $defaultsIf['puntoventa_id'],
            'puntoventaremitodefault_id' => $prefs['puntoventaremito_id'] ?? null,
            'tipotransacciondefault_id' => $prefs['tipotransaccion_id'] ?? $defaultsIf['tipotransaccion_id'],
            'data' => null,
        ];
    }
}
