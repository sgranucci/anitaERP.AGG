<?php

namespace App\Http\Controllers\Solicitudpago;

use App\Exports\Solicitudpago\SolicitudpagoListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionSolicitudpago;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Retencionganancia;
use App\Models\Compras\Retencioniva;
use App\Models\Compras\Retencionsuss;
use App\Models\Configuracion\Impuesto;
use App\Models\Contable\Centrocosto;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Solicitudpago\FormapagosolRepositoryInterface;
use App\Repositories\Solicitudpago\Sector_SolicitudpagoRepositoryInterface;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Services\Solicitudpago\SolicitudpagoArchivosFusionService;
use App\Services\Solicitudpago\SolicitudpagoArbolIntegracionService;
use App\Services\Solicitudpago\SolicitudpagoCargaMasivaCsvService;
use App\Services\Solicitudpago\SolicitudpagoComprobantePdfService;
use App\Services\Solicitudpago\SolicitudpagoPaqueteMailPdfService;
use App\Services\Caja\IngresoEgresoAnularRevertirService;
use App\Support\Archivos\ArchivoAdjuntoCacheSupport;
use App\Support\Configuracion\ArbolAprobacionEnlaceSupport;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoDisenadorPreviewSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoLienzoSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoVistaMenuSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Listado\ListadoVisualSupport;
use App\Support\Solicitudpago\SolicitudpagoArchivoStorageSupport;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoListadoColumnas;
use App\Support\Solicitudpago\SolicitudpagoListadoFiltros;
use App\Support\Solicitudpago\SolicitudpagoListadoPreferenciasUsuario;
use App\Support\Solicitudpago\SolicitudpagoRetencionCalculoSupport;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use App\Support\Solicitudpago\SolicitudpagoVisibilidadSupport;
use Illuminate\Http\Request;
use PDF;

class SolicitudpagoController extends Controller
{
    private const SESSION_FILTROS = 'solicitudpago_listado_filtros';

    public function __construct(
        private SolicitudpagoRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
        private Sector_SolicitudpagoRepositoryInterface $sectorRepository,
        private FormapagosolRepositoryInterface $formapagosolRepository,
        private MonedaRepositoryInterface $monedaRepository,
        private CentrocostoRepositoryInterface $centrocostoRepository,
        private SolicitudpagoArbolIntegracionService $arbolIntegracionService,
        private SolicitudpagoArchivosFusionService $archivosFusionService,
        private SolicitudpagoComprobantePdfService $comprobantePdfService,
        private SolicitudpagoPaqueteMailPdfService $paqueteMailPdfService,
        private SolicitudpagoCargaMasivaCsvService $cargaMasivaCsvService,
        private IngresoEgresoAnularRevertirService $ingresoEgresoAnularRevertirService,
    ) {
    }

