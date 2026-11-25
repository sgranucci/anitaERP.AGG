<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\EmpleadoSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionEmpleado_Sueldos;
use App\Models\Contable\Centrocosto;
use App\Models\Sueldos\Agrupamiento_Sueldos;
use App\Models\Sueldos\Art_Sueldos;
use App\Models\Sueldos\Categoria_Base_Sueldos;
use App\Models\Sueldos\Categoria_Sueldos;
use App\Models\Sueldos\Empleado_Base_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Lugartrabajo_Sueldos;
use App\Models\Sueldos\Motivoegreso_Sueldos;
use App\Models\Sueldos\Nombrebase_Sueldos;
use App\Models\Sueldos\Obrasocial_Sueldos;
use App\Models\Sueldos\Sindicato_Sueldos;
use App\Models\Sueldos\Vacacion_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sueldos\Empleado_SueldosRepositoryInterface;
use App\Services\Configuracion\ModuloAvisoService;
use App\Services\Sueldos\CategoriaBaseSueldosService;
use App\Services\Sueldos\DevengamientoVacacionesService;
use App\Services\Sueldos\EmpleadoBaseSueldosService;
use App\Services\Sueldos\EmpleadoIngresoService;
use App\Services\Sueldos\LiquidacionCalculadorService;
use App\Support\Sueldos\CategoriaOrigenBases;
use App\Support\Sueldos\EmpleadoLegajoVisualizacion;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoLienzoSupport;
use App\Support\Listado\ListadoVisualSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoDisenadorPreviewSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoFiltrosMemoriaSupport;
use App\Support\Listado\ListadoVistaMenuSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Sueldos\EmpleadoEstados;
use App\Support\Sueldos\EmpleadoSueldosListadoColumnas;
use App\Support\Sueldos\EmpleadoSueldosListadoFiltros;
use App\Support\Sueldos\EmpleadoSueldosListadoPreferenciasUsuario;
use App\Support\Sueldos\Formula\FormulaException;
use App\Models\Sueldos\Liquidacion_Sueldos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class Empleado_SueldosController extends Controller
{
    private const SESSION_FILTROS = 'filtros_empleado_sueldos';

    public function __construct(
        private Empleado_SueldosRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
        private EmpleadoBaseSueldosService $baseService,
        private CategoriaBaseSueldosService $categoriaBaseService,
        private EmpleadoIngresoService $ingresoService,
        private ModuloAvisoService $moduloAvisoService,
        private DevengamientoVacacionesService $devengamientoVacaciones,
    ) {
    }

    public function index(Request $request)
    {
        if (! can('listar-empleado-sueldos', false) && ! can(EmpleadoLegajoVisualizacion::PERMISO, false)) {
            can('listar-empleado-sueldos');
        }

        // Limpiar: olvida memoria y vuelve a Activos + Todas mis empresas
        if (ListadoFiltrosMemoriaSupport::solicitudLimpia($request)) {
            ListadoFiltrosMemoriaSupport::olvidar(self::SESSION_FILTROS);

            return redirect()->route('consultar_empleado_sueldos', [
                'empresa_todas' => 1,
            ]);
        }

        $forzarEstandar = $request->boolean('vista_estandar')
            || $request->input('vista_modo') === 'estandar';

        // Memoria: visita sin query restaura el último filtro
        if (! ListadoFiltrosMemoriaSupport::requestTraeFiltros($request) && ! $forzarEstandar) {
            $guardados = ListadoFiltrosMemoriaSupport::restaurar(self::SESSION_FILTROS);
            if ($guardados !== []) {
                return redirect()->route('consultar_empleado_sueldos', $guardados);
            }
        }

        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $vistas = ListadoVistaSupport::listarParaUsuario(EmpleadoSueldosListadoColumnas::RECURSO, $usuarioId);
        $vistaActiva = null;

        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                EmpleadoSueldosListadoColumnas::RECURSO,
                $usuarioId
            );
        } elseif (
            ! $forzarEstandar
            && ! $request->has('filtro_valor')
            && ! $request->has('qbe')
            && ! $request->has('filtro_estado')
            && ! $request->has('empresa_id')
            && ! $request->has('empresa_todas')
        ) {
            $vistaActiva = ListadoVistaSupport::defaultDelUsuario(EmpleadoSueldosListadoColumnas::RECURSO, $usuarioId);
        }

        $filtrosRequest = ListadoVistaSupport::prepararQbeContraVista(
            $this->resolverFiltrosListado($request),
            $request
        );
        $filtros = $filtrosRequest;
        if ($vistaActiva && is_array($vistaActiva->filtros_json)) {
            $filtros = EmpleadoSueldosListadoFiltros::fusionarDesdeVista($filtros, $vistaActiva->filtros_json);
        }
        unset($filtros['_qbe_explicito']);
        $filtros = ListadoVisualSupport::aplicarPedido(
            $filtros,
            $request,
            ($vistaActiva && is_array($vistaActiva->filtros_json)) ? $vistaActiva->filtros_json : null,
            EmpleadoSueldosListadoFiltros::camposOrdenables()
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
            $params = EmpleadoSueldosListadoFiltros::paraQueryString($filtros);
            if ($vistaActiva) {
                $params['vista_id'] = $vistaActiva->id;
            } elseif (!empty($forzarEstandar)) {
                $params['vista_estandar'] = 1;
            }

            return redirect()
                ->route('consultar_empleado_sueldos', $params)
                ->with('mensaje', 'Se volvió al orden estándar.');
        }


        $catalogo = EmpleadoSueldosListadoColumnas::catalogoActivo();
        $etiquetasInstalacion = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            EmpleadoSueldosListadoColumnas::RECURSO,
            $catalogo
        );
        if ($vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== []) {
            $grillaLayout = EmpleadoSueldosListadoPreferenciasUsuario::normalizarLayout($vistaActiva->columnas_json);
        } else {
            $grillaLayout = EmpleadoSueldosListadoPreferenciasUsuario::grillaEstandar();
        }
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);

        $datas = $this->repository->leeEmpleado($filtros, true);

        $cortes = ['activo' => false];
        if (ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], EmpleadoSueldosListadoFiltros::camposOrdenables()) !== []) {
            $cortes = $this->repository->cortesEmpleado($filtros);
        }
        $graficoEmpleado = $this->graficoSueldoPorCategoria($filtros);

        $camposFiltro = EmpleadoSueldosListadoFiltros::camposQbeDisponibles();
        foreach ($camposFiltro as $key => $meta) {
            $camposFiltro[$key]['label'] = $etiquetas[$key] ?? $etiquetasInstalacion[$key] ?? $meta['label'];
        }

        $filtrosQuery = EmpleadoSueldosListadoFiltros::paraQueryString($filtros);
        $filtrosQuery = array_merge($filtrosQuery, ListadoVisualSupport::paraQueryString($filtros));
        $filtrosQuery['columnas'] = implode(',', $columnasVisibles);
        if ($vistaActiva) {
            $filtrosQuery['vista_id'] = $vistaActiva->id;
        } elseif ($forzarEstandar) {
            $filtrosQuery['vista_estandar'] = 1;
        }

        $page = (int) $request->input('page', 0);
        if ($page > 1) {
            $filtrosQuery['page'] = $page;
        }
        if (ListadoFiltrosMemoriaSupport::requestTraeFiltros($request)) {
            ListadoFiltrosMemoriaSupport::persistir(self::SESSION_FILTROS, $filtrosQuery);
        }

        return view('sueldos.empleado.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => $camposFiltro,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'estadosLabels' => EmpleadoEstados::LABELS,
            'categorias' => Categoria_Sueldos::query()->orderBy('codigo')->get(['id', 'codigo', 'descripcion']),
            'columnasVisibles' => $columnasVisibles,
            'grillaLayout' => $grillaLayout,
            'catalogoColumnas' => $catalogo,
            'etiquetasColumnas' => $etiquetas,
            'etiquetasInstalacion' => $etiquetasInstalacion,
            'vistasListado' => $vistas,
            'vistaActiva' => $vistaActiva,
            'workbenchListo' => ListadoVistaSupport::tablasDisponibles(),
            'cortes' => $cortes,
            'graficoEmpleado' => $graficoEmpleado,
            'graficoSeries' => ($graficoSeries = ListadoVisualSupport::seriesDeConsulta(
                $filtros,
                EmpleadoSueldosListadoFiltros::camposOrdenables(),
                $etiquetas,
                fn (array $para): array => $this->repository->cortesEmpleado($para)
            )),
            'graficoSerie' => $graficoSeries[0] ?? ['labels' => [], 'series' => [], 'tipo' => '', 'titulo' => ''],
        ]);
    }

    /**
     * Barras de sueldo básico por categoría sobre el universo del filtro.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{labels: list<string>, montos: list<float>, cantidades: list<int>, total: int, truncado: bool}
     */
    private function graficoSueldoPorCategoria(array $filtros): array
    {
        $consulta = $filtros;
        $consulta['agrupar'] = ['categoria'];
        $cortes = $this->repository->cortesEmpleado($consulta);
        $labels = [];
        $montos = [];
        $cantidades = [];
        foreach ($cortes['filas'] ?? [] as $fila) {
            if ((int) ($fila['nivel'] ?? 0) !== 0) {
                continue;
            }
            $valor = trim((string) ($fila['valor'] ?? ''));
            $labels[] = $valor !== '' ? $valor : '(vacío)';
            $montos[] = round((float) (($fila['sumas']['sueldo_basico'] ?? 0)), 2);
            $cantidades[] = (int) ($fila['count'] ?? 0);
        }

        return [
            'labels' => $labels,
            'montos' => $montos,
            'cantidades' => $cantidades,
            'total' => (int) ($cortes['total'] ?? 0),
            'truncado' => (bool) ($cortes['truncado'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        // Sin empresa_id en la request → Todas mis empresas (no la primera asignada).
        return EmpleadoSueldosListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            null
        );
    }

    public function sincronizarAnita(Request $request)
    {
        can('actualizar-empleado-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $r = $this->repository->sincronizarConAnita();

        if (! empty($r['errores'])) {
            return redirect('sueldos/empleado')
                ->with('error', 'No se pudo sincronizar con Anita: '.implode(' | ', $r['errores']));
        }

        return redirect('sueldos/empleado')->with(
            'mensaje',
            'Sincronización con Anita: '.$r['importados'].' empleados nuevos, '.$r['ya_existia'].' ya existentes, '
                .($r['actualizados_egreso'] ?? 0).' egreso/estado actualizados, '
                .($r['actualizados_sueldo'] ?? 0).' sueldos/jornales actualizados, '
                .($r['actualizados_datos'] ?? 0).' datos organizativos actualizados (CC, categoría, etc.), '
                .$r['sin_empresa'].' sin empresa ERP (de '.$r['en_anita'].' en Anita). '
                .'Historia: '.$r['historia'].' · Leyendas: '.$r['leyendas'].' · Bases: '.$r['bases'].'.'
        );
    }

    public function vincularDomicilios(Request $request)
    {
        can('actualizar-empleado-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $r = $this->repository->vincularDomicilios(true);

        return redirect('sueldos/empleado')->with(
            'mensaje',
            'Vinculación de domicilios: '.$r['procesados'].' empleados procesados · '
                .($r['a_actualizar'] ?? 0).' actualizados · '
                .$r['provincia_vinculada'].' provincias · '
                .$r['provincia_corregida'].' provincias corregidas (CABA) · '
                .$r['localidad_vinculada'].' localidades · '
                .($r['pais_vinculado'] ?? 0).' país · '
                .$r['cp_completado'].' códigos postales. '
                .'Sin coincidencia: '.$r['sin_provincia_textos'].' textos de provincia, '
                .$r['sin_localidad_textos'].' de localidad.'
        );
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-empleado-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
        $columnasRequest = $request->input('columnas');
        if (is_string($columnasRequest)) {
            $columnasRequest = array_filter(array_map('trim', explode(',', $columnasRequest)));
        }
        $columnasVisibles = EmpleadoSueldosListadoPreferenciasUsuario::resolverColumnas(
            is_array($columnasRequest) ? $columnasRequest : null
        );
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            EmpleadoSueldosListadoColumnas::RECURSO,
            EmpleadoSueldosListadoColumnas::catalogoActivo()
        );

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeEmpleado($filtros, false);
                $view = \View::make('sueldos.empleado.listado', [
                    'datas' => $datas,
                    'columnasVisibles' => $columnasVisibles,
                    'etiquetasColumnas' => $etiquetas,
                    'filtros' => $filtros,
                ])->render();
                $rutaPdf = storage_path('pdf/listados/listado_empleado_sueldos.pdf');
                DompdfListadoSupport::guardarLegalLandscape($view, $rutaPdf, [
                    'titulo_corto' => 'Listado de empleados',
                    'dompdf' => [
                        'isFontSubsettingEnabled' => false,
                        'isJavascriptEnabled' => false,
                    ],
                ]);

                return response()->download($rutaPdf);

            case 'EXCEL':
                return app(EmpleadoSueldosListadoExport::class)
                    ->parametros($filtros, $columnasVisibles, $etiquetas)
                    ->download('empleado_sueldos.xlsx');

            case 'CSV':
                return app(EmpleadoSueldosListadoExport::class)
                    ->parametros($filtros, $columnasVisibles, $etiquetas)
                    ->download('empleado_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_empleado_sueldos', EmpleadoSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function previewWorkbench(Request $request)
    {
        can('listar-empleado-sueldos');

        $filtros = $this->resolverFiltrosListado($request);
        $filtros['_per_page'] = ListadoDisenadorPreviewSupport::LIMITE_MUESTRA;
        $layout = EmpleadoSueldosListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
        $page = $this->repository->leeEmpleado($filtros, true);
        $total = method_exists($page, 'total') ? (int) $page->total() : $page->count();
        $filas = method_exists($page, 'getCollection') ? $page->getCollection() : $page;
        $orden = ListadoOrdenamientoSupport::normalizar(
            $request->input('sort', $filtros['sort'] ?? []),
            EmpleadoSueldosListadoFiltros::camposOrdenables()
        );
        $agrupar = ListadoAgrupacionSupport::normalizar(
            $request->input('group', $filtros['agrupar'] ?? []),
            EmpleadoSueldosListadoFiltros::camposOrdenables()
        );
        $filtrosCortes = $filtros;
        $filtrosCortes['agrupar'] = $agrupar;
        $cortes = $agrupar !== [] ? $this->repository->cortesEmpleado($filtrosCortes) : ['activo' => false];

        return response()->json(ListadoDisenadorPreviewSupport::payload(
            $layout,
            $orden,
            $agrupar,
            $filas,
            static fn (object $row, string $key): string => EmpleadoSueldosListadoColumnas::valorCelda($row, $key),
            $total,
            $etiquetas,
            $cortes
        ));
    }

    public function guardarVistaListado(Request $request)
    {
        can('listar-empleado-sueldos');

        $filtros = $this->resolverFiltrosListado($request);
        $layout = EmpleadoSueldosListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $orden = $filtros['sort'] ?? [];
        $vista = ListadoVistaSupport::guardar(
            EmpleadoSueldosListadoColumnas::RECURSO,
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
                    EmpleadoSueldosListadoFiltros::camposOrdenables()
                )),
                'graficos' => $graficosVista,
            ],
            $layout,
            $request->boolean('es_default'),
            $request->boolean('compartida'),
            $request->filled('vista_id') ? (int) $request->input('vista_id') : null
        );
        if (! $vista) {
            return redirect()->route('consultar_empleado_sueldos', EmpleadoSueldosListadoFiltros::paraQueryString($filtros))
                ->with('error', 'No se pudo guardar la vista.');
        }
        ListadoVistaMenuSupport::sincronizar($vista, $request->boolean('crear_en_menu'));
        $qs = EmpleadoSueldosListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs['vista_id'] = $vista->id;

        return redirect()->route('consultar_empleado_sueldos', $qs)
            ->with('mensaje', 'Vista «'.$vista->nombre.'» guardada.');
    }

    public function eliminarVistaListado(int $id)
    {
        can('listar-empleado-sueldos');
        $ok = ListadoVistaSupport::eliminar($id, EmpleadoSueldosListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('consultar_empleado_sueldos', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        can('listar-empleado-sueldos');
        $layout = EmpleadoSueldosListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $vistaId = $request->filled('vista_id') ? (int) $request->input('vista_id') : 0;
        if ($vistaId > 0 && $request->boolean('actualizar_vista')) {
            $vista = ListadoVistaSupport::findParaUsuario($vistaId, EmpleadoSueldosListadoColumnas::RECURSO, (int) auth()->id());
            if ($vista && (int) $vista->usuario_id === (int) auth()->id()) {
                $vista->columnas_json = $layout;
                $vista->save();
            }
        } else {
            EmpleadoSueldosListadoPreferenciasUsuario::persistirGrillaEstandar($layout);
        }
        $filtros = $this->resolverFiltrosListado($request);
        $qs = EmpleadoSueldosListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs[$vistaId > 0 ? 'vista_id' : 'vista_estandar'] = $vistaId > 0 ? $vistaId : 1;

        return redirect()->route('consultar_empleado_sueldos', $qs)->with('mensaje', 'Grilla actualizada.');
    }

    public function guardarEtiquetasListado(Request $request)
    {
        can('listar-empleado-sueldos');
        $etiquetas = $request->input('etiquetas', []);
        if (! is_array($etiquetas)) {
            $etiquetas = [];
        }
        ListadoColumnaEtiquetaSupport::guardar(
            EmpleadoSueldosListadoColumnas::RECURSO,
            $etiquetas,
            array_keys(EmpleadoSueldosListadoColumnas::catalogoActivo())
        );

        return redirect()->route(
            'consultar_empleado_sueldos',
            EmpleadoSueldosListadoFiltros::paraQueryString($this->resolverFiltrosListado($request))
        )->with('mensaje', 'Etiquetas actualizadas.');
    }

    public function crear()
    {
        can('crear-empleado-sueldos');

        return view('sueldos.empleado.crear', $this->datosFormulario());
    }

    public function guardar(ValidacionEmpleado_Sueldos $request)
    {
        can('crear-empleado-sueldos');
        $data = $request->validated();
        $data['estado'] = EmpleadoEstados::PROVISORIO;
        $data['nombrearchivos'] = $request->file('nombrearchivos', []);
        $empleado = $this->repository->create($data);

        $this->moduloAvisoService->enviar('sueldos', 'empleado_alta_provisoria', (int) $empleado->id);

        return redirect()->route('editar_empleado_sueldos', ['id' => $empleado->id])
            ->with('mensaje', 'Empleado creado en alta provisoria. Se envió aviso para autorización.');
    }

    public function editar(Request $request, $id)
    {
        $soloVisualizacion = $request->query('vista') === EmpleadoLegajoVisualizacion::VISTA;
        $consulta = $request->query('origen') === 'modal_consulta'
            || $request->query('vista') === 'consulta';
        if ($soloVisualizacion) {
            can(EmpleadoLegajoVisualizacion::PERMISO);
        } elseif ($consulta) {
            if (! can('listar-empleado-sueldos', false)
                && ! can('editar-empleado-sueldos', false)
                && ! can('actualizar-empleado-sueldos', false)) {
                can('listar-empleado-sueldos');
            }
        } else {
            can('editar-empleado-sueldos');
        }
        $data = $this->repository->findOrFail($id);

        // La consulta y la visualización no recalculan vacaciones: eso escribe el ledger.
        if (! $consulta && ! $soloVisualizacion) {
            $this->sincronizarSaldosVacaciones($data);
        }

        $usaTabla = $data->categoria
            ? CategoriaOrigenBases::usaTablaCategoria($data->categoria->origen_bases)
            : true;

        $basesGrilla = $this->basesGrillaEfectivas($data);

        $valoresCategoriaRemuneracion = [];
        if ($usaTabla) {
            foreach ($basesGrilla as $base) {
                if (($base['origen'] ?? '') !== 'categoria') {
                    continue;
                }
                $cod = (int) ($base['nombrebase_codigo'] ?? 0);
                if (in_array($cod, [1, 2, 3], true) && $base['valor'] !== null) {
                    $valoresCategoriaRemuneracion[$cod] = (float) $base['valor'];
                }
            }
        }

        $nombrebaseIdsCategoria = $this->nombrebaseIdsDeCategoria($data);

        return view('sueldos.empleado.editar', array_merge($this->datosFormulario(), [
            'data' => $data,
            'usaTabla' => $usaTabla,
            'basesGrilla' => $basesGrilla,
            'valoresCategoriaRemuneracion' => $valoresCategoriaRemuneracion,
            'nombrebases' => Nombrebase_Sueldos::query()->orderBy('codigo')->get(),
            'nombrebaseIdsCategoria' => $nombrebaseIdsCategoria,
            'puedeBorrarVigencia' => ! $soloVisualizacion && can('borrar-vigencia-empleado-sueldos', false),
            'puedeBaja' => ! $soloVisualizacion && can('baja-empleado-sueldos', false),
            'puedeBorrarHistoria' => ! $soloVisualizacion && can('borrar-historia-empleado-sueldos', false),
            'puedeAutorizar' => ! $soloVisualizacion && can('autorizar-empleado-sueldos', false),
            'soloVisualizacion' => $soloVisualizacion,
            'qVista' => $soloVisualizacion ? ['vista' => EmpleadoLegajoVisualizacion::VISTA] : [],
        ]));
    }

    public function actualizar(ValidacionEmpleado_Sueldos $request, $id)
    {
        can('actualizar-empleado-sueldos');
        $data = $request->validated();
        $data['nombrearchivos'] = $request->file('nombrearchivos', []);
        $data['nombresanteriores'] = $request->input('nombresanteriores', []);
        $data['foto_archivo'] = $request->file('foto_archivo');
        $this->repository->update($data, $id);
        $this->sincronizarSaldosVacaciones($this->repository->findOrFail($id));

        return redirect()->route('consultar_empleado_sueldos')
            ->with('mensaje', 'Empleado actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-empleado-sueldos');
        if ($request->ajax()) {
            try {
                $ok = $this->repository->delete($id);

                return response()->json(['mensaje' => $ok ? 'ok' : 'ng']);
            } catch (\InvalidArgumentException $e) {
                return response()->json([
                    'mensaje' => 'ng',
                    'error' => $e->getMessage(),
                ], 422);
            } catch (\Illuminate\Database\QueryException $e) {
                return response()->json([
                    'mensaje' => 'ng',
                    'error' => 'No se puede eliminar el empleado porque tiene movimientos relacionados (pérdida de personal, recibos u otros).',
                ], 422);
            }
        }
        abort(404);
    }

    public function autorizarDesdeAviso(Request $request, $id)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'El enlace de autorización no es válido o expiró.');
        }

        try {
            $emp = $this->repository->findOrFail($id);
            $this->ingresoService->autorizarAlta($emp, Auth::id());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('editar_empleado_sueldos', ['id' => $id])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('editar_empleado_sueldos', ['id' => $id])
            ->with('mensaje', 'Alta autorizada. El empleado quedó activo.');
    }

    public function autorizar($id)
    {
        can('autorizar-empleado-sueldos');
        try {
            $emp = $this->repository->findOrFail($id);
            $this->ingresoService->autorizarAlta($emp, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('editar_empleado_sueldos', ['id' => $id])
            ->with('mensaje', 'Alta autorizada. El empleado quedó activo.');
    }

    public function darBaja(Request $request, $id)
    {
        return $this->confirmarBajaDefinitiva($request, $id);
    }

    public function programarBaja(Request $request, $id)
    {
        can('baja-empleado-sueldos');
        $request->validate([
            'fecha_egreso' => 'required|date',
            'motivoegreso_id' => 'nullable|integer|exists:motivoegreso_sueldos,id',
            'comentario_baja' => 'nullable|string|max:80',
        ]);

        try {
            $emp = $this->repository->findOrFail($id);
            $this->ingresoService->programarBaja(
                $emp,
                $request->input('fecha_egreso'),
                $request->input('motivoegreso_id') ? (int) $request->input('motivoegreso_id') : null,
                $request->input('comentario_baja')
            );
            $emp = $this->repository->findOrFail($id);
            $this->sincronizarSaldosVacaciones($emp);
            $this->moduloAvisoService->enviar('sueldos', 'empleado_baja_provisoria', (int) $emp->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('editar_empleado_sueldos', ['id' => $id])
            ->with('mensaje', 'Baja provisoria programada. El legajo sigue activo; se avisó a los destinatarios configurados.');
    }

    public function confirmarBajaDefinitiva(Request $request, $id)
    {
        can('baja-empleado-sueldos');
        $request->validate([
            'fecha_egreso' => 'required|date',
            'motivoegreso_id' => 'nullable|integer|exists:motivoegreso_sueldos,id',
            'comentario_baja' => 'nullable|string|max:80',
        ]);

        try {
            $emp = $this->repository->findOrFail($id);
            $this->ingresoService->confirmarBajaDefinitiva(
                $emp,
                $request->input('fecha_egreso'),
                $request->input('motivoegreso_id') ? (int) $request->input('motivoegreso_id') : null,
                $request->input('comentario_baja')
            );
            $this->sincronizarSaldosVacaciones($this->repository->findOrFail($id));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('editar_empleado_sueldos', ['id' => $id])
            ->with('mensaje', 'Baja definitiva registrada. Se actualizó la historia de ingresos/egresos.');
    }

    public function modificarBajaProvisoria(Request $request, $id)
    {
        can('baja-empleado-sueldos');
        $request->validate([
            'fecha_egreso' => 'required|date',
            'motivoegreso_id' => 'nullable|integer|exists:motivoegreso_sueldos,id',
            'comentario_baja' => 'nullable|string|max:80',
        ]);

        try {
            $emp = $this->repository->findOrFail($id);
            $this->ingresoService->modificarBajaProvisoria(
                $emp,
                $request->input('fecha_egreso'),
                $request->input('motivoegreso_id') ? (int) $request->input('motivoegreso_id') : null,
                $request->input('comentario_baja')
            );
            $emp = $this->repository->findOrFail($id);
            $this->sincronizarSaldosVacaciones($emp);
            $this->moduloAvisoService->enviar('sueldos', 'empleado_baja_provisoria', (int) $emp->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('editar_empleado_sueldos', ['id' => $id])
            ->with('mensaje', 'Baja provisoria actualizada. Se avisó a los destinatarios configurados.');
    }

    public function anularBajaProvisoria(Request $request, $id)
    {
        can('baja-empleado-sueldos');
        $request->validate(['comentario' => 'nullable|string|max:80']);

        try {
            $emp = $this->repository->findOrFail($id);
            $this->ingresoService->anularBajaProvisoria($emp, $request->input('comentario'));
            $this->sincronizarSaldosVacaciones($this->repository->findOrFail($id));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('editar_empleado_sueldos', ['id' => $id])
            ->with('mensaje', 'Baja provisoria anulada. El legajo quedó activo sin fecha de egreso.');
    }

    public function eliminarHistoria(Request $request, $id, $ingresoId)
    {
        can('borrar-historia-empleado-sueldos');

        try {
            $emp = $this->repository->findOrFail($id);
            $this->ingresoService->eliminarHistoria($emp, (int) $ingresoId);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('editar_empleado_sueldos', ['id' => $id])
            ->with('mensaje', 'Registro de historia eliminado.');
    }

    public function reincorporar(Request $request, $id)
    {
        can('baja-empleado-sueldos');
        $request->validate(['fecha_ingreso' => 'required|date']);

        try {
            $emp = $this->repository->findOrFail($id);
            $this->ingresoService->reincorporar($emp, $request->input('fecha_ingreso'));
            $this->sincronizarSaldosVacaciones($this->repository->findOrFail($id));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('editar_empleado_sueldos', ['id' => $id])
            ->with('mensaje', 'Reincorporación registrada.');
    }

    // --- Bases AJAX (legajo; con categoría T no se tocan códigos de la tabla) ---

    public function guardarBase(Request $request, $id)
    {
        can('actualizar-empleado-sueldos');
        $emp = $this->repository->findOrFail($id);

        $request->validate([
            'nombrebase_id' => 'required|integer|exists:nombrebase_sueldos,id',
            'valor' => 'required|numeric',
            'fecha_vigencia' => 'required|date',
        ]);

        $nombrebaseId = (int) $request->input('nombrebase_id');
        $this->assertNombrebaseEditableEnLegajo($emp, $nombrebaseId);

        $resultado = $this->baseService->guardarBase(
            (int) $emp->id,
            $nombrebaseId,
            (float) $request->input('valor'),
            $request->input('fecha_vigencia'),
            Auth::id()
        );

        return response()->json([
            'mensaje' => 'ok',
            'creo_version' => $resultado['creo_version'],
            'bases' => $this->basesGrillaEfectivas($emp),
        ]);
    }

    public function guardarVigenciasLote(Request $request, $id)
    {
        can('actualizar-empleado-sueldos');
        $emp = $this->repository->findOrFail($id);

        $datos = $request->validate([
            'nombrebase_id' => 'required|integer|exists:nombrebase_sueldos,id',
            'items' => 'nullable|array',
            'items.*.id' => 'nullable|integer',
            'items.*.valor' => 'required_with:items|numeric',
            'items.*.fecha' => 'required_with:items|date',
            'eliminar' => 'nullable|array',
            'eliminar.*' => 'integer',
        ]);

        $nombrebaseId = (int) $datos['nombrebase_id'];
        $this->assertNombrebaseEditableEnLegajo($emp, $nombrebaseId);

        $eliminar = $datos['eliminar'] ?? [];
        if ($eliminar !== [] && ! can('borrar-vigencia-empleado-sueldos', false)) {
            return response()->json(['mensaje' => 'ng', 'error' => 'No tiene permiso para eliminar vigencias.'], 403);
        }

        $resultado = $this->baseService->guardarVigenciasLote(
            (int) $emp->id,
            $nombrebaseId,
            $datos['items'] ?? [],
            $eliminar,
            Auth::id()
        );

        if (empty($resultado['ok'])) {
            return response()->json(['mensaje' => 'ng', 'error' => $resultado['error'] ?? 'No se pudo guardar.'], 422);
        }

        return response()->json([
            'mensaje' => 'ok',
            'nombrebase_id' => $nombrebaseId,
            'historial' => $this->baseService->historial((int) $emp->id, $nombrebaseId),
            'bases' => $this->basesGrillaEfectivas($emp),
        ]);
    }

    public function bases($id)
    {
        EmpleadoLegajoVisualizacion::autorizarComoEdicion();
        $emp = $this->repository->findOrFail($id);

        return response()->json([
            'grilla' => $this->basesGrillaEfectivas($emp),
        ]);
    }

    /**
     * Preview JSON de conceptos que liquidarían para el legajo (no persiste).
     */
    public function simularLiquidacion(Request $request, LiquidacionCalculadorService $calculador, $id)
    {
        EmpleadoLegajoVisualizacion::autorizarComoEdicion();
        $emp = $this->repository->findOrFail($id);

        $periodo = (string) $request->input('periodo', now()->format('Y-m'));
        $tipo = (string) $request->input('tipo', 'mensual');
        if (! isset(Liquidacion_Sueldos::TIPOS[$tipo])) {
            $tipo = 'mensual';
        }

        try {
            $resultado = $calculador->simularEmpleado($emp, $periodo, $tipo);
        } catch (FormulaException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'lineas' => [],
                'totales' => ['haber' => 0, 'descuento' => 0, 'contribucion' => 0, 'neto' => 0, 'cantidad' => 0],
                'errores' => [$e->getMessage()],
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo simular: '.$e->getMessage(),
                'lineas' => [],
                'errores' => [$e->getMessage()],
            ], 500);
        }

        return response()->json($resultado);
    }

    /**
     * Debugger de fórmulas del legajo (rastro paso a paso). No persiste.
     */
    public function depurarFormulas(Request $request, LiquidacionCalculadorService $calculador, $id)
    {
        EmpleadoLegajoVisualizacion::autorizarComoEdicion();
        $emp = $this->repository->findOrFail($id);

        $periodo = (string) $request->input('periodo', now()->format('Y-m'));
        $tipo = (string) $request->input('tipo', 'mensual');
        $solo = $request->input('concepto_codigo');
        $soloCodigo = ($solo !== null && $solo !== '') ? (int) $solo : null;

        try {
            $resultado = $calculador->depurarEmpleado($emp, $periodo, $tipo, $soloCodigo);

            return response()->json($resultado);
        } catch (FormulaException $e) {
            return response()->json(['message' => $e->getMessage(), 'pasos' => []], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se pudo depurar: '.$e->getMessage(), 'pasos' => []], 500);
        }
    }

    public function historialBases(Request $request, $id)
    {
        EmpleadoLegajoVisualizacion::autorizarComoEdicion();
        $emp = $this->repository->findOrFail($id);
        $nb = $request->input('nombrebase_id') ? (int) $request->input('nombrebase_id') : null;

        $usaTabla = $emp->categoria
            ? CategoriaOrigenBases::usaTablaCategoria($emp->categoria->origen_bases)
            : true;

        // Categoría T: vigencias de códigos de la tabla → categoría; el resto → legajo.
        $usarCategoria = false;
        if ($usaTabla && $emp->categoria_id) {
            if ($nb === null || $nb <= 0) {
                $usarCategoria = true;
            } else {
                $usarCategoria = Categoria_Base_Sueldos::query()
                    ->where('categoria_id', (int) $emp->categoria_id)
                    ->where('nombrebase_id', $nb)
                    ->exists();
            }
        }

        $historial = $usarCategoria
            ? $this->categoriaBaseService->historial((int) $emp->categoria_id, $nb)
            : $this->baseService->historial((int) $emp->id, $nb);

        return response()->json([
            'historial' => $historial,
        ]);
    }

    public function actualizarVigencia(Request $request, $id, $baseId)
    {
        can('actualizar-empleado-sueldos');
        $emp = $this->repository->findOrFail($id);
        $request->validate(['valor' => 'required|numeric', 'fecha_vigencia' => 'required|date']);

        $base = Empleado_Base_Sueldos::query()
            ->where('id', (int) $baseId)
            ->where('empleado_id', (int) $emp->id)
            ->first();
        if ($base === null) {
            return response()->json(['mensaje' => 'ng', 'error' => 'Vigencia no encontrada.'], 404);
        }
        $this->assertNombrebaseEditableEnLegajo($emp, (int) $base->nombrebase_id);

        $resultado = $this->baseService->actualizarVigencia(
            (int) $baseId,
            (float) $request->input('valor'),
            $request->input('fecha_vigencia'),
            Auth::id()
        );

        if (empty($resultado['ok'])) {
            return response()->json(['mensaje' => 'ng', 'error' => $resultado['error'] ?? 'No se pudo actualizar.'], 422);
        }

        return response()->json([
            'mensaje' => 'ok',
            'historial' => $this->baseService->historial((int) $emp->id, (int) $base->nombrebase_id),
            'bases' => $this->basesGrillaEfectivas($emp),
        ]);
    }

    public function eliminarBase($id, $baseId)
    {
        can('borrar-vigencia-empleado-sueldos');
        $emp = $this->repository->findOrFail($id);

        $base = Empleado_Base_Sueldos::query()
            ->where('id', (int) $baseId)
            ->where('empleado_id', (int) $emp->id)
            ->first();
        if ($base === null) {
            return response()->json(['mensaje' => 'ng', 'error' => 'Vigencia no encontrada.'], 404);
        }
        $this->assertNombrebaseEditableEnLegajo($emp, (int) $base->nombrebase_id);

        $nombrebaseId = (int) $base->nombrebase_id;
        $ok = $this->baseService->eliminarBase((int) $baseId);

        return response()->json([
            'mensaje' => $ok ? 'ok' : 'ng',
            'historial' => $ok ? $this->baseService->historial((int) $emp->id, $nombrebaseId) : [],
            'bases' => $this->basesGrillaEfectivas($emp),
        ]);
    }

    public function eliminarBaseCompleta($id, $nombrebaseId)
    {
        can('borrar-vigencia-empleado-sueldos');
        $emp = $this->repository->findOrFail($id);
        $this->assertNombrebaseEditableEnLegajo($emp, (int) $nombrebaseId);
        $cant = $this->baseService->eliminarBaseCompleta((int) $emp->id, (int) $nombrebaseId);

        return response()->json([
            'mensaje' => $cant > 0 ? 'ok' : 'ng',
            'eliminados' => $cant,
            'bases' => $this->basesGrillaEfectivas($emp),
        ]);
    }

    /** @return array<string, mixed> */
    private function datosFormulario(): array
    {
        return [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'categorias' => Categoria_Sueldos::query()->orderBy('codigo')->get(),
            'agrupamientos' => Agrupamiento_Sueldos::query()->orderBy('codigo')->get(),
            'lugares' => Lugartrabajo_Sueldos::query()->orderBy('codigo')->get(),
            'centrocostos' => Centrocosto::query()->orderBy('codigo')->get(),
            'obrasociales' => Obrasocial_Sueldos::query()->orderBy('codigo')->get(),
            'sindicatos' => Sindicato_Sueldos::query()->orderBy('codigo')->get(),
            'vacaciones' => Vacacion_Sueldos::query()->orderBy('codigo')->get(),
            'arts' => Art_Sueldos::query()->orderBy('codigo')->get(),
            'motivosegreso' => Motivoegreso_Sueldos::query()->orderBy('codigo')->get(),
            'estadosLabels' => EmpleadoEstados::LABELS,
            // B(1)/B(2)/B(3) del legajo: nombres de negocio desde nombase (no los
            // nombres técnicos Anita emp_jornal_dia / emp_jornal_hora).
            'etiquetasRemuneracion' => $this->etiquetasRemuneracionLegajo(),
        ];
    }

    /**
     * @return array{1: string, 2: string, 3: string}
     */
    private function etiquetasRemuneracionLegajo(): array
    {
        $porCodigo = Nombrebase_Sueldos::query()
            ->whereIn('codigo', [1, 2, 3])
            ->pluck('descripcion', 'codigo')
            ->map(fn ($d) => trim((string) $d))
            ->all();

        $pick = static function (array $map, int $codigo, string $fallback): string {
            $txt = trim((string) ($map[$codigo] ?? ''));

            return $txt !== '' ? $txt : $fallback;
        };

        return [
            1 => $pick($porCodigo, 1, 'Sueldo básico'),
            2 => $pick($porCodigo, 2, 'Base 2 (jornal día)'),
            3 => $pick($porCodigo, 3, 'Base 3 (jornal hora)'),
        ];
    }

    /**
     * Con categoría T solo se pueden mutar bases del legajo que no viven en la
     * tabla de la categoría (ej. mutual B7, horas B11). Con C, todas.
     */
    private function assertNombrebaseEditableEnLegajo($emp, int $nombrebaseId): void
    {
        if ($nombrebaseId <= 0) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json(['mensaje' => 'ng', 'error' => 'Base inválida.'], 422)
            );
        }

        if (! $emp->categoria || ! CategoriaOrigenBases::usaTablaCategoria($emp->categoria->origen_bases)) {
            return;
        }

        $enTabla = Categoria_Base_Sueldos::query()
            ->where('categoria_id', (int) $emp->categoria_id)
            ->where('nombrebase_id', $nombrebaseId)
            ->exists();

        if ($enTabla) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'mensaje' => 'ng',
                    'error' => 'Esa base pertenece a la categoría (tabla). Modificala en el ABM de categorías.',
                ], 422)
            );
        }
    }

    /**
     * Ids de nombrebase que ya están en la tabla de la categoría (solo lectura en legajo).
     *
     * @return list<int>
     */
    private function nombrebaseIdsDeCategoria($emp): array
    {
        if (! $emp->categoria_id
            || ! $emp->categoria
            || ! CategoriaOrigenBases::usaTablaCategoria($emp->categoria->origen_bases)) {
            return [];
        }

        return Categoria_Base_Sueldos::query()
            ->where('categoria_id', (int) $emp->categoria_id)
            ->distinct()
            ->pluck('nombrebase_id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Grilla efectiva: categoría T = bases de categoría + bases del legajo que no
     * están en la tabla (ej. mutual B7, horas B11). Categoría C = solo legajo.
     *
     * @return list<array<string, mixed>>
     */
    private function basesGrillaEfectivas(Empleado_Sueldos $emp): array
    {
        $usaTabla = $emp->categoria
            && CategoriaOrigenBases::usaTablaCategoria($emp->categoria->origen_bases);

        if (! $usaTabla) {
            return array_map(static function (array $b): array {
                $b['origen'] = 'empleado';
                $b['origen_label'] = 'Legajo';

                return $b;
            }, $this->baseService->resumenBasesGrilla((int) $emp->id));
        }

        $porCodigo = [];
        if ($emp->categoria_id) {
            foreach ($this->categoriaBaseService->resumenBasesGrilla((int) $emp->categoria_id) as $b) {
                $b['origen'] = 'categoria';
                $b['origen_label'] = 'Categoría';
                $porCodigo[(int) $b['nombrebase_codigo']] = $b;
            }
        }
        foreach ($this->baseService->resumenBasesGrilla((int) $emp->id) as $b) {
            $cod = (int) $b['nombrebase_codigo'];
            if (isset($porCodigo[$cod])) {
                continue;
            }
            $b['origen'] = 'empleado';
            $b['origen_label'] = 'Legajo';
            $porCodigo[$cod] = $b;
        }
        ksort($porCodigo, SORT_NUMERIC);

        return array_values($porCodigo);
    }

    /** Actualiza el ledger de vacaciones (devengado + consumos) del empleado. */
    private function sincronizarSaldosVacaciones(Empleado_Sueldos $empleado): void
    {
        $usuarioId = Auth::id();
        $this->devengamientoVacaciones->recalcularEmpleado(
            $empleado,
            $usuarioId !== null ? (int) $usuarioId : null
        );
    }
}
