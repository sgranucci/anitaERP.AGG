<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\ReporteSueldosDefinibleExport;
use App\Exports\Sueldos\ReporteSueldosDefinibleParidadExport;
use App\Http\Controllers\Controller;
use App\Jobs\Sueldos\EjecutarReporteSueldosDefinibleJob;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Models\Sueldos\ReporteSueldosDefinibleAlerta;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sueldos\Liquidacion_SueldosRepositoryInterface;
use App\Repositories\Sueldos\Obrasocial_SueldosRepositoryInterface;
use App\Repositories\Sueldos\ReporteSueldosDefinibleRepository;
use App\Repositories\Sueldos\Sindicato_SueldosRepositoryInterface;
use App\Services\Sueldos\ReporteSueldosDefinibleAnitaTraductorService;
use App\Services\Sueldos\ReporteSueldosDefinibleDatasetService;
use App\Services\Sueldos\ReporteSueldosDefinibleDistribucionService;
use App\Services\Sueldos\ReporteSueldosDefinibleEjecucionService;
use App\Models\Sueldos\ReporteSueldosDefinibleCertificacion;
use App\Models\Sueldos\ReporteSueldosDefinibleDataset;
use App\Models\Sueldos\ReporteSueldosDefinibleEjecucion;
use App\Models\Sueldos\ReporteSueldosDefinibleEnvio;
use App\Models\Sueldos\ReporteSueldosDefinibleParidad;
use App\Models\Sueldos\ReporteSueldosDefinibleSuscripcion;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleAclSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleAlertaSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleCampoEmpleadoSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleFormulaSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleListadoFiltros;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleParidadAnitaSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleParidadPublicacionSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleProcesador;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleSuscripcionSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleVarianteSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleVersionSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel;

class ReporteSueldosDefinibleController extends Controller
{
    public function __construct(
        private readonly ReporteSueldosDefinibleRepository $repository,
        private readonly ReporteSueldosDefinibleProcesador $procesador,
        private readonly ReporteSueldosDefinibleAnitaTraductorService $traductor,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly Liquidacion_SueldosRepositoryInterface $liquidacionRepository,
        private readonly Obrasocial_SueldosRepositoryInterface $obrasocialRepository,
        private readonly Sindicato_SueldosRepositoryInterface $sindicatoRepository,
        private readonly UsuarioRepositoryInterface $usuarioRepository,
        private readonly ReporteSueldosDefinibleAclSupport $aclSupport,
        private readonly ReporteSueldosDefinibleVersionSupport $versionSupport,
        private readonly ReporteSueldosDefinibleSuscripcionSupport $suscripcionSupport,
        private readonly ReporteSueldosDefinibleEjecucionService $ejecucionService,
        private readonly ReporteSueldosDefinibleAlertaSupport $alertaSupport,
        private readonly ReporteSueldosDefinibleVarianteSupport $varianteSupport,
        private readonly ReporteSueldosDefinibleDatasetService $datasetService,
        private readonly ReporteSueldosDefinibleDistribucionService $distribucionService,
        private readonly ReporteSueldosDefinibleParidadAnitaSupport $paridadAnitaSupport,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-reporte-sueldos-definible');
        $filtros = ReporteSueldosDefinibleListadoFiltros::resolverDesdeRequest($request);
        $coleccion = $this->repository->leeReportes($filtros, true);
        $filtrosQuery = ReporteSueldosDefinibleListadoFiltros::paraQueryString($filtros);
        $coleccion->appends($filtrosQuery);

        return view('sueldos.reporte_definible.index', [
            'coleccion' => $coleccion,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => ReporteSueldosDefinibleListadoFiltros::CAMPOS,
            'tiposListado' => ReporteSueldosDefinibleSupport::tiposListado(),
            'puede_crear' => can('crear-reporte-sueldos-definible', false),
            'puede_editar' => can('editar-reporte-sueldos-definible', false),
            'puede_eliminar' => can('eliminar-reporte-sueldos-definible', false),
            'puede_ejecutar' => can('ejecutar-reporte-sueldos-definible', false) || can('listar-reporte-sueldos-definible', false),
            'puede_importar' => can('importar-reporte-sueldos-definible', false),
            'plantillas' => \App\Models\Sueldos\ReporteSueldosDefinible::query()
                ->where('origen', 'plantilla')
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'titulo', 'tipo']),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-reporte-sueldos-definible');
        ini_set('memory_limit', '512M');
        set_time_limit(120);
        $filtros = ReporteSueldosDefinibleListadoFiltros::resolverDesdeRequest($request, $busqueda);
        if (! $formato) {
            return redirect()->route('reporte_sueldos_definible', ReporteSueldosDefinibleListadoFiltros::paraQueryString($filtros));
        }
        $filas = $this->repository->leeReportes($filtros, false);
        $resultado = [
            'columnas' => [
                ['nro' => 1, 'descripcion' => 'Código', 'numerica' => false],
                ['nro' => 2, 'descripcion' => 'Título', 'numerica' => false],
                ['nro' => 3, 'descripcion' => 'Tipo', 'numerica' => false],
                ['nro' => 4, 'descripcion' => 'Columnas', 'numerica' => true],
            ],
            'filas' => $filas->map(fn ($r) => [
                'legajo' => $r->codigo,
                'nombre' => $r->titulo,
                'c1' => $r->codigo,
                'c2' => $r->titulo,
                'c3' => $r->tipo,
                'c4' => (int) ($r->columnas_count ?? 0),
            ])->all(),
            'totales' => [],
        ];

        return $this->descargarResultado($resultado, 'Catálogo listados definibles sueldos', '', $formato);
    }