    public function previewCargaMasiva(Request $request)
    {
        can('crear-solicitud-pago');
        $request->validate([
            'archivo' => [
                'required',
                'file',
                'max:10240',
                function (string $attribute, $value, $fail) {
                    if (! $value instanceof \Illuminate\Http\UploadedFile) {
                        $fail('Archivo inválido.');

                        return;
                    }
                    $ext = strtolower($value->getClientOriginalExtension() ?: '');
                    if (! in_array($ext, ['csv', 'txt'], true)) {
                        $fail('El archivo debe ser CSV.');
                    }
                },
            ],
        ], [
            'archivo.required' => 'Seleccione un archivo CSV.',
        ]);

        try {
            $preview = $this->cargaMasivaCsvService->preview($request->file('archivo'));

            return response()->json(['ok' => true] + $preview);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function calcularRetenciones(Request $request)
    {
        if (! can('crear-solicitud-pago', false)
            && ! can('editar-solicitud-pago', false)
            && ! can('actualizar-solicitud-pago', false)
        ) {
            return response()->json(['ok' => false, 'message' => 'Sin permiso'], 403);
        }

        $monto = $this->numeroRetencionPedido($request->input('monto'));
        $neto = $request->filled('retencion_neto_gravado')
            ? $this->numeroRetencionPedido($request->input('retencion_neto_gravado'))
            : null;
        $tasa = $request->filled('retencion_tasa_iva')
            ? $this->numeroRetencionPedido($request->input('retencion_tasa_iva'))
            : null;

        try {
            $presentacion = SolicitudpagoRetencionCalculoSupport::calcularDesdePedido(
                (int) $request->input('proveedor_id'),
                (int) $request->input('empresa_id'),
                $request->input('fecha') ?: null,
                $monto ?? 0.0,
                (string) $request->input('retencion_modo', ''),
                $neto,
                $tasa,
                SolicitudpagoRetencionCalculoSupport::codigosPedidos($request->only([
                    'retencionganancia_id', 'retencioniva_id', 'retencionsuss_id',
                ])),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $presentacion);
    }

    public function codigosRetencionProveedor(Request $request)
    {
        if (! can('crear-solicitud-pago', false)
            && ! can('editar-solicitud-pago', false)
            && ! can('actualizar-solicitud-pago', false)
        ) {
            return response()->json(['ok' => false, 'message' => 'Sin permiso'], 403);
        }

        $proveedor = Proveedor::query()->find((int) $request->input('proveedor_id'));
        if ($proveedor === null) {
            return response()->json(['ok' => false, 'message' => 'No se encontró el proveedor.'], 404);
        }

        return response()->json([
            'ok' => true,
            'retencionganancia_id' => $proveedor->retencionganancia_id ? (int) $proveedor->retencionganancia_id : null,
            'retencioniva_id' => $proveedor->retencioniva_id ? (int) $proveedor->retencioniva_id : null,
            'retencionsuss_id' => $proveedor->retencionsuss_id ? (int) $proveedor->retencionsuss_id : null,
        ]);
    }

    private function numeroRetencionPedido(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_int($valor) || is_float($valor)) {
            return round((float) $valor, 2);
        }
        $t = trim(str_replace(' ', '', (string) $valor));
        if ($t === '') {
            return null;
        }
        if (str_contains($t, ',')) {
            $t = str_replace('.', '', $t);
            $t = str_replace(',', '.', $t);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $t)) {
            $t = str_replace('.', '', $t);
        }
        if (! is_numeric($t)) {
            return null;
        }

        return round((float) $t, 2);
    }

    public function confirmarCargaMasiva(Request $request)
    {
        can('crear-solicitud-pago');
        $request->validate([
            'token' => 'required|string|max:64',
        ]);

        try {
            $resultado = $this->cargaMasivaCsvService->confirmar((string) $request->input('token'));

            return response()->json(['ok' => true] + $resultado);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function index(Request $request)
    {
        can('listar-solicitud-pago');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $empresaDefault = $empresaDefault ? (int) $empresaDefault : null;

        // Limpiar filtros de texto: borra recuerdo de sesión y conserva empresa externa
        if ($request->boolean('limpiar_filtros')) {
            session()->forget(self::SESSION_FILTROS);
            $filtrosEmpresa = SolicitudpagoListadoFiltros::resolverDesdeRequest($request, null, $empresaDefault);

            return redirect()->route(
                'consultar_solicitudpago',
                SolicitudpagoListadoFiltros::paraQueryStringEmpresa($filtrosEmpresa)
            );
        }

        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $vistas = ListadoVistaSupport::listarParaUsuario(SolicitudpagoListadoColumnas::RECURSO, $usuarioId);
        $forzarEstandar = $request->boolean('vista_estandar') || $request->input('vista_modo') === 'estandar';
        $vistaActiva = null;
        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                SolicitudpagoListadoColumnas::RECURSO,
                $usuarioId
            );
        }

        // Memoria de filtros: con query se persiste; sin query se restaura el último filtro
        if ($this->requestTraeFiltrosListado($request)) {
            $filtros = $this->filtrosWorkbench($request, $empresaDefault, $vistaActiva);
            $filtrosQuery = $this->queryWorkbench($filtros, $vistaActiva, $forzarEstandar);
            $page = (int) $request->input('page', 0);
            if ($page > 1) {
                $filtrosQuery['page'] = $page;
            }
            if (
                SolicitudpagoListadoFiltros::tieneCriteriosAplicados($filtros)
                || SolicitudpagoListadoFiltros::tieneAlcanceMiCentrocosto($filtros)
                || SolicitudpagoListadoFiltros::paraQueryStringEmpresa($filtros) !== []
                || $page > 1
                || $vistaActiva
            ) {
                session([self::SESSION_FILTROS => $filtrosQuery]);
            } else {
                session()->forget(self::SESSION_FILTROS);
            }
        } else {
            $guardados = session(self::SESSION_FILTROS, []);
            if (is_array($guardados) && $guardados !== [] && ! $forzarEstandar) {
                return redirect()->route('consultar_solicitudpago', $guardados);
            }
            if (! $forzarEstandar) {
                $vistaActiva = ListadoVistaSupport::defaultDelUsuario(SolicitudpagoListadoColumnas::RECURSO, $usuarioId);
            }
            $filtros = $this->filtrosWorkbench($request, $empresaDefault, $vistaActiva);
            $filtrosQuery = $this->queryWorkbench($filtros, $vistaActiva, $forzarEstandar);
        }

        $camposFiltro = SolicitudpagoListadoFiltros::camposQbeDisponibles();
        $catalogo = SolicitudpagoListadoColumnas::catalogoActivo();
        $etiquetasInstalacion = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            SolicitudpagoListadoColumnas::RECURSO,
            $catalogo
        );
        if ($vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== []) {
            $grillaLayout = SolicitudpagoListadoPreferenciasUsuario::normalizarLayout($vistaActiva->columnas_json);
        } else {
            $grillaLayout = SolicitudpagoListadoPreferenciasUsuario::grillaEstandar();
        }
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);
        foreach ($camposFiltro as $key => $meta) {
            $camposFiltro[$key]['label'] = $etiquetas[$key] ?? $etiquetasInstalacion[$key] ?? $meta['label'];
        }
        $filtrosQuery['columnas'] = implode(',', $columnasVisibles);
        $coleccion = $this->repository->leeSolicitudpago($filtros, true);
        $agruparActivo = ListadoAgrupacionSupport::normalizar(
            $filtros['agrupar'] ?? [],
            SolicitudpagoListadoFiltros::camposOrdenables()
        );
        $cortes = $agruparActivo !== []
            ? $this->repository->cortesSolicitudpago($filtros)
            : ['activo' => false];
        $graficoSeries = ListadoVisualSupport::seriesDeConsulta(
            $filtros,
            SolicitudpagoListadoFiltros::camposOrdenables(),
            $etiquetas,
            fn (array $para): array => $this->repository->cortesSolicitudpago($para)
        );
        $estado_enum = SolicitudpagoEstados::opciones();
        $tratamiento_enum = SolicitudpagoTratamientos::opciones();
        $limpiarFiltrosUrl = route(
            'consultar_solicitudpago',
            array_merge(SolicitudpagoListadoFiltros::paraQueryStringEmpresa($filtros), ['limpiar_filtros' => 1])
        );
        $puedeVerTodas = SolicitudpagoVisibilidadSupport::puedeVerTodasSinRestriccion();
        $alcanceListado = $filtros['alcance'] ?? SolicitudpagoVisibilidadSupport::ALCANCE_TODAS;
        $alcanceToggleUrl = null;
        if ($puedeVerTodas) {
            $paramsToggle = $filtrosQuery;
            unset($paramsToggle['page']);
            // alcance=todas debe ir explícito: si se omite, la sesión restaura mi_cc.
            $paramsToggle['alcance'] = $alcanceListado === SolicitudpagoVisibilidadSupport::ALCANCE_MI_CC
                ? SolicitudpagoVisibilidadSupport::ALCANCE_TODAS
                : SolicitudpagoVisibilidadSupport::ALCANCE_MI_CC;
            $alcanceToggleUrl = route('consultar_solicitudpago', $paramsToggle);
        }
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('solicitudpago.solicitudpago.index', compact(
            'coleccion',
            'filtros',
            'filtrosQuery',
            'camposFiltro',
            'estado_enum',
            'tratamiento_enum',
            'limpiarFiltrosUrl',
            'puedeVerTodas',
            'alcanceListado',
            'alcanceToggleUrl',
            'empresa_query',
            'columnasVisibles',
            'grillaLayout',
            'catalogo',
            'etiquetas',
            'etiquetasInstalacion',
            'vistas',
            'vistaActiva',
            'cortes',
            'graficoSeries'
        ) + [
            'catalogoColumnas' => $catalogo,
            'etiquetasColumnas' => $etiquetas,
            'vistasListado' => $vistas,
            'workbenchListo' => ListadoVistaSupport::tablasDisponibles(),
            'graficoSerie' => $graficoSeries[0] ?? ['labels' => [], 'series' => [], 'tipo' => '', 'titulo' => ''],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosWorkbench(Request $request, ?int $empresaDefault, mixed $vistaActiva): array
    {
        $filtros = ListadoVistaSupport::prepararQbeContraVista(
            SolicitudpagoListadoFiltros::resolverDesdeRequest($request, null, $empresaDefault),
            $request
        );
        if (
            $vistaActiva
            && is_array($vistaActiva->filtros_json)
            && ! $request->has('empresa_id')
            && ! $request->has('empresa_todas')
            && ! $request->boolean('empresa_todas')
            && $request->input('empresa_scope') !== 'todas'
        ) {
            $filtros['empresa_id'] = null;
            $filtros['empresa_scope'] = 'una';
        }
        if ($vistaActiva && is_array($vistaActiva->filtros_json)) {
            $filtros = SolicitudpagoListadoFiltros::fusionarDesdeVista($filtros, $vistaActiva->filtros_json);
        }
        unset($filtros['_qbe_explicito']);
        if (! SolicitudpagoVisibilidadSupport::puedeVerTodasSinRestriccion()) {
            $filtros['alcance'] = SolicitudpagoVisibilidadSupport::ALCANCE_TODAS;
        }
        $filtros = ListadoVisualSupport::aplicarPedido(
            $filtros,
            $request,
            ($vistaActiva && is_array($vistaActiva->filtros_json)) ? $vistaActiva->filtros_json : null,
            SolicitudpagoListadoFiltros::camposOrdenables()
        );
        if ($request->exists('calculadas')) {
            $filtros['calculadas'] = SolicitudpagoListadoFiltros::normalizarCalculadas($request->input('calculadas'));
        } elseif ($vistaActiva && is_array($vistaActiva->filtros_json)) {
            $filtros['calculadas'] = SolicitudpagoListadoFiltros::normalizarCalculadas($vistaActiva->filtros_json['calculadas'] ?? $filtros['calculadas'] ?? []);
        }
        ListadoVistaSupport::recordarQbeSiEnvio($vistaActiva, $request, $filtros);
        if ($vistaActiva && ($request->exists('group') || $request->exists('sort'))) {
            ListadoVistaSupport::recordarOrdenYAgrupar(
                $vistaActiva,
                $filtros['orden'] ?? [],
                $filtros['agrupar'] ?? []
            );
        }
        if (($filtros['empresa_scope'] ?? 'una') !== 'todas' && empty($filtros['empresa_id']) && $empresaDefault) {
            $filtros['empresa_id'] = $empresaDefault;
            $filtros['empresa_scope'] = 'una';
        }

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function queryWorkbench(array $filtros, mixed $vistaActiva, bool $forzarEstandar): array
    {
        $filtrosQuery = array_merge(
            SolicitudpagoListadoFiltros::paraQueryString($filtros),
            ListadoVisualSupport::paraQueryString($filtros)
        );
        if ($vistaActiva) {
            $filtrosQuery['vista_id'] = $vistaActiva->id;
        } elseif ($forzarEstandar) {
            $filtrosQuery['vista_estandar'] = 1;
        }

        return $filtrosQuery;
    }

    /**
     * true si la request trae parámetros de filtro/paginación del listado.
     */
    private function requestTraeFiltrosListado(Request $request): bool
    {
        foreach ([
            'filtro_valor',
            'filtro_campo',
            'filtro_operador',
            'filtro_busqueda_rapida',
            'madre_hija',
            'estado',
            'tratamiento',
            'fecha_desde',
            'fecha_hasta',
            'alcance',
            'empresa_id',
            'empresa_todas',
            'empresa_scope',
            'page',
            'qbe',
            'sort',
            'group',
            'filtro_modo',
            'vista_id',
            'vista_estandar',
            'graficos',
            'grafico',
            'grafico_click',
            'grafico_off',
            'formato',
            'calculadas',
            'aplicar_qbe',
            'quitar_qbe',
            'quitar_orden',
            'columnas',
        ] as $key) {
            if ($request->query->has($key)) {
                return true;
            }
        }

        return false;
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-solicitud-pago');
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '300');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = SolicitudpagoListadoFiltros::resolverDesdeRequest(
            $request,
            $busqueda,
            $empresaDefault ? (int) $empresaDefault : null
        );
        if (! SolicitudpagoVisibilidadSupport::puedeVerTodasSinRestriccion()) {
            $filtros['alcance'] = SolicitudpagoVisibilidadSupport::ALCANCE_TODAS;
        }
        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $datas = $this->repository->leeSolicitudpago($filtros, false);
                $titulo = 'Solicitudes de pago';
                $pdf = PDF::loadView('solicitudpago.solicitudpago.listado', compact('datas', 'titulo', 'filtros'));
                $pdf->setPaper('legal', 'landscape');
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $pdf->save($path.'/listado_solicitudpago.pdf');

                return response()->download($path.'/listado_solicitudpago.pdf');
            case 'EXCEL':
                return (new SolicitudpagoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('solicitudpago.xlsx');
            case 'CSV':
                return (new SolicitudpagoListadoExport($this->repository))
                    ->parametros($filtros, true)
                    ->download('solicitudpago.csv', \Maatwebsite\Excel\Excel::CSV);
            default:
                return redirect()->route('consultar_solicitudpago', SolicitudpagoListadoFiltros::paraQueryString($filtros));
        }
    }

    public function previewWorkbench(Request $request)
    {
        can('listar-solicitud-pago');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = SolicitudpagoListadoFiltros::resolverDesdeRequest(
            $request,
            null,
            $empresaDefault ? (int) $empresaDefault : null
        );
        $filtros['_per_page'] = ListadoDisenadorPreviewSupport::LIMITE_MUESTRA;
        $layout = SolicitudpagoListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
        $page = $this->repository->leeSolicitudpago($filtros, true);
        $total = method_exists($page, 'total') ? (int) $page->total() : $page->count();
        $filas = method_exists($page, 'getCollection') ? $page->getCollection() : $page;
        $orden = ListadoOrdenamientoSupport::normalizar(
            $request->input('sort', $filtros['orden'] ?? []),
            SolicitudpagoListadoFiltros::camposOrdenables()
        );
        $agrupar = ListadoAgrupacionSupport::normalizar(
            $request->input('group', $filtros['agrupar'] ?? []),
            SolicitudpagoListadoFiltros::camposOrdenables()
        );
        $filtrosCortes = $filtros;
        $filtrosCortes['agrupar'] = $agrupar;
        $cortes = $agrupar !== []
            ? $this->repository->cortesSolicitudpago($filtrosCortes)
            : ['activo' => false];

        return response()->json(ListadoDisenadorPreviewSupport::payload(
            $layout,
            $orden,
            $agrupar,
            $filas,
            static fn (object $row, string $key): string => SolicitudpagoListadoColumnas::valorCelda($row, $key),
            $total,
            $etiquetas,
            $cortes
        ));
    }

    public function guardarVistaListado(Request $request)
    {
        can('listar-solicitud-pago');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = SolicitudpagoListadoFiltros::resolverDesdeRequest(
            $request,
            null,
            $empresaDefault ? (int) $empresaDefault : null
        );
        $layout = SolicitudpagoListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $graficosVista = ListadoLienzoSupport::normalizar(
            $request->input('graficos'),
            $request->input('grafico'),
            $request->exists('graficos'),
            SolicitudpagoListadoFiltros::camposOrdenables()
        );
        $vista = ListadoVistaSupport::guardar(
            SolicitudpagoListadoColumnas::RECURSO,
            (int) auth()->id(),
            (string) $request->input('nombre', ''),
            [
                'modo' => $filtros['modo'],
                'campo' => $filtros['campo'],
                'operador' => $filtros['operador'],
                'valor' => $filtros['valor'],
                'valor_hasta' => $filtros['valor_hasta'] ?? '',
                'qbe' => $filtros['qbe'] ?? [],
                'orden' => $filtros['orden'] ?? [],
                'agrupar' => $filtros['agrupar'] ?? [],
                'estado' => $filtros['estado'] ?? '',
                'tratamiento' => $filtros['tratamiento'] ?? '',
                'madre_hija' => $filtros['madre_hija'] ?? '',
                'fecha_desde' => $filtros['fecha_desde'] ?? '',
                'fecha_hasta' => $filtros['fecha_hasta'] ?? '',
                'alcance' => $filtros['alcance'] ?? '',
                'empresa_id' => $filtros['empresa_id'] ?? null,
                'empresa_scope' => $filtros['empresa_scope'] ?? 'una',
                'calculadas' => SolicitudpagoListadoFiltros::normalizarCalculadas($request->input('calculadas', $filtros['calculadas'] ?? [])),
                'grafico' => ListadoLienzoSupport::primero($graficosVista),
                'graficos' => $graficosVista,
                'formato' => ListadoVisualSupport::normalizarFormato(
                    $request->input('formato', []),
                    SolicitudpagoListadoFiltros::camposOrdenables()
                ),
            ],
            $layout,
            $request->boolean('es_default'),
            $request->boolean('compartida'),
            $request->filled('vista_id') ? (int) $request->input('vista_id') : null
        );
        if (! $vista) {
            return redirect()->route('consultar_solicitudpago', SolicitudpagoListadoFiltros::paraQueryString($filtros))
                ->with('error', 'No se pudo guardar la vista.');
        }
        ListadoVistaMenuSupport::sincronizar($vista, $request->boolean('crear_en_menu'));
        $qs = SolicitudpagoListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs['vista_id'] = $vista->id;

        return redirect()->route('consultar_solicitudpago', $qs)
            ->with('mensaje', 'Vista «'.$vista->nombre.'» guardada.');
    }

    public function eliminarVistaListado(Request $request, int $id)
    {
        can('listar-solicitud-pago');
        $ok = ListadoVistaSupport::eliminar($id, SolicitudpagoListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('consultar_solicitudpago', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        can('listar-solicitud-pago');

        $layout = SolicitudpagoListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $vistaId = $request->filled('vista_id') ? (int) $request->input('vista_id') : 0;
        if ($vistaId > 0 && $request->boolean('actualizar_vista')) {
            $vista = ListadoVistaSupport::findParaUsuario(
                $vistaId,
                SolicitudpagoListadoColumnas::RECURSO,
                (int) auth()->id()
            );
            if ($vista && (int) $vista->usuario_id === (int) auth()->id()) {
                $vista->columnas_json = $layout;
                $vista->save();
            }
        } else {
            SolicitudpagoListadoPreferenciasUsuario::persistirGrillaEstandar($layout);
        }
        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = SolicitudpagoListadoFiltros::resolverDesdeRequest(
            $request,
            null,
            $empresaDefault ? (int) $empresaDefault : null
        );
        $qs = SolicitudpagoListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        if ($vistaId > 0) {
            $qs['vista_id'] = $vistaId;
        } else {
            $qs['vista_estandar'] = 1;
        }

        return redirect()->route('consultar_solicitudpago', $qs)
            ->with('mensaje', $vistaId > 0 ? 'Grilla de la vista actualizada.' : 'Vista estándar actualizada.');
    }

    public function guardarEtiquetasListado(Request $request)
    {
        can('listar-solicitud-pago');

        $etiquetas = $request->input('etiquetas', []);
        if (! is_array($etiquetas)) {
            $etiquetas = [];
        }
        ListadoColumnaEtiquetaSupport::guardar(
            SolicitudpagoListadoColumnas::RECURSO,
            $etiquetas,
            array_keys(SolicitudpagoListadoColumnas::catalogoActivo())
        );
        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = SolicitudpagoListadoFiltros::resolverDesdeRequest(
            $request,
            null,
            $empresaDefault ? (int) $empresaDefault : null
        );
        $qs = SolicitudpagoListadoFiltros::paraQueryString($filtros);
        if ($request->filled('vista_id')) {
            $qs['vista_id'] = (int) $request->input('vista_id');
        }

        return redirect()->route('consultar_solicitudpago', $qs)
            ->with('mensaje', 'Etiquetas por defecto de la instalación actualizadas.');
    }

    public function crear()
    {
        can('crear-solicitud-pago');

        return view('solicitudpago.solicitudpago.crear', $this->datosFormulario());
    }

    public function guardar(ValidacionSolicitudpago $request)
    {
        try {
            $data = $request->validated();
            $data['archivos_nuevos'] = array_values(array_filter(
                array_merge(
                    (array) $request->file('nombrearchivos', []),
                    (array) $request->file('archivos_nuevos', [])
                )
            ));
            $sp = $this->repository->create($data);
            $pdfUrl = route('imprimir_pdf_solicitudpago', $sp->id);

            $redirect = redirect()
                ->route('editar_solicitudpago', $sp->id)
                ->with('mensaje', 'Solicitud de pago #'.$sp->codigo.' creada con éxito.')
                ->with('abrir_pdf_solicitudpago', $pdfUrl);

            if (
                config('solicitudpago.arbol_al_crear', true)
                && ($sp->estado ?? '') === SolicitudpagoEstados::EMITIDA
                && ! $this->arbolIntegracionService->findPorSolicitudpago((int) $sp->id)->count()
            ) {
                $redirect->with('advertencias', [
                    'No se generaron pendientes de aprobación ni correos: el concepto de la solicitud no tiene firmantes '
                    .'operativos (solapa Usuarios del concepto) aplicables al monto. Configure el árbol en el concepto y use «Reenviar al árbol».',
                ]);
            }

            return $redirect;
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', $e->getMessage());
        }
    }

    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta'
            || $request->query('vista') === 'consulta';
        if ($soloConsulta) {
            if (! can('listar-solicitud-pago', false) && ! can('editar-solicitud-pago', false)) {
                abort(403);
            }
        } else {
            can('editar-solicitud-pago');
        }

        $data = $this->repository->findOrFail($id);
        $this->asegurarAccesoSolicitud((int) $id);
        $ocultarVolver = $soloConsulta;
        $bloqueaEdicion = SolicitudpagoEstados::bloqueaEdicion($data->estado ?? '');
        $puedeActualizar = can('actualizar-solicitud-pago', false) && ! $bloqueaEdicion;
        if ($bloqueaEdicion) {
            $soloConsulta = true;
        }
        $tienePendientesCorreoArbol = $this->arbolIntegracionService->tienePendientesConCorreo((int) $id);
        $arbolMovimientos = $this->arbolIntegracionService->findPorSolicitudpago((int) $id);

        return view('solicitudpago.solicitudpago.editar', array_merge(
            $this->datosFormulario($data),
            compact('data', 'soloConsulta', 'ocultarVolver', 'puedeActualizar', 'bloqueaEdicion', 'tienePendientesCorreoArbol', 'arbolMovimientos')
        ));
    }

    /**
     * Visualización desde enlace del árbol de aprobación (correo).
     * Acceso por hashvisualizar: no exige permiso de edición ni filtro de visibilidad del ABM.
     */
    public function visualizar($id, $hash = null)
    {
        $hash = is_string($hash) ? $hash : '';
        $flEncontro = $hash === '';
        if ($hash !== '') {
            foreach ($this->arbolIntegracionService->findPorSolicitudpago((int) $id) as $movimiento) {
                if (ArbolAprobacionEnlaceSupport::hashesCoinciden($hash, (string) ($movimiento->hashvisualizar ?? ''))) {
                    $flEncontro = true;
                    break;
                }
            }
        }

        if (! $flEncontro) {
            return redirect()->route('inicio')
                ->with('mensaje', 'No tiene permisos para visualizar la solicitud de pago');
        }

        if ($hash === '') {
            can('editar-solicitud-pago');
            $this->asegurarAccesoSolicitud((int) $id);
        }

        $data = $this->repository->findOrFail($id);
        $soloConsulta = true;
        $ocultarVolver = false;
        $puedeActualizar = false;
        $acceso_visualizacion_por_hash = $hash !== '';
        $tienePendientesCorreoArbol = $this->arbolIntegracionService->tienePendientesConCorreo((int) $id);
        $arbolMovimientos = $this->arbolIntegracionService->findPorSolicitudpago((int) $id);

        return view('solicitudpago.solicitudpago.editar', array_merge(
            $this->datosFormulario($data),
            compact(
                'data',
                'soloConsulta',
                'ocultarVolver',
                'puedeActualizar',
                'acceso_visualizacion_por_hash',
                'tienePendientesCorreoArbol',
                'arbolMovimientos'
            )
        ));
    }

    public function actualizar(ValidacionSolicitudpago $request, $id)
    {
        can('actualizar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);
        $actual = $this->repository->findOrFail($id);
        if (SolicitudpagoEstados::bloqueaEdicion($actual->estado ?? '')) {
            return redirect()
                ->route('editar_solicitudpago', $id)
                ->with('mensaje_error', 'La solicitud en estado Controlada no puede modificarse. Para anularla use Suspender.');
        }

        try {
            $data = $request->validated();
            $data['archivos_nuevos'] = array_values(array_filter(
                array_merge(
                    (array) $request->file('nombrearchivos', []),
                    (array) $request->file('archivos_nuevos', [])
                )
            ));
            if ($request->boolean('archivos_gestionados')) {
                $data['archivo_ids_existentes'] = $request->input('archivo_ids_existentes', []);
            }
            $this->repository->update($data, $id);

            if ($request->input('origen') === 'modal_consulta' || $request->input('vista') === 'consulta') {
                return redirect()
                    ->route('editar_solicitudpago', [
                        'id' => $id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ])
                    ->with('mensaje', 'Solicitud de pago actualizada con éxito');
            }

            return redirect('solicitudpago/solicitudpago')->with('mensaje', 'Solicitud de pago actualizada con éxito');
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', $e->getMessage());
        }
    }

    public function familiaVinculos($id)
    {
        can('listar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);

        $data = $this->repository->findOrFail($id);

        // Si es hija, mostrar el plan de la madre
        if ((int) ($data->solicitudpago_madre_id ?? 0) > 0 && $data->madre) {
            $data = $this->repository->findOrFail((int) $data->solicitudpago_madre_id);
        }

        if (($data->cuotas ?? collect())->isEmpty()) {
            return response(
                '<div class="alert alert-secondary mb-0">Esta solicitud no tiene plan de cuotas.</div>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }

        return view('solicitudpago.solicitudpago.partials.familia_vinculos', [
            'data' => $data,
            'modo_modal' => true,
        ]);
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-solicitud-pago');
        if (! SolicitudpagoEstados::esAdministradorSesion()) {
            if ($request->ajax()) {
                return response()->json([
                    'mensaje' => 'ng',
                    'error' => 'Solo el rol administrador puede borrar. Para anular use Suspender.',
                ], 403);
            }

            return redirect()
                ->route('consultar_solicitudpago')
                ->with('mensaje_error', 'Solo el rol administrador puede borrar solicitudes. Para anular use Suspender.');
        }
        $this->asegurarAccesoSolicitud((int) $id);

        if ($request->ajax()) {
            return response()->json(['mensaje' => $this->repository->delete($id) ? 'ok' : 'ng']);
        }

        abort(404);
    }

    public function suspender($id)
    {
        can('actualizar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);
        $this->repository->cambiarEstado((int) $id, SolicitudpagoEstados::SUSPENDIDA, 'SUSPENDIDA');

        return redirect()->route('editar_solicitudpago', $id)->with('mensaje', 'Solicitud suspendida');
    }

    public function levantarSuspension($id)
    {
        can('actualizar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);
        $this->repository->cambiarEstado((int) $id, SolicitudpagoEstados::EMITIDA, 'Levanta suspensión');

        return redirect()->route('editar_solicitudpago', $id)->with('mensaje', 'Suspensión levantada');
    }

    public function reenviarArbolAprobacion($id)
    {
        can('actualizar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);

        try {
            $resultado = $this->arbolIntegracionService->reenviarAlArbolAprobacion((int) $id);
        } catch (\Throwable $e) {
            return redirect()->route('editar_solicitudpago', $id)
                ->with('mensaje_error', $e->getMessage());
        }

        return redirect()->route('editar_solicitudpago', $id)
            ->with(
                ! empty($resultado['ok']) ? 'mensaje' : 'mensaje_error',
                $resultado['mensaje'] ?? 'No se pudo reenviar al árbol de aprobación.'
            );
    }

    public function reenviarCorreoArbol($id)
    {
        can('actualizar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);

        try {
            $resultado = $this->arbolIntegracionService->reenviarCorreoNivelPendiente((int) $id);
        } catch (\Throwable $e) {
            return redirect()->route('editar_solicitudpago', $id)
                ->with('mensaje_error', $e->getMessage());
        }

        return redirect()->route('editar_solicitudpago', $id)
            ->with(
                ! empty($resultado['ok']) ? 'mensaje' : 'mensaje_error',
                $resultado['mensaje'] ?? 'No se pudo reenviar el correo del árbol.'
            );
    }

    public function imprimirPdf($id)
    {
        if (! can('listar-solicitud-pago', false) && ! can('editar-solicitud-pago', false)) {
            return redirect()->route('inicio')->with('mensaje', 'No tiene permisos para emitir la solicitud de pago.');
        }
        $this->asegurarAccesoSolicitud((int) $id);

        try {
            $resultado = $this->comprobantePdfService->generar((int) $id);
        } catch (\Throwable $e) {
            return redirect()
                ->route('editar_solicitudpago', $id)
                ->with('mensaje_error', 'No se pudo emitir el comprobante: '.$e->getMessage());
        }

        return $resultado['pdf']->stream($resultado['nombre']);
    }

    /**
     * Descarga pública desde el mail del árbol: comprobante PDF + adjuntos (autorizada por hash).
     */
    public function descargarPaqueteMail($id, $hash)
    {
        $hash = is_string($hash) ? $hash : '';
        if (! $this->arbolIntegracionService->hashAutorizaDescargaPaquete((int) $id, $hash)) {
            return redirect()->route('inicio')
                ->with('mensaje', 'No tiene permisos para descargar la solicitud de pago o el enlace ya no es válido.');
        }

        try {
            $resultado = $this->paqueteMailPdfService->generar((int) $id);
        } catch (\Throwable $e) {
            report($e);

            return response(
                'No se pudo generar el PDF de la solicitud: '.$e->getMessage(),
                500,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        return response($resultado['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$resultado['nombre'].'"',
        ]);
    }

    public function descargarArchivo(Request $request, $id, $archivoId)
    {
        can('listar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);
        $sp = $this->repository->findOrFail($id);
        $arch = $sp->archivos->firstWhere('id', (int) $archivoId);
        if (! $arch) {
            abort(404);
        }

        $ruta = SolicitudpagoArchivoStorageSupport::rutaAbsoluta($arch, (int) $sp->codigo);
        if ($ruta === null) {
            abort(404, 'Archivo no encontrado en el repositorio de solicitudes de pago.');
        }

        $nombre = $arch->nombre_original ?: basename((string) $arch->archivo);
        if ($request->boolean('inline')) {
            return ArchivoAdjuntoCacheSupport::aplicarAntiCacheNavegador(response()->file($ruta, [
                'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            ]));
        }

        return response()->download($ruta, $nombre);
    }

    public function unirArchivos($id)
    {
        can('listar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);
        $sp = $this->repository->findOrFail($id);

        try {
            $resultado = $this->archivosFusionService->fusionar($sp);
        } catch (\Throwable $e) {
            return redirect()
                ->route('editar_solicitudpago', ['id' => $id, 'tab' => 'archivos'])
                ->with('mensaje_error', $e->getMessage());
        }

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$resultado['nombre'].'"',
        ];

        return response($resultado['contenido'], 200, $headers);
    }

    public function importarCuotas(Request $request, $id)
    {
        can('actualizar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);
        $request->validate([
            'archivo_cuotas' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $filas = \App\Support\Solicitudpago\SolicitudpagoCuotasImportColumnasSupport::leerFilas(
                $request->file('archivo_cuotas')
            );
            if ($filas === []) {
                return back()->with('mensaje', 'No se encontraron cuotas válidas en el Excel.');
            }

            $sp = $this->repository->findOrFail($id);
            $payload = $sp->toArray();
            $payload['nro_cuotas'] = array_column($filas, 'nro_cuota');
            $payload['fecha_vencimientos_cuota'] = array_column($filas, 'fecha_vencimiento');
            $payload['montos_cuota'] = array_column($filas, 'monto');
            $payload['solicitudpago_hija_ids'] = array_fill(0, count($filas), null);

            // Conservar cuentas existentes
            $payload['empresa_ids'] = $sp->cuentas->pluck('empresa_id')->all();
            $payload['cuentacontable_ids'] = $sp->cuentas->pluck('cuentacontable_id')->all();
            $payload['centrocosto_ids'] = $sp->cuentas->pluck('centrocosto_id')->all();
            $payload['debe_haberes'] = $sp->cuentas->pluck('debe_haber')->all();
            $payload['montos_cuenta'] = $sp->cuentas->pluck('monto')->all();
            $payload['archivo_ids_existentes'] = $sp->archivos->pluck('id')->all();

            $this->repository->update($payload, $id);

            return redirect()->route('editar_solicitudpago', $id)
                ->with('mensaje', 'Se importaron '.count($filas).' cuota(s) desde Excel.');
        } catch (\Throwable $e) {
            return back()->with('mensaje', $e->getMessage());
        }
    }

    public function marcarPagada($id)
    {
        can('actualizar-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);

        return redirect()->route('editar_solicitudpago', $id)
            ->with('mensaje_error', 'La solicitud pasa a Pagada solo al registrar la orden de pago (Pagar).');
    }

    public function irAPago($id)
    {
        can('actualizar-solicitud-pago');
        can('crear-ingresos-egresos-caja');
        $this->asegurarAccesoSolicitud((int) $id);
        $sp = $this->repository->findOrFail($id);
        if ($sp->estado !== SolicitudpagoEstados::AUTORIZADA) {
            return redirect()->route('editar_solicitudpago', $id)
                ->with('mensaje', 'Solo se puede pagar una solicitud AUTORIZADA.');
        }

        return redirect()->route('crear_ingresoegreso', [
            'solicitudpago_id' => $sp->id,
            'empresa_id' => $sp->empresa_id,
            'proveedor_id' => $sp->proveedor_id,
            'detalle' => \App\Support\Caja\IngresoEgresoSolicitudpagoSupport::descripcionMovimientoDesdeSp($sp),
            'origen' => 'solicitudpago',
        ]);
    }

    public function anularPago(Request $request, $id)
    {
        can('anular-pago-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);

        $mov = $this->ingresoEgresoAnularRevertirService->movimientoPagoDeSolicitud((int) $id);
        if ($mov === null) {
            $msg = 'No hay orden de pago (IE) vinculada a esta solicitud.';
            if ($request->ajax()) {
                return response()->json(['mensaje' => $msg], 422);
            }

            return redirect()->back()->with('mensaje', $msg);
        }

        try {
            // Acceso ya validado por SP; no exigir alcance del ABM de IE (suele ser de otro usuario).
            $resultado = $this->ingresoEgresoAnularRevertirService->anularFisicamente((int) $mov->id, false);
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ok', 'resultado' => $resultado]);
            }

            return redirect()->route('consultar_solicitudpago')->with('mensaje', 'Pago anulado físicamente. Solicitud vuelve a AUTORIZADA.');
        } catch (\Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('mensaje', $e->getMessage());
        }
    }

    public function revertirPago(Request $request, $id)
    {
        can('revertir-pago-solicitud-pago');
        $this->asegurarAccesoSolicitud((int) $id);

        $mov = $this->ingresoEgresoAnularRevertirService->movimientoPagoDeSolicitud((int) $id);
        if ($mov === null) {
            $msg = 'No hay orden de pago (IE) vinculada a esta solicitud.';
            if ($request->ajax()) {
                return response()->json(['mensaje' => $msg], 422);
            }

            return redirect()->back()->with('mensaje', $msg);
        }

        try {
            // Acceso ya validado por SP; no exigir alcance del ABM de IE (suele ser de otro usuario).
            $resultado = $this->ingresoEgresoAnularRevertirService->revertir((int) $mov->id, $request->input('fecha'), false);
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ok', 'resultado' => $resultado]);
            }

            return redirect()->route('consultar_solicitudpago')->with(
                'mensaje',
                'Pago revertido. Anulación N° '.$resultado['numerotransaccion'].'. Solicitud AUTORIZADA.'
            );
        } catch (\Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('mensaje', $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function datosFormulario($data = null): array
    {
        $centrocostoCabecera = null;
        if ($data !== null && $data->centrocosto_id) {
            $centrocostoCabecera = $data->relationLoaded('centrocostos')
                ? $data->centrocostos
                : $data->centrocostos()->first();
        } elseif ($data === null) {
            $ccId = (int) (auth()->user()->centrocosto_id ?? 0);
            if ($ccId > 0) {
                $centrocostoCabecera = Centrocosto::query()->find($ccId);
            }
        }

        return [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'sector_query' => $this->sectorRepository->all(),
            'formapagosol_query' => $this->formapagosolRepository->all(),
            'moneda_query' => $this->monedaRepository->all(),
            'centrocosto_query' => $this->centrocostoRepository->all(),
            'centrocosto_cabecera' => $centrocostoCabecera,
            'estado_enum' => SolicitudpagoEstados::opciones(),
            'tratamiento_enum' => SolicitudpagoTratamientos::opciones(),
            'impuesto_query' => Impuesto::soloNacionales()->orderBy('valor')->orderBy('nombre')->get(),
            'retencionganancia_query' => Retencionganancia::query()->orderBy('codigo')->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'retencioniva_query' => Retencioniva::query()->orderBy('codigo')->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'retencionsuss_query' => Retencionsuss::query()->orderBy('codigo')->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
        ];
    }

    private function asegurarAccesoSolicitud(int $id): void
    {
        if (! SolicitudpagoVisibilidadSupport::solicitudAccesiblePorId($id)) {
            abort(403, 'No tiene acceso a esta solicitud de pago.');
        }
    }
}
