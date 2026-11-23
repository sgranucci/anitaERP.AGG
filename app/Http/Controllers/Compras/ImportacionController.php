<?php

declare(strict_types=1);

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\ImportacionListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionImportacion;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Importacion_Archivo;
use App\Models\Compras\Importacion_Gasto_Tipo;
use App\Models\Compras\Importacion_Nomenclatura;
use App\Models\Compras\Ordencompra;
use App\Models\Configuracion\Moneda;
use App\Models\Stock\Lote;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Ventas\Incoterm;
use App\Repositories\Compras\ImportacionRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ImportacionChecklistService;
use App\Services\Compras\ImportacionLandedCostService;
use App\Services\Compras\ImportacionLibroIvaSyncService;
use App\Services\Compras\ImportacionNacionalizacionService;
use App\Support\Compras\ImportacionAnitaBridgeSupport;
use App\Support\Compras\ImportacionCashflowSupport;
use App\Support\Compras\ImportacionEstados;
use App\Support\Compras\ImportacionListadoColumnas;
use App\Support\Compras\ImportacionListadoFiltros;
use App\Support\Compras\ImportacionListadoPreferenciasUsuario;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoDisenadorPreviewSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoVistaMenuSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Reportes\DompdfListadoSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ImportacionController extends Controller
{
    public function __construct(
        private readonly ImportacionRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly ImportacionLandedCostService $landedCostService,
        private readonly ImportacionNacionalizacionService $nacionalizacionService,
        private readonly ImportacionChecklistService $checklistService,
        private readonly ImportacionLibroIvaSyncService $libroIvaSyncService,
    ) {}

    public function index(Request $request)
    {
        can('listar-importacion');

        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $recurso = ImportacionListadoColumnas::RECURSO;
        $vistas = ListadoVistaSupport::listarParaUsuario($recurso, $usuarioId);
        $vistaActiva = null;
        $forzarEstandar = $request->boolean('vista_estandar')
            || $request->input('vista_modo') === 'estandar';

        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario((int) $request->input('vista_id'), $recurso, $usuarioId);
        } elseif (
            ! $forzarEstandar
            && ! $request->has('filtro_valor')
            && ! $request->has('qbe')
            && ! $request->has('filtro_estado')
            && ! $request->has('empresa_id')
            && ! $request->has('empresa_todas')
            && ! $request->boolean('filtro_limpiar')
        ) {
            // Solo auto-aplicar la vista default al entrar sin criterios (no al elegir "Vista estándar").
            $vistaActiva = ListadoVistaSupport::defaultDelUsuario($recurso, $usuarioId);
        }

        $filtrosRequest = ListadoVistaSupport::prepararQbeContraVista(
            ImportacionListadoFiltros::resolverDesdeRequest($request),
            $request
        );
        $filtros = $filtrosRequest;
        if ($vistaActiva && is_array($vistaActiva->filtros_json)) {
            $filtros = ImportacionListadoFiltros::fusionarDesdeVista($filtros, $vistaActiva->filtros_json);
        }
        unset($filtros['_qbe_explicito']);

        $guardoQbeVista = ListadoVistaSupport::recordarQbeSiEnvio($vistaActiva, $request, $filtros);
        if ($request->boolean('quitar_qbe') && $vistaActiva && $guardoQbeVista) {
            return redirect()
                ->route('importacion', ['vista_id' => $vistaActiva->id] + ImportacionListadoFiltros::paraQueryString(
                    array_merge(ImportacionListadoFiltros::filtrosVacios(), [
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'empresa_todas' => $filtros['empresa_todas'] ?? false,
                        'filtro_estado' => $filtros['filtro_estado'] ?? '',
                    ])
                ))
                ->with('mensaje', 'Se quitaron los filtros de la vista «'.$vistaActiva->nombre.'». Las columnas siguen igual.');
        }
        if ($vistaActiva && ($request->exists('group') || $request->exists('sort'))) {
            if ($request->exists('group')) {
                $filtros['agrupar'] = $filtrosRequest['agrupar'] ?? [];
            }
            if ($request->exists('sort')) {
                $filtros['orden'] = $filtrosRequest['orden'] ?? [];
            }
            ListadoVistaSupport::recordarOrdenYAgrupar($vistaActiva, $filtros['orden'] ?? [], $filtros['agrupar'] ?? []);
        }

        if ($request->boolean('quitar_orden')) {
            $filtros['orden'] = [];
            if ($vistaActiva) {
                ListadoVistaSupport::recordarOrdenYAgrupar(
                    $vistaActiva,
                    [],
                    $filtros['agrupar'] ?? []
                );
            }
            $params = ImportacionListadoFiltros::paraQueryString($filtros);
            if ($vistaActiva) {
                $params['vista_id'] = $vistaActiva->id;
            } elseif (!empty($forzarEstandar)) {
                $params['vista_estandar'] = 1;
            }

            return redirect()
                ->route('importacion', $params)
                ->with('mensaje', 'Se volvió al orden estándar.');
        }


        $catalogo = ImportacionListadoColumnas::catalogoActivo();
        $etiquetasInstalacion = ListadoColumnaEtiquetaSupport::etiquetasEfectivas($recurso, $catalogo);

        // Grilla: vista nombrada = su JSON; estándar = preferencia/defaults. Nunca mezclar.
        if ($vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== []) {
            $grillaLayout = ImportacionListadoPreferenciasUsuario::normalizarLayout($vistaActiva->columnas_json);
        } else {
            $grillaLayout = ImportacionListadoPreferenciasUsuario::grillaEstandar();
        }
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);

        $datas = $this->repository->leeImportacion($filtros, true);

        $cortes = ['activo' => false];
        $agruparActivo = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], ImportacionListadoFiltros::camposOrdenables());
        if ($agruparActivo !== []) {
            $cortes = $this->repository->cortesImportacion($filtros);
        }

        $camposFiltro = ImportacionListadoFiltros::camposQbeDisponibles();
        foreach ($camposFiltro as $key => $meta) {
            $camposFiltro[$key]['label'] = $etiquetas[$key] ?? $etiquetasInstalacion[$key] ?? $meta['label'];
        }

        $filtrosQuery = ImportacionListadoFiltros::paraQueryString($filtros);
        $filtrosQuery['columnas'] = implode(',', $columnasVisibles);
        if ($vistaActiva) {
            $filtrosQuery['vista_id'] = $vistaActiva->id;
        } elseif ($forzarEstandar) {
            $filtrosQuery['vista_estandar'] = 1;
        }

        return view('compras.importacion.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => $camposFiltro,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'estados' => ImportacionEstados::paraSelect(),
            'columnasVisibles' => $columnasVisibles,
            'grillaLayout' => $grillaLayout,
            'catalogoColumnas' => $catalogo,
            'etiquetasColumnas' => $etiquetas,
            'etiquetasInstalacion' => $etiquetasInstalacion,
            'vistasListado' => $vistas,
            'vistaActiva' => $vistaActiva,
            'workbenchListo' => ListadoVistaSupport::tablasDisponibles(),
            'cortes' => $cortes,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-importacion');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ImportacionListadoFiltros::resolverDesdeRequest($request, $busqueda);
        [$columnasVisibles, $etiquetas] = $this->columnasYEtiquetasExport($request);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $datas = $this->repository->leeImportacion($filtros, false);
                $html = view('compras.importacion.listado', [
                    'datas' => $datas,
                    'columnasVisibles' => $columnasVisibles,
                    'etiquetasColumnas' => $etiquetas,
                    'filtros' => $filtros,
                ])->render();
                $ruta = storage_path('pdf/listados/listado_importacion.pdf');
                DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                    'titulo_corto' => 'Importaciones',
                    'dompdf' => [
                        'isFontSubsettingEnabled' => false,
                        'isJavascriptEnabled' => false,
                    ],
                ]);

                return response()->download($ruta);

            case 'EXCEL':
                return (new ImportacionListadoExport($this->repository))
                    ->parametros($filtros, $columnasVisibles, $etiquetas)
                    ->download('importaciones.xlsx');

            case 'CSV':
                return (new ImportacionListadoExport($this->repository))
                    ->parametros($filtros, $columnasVisibles, $etiquetas)
                    ->download('importaciones.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('importacion', ImportacionListadoFiltros::paraQueryString($filtros));
    }

    /**
     * Columnas (orden de la grilla) y títulos para PDF/Excel/CSV: los que muestra la pantalla
     * (`columnas` del query) con los títulos de la vista abierta si hay `vista_id`.
     *
     * @return array{0: list<string>, 1: array<string, string>}
     */
    private function columnasYEtiquetasExport(Request $request): array
    {
        $recurso = ImportacionListadoColumnas::RECURSO;
        $columnasRequest = $request->input('columnas');
        if (is_string($columnasRequest)) {
            $columnasRequest = array_values(array_filter(array_map('trim', explode(',', $columnasRequest))));
        }
        $columnasRequest = is_array($columnasRequest) ? $columnasRequest : null;

        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas($recurso, ImportacionListadoColumnas::catalogoActivo());

        if ($request->filled('vista_id')) {
            $vista = ListadoVistaSupport::findParaUsuario((int) $request->input('vista_id'), $recurso, auth()->id() ? (int) auth()->id() : null);
            if ($vista && is_array($vista->columnas_json) && $vista->columnas_json !== []) {
                $layout = ImportacionListadoPreferenciasUsuario::normalizarLayout($vista->columnas_json);
                $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
                if ($columnasRequest === null) {
                    $columnasRequest = ListadoGrillaConfigSupport::keysVisibles($layout);
                }
            }
        }

        return [ImportacionListadoPreferenciasUsuario::resolverColumnas($columnasRequest), $etiquetas];
    }

    /**
     * Preview del diseñador: muestra acotada con los mismos filtros + layout borrador.
     */
    public function previewWorkbench(Request $request)
    {
        can('listar-importacion');

        $campos = ImportacionListadoFiltros::camposOrdenables();
        $filtros = ImportacionListadoFiltros::resolverDesdeRequest($request);
        $filtros['_per_page'] = ListadoDisenadorPreviewSupport::LIMITE_MUESTRA;
        $layout = ImportacionListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);

        $orden = ListadoOrdenamientoSupport::normalizar($request->input('sort', $filtros['orden'] ?? []), $campos);
        $agrupar = ListadoAgrupacionSupport::normalizar($request->input('group', $filtros['agrupar'] ?? []), $campos);
        $filtros['orden'] = $orden;
        $filtros['agrupar'] = $agrupar;

        $page = $this->repository->leeImportacion($filtros, true);
        $total = method_exists($page, 'total') ? (int) $page->total() : $page->count();
        $filas = method_exists($page, 'getCollection') ? $page->getCollection() : $page;

        $cortes = $agrupar !== []
            ? $this->repository->cortesImportacion($filtros)
            : ['activo' => false];

        return response()->json(ListadoDisenadorPreviewSupport::payload(
            $layout,
            $orden,
            $agrupar,
            $filas,
            static fn (object $row, string $key): string => ImportacionListadoColumnas::valorCelda($row, $key),
            $total,
            $etiquetas,
            $cortes
        ));
    }

    public function guardarVistaListado(Request $request)
    {
        can('listar-importacion');

        $filtros = ImportacionListadoFiltros::resolverDesdeRequest($request);
        // Solo el JSON de la vista; NO pisar la grilla estándar del usuario ni los chips externos.
        $layout = ImportacionListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);

        $vista = ListadoVistaSupport::guardar(
            ImportacionListadoColumnas::RECURSO,
            (int) auth()->id(),
            (string) $request->input('nombre', ''),
            [
                'modo' => $filtros['modo'],
                'valor' => $filtros['valor'],
                'valor_hasta' => $filtros['valor_hasta'] ?? '',
                'qbe' => $filtros['qbe'] ?? [],
                'orden' => $filtros['orden'] ?? [],
                'agrupar' => $filtros['agrupar'] ?? [],
            ],
            $layout,
            $request->boolean('es_default'),
            $request->boolean('compartida'),
            $request->filled('vista_id') ? (int) $request->input('vista_id') : null
        );

        if (! $vista) {
            return redirect()->route('importacion', ImportacionListadoFiltros::paraQueryString($filtros))
                ->with('error', 'No se pudo guardar la vista.');
        }

        ListadoVistaMenuSupport::sincronizar($vista, $request->boolean('crear_en_menu'));

        $qs = ImportacionListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs['vista_id'] = $vista->id;

        $msg = 'Vista «'.$vista->nombre.'» guardada (grilla + filtros).';
        if ($request->boolean('crear_en_menu') && ListadoVistaMenuSupport::columnaMenuDisponible()) {
            $msg .= ' Atajo de menú sincronizado.';
        }

        return redirect()->route('importacion', $qs)->with('mensaje', $msg);
    }

    public function eliminarVistaListado(Request $request, int $id)
    {
        can('listar-importacion');

        $ok = ListadoVistaSupport::eliminar($id, ImportacionListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('importacion', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        can('listar-importacion');

        $layout = ImportacionListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $vistaId = $request->filled('vista_id') ? (int) $request->input('vista_id') : 0;

        if ($vistaId > 0 && $request->boolean('actualizar_vista')) {
            // Aplicar solo sobre la vista nombrada activa (y solo si es del usuario).
            $vista = ListadoVistaSupport::findParaUsuario($vistaId, ImportacionListadoColumnas::RECURSO, (int) auth()->id());
            if ($vista && (int) $vista->usuario_id === (int) auth()->id()) {
                $vista->columnas_json = $layout;
                $vista->save();
            }
        } else {
            // Sin vista activa → modifica la Vista estándar personal.
            ImportacionListadoPreferenciasUsuario::persistirGrillaEstandar($layout);
        }

        $qs = ImportacionListadoFiltros::paraQueryString(ImportacionListadoFiltros::resolverDesdeRequest($request));
        $qs['columnas'] = implode(',', $columnasVisibles);
        if ($vistaId > 0) {
            $qs['vista_id'] = $vistaId;
        } else {
            $qs['vista_estandar'] = 1;
        }

        return redirect()->route('importacion', $qs)
            ->with('mensaje', $vistaId > 0 ? 'Grilla de la vista actualizada.' : 'Vista estándar actualizada.');
    }

    public function guardarEtiquetasListado(Request $request)
    {
        can('listar-importacion');

        $etiquetas = $request->input('etiquetas', []);
        ListadoColumnaEtiquetaSupport::guardar(
            ImportacionListadoColumnas::RECURSO,
            is_array($etiquetas) ? $etiquetas : [],
            array_keys(ImportacionListadoColumnas::catalogoActivo())
        );

        $qs = ImportacionListadoFiltros::paraQueryString(ImportacionListadoFiltros::resolverDesdeRequest($request));
        if ($request->filled('columnas')) {
            $qs['columnas'] = (string) $request->input('columnas');
        }
        if ($request->filled('vista_id')) {
            $qs['vista_id'] = (int) $request->input('vista_id');
        }

        return redirect()->route('importacion', $qs)
            ->with('mensaje', 'Etiquetas por defecto de la instalación actualizadas.');
    }

    public function crear(Request $request)
    {
        can('crear-importacion');

        return view('compras.importacion.crear', $this->formShared($request) + [
            'data' => null,
        ]);
    }

    public function guardar(ValidacionImportacion $request)
    {
        can('crear-importacion');
        $data = $this->enriquecerPayload($request);
        $this->assertEmpresaPermitida((int) $data['empresa_id']);
        $importacion = $this->repository->create($data);
        $this->guardarArchivosNuevos($importacion->id, $request);

        return redirect()->route('editar_importacion', $importacion->id)
            ->with('mensaje', 'Carpeta de importación creada con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-importacion');
        $data = $this->repository->findConRelaciones((int) $id);
        $this->checklistService->asegurarDefaults($data);

        return view('compras.importacion.editar', $this->formShared($request) + [
            'data' => $data->fresh([
                'empresa', 'proveedor', 'moneda', 'incoterm',
                'ordenescompra', 'embarques', 'gastos.tipo', 'lotes', 'archivos', 'checklist', 'prorrateos.lineas',
            ]),
        ]);
    }

    public function actualizar(ValidacionImportacion $request, $id)
    {
        can('actualizar-importacion');
        $data = $this->enriquecerPayload($request);
        if (! empty($data['empresa_id'])) {
            $this->assertEmpresaPermitida((int) $data['empresa_id']);
        }
        try {
            $importacion = $this->repository->update($data, (int) $id);
            $this->guardarArchivosNuevos($importacion->id, $request);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $retorno = QueryRetornoListado::desdeRequest($request, ImportacionListadoFiltros::class);

        return redirect()->route('importacion', $retorno)
            ->with('mensaje', 'Importación actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('eliminar-importacion');
        try {
            $this->repository->delete((int) $id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('importacion')->with('mensaje', 'Importación eliminada');
    }

    public function nacionalizar(Request $request, $id)
    {
        can('nacionalizar-importacion');
        $importacion = $this->repository->findOrFail((int) $id);
        $request->validate([
            'numerodespacho' => 'required_without:lote_id|nullable|string|max:255',
            'pais_id' => 'required_without:lote_id|nullable|integer|exists:pais,id',
            'fechaingreso' => 'nullable|date',
            'lote_id' => 'nullable|integer|exists:lote,id',
            'recepcion_proveedor_id' => 'nullable|integer|exists:recepcion_proveedor,id',
        ]);

        try {
            $importacion = $this->nacionalizacionService->nacionalizar(
                $importacion,
                $request->only(['numerodespacho', 'pais_id', 'fechaingreso']),
                $request->filled('lote_id') ? (int) $request->input('lote_id') : null,
            );
            if ($request->filled('recepcion_proveedor_id')) {
                $this->nacionalizacionService->vincularRecepcion($importacion, (int) $request->input('recepcion_proveedor_id'));
            }
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('editar_importacion', $importacion->id)
            ->with('mensaje', 'Importación nacionalizada');
    }

    public function calcularCostos(Request $request, $id)
    {
        can('editar-importacion');
        $importacion = $this->repository->findConRelaciones((int) $id);
        $partidas = $this->partidasDesdeOrdenes($importacion);
        try {
            $prorrateo = $this->landedCostService->guardarCalculo(
                $importacion,
                $partidas,
                (bool) $request->boolean('usar_reales', true)
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('editar_importacion', $importacion->id)
            ->with('mensaje', 'Prorrateo calculado (id '.$prorrateo->id.')');
    }

    public function previewCostos(Request $request, $id)
    {
        can('editar-importacion');
        $importacion = $this->repository->findConRelaciones((int) $id);
        $partidas = $this->partidasDesdeOrdenes($importacion);
        $resultado = $this->landedCostService->calcular(
            $importacion,
            $partidas,
            (bool) $request->boolean('usar_reales', true)
        );

        return response()->json($resultado);
    }

    public function cerrarCostos(Request $request, $id)
    {
        can('cerrar-costos-importacion');
        $importacion = $this->repository->findOrFail((int) $id);
        try {
            $this->landedCostService->cerrarCostos($importacion);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('editar_importacion', $importacion->id)
            ->with('mensaje', 'Costos cerrados');
    }

    public function checklistMarcar(Request $request, $id, $itemId)
    {
        can('actualizar-importacion');
        $importacion = $this->repository->findOrFail((int) $id);
        $item = $importacion->checklist()->whereKey((int) $itemId)->firstOrFail();
        $this->checklistService->marcar(
            $item,
            (bool) $request->boolean('cumplido'),
            $request->input('observacion')
        );

        return back()->with('mensaje', 'Checklist actualizado');
    }

    public function syncLibroIva(Request $request, $id)
    {
        can('actualizar-importacion');
        $importacion = $this->repository->findConRelaciones((int) $id);
        $request->validate([
            'fecha' => 'required|date',
            'importe' => 'required|numeric|min:0',
            'iva' => 'nullable|numeric|min:0',
        ]);
        $ok = $this->libroIvaSyncService->sincronizarBien($importacion, $request->only([
            'fecha', 'importe', 'iva', 'despacho', 'cuit_despachante', 'razon_social',
        ]));

        return back()->with(
            $ok ? 'mensaje' : 'error',
            $ok ? 'Libro IVA importaciones actualizado' : 'No se pudo sincronizar (tabla ausente)'
        );
    }

    public function simular(Request $request)
    {
        can('listar-importacion');
        $request->validate([
            'fob' => 'required|numeric|min:0',
            'gastos' => 'nullable|array',
            'partidas' => 'nullable|array',
        ]);
        $resultado = $this->landedCostService->simular(
            (float) $request->input('fob'),
            (array) $request->input('partidas', [['valor' => (float) $request->input('fob')]]),
            (array) $request->input('gastos', [])
        );

        return response()->json($resultado);
    }

    public function cashflow(Request $request)
    {
        can('listar-importacion');
        $empresaId = $request->filled('empresa_id') ? (int) $request->input('empresa_id') : null;
        $filas = ImportacionCashflowSupport::proyectar(
            $empresaId,
            $request->input('desde'),
            $request->input('hasta')
        );

        return view('compras.importacion.cashflow', [
            'filas' => $filas,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'filtros' => $request->only(['empresa_id', 'desde', 'hasta']),
            'anitaBridge' => ImportacionAnitaBridgeSupport::diagnostico(),
            'nomenclaturas' => Importacion_Nomenclatura::query()->where('activo', true)->orderBy('codigo')->limit(200)->get(),
        ]);
    }

    public function descargarArchivo($id, $archivoId)
    {
        can('listar-importacion');
        $importacion = $this->repository->findOrFail((int) $id);
        $archivo = Importacion_Archivo::query()
            ->where('importacion_id', $importacion->id)
            ->whereKey((int) $archivoId)
            ->firstOrFail();

        return Storage::disk('public')->download($archivo->ruta, $archivo->nombre_original);
    }

    public function consultaCarpeta(Request $request)
    {
        $this->assertPuedeConsultarCarpetaDesdeOc();
        $empresaId = (int) $request->input('empresa_id', 0);
        $consulta = trim((string) $request->input('consulta', ''));

        $query = \App\Models\Compras\Importacion::query()
            ->with(['proveedor:id,nombre'])
            ->orderByDesc('id')
            ->limit(80);

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        } else {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');
        }

        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('codigo', 'like', '%'.$consulta.'%')
                    ->orWhere('id', 'like', '%'.$consulta.'%')
                    ->orWhereHas('proveedor', function ($pq) use ($consulta) {
                        $pq->where('nombre', 'like', '%'.$consulta.'%')
                            ->orWhere('codigo', 'like', '%'.$consulta.'%');
                    });
            });
        }

        $html = '';
        $puedeAbm = can('listar-importacion', false) || can('editar-importacion', false);
        foreach ($query->get() as $row) {
            $estado = ImportacionEstados::etiqueta((string) $row->estado);
            $html .= '<tr data-id="'.(int) $row->id
                .'" data-codigo="'.e((string) $row->codigo)
                .'" data-estado="'.e($estado).'">';
            $html .= '<td>'.(int) $row->id.'</td>';
            $html .= '<td>'.e((string) $row->codigo).'</td>';
            $html .= '<td>'.e((string) ($row->proveedor->nombre ?? '')).'</td>';
            $html .= '<td>'.e($estado).'</td>';
            $html .= '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsulta-importacion-carpeta">Elegir</a>';
            if ($puedeAbm) {
                $url = route('editar_importacion', [
                    'id' => $row->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
                $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
            }
            $html .= '</td></tr>';
        }

        return response()->json([
            'data' => $html !== '' ? $html : '<tr><td colspan="5" class="text-muted">Sin resultados</td></tr>',
        ]);
    }

    public function resolverCarpeta(Request $request)
    {
        $this->assertPuedeConsultarCarpetaDesdeOc();
        $empresaId = (int) $request->input('empresa_id', 0);
        $valor = trim((string) $request->input('valor', $request->input('codigo', '')));
        if ($valor === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Ingrese código de carpeta']);
        }

        $query = \App\Models\Compras\Importacion::query();
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        } else {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');
        }

        if (ctype_digit($valor)) {
            $query->where(function ($q) use ($valor) {
                $q->where('codigo', $valor)->orWhere('id', (int) $valor);
            });
        } else {
            $query->where('codigo', 'like', '%'.$valor.'%');
        }

        $row = $query->orderByDesc('id')->first();
        if (! $row) {
            return response()->json(['ok' => false, 'mensaje' => 'Carpeta no encontrada']);
        }

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => (int) $row->id,
                'codigo' => (string) $row->codigo,
                'estado' => ImportacionEstados::etiqueta((string) $row->estado),
            ],
        ]);
    }

    private function assertPuedeConsultarCarpetaDesdeOc(): void
    {
        if (
            can('listar-importacion', false)
            || can('crear-ordencompra', false)
            || can('editar-ordencompra', false)
            || can('actualizar-ordencompra', false)
            || can('crear-comprobante-proveedor', false)
            || can('editar-comprobante-proveedor', false)
            || can('actualizar-comprobante-proveedor', false)
        ) {
            return;
        }
        can('listar-importacion');
    }

    public function consultaOrdencompra(Request $request)
    {
        can('listar-importacion');
        $empresaId = (int) $request->input('empresa_id', 0);
        $consulta = trim((string) $request->input('consulta', ''));

        $query = Ordencompra::query()
            ->with(['proveedores:id,nombre'])
            ->orderByDesc('numeroordencompra')
            ->limit(80);

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        } else {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');
        }

        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('numeroordencompra', 'like', '%'.$consulta.'%')
                    ->orWhere('id', 'like', '%'.$consulta.'%')
                    ->orWhereHas('proveedores', function ($pq) use ($consulta) {
                        $pq->where('nombre', 'like', '%'.$consulta.'%')
                            ->orWhere('codigo', 'like', '%'.$consulta.'%');
                    });
            });
        }

        $html = '';
        $puedeAbm = can('listar-ordencompra', false) || can('editar-ordencompra', false);
        foreach ($query->get() as $row) {
            $html .= '<tr data-id="'.(int) $row->id
                .'" data-numero="'.e((string) $row->numeroordencompra)
                .'" data-fecha="'.e((string) $row->fecha)
                .'" data-estado="'.e((string) $row->estadoordencompra)
                .'" data-proveedor="'.e((string) ($row->proveedores->nombre ?? '')).'">';
            $html .= '<td>'.(int) $row->id.'</td>';
            $html .= '<td>'.e((string) $row->numeroordencompra).'</td>';
            $html .= '<td>'.e((string) ($row->proveedores->nombre ?? '')).'</td>';
            $html .= '<td>'.e((string) $row->fecha).'</td>';
            $html .= '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsulta-importacion-oc">Elegir</a>';
            if ($puedeAbm) {
                $url = route('editar_ordencompra', [
                    'id' => $row->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
                $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
            }
            $html .= '</td></tr>';
        }

        return response()->json([
            'data' => $html !== '' ? $html : '<tr><td colspan="5" class="text-muted">Sin resultados</td></tr>',
        ]);
    }

    public function resolverOrdencompra(Request $request)
    {
        can('listar-importacion');
        $empresaId = (int) $request->input('empresa_id', 0);
        $valor = trim((string) $request->input('valor', $request->input('codigo', '')));
        if ($valor === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Ingrese número de OC']);
        }

        $query = Ordencompra::query()->with(['proveedores:id,nombre']);
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        } else {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');
        }

        if (ctype_digit($valor)) {
            $query->where(function ($q) use ($valor) {
                $q->where('numeroordencompra', (int) $valor)
                    ->orWhere('id', (int) $valor);
            });
        } else {
            $query->where('numeroordencompra', 'like', '%'.$valor.'%');
        }

        $oc = $query->orderByDesc('numeroordencompra')->first();
        if (! $oc) {
            return response()->json(['ok' => false, 'mensaje' => 'Orden de compra no encontrada']);
        }

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => (int) $oc->id,
                'numero' => (string) $oc->numeroordencompra,
                'fecha' => (string) $oc->fecha,
                'estado' => (string) $oc->estadoordencompra,
                'proveedor' => (string) ($oc->proveedores->nombre ?? ''),
            ],
        ]);
    }

    public function consultaLote(Request $request)
    {
        can('listar-importacion');
        $consulta = trim((string) $request->input('consulta', ''));
        $query = Lote::query()->orderByDesc('id')->limit(80);
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('numerodespacho', 'like', '%'.$consulta.'%')
                    ->orWhere('id', 'like', '%'.$consulta.'%');
            });
        }

        $html = '';
        foreach ($query->get() as $row) {
            $html .= '<tr data-id="'.(int) $row->id
                .'" data-despacho="'.e((string) $row->numerodespacho)
                .'" data-ingreso="'.e((string) $row->fechaingreso).'">';
            $html .= '<td>'.(int) $row->id.'</td>';
            $html .= '<td>'.e((string) $row->numerodespacho).'</td>';
            $html .= '<td>'.e((string) $row->fechaingreso).'</td>';
            $html .= '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsulta-importacion-lote">Elegir</a></td>';
            $html .= '</tr>';
        }

        return response()->json([
            'data' => $html !== '' ? $html : '<tr><td colspan="4" class="text-muted">Sin resultados</td></tr>',
        ]);
    }

    public function resolverLote(Request $request)
    {
        can('listar-importacion');
        $valor = trim((string) $request->input('valor', $request->input('codigo', '')));
        if ($valor === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Ingrese número de despacho']);
        }

        $query = Lote::query();
        if (ctype_digit($valor)) {
            $query->where(function ($q) use ($valor) {
                $q->where('numerodespacho', $valor)
                    ->orWhere('id', (int) $valor);
            });
        } else {
            $query->where('numerodespacho', 'like', '%'.$valor.'%');
        }

        $lote = $query->orderByDesc('id')->first();
        if (! $lote) {
            return response()->json(['ok' => false, 'mensaje' => 'Lote / despacho no encontrado']);
        }

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => (int) $lote->id,
                'despacho' => (string) $lote->numerodespacho,
                'ingreso' => (string) $lote->fechaingreso,
            ],
        ]);
    }

    public function consultaComprobanteProveedor(Request $request)
    {
        can('listar-importacion');
        $empresaId = (int) $request->input('empresa_id', 0);
        $consulta = trim((string) $request->input('consulta', ''));

        $query = Comprobante_Proveedor::query()
            ->with([
                'proveedores:id,nombre',
                'tipotransaccion_compras:id,abreviatura',
            ])
            ->orderByDesc('id')
            ->limit(80);

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        } else {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');
        }

        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('numerocomprobante', 'like', '%'.$consulta.'%')
                    ->orWhere('letra', 'like', '%'.$consulta.'%')
                    ->orWhere('sucursal', 'like', '%'.$consulta.'%')
                    ->orWhere('id', 'like', '%'.$consulta.'%')
                    ->orWhereHas('proveedores', function ($pq) use ($consulta) {
                        $pq->where('nombre', 'like', '%'.$consulta.'%')
                            ->orWhere('codigo', 'like', '%'.$consulta.'%');
                    });
            });
        }

        $html = '';
        $puedeAbm = can('listar-comprobante-proveedor', false) || can('editar-comprobante-proveedor', false);
        foreach ($query->get() as $row) {
            $item = $this->payloadComprobanteProveedorParaGasto($row);
            $html .= '<tr data-id="'.$item['id']
                .'" data-codigo="'.e($item['codigo'])
                .'" data-proveedor="'.e($item['proveedor'])
                .'" data-fecha="'.e($item['fecha_corta'])
                .'" data-fecha-ymd="'.e($item['fecha'])
                .'" data-importe="'.e((string) $item['importe'])
                .'" data-cotizacion="'.e((string) ($item['cotizacion'] ?? '')).'">';
            $html .= '<td>'.$item['id'].'</td>';
            $html .= '<td>'.e($item['codigo']).'</td>';
            $html .= '<td>'.e($item['proveedor']).'</td>';
            $html .= '<td>'.e($item['fecha_corta']).'</td>';
            $html .= '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsulta-importacion-cp">Elegir</a>';
            if ($puedeAbm) {
                $url = route('editar_comprobante_proveedor', [
                    'id' => $row->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
                $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
            }
            $html .= '</td></tr>';
        }

        return response()->json([
            'data' => $html !== '' ? $html : '<tr><td colspan="5" class="text-muted">Sin resultados</td></tr>',
        ]);
    }

    public function resolverComprobanteProveedor(Request $request)
    {
        can('listar-importacion');
        $empresaId = (int) $request->input('empresa_id', 0);
        $valor = trim((string) $request->input('valor', $request->input('codigo', '')));
        if ($valor === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Ingrese comprobante']);
        }

        $query = Comprobante_Proveedor::query()
            ->with([
                'proveedores:id,nombre',
                'tipotransaccion_compras:id,abreviatura',
            ]);
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        } else {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');
        }

        if (ctype_digit($valor)) {
            $query->where(function ($q) use ($valor) {
                $q->where('id', (int) $valor)
                    ->orWhere('numerocomprobante', (int) $valor);
            });
        } else {
            $partes = preg_split('/[\s\-\/]+/', $valor) ?: [];
            $query->where(function ($q) use ($valor, $partes) {
                $q->where('numerocomprobante', 'like', '%'.$valor.'%');
                if (count($partes) >= 3) {
                    $q->orWhere(function ($q2) use ($partes) {
                        $q2->where('letra', $partes[0])
                            ->where('sucursal', (int) $partes[1])
                            ->where('numerocomprobante', (int) $partes[2]);
                    });
                }
            });
        }

        $row = $query->orderByDesc('id')->first();
        if (! $row) {
            return response()->json(['ok' => false, 'mensaje' => 'Comprobante no encontrado']);
        }

        return response()->json([
            'ok' => true,
            'item' => $this->payloadComprobanteProveedorParaGasto($row),
        ]);
    }

    /**
     * Datos de CP para precargar el renglón de gasto (importe, fecha, cotización).
     *
     * @return array{
     *   id: int,
     *   codigo: string,
     *   proveedor: string,
     *   fecha: string,
     *   fecha_corta: string,
     *   importe: float|null,
     *   cotizacion: float|null
     * }
     */
    private function payloadComprobanteProveedorParaGasto(Comprobante_Proveedor $row): array
    {
        $total = $row->total;
        $importe = $total !== null && $total !== '' ? round((float) $total, 4) : null;
        $cot = $row->cotizacion;
        $cotizacion = $cot !== null && (float) $cot > 0 ? round((float) $cot, 6) : null;
        $fechaYmd = '';
        if ($row->fechacomprobante instanceof \DateTimeInterface) {
            $fechaYmd = $row->fechacomprobante->format('Y-m-d');
        } elseif (! empty($row->fechacomprobante)) {
            $ts = strtotime((string) $row->fechacomprobante);
            $fechaYmd = $ts ? date('Y-m-d', $ts) : '';
        }

        return [
            'id' => (int) $row->id,
            'codigo' => $this->codigoComprobanteProveedor($row),
            'proveedor' => (string) ($row->proveedores->nombre ?? ''),
            'fecha' => $fechaYmd,
            'fecha_corta' => $this->formatearFechaCorta($row->fechacomprobante),
            'importe' => $importe,
            'cotizacion' => $cotizacion,
        ];
    }

    public function consultaRecepcion(Request $request)
    {
        can('listar-importacion');
        $empresaId = (int) $request->input('empresa_id', 0);
        $consulta = trim((string) $request->input('consulta', ''));

        $query = Recepcion_Proveedor::query()
            ->with(['proveedores:id,nombre', 'ordencompras:id,numeroordencompra'])
            ->orderByDesc('id')
            ->limit(80);

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        } else {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');
        }

        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('numerorecepcion', 'like', '%'.$consulta.'%')
                    ->orWhere('id', 'like', '%'.$consulta.'%')
                    ->orWhereHas('proveedores', function ($pq) use ($consulta) {
                        $pq->where('nombre', 'like', '%'.$consulta.'%');
                    })
                    ->orWhereHas('ordencompras', function ($oq) use ($consulta) {
                        $oq->where('numeroordencompra', 'like', '%'.$consulta.'%');
                    });
            });
        }

        $html = '';
        $puedeAbm = can('listar-recepcion-proveedor', false) || can('editar-recepcion-proveedor', false);
        foreach ($query->get() as $row) {
            $desc = trim(($row->proveedores->nombre ?? '').' · '.$this->formatearFechaCorta($row->fecha));
            $html .= '<tr data-id="'.(int) $row->id
                .'" data-numero="'.e((string) $row->numerorecepcion)
                .'" data-desc="'.e($desc)
                .'" data-oc="'.e((string) ($row->ordencompras->numeroordencompra ?? ''))
                .'" data-proveedor="'.e((string) ($row->proveedores->nombre ?? ''))
                .'" data-fecha="'.e($this->formatearFechaCorta($row->fecha)).'">';
            $html .= '<td>'.(int) $row->id.'</td>';
            $html .= '<td>'.e((string) $row->numerorecepcion).'</td>';
            $html .= '<td>'.e((string) ($row->ordencompras->numeroordencompra ?? '')).'</td>';
            $html .= '<td>'.e((string) ($row->proveedores->nombre ?? '')).'</td>';
            $html .= '<td>'.e($this->formatearFechaCorta($row->fecha)).'</td>';
            $html .= '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsulta-importacion-com">Elegir</a>';
            if ($puedeAbm) {
                $url = route('editar_recepcion_proveedor', [
                    'id' => $row->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
                $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
            }
            $html .= '</td></tr>';
        }

        return response()->json([
            'data' => $html !== '' ? $html : '<tr><td colspan="6" class="text-muted">Sin resultados</td></tr>',
        ]);
    }

    public function resolverRecepcion(Request $request)
    {
        can('listar-importacion');
        $empresaId = (int) $request->input('empresa_id', 0);
        $valor = trim((string) $request->input('valor', $request->input('codigo', '')));
        if ($valor === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Ingrese Nº recepción']);
        }

        $query = Recepcion_Proveedor::query()
            ->with(['proveedores:id,nombre']);
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        } else {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');
        }

        if (ctype_digit($valor)) {
            $query->where(function ($q) use ($valor) {
                $q->where('numerorecepcion', (int) $valor)
                    ->orWhere('id', (int) $valor);
            });
        } else {
            $query->where('numerorecepcion', 'like', '%'.$valor.'%');
        }

        $row = $query->orderByDesc('id')->first();
        if (! $row) {
            return response()->json(['ok' => false, 'mensaje' => 'Recepción COM no encontrada']);
        }

        $desc = trim(($row->proveedores->nombre ?? '').' · '.$this->formatearFechaCorta($row->fecha));

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => (int) $row->id,
                'numero' => (string) $row->numerorecepcion,
                'desc' => $desc,
                'proveedor' => (string) ($row->proveedores->nombre ?? ''),
                'fecha' => $this->formatearFechaCorta($row->fecha),
            ],
        ]);
    }

    private function codigoComprobanteProveedor(Comprobante_Proveedor $row): string
    {
        $abrev = (string) ($row->tipotransaccion_compras->abreviatura ?? '');
        $letra = (string) ($row->letra ?? '');
        $suc = (string) ($row->sucursal ?? '');
        $nro = (string) ($row->numerocomprobante ?? '');

        return trim($abrev.' '.$letra.' '.$suc.'-'.$nro);
    }

    private function formatearFechaCorta(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('d/m/Y');
        }
        $ts = strtotime((string) $fecha);

        return $ts ? date('d/m/Y', $ts) : (string) $fecha;
    }

    /** @return array<string, mixed> */
    private function formShared(Request $request): array
    {
        return [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'moneda_query' => Moneda::query()->orderBy('nombre')->get(['id', 'nombre', 'abreviatura']),
            'incoterm_query' => Incoterm::query()->orderBy('nombre')->get(['id', 'nombre', 'abreviatura']),
            'gasto_tipos' => Importacion_Gasto_Tipo::query()->where('activo', true)->orderBy('orden')->get(),
            'estados' => ImportacionEstados::paraSelect(),
            'filtrosQuery' => QueryRetornoListado::desdeRequest($request, ImportacionListadoFiltros::class),
        ];
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'Empresa no permitida');
        }
    }

    /** @return array<string, mixed> */
    private function enriquecerPayload(ValidacionImportacion $request): array
    {
        $data = $request->validated();
        $data['ordencompra_ids'] = collect((array) $request->input('ordencompra_ids', []))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();
        $data['lote_ids'] = collect((array) $request->input('lote_ids', []))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        return $data;
    }

    private function guardarArchivosNuevos(int $importacionId, Request $request): void
    {
        if (! $request->hasFile('nombrearchivos')) {
            return;
        }
        foreach ($request->file('nombrearchivos') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $ruta = $file->store('importaciones/'.$importacionId, 'public');
            Importacion_Archivo::query()->create([
                'importacion_id' => $importacionId,
                'nombre_original' => $file->getClientOriginalName(),
                'ruta' => $ruta,
                'mime' => $file->getClientMimeType(),
                'tamano' => $file->getSize(),
                'usuario_id' => Auth::id(),
            ]);
        }
    }

    /**
     * @return list<array{articulo_id: ?int, ordencompra_articulo_id: ?int, sku: ?string, valor: float, cantidad: float, peso: float, volumen: float}>
     */
    private function partidasDesdeOrdenes($importacion): array
    {
        $partidas = [];
        $importacion->loadMissing('ordenescompra.ordencompra_articulos');
        foreach ($importacion->ordenescompra as $oc) {
            $lineas = $oc->ordencompra_articulos;
            if (! $lineas || $lineas->isEmpty()) {
                continue;
            }
            foreach ($lineas as $lin) {
                $cant = (float) ($lin->cantidad ?? $lin->cantidadpedida ?? 0);
                $precio = (float) ($lin->precio ?? $lin->preciounitario ?? 0);
                $partidas[] = [
                    'articulo_id' => isset($lin->articulo_id) ? (int) $lin->articulo_id : null,
                    'ordencompra_articulo_id' => (int) ($lin->id ?? 0) ?: null,
                    'sku' => $lin->sku ?? $lin->codigo ?? null,
                    'valor' => $cant * $precio,
                    'cantidad' => $cant,
                    'peso' => (float) ($lin->peso ?? 0),
                    'volumen' => (float) ($lin->volumen ?? 0),
                ];
            }
        }

        if ($partidas === []) {
            $partidas[] = [
                'articulo_id' => null,
                'ordencompra_articulo_id' => null,
                'sku' => null,
                'valor' => (float) $importacion->fob_estimado,
                'cantidad' => 1.0,
                'peso' => 0.0,
                'volumen' => 0.0,
            ];
        }

        return $partidas;
    }
}