    public function crear()
    {
        can('crear-reporte-sueldos-definible');

        return view('sueldos.reporte_definible.crear', [
            'tiposListado' => ReporteSueldosDefinibleSupport::tiposListado(),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function guardar(Request $request)
    {
        can('crear-reporte-sueldos-definible');
        $data = $request->validate([
            'codigo' => 'nullable|integer|min:1|unique:reporte_sueldos_definible,codigo',
            'titulo' => 'required|string|max:80',
            'tipo' => 'required|in:osocial,sindicato,generico',
            'asociado_codigo' => 'nullable|integer|min:0',
            'empresa_id' => 'nullable|integer',
            'observaciones' => 'nullable|string|max:2000',
            'activo' => 'nullable|boolean',
        ]);
        $this->normalizarYValidarAsociado($data);
        $data['activo'] = $request->boolean('activo', true);
        $data['origen'] = 'manual';
        $reporte = $this->repository->create($data);

        return redirect()
            ->route('editar_reporte_sueldos_definible', ['id' => $reporte->id])
            ->with('mensaje', 'Listado creado. Defina columnas y conceptos.');
    }

    public function editar($id)
    {
        can('editar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $data = $this->repository->findConEstructura((int) $id);
        if (! $data) {
            return redirect()->route('reporte_sueldos_definible')->with('mensaje_error', 'Listado no encontrado.');
        }

        $suscripciones = $this->suscripcionSupport->listar((int) $id);

        return view('sueldos.reporte_definible.editar', [
            'data' => $data,
            'tiposListado' => ReporteSueldosDefinibleSupport::tiposListado(),
            'contenidos' => ReporteSueldosDefinibleSupport::contenidosColumna(),
            'camposEmpleado' => ReporteSueldosDefinibleCampoEmpleadoSupport::catalogo(),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'versiones' => $data->versiones()->limit(20)->get(),
            'aclUsuarios' => $this->aclSupport->usuarioIds((int) $id),
            'usuariosAcl' => $this->usuarioRepository->listadoOperativoParaSelector(
                null,
                null,
                ['id', 'nombre', 'email', 'usuario'],
                soloConEmail: false,
                with: []
            ),
            'suscripciones' => $suscripciones,
            'enviosPorSuscripcion' => ReporteSueldosDefinibleEnvio::query()
                ->whereIn('suscripcion_id', $data->suscripciones()->pluck('id'))
                ->orderByDesc('id')
                ->get()
                ->groupBy('suscripcion_id')
                ->map(fn ($grupo) => $grupo->take(8)),
            'liquidacionesFijas' => Liquidacion_Sueldos::query()
                ->whereIn('id', collect($suscripciones)
                    ->map(fn ($s) => (int) (($s->filtros_default['liquidacion_id'] ?? 0)))
                    ->filter()
                    ->all())
                ->get()
                ->keyBy('id'),
            'owner' => $data->owner,
            'alertas' => $this->alertaSupport->listar((int) $id),
            'ejecuciones' => $data->ejecuciones()->with('usuario:id,nombre')->limit(30)->get(),
            'tiposAlerta' => \App\Models\Sueldos\ReporteSueldosDefinibleAlerta::tipos(),
            'periodicidades' => \App\Models\Sueldos\ReporteSueldosDefinibleSuscripcion::periodicidades(),
            'dimensionesBurst' => \App\Models\Sueldos\ReporteSueldosDefinibleSuscripcion::dimensionesBurst(),
        ]);
    }

    public function consultaAsociado(Request $request)
    {
        if (! $this->puedeConsultarAsociado()) {
            abort(403);
        }

        $tipo = (string) $request->input('tipo');
        $this->assertTipoAsociado($tipo);
        $filas = $tipo === ReporteSueldosDefinibleSupport::TIPO_OSOCIAL
            ? $this->obrasocialRepository->listadoParaConsulta((string) $request->input('consulta', ''))
            : $this->sindicatoRepository->listadoParaConsulta((string) $request->input('consulta', ''));
        $puedeAbrirAbm = $tipo === ReporteSueldosDefinibleSupport::TIPO_OSOCIAL
            ? can('editar-obrasocial-sueldos', false)
            : can('editar-sindicato-sueldos', false);

        $html = '';
        foreach ($filas as $fila) {
            $html .= '<tr>';
            $html .= '<td class="codigoasociado">'.e($fila->codigo).'</td>';
            $html .= '<td class="descripcionasociado">'.e($fila->descripcion).'</td>';
            $html .= '<td>'.e($fila->numero ?? '').'</td>';
            $html .= '<td class="text-nowrap">';
            $html .= '<button type="button" class="btn btn-warning btn-sm eligeconsultaasociado">Elegir</button>';
            if ($puedeAbrirAbm) {
                $ruta = $tipo === ReporteSueldosDefinibleSupport::TIPO_OSOCIAL
                    ? route('editar_obrasocial_sueldos', ['id' => $fila->id])
                    : route('editar_sindicato_sueldos', ['id' => $fila->id]);
                $html .= ' <a class="btn btn-info btn-sm" href="'.e($ruta).'" target="_blank" rel="noopener">Consultar</a>';
            }
            $html .= '</td></tr>';
        }

        if ($html === '') {
            $html = '<tr><td colspan="4" class="text-center text-muted">Sin resultados</td></tr>';
        }

        return response()->json(['data' => $html]);
    }

    public function leerAsociado($tipo, $codigo)
    {
        if (! $this->puedeConsultarAsociado()) {
            abort(403);
        }

        $tipo = (string) $tipo;
        $this->assertTipoAsociado($tipo);
        $codigo = (int) preg_replace('/\D+/', '', (string) $codigo);
        $fila = $tipo === ReporteSueldosDefinibleSupport::TIPO_OSOCIAL
            ? $this->obrasocialRepository->findPorCodigo($codigo)
            : $this->sindicatoRepository->findPorCodigo($codigo);

        if (! $fila) {
            return response()->json(['error' => 'Asociado no encontrado'], 404);
        }

        return response()->json([
            'codigo' => (int) $fila->codigo,
            'descripcion' => (string) $fila->descripcion,
            'numero' => (string) ($fila->numero ?? ''),
            'tipo' => $tipo,
        ]);
    }

    public function actualizar(Request $request, $id)
    {
        can('actualizar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $data = $request->validate([
            'titulo' => 'required|string|max:80',
            'tipo' => 'required|in:osocial,sindicato,generico',
            'asociado_codigo' => 'nullable|integer|min:0',
            'empresa_id' => 'nullable|integer',
            'observaciones' => 'nullable|string|max:2000',
            'activo' => 'nullable|boolean',
        ]);
        $this->normalizarYValidarAsociado($data);
        $data['activo'] = $request->boolean('activo', true);
        $this->repository->update((int) $id, $data);

        return redirect()
            ->route('editar_reporte_sueldos_definible', ['id' => $id])
            ->with('mensaje', 'Listado actualizado.');
    }

    public function eliminar($id)
    {
        can('eliminar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $this->repository->delete((int) $id);

        return redirect()->route('reporte_sueldos_definible')->with('mensaje', 'Listado eliminado.');
    }

    public function copiar($id)
    {
        can('crear-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $nuevo = $this->repository->copiar((int) $id);
        if (! $nuevo) {
            return redirect()->route('reporte_sueldos_definible')->with('mensaje_error', 'No se pudo copiar.');
        }

        return redirect()
            ->route('editar_reporte_sueldos_definible', ['id' => $nuevo->id])
            ->with('mensaje', 'Copia creada.');
    }

    public function crearDesdePlantilla(Request $request)
    {
        can('crear-reporte-sueldos-definible');
        $data = $request->validate(['plantilla_id' => 'required|integer']);
        $nuevo = $this->repository->crearDesdePlantilla((int) $data['plantilla_id']);
        if (! $nuevo) {
            return back()->with('mensaje_error', 'Plantilla no encontrada.');
        }

        return redirect()
            ->route('editar_reporte_sueldos_definible', ['id' => $nuevo->id])
            ->with('mensaje', 'Listado creado desde plantilla.');
    }

    public function guardarColumna(Request $request, $id)
    {
        can('actualizar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $data = $request->validate([
            'columna_id' => 'nullable|integer',
            'nro_columna' => 'required|integer|min:1',
            'descripcion' => 'required|string|max:80',
            'contenido' => 'required|in:importe,cantidad,valor,campo_empleado,concepto_ganancias,formula',
            'campo_empleado' => 'nullable|integer|min:1',
            'largo' => 'nullable|integer|min:1|max:80',
            'formula' => 'nullable|string|max:255',
            'orden' => 'nullable|integer',
            'conceptos' => 'nullable|array',
            'conceptos.*.concepto_codigo' => 'nullable|integer|exists:concepto_sueldos,codigo',
            'conceptos.*.signo' => 'nullable|in:+,-',
        ]);
        $reporte = $this->repository->findConEstructura((int) $id);
        abort_if(! $reporte, 404);
        $columnaId = (int) ($data['columna_id'] ?? 0);
        $nro = (int) $data['nro_columna'];
        if ($reporte->columnas->contains(fn ($columna) => (int) $columna->nro_columna === $nro
            && (int) $columna->id !== $columnaId)) {
            throw ValidationException::withMessages(['nro_columna' => 'Ya existe una columna con ese número.']);
        }

        $contenido = (string) $data['contenido'];
        if ($contenido === ReporteSueldosDefinibleSupport::CONTENIDO_CAMPO_EMPLEADO) {
            $campo = (int) ($data['campo_empleado'] ?? 0);
            if (! isset(ReporteSueldosDefinibleCampoEmpleadoSupport::catalogo()[$campo])) {
                throw ValidationException::withMessages(['campo_empleado' => 'Seleccione un campo de empleado válido.']);
            }
            $data['conceptos'] = [];
            $data['formula'] = null;
        } elseif ($contenido === ReporteSueldosDefinibleSupport::CONTENIDO_FORMULA) {
            $disponibles = $reporte->columnas->pluck('nro_columna')->map(fn ($valor) => (int) $valor)->all();
            if (! in_array($nro, $disponibles, true)) {
                $disponibles[] = $nro;
            }
            $errores = ReporteSueldosDefinibleFormulaSupport::validar(
                (string) ($data['formula'] ?? ''),
                $disponibles,
                $nro
            );
            if ($errores !== []) {
                throw ValidationException::withMessages(['formula' => $errores]);
            }
            $formulas = $reporte->columnas
                ->filter(fn ($columna) => $columna->contenido === ReporteSueldosDefinibleSupport::CONTENIDO_FORMULA
                    && (int) $columna->id !== $columnaId)
                ->mapWithKeys(fn ($columna) => [(int) $columna->nro_columna => (string) $columna->formula])
                ->all();
            $formulas[$nro] = (string) $data['formula'];
            if (ReporteSueldosDefinibleFormulaSupport::tieneCiclo($formulas)) {
                throw ValidationException::withMessages(['formula' => 'La fórmula genera una dependencia circular entre columnas.']);
            }
            $data['conceptos'] = [];
            $data['campo_empleado'] = null;
        } else {
            $conceptos = collect($data['conceptos'] ?? [])
                ->filter(fn ($concepto) => (int) ($concepto['concepto_codigo'] ?? 0) > 0);
            if ($conceptos->isEmpty()) {
                throw ValidationException::withMessages(['conceptos' => 'Agregue al menos un concepto a la columna numérica.']);
            }
            if ($conceptos->pluck('concepto_codigo')->map(fn ($codigo) => (int) $codigo)->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['conceptos' => 'Un concepto no puede repetirse dentro de la misma columna.']);
            }
            $codigos = $conceptos->pluck('concepto_codigo')->map(fn ($c) => (int) $c)->all();
            $activos = \App\Models\Sueldos\Concepto_Sueldos::query()
                ->whereIn('codigo', $codigos)
                ->where('activo', true)
                ->pluck('codigo')
                ->map(fn ($c) => (int) $c)
                ->all();
            $inactivos = array_values(array_diff($codigos, $activos));
            if ($inactivos !== []) {
                throw ValidationException::withMessages([
                    'conceptos' => 'Conceptos inactivos o inexistentes: '.implode(', ', $inactivos),
                ]);
            }
            $data['conceptos'] = $conceptos->values()->all();
            $data['campo_empleado'] = null;
            $data['formula'] = null;
        }
        $esActualizacion = $columnaId > 0;
        $columna = $this->repository->guardarColumna((int) $id, $data, $data['columna_id'] ?? null);
        $conceptos = isset($data['conceptos']) && is_array($data['conceptos'])
            ? $data['conceptos']
            : [];
        $this->repository->sincronizarConceptos((int) $columna->id, $conceptos);

        return redirect()
            ->route('editar_reporte_sueldos_definible', [
                'id' => $id,
                'tab' => 'diseno',
                'columna' => $columna->id,
            ])
            ->with('mensaje', $esActualizacion ? 'Columna actualizada.' : 'Columna grabada.');
    }

    public function eliminarColumna($id, $columnaId)
    {
        can('actualizar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $this->repository->eliminarColumna((int) $id, (int) $columnaId);

        return redirect()
            ->route('editar_reporte_sueldos_definible', ['id' => $id])
            ->with('mensaje', 'Columna eliminada.');
    }

    public function importarAnita(Request $request)
    {
        can('importar-reporte-sueldos-definible');
        $data = $request->validate([
            'desde' => 'nullable|integer',
            'hasta' => 'nullable|integer',
            'listado' => 'nullable|integer',
            'ejecutar' => 'nullable|boolean',
        ]);
        $d = $h = null;
        if (! empty($data['listado'])) {
            $d = $h = (int) $data['listado'];
        } else {
            $d = isset($data['desde']) ? (int) $data['desde'] : null;
            $h = isset($data['hasta']) ? (int) $data['hasta'] : $d;
        }
        $dry = ! $request->boolean('ejecutar');
        $result = $this->traductor->importar($d, $h, true, $dry);
        $msg = sprintf(
            '%s: %d nuevos, %d actualizados, %d columnas, %d conceptos.',
            $dry ? 'Dry-run (no grabó)' : 'Importado',
            $result['importados'],
            $result['actualizados'],
            $result['columnas'],
            $result['conceptos']
        );
        if ($result['errores'] !== []) {
            return redirect()->route('reporte_sueldos_definible')->with('mensaje_error', $msg.' '.implode(' ', $result['errores']));
        }

        return redirect()->route('reporte_sueldos_definible')->with('mensaje', $msg);
    }

    public function ejecutar(Request $request, $id = null)
    {
        can('ejecutar-reporte-sueldos-definible', false) || can('listar-reporte-sueldos-definible');
        $id = (int) ($id ?: $request->input('id'));
        if ($id <= 0) {
            return redirect()->route('reporte_sueldos_definible')->with('mensaje_error', 'Indique listado.');
        }
        $this->assertAcl($id);
        $reporte = $this->repository->findConEstructura($id);
        if (! $reporte) {
            return redirect()->route('reporte_sueldos_definible')->with('mensaje_error', 'Listado no encontrado.');
        }

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtrosEjec = $this->resolverFiltrosEjecucion($request);
        $varianteAplicada = null;
        if ($request->filled('variante_id')) {
            $varianteAplicada = $this->varianteSupport->listar((int) $id, (int) Auth::id())
                ->firstWhere('id', (int) $request->input('variante_id'));
            if ($varianteAplicada) {
                $base = is_array($varianteAplicada->filtros) ? $varianteAplicada->filtros : [];
                $filtrosEjec = array_replace($base, array_filter(
                    $filtrosEjec,
                    fn ($valor) => $valor !== null && $valor !== '' && $valor !== []
                ));
                $orden = is_array($varianteAplicada->ordenamiento) ? $varianteAplicada->ordenamiento : [];
                if (! empty($orden['columna']) && empty($filtrosEjec['orden_columna'])) {
                    $filtrosEjec['orden_columna'] = (int) $orden['columna'];
                }
                if (! empty($orden['direccion']) && $request->missing('orden_direccion')) {
                    $filtrosEjec['orden_direccion'] = $orden['direccion'] === 'asc' ? 'asc' : 'desc';
                }
            }
        }
        $liquidacionesSeleccionadas = Liquidacion_Sueldos::query()
            ->whereIn('id', array_filter([
                $filtrosEjec['liquidacion_id'] ?? null,
                $filtrosEjec['liquidacion_id_comparar'] ?? null,
            ]))
            ->get()
            ->keyBy('id');
        $resultado = null;
        $pagina = null;
        $ejecucion = null;
        if ($request->boolean('consultar') || $request->filled('formato')) {
            $corrida = $this->ejecucionService->ejecutar($reporte, $filtrosEjec, [
                'usuario_id' => Auth::id(),
                'origen' => 'manual',
            ]);
            $resultado = $corrida['resultado'];
            $ejecucion = $corrida['ejecucion'];
            $visibles = array_values(array_filter(array_map('intval', (array) (
                $request->input('columnas_visibles')
                ?? $varianteAplicada?->columnas_visibles
                ?? []
            ))));
            if ($visibles !== [] && isset($resultado['columnas'])) {
                $resultado['columnas'] = array_values(array_filter(
                    $resultado['columnas'],
                    fn ($columna) => in_array((int) ($columna['nro'] ?? 0), $visibles, true)
                ));
            }
            $page = max(1, (int) $request->input('page', 1));
            $perPage = 50;
            $items = collect($resultado['filas'] ?? []);
            $pagina = new LengthAwarePaginator(
                $items->forPage($page, $perPage)->values(),
                $items->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return view('sueldos.reporte_definible.ejecutar', [
            'data' => $reporte,
            'empresa_query' => $empresaQuery,
            'liquidacionSeleccionada' => $liquidacionesSeleccionadas->get($filtrosEjec['liquidacion_id'] ?? 0),
            'liquidacionCompararSeleccionada' => $liquidacionesSeleccionadas->get($filtrosEjec['liquidacion_id_comparar'] ?? 0),
            'filtrosEjec' => $filtrosEjec,
            'resultado' => $resultado,
            'ejecucion' => $ejecucion,
            'pagina' => $pagina,
            'agrupaciones' => ReporteSueldosDefinibleSupport::agrupaciones(),
            'variantes' => $this->varianteSupport->listar((int) $id, (int) Auth::id()),
            'varianteAplicada' => $varianteAplicada,
            'columnasVisibles' => array_values(array_filter(array_map('intval', (array) (
                $request->input('columnas_visibles')
                ?? $varianteAplicada?->columnas_visibles
                ?? []
            )))),
            'puede_ver_empleado' => can('editar-empleado-sueldos', false) || can('listar-empleado-sueldos', false),
        ]);
    }

    public function encolar(Request $request, $id)
    {
        can('ejecutar-reporte-sueldos-definible', false) || can('listar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $reporte = $this->repository->findConEstructura((int) $id);
        abort_if(! $reporte, 404);

        $ejecucion = $this->ejecucionService->crearPendiente(
            $reporte,
            $this->resolverFiltrosEjecucion($request),
            ['usuario_id' => Auth::id(), 'origen' => 'cola']
        );
        EjecutarReporteSueldosDefinibleJob::dispatch((int) $ejecucion->id);

        return redirect()
            ->route('editar_reporte_sueldos_definible', ['id' => $id, 'tab' => 'operacion'])
            ->with('mensaje', 'Ejecución #'.$ejecucion->id.' enviada a la cola reports.');
    }

    public function previewEstructura($id)
    {
        can('editar-reporte-sueldos-definible', false) || can('listar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $reporte = $this->repository->findConEstructura((int) $id);
        abort_if(! $reporte, 404);

        $ejecucion = null;
        if ($reporte->publicado_ejecucion_id) {
            $ejecucion = ReporteSueldosDefinibleEjecucion::query()
                ->where('reporte_sueldos_definible_id', (int) $id)
                ->find($reporte->publicado_ejecucion_id);
        }
        $ejecucion ??= $reporte->ejecuciones()
            ->whereIn('estado', [
                ReporteSueldosDefinibleEjecucion::ESTADO_OK,
                ReporteSueldosDefinibleEjecucion::ESTADO_ADVERTENCIA,
            ])
            ->first();
        $resultado = $ejecucion?->resultadoDecodificado() ?? [];

        return response()->json([
            'columnas' => $reporte->columnas->map(fn ($columna) => [
                'nro_columna' => (int) $columna->nro_columna,
                'descripcion' => (string) $columna->descripcion,
                'contenido' => (string) $columna->contenido,
                'formula' => $columna->formula,
            ])->values(),
            'filas' => array_slice((array) ($resultado['filas'] ?? []), 0, 12),
            'fuente' => $ejecucion
                ? ($ejecucion->id === $reporte->publicado_ejecucion_id ? 'Ejecución publicada #'.$ejecucion->id : 'Última ejecución #'.$ejecucion->id)
                : 'Sin ejecución disponible',
        ]);
    }

    public function paridadAnita(Request $request, $id)
    {
        can('listar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $reporte = $this->repository->findConEstructura((int) $id);
        abort_if(! $reporte, 404);
        [$ejecucion, $filas] = $this->resolverParidad($request, $reporte);
        $soloDiferencias = $request->boolean('solo_diferencias');
        if ($soloDiferencias) {
            $filas = $filas->where('coincide', false)->values();
        }

        $certificaciones = ReporteSueldosDefinibleCertificacion::query()
            ->with(['usuario:id,nombre', 'liquidacion:id,numero'])
            ->where('reporte_sueldos_definible_id', (int) $id)
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('sueldos.reporte_definible.paridad', [
            'data' => $reporte,
            'ejecucion' => $ejecucion,
            'filas' => $filas,
            'soloDiferencias' => $soloDiferencias,
            'certificaciones' => $certificaciones,
            'nominaDefault' => ReporteSueldosDefinibleParidadPublicacionSupport::nominaRequerida($reporte),
            'resumen' => [
                'columnas' => $filas->count(),
                'coinciden' => $filas->where('coincide', true)->count(),
                'diferencias' => $filas->where('coincide', false)->count(),
                'max_diferencia' => (float) $filas->max(fn ($fila) => abs((float) $fila->diferencia)),
            ],
        ]);
    }

    public function certificarParidadAnita(Request $request, $id)
    {
        can('actualizar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $reporte = $this->repository->findConEstructura((int) $id);
        abort_if(! $reporte, 404);

        $data = $request->validate([
            'ejecucion_id' => 'required|integer|min:1',
            'liquidacion_id' => 'required|integer|min:1',
            'nomina' => 'required|in:normal,confidencial,ambos',
            'comentario' => 'nullable|string|max:2000',
        ]);

        $ejecucion = ReporteSueldosDefinibleEjecucion::query()
            ->where('reporte_sueldos_definible_id', (int) $id)
            ->whereKey((int) $data['ejecucion_id'])
            ->firstOrFail();

        $cert = ReporteSueldosDefinibleParidadPublicacionSupport::certificar(
            $reporte,
            $ejecucion,
            (int) $data['liquidacion_id'],
            (string) $data['nomina'],
            (int) Auth::id(),
            $data['comentario'] ?? null
        );

        return redirect()
            ->route('paridad_reporte_sueldos_definible', [
                'id' => $id,
                'ejecucion_id' => $ejecucion->id,
                'liquidacion_id' => $data['liquidacion_id'],
            ])
            ->with('mensaje', 'Certificación #'.$cert->id.' registrada (nómina '.$cert->nomina.').');
    }

    public function actaCertificacionParidad($id, $certificacionId)
    {
        can('listar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $reporte = $this->repository->find((int) $id);
        abort_if(! $reporte, 404);
        $cert = ReporteSueldosDefinibleCertificacion::query()
            ->with(['usuario:id,nombre,email', 'liquidacion:id,numero,descripcion', 'ejecucion'])
            ->where('reporte_sueldos_definible_id', (int) $id)
            ->whereKey((int) $certificacionId)
            ->firstOrFail();

        $pdf = Pdf::loadView('sueldos.reporte_definible.acta_certificacion_paridad', [
            'data' => $reporte,
            'certificacion' => $cert,
            'logos' => \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                collect([(object) ['nombreempresa' => config('app.empresa')]])
            ),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('acta_paridad_reporte_'.$id.'_cert_'.$certificacionId.'.pdf');
    }

    public function exportarParidadAnita(Request $request, $id, $formato = null)
    {
        can('listar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $formato = strtoupper((string) $formato);
        if (! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()->route('paridad_reporte_sueldos_definible', ['id' => $id] + $request->query());
        }
        $reporte = $this->repository->findConEstructura((int) $id);
        abort_if(! $reporte, 404);
        [$ejecucion, $filas] = $this->resolverParidad($request, $reporte);
        if ($request->boolean('solo_diferencias')) {
            $filas = $filas->where('coincide', false)->values();
        }
        $subtitulo = sprintf(
            'Ejecución %s%s',
            $ejecucion ? '#'.$ejecucion->id : 'en línea',
            $request->boolean('solo_diferencias') ? ' · solo diferencias' : ''
        );
        if ($formato === 'PDF') {
            return Pdf::loadView('sueldos.reporte_definible.paridad', [
                'data' => $reporte,
                'ejecucion' => $ejecucion,
                'filas' => $filas,
                'soloDiferencias' => $request->boolean('solo_diferencias'),
                'resumen' => [
                    'columnas' => $filas->count(),
                    'coinciden' => $filas->where('coincide', true)->count(),
                    'diferencias' => $filas->where('coincide', false)->count(),
                    'max_diferencia' => (float) $filas->max(fn ($fila) => abs((float) $fila->diferencia)),
                ],
                'esPdf' => true,
            ])->setPaper('legal', 'landscape')->download('paridad_reporte_sueldos_'.$id.'.pdf');
        }

        $export = (new ReporteSueldosDefinibleParidadExport)->parametros(
            $filas,
            'Paridad Anita — '.$reporte->codigo.' · '.$reporte->titulo,
            $subtitulo
        );

        return $formato === 'CSV'
            ? $export->download('paridad_reporte_sueldos_'.$id.'.csv', Excel::CSV)
            : $export->download('paridad_reporte_sueldos_'.$id.'.xlsx', Excel::XLSX);
    }

    public function publicarDataset(Request $request, $id, $datasetId)
    {
        can('actualizar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $reporte = $this->repository->find((int) $id);
        abort_if(! $reporte, 404);
        $dataset = ReporteSueldosDefinibleDataset::query()
            ->where('reporte_sueldos_definible_id', (int) $id)
            ->whereKey((int) $datasetId)
            ->firstOrFail();
        $this->datasetService->publicar($reporte, $dataset, $request->input('comentario'));

        return back()->with('mensaje', 'Dataset '.$dataset->uuid.' publicado (gobierno separado de la definición).');
    }

    public function exportar(Request $request, $id, $formato)
    {
        can('ejecutar-reporte-sueldos-definible', false) || can('listar-reporte-sueldos-definible');
        ini_set('memory_limit', '512M');
        set_time_limit(180);
        $this->assertAcl((int) $id);
        $reporte = $this->repository->findConEstructura((int) $id);
        if (! $reporte) {
            return redirect()->route('reporte_sueldos_definible')->with('mensaje_error', 'Listado no encontrado.');
        }
        $filtrosEjec = $this->resolverFiltrosEjecucion($request);
        $corrida = $this->ejecucionService->ejecutar($reporte, $filtrosEjec, [
            'usuario_id' => Auth::id(),
            'origen' => 'manual',
        ]);
        $resultado = $corrida['resultado'];
        $subtitulo = sprintf(
            'Origen %s | Liq %s | Agrupación %s',
            $filtrosEjec['origen'],
            $filtrosEjec['liquidacion_id'] ?? '—',
            $filtrosEjec['agrupacion']
        );

        return $this->descargarResultado(
            $resultado,
            $reporte->titulo.' ('.$reporte->codigo.')',
            $subtitulo,
            strtoupper((string) $formato)
        );
    }

    public function drillJson(Request $request, $id)
    {
        can('ejecutar-reporte-sueldos-definible', false) || can('listar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $reporte = $this->repository->findConEstructura((int) $id);
        if (! $reporte) {
            return response()->json(['ok' => false, 'mensaje' => 'No encontrado'], 404);
        }
        $lineas = $this->procesador->drillCelda(
            $reporte,
            (int) $request->input('columna_id'),
            (int) $request->input('liquidacion_id'),
            (int) $request->input('legajo')
        );

        return response()->json(['ok' => true, 'lineas' => $lineas]);
    }

    public function publicarVersion(Request $request, $id)
    {
        can('actualizar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $reporte = $this->repository->findConEstructura((int) $id);
        if (! $reporte) {
            return back()->with('mensaje_error', 'No encontrado');
        }
        $ver = $this->versionSupport->publicar($reporte, $request->input('comentario'));

        return back()->with('mensaje', 'Versión '.$ver->version.' publicada.');
    }

    public function restaurarVersion($id, $versionId)
    {
        can('actualizar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $reporte = $this->repository->find((int) $id);
        if (! $reporte) {
            return back()->with('mensaje_error', 'No encontrado');
        }
        $this->versionSupport->restaurar($reporte, (int) $versionId);

        return redirect()
            ->route('editar_reporte_sueldos_definible', ['id' => $id])
            ->with('mensaje', 'Versión restaurada y publicada como versión nueva.');
    }

    public function guardarAcl(Request $request, $id)
    {
        can('actualizar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $ids = $request->input('usuario_ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }
        $this->aclSupport->sincronizar((int) $id, $ids);

        return back()->with('mensaje', 'ACL actualizado.');
    }

    public function guardarSuscripcion(Request $request, $id)
    {
        can('actualizar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $data = $request->validate([
            'suscripcion_id' => 'nullable|integer',
            'email' => 'required|email|max:120',
            'nombre' => 'nullable|string|max:100',
            'destinatarios' => 'nullable|string|max:2000',
            'formato' => 'nullable|in:PDF,EXCEL,CSV',
            'periodicidad' => 'nullable|in:mensual,semanal,diaria',
            'dia_mes' => 'nullable|integer|min:1|max:28',
            'dia_semana' => 'nullable|integer|min:1|max:7',
            'hora' => 'nullable|date_format:H:i',
            'periodo_relativo' => 'nullable|in:ultima_liquidacion,fijo',
            'liquidacion_id' => 'nullable|required_if:periodo_relativo,fijo|integer|exists:liquidacion_sueldos,id',
            'burst_dimension' => 'nullable|in:ninguna,centrocosto,lugartrabajo,agrupamiento,empleado',
            'mensaje' => 'nullable|string|max:2000',
            'publicar' => 'nullable|boolean',
            'solo_si_alertas' => 'nullable|boolean',
        ]);
        $data['publicar'] = $request->boolean('publicar');
        $data['solo_si_alertas'] = $request->boolean('solo_si_alertas');
        if (($data['periodo_relativo'] ?? 'ultima_liquidacion') === 'fijo'
            && ! $this->liquidacionRepository->findParaConsulta((int) ($data['liquidacion_id'] ?? 0))) {
            throw ValidationException::withMessages([
                'liquidacion_id' => 'La liquidación no existe o no está autorizada para el usuario.',
            ]);
        }
        $data['filtros_default'] = $this->resolverFiltrosEjecucion($request);
        if (($data['periodo_relativo'] ?? 'ultima_liquidacion') !== 'fijo') {
            $data['filtros_default']['liquidacion_id'] = null;
        }
        $this->suscripcionSupport->guardar((int) $id, $data);

        return back()->with('mensaje', ! empty($data['suscripcion_id']) ? 'Suscripción actualizada.' : 'Suscripción agregada.');
    }

    public function eliminarSuscripcion($id, $suscripcionId)
    {
        can('actualizar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $this->suscripcionSupport->eliminar((int) $id, (int) $suscripcionId);

        return back()->with('mensaje', 'Suscripción eliminada.');
    }

    public function probarSuscripcion(Request $request, $id, $suscripcionId)
    {
        can('actualizar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $suscripcion = ReporteSueldosDefinibleSuscripcion::query()
            ->with('reporte.columnas.conceptos')
            ->where('reporte_sueldos_definible_id', (int) $id)
            ->findOrFail((int) $suscripcionId);
        $respuesta = $this->distribucionService->enviar($suscripcion, $request->boolean('dry_run'));
        $clave = $respuesta['estado'] === 'error' ? 'mensaje_error' : 'mensaje';

        return back()->with($clave, $respuesta['mensaje']);
    }

    public function guardarDestinatarioSuscripcion(Request $request, $id, $suscripcionId)
    {
        can('actualizar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $data = $request->validate([
            'dimension_clave' => 'required|string|max:120',
            'dimension_etiqueta' => 'nullable|string|max:160',
            'email' => 'required|email|max:120',
        ]);
        $suscripcion = \App\Models\Sueldos\ReporteSueldosDefinibleSuscripcion::query()
            ->where('reporte_sueldos_definible_id', (int) $id)
            ->findOrFail((int) $suscripcionId);
        $suscripcion->destinatariosBurst()->updateOrCreate(
            [
                'dimension_clave' => trim($data['dimension_clave']),
                'email' => strtolower(trim($data['email'])),
                'usuario_id' => null,
            ],
            [
                'dimension_etiqueta' => trim((string) ($data['dimension_etiqueta'] ?? '')) ?: null,
                'activo' => true,
            ]
        );

        return back()->with('mensaje', 'Destinatario del segmento agregado.');
    }

    public function eliminarDestinatarioSuscripcion($id, $suscripcionId, $destinatarioId)
    {
        can('actualizar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        \App\Models\Sueldos\ReporteSueldosDefinibleSuscripcionDestinatario::query()
            ->where('suscripcion_id', (int) $suscripcionId)
            ->whereHas('suscripcion', fn ($q) => $q->where('reporte_sueldos_definible_id', (int) $id))
            ->whereKey((int) $destinatarioId)
            ->delete();

        return back()->with('mensaje', 'Destinatario del segmento eliminado.');
    }

    public function guardarAlerta(Request $request, $id)
    {
        can('actualizar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $data = $request->validate([
            'nombre' => 'nullable|string|max:100',
            'tipo' => 'required|string|max:30',
            'columna_nro' => 'nullable|integer|min:1',
            'operador' => 'required|string|max:10',
            'umbral' => 'nullable|numeric',
            'umbral_hasta' => 'nullable|numeric',
            'bloqueante' => 'nullable|boolean',
        ]);
        $data['bloqueante'] = $request->boolean('bloqueante');
        $data['activo'] = true;
        $this->alertaSupport->guardar((int) $id, $data);

        return back()->with('mensaje', 'Control de calidad agregado.');
    }

    public function eliminarAlerta($id, $alertaId)
    {
        can('actualizar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        \App\Models\Sueldos\ReporteSueldosDefinibleAlerta::query()
            ->where('reporte_sueldos_definible_id', (int) $id)
            ->whereKey((int) $alertaId)
            ->delete();

        return back()->with('mensaje', 'Control eliminado.');
    }

    public function guardarVariante(Request $request, $id)
    {
        can('listar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $data = $request->validate([
            'nombre' => 'required|string|max:80',
            'compartida' => 'nullable|boolean',
            'predeterminada' => 'nullable|boolean',
            'pivot_spec' => 'nullable|array',
            'columnas_visibles' => 'nullable|array',
            'ordenamiento' => 'nullable|array',
            'visualizacion' => 'nullable|array',
        ]);
        $data['filtros'] = $this->resolverFiltrosEjecucion($request);
        $data['agrupaciones'] = $data['filtros']['agrupaciones'] ?? [];
        $data['compartida'] = $request->boolean('compartida');
        $data['predeterminada'] = $request->boolean('predeterminada');
        $data['columnas_visibles'] = array_values(array_filter(array_map('intval', (array) ($data['columnas_visibles'] ?? []))));
        if ($request->filled('pivot_spec_json') && empty($data['pivot_spec'])) {
            $decoded = json_decode((string) $request->input('pivot_spec_json'), true);
            $data['pivot_spec'] = is_array($decoded) ? $decoded : null;
        }
        $data['ordenamiento'] = $data['ordenamiento'] ?? [
            'columna' => $data['filtros']['orden_columna'] ?? 0,
            'direccion' => $data['filtros']['orden_direccion'] ?? 'desc',
        ];
        $variante = $this->varianteSupport->guardar((int) $id, (int) Auth::id(), $data);

        return redirect()
            ->route('ejecutar_reporte_sueldos_definible', ['id' => $id, 'variante_id' => $variante->id] + $data['filtros'])
            ->with('mensaje', 'Variante guardada.');
    }

    public function eliminarVariante($id, $varianteId)
    {
        can('listar-reporte-sueldos-definible');
        $this->assertAcl((int) $id);
        $this->varianteSupport->eliminar((int) $id, (int) Auth::id(), (int) $varianteId);

        return back()->with('mensaje', 'Variante eliminada.');
    }

    public function manual()
    {
        can('listar-reporte-sueldos-definible');

        return view('sueldos.reporte_definible.manual');
    }

    private function assertAcl(int $id): void
    {
        $uid = (int) (Auth::id() ?? 0);
        if ($uid > 0 && ! $this->aclSupport->puedeAcceder($id, $uid)) {
            abort(403, 'Sin acceso a este listado.');
        }
    }

    private function puedeConsultarAsociado(): bool
    {
        return can('crear-reporte-sueldos-definible', false)
            || can('editar-reporte-sueldos-definible', false)
            || can('actualizar-reporte-sueldos-definible', false)
            || can('listar-reporte-sueldos-definible', false);
    }

    private function assertTipoAsociado(string $tipo): void
    {
        if (! in_array($tipo, [
            ReporteSueldosDefinibleSupport::TIPO_OSOCIAL,
            ReporteSueldosDefinibleSupport::TIPO_SINDICATO,
        ], true)) {
            abort(422, 'El tipo no admite asociado.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function normalizarYValidarAsociado(array &$data): void
    {
        $tipo = (string) ($data['tipo'] ?? ReporteSueldosDefinibleSupport::TIPO_GENERICO);
        if ($tipo === ReporteSueldosDefinibleSupport::TIPO_GENERICO) {
            $data['asociado_codigo'] = null;

            return;
        }

        $codigo = (int) ($data['asociado_codigo'] ?? 0);
        if ($codigo <= 0) {
            $data['asociado_codigo'] = null;

            return;
        }

        $asociado = $tipo === ReporteSueldosDefinibleSupport::TIPO_OSOCIAL
            ? $this->obrasocialRepository->findPorCodigo($codigo)
            : $this->sindicatoRepository->findPorCodigo($codigo);
        if (! $asociado) {
            $etiqueta = $tipo === ReporteSueldosDefinibleSupport::TIPO_OSOCIAL
                ? 'obra social'
                : 'sindicato';
            throw ValidationException::withMessages([
                'asociado_codigo' => 'El código no corresponde a una '.$etiqueta.' existente.',
            ]);
        }

        $data['asociado_codigo'] = $codigo;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosEjecucion(Request $request): array
    {
        $lugares = $request->input('lugartrabajo_ids', []);
        if (! is_array($lugares)) {
            $lugares = [];
        }
        $centros = $request->input('centrocosto_ids', []);
        $agrupamientos = $request->input('agrupamiento_ids', []);
        $agrupaciones = $request->input('agrupaciones', []);
        $centros = is_array($centros) ? $centros : [];
        $agrupamientos = is_array($agrupamientos) ? $agrupamientos : [];
        $agrupaciones = is_array($agrupaciones) ? $agrupaciones : [];
        $agrupacion = (string) $request->input(
            'agrupacion',
            ReporteSueldosDefinibleSupport::AGRUPACION_EMPLEADO
        );

        return [
            'origen' => $request->input('origen', ReporteSueldosDefinibleSupport::ORIGEN_LIQUIDACION),
            'liquidacion_id' => $request->filled('liquidacion_id') ? (int) $request->input('liquidacion_id') : null,
            'liquidacion_id_comparar' => $request->filled('liquidacion_id_comparar')
                ? (int) $request->input('liquidacion_id_comparar')
                : null,
            'empresa_id' => $request->filled('empresa_id') ? (int) $request->input('empresa_id') : null,
            'filtro_estado' => $request->input('filtro_estado', 'activo'),
            'agrupacion' => $agrupacion,
            'agrupaciones' => array_values(array_filter(
                array_merge([$agrupacion], $agrupaciones),
                fn ($valor) => is_string($valor) && $valor !== ''
            )),
            'resumido' => $request->boolean('resumido'),
            'lugartrabajo_ids' => array_map('intval', $lugares),
            'centrocosto_ids' => array_map('intval', $centros),
            'agrupamiento_ids' => array_map('intval', $agrupamientos),
            'orden_columna' => max(0, (int) $request->input('orden_columna', 0)),
            'orden_direccion' => $request->input('orden_direccion') === 'asc' ? 'asc' : 'desc',
            'top_n' => max(0, min(10000, (int) $request->input('top_n', 0))),
            'incluir_confidencial' => $request->boolean('incluir_confidencial'),
        ];
    }

    /**
     * @return array{0:?ReporteSueldosDefinibleEjecucion,1:\Illuminate\Support\Collection}
     */
    private function resolverParidad(Request $request, $reporte): array
    {
        $ejecucionId = (int) $request->input('ejecucion_id', $reporte->publicado_ejecucion_id);
        $ejecucion = $ejecucionId > 0
            ? $reporte->ejecuciones()->whereKey($ejecucionId)->first()
            : $reporte->ejecuciones()->whereHas('paridades')->first();
        if ($ejecucion && $ejecucion->paridades()->exists()) {
            return [$ejecucion, $ejecucion->paridades()->orderBy('columna_nro')->get()];
        }
        if (! $request->filled('ejecucion_id')) {
            $ejecucion = $reporte->ejecuciones()->whereHas('paridades')->first();
            if ($ejecucion) {
                return [$ejecucion, $ejecucion->paridades()->orderBy('columna_nro')->get()];
            }
        }

        $liquidacionId = (int) $request->input('liquidacion_id');
        if ($liquidacionId <= 0) {
            return [$ejecucion, collect()];
        }
        $liquidacion = Liquidacion_Sueldos::query()->findOrFail($liquidacionId);
        $empresaAnita = (int) $request->input(
            'empresa_anita',
            $request->input('empresa', $liquidacion->empresa_id ?: 1)
        );
        $liquidacionAnita = (int) $request->input('liquidacion_anita', $liquidacion->numero);
        $resultado = $this->procesador->ejecutar($reporte, [
            'origen' => ReporteSueldosDefinibleSupport::ORIGEN_LIQUIDACION,
            'liquidacion_id' => $liquidacionId,
            'agrupacion' => ReporteSueldosDefinibleSupport::AGRUPACION_EMPLEADO,
            'resumido' => false,
            'incluir_confidencial' => $request->boolean('incluir_confidencial'),
        ]);
        $totalesAnita = $this->paridadAnitaSupport->totales(
            $reporte,
            $empresaAnita,
            $liquidacionAnita,
            $request->boolean('incluir_confidencial') ? 'ambos' : 'normal'
        );
        $tolerancia = abs((float) $request->input('tolerancia', 0.01));
        $filas = collect($resultado['columnas'] ?? [])
            ->filter(fn ($columna) => ! empty($columna['numerica']))
            ->map(function ($columna) use ($resultado, $totalesAnita, $tolerancia, $empresaAnita, $liquidacionAnita) {
                $nro = (int) $columna['nro'];
                $erp = (float) ($resultado['totales'][$nro] ?? 0);
                $anita = (float) ($totalesAnita[$nro] ?? 0);
                $diferencia = round($erp - $anita, 4);

                return (object) [
                    'columna_nro' => $nro,
                    'columna_descripcion' => (string) $columna['descripcion'],
                    'total_erp' => $erp,
                    'total_anita' => $anita,
                    'diferencia' => $diferencia,
                    'tolerancia' => $tolerancia,
                    'coincide' => abs($diferencia) <= $tolerancia,
                    'empresa_anita' => $empresaAnita,
                    'liquidacion_anita' => $liquidacionAnita,
                ];
            })->values();

        return [$ejecucion, $filas];
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    private function descargarResultado(array $resultado, string $titulo, string $subtitulo, string $formato)
    {
        $formato = strtoupper($formato);
        if (! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()->back()->with('mensaje_error', 'Formato no soportado.');
        }

        if ($formato === 'PDF') {
            $pdf = Pdf::loadView('sueldos.reporte_definible.listado', [
                'resultado' => $resultado,
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
                'logos' => \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                    collect([(object) ['nombreempresa' => config('app.empresa')]])
                ),
            ])->setPaper('legal', 'landscape');
            $path = storage_path('pdf/listados/listado_reporte_sueldos_definible.pdf');
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            $pdf->save($path);

            return response()->download($path)->deleteFileAfterSend(true);
        }

        $export = (new ReporteSueldosDefinibleExport)->parametros($resultado, $titulo, $subtitulo);
        $writer = $formato === 'CSV' ? Excel::CSV : Excel::XLSX;
        $ext = $formato === 'CSV' ? 'csv' : 'xlsx';

        return $export->download('reporte_sueldos_definible.'.$ext, $writer);
    }
}
