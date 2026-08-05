<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\RequisicionExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRequisicion;
use App\Models\Compras\Condicioncompra;
use App\Models\Compras\Condicionentrega;
use App\Models\Compras\Condicionpago;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Archivo;
use App\Models\Compras\Requisicion_Estado;
use App\Models\Configuracion\Moneda;
use App\Models\Configuracion\Oficinacompra;
use App\Models\Stock\Articulo;
use App\Models\Stock\Color;
use App\Models\Stock\Talle;
use App\Queries\Compras\RequisicionQueryInterface;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Repositories\Compras\RequisicionRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Presupuesto\PartidagastoRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Services\Compras\OrdencompraGestionService;
use App\Services\Compras\RequisicionArticuloCambioService;
use App\Services\Compras\RequisicionService;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Support\Compras\RequisicionLineasOcSupport;
use App\Support\Stock\ArticuloPrecioUltimaCompraSupport;
use App\Support\Compras\RequisicionListadoFiltros;
use App\Support\Compras\RequisicionProvisorioSupport;
use App\Support\Compras\RequisicionTotalesCabecera;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequisicionController extends Controller
{
    private $empresaRepository;

    private $centrocostoRepository;

    private $monedaRepository;

    private $formapagoRepository;

    private $requisicionRepository;

    private $requisicionQuery;

    private $requisicionService;

    private $arbolaprobacion_movimientoRepository;

    private $arbolaprobacionService;

    private $partidagastoRepository;

    private $cotizacionQuery;

    public function __construct(
        RequisicionRepositoryInterface $requisicionrepository,
        EmpresaRepositoryInterface $empresarepository,
        CentrocostoRepositoryInterface $centrocostorepository,
        MonedaRepositoryInterface $monedarepository,
        FormapagoRepositoryInterface $formapagorepository,
        RequisicionService $requisicionservice,
        RequisicionQueryInterface $requisicionquery,
        Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacion_movimientorepository,
        ArbolaprobacionService $arbolaprobacionservice,
        PartidagastoRepositoryInterface $partidagastorepository,
        CotizacionQueryInterface $cotizacionquery,
    ) {
        $this->requisicionRepository = $requisicionrepository;
        $this->empresaRepository = $empresarepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->monedaRepository = $monedarepository;
        $this->formapagoRepository = $formapagorepository;
        $this->requisicionService = $requisicionservice;
        $this->requisicionQuery = $requisicionquery;
        $this->arbolaprobacion_movimientoRepository = $arbolaprobacion_movimientorepository;
        $this->arbolaprobacionService = $arbolaprobacionservice;
        $this->partidagastoRepository = $partidagastorepository;
        $this->cotizacionQuery = $cotizacionquery;
    }

    public function index(Request $request)
    {
        can('listar-requisicion');
        // $this->requisicionService->sincronizarConAnita();
        $hay_requisiciones = $this->requisicionQuery->first();

        if (! $hay_requisiciones) {
            $this->requisicionService->sincronizarConAnita();
        }

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = RequisicionListadoFiltros::resolverDesdeRequest(
            $request,
            null,
            $empresaDefault ? (int) $empresaDefault : null
        );

        $requisicion = $this->requisicionQuery->leeRequisicion($filtros, true, true);

        $estadoAprobada = Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'), true)]['nombre'];
        $datas = [
            'requisicion' => $requisicion,
            'busqueda' => $filtros['busqueda'],
            'filtros' => $filtros,
            'filtrosQuery' => RequisicionListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => RequisicionListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'estado_enum' => Requisicion_Estado::$enumEstado,
            'estado_en_compras' => Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'],
            'estado_en_arbol_aprobacion' => Requisicion_Estado::$enumEstado[array_search('R', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'],
            'estado_aprobada_requisicion' => $estadoAprobada,
            'estado_genero_oc_requisicion' => Requisicion_Estado::$enumEstado[array_search('O', array_column(Requisicion_Estado::$enumEstado, 'valor'), true)]['nombre'],
            'estado_provisorio' => RequisicionProvisorioSupport::nombreEstadoProvisorio(),
            'tratamiento_enum' => Requisicion::$enumTratamiento,
            'contratacionDirecta_enum' => Requisicion::$enumContratacionDirecta,
        ];

        return view('compras.requisicion.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-requisicion');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = RequisicionListadoFiltros::resolverDesdeRequest(
            $request,
            $busqueda,
            $empresaDefault ? (int) $empresaDefault : null
        );

        switch ($formato) {
            case 'PDF':
                $requisicion = $this->requisicionQuery->leeRequisicion($filtros, false, true);

                $view = \View::make('compras.requisicion.listado', compact('requisicion'))
                    ->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_requisicion';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new RequisicionExport($this->requisicionQuery))
                    ->parametros($filtros)
                    ->download('requisicion.xlsx');

            case 'CSV':
                return (new RequisicionExport($this->requisicionQuery))
                    ->parametros($filtros)
                    ->download('requisicion.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_requisicion', RequisicionListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-requisicion');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $formapago_query = $this->formapagoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $oficinacompra_query = Oficinacompra::orderBy('nombre')->get();
        $proveedor_query = Proveedor::orderBy('nombre')->get();
        $estado_enum = Requisicion_Estado::$enumEstado;
        $tratamiento_enum = Requisicion::$enumTratamiento;
        $contratacionDirecta_enum = Requisicion::$enumContratacionDirecta;
        $data = null;
        $modo_provisorio = RequisicionProvisorioSupport::usuarioUsaModoProvisorio();
        $estado_provisorio = RequisicionProvisorioSupport::nombreEstadoProvisorio();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, RequisicionListadoFiltros::class);
        $color_query = Color::query()->orderBy('nombre')->get(['id', 'nombre']);
        $talle_query = Talle::query()->orderBy('nombre')->get(['id', 'nombre']);

        return view('compras.requisicion.crear', compact(
            'data',
            'empresa_query',
            'centrocosto_query',
            'formapago_query',
            'moneda_query',
            'oficinacompra_query',
            'proveedor_query',
            'estado_enum',
            'tratamiento_enum',
            'contratacionDirecta_enum',
            'modo_provisorio',
            'estado_provisorio',
            'filtrosQuery',
            'color_query',
            'talle_query',
        ));
    }

    public function guardar(ValidacionRequisicion $request)
    {
        $ret = $this->requisicionService->guardaRequisicion($request);

        if (($ret['mensaje'] ?? '') === 'seleccionar_centrocosto') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json($ret);
            }

            return redirect()->back()->withInput()->with('mensaje', 'Debe seleccionar el centro de costo de destino para el árbol de aprobación.');
        }

        if ($ret['mensaje'] == 'ok') {
            if (! empty($ret['modo_provisorio']) && ! empty($ret['requisicion_id'])) {
                return redirect()->route('editar_requisicion', QueryRetornoListado::paramsRutaEditar(
                    $request,
                    RequisicionListadoFiltros::class,
                    (int) $ret['requisicion_id']
                ))->with('mensaje', 'Requisición guardada en PROVISORIO. Revise los datos y confirme.');
            }

            return redirect()->route('consultar_requisicion', QueryRetornoListado::desdeRequest($request, RequisicionListadoFiltros::class))
                ->with('mensaje', 'Requisición creada con éxito');
        }

        return redirect()->back()->withInput()->with('mensaje-error', $ret['errores']);
    }

    /**
     * Comprobantes vinculados a la requisición (órdenes de compra; más adelante recepciones y facturas).
     */
    public function comprobantesAsociados(int $id)
    {
        if (! can('listar-requisicion', false) && ! can('editar-requisicion', false)) {
            return response()->json(['message' => 'No tiene permisos para esta consulta.'], 403);
        }

        if (! $this->requisicionQuery->requisicionAccesiblePorUsuario($id)) {
            return response()->json(['message' => 'Requisición no encontrada o sin acceso.'], 404);
        }

        $req = $this->requisicionRepository->find($id);
        if (! $req) {
            return response()->json(['message' => 'Requisición no encontrada.'], 404);
        }

        $ocs = Ordencompra::query()
            ->where('requisicion_id', $id)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get(['id', 'numeroordencompra', 'fecha', 'estadoordencompra']);

        $filas = $this->filasOrdenesCompraVinculadasDesdeColeccion($ocs);

        return response()->json([
            'numerorequisicion' => $req->numerorequisicion,
            'requisicion_id' => $req->id,
            'filas' => $filas,
            'proximamente' => [
                'Recepciones de proveedores asociadas a la orden de compra',
                'Facturas de compra vinculadas a esa orden',
            ],
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Ordencompra>  $ocs
     * @return list<array<string, mixed>>
     */
    private function filasOrdenesCompraVinculadasDesdeColeccion($ocs): array
    {
        $puedeVerOc = can('listar-ordencompra', false) || can('editar-ordencompra', false);
        $puedeEditarOc = can('editar-ordencompra', false);
        $puedeImprimirOc = can('listar-ordencompra', false) || can('editar-ordencompra', false);

        $filas = [];
        foreach ($ocs as $oc) {
            $fila = [
                'tipo' => 'orden_compra',
                'tipo_etiqueta' => 'Orden de compra',
                'id' => $oc->id,
                'numero' => $oc->numeroordencompra,
                'fecha' => $oc->fecha ? date('d/m/Y', strtotime((string) $oc->fecha)) : '',
                'estado' => (string) ($oc->estadoordencompra ?? ''),
            ];
            if ($puedeVerOc) {
                $fila['url_ver'] = urlAppDesdeRoute('solo_consulta_ordencompra', ['id' => $oc->id]);
            }
            if ($puedeEditarOc) {
                $fila['url_editar'] = urlAppDesdeRoute('editar_ordencompra', ['id' => $oc->id]);
            }
            if ($puedeImprimirOc) {
                $fila['url_imprimir_vertical'] = urlAppDesdeRoute('imprimir_pdf_ordencompra', ['id' => $oc->id]);
                $fila['url_imprimir_apaisado'] = urlAppDesdeRoute('imprimir_pdf_ordencompra', ['id' => $oc->id, 'formato' => 'apaisado']);
            }
            $filas[] = $fila;
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ordenesCompraVinculadasFilas(int $requisicionId): array
    {
        if ($requisicionId <= 0) {
            return [];
        }

        $ocs = Ordencompra::query()
            ->where('requisicion_id', $requisicionId)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get(['id', 'numeroordencompra', 'fecha', 'estadoordencompra']);

        return $this->filasOrdenesCompraVinculadasDesdeColeccion($ocs);
    }

    public function imprimirPdf($id)
    {
        if (! can('listar-requisicion', false) && ! can('editar-requisicion', false)) {
            return redirect()->route('inicio')->with('mensaje', 'No tienes permisos para imprimir la requisición');
        }

        if (! $this->requisicionQuery->requisicionAccesiblePorUsuario((int) $id)) {
            return redirect()->route('consultar_requisicion')->with('mensaje', 'Requisición no encontrada o sin acceso.');
        }

        $data = $this->requisicionRepository->find($id);
        $data->loadMissing([
            'requisicion_estados.usuarios',
            'requisicion_articulos.monedas',
            'requisicion_articulos.centrocostos_destino',
            'requisicion_presupuestos' => function ($q) {
                $q->orderByDesc('fecha')->orderByDesc('id');
            },
            'requisicion_presupuestos.proveedores',
            'requisicion_presupuestos.condicionentregas',
            'requisicion_presupuestos.condicioncompras',
            'requisicion_presupuestos.condicionpagos',
            'requisicion_presupuestos.requisicion_presupuesto_articulos.requisicion_articulo.articulos',
            'requisicion_presupuestos.requisicion_presupuesto_articulos.requisicion_articulo.monedas',
            'requisicion_presupuestos.requisicion_presupuesto_archivos',
        ]);

        RequisicionTotalesCabecera::aplicarAtributosVirtuales($data, app(CotizacionQueryInterface::class));

        $arbolMovimientos = $this->arbolaprobacion_movimientoRepository->findPorRequisicion((int) $id);

        $html = view('compras.requisicion.pdf', compact('data', 'arbolMovimientos'))->render();

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html);

        $nombreArchivo = 'Requisicion_'.preg_replace('/[^\w\-]+/', '_', (string) $data->numerorequisicion).'.pdf';

        return $pdf->download($nombreArchivo);
    }

    public function editar(Request $request, $id)
    {
        can('editar-requisicion');

        if (! $this->requisicionQuery->requisicionAccesiblePorUsuario((int) $id)) {
            return redirect()->route('consultar_requisicion')->with('mensaje', 'Requisición no encontrada o sin acceso.');
        }

        $data = $this->requisicionRepository->find($id);
        if (! $this->requisicionService->usuarioPuedeEditarRequisicionEnCompras($data)) {
            return redirect()->route('solo_consulta_requisicion', $id)
                ->with('mensaje', 'No puede modificar esta requisición en compras: su oficina de compra no coincide con la de la requisición.');
        }

        // Solo editable en PROVISORIO, PENDIENTE o EN COMPRAS (nombre exacto según enum, p. ej. "EN COMPRAS" con espacio)
        $nombrePendiente = Requisicion_Estado::$enumEstado[array_search('P', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
        $nombreEnCompras = Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
        $nombreAprobada = Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'), true)]['nombre'];
        $nombreProvisorio = RequisicionProvisorioSupport::nombreEstadoProvisorio();
        $edicionLimitadaAprobada = false;
        $estadoPermitido = (
            $data->estado === $nombrePendiente
            || $data->estado === $nombreEnCompras
            || $data->estado === $nombreProvisorio
        );
        // Compatibilidad registros viejos (p. ej. import) con guión bajo
        if (! $estadoPermitido && $data->estado === 'EN_COMPRAS') {
            $estadoPermitido = true;
        }
        // Aprobada: solo proveedor sugerido (p. ej. antes de generar OC desde el wizard)
        if (! $estadoPermitido && ($data->estado ?? '') === $nombreAprobada && can('actualizar-requisicion', false)) {
            $estadoPermitido = true;
            $edicionLimitadaAprobada = true;
        }
        if (! $estadoPermitido) {
            return redirect()->route('solo_consulta_requisicion', $id)
                ->with('mensaje', 'No puede modificar esta requisición por no estar pendiente o en compras.');
        }

        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $formapago_query = $this->formapagoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $oficinacompra_query = Oficinacompra::orderBy('nombre')->get();
        $proveedor_query = Proveedor::orderBy('nombre')->get();
        $condicionpago_query = Condicionpago::orderBy('nombre')->get();
        $condicionentrega_query = Condicionentrega::orderBy('nombre')->get();
        $condicioncompra_query = Condicioncompra::orderBy('nombre')->get();
        $estado_enum = Requisicion_Estado::$enumEstado;
        $estado_en_compras = Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
        $estado_en_arbol_aprobacion = Requisicion_Estado::$enumEstado[array_search('R', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
        $estado_aprobada_requisicion = $nombreAprobada;
        $tratamiento_enum = Requisicion::$enumTratamiento;
        $contratacionDirecta_enum = Requisicion::$enumContratacionDirecta;

        $acceso_visualizacion_por_hash = false;
        $visualizar = false;
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, RequisicionListadoFiltros::class);
        $datosOc = $this->datosOcRequisicion($data, $filtrosQuery);
        $tiene_ordencompra_asociada = $datosOc['tiene_ordencompra_asociada'];
        $ordenes_compra_vinculadas = $datosOc['ordenes_compra_vinculadas'];
        $requisicion_lineas_pendientes_oc = $datosOc['requisicion_lineas_pendientes_oc'];
        $puede_wizard_generar_multiples_oc = $datosOc['puede_wizard_generar_multiples_oc'];
        $requisicion_wizard_multiples_oc_url = $datosOc['requisicion_wizard_multiples_oc_url'];
        $es_provisorio = RequisicionProvisorioSupport::esEstadoProvisorio($data->estado ?? '');
        $estado_provisorio = $nombreProvisorio;
        $puede_confirmar_provisorio = $es_provisorio && can('confirmar-requisicion', false);
        $cambios_articulo = app(RequisicionArticuloCambioService::class)->listarPorRequisicion((int) $id);
        $color_query = Color::query()->orderBy('nombre')->get(['id', 'nombre']);
        $talle_query = Talle::query()->orderBy('nombre')->get(['id', 'nombre']);

        return view('compras.requisicion.editar', compact(
            'data',
            'empresa_query',
            'centrocosto_query',
            'formapago_query',
            'moneda_query',
            'oficinacompra_query',
            'proveedor_query',
            'condicionpago_query',
            'condicionentrega_query',
            'condicioncompra_query',
            'estado_enum',
            'estado_en_compras',
            'estado_en_arbol_aprobacion',
            'estado_aprobada_requisicion',
            'tratamiento_enum',
            'contratacionDirecta_enum',
            'acceso_visualizacion_por_hash',
            'visualizar',
            'tiene_ordencompra_asociada',
            'ordenes_compra_vinculadas',
            'requisicion_lineas_pendientes_oc',
            'puede_wizard_generar_multiples_oc',
            'requisicion_wizard_multiples_oc_url',
            'es_provisorio',
            'estado_provisorio',
            'puede_confirmar_provisorio',
            'edicionLimitadaAprobada',
            'filtrosQuery',
            'cambios_articulo',
            'color_query',
            'talle_query',
        ));
    }

    public function actualizar(ValidacionRequisicion $request, $id)
    {
        can('actualizar-requisicion');

        if (! $this->requisicionQuery->requisicionAccesiblePorUsuario((int) $id)) {
            return redirect()->route('consultar_requisicion')->with('mensaje', 'Requisición no encontrada o sin acceso.');
        }

        $ret = $this->requisicionService->actualizaRequisicion($request, $id);

        if (($ret['mensaje'] ?? '') === 'seleccionar_centrocosto') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json($ret);
            }

            return redirect()->back()->withInput()->with('mensaje', 'Debe seleccionar el centro de costo de destino para el árbol de aprobación.');
        }

        if ($ret['mensaje'] == 'ok') {
            $mensaje = ! empty($ret['solo_proveedor_aprobada'])
                ? 'Proveedor sugerido actualizado con éxito'
                : 'Requisición actualizada con éxito';
            if (! empty($ret['modo_provisorio']) || ! empty($ret['solo_proveedor_aprobada'])) {
                return redirect()->route('editar_requisicion', QueryRetornoListado::paramsRutaEditar(
                    $request,
                    RequisicionListadoFiltros::class,
                    (int) $id
                ))->with('mensaje', $mensaje);
            }

            return redirect()->route('consultar_requisicion', QueryRetornoListado::desdeRequest($request, RequisicionListadoFiltros::class))
                ->with('mensaje', $mensaje);
        } else {
            return redirect()->back()->withInput()->with('mensaje-error', $ret['errores'] ?? 'No se pudo actualizar la requisición.');
        }
    }

    public function confirmar(Request $request, int $id)
    {
        can('confirmar-requisicion');

        if (! $this->requisicionQuery->requisicionAccesiblePorUsuario($id)) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['mensaje' => 'error', 'errores' => 'Requisición no encontrada o sin acceso.'], 404);
            }

            return redirect()->route('consultar_requisicion')->with('mensaje', 'Requisición no encontrada o sin acceso.');
        }

        $centrocostoArbolId = (int) $request->input('centrocostodestino_arbol_id', 0);
        $centrocostoArbolId = $centrocostoArbolId > 0 ? $centrocostoArbolId : null;

        $ret = $this->requisicionService->confirmarRequisicion($id, $centrocostoArbolId);

        if (($ret['mensaje'] ?? '') === 'seleccionar_centrocosto') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json($ret);
            }

            return redirect()->route('editar_requisicion', QueryRetornoListado::paramsRutaEditar(
                $request,
                RequisicionListadoFiltros::class,
                $id
            ))->with('mensaje', 'Debe seleccionar el centro de costo de destino para el árbol de aprobación.');
        }

        if ($ret['mensaje'] === 'ok') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'mensaje' => 'ok',
                    'redirect' => route('editar_requisicion', QueryRetornoListado::paramsRutaEditar(
                        $request,
                        RequisicionListadoFiltros::class,
                        $id
                    )),
                ]);
            }

            return redirect()->route('editar_requisicion', QueryRetornoListado::paramsRutaEditar(
                $request,
                RequisicionListadoFiltros::class,
                $id
            ))->with('mensaje', 'Requisición confirmada. Árbol de aprobación y Anita actualizados.');
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($ret, 422);
        }

        return redirect()->route('editar_requisicion', QueryRetornoListado::paramsRutaEditar(
            $request,
            RequisicionListadoFiltros::class,
            $id
        ))->with('mensaje', $ret['errores'] ?? 'Error al confirmar la requisición.');
    }

    public function previewCentrocostoArbol(Request $request, int $id)
    {
        if (! can('editar-requisicion', false) && ! can('confirmar-requisicion', false) && ! can('crear-requisicion', false)) {
            return response()->json(['mensaje' => 'error', 'errores' => 'No tiene permisos.'], 403);
        }

        if (! $this->requisicionQuery->requisicionAccesiblePorUsuario($id)) {
            return response()->json(['mensaje' => 'error', 'errores' => 'Requisición no encontrada o sin acceso.'], 404);
        }

        return response()->json($this->requisicionService->previewCentrocostoArbol($id));
    }

    public function eliminarProvisorio(Request $request, int $id)
    {
        can('actualizar-requisicion');

        if (! $this->requisicionQuery->requisicionAccesiblePorUsuario((int) $id)) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ng', 'errores' => 'Requisición no encontrada o sin acceso.'], 403);
            }

            abort(403);
        }

        $ret = $this->requisicionService->eliminarProvisorio((int) $id);

        if ($ret['mensaje'] === 'ok') {
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ok']);
            }

            return redirect()->route('consultar_requisicion')->with('mensaje', 'Provisorio eliminado.');
        }

        if ($request->ajax()) {
            return response()->json(['mensaje' => 'ng', 'errores' => $ret['errores'] ?? 'No se pudo eliminar.'], 422);
        }

        return redirect()->back()->with('mensaje', $ret['errores'] ?? 'No se pudo eliminar el provisorio.');
    }

    public function firmantesRetomeArbol(Request $request, $id)
    {
        can('editar-requisicion');

        $centrocostoArbolId = (int) $request->input('centrocostodestino_arbol_id', 0);
        $centrocostoArbolId = $centrocostoArbolId > 0 ? $centrocostoArbolId : null;

        $ret = $this->requisicionService->firmantesRetomeArbol((int) $id, $centrocostoArbolId);
        if (($ret['mensaje'] ?? '') === 'ok') {
            return response()->json($ret);
        }

        return response()->json([
            'mensaje' => 'error',
            'errores' => $ret['errores'] ?? 'No se pudieron consultar los firmantes del árbol.',
        ], 422);
    }

    public function enviarArbolAprobacion(Request $request, $id)
    {
        can('editar-requisicion');

        $destinatarioId = (int) $request->input('destinatario_usuario_id', 0);
        $destinatarioId = $destinatarioId > 0 ? $destinatarioId : null;

        $centrocostoArbolId = (int) $request->input('centrocostodestino_arbol_id', 0);
        $centrocostoArbolId = $centrocostoArbolId > 0 ? $centrocostoArbolId : null;

        $ret = $this->requisicionService->enviarArbolAprobacionDesdeEnCompras((int) $id, $destinatarioId, $centrocostoArbolId);

        if ($ret['mensaje'] === 'ok') {
            $mensaje = 'Requisición enviada al árbol de aprobación; el circuito continúa con el siguiente nivel.';
        } elseif ($ret['mensaje'] === 'seleccionar_firmante') {
            $mensaje = 'Debe seleccionar un firmante para continuar el árbol de aprobación.';
        } elseif ($ret['mensaje'] === 'seleccionar_centrocosto') {
            $mensaje = 'Debe seleccionar el centro de costo de destino para continuar el árbol de aprobación.';
        } else {
            $mensaje = $ret['errores'] ?? 'No se pudo enviar al árbol de aprobación.';
        }

        $flashKey = $ret['mensaje'] === 'ok' ? 'mensaje' : 'mensaje_error';

        if ($request->ajax() || $request->wantsJson()) {
            if ($ret['mensaje'] === 'ok') {
                session()->flash('mensaje', $mensaje);

                return response()->json([
                    'mensaje' => 'ok',
                    'texto' => $mensaje,
                    'redirect' => $request->input('redirect_url') ?: url()->previous(),
                ]);
            }

            return response()->json(array_merge([
                'mensaje' => $ret['mensaje'],
                'errores' => $mensaje,
            ], array_filter([
                'firmantes' => $ret['firmantes'] ?? null,
                'nivel' => $ret['nivel'] ?? null,
                'centros_costo' => $ret['centros_costo'] ?? null,
                'centrocosto_arbol_id' => $ret['centrocosto_arbol_id'] ?? null,
            ], static fn ($v) => $v !== null)), 422);
        }

        return redirect()->back()->with($flashKey, $mensaje);
    }

    public function volverCompras(Request $request, int $id)
    {
        can('volver-compras-requisicion');

        if (! $this->requisicionQuery->requisicionAccesiblePorUsuario($id)) {
            return redirect()->route('consultar_requisicion')->with('mensaje', 'Requisición no encontrada o sin acceso.');
        }

        $ret = $this->requisicionService->volverAComprasDesdeArbol($id);

        if ($ret['mensaje'] === 'ok') {
            return redirect()->route('editar_requisicion', QueryRetornoListado::paramsRutaEditar(
                $request,
                RequisicionListadoFiltros::class,
                $id
            ))->with('mensaje', 'Requisición devuelta a compras. Las autorizaciones pendientes del árbol quedaron sin efecto; puede modificarla y volver a enviarla.');
        }

        return redirect()->back()->with('mensaje', $ret['errores'] ?? 'No se pudo devolver la requisición a compras.');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-requisicion');

        if (! $this->requisicionQuery->requisicionAccesiblePorUsuario((int) $id)) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ng', 'errores' => 'Requisición no encontrada o sin acceso.'], 403);
            }

            abort(403);
        }

        $ret = $this->requisicionService->eliminarRequisicion((int) $id);

        if ($request->ajax()) {
            if ($ret['mensaje'] === 'ok') {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json([
                'mensaje' => 'ng',
                'errores' => $ret['errores'] ?? 'No se pudo eliminar la requisición.',
            ], 422);
        }

        if ($ret['mensaje'] === 'ok') {
            return redirect()->route('consultar_requisicion')->with('mensaje', 'Requisición eliminada.');
        }

        return redirect()->back()->with('mensaje', $ret['errores'] ?? 'No se pudo eliminar la requisición.');
    }

    public function leerHistoriaRequisicion($requisicion_id)
    {
        if (! $this->requisicionQuery->requisicionAccesiblePorUsuario((int) $requisicion_id)) {
            return response()->json(['message' => 'Requisición no encontrada o sin acceso.'], 404);
        }

        return $this->requisicionService->leeHistoriaRequisicion($requisicion_id);
    }

    /**
     * Alias histórico: misma consulta que presupuesto/consulta_partidagasto (modal de líneas).
     */
    public function consultaPartidagastoRequisicion(Request $request)
    {
        $empresa_id = (int) $request->input('empresa_id', 0);
        $consulta = $request->input('consulta', '');
        $centrocostodestino_id = $request->input('centrocostodestino_id');
        $payload = $this->partidagastoRepository->consultaPartidagasto($consulta, $empresa_id, $centrocostodestino_id);

        return response()->json($payload);
    }

    /**
     * Precio y moneda de última compra (ERP COM → Anita stkm_pre_compra3 → artículo) para líneas de requisición.
     */
    public function precioUltimaCompraArticulo(Request $request)
    {
        if (! can('crear-requisicion', false)
            && ! can('editar-requisicion', false)
            && ! can('actualizar-requisicion', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        $skus = $request->input('skus', []);
        if (! is_array($skus)) {
            $skus = [$skus];
        }

        $datos = ArticuloPrecioUltimaCompraSupport::resolverDatosPorSkus($skus);

        return response()->json(['datos' => $datos]);
    }

    /**
     * Total de la requisición en moneda del primer ítem (misma lógica que listado y árbol de aprobación).
     */
    public function calcularTotales(Request $request)
    {
        if (! can('crear-requisicion', false)
            && ! can('editar-requisicion', false)
            && ! can('actualizar-requisicion', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        [$monto, $monedaId] = RequisicionTotalesCabecera::montoYMonedaDesdeRequest(
            $request->all(),
            $this->cotizacionQuery
        );
        $mon = Moneda::query()->find($monedaId);

        return response()->json([
            'total' => $monto,
            'moneda_id' => $monedaId,
            'moneda_abrev' => $mon ? (string) ($mon->abreviatura ?? '') : '',
        ]);
    }

    /**
     * Comprueba árbol de aprobación para alta (empresa) o edición en pendiente (requisición completa).
     */
    public function avisoArbolGrabacion(Request $request)
    {
        $requisicionId = (int) $request->query('requisicion_id', 0);
        $empresaId = (int) $request->query('empresa_id', 0);

        if ($requisicionId > 0) {
            can('editar-requisicion');
        } else {
            can('crear-requisicion');
        }

        if (RequisicionProvisorioSupport::usuarioUsaModoProvisorio() && $requisicionId <= 0) {
            return response()->json(['aviso' => null]);
        }

        $aviso = $this->arbolaprobacionService->avisoGrabacionRequisicionAjax($empresaId, $requisicionId);

        return response()->json(['aviso' => $aviso]);
    }

    /**
     * Listas de precio de compra (proveedor) para consulta desde una requisición.
     *
     * Modos de operación:
     *  - Modo artículo (articulo_id > 0): precio vigente a la fecha de referencia para ese artículo,
     *    sobre listas ACTIVAS. Si además llega proveedor_id, se filtran al proveedor.
     *  - Modo proveedor (sin articulo_id, con proveedor_id > 0): últimas listas vigentes del proveedor
     *    (estado ACTIVA), incluyendo todos los artículos con su precio vigente a la fecha de referencia.
     */
    public function consultaListasPrecioArticulo(Request $request)
    {
        if (! can('listar-requisicion', false) && ! can('editar-requisicion', false) && ! can('crear-requisicion', false)) {
            return response()->json(['message' => 'No tiene permisos para esta consulta.'], 403);
        }

        $articuloId = (int) $request->query('articulo_id', 0);

        $proveedorId = $request->query('proveedor_id');
        $proveedorId = ($proveedorId !== null && $proveedorId !== '' && (int) $proveedorId > 0)
            ? (int) $proveedorId
            : null;

        if ($articuloId <= 0 && $proveedorId === null) {
            return response()->json([
                'message' => 'Indique un artículo o un proveedor para consultar listas de precios.',
            ], 422);
        }

        $articulo = null;
        if ($articuloId > 0) {
            $articulo = Articulo::query()->select('id', 'sku', 'descripcion')->find($articuloId);
            if (! $articulo) {
                return response()->json(['message' => 'Artículo no encontrado.'], 404);
            }
        }

        $proveedor = null;
        if ($proveedorId !== null) {
            $proveedor = DB::table('proveedor')
                ->select('id', 'codigo', 'nombre', 'fantasia')
                ->where('id', $proveedorId)
                ->first();
            if (! $proveedor) {
                return response()->json(['message' => 'Proveedor no encontrado.'], 404);
            }
        }

        $fechaRef = $request->query('fecha_referencia');
        if (! is_string($fechaRef) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaRef)) {
            $fechaRef = date('Y-m-d');
        }

        $modoArticulo = $articuloId > 0;
        $modoProveedor = ! $modoArticulo && $proveedorId !== null;
        $filtradoPorProveedor = $modoArticulo && $proveedorId !== null;

        if ($modoArticulo) {
            $subMaxFv = DB::table('listaprecio_proveedor_articulo')
                ->select('listaprecio_proveedor_id', DB::raw('MAX(fechavigencia) as max_fv'))
                ->where('articulo_id', $articuloId)
                ->whereDate('fechavigencia', '<=', $fechaRef)
                ->groupBy('listaprecio_proveedor_id');

            $lineIds = DB::table('listaprecio_proveedor_articulo as lpa')
                ->joinSub($subMaxFv, 'mx', function ($join) {
                    $join->on('lpa.listaprecio_proveedor_id', '=', 'mx.listaprecio_proveedor_id')
                        ->on('lpa.fechavigencia', '=', 'mx.max_fv');
                })
                ->where('lpa.articulo_id', $articuloId)
                ->groupBy('lpa.listaprecio_proveedor_id')
                ->select(DB::raw('MAX(lpa.id) as id'))
                ->pluck('id')
                ->filter()
                ->values()
                ->all();
        } else {
            // Modo proveedor: para cada lista ACTIVA del proveedor, la última fechavigencia <= fechaRef por (lista, articulo)
            $subMaxFv = DB::table('listaprecio_proveedor_articulo as lpa2')
                ->join('listaprecio_proveedor as lp2', 'lp2.id', '=', 'lpa2.listaprecio_proveedor_id')
                ->select('lpa2.listaprecio_proveedor_id', 'lpa2.articulo_id', DB::raw('MAX(lpa2.fechavigencia) as max_fv'))
                ->where('lp2.estado', 'ACTIVA')
                ->where('lp2.proveedor_id', $proveedorId)
                ->whereDate('lpa2.fechavigencia', '<=', $fechaRef)
                ->groupBy('lpa2.listaprecio_proveedor_id', 'lpa2.articulo_id');

            $lineIds = DB::table('listaprecio_proveedor_articulo as lpa')
                ->joinSub($subMaxFv, 'mx', function ($join) {
                    $join->on('lpa.listaprecio_proveedor_id', '=', 'mx.listaprecio_proveedor_id')
                        ->on('lpa.articulo_id', '=', 'mx.articulo_id')
                        ->on('lpa.fechavigencia', '=', 'mx.max_fv');
                })
                ->groupBy('lpa.listaprecio_proveedor_id', 'lpa.articulo_id')
                ->select(DB::raw('MAX(lpa.id) as id'))
                ->pluck('id')
                ->filter()
                ->values()
                ->all();
        }

        $respuestaBase = [
            'articulo' => $articulo ? [
                'sku' => $articulo->sku,
                'descripcion' => $articulo->descripcion,
            ] : null,
            'proveedor' => $proveedor ? [
                'codigo' => $proveedor->codigo,
                'nombre' => $proveedor->nombre,
                'fantasia' => $proveedor->fantasia,
            ] : null,
            'fecha_referencia' => $fechaRef,
            'modo_proveedor' => $modoProveedor,
            'filtrado_por_proveedor' => $filtradoPorProveedor,
        ];

        if ($lineIds === []) {
            return response()->json(array_merge($respuestaBase, ['filas' => []]));
        }

        $q = DB::table('listaprecio_proveedor_articulo as lpa')
            ->join('listaprecio_proveedor as lp', 'lp.id', '=', 'lpa.listaprecio_proveedor_id')
            ->leftJoin('proveedor as prov', 'prov.id', '=', 'lp.proveedor_id')
            ->leftJoin('moneda as mon', 'mon.id', '=', 'lp.moneda_id')
            ->leftJoin('condicionpago as cp', 'cp.id', '=', 'lp.condicionpago_id')
            ->leftJoin('condicionentrega as ce', 'ce.id', '=', 'lp.condicionentrega_id')
            ->leftJoin('condicioncompra as ccom', 'ccom.id', '=', 'lp.condicioncompra_id')
            ->leftJoin('usuario as ulista', 'ulista.id', '=', 'lp.creousuario_id')
            ->leftJoin('usuario as ulinea', 'ulinea.id', '=', 'lpa.usuarioultcambio_id')
            ->leftJoin('articulo as art', 'art.id', '=', 'lpa.articulo_id')
            ->whereIn('lpa.id', $lineIds)
            ->where('lp.estado', 'ACTIVA');

        if ($modoArticulo && $filtradoPorProveedor) {
            $q->where('lp.proveedor_id', $proveedorId);
        }
        if ($modoProveedor) {
            $q->where('lp.proveedor_id', $proveedorId);
        }

        $q->select([
            'lp.id as lista_id',
            'lp.nombre as lista_nombre',
            'lp.fecha as lista_fecha',
            'lp.estado as lista_estado',
            'lp.observaciones as lista_observaciones',
            'lp.created_at as lista_created_at',
            'lp.updated_at as lista_updated_at',
            'prov.codigo as proveedor_codigo',
            'prov.nombre as proveedor_nombre',
            'prov.fantasia as proveedor_fantasia',
            'mon.abreviatura as moneda_abreviatura',
            'mon.nombre as moneda_nombre',
            'mon.codigo as moneda_codigo',
            'lpa.articulo_id',
            'art.sku as articulo_sku',
            'art.descripcion as articulo_descripcion',
            'lpa.precio',
            'lpa.descuento',
            'lpa.codigo_articulo_proveedor',
            'lpa.fechavigencia as linea_fechavigencia',
            'cp.nombre as condicion_pago',
            'ce.nombre as condicion_entrega',
            'ccom.nombre as condicion_compra',
            'ulista.nombre as lista_creador',
            'ulinea.nombre as linea_ultimo_usuario',
        ]);

        if ($modoProveedor) {
            // En modo proveedor priorizamos las listas más recientes y luego artículo
            $q->orderByDesc('lp.fecha')->orderByDesc('lp.id')->orderBy('art.sku');
        } elseif ($filtradoPorProveedor) {
            $q->orderByDesc('lp.fecha')->orderByDesc('lp.id');
        } else {
            $q->orderBy('lpa.precio')->orderBy('prov.nombre')->orderBy('lp.nombre');
        }

        $rows = $q->get();

        $filas = [];
        foreach ($rows as $r) {
            $precio = (float) $r->precio;
            $dto = (float) $r->descuento;
            $factor = max(0.0, 1 - ($dto / 100.0));
            $precioNeto = round($precio * $factor, 6);

            $filas[] = [
                'proveedor_codigo' => $r->proveedor_codigo,
                'proveedor_nombre' => $r->proveedor_nombre,
                'proveedor_fantasia' => $r->proveedor_fantasia,
                'lista_id' => $r->lista_id,
                'lista_nombre' => $r->lista_nombre,
                'lista_fecha' => $r->lista_fecha ? substr((string) $r->lista_fecha, 0, 10) : '',
                'lista_estado' => $r->lista_estado,
                'lista_observaciones' => $r->lista_observaciones,
                'lista_created_at' => $r->lista_created_at ? substr((string) $r->lista_created_at, 0, 19) : '',
                'lista_updated_at' => $r->lista_updated_at ? substr((string) $r->lista_updated_at, 0, 19) : '',
                'moneda_abreviatura' => $r->moneda_abreviatura,
                'moneda_nombre' => $r->moneda_nombre,
                'moneda_codigo' => $r->moneda_codigo,
                'articulo_id' => $r->articulo_id,
                'articulo_sku' => $r->articulo_sku,
                'articulo_descripcion' => $r->articulo_descripcion,
                'precio' => $r->precio,
                'precio_neto_descuento' => $precioNeto,
                'descuento' => $r->descuento,
                'codigo_articulo_proveedor' => $r->codigo_articulo_proveedor,
                'articulo_proveedor' => $r->codigo_articulo_proveedor,
                'linea_fechavigencia' => $r->linea_fechavigencia ? substr((string) $r->linea_fechavigencia, 0, 10) : '',
                'condicion_pago' => $r->condicion_pago,
                'condicion_entrega' => $r->condicion_entrega,
                'condicion_compra' => $r->condicion_compra,
                'lista_creador' => $r->lista_creador,
                'linea_ultimo_usuario' => $r->linea_ultimo_usuario,
            ];
        }

        return response()->json(array_merge($respuestaBase, ['filas' => $filas]));
    }

    /**
     * Descarga o sirve en línea (preview) un archivo adjunto de la requisición.
     * Acceso: permisos de consulta/edición de requisiciones, o hash de visualización del árbol (como en visualizar).
     */
    public function descargarArchivo(Request $request, int $id, int $archivo)
    {
        $hash = $request->query('hash');
        if (filled($hash)) {
            $flEncontro = false;
            foreach ($this->arbolaprobacion_movimientoRepository->findPorRequisicion($id) as $movimiento) {
                if ($movimiento->hashvisualizar == $hash) {
                    $flEncontro = true;
                    break;
                }
            }
            if (! $flEncontro) {
                abort(403);
            }
        } elseif (! can('listar-requisicion', false) && ! can('editar-requisicion', false) && ! can('crear-requisicion', false)) {
            abort(403);
        }

        $registro = Requisicion_Archivo::query()
            ->where('id', $archivo)
            ->where('requisicion_id', $id)
            ->first();
        if (! $registro) {
            abort(404);
        }

        $basename = basename((string) $registro->nombrearchivo);
        if ($basename === '' || str_contains($registro->nombrearchivo, '..')) {
            abort(404);
        }

        $path = public_path('storage/archivos/requisiciones/'.$id.'/'.$basename);
        if (! is_file($path)) {
            abort(404);
        }

        if ($request->boolean('inline')) {
            return response()->file($path);
        }

        return response()->download($path, $basename);
    }

    public function soloConsulta(Request $request, $id)
    {
        return $this->visualizar($id, null, $request);
    }

    public function visualizar($id, $hash = null, ?Request $request = null)
    {
        $request = $request ?? request();
        $aprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorRequisicion($id);

        if ($hash) {
            $flEncontro = false;
            foreach ($aprobacion_movimiento as $movimiento) {
                if ($movimiento->hashvisualizar == $hash) {
                    $flEncontro = true;
                    break;
                }
            }
        } else {
            $flEncontro = true;
        }

        if ($flEncontro) {
            if (! $hash && ! $this->requisicionQuery->requisicionAccesiblePorUsuario((int) $id)) {
                return redirect()->route('consultar_requisicion')->with('mensaje', 'Requisición no encontrada o sin acceso.');
            }

            $data = $this->requisicionRepository->find($id);
            $empresa_query = $this->empresaRepository->allFiltrado();
            $centrocosto_query = $this->centrocostoRepository->all();
            $formapago_query = $this->formapagoRepository->all();
            $moneda_query = $this->monedaRepository->all();
            $oficinacompra_query = Oficinacompra::orderBy('nombre')->get();
            $proveedor_query = Proveedor::orderBy('nombre')->get();
            $estado_enum = Requisicion_Estado::$enumEstado;
            $estado_en_compras = Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
            $estado_en_arbol_aprobacion = Requisicion_Estado::$enumEstado[array_search('R', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
            $estado_aprobada_requisicion = Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'), true)]['nombre'];
            $tratamiento_enum = Requisicion::$enumTratamiento;
            $contratacionDirecta_enum = Requisicion::$enumContratacionDirecta;
            $visualizar = true;
            $acceso_visualizacion_por_hash = filled($hash);
            $filtrosQuery = QueryRetornoListado::desdeRequest($request, RequisicionListadoFiltros::class);
            $datosOc = $this->datosOcRequisicion($data, $filtrosQuery);
            $tiene_ordencompra_asociada = $datosOc['tiene_ordencompra_asociada'];
            $ordenes_compra_vinculadas = $datosOc['ordenes_compra_vinculadas'];
            $requisicion_lineas_pendientes_oc = $datosOc['requisicion_lineas_pendientes_oc'];
            $puede_wizard_generar_multiples_oc = $datosOc['puede_wizard_generar_multiples_oc'];
            $requisicion_wizard_multiples_oc_url = $datosOc['requisicion_wizard_multiples_oc_url'];
            $cambios_articulo = app(RequisicionArticuloCambioService::class)->listarPorRequisicion((int) $id);
            $color_query = Color::query()->orderBy('nombre')->get(['id', 'nombre']);
            $talle_query = Talle::query()->orderBy('nombre')->get(['id', 'nombre']);

            return view('compras.requisicion.editar', compact(
                'data',
                'empresa_query',
                'centrocosto_query',
                'formapago_query',
                'moneda_query',
                'oficinacompra_query',
                'proveedor_query',
                'estado_enum',
                'estado_en_compras',
                'estado_en_arbol_aprobacion',
                'estado_aprobada_requisicion',
                'tratamiento_enum',
                'contratacionDirecta_enum',
                'visualizar',
                'acceso_visualizacion_por_hash',
                'tiene_ordencompra_asociada',
                'ordenes_compra_vinculadas',
                'requisicion_lineas_pendientes_oc',
                'puede_wizard_generar_multiples_oc',
                'requisicion_wizard_multiples_oc_url',
                'filtrosQuery',
                'cambios_articulo',
                'color_query',
                'talle_query',
            ));
        }

        return redirect()->route('inicio')->with('mensaje', 'No tienes permisos para visualizar la requisición')->send();
    }

    private function tieneOrdencompraAsociadaRequisicion(int $requisicionId): bool
    {
        return $this->requisicionService->tieneOrdencompraAsociada($requisicionId);
    }

    /**
     * @return array{
     *     tiene_ordencompra_asociada: bool,
     *     ordenes_compra_vinculadas: list<array<string, mixed>>,
     *     requisicion_lineas_pendientes_oc: int,
     *     puede_wizard_generar_multiples_oc: bool,
     *     requisicion_wizard_multiples_oc_url: ?string
     * }
     */
    private function datosOcRequisicion(Requisicion $data, array $retornoQuery = []): array
    {
        app(OrdencompraGestionService::class)->sincronizarEstadoRequisicionSegunLineasOc(
            (int) $data->id,
            (int) auth()->id()
        );
        $data->refresh();

        $tiene = $this->tieneOrdencompraAsociadaRequisicion((int) $data->id);
        $pendientes = RequisicionLineasOcSupport::cuentaPendientesOc((int) $data->id);
        $puedeWizard = $this->requisicionQuery->puedeUsuarioGenerarMultiplesOcDesdeRequisicion($data);

        return [
            'tiene_ordencompra_asociada' => $tiene,
            'ordenes_compra_vinculadas' => $tiene ? $this->ordenesCompraVinculadasFilas((int) $data->id) : [],
            'requisicion_lineas_pendientes_oc' => $pendientes,
            'puede_wizard_generar_multiples_oc' => $puedeWizard,
            'requisicion_wizard_multiples_oc_url' => $puedeWizard
                ? route('requisicion_wizard_multiples_oc', array_merge(['id' => $data->id], $retornoQuery))
                : null,
        ];
    }
}
