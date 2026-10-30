<?php

namespace App\Http\Controllers\Stock;

use App\Support\Database\SqlDialectSupport;
use App\Exports\Stock\FormulaArticuloExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionFormulaArticulo;
use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Models\Stock\Formula_Articulo_Archivo;
use App\Models\Stock\Formula_Articulo_Estado;
use App\Queries\Stock\FormulaArticuloQueryInterface;
use App\Repositories\Stock\Formula_Articulo_EstadoRepositoryInterface;
use App\Repositories\Stock\Formula_ArticuloRepositoryInterface;
use App\Services\Stock\FormulaArticuloAnitaSyncService;
use App\Services\Stock\FormulaArticuloService;
use App\Services\Stock\FormulaArticuloVinculoService;
use App\Services\Stock\FormulaArticuloCostoTotalService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoDisenadorPreviewSupport;
use App\Support\Listado\ListadoExportPresentacionSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoVistaMenuSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Listado\ListadoVisualSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Stock\ArticuloFormulasUsoSupport;
use App\Support\Stock\ArticuloPrecioUltimaCompraSupport;
use App\Support\Stock\FormulaArticuloGastronomia;
use App\Support\Stock\FormulaArticuloListadoColumnas;
use App\Support\Stock\FormulaArticuloListadoFiltros;
use App\Support\Stock\FormulaArticuloListadoPreferenciasUsuario;
use App\Support\Stock\RecepcionProveedorDepositoSupport;
use Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FormulaArticuloController extends Controller
{
    public function __construct(
        private Formula_ArticuloRepositoryInterface $formulaArticuloRepository,
        private FormulaArticuloQueryInterface $formulaArticuloQuery,
        private FormulaArticuloService $formulaArticuloService,
        private Formula_Articulo_EstadoRepositoryInterface $formulaArticuloEstadoRepository,
        private FormulaArticuloAnitaSyncService $formulaArticuloAnitaSyncService,
        private FormulaArticuloVinculoService $formulaArticuloVinculoService,
        private FormulaArticuloCostoTotalService $formulaArticuloCostoTotalService,
    ) {}

    public function index(Request $request)
    {
        can('listar-formula-articulo');

        if (! FormulaArticuloListadoFiltros::requestTraeFiltros($request)) {
            $guardados = FormulaArticuloListadoFiltros::guardados();
            if ($guardados !== []) {
                $redirect = redirect()->route('consultar_formula_articulo', $guardados);
                foreach (['mensaje', 'errores', 'error'] as $flash) {
                    $valor = session($flash);
                    if ($valor) {
                        $redirect->with($flash, $valor);
                    }
                }

                return $redirect;
            }
        }

        $armado = $this->armarListado($request);
        if ($armado instanceof RedirectResponse) {
            return $armado;
        }

        return view('stock.formula_articulo.index', $armado);
    }

    /**
     * Importación masiva desde Anita (ApiAnita). Puede superar el timeout del proxy web (504);
     * en ese caso usar: php artisan formula-articulo:sincronizar-anita
     */
    public function sincronizarDesdeAnita(Request $request)
    {
        can('actualizar-formula-articulo');

        if (! config('app.anita_sync_formula_articulo_index')) {
            abort(403);
        }

        if (! $request->isMethod('post')) {
            abort(405);
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        set_time_limit(0);
        ignore_user_abort(true);

        try {
            $usuarioId = Auth::check() ? (int) Auth::id() : 1;
            $ret = $this->formulaArticuloAnitaSyncService->sincronizarDesdeApi($usuarioId);
            $msg = 'Sincronización desde Anita: '.$ret['formulas'].' fórmulas, '.$ret['lineas'].' líneas de detalle.';
            if (! empty($ret['advertencias'])) {
                $msg .= ' '.implode(' ', array_slice($ret['advertencias'], 0, 8));
            }

            return redirect()->route('consultar_formula_articulo')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('FormulaArticulo sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('consultar_formula_articulo')->with('errores', [
                'No se completó la sincronización desde Anita. Si el error fue por tiempo de espera (504), ejecute en el servidor: php artisan formula-articulo:sincronizar-anita — Detalle: '.$e->getMessage(),
            ]);
        }
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-formula-articulo');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, is_string($busqueda) ? $busqueda : null);
        $columnasVisibles = $this->columnasDesdeRequest($request);
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            FormulaArticuloListadoColumnas::RECURSO,
            FormulaArticuloListadoColumnas::catalogoActivo()
        );
        $subtitulo = $this->subtituloListado($filtros, $etiquetas);

        switch ($formato) {
            case 'PDF':
                $formulas = $this->formulaArticuloQuery->leeListado($filtros, false, true);
                ArticuloPrecioUltimaCompraSupport::enriquecerFormulasPaginadasConCosto($formulas);
                $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($formulas);
                $html = view('stock.formula_articulo.listado', [
                    'formulas' => $formulas,
                    'columnasVisibles' => $columnasVisibles,
                    'etiquetasColumnas' => $etiquetas,
                    'subtitulo' => $subtitulo,
                    'totalFilas' => $formulas->count(),
                    'logosCabecera' => $logos,
                ])->render();
                $ruta = storage_path('pdf/listados/listado_formula_articulo.pdf');
                if (! is_dir(dirname($ruta))) {
                    mkdir(dirname($ruta), 0777, true);
                }
                DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                    'titulo_corto' => 'Fórmulas de artículos',
                ]);

                return response()->download($ruta, 'formulas_articulos.pdf');

            case 'EXCEL':
                return (new FormulaArticuloExport($this->formulaArticuloQuery))
                    ->parametros($filtros, $columnasVisibles, $etiquetas, $subtitulo)
                    ->download('formula_articulo.xlsx');

            case 'CSV':
                return (new FormulaArticuloExport($this->formulaArticuloQuery))
                    ->parametros($filtros, $columnasVisibles, $etiquetas, $subtitulo)
                    ->download('formula_articulo.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_formula_articulo', FormulaArticuloListadoFiltros::paraQueryString($filtros));
    }

    public function previewWorkbench(Request $request)
    {
        can('listar-formula-articulo');

        $filtros = $this->resolverFiltrosListado($request);
        $filtros['_per_page'] = ListadoDisenadorPreviewSupport::LIMITE_MUESTRA;
        $layout = FormulaArticuloListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
        $page = $this->formulaArticuloQuery->leeListado($filtros, true, true);
        $total = method_exists($page, 'total') ? (int) $page->total() : $page->count();
        $filas = method_exists($page, 'getCollection') ? $page->getCollection() : $page;
        $orden = ListadoOrdenamientoSupport::normalizar(
            $request->input('sort', $filtros['sort'] ?? []),
            FormulaArticuloListadoFiltros::camposOrdenables()
        );
        $agrupar = ListadoAgrupacionSupport::normalizar(
            $request->input('group', $filtros['agrupar'] ?? []),
            FormulaArticuloListadoFiltros::camposOrdenables()
        );
        $filtrosCortes = $filtros;
        $filtrosCortes['agrupar'] = $agrupar;
        $cortes = $agrupar !== []
            ? $this->formulaArticuloQuery->cortesListado($filtrosCortes)
            : ['activo' => false];

        return response()->json(ListadoDisenadorPreviewSupport::payload(
            $layout,
            $orden,
            $agrupar,
            $filas,
            static fn (object $row, string $key): string => FormulaArticuloListadoColumnas::valorCelda($row, $key),
            $total,
            $etiquetas,
            $cortes
        ));
    }

    public function guardarVistaListado(Request $request)
    {
        can('listar-formula-articulo');

        $filtros = $this->resolverFiltrosListado($request);
        $layout = FormulaArticuloListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $orden = $filtros['sort'] ?? [];
        $vista = ListadoVistaSupport::guardar(
            FormulaArticuloListadoColumnas::RECURSO,
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
            return redirect()->route('consultar_formula_articulo', FormulaArticuloListadoFiltros::paraQueryString($filtros))
                ->with('error', 'No se pudo guardar la vista.');
        }
        ListadoVistaMenuSupport::sincronizar($vista, $request->boolean('crear_en_menu'));
        $qs = FormulaArticuloListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs['vista_id'] = $vista->id;
        FormulaArticuloListadoFiltros::persistir($qs);

        return redirect()->route('consultar_formula_articulo', $qs)
            ->with('mensaje', 'Vista «'.$vista->nombre.'» guardada.');
    }

    public function eliminarVistaListado(int $id)
    {
        can('listar-formula-articulo');
        $ok = ListadoVistaSupport::eliminar($id, FormulaArticuloListadoColumnas::RECURSO, (int) auth()->id());
        FormulaArticuloListadoFiltros::olvidar();

        return redirect()->route('consultar_formula_articulo', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        can('listar-formula-articulo');
        $layout = FormulaArticuloListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $vistaId = $request->filled('vista_id') ? (int) $request->input('vista_id') : 0;
        if ($vistaId > 0 && $request->boolean('actualizar_vista')) {
            $vista = ListadoVistaSupport::findParaUsuario($vistaId, FormulaArticuloListadoColumnas::RECURSO, (int) auth()->id());
            if ($vista && (int) $vista->usuario_id === (int) auth()->id()) {
                $vista->columnas_json = $layout;
                $vista->save();
            }
        } else {
            FormulaArticuloListadoPreferenciasUsuario::persistirGrillaEstandar($layout);
        }
        $filtros = $this->resolverFiltrosListado($request);
        $qs = FormulaArticuloListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs[$vistaId > 0 ? 'vista_id' : 'vista_estandar'] = $vistaId > 0 ? $vistaId : 1;

        return redirect()->route('consultar_formula_articulo', $qs)->with('mensaje', 'Grilla actualizada.');
    }

    public function guardarEtiquetasListado(Request $request)
    {
        can('listar-formula-articulo');
        $etiquetas = $request->input('etiquetas', []);
        if (! is_array($etiquetas)) {
            $etiquetas = [];
        }
        ListadoColumnaEtiquetaSupport::guardar(
            FormulaArticuloListadoColumnas::RECURSO,
            $etiquetas,
            array_keys(FormulaArticuloListadoColumnas::catalogoActivo())
        );

        return redirect()->route(
            'consultar_formula_articulo',
            FormulaArticuloListadoFiltros::paraQueryString($this->resolverFiltrosListado($request))
        )->with('mensaje', 'Etiquetas actualizadas.');
    }

    /**
     * Costo total estimado de la fórmula (JSON). Reutilizable desde otros módulos vía servicio.
     */
    public function costoTotal(int $id, Request $request)
    {
        if (! can('listar-formula-articulo', false)
            && ! can('crear-formula-articulo', false)
            && ! can('editar-formula-articulo', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        $opcionales = $request->input('opcionales', null);
        if (is_array($opcionales)) {
            $opcMap = [];
            foreach ($opcionales as $k => $v) {
                $opcMap[(string) $k] = $v !== null && $v !== '' ? (int) $v : null;
            }
            $opcionales = $opcMap;
        } else {
            $opcionales = null;
        }

        try {
            $result = $this->formulaArticuloCostoTotalService->calcular($id, $opcionales);
        } catch (\Throwable $e) {
            Log::warning('FormulaArticulo costoTotal: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'No se pudo calcular el costo total.'], 500);
        }

        return response()->json([
            'total' => round($result->total, 4),
            'total_por_unidad' => round($result->totalPorUnidadFormula(), 4),
            'completo' => $result->completo,
            'cantidad_unidad' => $result->cantidadUnidad,
            'advertencias' => $result->advertencias,
        ]);
    }

    /**
     * Precio última compra (ERP → Anita stkm_pre_compra3 → artículo) para ítems de fórmula; no se persiste.
     */
    public function costosUltimaCompra(Request $request)
    {
        if (! can('listar-formula-articulo', false)
            && ! can('crear-formula-articulo', false)
            && ! can('editar-formula-articulo', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        $skus = $request->input('skus', []);
        if (! is_array($skus)) {
            $skus = [$skus];
        }

        $costos = ArticuloPrecioUltimaCompraSupport::resolverPreciosPorSkus($skus);

        return response()->json(['costos' => $costos]);
    }

    public function crear()
    {
        can('crear-formula-articulo');

        $deposito_query = Depmae::query()->paraUsuarioAutorizado()->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))->get();
        $data = null;
        $estado_enum = Formula_Articulo_Estado::$enumEstado;

        return view('stock.formula_articulo.crear', compact('data', 'deposito_query', 'estado_enum'));
    }

    public function guardar(ValidacionFormulaArticulo $request)
    {
        can('crear-formula-articulo');

        $ret = $this->formulaArticuloService->guardar($request);
        if ($ret['mensaje'] === 'ok') {
            return redirect()->route('consultar_formula_articulo')->with('mensaje', 'Fórmula creada con éxito');
        }

        return redirect()->back()->withInput()->with('errores', [$ret['errores'] ?? 'Error al guardar']);
    }

    public function editar(Request $request, int $id)
    {
        can('editar-formula-articulo');

        $data = $this->formulaArticuloRepository->find($id);
        ArticuloPrecioUltimaCompraSupport::enriquecerLineasFormulaConCosto($data->formula_articulo_hijos);
        $costoTotal = $this->formulaArticuloCostoTotalService->calcular($id);
        $deposito_query = Depmae::query()->paraUsuarioAutorizado()->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))->get();
        $estado_enum = Formula_Articulo_Estado::$enumEstado;
        $retornoArticulo = $this->resolverRetornoArticulo($request);
        $ocultarVolver = $request->query('origen') === 'modal_consulta';

        return view('stock.formula_articulo.editar', compact('data', 'deposito_query', 'estado_enum', 'retornoArticulo', 'ocultarVolver', 'costoTotal'));
    }

    public function actualizar(ValidacionFormulaArticulo $request, int $id)
    {
        can('actualizar-formula-articulo');

        $ret = $this->formulaArticuloService->actualizar($request, $id);
        if ($ret['mensaje'] === 'ok') {
            return $this->redirectDespuesDeActualizarFormula($request, 'Fórmula actualizada con éxito', $id);
        }

        return redirect()->back()->withInput()->with('errores', [$ret['errores'] ?? 'Error al actualizar']);
    }

    public function eliminar(Request $request, int $id)
    {
        can('borrar-formula-articulo');

        $ret = $this->formulaArticuloService->eliminar($id);
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['mensaje' => $ret['mensaje'] === 'ok' ? 'ok' : 'ng', 'error' => $ret['errores'] ?? '']);
        }

        return redirect()->route('consultar_formula_articulo')->with('mensaje', $ret['mensaje'] === 'ok' ? 'Eliminado' : ($ret['errores'] ?? 'Error'));
    }

    public function descargarArchivo(int $id, int $archivo)
    {
        if (! can('listar-formula-articulo', false) && ! can('editar-formula-articulo', false)) {
            abort(403);
        }

        $registro = Formula_Articulo_Archivo::query()
            ->where('id', $archivo)
            ->where('formula_articulo_id', $id)
            ->first();
        if (! $registro) {
            abort(404);
        }

        $basename = basename((string) $registro->nombrearchivo);
        if ($basename === '' || str_contains($registro->nombrearchivo, '..')) {
            abort(404);
        }

        $path = public_path('storage/archivos/formulas_articulo/'.$id.'/'.$basename);
        if (! is_file($path)) {
            abort(404);
        }

        return response()->download($path, $basename);
    }

    public function leerHistoria(int $formula_articulo_id)
    {
        if (! can('listar-formula-articulo', false) && ! can('editar-formula-articulo', false)) {
            abort(403);
        }

        return $this->formulaArticuloEstadoRepository->leeHistoria($formula_articulo_id);
    }

    /**
     * Artículos de compra cuyo SKU alt./insumo apunta al artículo insumo indicado.
     */
    public function articulosCompraPorInsumo(int $articulo_id)
    {
        if (! can('listar-formula-articulo', false)
            && ! can('editar-formula-articulo', false)
            && ! can('listar-articulos', false)) {
            abort(403);
        }

        $insumo = Articulo::query()->find($articulo_id, ['id', 'sku', 'descripcion', 'empresa_id']);
        if (! $insumo) {
            return response()->json(['datos' => [], 'insumo' => null, 'mensaje' => 'Artículo no encontrado.']);
        }

        RecepcionProveedorDepositoSupport::reiniciarCache();

        $empresaId = (int) ($insumo->empresa_id ?? 0);
        $empresaId = $empresaId > 0 ? $empresaId : null;

        $rows = RecepcionProveedorDepositoSupport::resolverArticulosCompraDesdeInsumo($insumo, $empresaId)
            ->sortBy('sku')
            ->values()
            ->map(static function (Articulo $compra): array {
                return [
                    'id' => (int) $compra->id,
                    'sku' => (string) $compra->sku,
                    'descripcion' => (string) ($compra->descripcion ?? ''),
                    'skualternativo' => (string) ($compra->skualternativo ?? ''),
                ];
            })
            ->all();

        return response()->json([
            'datos' => $rows,
            'insumo' => [
                'id' => (int) $insumo->id,
                'sku' => (string) $insumo->sku,
                'descripcion' => (string) ($insumo->descripcion ?? ''),
            ],
        ]);
    }

    /**
     * Fórmulas que incluyen el artículo como componente o como subfórmula.
     */
    public function formulasPorArticulo(int $articulo_id)
    {
        if (! can('listar-formula-articulo', false)
            && ! can('editar-formula-articulo', false)
            && ! can('listar-articulos', false)) {
            abort(403);
        }

        $articulo = Articulo::query()->find($articulo_id, ['id', 'sku', 'descripcion']);
        if (! $articulo) {
            return response()->json(['datos' => [], 'articulo' => null, 'mensaje' => 'Artículo no encontrado.']);
        }

        $filas = ArticuloFormulasUsoSupport::listar((int) $articulo->id);
        foreach ($filas as &$fila) {
            unset($fila['linea_id']);
        }
        unset($fila);

        return response()->json([
            'datos' => $filas,
            'articulo' => [
                'id' => (int) $articulo->id,
                'sku' => (string) $articulo->sku,
                'descripcion' => (string) ($articulo->descripcion ?? ''),
            ],
        ]);
    }

    /**
     * Artículos con articulo.formula en esta fórmula o en alguna que la incluye como subfórmula.
     */
    public function articulosAsociados(int $id)
    {
        if (! can('listar-formula-articulo', false) && ! can('editar-formula-articulo', false)) {
            abort(403);
        }

        $formulaIds = $this->formulaArticuloService->idsFormulasParaArticulosAsociados($id);
        if ($formulaIds === []) {
            return response()->json(['datos' => []]);
        }

        $rows = Articulo::query()
            ->whereIn('formula', $formulaIds)
            ->orderBy('sku')
            ->get(['id', 'sku', 'descripcion']);

        return response()->json(['datos' => $rows]);
    }

    /**
     * Vincula formula_articulo.articulo_id y articulo.formula según código → SKU V####.
     */
    public function vincularArticulosPorCodigo(Request $request)
    {
        can('actualizar-formula-articulo');

        ini_set('max_execution_time', '0');

        try {
            $ret = $this->formulaArticuloVinculoService->vincularPorCodigoSku(false);
            $msg = 'Vínculo código→SKU: '.$ret['formulas_vinculadas'].' fórmula(s), '
                .$ret['articulos_actualizados'].' artículo(s) con formula actualizada, '
                .$ret['articulos_corregidos'].' corregido(s) por SKU.';
            if (! empty($ret['sin_articulo'])) {
                $msg .= ' Sin artículo: '.count($ret['sin_articulo']).'.';
            }

            return redirect()->route('consultar_formula_articulo')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('FormulaArticulo vincularArticulosPorCodigo: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('consultar_formula_articulo')->with('errores', [
                'No se completó el vínculo: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Resuelve formula_articulo.id para consulta desde el CRUD de artículos.
     */
    public function resolverPorArticulo(int $articulo_id)
    {
        if (! can('listar-formula-articulo', false) && ! can('listar-articulos', false)) {
            return response()->json(['message' => 'No tiene permisos para esta consulta.'], 403);
        }

        return response()->json($this->formulaArticuloService->resolverIdParaArticulo($articulo_id));
    }

    /**
     * HTML para modal de consulta desde el CRUD de artículos.
     */
    public function modal(int $id)
    {
        if (! can('listar-formula-articulo', false)
            && ! can('listar-articulos', false)
            && ! can('crear-formula-articulo', false)
            && ! can('editar-formula-articulo', false)) {
            abort(403);
        }

        try {
            $data = $this->formulaArticuloRepository->find($id);
            ArticuloPrecioUltimaCompraSupport::enriquecerLineasFormulaConCosto($data->formula_articulo_hijos);
            $costoTotal = $this->formulaArticuloCostoTotalService->calcular($id);
        } catch (\Throwable $e) {
            abort(404);
        }

        return view('stock.formula_articulo.partials.modal_contenido', compact('data', 'costoTotal'));
    }

    /**
     * Búsqueda JSON de fórmulas para elegir subfórmula en el formulario.
     */
    public function buscarJson(Request $request)
    {
        if (! can('listar-formula-articulo', false) && ! can('crear-formula-articulo', false) && ! can('editar-formula-articulo', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        $consulta = trim((string) $request->get('consulta', ''));
        $excludeId = (int) $request->get('exclude_id', 0);

        $rows = $this->formulaArticuloQuery->leeFormulaArticulo($consulta, false, true);
        $out = [];
        foreach ($rows as $r) {
            if ($excludeId > 0 && (int) $r->id === $excludeId) {
                continue;
            }
            $out[] = [
                'id' => $r->id,
                'codigo' => $r->codigo,
                'sku' => $r->articulo_sku,
                'descripcion' => $r->articulo_descripcion,
                'detalle' => $r->detalle,
                'estado' => $r->estado,
            ];
            if (count($out) >= 40) {
                break;
            }
        }

        return response()->json(['datos' => $out]);
    }

    /**
     * @return array<string, mixed>|RedirectResponse
     */
    private function armarListado(Request $request): array|RedirectResponse
    {
        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $vistas = ListadoVistaSupport::listarParaUsuario(FormulaArticuloListadoColumnas::RECURSO, $usuarioId);
        $vistaActiva = null;
        $forzarEstandar = $request->boolean('vista_estandar')
            || $request->input('vista_modo') === 'estandar';

        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                FormulaArticuloListadoColumnas::RECURSO,
                $usuarioId
            );
        } elseif (
            ! $forzarEstandar
            && ! $request->has('filtro_valor')
            && ! $request->has('busqueda')
            && ! $request->has('qbe')
            && ! $request->boolean('limpiar_filtros')
            && ! $request->boolean('filtro_limpiar')
            && ! $request->has('filtro_estado')
            && ! $request->has('filtro_articulos')
            && ! $request->has('con_opcionales')
        ) {
            $vistaActiva = ListadoVistaSupport::defaultDelUsuario(FormulaArticuloListadoColumnas::RECURSO, $usuarioId)
                ?? ListadoVistaSupport::defaultDelRol(FormulaArticuloListadoColumnas::RECURSO, (int) session('rol_id'));
        }

        $filtrosRequest = ListadoVistaSupport::prepararQbeContraVista(
            $this->resolverFiltrosListado($request),
            $request
        );
        $filtros = $filtrosRequest;
        if ($vistaActiva && is_array($vistaActiva->filtros_json)) {
            $filtros = FormulaArticuloListadoFiltros::fusionarDesdeVista($filtros, $vistaActiva->filtros_json);
        }
        unset($filtros['_qbe_explicito']);
        $filtros = ListadoVisualSupport::aplicarPedido(
            $filtros,
            $request,
            ($vistaActiva && is_array($vistaActiva->filtros_json)) ? $vistaActiva->filtros_json : null,
            FormulaArticuloListadoFiltros::camposOrdenables()
        );
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
            $params = FormulaArticuloListadoFiltros::paraQueryString($filtros);
            if ($vistaActiva) {
                $params['vista_id'] = $vistaActiva->id;
            } elseif ($forzarEstandar) {
                $params['vista_estandar'] = 1;
            }

            return redirect()
                ->route('consultar_formula_articulo', $params)
                ->with('mensaje', 'Se quitó el orden de la grilla.');
        }

        $catalogo = FormulaArticuloListadoColumnas::catalogoActivo();
        $etiquetasInstalacion = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            FormulaArticuloListadoColumnas::RECURSO,
            $catalogo
        );
        if ($vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== []) {
            $grillaLayout = FormulaArticuloListadoPreferenciasUsuario::normalizarLayout($vistaActiva->columnas_json);
        } else {
            $grillaLayout = FormulaArticuloListadoPreferenciasUsuario::grillaEstandar();
        }
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);

        $formulas = $this->formulaArticuloQuery->leeListado($filtros, true, true);
        ArticuloPrecioUltimaCompraSupport::enriquecerFormulasPaginadasConCosto($formulas);
        $this->formulaArticuloCostoTotalService->enriquecerFormulasConCostoTotal($formulas);

        $cortes = ['activo' => false];
        if (ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], FormulaArticuloListadoFiltros::camposOrdenables()) !== []) {
            $cortes = $this->formulaArticuloQuery->cortesListado($filtros);
        }

        $camposFiltro = FormulaArticuloListadoFiltros::camposQbeDisponibles();
        foreach ($camposFiltro as $key => $meta) {
            $camposFiltro[$key]['label'] = $etiquetas[$key] ?? $etiquetasInstalacion[$key] ?? $meta['label'];
        }

        $filtrosQuery = FormulaArticuloListadoFiltros::paraQueryString($filtros);
        $filtrosQuery = array_merge($filtrosQuery, ListadoVisualSupport::paraQueryString($filtros));
        $filtrosQuery['columnas'] = implode(',', $columnasVisibles);
        if ($vistaActiva) {
            $filtrosQuery['vista_id'] = $vistaActiva->id;
        } elseif ($forzarEstandar) {
            $filtrosQuery['vista_estandar'] = 1;
        }

        if ($request->boolean('filtro_limpiar')) {
            FormulaArticuloListadoFiltros::olvidar();
        } elseif (FormulaArticuloListadoFiltros::requestTraeFiltros($request)) {
            $page = (int) $request->query('page', 0);
            $guardar = $filtrosQuery;
            if ($page > 1) {
                $guardar['page'] = $page;
            }
            FormulaArticuloListadoFiltros::persistir($guardar);
        }

        return [
            'formulas' => $formulas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => $camposFiltro,
            'opcionalesHabilitados' => FormulaArticuloGastronomia::opcionalesHabilitados(),
            'estado_enum' => Formula_Articulo_Estado::$enumEstado,
            'sinFormulasCargadas' => (int) ($formulas->total() ?? 0) === 0 && ! FormulaArticuloListadoFiltros::tieneCriteriosAplicados($filtros),
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
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        return FormulaArticuloListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
    }

    /**
     * @return list<string>
     */
    private function columnasDesdeRequest(Request $request): array
    {
        $columnasRequest = $request->input('columnas');
        if (is_string($columnasRequest)) {
            $columnasRequest = array_filter(array_map('trim', explode(',', $columnasRequest)));
        }

        return FormulaArticuloListadoPreferenciasUsuario::resolverColumnas(
            is_array($columnasRequest) ? $columnasRequest : null
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, string>  $etiquetas
     */
    private function subtituloListado(array $filtros, array $etiquetas): string
    {
        $partes = [];
        $estado = FormulaArticuloListadoFiltros::normalizarEstado($filtros['estado'] ?? '');
        if ($estado !== '') {
            $partes[] = 'Estado: '.$estado;
        }
        $articulos = FormulaArticuloListadoFiltros::etiquetaArticulos((string) ($filtros['articulos'] ?? ''));
        if ($articulos !== '') {
            $partes[] = $articulos;
        }
        $opc = FormulaArticuloListadoFiltros::normalizarOpcionales($filtros['con_opcionales'] ?? '');
        if ($opc === 'si') {
            $partes[] = 'Con opcionales';
        } elseif ($opc === 'no') {
            $partes[] = 'Sin opcionales';
        }
        $presentacion = $filtros;
        $presentacion['orden'] = $filtros['sort'] ?? [];
        $texto = ListadoExportPresentacionSupport::subtitulo(
            $presentacion,
            $etiquetas,
            FormulaArticuloListadoFiltros::camposOrdenables()
        );
        if ($texto !== '') {
            $partes[] = $texto;
        }

        return implode(' · ', $partes);
    }

    /**
     * @return array{articulo_id: int, origen: string}|null
     */
    private function resolverRetornoArticulo(Request $request): ?array
    {
        $articuloId = (int) $request->input('retorno_articulo_id', $request->query('retorno_articulo_id', 0));
        $origen = (string) $request->input('retorno_origen', $request->query('retorno_origen', ''));

        if ($articuloId <= 0 || ! in_array($origen, ['index', 'editar'], true)) {
            return null;
        }

        return [
            'articulo_id' => $articuloId,
            'origen' => $origen,
        ];
    }

    private function redirectDespuesDeActualizarFormula(Request $request, string $mensaje, int $formulaId)
    {
        if ($request->input('origen') === 'modal_consulta') {
            return redirect()
                ->route('editar_formula_articulo', ['id' => $formulaId, 'origen' => 'modal_consulta'])
                ->with('mensaje', $mensaje);
        }

        $retorno = $this->resolverRetornoArticulo($request);
        if ($retorno === null) {
            return redirect()->route('consultar_formula_articulo')->with('mensaje', $mensaje);
        }

        $url = $retorno['origen'] === 'editar'
            ? route('editar_articulo', ['id' => $retorno['articulo_id']])
            : route('articulo');

        return redirect($url)->with('mensaje', $mensaje);
    }
}
