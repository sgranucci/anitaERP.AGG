<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\SancionReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Sueldos\Motivo_Sancion_Sueldos;
use App\Models\Sueldos\Tipo_Sancion_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sueldos\Empleado_Sancion_SueldosRepositoryInterface;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoDisenadorPreviewSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoLienzoSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoVistaMenuSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Listado\ListadoVisualSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use App\Support\Sueldos\EmpleadoSancionSupport;
use App\Support\Sueldos\SancionReporteListadoColumnas;
use App\Support\Sueldos\SancionReporteListadoFiltros;
use App\Support\Sueldos\SancionReporteListadoPreferenciasUsuario;
use Illuminate\Http\Request;

class SancionReporte_SueldosController extends Controller
{
    public function __construct(
        private Empleado_Sancion_SueldosRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-sancion-reporte-sueldos');

        [$filtros, $vistaActiva, $forzarEstandar] = $this->filtrosDePantalla($request);
        $filtrosConsulta = $filtros;
        $filtrosConsulta['agrupar'] = SancionReporteListadoFiltros::agruparEfectivo($filtros);

        $catalogo = SancionReporteListadoColumnas::catalogoActivo();
        $etiquetasInstalacion = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            SancionReporteListadoColumnas::RECURSO,
            $catalogo
        );
        if ($vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== []) {
            $grillaLayout = SancionReporteListadoPreferenciasUsuario::normalizarLayout($vistaActiva->columnas_json);
        } else {
            $grillaLayout = SancionReporteListadoPreferenciasUsuario::grillaEstandar();
        }
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);

        $datas = $this->repository->leeSancionesReporte($filtrosConsulta, true);
        $cortes = ['activo' => false];
        if ($filtrosConsulta['agrupar'] !== []) {
            $cortes = $this->repository->cortesSanciones($filtrosConsulta);
        }

        $paraTipo = $filtros;
        $paraTipo['agrupar'] = ['tipo'];
        $paraTipo['consolidar_empresas'] = true;
        $graficoPorTipo = $this->graficoDesdeCortes($this->repository->cortesSanciones($paraTipo));

        $camposFiltro = SancionReporteListadoFiltros::camposQbeDisponibles();
        foreach ($camposFiltro as $key => $meta) {
            $camposFiltro[$key]['label'] = $etiquetas[$key] ?? $etiquetasInstalacion[$key] ?? $meta['label'];
        }

        $filtrosQuery = $this->queryDeFiltros($filtros, $columnasVisibles, $vistaActiva, $forzarEstandar, $request);
        $tipoSel = ! empty($filtros['tipo_sancion_id'])
            ? Tipo_Sancion_Sueldos::query()->find((int) $filtros['tipo_sancion_id'])
            : null;
        $motivoSel = ! empty($filtros['motivo_sancion_id'])
            ? Motivo_Sancion_Sueldos::query()->find((int) $filtros['motivo_sancion_id'])
            : null;

        return view('sueldos.sancion_reporte.index', [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'filtros' => $filtros,
            'filtrosConsulta' => $filtrosConsulta,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => true,
            'datas' => $datas,
            'estados' => EmpleadoSancionSupport::ESTADOS,
            'camposFiltro' => $camposFiltro,
            'columnasVisibles' => $columnasVisibles,
            'grillaLayout' => $grillaLayout,
            'catalogoColumnas' => $catalogo,
            'etiquetasColumnas' => $etiquetas,
            'etiquetasInstalacion' => $etiquetasInstalacion,
            'vistasListado' => ListadoVistaSupport::listarParaUsuario(
                SancionReporteListadoColumnas::RECURSO,
                auth()->id() ? (int) auth()->id() : null
            ),
            'vistaActiva' => $vistaActiva,
            'workbenchListo' => ListadoVistaSupport::tablasDisponibles(),
            'cortes' => $cortes,
            'graficoPorTipo' => $graficoPorTipo,
            'graficoSeries' => ($graficoSeries = ListadoVisualSupport::seriesDeConsulta(
                $filtros,
                SancionReporteListadoFiltros::camposOrdenables(),
                $etiquetas,
                fn (array $para): array => $this->repository->cortesSanciones($para)
            )),
            'graficoSerie' => $graficoSeries[0] ?? ['labels' => [], 'series' => [], 'tipo' => '', 'titulo' => ''],
            'tipoSel' => $tipoSel,
            'motivoSel' => $motivoSel,
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-sancion-reporte-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        [$filtros] = $this->filtrosDePantalla($request);
        $filtros['agrupar'] = SancionReporteListadoFiltros::agruparEfectivo($filtros);
        $columnasRequest = $request->input('columnas');
        if (is_string($columnasRequest)) {
            $columnasRequest = array_filter(array_map('trim', explode(',', $columnasRequest)));
        }
        $columnasVisibles = SancionReporteListadoPreferenciasUsuario::resolverColumnas(
            is_array($columnasRequest) ? $columnasRequest : null
        );
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            SancionReporteListadoColumnas::RECURSO,
            SancionReporteListadoColumnas::catalogoActivo()
        );
        $datas = $this->repository->leeSancionesReporte($filtros, false);
        $subtitulo = SancionReporteListadoFiltros::subtitulo($filtros);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('sueldos.sancion_reporte.listado', [
                    'datas' => $datas,
                    'subtitulo' => $subtitulo,
                    'columnasVisibles' => $columnasVisibles,
                    'etiquetasColumnas' => $etiquetas,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre = 'listado_sancion_sueldos';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre.'.pdf');

                return response()->download($path.'/'.$nombre.'.pdf');
            case 'EXCEL':
                return app(SancionReporteExport::class)
                    ->parametros($filtros, $columnasVisibles, $etiquetas)
                    ->download('consultas_sanciones.xlsx');
            case 'CSV':
                return app(SancionReporteExport::class)
                    ->parametros($filtros, $columnasVisibles, $etiquetas)
                    ->download('consultas_sanciones.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('sancion_reporte_sueldos', SancionReporteListadoFiltros::paraQueryString($filtros, true));
    }

    public function previewWorkbench(Request $request)
    {
        can('listar-sancion-reporte-sueldos');

        [$filtros] = $this->filtrosDePantalla($request);
        $filtros['_per_page'] = ListadoDisenadorPreviewSupport::LIMITE_MUESTRA;
        $filtros['agrupar'] = ListadoAgrupacionSupport::normalizar(
            $request->input('group', $filtros['agrupar'] ?? []),
            SancionReporteListadoFiltros::camposOrdenables()
        );
        $layout = SancionReporteListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
        $page = $this->repository->leeSancionesReporte($filtros, true);
        $total = method_exists($page, 'total') ? (int) $page->total() : $page->count();
        $filas = method_exists($page, 'getCollection') ? $page->getCollection() : $page;
        $orden = ListadoOrdenamientoSupport::normalizar(
            $request->input('sort', $filtros['sort'] ?? []),
            SancionReporteListadoFiltros::camposOrdenables()
        );
        $cortes = $filtros['agrupar'] !== [] ? $this->repository->cortesSanciones($filtros) : ['activo' => false];

        return response()->json(ListadoDisenadorPreviewSupport::payload(
            $layout,
            $orden,
            $filtros['agrupar'],
            $filas,
            static fn (object $row, string $key): string => SancionReporteListadoColumnas::valorCelda($row, $key),
            $total,
            $etiquetas,
            $cortes
        ));
    }

    public function guardarVistaListado(Request $request)
    {
        can('listar-sancion-reporte-sueldos');

        [$filtros] = $this->filtrosDePantalla($request);
        $layout = SancionReporteListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $orden = $filtros['sort'] ?? [];
        $vista = ListadoVistaSupport::guardar(
            SancionReporteListadoColumnas::RECURSO,
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
                    SancionReporteListadoFiltros::camposOrdenables()
                )),
                'graficos' => $graficosVista,
            ],
            $layout,
            $request->boolean('es_default'),
            $request->boolean('compartida'),
            $request->filled('vista_id') ? (int) $request->input('vista_id') : null
        );
        if (! $vista) {
            return redirect()->route('sancion_reporte_sueldos', SancionReporteListadoFiltros::paraQueryString($filtros, true))
                ->with('error', 'No se pudo guardar la vista.');
        }
        ListadoVistaMenuSupport::sincronizar($vista, $request->boolean('crear_en_menu'));
        $qs = SancionReporteListadoFiltros::paraQueryString($filtros, true);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs['vista_id'] = $vista->id;

        return redirect()->route('sancion_reporte_sueldos', $qs)
            ->with('mensaje', 'Vista «'.$vista->nombre.'» guardada.');
    }

    public function eliminarVistaListado(int $id)
    {
        can('listar-sancion-reporte-sueldos');
        $ok = ListadoVistaSupport::eliminar($id, SancionReporteListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('sancion_reporte_sueldos', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        can('listar-sancion-reporte-sueldos');
        $layout = SancionReporteListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $vistaId = $request->filled('vista_id') ? (int) $request->input('vista_id') : 0;
        if ($vistaId > 0 && $request->boolean('actualizar_vista')) {
            $vista = ListadoVistaSupport::findParaUsuario($vistaId, SancionReporteListadoColumnas::RECURSO, (int) auth()->id());
            if ($vista && (int) $vista->usuario_id === (int) auth()->id()) {
                $vista->columnas_json = $layout;
                $vista->save();
            }
        } else {
            SancionReporteListadoPreferenciasUsuario::persistirGrillaEstandar($layout);
        }
        [$filtros] = $this->filtrosDePantalla($request);
        $qs = SancionReporteListadoFiltros::paraQueryString($filtros, true);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs[$vistaId > 0 ? 'vista_id' : 'vista_estandar'] = $vistaId > 0 ? $vistaId : 1;

        return redirect()->route('sancion_reporte_sueldos', $qs)->with('mensaje', 'Grilla actualizada.');
    }

    public function guardarEtiquetasListado(Request $request)
    {
        can('listar-sancion-reporte-sueldos');
        $etiquetas = $request->input('etiquetas', []);
        if (! is_array($etiquetas)) {
            $etiquetas = [];
        }
        ListadoColumnaEtiquetaSupport::guardar(
            SancionReporteListadoColumnas::RECURSO,
            $etiquetas,
            array_keys(SancionReporteListadoColumnas::catalogoActivo())
        );
        [$filtros] = $this->filtrosDePantalla($request);

        return redirect()->route(
            'sancion_reporte_sueldos',
            SancionReporteListadoFiltros::paraQueryString($filtros, true)
        )->with('mensaje', 'Etiquetas actualizadas.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: mixed, 2: bool}
     */
    private function filtrosDePantalla(Request $request): array
    {
        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $forzarEstandar = $request->boolean('vista_estandar') || $request->input('vista_modo') === 'estandar';
        $vistaActiva = null;

        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                SancionReporteListadoColumnas::RECURSO,
                $usuarioId
            );
        } elseif (
            ! $forzarEstandar
            && ! $request->has('filtro_valor')
            && ! $request->has('qbe')
            && ! $request->boolean('filtro_limpiar')
            && ! $request->has('fecha_desde')
            && ! $request->has('apellido')
            && ! $request->has('empresa_ids')
            && ! $request->has('estado')
        ) {
            $vistaActiva = ListadoVistaSupport::defaultDelUsuario(SancionReporteListadoColumnas::RECURSO, $usuarioId);
        }

        $filtrosRequest = ListadoVistaSupport::prepararQbeContraVista(
            SancionReporteListadoFiltros::resolverDesdeRequest($request),
            $request
        );
        $filtros = $filtrosRequest;
        if ($vistaActiva && is_array($vistaActiva->filtros_json)) {
            $filtros = SancionReporteListadoFiltros::fusionarDesdeVista($filtros, $vistaActiva->filtros_json);
        }
        unset($filtros['_qbe_explicito']);
        $filtros = ListadoVisualSupport::aplicarPedido(
            $filtros,
            $request,
            ($vistaActiva && is_array($vistaActiva->filtros_json)) ? $vistaActiva->filtros_json : null,
            SancionReporteListadoFiltros::camposOrdenables()
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

        $filtros = $this->aplicarEmpresas($request, $filtros);

        return [$filtros, $vistaActiva, $forzarEstandar];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarEmpresas(Request $request, array $filtros): array
    {
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        $envio = $request->exists('fecha_desde')
            || $request->has('empresa_ids')
            || $request->exists('consolidar_empresas')
            || $request->boolean('consultar');

        if (! $request->exists('consolidar_empresas')) {
            $filtros['consolidar_empresas'] = ReportePreferenciasUsuario::leerBool(
                SancionReporteListadoFiltros::PREFERENCIAS_CLAVE,
                'consolidar_empresas',
                true
            );
        }

        if (($filtros['empresa_ids'] ?? []) === [] && ! $request->boolean('filtro_limpiar')) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(SancionReporteListadoFiltros::PREFERENCIAS_CLAVE);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            }
        }

        $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas(
            $filtros['empresa_ids'] ?? [],
            $permitidos
        );
        if ($filtros['empresa_ids'] === [] && $permitidos !== []) {
            $filtros['empresa_ids'] = $permitidos;
        }

        if ($envio) {
            ReportePreferenciasUsuario::persistir(SancionReporteListadoFiltros::PREFERENCIAS_CLAVE, [
                'empresa_ids' => $filtros['empresa_ids'],
                'consolidar_empresas' => (bool) ($filtros['consolidar_empresas'] ?? true),
            ]);
        }

        return $filtros;
    }

    /**
     * @param  list<string>  $columnasVisibles
     * @return array<string, mixed>
     */
    private function queryDeFiltros(array $filtros, array $columnasVisibles, mixed $vistaActiva, bool $forzarEstandar, Request $request): array
    {
        $filtrosQuery = SancionReporteListadoFiltros::paraQueryString($filtros, true);
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

        return $filtrosQuery;
    }

    /**
     * @param  array<string, mixed>  $cortes
     * @return array{labels: list<string>, cantidades: list<int>, importes: list<float>, total: int, truncado: bool}
     */
    private function graficoDesdeCortes(array $cortes): array
    {
        $labels = [];
        $cantidades = [];
        $importes = [];
        foreach ($cortes['filas'] ?? [] as $fila) {
            if ((int) ($fila['nivel'] ?? 0) !== 0) {
                continue;
            }
            $valor = trim((string) ($fila['valor'] ?? ''));
            $labels[] = $valor !== '' ? $valor : '(sin tipo)';
            $cantidades[] = (int) ($fila['count'] ?? 0);
            $importes[] = round((float) (($fila['sumas']['importe_perdida'] ?? 0)), 2);
        }

        return [
            'labels' => $labels,
            'cantidades' => $cantidades,
            'importes' => $importes,
            'total' => (int) ($cortes['total'] ?? 0),
            'truncado' => (bool) ($cortes['truncado'] ?? false),
        ];
    }
}
