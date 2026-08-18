<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\ReporteDefinibleExport;
use App\Exports\Contable\ReporteDefinibleParidadExport;
use App\Http\Controllers\Controller;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\ReporteContableRepository;
use App\Services\Contable\ReporteDefinibleAnitaTraductorService;
use App\Services\Contable\ReporteDefinibleDistribucionService;
use App\Services\Contable\ReporteDefinibleDrillService;
use App\Services\Contable\ReporteDefinibleParidadService;
use App\Services\Contable\ReporteDefinibleReporteService;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleAclSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleAlertaSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleCoberturaSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleConjuntoSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleEliminacionSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleLayoutResolver;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleLayoutSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleNotaSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleParticipacionSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefiniblePublicacionSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefiniblePartidaPlanReader;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleSuscripcionSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleValidacionSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleVarianteSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleVersionSupport;
use App\Support\Contable\ReporteDefinibleListadoFiltros;
use App\Support\Contable\SumasSaldos\SumasSaldosRuntimeSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class ReporteDefinibleController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'reporte_definible';

    public function __construct(
        private readonly ReporteContableRepository $repository,
        private readonly ReporteDefinibleReporteService $reporteService,
        private readonly ReporteDefinibleAnitaTraductorService $traductorService,
        private readonly ReporteDefinibleCoberturaSupport $coberturaSupport,
        private readonly ReporteDefiniblePartidaPlanReader $partidaPlanReader,
        private readonly ReporteDefinibleConjuntoSupport $conjuntoSupport,
        private readonly ReporteDefinibleLayoutResolver $layoutResolver,
        private readonly ReporteDefinibleLayoutSupport $layoutSupport,
        private readonly ReporteDefinibleEliminacionSupport $eliminacionSupport,
        private readonly ReporteDefinibleParticipacionSupport $participacionSupport,
        private readonly ReporteDefinibleVersionSupport $versionSupport,
        private readonly ReporteDefinibleAclSupport $aclSupport,
        private readonly ReporteDefinibleVarianteSupport $varianteSupport,
        private readonly ReporteDefinibleAlertaSupport $alertaSupport,
        private readonly ReporteDefinibleNotaSupport $notaSupport,
        private readonly ReporteDefinibleValidacionSupport $validacionSupport,
        private readonly ReporteDefinibleParidadService $paridadService,
        private readonly ReporteDefinibleDrillService $drillService,
        private readonly ReporteDefiniblePublicacionSupport $publicacionSupport,
        private readonly ReporteDefinibleSuscripcionSupport $suscripcionSupport,
        private readonly ReporteDefinibleDistribucionService $distribucionService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly MonedaRepositoryInterface $monedaRepository,
        private readonly UsuarioRepositoryInterface $usuarioRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-reporte-definible');

        $filtros = ReporteDefinibleListadoFiltros::resolverDesdeRequest($request);
        $coleccion = $this->repository->leeReportes($filtros, true);
        $filtrosQuery = ReporteDefinibleListadoFiltros::paraQueryString($filtros);
        $coleccion->appends($filtrosQuery);

        return view('contable.reporte_definible.index', [
            'coleccion' => $coleccion,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'tiposReporte' => ReporteDefinibleSupport::tiposReporte(),
            'puede_crear' => can('crear-reporte-definible', false),
            'puede_editar' => can('editar-reporte-definible', false),
            'puede_eliminar' => can('eliminar-reporte-definible', false),
            'puede_ejecutar' => can('ejecutar-reporte-definible', false) || can('listar-reporte-definible', false),
            'puede_importar' => can('importar-reporte-definible', false),
            'plantillas' => \App\Models\Contable\ReporteContable::query()
                ->where('origen', 'plantilla')
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre', 'tipo']),
        ]);
    }

    public function crear()
    {
        can('crear-reporte-definible');

        return view('contable.reporte_definible.crear', [
            'tiposReporte' => ReporteDefinibleSupport::tiposReporte(),
        ]);
    }

    public function guardar(Request $request)
    {
        can('crear-reporte-definible');

        $data = $request->validate([
            'codigo' => 'nullable|integer|min:1|unique:reporte_contable,codigo',
            'nombre' => 'required|string|max:80',
            'titulo1' => 'nullable|string|max:80',
            'titulo2' => 'nullable|string|max:80',
            'tipo' => 'required|in:balance,resultado,otro',
            'observaciones' => 'nullable|string|max:2000',
            'activo' => 'nullable|boolean',
        ]);
        if (($data['codigo'] ?? null) === null || (int) ($data['codigo'] ?? 0) <= 0) {
            unset($data['codigo']);
        }
        $data['activo'] = $request->boolean('activo', true);
        $data['origen'] = 'manual';

        $reporte = $this->repository->create($data);

        return redirect()
            ->route('editar_reporte_definible', ['id' => $reporte->id])
            ->with('mensaje', 'Informe creado. Ahora defina la estructura de rubros y cuentas.');
    }

    public function editar($id)
    {
        can('editar-reporte-definible');
        $this->assertAclInforme((int) $id);

        $data = $this->repository->findConEstructura((int) $id);
        if (! $data) {
            return redirect()->route('reporte_definible')->with('mensaje_error', 'Informe no encontrado.');
        }

        $estructura = $this->repository->estructuraUi((int) $id);
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaIds = $empresaQuery->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->conjuntoSupport->expandirEnReporte($data);
        $cobertura = $this->coberturaSupport->analizar($data, $empresaIds);
        $usuarioId = (int) auth()->id();

        return view('contable.reporte_definible.editar', [
            'data' => $data,
            'estructura' => $estructura,
            'cobertura' => $cobertura,
            'tiposReporte' => ReporteDefinibleSupport::tiposReporte(),
            'tiposRubro' => ReporteDefinibleSupport::tiposRubro(),
            'tiposRubroAyuda' => ReporteDefinibleSupport::tiposRubroAyuda(),
            'empresa_query' => $empresaQuery,
            'conjuntos' => $this->conjuntoSupport->listadoParaSelector(),
            'layouts_disponibles' => $this->layoutResolver->listarParaEjecucion((int) $id),
            'layouts_payload' => $this->layoutSupport->payloadUi((int) $id),
            'eli_reglas' => $this->eliminacionSupport->payloadUi((int) $id),
            'participaciones' => $this->participacionSupport->payloadUi((int) $id),
            'aclUsuarios' => $this->aclSupport->usuarioIds((int) $id),
            'usuariosAcl' => $this->usuarioRepository->listadoOperativoParaSelector(
                null,
                null,
                ['id', 'nombre', 'email', 'usuario'],
                soloConEmail: false,
                with: []
            ),
            'alertas_payload' => $this->alertaSupport->payloadUi((int) $id),
            'tipos_alerta' => \App\Models\Contable\ReporteContableAlerta::tipos(),
            'notas_payload' => $this->notaSupport->payloadUi((int) $id),
            'notas_lineas' => $this->notaSupport->lineasDisponibles((int) $id),
            'suscripciones_payload' => $this->suscripcionSupport->payloadUi((int) $id),
            'periodicidades_suscripcion' => \App\Models\Contable\ReporteContableSuscripcion::periodicidades(),
            'periodos_relativos_suscripcion' => \App\Models\Contable\ReporteContableSuscripcion::periodosRelativos(),
            'formatos_suscripcion' => \App\Models\Contable\ReporteContableSuscripcion::formatos(),
            'dias_semana_suscripcion' => \App\Models\Contable\ReporteContableSuscripcion::diasSemana(),
            'variantes' => $this->varianteSupport->payloadUi((int) $id, $usuarioId),
            'versiones' => $data->versiones()->limit(20)->get(),
            'impacto_publicaciones' => $this->publicacionSupport->impactoDefinicion((int) $id),
            'tipos_columna_layout' => ReporteDefinibleLayoutResolver::tiposColumna(),
            'puede_actualizar' => can('actualizar-reporte-definible', false),
            'puede_ejecutar' => can('ejecutar-reporte-definible', false) || can('listar-reporte-definible', false),
        ]);
    }

    public function actualizar(Request $request, $id)
    {
        can('actualizar-reporte-definible');

        $data = $request->validate([
            'codigo' => 'required|integer|min:1|unique:reporte_contable,codigo,'.$id,
            'nombre' => 'required|string|max:80',
            'titulo1' => 'nullable|string|max:80',
            'titulo2' => 'nullable|string|max:80',
            'tipo' => 'required|in:balance,resultado,otro',
            'observaciones' => 'nullable|string|max:2000',
            'activo' => 'nullable|boolean',
            'valido_desde' => 'nullable|date',
            'valido_hasta' => 'nullable|date',
            'estado_publicacion' => 'nullable|in:borrador,publicado',
        ]);
        $data['activo'] = $request->boolean('activo', true);
        $data['valido_desde'] = $request->input('valido_desde') ?: null;
        $data['valido_hasta'] = $request->input('valido_hasta') ?: null;
        if (isset($data['estado_publicacion'])) {
            // only allow explicit set from form
        } else {
            unset($data['estado_publicacion']);
        }

        $this->repository->update($data, (int) $id);

        return redirect()
            ->route('editar_reporte_definible', ['id' => $id])
            ->with('mensaje', 'Cabecera actualizada.');
    }

    public function eliminar($id)
    {
        can('eliminar-reporte-definible');
        $this->assertAclInforme((int) $id);
        $this->repository->delete((int) $id);

        return redirect()->route('reporte_definible')->with('mensaje', 'Informe eliminado.');
    }

    public function copiar($id)
    {
        can('crear-reporte-definible');
        $this->assertAclInforme((int) $id);
        $nuevo = $this->repository->copiar((int) $id);

        $origen = \App\Models\Contable\ReporteContable::query()->find((int) $id);
        if ($origen) {
            $this->notaSupport->copiar($origen, $nuevo);
        }

        return redirect()
            ->route('editar_reporte_definible', ['id' => $nuevo->id])
            ->with('mensaje', 'Copia creada. Puede ajustar la estructura.');
    }

    public function guardarRubro(Request $request, $id)
    {
        can('actualizar-reporte-definible');
        $data = $request->validate([
            'nombre' => 'required|string|max:80',
            'codigo_linea' => 'nullable|string|max:20',
            'tipo' => 'required|in:cuentas,total,formula,texto',
            'parent_id' => 'nullable|integer',
            'formula' => 'nullable|string|max:255',
            'estilo_negrita' => 'nullable|boolean',
            'estilo_subrayado' => 'nullable|boolean',
        ]);
        $data['estilo_negrita'] = $request->boolean('estilo_negrita');
        $data['estilo_subrayado'] = $request->boolean('estilo_subrayado');

        $this->repository->crearRubro((int) $id, $data);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'estructura' => $this->repository->estructuraUi((int) $id),
            ]);
        }

        return redirect()
            ->route('editar_reporte_definible', ['id' => $id])
            ->with('mensaje', 'Rubro agregado.');
    }

    public function actualizarRubro(Request $request, $id, $rubroId)
    {
        can('actualizar-reporte-definible');
        $data = $request->validate([
            'nombre' => 'required|string|max:80',
            'codigo_linea' => 'nullable|string|max:20',
            'tipo' => 'required|in:cuentas,total,formula,texto',
            'formula' => 'nullable|string|max:255',
            'estilo_negrita' => 'nullable|boolean',
            'estilo_subrayado' => 'nullable|boolean',
            'conjunto_id' => 'nullable|integer',
            'lado_presentacion' => 'nullable|in:D,H',
            'ocultar_si_cero' => 'nullable|boolean',
        ]);
        $data['estilo_negrita'] = $request->boolean('estilo_negrita');
        $data['estilo_subrayado'] = $request->boolean('estilo_subrayado');
        $data['conjunto_id'] = (int) ($data['conjunto_id'] ?? 0);
        $data['ocultar_si_cero'] = $request->boolean('ocultar_si_cero');
        $data['lado_presentacion'] = $request->input('lado_presentacion') ?: null;

        $this->repository->actualizarRubro((int) $rubroId, $data);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'estructura' => $this->repository->estructuraUi((int) $id),
            ]);
        }

        return redirect()
            ->route('editar_reporte_definible', ['id' => $id])
            ->with('mensaje', 'Rubro actualizado.');
    }

    public function eliminarRubro(Request $request, $id, $rubroId)
    {
        can('actualizar-reporte-definible');
        $this->repository->eliminarRubro((int) $rubroId);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'estructura' => $this->repository->estructuraUi((int) $id),
            ]);
        }

        return redirect()
            ->route('editar_reporte_definible', ['id' => $id])
            ->with('mensaje', 'Rubro eliminado.');
    }

    public function guardarCuenta(Request $request, $id, $rubroId)
    {
        can('actualizar-reporte-definible');
        $data = $request->validate([
            'codigo_cuenta' => 'required|integer|min:1',
            'codigo_hasta' => 'nullable|integer|min:1',
            'cuentacontable_id' => 'nullable|integer',
            'empresa_id' => 'nullable|integer',
            'origen' => 'nullable|in:R,P',
            'signo' => 'nullable|integer',
            'carga_ccosto' => 'nullable|string|max:1',
        ]);
        if (! empty($data['codigo_hasta']) && (int) $data['codigo_hasta'] < (int) $data['codigo_cuenta']) {
            return response()->json(['ok' => false, 'mensaje' => 'código hasta debe ser ≥ desde'], 422);
        }

        $this->repository->agregarCuenta((int) $rubroId, $data);

        if ($request->expectsJson() || $request->ajax()) {
            $reporte = $this->repository->findConEstructura((int) $id);

            return response()->json([
                'ok' => true,
                'estructura' => $this->repository->estructuraUi((int) $id),
                'cuentas' => $this->cuentasRubroPayload($reporte, (int) $rubroId),
            ]);
        }

        return redirect()
            ->route('editar_reporte_definible', ['id' => $id, 'rubro' => $rubroId])
            ->with('mensaje', 'Cuenta asignada al rubro.');
    }

    public function eliminarCuenta(Request $request, $id, $cuentaId)
    {
        can('actualizar-reporte-definible');
        $cta = \App\Models\Contable\ReporteContableCuenta::query()->find((int) $cuentaId);
        $rubroId = $cta ? (int) $cta->reporte_contable_rubro_id : 0;
        $this->repository->eliminarCuenta((int) $cuentaId);

        if ($request->expectsJson() || $request->ajax()) {
            $reporte = $this->repository->findConEstructura((int) $id);

            return response()->json([
                'ok' => true,
                'estructura' => $this->repository->estructuraUi((int) $id),
                'cuentas' => $rubroId > 0 ? $this->cuentasRubroPayload($reporte, $rubroId) : [],
            ]);
        }

        return redirect()
            ->route('editar_reporte_definible', ['id' => $id])
            ->with('mensaje', 'Cuenta quitada del rubro.');
    }

    public function estructuraJson($id)
    {
        if (! can('editar-reporte-definible', false) && ! can('listar-reporte-definible', false)) {
            can('listar-reporte-definible');
        }

        return response()->json([
            'estructura' => $this->repository->estructuraUi((int) $id),
        ]);
    }

    public function cuentasRubroJson($id, $rubroId)
    {
        if (! can('editar-reporte-definible', false) && ! can('listar-reporte-definible', false)) {
            can('listar-reporte-definible');
        }
        $reporte = $this->repository->findConEstructura((int) $id);

        return response()->json([
            'cuentas' => $this->cuentasRubroPayload($reporte, (int) $rubroId),
        ]);
    }

    public function ejecutar(Request $request, $id = null)
    {
        if (! can('ejecutar-reporte-definible', false) && ! can('listar-reporte-definible', false)) {
            can('listar-reporte-definible');
        }

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $monedaQuery = $this->monedaRepository->all();
        $filtros = ReporteDefinibleListadoFiltros::resolverEjecucionDesdeRequest($request);
        if ($id) {
            $filtros['reporte_contable_id'] = (int) $id;
        }

        $permitidos = $empresaQuery->pluck('id')->map(fn ($v) => (int) $v)->all();
        if (empty($filtros['empresa_ids'])) {
            $prefs = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE);
            $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas(
                $prefs ?: $permitidos,
                $permitidos
            );
            if ($filtros['empresa_ids'] === [] && $permitidos !== []) {
                $filtros['empresa_ids'] = count($permitidos) === 1 ? [$permitidos[0]] : $permitidos;
            }
        } else {
            $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas(
                $filtros['empresa_ids'],
                $permitidos
            );
        }

        $reportes = $this->repository->leeReportes(['activo' => '1'], false);
        $reporte = null;
        $resultado = null;
        $consultado = false;

        if ($filtros['reporte_contable_id'] > 0) {
            $this->assertAclInforme((int) $filtros['reporte_contable_id']);
            $reporte = $this->repository->findConEstructura((int) $filtros['reporte_contable_id']);
        }

        if ($request->boolean('consultar') && $reporte) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_ids' => $filtros['empresa_ids'],
                'consolidar_empresas' => (bool) ($filtros['consolidar_empresas'] ?? true),
            ]);
            $resultado = $this->reporteService->ejecutar((int) $reporte->id, $filtros);
            $hoy = date('Y-m-d');
            if ($reporte->valido_desde && $hoy < $reporte->valido_desde->format('Y-m-d')) {
                $resultado['advertencias'][] = 'Aviso: el informe aún no está vigente (válido desde '.$reporte->valido_desde->format('d/m/Y').').';
            }
            if ($reporte->valido_hasta && $hoy > $reporte->valido_hasta->format('Y-m-d')) {
                $resultado['advertencias'][] = 'Aviso: el informe está fuera de vigencia (válido hasta '.$reporte->valido_hasta->format('d/m/Y').').';
            }
            $consultado = true;
        }

        $filtrosQuery = ReporteDefinibleListadoFiltros::ejecucionParaQueryString($filtros);
        $publicadoAviso = $consultado && $reporte && $resultado
            ? $this->publicacionSupport->compararConPublicado((int) $reporte->id, $filtros, $resultado)
            : null;

        return view('contable.reporte_definible.ejecutar', [
            'publicado_aviso' => $publicadoAviso,
            'reportes' => $reportes,
            'reporte' => $reporte,
            'empresa_query' => $empresaQuery,
            'moneda_query' => $monedaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'periodo_texto' => $this->reporteService->formatearPeriodoTexto($filtros),
            'mes_actual' => (int) date('n'),
            'anio_actual' => (int) date('Y'),
            'puede_editar' => can('editar-reporte-definible', false),
            'escenarios_presupuesto' => $this->partidaPlanReader->listarEscenariosParaSelector(),
            'layouts_disponibles' => $this->layoutResolver->listarParaEjecucion(
                $reporte ? (int) $reporte->id : null
            ),
            'variantes' => $reporte
                ? $this->varianteSupport->payloadUi((int) $reporte->id, (int) auth()->id())
                : [],
        ]);
    }

    /**
     * Paridad del informe contra Anita (ctamov + subdiario) con drill a cuenta.
     */
    public function paridadAnita(Request $request, $id)
    {
        if (! can('ejecutar-reporte-definible', false) && ! can('listar-reporte-definible', false)) {
            can('listar-reporte-definible');
        }
        $this->assertAclInforme((int) $id);

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ReporteDefinibleListadoFiltros::resolverEjecucionDesdeRequest($request);
        $filtros['reporte_contable_id'] = (int) $id;

        $permitidos = $empresaQuery->pluck('id')->map(fn ($v) => (int) $v)->all();
        // Sin empresa elegida se compara una sola: cada empresa es una lectura al bridge Anita.
        $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas(
            $filtros['empresa_ids'] ?: array_slice($permitidos, 0, 1),
            $permitidos
        );

        $tolerancia = (float) $request->input('tolerancia', ReporteDefinibleParidadService::TOLERANCIA_DEFAULT);
        $resultado = $this->paridadService->comparar((int) $id, $filtros, $tolerancia);

        $filtrosQuery = ReporteDefinibleListadoFiltros::ejecucionParaQueryString($filtros);
        $filtrosQuery['tolerancia'] = $tolerancia;
        if ($request->boolean('solo_diferencias')) {
            $filtrosQuery['solo_diferencias'] = 1;
        }

        return view('contable.reporte_definible.paridad', [
            'reporte' => $resultado['reporte'],
            'resultado' => $resultado,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'solo_diferencias' => $request->boolean('solo_diferencias'),
            'empresa_query' => $empresaQuery,
        ]);
    }

    /**
     * Congela el resultado tal como se está viendo, para poder reimprimirlo idéntico.
     */
    public function publicarResultado(Request $request, $id)
    {
        can('ejecutar-reporte-definible');
        $this->assertAclInforme((int) $id);

        $reporte = $this->repository->findConEstructura((int) $id);
        if (! $reporte) {
            return redirect()->route('reporte_definible')->with('mensaje_error', 'Informe no encontrado.');
        }

        $filtros = ReporteDefinibleListadoFiltros::resolverEjecucionDesdeRequest($request);
        $filtros['reporte_contable_id'] = (int) $id;
        $permitidos = $this->empresaRepository->allFiltrado()->pluck('id')->map(fn ($v) => (int) $v)->all();
        $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas(
            $filtros['empresa_ids'] ?: $permitidos,
            $permitidos
        );

        $resultado = $this->reporteService->ejecutar((int) $id, $filtros);
        if (($resultado['filas'] ?? []) === []) {
            return back()->with('mensaje_error', 'No hay resultado para publicar: ejecute el informe primero.');
        }

        $publicacion = $this->publicacionSupport->publicar(
            $reporte,
            $filtros,
            $resultado,
            auth()->id(),
            $request->input('nombre'),
            $request->input('observacion'),
            $this->reporteService->formatearPeriodoTexto($filtros),
        );

        return redirect()
            ->route('publicaciones_reporte_definible', ['id' => $id])
            ->with('mensaje', 'Resultado publicado: «'.$publicacion->nombre.'». Se puede reimprimir idéntico.');
    }

    public function publicaciones(Request $request, $id)
    {
        if (! can('ejecutar-reporte-definible', false) && ! can('listar-reporte-definible', false)) {
            can('listar-reporte-definible');
        }
        $this->assertAclInforme((int) $id);

        $reporte = $this->repository->findConEstructura((int) $id);
        if (! $reporte) {
            return redirect()->route('reporte_definible')->with('mensaje_error', 'Informe no encontrado.');
        }

        return view('contable.reporte_definible.publicaciones', [
            'reporte' => $reporte,
            'publicaciones' => $this->publicacionSupport->listar((int) $id),
        ]);
    }

    /**
     * Reimpresión del documento congelado: no vuelve a calcular nada.
     */
    public function verPublicacion(Request $request, $id, $publicacionId)
    {
        if (! can('ejecutar-reporte-definible', false) && ! can('listar-reporte-definible', false)) {
            can('listar-reporte-definible');
        }
        $this->assertAclInforme((int) $id);

        $publicacion = \App\Models\Contable\ReporteContablePublicacion::query()
            ->with('usuario:id,nombre')
            ->findOrFail((int) $publicacionId);
        if ((int) $publicacion->reporte_contable_id !== (int) $id) {
            abort(404);
        }

        $reporte = $this->repository->findConEstructura((int) $id);
        $resultado = $publicacion->resultadoArray();
        $formato = strtoupper((string) $request->input('formato', ''));

        if ($formato === 'PDF') {
            $pdf = \PDF::loadView('contable.reporte_definible.listado', [
                'reporte' => $reporte,
                'resultado' => $resultado,
                'periodo_texto' => (string) $publicacion->periodo_texto,
                'publicacion' => $publicacion,
            ])->setPaper('legal', 'landscape');

            return $pdf->stream('publicacion_'.$publicacion->id.'.pdf');
        }

        return view('contable.reporte_definible.publicacion', [
            'reporte' => $reporte,
            'publicacion' => $publicacion,
            'resultado' => $resultado,
        ]);
    }

    /**
     * Drill de una celda: cuentas del rubro y, dentro de cada cuenta, los asientos
     * con el documento que los originó.
     */
    public function drillJson(Request $request, $id)
    {
        if (! can('ejecutar-reporte-definible', false) && ! can('listar-reporte-definible', false)) {
            can('listar-reporte-definible');
        }
        $this->assertAclInforme((int) $id);

        $filtros = ReporteDefinibleListadoFiltros::resolverEjecucionDesdeRequest($request);
        $permitidos = $this->empresaRepository->allFiltrado()->pluck('id')->map(fn ($v) => (int) $v)->all();
        $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas(
            $filtros['empresa_ids'] ?: $permitidos,
            $permitidos
        );

        $codigo = (int) $request->input('codigo', 0);
        if ($codigo > 0) {
            return response()->json(array_merge(
                ['ok' => true, 'modo' => 'asientos'],
                $this->drillService->asientosDeCuenta($codigo, $filtros)
            ));
        }

        $rubroId = (int) $request->input('rubro_id', 0);
        if ($rubroId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Indique rubro o cuenta.'], 422);
        }

        return response()->json(array_merge(
            ['ok' => true, 'modo' => 'cuentas'],
            $this->drillService->cuentasDeRubro((int) $id, $rubroId, $filtros)
        ));
    }

    public function exportarParidadAnita(Request $request, $id, ?string $formato = null)
    {
        if (! can('ejecutar-reporte-definible', false) && ! can('listar-reporte-definible', false)) {
            can('listar-reporte-definible');
        }
        $this->assertAclInforme((int) $id);
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ReporteDefinibleListadoFiltros::resolverEjecucionDesdeRequest($request);
        $filtros['reporte_contable_id'] = (int) $id;

        $permitidos = $this->empresaRepository->allFiltrado()->pluck('id')->map(fn ($v) => (int) $v)->all();
        $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas(
            $filtros['empresa_ids'] ?: array_slice($permitidos, 0, 1),
            $permitidos
        );

        $tolerancia = (float) $request->input('tolerancia', ReporteDefinibleParidadService::TOLERANCIA_DEFAULT);
        $soloDiferencias = $request->boolean('solo_diferencias');
        $formato = strtoupper(trim((string) $formato));

        if (! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            $query = ReporteDefinibleListadoFiltros::ejecucionParaQueryString($filtros);
            $query['tolerancia'] = $tolerancia;

            return redirect()->route('paridad_anita_reporte_definible', array_merge(['id' => (int) $id], $query));
        }

        $resultado = $this->paridadService->comparar((int) $id, $filtros, $tolerancia);
        if ($soloDiferencias) {
            $resultado['filas'] = array_values(array_filter(
                $resultado['filas'],
                fn ($f) => empty($f['cuadra']) || empty($f['cuadra_motor'])
            ));
        }

        if ($formato === 'PDF') {
            $pdf = \PDF::loadView('contable.reporte_definible.paridad_listado', [
                'resultado' => $resultado,
                'filas' => $resultado['filas'],
                'resumen' => $resultado['resumen'],
                'parametros' => $resultado['parametros'],
                'reporte' => $resultado['reporte'],
                'solo_diferencias' => $soloDiferencias,
            ])->setPaper('legal', 'landscape');

            $dir = storage_path('pdf/listados');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $path = $dir.'/listado_paridad_reporte_definible.pdf';
            $pdf->save($path);

            return response()->file($path);
        }

        $export = (new ReporteDefinibleParidadExport())
            ->parametros($resultado, $soloDiferencias, $formato === 'CSV');

        return $formato === 'CSV'
            ? $export->download('paridad_reporte_definible.csv', Excel::CSV)
            : $export->download('paridad_reporte_definible.xlsx');
    }

    public function preview(Request $request, $id)
    {
        if (! can('editar-reporte-definible', false) && ! can('ejecutar-reporte-definible', false)
            && ! can('listar-reporte-definible', false)) {
            can('listar-reporte-definible');
        }
        $this->assertAclInforme((int) $id);

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ReporteDefinibleListadoFiltros::resolverEjecucionDesdeRequest($request);
        $filtros['reporte_contable_id'] = (int) $id;
        $filtros['consultar'] = true;
        $filtros['ocultar_ceros'] = $request->boolean('ocultar_ceros', true);

        $permitidos = $empresaQuery->pluck('id')->map(fn ($v) => (int) $v)->all();
        $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas(
            $filtros['empresa_ids'] ?: $permitidos,
            $permitidos
        );

        if (($filtros['layout_id'] ?? 0) <= 0) {
            $rep = $this->repository->find((int) $id);
            if ($rep && $rep->layout_default_id) {
                $filtros['layout_id'] = (int) $rep->layout_default_id;
            } else {
                $full = \App\Models\Contable\ReporteContableLayout::query()
                    ->sistema()
                    ->where('codigo', 'FULL_GERENCIAL')
                    ->value('id');
                $filtros['layout_id'] = (int) ($full ?: 0);
                if ($filtros['layout_id'] <= 0) {
                    $filtros['columnas_layout'] = ReporteDefinibleSupport::LAYOUT_COMPARATIVO;
                }
            }
        }

        $resultado = $this->reporteService->ejecutar((int) $id, $filtros);

        return response()->json([
            'ok' => true,
            'columnas' => $resultado['columnas'] ?? [],
            'filas' => $resultado['filas'] ?? [],
            'advertencias' => $resultado['advertencias'] ?? [],
            'periodo_texto' => $this->reporteService->formatearPeriodoTexto($filtros),
        ]);
    }

    public function publicarVersion(Request $request, $id)
    {
        can('actualizar-reporte-definible');
        $reporte = $this->repository->findConEstructura((int) $id);
        if (! $reporte) {
            return redirect()->route('reporte_definible')->with('mensaje_error', 'Informe no encontrado.');
        }
        $nombre = trim((string) $request->input('nombre', ''));
        $ver = $this->versionSupport->publicar($reporte, auth()->id(), $nombre !== '' ? $nombre : null);

        return redirect()
            ->route('editar_reporte_definible', $id)
            ->with('mensaje', 'Versión '.$ver->version.' publicada.');
    }

    public function restaurarVersion(Request $request, $id, $versionId)
    {
        can('actualizar-reporte-definible');
        $reporte = $this->repository->findConEstructura((int) $id);
        $version = \App\Models\Contable\ReporteContableVersion::query()->findOrFail((int) $versionId);
        if (! $reporte) {
            return redirect()->route('reporte_definible')->with('mensaje_error', 'Informe no encontrado.');
        }
        $this->versionSupport->restaurar($reporte, $version);

        return redirect()
            ->route('editar_reporte_definible', $id)
            ->with('mensaje', 'Estructura restaurada desde versión '.$version->version.'.');
    }

    public function crearDesdePlantilla(Request $request)
    {
        can('crear-reporte-definible');
        $plantillaId = (int) $request->input('plantilla_id', 0);
        $plantilla = $this->repository->find($plantillaId);
        if (! $plantilla || $plantilla->origen !== 'plantilla') {
            return redirect()->route('reporte_definible')->with('mensaje_error', 'Plantilla inválida.');
        }
        $nuevo = $this->repository->copiar($plantillaId);
        $nuevo->origen = 'manual';
        $nuevo->activo = true;
        $nuevo->nombre = str_replace('PLANTILLA ', '', (string) $nuevo->nombre);
        $nuevo->save();

        return redirect()
            ->route('editar_reporte_definible', $nuevo->id)
            ->with('mensaje', 'Informe creado desde plantilla.');
    }

    public function exportar(Request $request, $id, string $formato)
    {
        if (! can('ejecutar-reporte-definible', false) && ! can('listar-reporte-definible', false)) {
            can('listar-reporte-definible');
        }
        $this->assertAclInforme((int) $id);
        SumasSaldosRuntimeSupport::elevarLimites();

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ReporteDefinibleListadoFiltros::resolverEjecucionDesdeRequest($request);
        $filtros['reporte_contable_id'] = (int) $id;
        $permitidos = $empresaQuery->pluck('id')->map(fn ($v) => (int) $v)->all();
        $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas(
            $filtros['empresa_ids'] ?? [],
            $permitidos
        );

        $resultado = $this->reporteService->ejecutar((int) $id, $filtros);
        $reporte = $resultado['reporte'] ?? null;
        if (! $reporte) {
            return redirect()->route('ejecutar_reporte_definible', ['id' => $id])
                ->with('mensaje_error', 'Informe no encontrado.');
        }

        $formato = strtoupper($formato);
        $nombre = 'reporte_definible_'.$reporte->codigo.'_'.date('Ymd_His');

        if ($formato === 'PDF') {
            $pdf = \PDF::loadView('contable.reporte_definible.listado', [
                'reporte' => $reporte,
                'resultado' => $resultado,
                'filtros' => $filtros,
                'periodo_texto' => $this->reporteService->formatearPeriodoTexto($filtros),
            ])->setPaper('legal', 'landscape');
            $dir = storage_path('pdf/listados');
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $path = $dir.'/listado_reporte_definible.pdf';
            $pdf->save($path);

            return response()->download($path, $nombre.'.pdf');
        }

        if (in_array($formato, ['EXCEL', 'CSV'], true)) {
            $export = (new ReporteDefinibleExport())
                ->parametros($filtros, $resultado, $reporte, $formato === 'CSV');

            return $export->download(
                $nombre.($formato === 'CSV' ? '.csv' : '.xlsx'),
                $formato === 'CSV' ? Excel::CSV : Excel::XLSX
            );
        }

        return redirect()->route('ejecutar_reporte_definible', array_merge(
            ['id' => $id],
            ReporteDefinibleListadoFiltros::ejecucionParaQueryString($filtros)
        ));
    }

    public function importarAnita(Request $request)
    {
        can('importar-reporte-definible');
        SumasSaldosRuntimeSupport::elevarLimites();

        $desde = $request->filled('informe_desde') ? (int) $request->input('informe_desde') : null;
        $hasta = $request->filled('informe_hasta') ? (int) $request->input('informe_hasta') : null;
        if ($request->filled('informe')) {
            $desde = $hasta = (int) $request->input('informe');
        }

        $result = $this->traductorService->importar($desde, $hasta, true);

        $msg = sprintf(
            'Importación Anita: %d nuevos, %d actualizados, %d rubros, %d cuentas.',
            $result['importados'],
            $result['actualizados'],
            $result['rubros'],
            $result['cuentas']
        );
        if ($result['errores'] !== []) {
            return redirect()->route('reporte_definible')
                ->with('mensaje_error', $msg.' Errores: '.implode(' | ', $result['errores']));
        }

        return redirect()->route('reporte_definible')
            ->with('mensaje', $msg)
            ->with('advertencias_import', $result['advertencias']);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-reporte-definible');
        $filtros = ReporteDefinibleListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $filas = $this->repository->leeReportes($filtros, false);

        $formato = strtoupper((string) $formato);
        if (! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()->route('reporte_definible', ReporteDefinibleListadoFiltros::paraQueryString($filtros));
        }

        if ($formato === 'PDF') {
            $pdf = \PDF::loadView('contable.reporte_definible.listado_index', [
                'filas' => $filas,
                'filtros' => $filtros,
            ])->setPaper('legal', 'landscape');
            $dir = storage_path('pdf/listados');
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $path = $dir.'/listado_reportes_definibles.pdf';
            $pdf->save($path);

            return response()->download($path);
        }

        // Excel/CSV simple via view export of catalog
        $export = new \App\Exports\Contable\ReporteDefinibleCatalogoExport();
        $export->parametros($filtros, $filas);

        return $export->download(
            'reportes_definibles_'.date('Ymd_His').($formato === 'CSV' ? '.csv' : '.xlsx'),
            $formato === 'CSV' ? Excel::CSV : Excel::XLSX
        );
    }

    public function layoutsJson($id)
    {
        $this->assertPuedeDisenar();

        return response()->json([
            'ok' => true,
            'layouts' => $this->layoutSupport->payloadUi((int) $id),
        ]);
    }

    public function clonarLayout(Request $request, $id)
    {
        can('actualizar-reporte-definible');
        $data = $request->validate([
            'layout_origen_id' => 'required|integer|min:1',
            'codigo' => 'nullable|string|max:40',
        ]);
        $layout = $this->layoutSupport->clonarDesdeSistema(
            (int) $data['layout_origen_id'],
            (int) $id,
            $data['codigo'] ?? null
        );

        return response()->json([
            'ok' => true,
            'layout' => $layout,
            'layouts' => $this->layoutSupport->payloadUi((int) $id),
        ]);
    }

    public function crearLayout(Request $request, $id)
    {
        can('actualizar-reporte-definible');
        $data = $request->validate([
            'codigo' => 'required|string|max:40',
            'nombre' => 'required|string|max:80',
            'es_default' => 'nullable|boolean',
            'orden' => 'nullable|integer',
        ]);
        $data['es_default'] = $request->boolean('es_default');
        $layout = $this->layoutSupport->crearLayoutInforme((int) $id, $data);

        return response()->json([
            'ok' => true,
            'layout' => $layout,
            'layouts' => $this->layoutSupport->payloadUi((int) $id),
        ]);
    }

    public function actualizarLayout(Request $request, $id, $layoutId)
    {
        can('actualizar-reporte-definible');
        $layout = \App\Models\Contable\ReporteContableLayout::query()->findOrFail((int) $layoutId);
        $this->assertLayoutDelInforme($layout, (int) $id);
        $data = $request->validate([
            'nombre' => 'nullable|string|max:80',
            'activo' => 'nullable|boolean',
            'orden' => 'nullable|integer',
            'es_default' => 'nullable|boolean',
        ]);
        if ($request->has('activo')) {
            $data['activo'] = $request->boolean('activo');
        }
        if ($request->boolean('es_default')) {
            $data['es_default'] = true;
        }
        $layout = $this->layoutSupport->actualizarLayout($layout, $data);

        return response()->json([
            'ok' => true,
            'layout' => $layout,
            'layouts' => $this->layoutSupport->payloadUi((int) $id),
        ]);
    }

    public function eliminarLayout(Request $request, $id, $layoutId)
    {
        can('actualizar-reporte-definible');
        $layout = \App\Models\Contable\ReporteContableLayout::query()->findOrFail((int) $layoutId);
        $this->assertLayoutDelInforme($layout, (int) $id);
        $this->layoutSupport->eliminarLayout($layout);

        return response()->json([
            'ok' => true,
            'layouts' => $this->layoutSupport->payloadUi((int) $id),
        ]);
    }

    public function marcarLayoutDefault(Request $request, $id, $layoutId)
    {
        can('actualizar-reporte-definible');
        $this->layoutSupport->marcarDefault((int) $id, (int) $layoutId);

        return response()->json([
            'ok' => true,
            'layouts' => $this->layoutSupport->payloadUi((int) $id),
        ]);
    }

    public function agregarColumnaLayout(Request $request, $id, $layoutId)
    {
        can('actualizar-reporte-definible');
        $layout = \App\Models\Contable\ReporteContableLayout::query()->findOrFail((int) $layoutId);
        $this->assertLayoutDelInforme($layout, (int) $id);
        $data = $request->validate([
            'tipo' => 'required|string|max:30',
            'key' => 'nullable|string|max:40',
            'label' => 'nullable|string|max:80',
            'orden' => 'nullable|integer',
            'meta' => 'nullable|array',
        ]);
        $col = $this->layoutSupport->agregarColumna($layout, $data);

        return response()->json([
            'ok' => true,
            'columna' => $col,
            'layouts' => $this->layoutSupport->payloadUi((int) $id),
        ]);
    }

    public function actualizarColumnaLayout(Request $request, $id, $layoutId, $columnaId)
    {
        can('actualizar-reporte-definible');
        $layout = \App\Models\Contable\ReporteContableLayout::query()->findOrFail((int) $layoutId);
        $this->assertLayoutDelInforme($layout, (int) $id);
        $col = \App\Models\Contable\ReporteContableLayoutColumna::query()
            ->where('reporte_contable_layout_id', $layout->id)
            ->whereKey((int) $columnaId)
            ->firstOrFail();
        $data = $request->validate([
            'tipo' => 'nullable|string|max:30',
            'key' => 'nullable|string|max:40',
            'label' => 'nullable|string|max:80',
            'orden' => 'nullable|integer',
            'meta' => 'nullable|array',
        ]);
        $col = $this->layoutSupport->actualizarColumna($col, $data);

        return response()->json([
            'ok' => true,
            'columna' => $col,
            'layouts' => $this->layoutSupport->payloadUi((int) $id),
        ]);
    }

    public function eliminarColumnaLayout(Request $request, $id, $layoutId, $columnaId)
    {
        can('actualizar-reporte-definible');
        $layout = \App\Models\Contable\ReporteContableLayout::query()->findOrFail((int) $layoutId);
        $this->assertLayoutDelInforme($layout, (int) $id);
        $col = \App\Models\Contable\ReporteContableLayoutColumna::query()
            ->where('reporte_contable_layout_id', $layout->id)
            ->whereKey((int) $columnaId)
            ->firstOrFail();
        $this->layoutSupport->eliminarColumna($col);

        return response()->json([
            'ok' => true,
            'layouts' => $this->layoutSupport->payloadUi((int) $id),
        ]);
    }

    public function reordenarColumnasLayout(Request $request, $id, $layoutId)
    {
        can('actualizar-reporte-definible');
        $layout = \App\Models\Contable\ReporteContableLayout::query()->findOrFail((int) $layoutId);
        $this->assertLayoutDelInforme($layout, (int) $id);
        $data = $request->validate([
            'ordenes' => 'required|array',
            'ordenes.*.id' => 'required|integer',
            'ordenes.*.orden' => 'required|integer',
        ]);
        $this->layoutSupport->reordenarColumnas($layout, $data['ordenes']);

        return response()->json([
            'ok' => true,
            'layouts' => $this->layoutSupport->payloadUi((int) $id),
        ]);
    }

    public function eliReglasJson($id)
    {
        $this->assertPuedeDisenar();

        return response()->json([
            'ok' => true,
            'reglas' => $this->eliminacionSupport->payloadUi((int) $id),
        ]);
    }

    public function guardarEliRegla(Request $request, $id)
    {
        can('actualizar-reporte-definible');
        $data = $request->validate([
            'nombre' => 'required|string|max:80',
            'codigo_desde' => 'required|integer|min:1',
            'codigo_hasta' => 'nullable|integer|min:1',
            'activo' => 'nullable|boolean',
            'orden' => 'nullable|integer',
            'ambito' => 'nullable|in:todas,pareja',
            'empresa_a_id' => 'nullable|integer|min:1',
            'empresa_b_id' => 'nullable|integer|min:1',
        ]);
        $data['activo'] = $request->boolean('activo', true);
        $regla = $this->eliminacionSupport->crearRegla((int) $id, $data);

        return response()->json([
            'ok' => true,
            'regla' => $regla,
            'reglas' => $this->eliminacionSupport->payloadUi((int) $id),
        ]);
    }

    public function guardarParticipacion(Request $request, $id)
    {
        can('actualizar-reporte-definible');
        $data = $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'porcentaje' => 'required|numeric|min:0|max:100',
            'vigente_desde' => 'nullable|date',
            'vigente_hasta' => 'nullable|date',
        ]);
        $row = $this->participacionSupport->upsert((int) $id, $data);

        return response()->json([
            'ok' => true,
            'participacion' => $row,
            'participaciones' => $this->participacionSupport->payloadUi((int) $id),
        ]);
    }

    public function eliminarParticipacion(Request $request, $id, $partId)
    {
        can('actualizar-reporte-definible');
        $this->participacionSupport->eliminar((int) $id, (int) $partId);

        return response()->json([
            'ok' => true,
            'participaciones' => $this->participacionSupport->payloadUi((int) $id),
        ]);
    }

    public function actualizarEliRegla(Request $request, $id, $reglaId)
    {
        can('actualizar-reporte-definible');
        $regla = \App\Models\Contable\ReporteContableEliRegla::query()->findOrFail((int) $reglaId);
        if ((int) $regla->reporte_contable_id !== (int) $id) {
            abort(404);
        }
        $data = $request->validate([
            'nombre' => 'nullable|string|max:80',
            'codigo_desde' => 'nullable|integer|min:1',
            'codigo_hasta' => 'nullable|integer|min:0',
            'activo' => 'nullable|boolean',
            'orden' => 'nullable|integer',
        ]);
        if ($request->has('activo')) {
            $data['activo'] = $request->boolean('activo');
        }
        $regla = $this->eliminacionSupport->actualizarRegla($regla, $data);

        return response()->json([
            'ok' => true,
            'regla' => $regla,
            'reglas' => $this->eliminacionSupport->payloadUi((int) $id),
        ]);
    }

    public function eliminarEliRegla(Request $request, $id, $reglaId)
    {
        can('actualizar-reporte-definible');
        $regla = \App\Models\Contable\ReporteContableEliRegla::query()->findOrFail((int) $reglaId);
        if ((int) $regla->reporte_contable_id !== (int) $id) {
            abort(404);
        }
        $this->eliminacionSupport->eliminarRegla($regla);

        return response()->json([
            'ok' => true,
            'reglas' => $this->eliminacionSupport->payloadUi((int) $id),
        ]);
    }

    public function diffVersion(Request $request, $id)
    {
        can('editar-reporte-definible');
        $this->assertAclInforme((int) $id);
        $reporte = $this->repository->findConEstructura((int) $id);
        if (! $reporte) {
            return response()->json(['ok' => false, 'mensaje' => 'Informe no encontrado.'], 404);
        }

        $versionAId = (int) $request->input('version_a_id', 0);
        $versionBId = (int) $request->input('version_b_id', 0);
        $actual = $this->versionSupport->armarSnapshot($reporte);

        if ($versionAId <= 0 && $versionBId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Indique al menos una versión.'], 422);
        }

        $snapA = $actual;
        $snapB = $actual;
        if ($versionAId > 0) {
            $va = \App\Models\Contable\ReporteContableVersion::query()->findOrFail($versionAId);
            if ((int) $va->reporte_contable_id !== (int) $id) {
                abort(404);
            }
            $snapA = $va->snapshot ?? [];
        }
        if ($versionBId > 0) {
            $vb = \App\Models\Contable\ReporteContableVersion::query()->findOrFail($versionBId);
            if ((int) $vb->reporte_contable_id !== (int) $id) {
                abort(404);
            }
            $snapB = $vb->snapshot ?? [];
        } elseif ($versionAId > 0) {
            // Comparar versión A vs estructura actual
            $snapB = $actual;
        }

        return response()->json([
            'ok' => true,
            'diff' => $this->versionSupport->diff($snapA, $snapB),
        ]);
    }

    public function syncAccesos(Request $request, $id)
    {
        can('actualizar-reporte-definible');
        $this->assertAclInforme((int) $id);
        $ids = $request->input('usuario_ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }
        $this->aclSupport->syncUsuarios(
            (int) $id,
            $this->usuarioRepository->filtrarIdsOperativos($ids)
        );

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'accesos' => $this->aclSupport->payloadUi((int) $id),
            ]);
        }

        return redirect()
            ->route('editar_reporte_definible', ['id' => $id])
            ->with('mensaje', 'Accesos actualizados.')
            ->withFragment('tab-acceso');
    }

    public function variantesJson($id)
    {
        $this->assertPuedeDisenar();
        $this->assertAclInforme((int) $id);

        return response()->json([
            'ok' => true,
            'variantes' => $this->varianteSupport->payloadUi((int) $id, (int) auth()->id()),
        ]);
    }

    public function guardarVariante(Request $request, $id)
    {
        if (! can('ejecutar-reporte-definible', false) && ! can('listar-reporte-definible', false)) {
            can('listar-reporte-definible');
        }
        $this->assertAclInforme((int) $id);
        $data = $request->validate([
            'nombre' => 'required|string|max:80',
            'filtros' => 'nullable|array',
        ]);
        $row = $this->varianteSupport->guardar(
            (int) $id,
            (int) auth()->id(),
            (string) $data['nombre'],
            $data['filtros'] ?? []
        );

        return response()->json([
            'ok' => true,
            'variante' => $row,
            'variantes' => $this->varianteSupport->payloadUi((int) $id, (int) auth()->id()),
        ]);
    }

    public function eliminarVariante(Request $request, $id, $varianteId)
    {
        if (! can('ejecutar-reporte-definible', false) && ! can('listar-reporte-definible', false)) {
            can('listar-reporte-definible');
        }
        $this->assertAclInforme((int) $id);
        $this->varianteSupport->eliminar((int) $id, (int) auth()->id(), (int) $varianteId);

        return response()->json([
            'ok' => true,
            'variantes' => $this->varianteSupport->payloadUi((int) $id, (int) auth()->id()),
        ]);
    }

    public function alertasJson($id)
    {
        $this->assertPuedeDisenar();
        $this->assertAclInforme((int) $id);

        return response()->json([
            'ok' => true,
            'alertas' => $this->alertaSupport->payloadUi((int) $id),
            'tipos' => \App\Models\Contable\ReporteContableAlerta::tipos(),
        ]);
    }

    public function guardarAlerta(Request $request, $id)
    {
        can('actualizar-reporte-definible');
        $this->assertAclInforme((int) $id);
        $data = $request->validate([
            'tipo' => 'required|string|max:40',
            'etiqueta' => 'nullable|string|max:120',
            'expresion' => 'nullable|string|max:255',
            'umbral' => 'nullable|numeric',
            'activo' => 'nullable|boolean',
            'orden' => 'nullable|integer',
        ]);
        $data['activo'] = $request->boolean('activo', true);
        $row = $this->alertaSupport->crear((int) $id, $data);

        return response()->json([
            'ok' => true,
            'alerta' => $row,
            'alertas' => $this->alertaSupport->payloadUi((int) $id),
        ]);
    }

    public function actualizarAlerta(Request $request, $id, $alertaId)
    {
        can('actualizar-reporte-definible');
        $this->assertAclInforme((int) $id);
        $alerta = \App\Models\Contable\ReporteContableAlerta::query()->findOrFail((int) $alertaId);
        if ((int) $alerta->reporte_contable_id !== (int) $id) {
            abort(404);
        }
        $data = $request->validate([
            'tipo' => 'nullable|string|max:40',
            'etiqueta' => 'nullable|string|max:120',
            'expresion' => 'nullable|string|max:255',
            'umbral' => 'nullable|numeric',
            'activo' => 'nullable|boolean',
            'orden' => 'nullable|integer',
        ]);
        if ($request->has('activo')) {
            $data['activo'] = $request->boolean('activo');
        }
        $row = $this->alertaSupport->actualizar($alerta, $data);

        return response()->json([
            'ok' => true,
            'alerta' => $row,
            'alertas' => $this->alertaSupport->payloadUi((int) $id),
        ]);
    }

    public function eliminarAlerta(Request $request, $id, $alertaId)
    {
        can('actualizar-reporte-definible');
        $this->assertAclInforme((int) $id);
        $this->alertaSupport->eliminar((int) $id, (int) $alertaId);

        return response()->json([
            'ok' => true,
            'alertas' => $this->alertaSupport->payloadUi((int) $id),
        ]);
    }

    public function notasJson($id)
    {
        $this->assertPuedeDisenar();
        $this->assertAclInforme((int) $id);

        return response()->json([
            'ok' => true,
            'notas' => $this->notaSupport->payloadUi((int) $id),
            'lineas' => $this->notaSupport->lineasDisponibles((int) $id),
        ]);
    }

    public function guardarNota(Request $request, $id)
    {
        can('actualizar-reporte-definible');
        $this->assertAclInforme((int) $id);

        $this->notaSupport->crear((int) $id, $this->datosNota($request), auth()->id());

        return response()->json([
            'ok' => true,
            'notas' => $this->notaSupport->payloadUi((int) $id),
        ]);
    }

    public function actualizarNota(Request $request, $id, $notaId)
    {
        can('actualizar-reporte-definible');
        $this->assertAclInforme((int) $id);

        $nota = $this->notaSupport->actualizar((int) $id, (int) $notaId, $this->datosNota($request), auth()->id());

        return response()->json([
            'ok' => true,
            'nota_id' => (int) $nota->id,
            'notas' => $this->notaSupport->payloadUi((int) $id),
        ]);
    }

    public function eliminarNota(Request $request, $id, $notaId)
    {
        can('actualizar-reporte-definible');
        $this->assertAclInforme((int) $id);
        $this->notaSupport->eliminar((int) $id, (int) $notaId);

        return response()->json([
            'ok' => true,
            'notas' => $this->notaSupport->payloadUi((int) $id),
        ]);
    }

    public function historialNota($id, $notaId)
    {
        $this->assertPuedeDisenar();
        $this->assertAclInforme((int) $id);

        return response()->json([
            'ok' => true,
            'historial' => $this->notaSupport->historial((int) $id, (int) $notaId),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosNota(Request $request): array
    {
        $data = $request->validate([
            'rubro_id' => 'nullable|integer',
            'texto' => 'required|string|max:4000',
            'periodo_desde' => 'nullable|string|max:7',
            'periodo_hasta' => 'nullable|string|max:7',
            'activo' => 'nullable|boolean',
            'orden' => 'nullable|integer',
        ]);
        $data['activo'] = $request->boolean('activo', true);

        return $data;
    }

    public function suscripcionesJson($id)
    {
        $this->assertPuedeDisenar();
        $this->assertAclInforme((int) $id);

        return response()->json([
            'ok' => true,
            'suscripciones' => $this->suscripcionSupport->payloadUi((int) $id),
        ]);
    }

    public function guardarSuscripcion(Request $request, $id)
    {
        can('actualizar-reporte-definible');
        $this->assertAclInforme((int) $id);

        $data = $this->datosSuscripcion($request);
        $filtros = $this->filtrosSuscripcion($request);

        $this->suscripcionSupport->crear((int) $id, $data, $filtros, auth()->id());

        return response()->json([
            'ok' => true,
            'suscripciones' => $this->suscripcionSupport->payloadUi((int) $id),
        ]);
    }

    public function actualizarSuscripcion(Request $request, $id, $suscripcionId)
    {
        can('actualizar-reporte-definible');
        $this->assertAclInforme((int) $id);

        $data = $this->datosSuscripcion($request);
        // Los filtros solo se reemplazan si la pantalla los volvió a capturar.
        $filtros = $request->boolean('capturar_filtros') ? $this->filtrosSuscripcion($request) : null;

        $this->suscripcionSupport->actualizar((int) $id, (int) $suscripcionId, $data, $filtros);

        return response()->json([
            'ok' => true,
            'suscripciones' => $this->suscripcionSupport->payloadUi((int) $id),
        ]);
    }

    public function eliminarSuscripcion(Request $request, $id, $suscripcionId)
    {
        can('actualizar-reporte-definible');
        $this->assertAclInforme((int) $id);
        $this->suscripcionSupport->eliminar((int) $id, (int) $suscripcionId);

        return response()->json([
            'ok' => true,
            'suscripciones' => $this->suscripcionSupport->payloadUi((int) $id),
        ]);
    }

    /**
     * Envío de prueba inmediato: mismo camino que el programado, sin esperar el día.
     */
    public function probarSuscripcion(Request $request, $id, $suscripcionId)
    {
        can('actualizar-reporte-definible');
        $this->assertAclInforme((int) $id);

        $suscripcion = \App\Models\Contable\ReporteContableSuscripcion::query()
            ->where('reporte_contable_id', (int) $id)
            ->whereKey((int) $suscripcionId)
            ->first();

        if (! $suscripcion) {
            return response()->json(['ok' => false, 'mensaje' => 'Envío no encontrado.'], 404);
        }

        try {
            $resultado = $this->distribucionService->enviar($suscripcion, $request->boolean('dry_run'));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 500);
        }

        if (! $request->boolean('dry_run')) {
            $this->suscripcionSupport->registrarResultado(
                $suscripcion,
                $resultado['estado'],
                $resultado['mensaje']
            );
        }

        return response()->json([
            'ok' => $resultado['estado'] !== \App\Models\Contable\ReporteContableSuscripcion::ESTADO_ERROR,
            'mensaje' => $resultado['mensaje'],
            'suscripciones' => $this->suscripcionSupport->payloadUi((int) $id),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosSuscripcion(Request $request): array
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:160',
            'activo' => 'nullable|boolean',
            'periodicidad' => 'nullable|string|max:20',
            'dia_mes' => 'nullable|integer|min:1|max:28',
            'dia_semana' => 'nullable|integer|min:1|max:7',
            'hora' => 'nullable|string|max:5',
            'periodo_relativo' => 'nullable|string|max:20',
            'formato' => 'nullable|string|max:10',
            'publicar' => 'nullable|boolean',
            'solo_si_alertas' => 'nullable|boolean',
            'destinatarios' => 'nullable|string|max:2000',
            'usuario_ids' => 'nullable|array',
            'usuario_ids.*' => 'integer',
            'mensaje' => 'nullable|string|max:2000',
        ]);

        $data['activo'] = $request->boolean('activo', true);
        $data['publicar'] = $request->boolean('publicar');
        $data['solo_si_alertas'] = $request->boolean('solo_si_alertas');

        return $data;
    }

    /**
     * Filtros del envío: los que la pantalla tenga cargados, con las empresas permitidas al usuario.
     *
     * @return array<string, mixed>
     */
    private function filtrosSuscripcion(Request $request): array
    {
        $filtros = ReporteDefinibleListadoFiltros::resolverEjecucionDesdeRequest($request);
        $permitidos = $this->empresaRepository->allFiltrado()->pluck('id')->map(fn ($v) => (int) $v)->all();
        $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas(
            $filtros['empresa_ids'] ?: $permitidos,
            $permitidos
        );

        return $filtros;
    }

    public function validarJson($id)
    {
        $this->assertPuedeDisenar();
        $this->assertAclInforme((int) $id);
        $reporte = $this->repository->findConEstructura((int) $id);
        if (! $reporte) {
            return response()->json(['ok' => false, 'mensaje' => 'Informe no encontrado.'], 404);
        }
        $issues = $this->validacionSupport->validar($reporte);

        return response()->json([
            'ok' => true,
            'issues' => $issues,
            'ok_definicion' => $issues === [],
        ]);
    }

    public function agregarCuentasCobertura(Request $request, $id)
    {
        can('actualizar-reporte-definible');
        $this->assertAclInforme((int) $id);
        $data = $request->validate([
            'rubro_id' => 'required|integer|min:1',
            'codigos' => 'required|array|min:1',
            'codigos.*' => 'integer|min:1',
            'origen' => 'nullable|string|max:1',
            'signo' => 'nullable|integer',
        ]);
        $rubro = \App\Models\Contable\ReporteContableRubro::query()->findOrFail((int) $data['rubro_id']);
        if ((int) $rubro->reporte_contable_id !== (int) $id) {
            abort(404);
        }

        $creadas = [];
        foreach ($data['codigos'] as $codigo) {
            $creadas[] = $this->repository->agregarCuenta((int) $rubro->id, [
                'codigo_cuenta' => (int) $codigo,
                'origen' => $data['origen'] ?? 'R',
                'signo' => $data['signo'] ?? 1,
            ]);
        }

        return response()->json([
            'ok' => true,
            'creadas' => count($creadas),
            'cuentas' => $this->cuentasRubroPayload(
                $this->repository->findConEstructura((int) $id),
                (int) $rubro->id
            ),
        ]);
    }

    private function assertAclInforme(int $reporteId): void
    {
        if ($reporteId <= 0) {
            return;
        }
        $usuarioId = (int) auth()->id();
        if (! $this->aclSupport->usuarioPuede($usuarioId, $reporteId)) {
            abort(403, 'No tiene acceso a este informe contable.');
        }
    }

    private function assertPuedeDisenar(): void
    {
        if (! can('editar-reporte-definible', false) && ! can('listar-reporte-definible', false)) {
            can('listar-reporte-definible');
        }
    }

    private function assertLayoutDelInforme(\App\Models\Contable\ReporteContableLayout $layout, int $reporteId): void
    {
        if ((int) $layout->reporte_contable_id !== $reporteId) {
            abort(404);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cuentasRubroPayload(?\App\Models\Contable\ReporteContable $reporte, int $rubroId): array
    {
        if (! $reporte) {
            return [];
        }
        $rubro = $reporte->rubros->firstWhere('id', $rubroId);
        if (! $rubro) {
            return [];
        }

        $out = [];
        foreach ($rubro->cuentas as $cta) {
            $out[] = [
                'id' => (int) $cta->id,
                'codigo_cuenta' => (int) $cta->codigo_cuenta,
                'codigo_hasta' => $cta->codigo_hasta !== null ? (int) $cta->codigo_hasta : null,
                'codigo_fmt' => app(\App\Support\Contable\ReporteDefinible\ReporteDefinibleCuentaRangoSupport::class)
                    ->etiqueta((int) $cta->codigo_cuenta, $cta->codigo_hasta !== null ? (int) $cta->codigo_hasta : null),
                'nombre' => $cta->cuentacontable->nombre ?? '',
                'empresa_id' => $cta->empresa_id,
                'origen' => $cta->origen,
                'signo' => (int) $cta->signo,
                'carga_ccosto' => $cta->carga_ccosto,
            ];
        }

        return $out;
    }
}
