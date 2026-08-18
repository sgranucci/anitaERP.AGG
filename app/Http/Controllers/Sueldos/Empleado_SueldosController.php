<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\EmpleadoSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionEmpleado_Sueldos;
use App\Models\Configuracion\Pais;
use App\Models\Configuracion\Provincia;
use App\Models\Contable\Centrocosto;
use App\Models\Sueldos\Agrupamiento_Sueldos;
use App\Models\Sueldos\Art_Sueldos;
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
use App\Support\Sueldos\EmpleadoEstados;
use App\Support\Sueldos\EmpleadoSueldosListadoFiltros;
use App\Support\Sueldos\Formula\FormulaException;
use App\Models\Sueldos\Liquidacion_Sueldos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class Empleado_SueldosController extends Controller
{
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
        can('listar-empleado-sueldos');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = EmpleadoSueldosListadoFiltros::resolverDesdeRequest($request, null, $empresaDefault ? (int) $empresaDefault : null);
        $datas = $this->repository->leeEmpleado($filtros, true);

        return view('sueldos.empleado.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => EmpleadoSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => EmpleadoSueldosListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'estadosLabels' => EmpleadoEstados::LABELS,
            'categorias' => Categoria_Sueldos::query()->orderBy('codigo')->get(['id', 'codigo', 'descripcion']),
        ]);
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
                .$r['sin_empresa'].' sin empresa ERP (de '.$r['en_anita'].' en Anita). '
                .'Historia: '.$r['historia'].' · Leyendas: '.$r['leyendas'].' · Bases: '.$r['bases'].'.'
        );
    }

    public function vincularDomicilios(Request $request)
    {
        can('actualizar-empleado-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $r = $this->repository->vincularDomicilios();

        return redirect('sueldos/empleado')->with(
            'mensaje',
            'Vinculación de domicilios: '.$r['procesados'].' empleados procesados · '
                .$r['provincia_vinculada'].' provincias vinculadas · '
                .$r['provincia_corregida'].' provincias corregidas (CABA) · '
                .$r['localidad_vinculada'].' localidades vinculadas · '
                .$r['cp_completado'].' códigos postales completados. '
                .'Sin coincidencia: '.$r['sin_provincia_textos'].' textos de provincia, '
                .$r['sin_localidad_textos'].' de localidad.'
        );
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-empleado-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = EmpleadoSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda, $empresaDefault ? (int) $empresaDefault : null);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeEmpleado($filtros, false);
                $view = \View::make('sueldos.empleado.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/listado_empleado_sueldos.pdf');

                return response()->download($path.'/listado_empleado_sueldos.pdf');

            case 'EXCEL':
                return app(EmpleadoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('empleado_sueldos.xlsx');

            case 'CSV':
                return app(EmpleadoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('empleado_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_empleado_sueldos', EmpleadoSueldosListadoFiltros::paraQueryString($filtros));
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

    public function editar($id)
    {
        can('editar-empleado-sueldos');
        $data = $this->repository->findOrFail($id);

        // Motor de vacaciones al abrir el legajo: la solapa solo lee el ledger.
        $this->sincronizarSaldosVacaciones($data);

        $usaTabla = $data->categoria
            ? CategoriaOrigenBases::usaTablaCategoria($data->categoria->origen_bases)
            : true;

        $basesGrilla = $usaTabla && $data->categoria_id
            ? $this->categoriaBaseService->resumenBasesGrilla((int) $data->categoria_id)
            : $this->baseService->resumenBasesGrilla((int) $data->id);

        return view('sueldos.empleado.editar', array_merge($this->datosFormulario(), [
            'data' => $data,
            'usaTabla' => $usaTabla,
            'basesGrilla' => $basesGrilla,
            'nombrebases' => Nombrebase_Sueldos::query()->orderBy('codigo')->get(),
            'puedeBorrarVigencia' => can('borrar-vigencia-empleado-sueldos', false),
            'puedeBaja' => can('baja-empleado-sueldos', false),
            'puedeAutorizar' => can('autorizar-empleado-sueldos', false),
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
            return response()->json(['mensaje' => $this->repository->delete($id) ? 'ok' : 'ng']);
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
        can('baja-empleado-sueldos');
        $request->validate([
            'fecha_egreso' => 'required|date',
            'motivoegreso_id' => 'nullable|integer|exists:motivoegreso_sueldos,id',
            'comentario_baja' => 'nullable|string|max:80',
        ]);

        try {
            $emp = $this->repository->findOrFail($id);
            $this->ingresoService->darDeBaja(
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
            ->with('mensaje', 'Baja registrada. Se actualizó la historia de ingresos/egresos.');
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

    // --- Bases AJAX (solo cuando categoría origen = empleado) ---

    public function guardarBase(Request $request, $id)
    {
        can('actualizar-empleado-sueldos');
        $emp = $this->repository->findOrFail($id);
        $this->assertBasesEditables($emp);

        $request->validate([
            'nombrebase_id' => 'required|integer|exists:nombrebase_sueldos,id',
            'valor' => 'required|numeric',
            'fecha_vigencia' => 'required|date',
        ]);

        $resultado = $this->baseService->guardarBase(
            (int) $emp->id,
            (int) $request->input('nombrebase_id'),
            (float) $request->input('valor'),
            $request->input('fecha_vigencia'),
            Auth::id()
        );

        return response()->json(['ok' => true, 'creo_version' => $resultado['creo_version']]);
    }

    public function guardarVigenciasLote(Request $request, $id)
    {
        can('actualizar-empleado-sueldos');
        $emp = $this->repository->findOrFail($id);
        $this->assertBasesEditables($emp);

        $request->validate([
            'nombrebase_id' => 'required|integer|exists:nombrebase_sueldos,id',
            'items' => 'nullable|array',
            'eliminar_ids' => 'nullable|array',
        ]);

        $resultado = $this->baseService->guardarVigenciasLote(
            (int) $emp->id,
            (int) $request->input('nombrebase_id'),
            $request->input('items', []),
            $request->input('eliminar_ids', []),
            Auth::id()
        );

        return response()->json($resultado);
    }

    public function bases($id)
    {
        can('editar-empleado-sueldos');
        $emp = $this->repository->findOrFail($id);

        return response()->json([
            'grilla' => $this->baseService->resumenBasesGrilla((int) $emp->id),
        ]);
    }

    /**
     * Preview JSON de conceptos que liquidarían para el legajo (no persiste).
     */
    public function simularLiquidacion(Request $request, LiquidacionCalculadorService $calculador, $id)
    {
        can('editar-empleado-sueldos');
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
        can('editar-empleado-sueldos');
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
        can('editar-empleado-sueldos');
        $emp = $this->repository->findOrFail($id);
        $nb = $request->input('nombrebase_id') ? (int) $request->input('nombrebase_id') : null;

        // Con bases por tabla de categoría, la grilla y el modal muestran las vigencias
        // de la categoría (solo lectura); el empleado no tiene filas propias.
        $usaTabla = $emp->categoria
            ? CategoriaOrigenBases::usaTablaCategoria($emp->categoria->origen_bases)
            : true;

        $historial = $usaTabla && $emp->categoria_id
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
        $this->assertBasesEditables($emp);
        $request->validate(['valor' => 'required|numeric', 'fecha_vigencia' => 'required|date']);

        $resultado = $this->baseService->actualizarVigencia(
            (int) $baseId,
            (float) $request->input('valor'),
            $request->input('fecha_vigencia'),
            Auth::id()
        );

        return response()->json($resultado);
    }

    public function eliminarBase($id, $baseId)
    {
        can('borrar-vigencia-empleado-sueldos');
        $this->repository->findOrFail($id);

        return response()->json(['ok' => $this->baseService->eliminarBase((int) $baseId)]);
    }

    public function eliminarBaseCompleta($id, $nombrebaseId)
    {
        can('borrar-vigencia-empleado-sueldos');
        $emp = $this->repository->findOrFail($id);
        $cant = $this->baseService->eliminarBaseCompleta((int) $emp->id, (int) $nombrebaseId);

        return response()->json(['ok' => true, 'eliminados' => $cant]);
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
            'pais_query' => Pais::query()->orderBy('nombre')->get(),
            'provincia_query' => Provincia::query()->orderBy('nombre')->get(),
            'vacaciones' => Vacacion_Sueldos::query()->orderBy('codigo')->get(),
            'arts' => Art_Sueldos::query()->orderBy('codigo')->get(),
            'motivosegreso' => Motivoegreso_Sueldos::query()->orderBy('codigo')->get(),
            'estadosLabels' => EmpleadoEstados::LABELS,
        ];
    }

    private function assertBasesEditables($emp): void
    {
        if ($emp->categoria && CategoriaOrigenBases::usaTablaCategoria($emp->categoria->origen_bases)) {
            abort(422, 'Las bases se heredan de la categoría (tabla).');
        }
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
