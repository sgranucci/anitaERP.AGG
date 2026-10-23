<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\BienUsoListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionBienUso;
use App\Models\Contable\BienUso;
use App\Models\Configuracion\Moneda;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\BienUsoRepositoryInterface;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Facades\Schema;
use App\Support\Contable\BienUsoListadoColumnas;
use App\Support\Contable\BienUsoListadoFiltros;
use App\Support\Contable\BienUsoListadoPreferenciasUsuario;
use App\Support\Contable\BienUsoVisibilidadSupport;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoLienzoSupport;
use App\Support\Listado\ListadoVisualSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoDisenadorPreviewSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoVistaMenuSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Stock\BienUsoAsignacionSupport;
use Illuminate\Http\Request;

class BienUsoController extends Controller
{
    private BienUsoRepositoryInterface $repository;

    private EmpresaRepositoryInterface $empresaRepository;

    public function __construct(BienUsoRepositoryInterface $repository, EmpresaRepositoryInterface $empresaRepository)
    {
        $this->repository = $repository;
        $this->empresaRepository = $empresaRepository;
    }

    public function index(Request $request)
    {
        can('listar-bien-uso');

        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $vistas = ListadoVistaSupport::listarParaUsuario(BienUsoListadoColumnas::RECURSO, $usuarioId);
        $vistaActiva = null;
        $forzarEstandar = $request->boolean('vista_estandar')
            || $request->input('vista_modo') === 'estandar';

        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                BienUsoListadoColumnas::RECURSO,
                $usuarioId
            );
        } elseif (
            ! $forzarEstandar
            && ! $request->has('filtro_valor')
            && ! $request->has('qbe')
            && ! $request->boolean('limpiar_filtros')
            && ! $request->boolean('filtro_limpiar')
            && ! $request->has('filtro_estado')
            && ! $request->has('empresa_id')
            && ! $request->has('empresa_todas')
            && ! $request->has('filtro_centrocosto_id')
        ) {
            $vistaActiva = ListadoVistaSupport::defaultDelUsuario(BienUsoListadoColumnas::RECURSO, $usuarioId);
        }

        $filtrosRequest = ListadoVistaSupport::prepararQbeContraVista(
            $this->resolverFiltrosListado($request),
            $request
        );
        $filtros = $filtrosRequest;
        if ($vistaActiva && is_array($vistaActiva->filtros_json)) {
            $filtros = BienUsoListadoFiltros::fusionarDesdeVista($filtros, $vistaActiva->filtros_json);
        }
        unset($filtros['_qbe_explicito']);
        $filtros = ListadoVisualSupport::aplicarPedido(
            $filtros,
            $request,
            ($vistaActiva && is_array($vistaActiva->filtros_json)) ? $vistaActiva->filtros_json : null,
            BienUsoListadoFiltros::camposOrdenables()
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

        $catalogo = BienUsoListadoColumnas::catalogoActivo();
        $etiquetasInstalacion = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            BienUsoListadoColumnas::RECURSO,
            $catalogo
        );
        if ($vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== []) {
            $grillaLayout = BienUsoListadoPreferenciasUsuario::normalizarLayout($vistaActiva->columnas_json);
        } else {
            $grillaLayout = BienUsoListadoPreferenciasUsuario::grillaEstandar();
        }
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);

        $datas = $this->repository->leeBienUso($filtros, true);

        $cortes = ['activo' => false];
        if (ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], BienUsoListadoFiltros::camposOrdenables()) !== []) {
            $cortes = $this->repository->cortesBienUso($filtros);
        }

        $camposFiltro = BienUsoListadoFiltros::camposQbeDisponibles();
        foreach ($camposFiltro as $key => $meta) {
            $camposFiltro[$key]['label'] = $etiquetas[$key] ?? $etiquetasInstalacion[$key] ?? $meta['label'];
        }

        $filtrosQuery = BienUsoListadoFiltros::paraQueryString($filtros);
        $filtrosQuery = array_merge($filtrosQuery, ListadoVisualSupport::paraQueryString($filtros));
        $filtrosQuery['columnas'] = implode(',', $columnasVisibles);
        if ($request->boolean('filtro_limpiar')) {
            $filtrosQuery['filtro_limpiar'] = 1;
        }
        if ($vistaActiva) {
            $filtrosQuery['vista_id'] = $vistaActiva->id;
        } elseif ($forzarEstandar) {
            $filtrosQuery['vista_estandar'] = 1;
        }

        return view('contable.bien_uso.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => $camposFiltro,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'estado_enum' => BienUso::$enumEstado,
            'centrocosto_opciones' => BienUsoVisibilidadSupport::opcionesCentrocostoAbm(),
            'tipo_bien_enum' => BienUso::$enumTipoBien,
            'puede_ver_bien_uso' => can('editar-bien-uso', false) || can('listar-bien-uso', false),
            'alcance_centro_costo' => BienUsoVisibilidadSupport::etiquetaAlcanceActivo(),
            'filtro_centrocosto_restringido' => BienUsoVisibilidadSupport::tieneRestriccionPorPerfil(),
            'columnasVisibles' => $columnasVisibles,
            'grillaLayout' => $grillaLayout,
            'catalogoColumnas' => $catalogo,
            'etiquetasColumnas' => $etiquetas,
            'etiquetasInstalacion' => $etiquetasInstalacion,
            'vistasListado' => $vistas,
            'vistaActiva' => $vistaActiva,
            'workbenchListo' => ListadoVistaSupport::tablasDisponibles(),
            'cortes' => $cortes,
            'graficoSeries' => ($graficoSeries = ListadoVisualSupport::seriesDeConsulta(
                $filtros,
                BienUsoListadoFiltros::camposOrdenables(),
                $etiquetas,
                fn (array $para): array => $this->repository->cortesBienUso($para)
            )),
            'graficoSerie' => $graficoSeries[0] ?? ['labels' => [], 'series' => [], 'tipo' => '', 'titulo' => ''],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;

        return BienUsoListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            $empresaDefault ? (int) $empresaDefault : null
        );
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-bien-uso');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
        $columnasRequest = $request->input('columnas');
        if (is_string($columnasRequest)) {
            $columnasRequest = array_filter(array_map('trim', explode(',', $columnasRequest)));
        }
        $columnasVisibles = BienUsoListadoPreferenciasUsuario::resolverColumnas(
            is_array($columnasRequest) ? $columnasRequest : null
        );
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            BienUsoListadoColumnas::RECURSO,
            BienUsoListadoColumnas::catalogoActivo()
        );

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeBienUso($filtros, false);

                $view = \View::make('contable.bien_uso.listado', [
                    'datas' => $datas,
                    'estado_enum' => BienUso::$enumEstado,
                    'tipo_bien_enum' => BienUso::$enumTipoBien,
                    'columnasVisibles' => $columnasVisibles,
                    'etiquetasColumnas' => $etiquetas,
                    'filtros' => $filtros,
                ])->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_bien_uso';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new BienUsoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('bien_uso.xlsx');

            case 'CSV':
                return (new BienUsoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('bien_uso.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('bien_uso', BienUsoListadoFiltros::paraQueryString($filtros));
    }

    public function previewWorkbench(Request $request)
    {
        can('listar-bien-uso');

        $filtros = $this->resolverFiltrosListado($request);
        $filtros['_per_page'] = ListadoDisenadorPreviewSupport::LIMITE_MUESTRA;
        $layout = BienUsoListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
        $page = $this->repository->leeBienUso($filtros, true);
        $total = method_exists($page, 'total') ? (int) $page->total() : $page->count();
        $filas = method_exists($page, 'getCollection') ? $page->getCollection() : $page;
        $orden = ListadoOrdenamientoSupport::normalizar(
            $request->input('sort', $filtros['sort'] ?? []),
            BienUsoListadoFiltros::camposOrdenables()
        );
        $agrupar = ListadoAgrupacionSupport::normalizar(
            $request->input('group', $filtros['agrupar'] ?? []),
            BienUsoListadoFiltros::camposOrdenables()
        );
        $filtrosCortes = $filtros;
        $filtrosCortes['agrupar'] = $agrupar;
        $cortes = $agrupar !== [] ? $this->repository->cortesBienUso($filtrosCortes) : ['activo' => false];

        return response()->json(ListadoDisenadorPreviewSupport::payload(
            $layout,
            $orden,
            $agrupar,
            $filas,
            static fn (object $row, string $key): string => BienUsoListadoColumnas::valorCelda($row, $key),
            $total,
            $etiquetas,
            $cortes
        ));
    }

    public function guardarVistaListado(Request $request)
    {
        can('listar-bien-uso');

        $filtros = $this->resolverFiltrosListado($request);
        $layout = BienUsoListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $orden = $filtros['sort'] ?? [];
        $vista = ListadoVistaSupport::guardar(
            BienUsoListadoColumnas::RECURSO,
            (int) auth()->id(),
            (string) $request->input('nombre', ''),
            [
                'modo' => $filtros['modo'],
                'qbe' => $filtros['qbe'] ?? [],
                'sort' => $orden,
                'orden' => $orden,
                'agrupar' => $filtros['agrupar'] ?? [],
                'grafico' => ListadoLienzoSupport::primero($graficosVista = ListadoLienzoSupport::normalizar(
                    $request->input('graficos'),
                    $request->input('grafico'),
                    $request->exists('graficos'),
                    BienUsoListadoFiltros::camposOrdenables()
                )),
                'graficos' => $graficosVista,
            ],
            $layout,
            $request->boolean('es_default'),
            $request->boolean('compartida'),
            $request->filled('vista_id') ? (int) $request->input('vista_id') : null
        );
        if (! $vista) {
            return redirect()->route('bien_uso', BienUsoListadoFiltros::paraQueryString($filtros))
                ->with('error', 'No se pudo guardar la vista.');
        }
        ListadoVistaMenuSupport::sincronizar($vista, $request->boolean('crear_en_menu'));
        $qs = BienUsoListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs['vista_id'] = $vista->id;

        return redirect()->route('bien_uso', $qs)
            ->with('mensaje', 'Vista «'.$vista->nombre.'» guardada.');
    }

    public function eliminarVistaListado(int $id)
    {
        can('listar-bien-uso');
        $ok = ListadoVistaSupport::eliminar($id, BienUsoListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('bien_uso', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        can('listar-bien-uso');
        $layout = BienUsoListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $vistaId = $request->filled('vista_id') ? (int) $request->input('vista_id') : 0;
        if ($vistaId > 0 && $request->boolean('actualizar_vista')) {
            $vista = ListadoVistaSupport::findParaUsuario($vistaId, BienUsoListadoColumnas::RECURSO, (int) auth()->id());
            if ($vista && (int) $vista->usuario_id === (int) auth()->id()) {
                $vista->columnas_json = $layout;
                $vista->save();
            }
        } else {
            BienUsoListadoPreferenciasUsuario::persistirGrillaEstandar($layout);
        }
        $filtros = $this->resolverFiltrosListado($request);
        $qs = BienUsoListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs[$vistaId > 0 ? 'vista_id' : 'vista_estandar'] = $vistaId > 0 ? $vistaId : 1;

        return redirect()->route('bien_uso', $qs)->with('mensaje', 'Grilla actualizada.');
    }

    public function guardarEtiquetasListado(Request $request)
    {
        can('listar-bien-uso');
        $etiquetas = $request->input('etiquetas', []);
        if (! is_array($etiquetas)) {
            $etiquetas = [];
        }
        ListadoColumnaEtiquetaSupport::guardar(
            BienUsoListadoColumnas::RECURSO,
            $etiquetas,
            array_keys(BienUsoListadoColumnas::catalogoActivo())
        );

        return redirect()->route(
            'bien_uso',
            BienUsoListadoFiltros::paraQueryString($this->resolverFiltrosListado($request))
        )->with('mensaje', 'Etiquetas actualizadas.');
    }

    public function crear(Request $request)
    {
        can('crear-bien-uso');

        $data = new BienUso;
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, BienUsoListadoFiltros::class);

        return view('contable.bien_uso.crear', [
            'data' => $data,
            'estado_enum' => BienUso::$enumEstado,
            'centrocosto_opciones' => BienUsoVisibilidadSupport::opcionesCentrocostoAbm(),
            'tipo_bien_enum' => BienUso::$enumTipoBien,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'moneda_query' => Moneda::query()->orderBy('id')->get(['id', 'nombre', 'codigo']),
            'filtrosQuery' => $filtrosQuery,
        ]);
    }

    public function guardar(ValidacionBienUso $request)
    {
        can('crear-bien-uso');

        $this->validarCentrocostoPermitido((int) $request->input('centrocosto_id'));

        $this->repository->create($request->all());

        return redirect()->route('bien_uso', QueryRetornoListado::desdeRequest($request, BienUsoListadoFiltros::class))
            ->with('mensaje', 'Bien de uso creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            can('listar-bien-uso');
        } else {
            can('editar-bien-uso');
        }

        $data = $this->repository->findOrFail($id);
        BienUsoVisibilidadSupport::abortSiNoPuedeAccederRegistro($data);
        $data->load([
            'rubro',
            'subrubro',
            'cuentaOrigen',
            'cuentaAmort',
            'cuentaContrap',
            'centrocostos',
            'proveedor',
            'bienReemplazo',
            'moneda',
        ]);

        $inventarioActual = BienUsoAsignacionSupport::inventarioActual((int) $data->id);
        $historial = BienUsoAsignacionSupport::historialMovimientos((int) $data->id);
        $transferenciasPendientes = BienUsoAsignacionSupport::transferenciasPendientesEntrada((int) $data->id);
        $transferenciasPendientesSalida = BienUsoAsignacionSupport::transferenciasPendientesSalida((int) $data->id);

        $filtrosQuery = QueryRetornoListado::desdeRequest($request, BienUsoListadoFiltros::class);

        return view('contable.bien_uso.editar', [
            'data' => $data,
            'estado_enum' => BienUso::$enumEstado,
            'centrocosto_opciones' => BienUsoVisibilidadSupport::opcionesCentrocostoAbm(),
            'tipo_bien_enum' => BienUso::$enumTipoBien,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'moneda_query' => Moneda::query()->orderBy('id')->get(['id', 'nombre', 'codigo']),
            'soloConsulta' => $soloConsulta,
            'inventarioActual' => $inventarioActual,
            'historial' => $historial,
            'transferenciasPendientes' => $transferenciasPendientes,
            'transferenciasPendientesSalida' => $transferenciasPendientesSalida,
            'filtrosQuery' => $filtrosQuery,
        ]);
    }

    public function consulta(Request $request)
    {
        $this->assertPuedeConsultarBienUso();
        $consulta = trim((string) $request->input('consulta', ''));
        $query = BienUso::query()->select('id', 'codigo_bien', 'codigo_inventario', 'descripcion', 'hostname', 'estado');
        BienUsoVisibilidadSupport::aplicarScope($query);
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('codigo_bien', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('codigo_inventario', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('descripcion', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('hostname', 'LIKE', '%'.$consulta.'%');
            });
        }
        $ordenCol = Schema::hasColumn('bien_uso', 'codigo_bien') ? 'codigo_bien' : 'codigo_inventario';
        $rows = $query->orderByRaw(SqlDialectSupport::ordenCodigoAsc($ordenCol))->limit(200)->get();
        $puedeAbrirAbm = can('editar-bien-uso', false) || can('listar-bien-uso', false);
        $html = '';
        if ($rows->isEmpty()) {
            $html = '<tr><td colspan="4">Sin resultados</td></tr>';
        } else {
            foreach ($rows as $row) {
                $codigo = $row->codigo_bien ?? $row->codigo_inventario ?? '';
                $desc = $row->descripcion ?: ($row->hostname ?: ('#'.$row->id));
                $html .= '<tr>';
                $html .= '<td class="id">'.e($row->id).'</td>';
                $html .= '<td class="codigo">'.e($codigo).'</td>';
                $html .= '<td class="descripcion">'.e($desc).'</td>';
                $html .= '<td class="text-nowrap">';
                $html .= '<a class="btn btn-warning btn-sm eligeconsultabienuso">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_bien_uso', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $html .= '</td></tr>';
            }
        }

        return response()->json(['data' => $html]);
    }

    public function resolver(Request $request)
    {
        $this->assertPuedeConsultarBienUso();
        $valor = trim((string) $request->query('valor', ''));
        if ($valor === '') {
            return response()->json(['ok' => false]);
        }
        $query = BienUso::query();
        BienUsoVisibilidadSupport::aplicarScope($query);
        $row = $query->where(function ($q) use ($valor) {
            $q->where('codigo_bien', $valor)->orWhere('codigo_inventario', $valor);
        })->first(['id', 'codigo_bien', 'codigo_inventario', 'descripcion', 'hostname']);
        if (! $row) {
            return response()->json(['ok' => false, 'mensaje' => 'Bien de uso no encontrado']);
        }

        return response()->json([
            'ok' => true,
            'id' => (int) $row->id,
            'codigo' => (string) ($row->codigo_bien ?? $row->codigo_inventario ?? ''),
            'descripcion' => (string) ($row->descripcion ?: ($row->hostname ?: '')),
        ]);
    }

    private function assertPuedeConsultarBienUso(): void
    {
        foreach (['listar-bien-uso', 'crear-bien-uso', 'editar-bien-uso', 'actualizar-bien-uso'] as $permiso) {
            if (can($permiso, false)) {
                return;
            }
        }
        abort(403);
    }

    public function actualizar(ValidacionBienUso $request, $id)
    {
        if ($request->input('origen') === 'modal_consulta') {
            abort(403);
        }

        can('actualizar-bien-uso');

        $this->validarCentrocostoPermitido((int) $request->input('centrocosto_id'));
        $bien = $this->repository->findOrFail($id);
        BienUsoVisibilidadSupport::abortSiNoPuedeAccederRegistro($bien);

        $this->repository->update($request->all(), $id);

        return redirect()->route('bien_uso', QueryRetornoListado::desdeRequest($request, BienUsoListadoFiltros::class))
            ->with('mensaje', 'Bien de uso actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-bien-uso');

        $bien = $this->repository->findOrFail($id);
        BienUsoVisibilidadSupport::abortSiNoPuedeAccederRegistro($bien);

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    private function validarCentrocostoPermitido(int $centrocostoId): void
    {
        if (! BienUsoVisibilidadSupport::puedeAcceder($centrocostoId)) {
            abort(403, 'No puede asignar ese centro de costo al bien.');
        }
    }
}
