<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\LiquidacionAsientoExport;
use App\Exports\Sueldos\LiquidacionSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionLiquidacion_Sueldos;
use App\Models\Seguridad\Usuario;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Models\Sueldos\Motivoegreso_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sueldos\Liquidacion_SueldosRepositoryInterface;
use App\Services\Sueldos\AnitaLiquidacionNovedadSyncService;
use App\Services\Sueldos\ImportarAuxconfLiquidacionService;
use App\Services\Sueldos\LiquidacionAsientoService;
use App\Services\Sueldos\LiquidacionSolicitudpagoService;
use App\Services\Sueldos\LiquidacionCalculadorService;
use App\Services\Sueldos\LiquidacionReciboEliminarService;
use App\Services\Sueldos\LsdGeneradorPresentacionService;
use App\Services\Sueldos\PlanCuotaLiquidacionService;
use App\Services\Sueldos\ReciboAnexoIIIArmadorService;
use App\Services\Sueldos\ReciboLotePdfService;
use App\Services\Sueldos\ReciboMultiempresaService;
use App\Support\Sueldos\Formula\FormulaException;
use App\Support\Sueldos\LiquidacionConfidencialSeguridadSupport;
use App\Support\Sueldos\LiquidacionSueldosListadoFiltros;
use App\Support\Sueldos\SueldosAsientoCalidadCierreSupport;
use App\Support\Sueldos\SueldosAsientoExportSupport;
use App\Support\Sueldos\SueldosAsientoSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Excel;

class Liquidacion_SueldosController extends Controller
{
    public function __construct(
        private Liquidacion_SueldosRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * Trae maeliq (master) + novedades Anita. Default: empresa 1, fecha_liq >= 20260700.
     */
    public function sincronizarAnita(Request $request, AnitaLiquidacionNovedadSyncService $sync)
    {
        can('crear-liquidacion-sueldos');

        $empresaId = (int) $request->input('empresa_id', 1);
        $fechaDesde = (int) $request->input('fecha_liq_desde', 20260700);
        if ($empresaId <= 0) {
            $empresaId = 1;
        }
        if ($fechaDesde < 19000100) {
            $fechaDesde = 20260700;
        }

        $r = $sync->sincronizarEmpresaDesdeFechaLiq($empresaId, $fechaDesde);
        $liq = $r['liquidaciones'];
        $nov = $r['novedades'];

        $msg = 'Sync Anita empresa '.$empresaId.' (mael_fecha_liq >= '.$fechaDesde.'): '
            .'liquidaciones Anita '.$liq['en_anita']
            .' · importadas '.$liq['importadas']
            .' · actualizadas '.$liq['actualizadas']
            .' · números '.implode(', ', $liq['numeros'] ?: ['—'])
            .' · novedades en alcance '.$nov['en_anita']
            .' · importadas '.$nov['importados']
            .' · actualizadas '.($nov['actualizados'] ?? 0)
            .' · eliminadas '.($nov['eliminados'] ?? 0)
            .' · omitidas '.$nov['omitidos'];

        $errores = array_merge($liq['errores'] ?? [], $nov['errores'] ?? []);
        if ($errores !== []) {
            return redirect()->route('consultar_liquidacion_sueldos')
                ->with('mensaje', $msg)
                ->with('error', implode(' | ', array_slice($errores, 0, 5)));
        }

        return redirect()->route('consultar_liquidacion_sueldos')->with('mensaje', $msg);
    }

    public function index(Request $request)
    {
        can('listar-liquidacion-sueldos');

        $filtros = LiquidacionSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeLiquidacion($filtros, true);
        LiquidacionConfidencialSeguridadSupport::aplicarTotalesVisiblesColeccion($datas);

        return view('sueldos.liquidacion.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => LiquidacionSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => LiquidacionSueldosListadoFiltros::CAMPOS,
            'empresas' => $this->empresas(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-liquidacion-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = LiquidacionSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeLiquidacion($filtros, false);
                LiquidacionConfidencialSeguridadSupport::aplicarTotalesVisiblesColeccion($datas);

                $view = \View::make('sueldos.liquidacion.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_liquidacion_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(LiquidacionSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('liquidaciones_sueldos.xlsx');

            case 'CSV':
                return app(LiquidacionSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('liquidaciones_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_liquidacion_sueldos', LiquidacionSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-liquidacion-sueldos');

        $empresas = $this->empresas();
        $empresaDefault = (int) (old('empresa_id') ?: optional($empresas->first())->id ?: 0);

        return view('sueldos.liquidacion.crear', [
            'empresas' => $empresas,
            'motivosegreso' => $this->motivosegreso(),
            'numeroSugerido' => $empresaDefault > 0 ? $this->repository->proximoNumero($empresaDefault) : null,
        ]);
    }

    /**
     * Próximo código Anita sugerido para una empresa (alta de corrida).
     */
    public function proximoNumero(Request $request)
    {
        can('crear-liquidacion-sueldos');

        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0) {
            return response()->json(['numero' => null], 422);
        }

        return response()->json([
            'numero' => $this->repository->proximoNumero($empresaId),
        ]);
    }

    /**
     * Candidatos a liquidar según tipo/período (para armar la selección en el ABM).
     */
    public function candidatosEmpleados(Request $request)
    {
        if (! can('crear-liquidacion-sueldos', false) && ! can('editar-liquidacion-sueldos', false)) {
            abort(403);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $tipo = (string) $request->input('tipo', 'mensual');
        $desde = $request->input('periodo_desde');
        $hasta = $request->input('periodo_hasta');
        $periodo = preg_replace('/\D+/', '', (string) $request->input('periodo', ''));
        $motivoId = (int) $request->input('motivoegreso_id', 0);

        if ($empresaId <= 0) {
            return response()->json(['data' => [], 'mensaje' => 'Seleccioná empresa'], 422);
        }

        $q = \App\Models\Sueldos\Empleado_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->orderBy('legajo');

        if ($tipo === 'final') {
            $q->whereNotNull('fecha_egreso');
            if ($desde && $hasta) {
                $q->whereBetween('fecha_egreso', [$desde, $hasta]);
            } elseif (strlen($periodo) === 6) {
                $q->whereYear('fecha_egreso', (int) substr($periodo, 0, 4))
                    ->whereMonth('fecha_egreso', (int) substr($periodo, 4, 2));
            }
            if ($motivoId > 0) {
                $q->where('motivoegreso_id', $motivoId);
            }
        } else {
            $q->where('estado', \App\Support\Sueldos\EmpleadoEstados::ACTIVO);
        }

        $rows = $q->limit(500)->get(['id', 'legajo', 'nombre', 'estado', 'fecha_egreso', 'motivoegreso_id']);
        $data = $rows->map(function ($e) {
            return [
                'id' => (int) $e->id,
                'legajo' => $e->legajo,
                'nombre' => (string) $e->nombre,
                'estado' => (string) $e->estado,
                'fecha_egreso' => $e->fecha_egreso ? $e->fecha_egreso->format('Y-m-d') : null,
                'fecha_egreso_fmt' => $e->fecha_egreso ? $e->fecha_egreso->format('d/m/Y') : null,
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'total' => $data->count(),
            'tipo' => $tipo,
        ]);
    }

    public function guardar(ValidacionLiquidacion_Sueldos $request)
    {
        can('crear-liquidacion-sueldos');
        $liq = $this->repository->create($request->validated());

        return redirect('sueldos/liquidacion')
            ->with('mensaje', 'Corrida de liquidación creada (cód. Anita '.$liq->numero.')');
    }

    public function editar($id)
    {
        can('editar-liquidacion-sueldos');
        $data = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($data);

        if (! $data->esEditable()) {
            return redirect('sueldos/liquidacion')
                ->with('error', 'La corrida está '.strtolower($data->estadoLabel()).' y no puede editarse.');
        }

        $data->loadMissing('motivoegreso:id,codigo,descripcion,clase');

        return view('sueldos.liquidacion.editar', [
            'data' => $data,
            'empresas' => $this->empresas(),
            'motivosegreso' => $this->motivosegreso(),
        ]);
    }

    public function actualizar(ValidacionLiquidacion_Sueldos $request, $id)
    {
        can('actualizar-liquidacion-sueldos');

        $data = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($data);
        if (! $data->esEditable()) {
            return redirect('sueldos/liquidacion')
                ->with('error', 'La corrida no puede editarse en su estado actual.');
        }

        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/liquidacion')
            ->with('mensaje', 'Corrida de liquidación actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-liquidacion-sueldos');

        if ($request->ajax()) {
            $data = $this->repository->findOrFail($id);
            LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($data);
            if (! $data->esEditable()) {
                return response()->json(['mensaje' => 'ng', 'error' => 'Solo se pueden eliminar corridas en borrador/calculadas.'], 422);
            }
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * Transiciones de estado del workflow (sin cálculo aún: el motor llega en la
     * etapa siguiente). Valida transiciones permitidas.
     */
    public function estado(Request $request, PlanCuotaLiquidacionService $planCuotas, $id)
    {
        can('cerrar-liquidacion-sueldos');

        $destino = (string) $request->input('estado');
        $liq = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);

        $permitidas = [
            'revisada' => ['calculada'],
            'cerrada' => ['calculada', 'revisada'],
            'reabrir' => ['cerrada'],   // vuelve a calculada
            'anulada' => ['borrador', 'calculada', 'revisada', 'cerrada'],
        ];

        if ($destino === 'reabrir') {
            if (! in_array($liq->estado, $permitidas['reabrir'], true)
                || $liq->contabilizado
                || $liq->estado === 'contabilizada') {
                return back()->with('error', 'La corrida no puede reabrirse (está contabilizada).');
            }
            $this->repository->cambiarEstado((int) $id, 'calculada');
            // Retrocede el contador de cuotas confirmadas al cerrar.
            $planCuotas->revertir($liq);

            return back()->with('mensaje', 'Corrida reabierta (calculada).');
        }

        if (! isset($permitidas[$destino]) || ! in_array($liq->estado, $permitidas[$destino], true)) {
            return back()->with('error', 'Transición de estado no permitida.');
        }

        if ($destino === 'cerrada' && empty($liq->simulacion)) {
            try {
                SueldosAsientoCalidadCierreSupport::assertPuedeCerrar($liq);
            } catch (\Throwable $e) {
                return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
                    ->with('error', $e->getMessage());
            }
        }

        $this->repository->cambiarEstado((int) $id, $destino);

        // Planes de cuotas: al cerrar avanza el contador (y finaliza al llegar a N);
        // al anular retrocede lo confirmado y limpia los movimientos de la corrida.
        if ($destino === 'cerrada') {
            $planCuotas->confirmar($liq);
            if (Schema::hasTable('lsd_recibo_base_sueldos')) {
                app(LsdGeneradorPresentacionService::class)->persistirBasesCierre($liq->fresh());
            }
        } elseif ($destino === 'anulada') {
            $planCuotas->revertir($liq);
        }

        return back()->with('mensaje', 'Corrida actualizada a: '.(Liquidacion_Sueldos::ESTADOS[$destino] ?? $destino));
    }

    /**
     * Ejecuta el motor de calculo de la corrida (genera recibos y detalles).
     */
    public function calcular(Request $request, LiquidacionCalculadorService $calculador, $id)
    {
        can('editar-liquidacion-sueldos');

        $liq = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);
        if (! $liq->esEditable()) {
            return redirect('sueldos/liquidacion')
                ->with('error', 'La corrida está '.strtolower($liq->estadoLabel()).' y no puede recalcularse.');
        }

        try {
            $resumen = $calculador->calcular($liq);
        } catch (FormulaException $e) {
            return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
                ->with('error', 'Error en fórmula: '.$e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
                ->with('error', 'No se pudo calcular la corrida: '.$e->getMessage());
        }

        if ($resumen['sin_conceptos']) {
            return redirect('sueldos/liquidacion')
                ->with('error', 'No hay conceptos activos para liquidar. Cargue conceptos antes de calcular.');
        }

        $liq->refresh();
        $totalesVisibles = LiquidacionConfidencialSeguridadSupport::totalesVisiblesCorrida($liq);
        $msg = 'Corrida calculada: '.$totalesVisibles['cantidad'].' recibo(s). Neto total $ '
            .number_format($totalesVisibles['neto'], 2, ',', '.');
        $egresos = $resumen['egresos_periodo'] ?? [];
        $avisoEgresos = null;
        if (is_array($egresos) && $egresos !== []) {
            $sample = collect($egresos)->take(8)
                ->map(fn ($e) => 'leg.'.$e['legajo'].' '.$e['fecha_egreso'].' ('.$e['modo'].')')
                ->implode('; ');
            $avisoEgresos = count($egresos).' legajo(s) con egreso en el período: '.$sample
                .(count($egresos) > 8 ? '…' : '')
                .'. Las bajas provisorias siguen activas hasta confirmar la definitiva.';
        }
        $errN = (int) ($resumen['errores_count'] ?? $resumen['errores_formula_count'] ?? 0);
        $avisos = [];
        if ($avisoEgresos) {
            $avisos[] = $avisoEgresos;
        }
        if ($errN > 0) {
            $avisos[] = $errN.' incidencia(s) al calcular (fórmulas, empleados omitidos u otros). '
                .'Revise el panel «Errores de liquidación» más abajo.';
        }
        $redir = redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
            ->with('mensaje', $msg);
        if ($avisos !== []) {
            $redir->with('mensaje-aviso', $avisos);
        }

        return $redir;
    }

    /**
     * Resultado de la corrida: recibos generados con su detalle.
     */
    public function resultado(Request $request, ReciboMultiempresaService $multi, $id)
    {
        can('listar-liquidacion-sueldos');

        $liq = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);
        $liq->loadMissing([
            'asiento:id,numeroasiento,fecha',
            'asientosSueldos.asiento:id,numeroasiento,fecha',
            'solicitudpago:id,codigo,estado,monto',
        ]);

        $busqueda = trim((string) $request->input('q', ''));
        $query = $liq->recibos()
            ->with(['detalles', 'empleado:id,confidencial,nombre']);
        LiquidacionConfidencialSeguridadSupport::aplicarVisibilidadRecibos($query);
        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $query->where(function ($q) use ($like, $busqueda) {
                $q->where('apellido_nombre', 'like', $like)
                    ->orWhere('cuil', 'like', $like)
                    ->orWhere('legajo', 'like', $like);
                if (ctype_digit($busqueda)) {
                    $q->orWhere('numero_recibo', (int) $busqueda)
                        ->orWhere('legajo', (int) $busqueda);
                }
            });
        }
        $recibos = $query->orderBy('numero_recibo')->paginate(25)->appends($request->only('q'));

        $hermanosPorRecibo = $multi->hermanosPorRecibos($recibos->getCollection());
        $totalesVisibles = LiquidacionConfidencialSeguridadSupport::totalesVisiblesCorrida($liq);

        $filtroEtapaError = trim((string) $request->input('err_etapa', ''));
        $filtroQError = trim((string) $request->input('err_q', ''));
        $erroresQuery = $liq->errores()->orderBy('legajo')->orderBy('concepto_codigo')->orderBy('id');
        if ($filtroEtapaError !== '' && array_key_exists($filtroEtapaError, \App\Models\Sueldos\Liquidacion_Error_Sueldos::ETAPAS)) {
            $erroresQuery->where('etapa', $filtroEtapaError);
        }
        if ($filtroQError !== '') {
            $likeErr = '%'.$filtroQError.'%';
            $erroresQuery->where(function ($q) use ($likeErr, $filtroQError) {
                $q->where('apellido_nombre', 'like', $likeErr)
                    ->orWhere('mensaje', 'like', $likeErr)
                    ->orWhere('concepto_descripcion', 'like', $likeErr)
                    ->orWhere('legajo', 'like', $likeErr);
                if (ctype_digit($filtroQError)) {
                    $q->orWhere('legajo', (int) $filtroQError)
                        ->orWhere('concepto_codigo', (int) $filtroQError);
                }
            });
        }
        $erroresLiquidacion = $erroresQuery->paginate(50, ['*'], 'err_page')
            ->appends($request->only(['q', 'err_etapa', 'err_q']));
        $erroresCountTotal = (int) $liq->errores()->count();
        $erroresCountPorEtapa = $liq->errores()
            ->selectRaw('etapa, count(*) as c')
            ->groupBy('etapa')
            ->pluck('c', 'etapa');
        $empleadoIdsConError = $liq->errores()
            ->whereNotNull('empleado_id')
            ->distinct()
            ->pluck('empleado_id')
            ->flip();

        // Conceptos únicos con error de fórmula (p.ej. 142 filas → 1 botón Editar).
        $conceptosUnicosConError = $liq->errores()
            ->where('etapa', 'formula')
            ->whereNotNull('concepto_codigo')
            ->get([
                'concepto_id', 'concepto_codigo', 'concepto_descripcion',
                'formula_pos', 'formula_campo', 'mensaje',
            ])
            ->groupBy('concepto_codigo')
            ->map(function ($rows) {
                $first = $rows->first();
                $pos = $first->formulaPosicion();

                return [
                    'concepto_id' => $first->concepto_id,
                    'concepto_codigo' => (int) $first->concepto_codigo,
                    'concepto_descripcion' => (string) ($first->concepto_descripcion ?? ''),
                    'formula_pos' => $pos >= 0 ? $pos : null,
                    'formula_campo' => $first->formulaCampoId(),
                    'mensaje' => (string) $first->mensaje,
                    'cantidad' => $rows->count(),
                ];
            })
            ->sortBy('concepto_codigo')
            ->values();

        $codigosSinId = $conceptosUnicosConError
            ->filter(fn ($c) => empty($c['concepto_id']))
            ->pluck('concepto_codigo')
            ->all();
        if ($codigosSinId !== []) {
            $idsPorCodigo = \App\Models\Sueldos\Concepto_Sueldos::query()
                ->whereIn('codigo', $codigosSinId)
                ->pluck('id', 'codigo');
            $conceptosUnicosConError = $conceptosUnicosConError->map(function ($c) use ($idsPorCodigo) {
                if (empty($c['concepto_id']) && isset($idsPorCodigo[$c['concepto_codigo']])) {
                    $c['concepto_id'] = (int) $idsPorCodigo[$c['concepto_codigo']];
                }

                return $c;
            });
        }

        $previewAsiento = null;
        $errorAsiento = null;
        if (empty($liq->simulacion)
            && in_array((string) $liq->estado, ['calculada', 'revisada', 'cerrada', 'contabilizada', 'pagada'], true)
            && (int) ($liq->cantidad_recibos ?? 0) > 0) {
            try {
                $previewAsiento = app(LiquidacionAsientoService::class)->preview($liq);
            } catch (\Throwable $e) {
                $errorAsiento = $e->getMessage();
            }
        }

        return view('sueldos.liquidacion.resultado', [
            'liq' => $liq,
            'recibos' => $recibos,
            'hermanosPorRecibo' => $hermanosPorRecibo,
            'totalesVisibles' => $totalesVisibles,
            'puedeImportarConfidencial' => LiquidacionConfidencialSeguridadSupport::puedeImportarConfidencial()
                && $liq->esEditable(),
            'previewAsiento' => $previewAsiento,
            'errorAsiento' => $errorAsiento,
            'erroresLiquidacion' => $erroresLiquidacion,
            'erroresCountTotal' => $erroresCountTotal,
            'erroresCountPorEtapa' => $erroresCountPorEtapa,
            'empleadoIdsConError' => $empleadoIdsConError,
            'filtroEtapaError' => $filtroEtapaError,
            'filtroQError' => $filtroQError,
            'conceptosUnicosConError' => $conceptosUnicosConError,
            'conceptoIdPorCodigo' => $conceptosUnicosConError
                ->filter(fn ($c) => ! empty($c['concepto_id']))
                ->mapWithKeys(fn ($c) => [(int) $c['concepto_codigo'] => (int) $c['concepto_id']]),
            'puedeEditarConcepto' => can('editar-concepto-sueldos', false),
        ]);
    }

    public function previewAsiento($id)
    {
        can(SueldosAsientoSupport::PERMISO_CONTABILIZAR);

        $liq = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);

        try {
            $preview = app(LiquidacionAsientoService::class)->preview($liq);
        } catch (\Throwable $e) {
            return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
            ->with('previewAsiento', $preview);
    }

    /**
     * Exporta el asiento de la corrida en PDF / Excel / CSV.
     * Sin contabilizar: preview (sin N°). Contabilizada: movimientos reales con numeroasiento.
     */
    public function exportarAsiento($id, $formato = null)
    {
        can('listar-liquidacion-sueldos');

        $formato = strtoupper((string) $formato);
        $liq = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);

        if (! empty($liq->simulacion)) {
            return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
                ->with('error', 'Las corridas de simulación no generan asiento contable.');
        }

        try {
            $preview = null;
            if (! SueldosAsientoExportSupport::tieneAsientosGrabados($liq->loadMissing([
                'asiento',
                'asientosSueldos.asiento',
            ]))) {
                $preview = app(LiquidacionAsientoService::class)->preview($liq);
            }
            $payload = SueldosAsientoExportSupport::armar($liq, $preview);
        } catch (\Throwable $e) {
            return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
                ->with('error', $e->getMessage());
        }

        switch ($formato) {
            case 'PDF':
                $html = view('sueldos.liquidacion.asiento_pdf', compact('payload'))->render();
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'portrait');
                $pdf->loadHTML($html);

                return $pdf->download(SueldosAsientoExportSupport::nombreArchivo($payload, 'pdf'));

            case 'EXCEL':
                return (new LiquidacionAsientoExport($payload, false))
                    ->download(SueldosAsientoExportSupport::nombreArchivo($payload, 'xlsx'));

            case 'CSV':
                return (new LiquidacionAsientoExport($payload, true))
                    ->download(
                        SueldosAsientoExportSupport::nombreArchivo($payload, 'csv'),
                        Excel::CSV
                    );
        }

        return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id]);
    }

    public function contabilizar($id)
    {
        can(SueldosAsientoSupport::PERMISO_CONTABILIZAR);

        $liq = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);

        try {
            $asientoId = app(LiquidacionAsientoService::class)->generar($liq);
        } catch (\Throwable $e) {
            return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
            ->with('mensaje', 'Corrida contabilizada. Asiento #'.$asientoId.'.');
    }

    public function descontabilizar($id)
    {
        can(SueldosAsientoSupport::PERMISO_CONTABILIZAR);

        $liq = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);

        try {
            $reversoId = app(LiquidacionAsientoService::class)->revertir($liq);
        } catch (\Throwable $e) {
            return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
            ->with('mensaje', 'Corrida descontabilizada. Contra-asiento #'.$reversoId.'.');
    }

    public function generarSolicitudpago($id)
    {
        can(SueldosAsientoSupport::PERMISO_GENERAR_SP);

        $liq = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);

        try {
            $spId = app(LiquidacionSolicitudpagoService::class)->generar($liq);
        } catch (\Throwable $e) {
            return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
                ->with('error', $e->getMessage());
        }

        $sp = $liq->fresh()->solicitudpago;

        return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
            ->with('mensaje', 'Solicitud de pago '.($sp->codigo ?? $spId).' generada (autorizada). Pagala desde Caja.');
    }

    /**
     * Quita un recibo (empleado) de la corrida sin recalcular al resto.
     */
    public function eliminarRecibo(Request $request, LiquidacionReciboEliminarService $eliminar, $id, $reciboId)
    {
        can('editar-liquidacion-sueldos');

        $liq = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);
        $recibo = LiquidacionConfidencialSeguridadSupport::reciboVisibleDeCorrida((int) $id, (int) $reciboId);

        try {
            $res = $eliminar->eliminar($liq, $recibo);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
            }

            return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
                ->with('error', $e->getMessage());
        }

        $msg = 'Se quitó el legajo '.$res['legajo']
            .($res['nombre'] !== '' ? ' ('.$res['nombre'].')' : '')
            .' de la corrida sin recalcular al resto. Quedan '.$res['recibos_restantes'].' recibo(s).';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['mensaje' => 'ok', 'texto' => $msg, 'recibos_restantes' => $res['recibos_restantes']]);
        }

        return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
            ->with('mensaje', $msg);
    }

    /**
     * Vista previa HTML del recibo Anexo III (Dto. 407).
     * Query multiempresa=1|0 overridea el alcance de la corrida.
     */
    public function reciboPreview(
        Request $request,
        ReciboAnexoIIIArmadorService $armador,
        ReciboMultiempresaService $multi,
        $id,
        $reciboId
    ) {
        can('listar-liquidacion-sueldos');

        $recibo = LiquidacionConfidencialSeguridadSupport::reciboVisibleDeCorrida((int) $id, (int) $reciboId);
        $liq = $recibo->liquidacion ?? $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);
        $emitirMulti = $multi->emitirMultiempresa(
            $liq,
            $request->has('multiempresa') ? $request->boolean('multiempresa') : null
        );
        $cadena = $multi->cadenaEmision($recibo, $emitirMulti);
        $bloques = [];
        foreach ($cadena as $idx => $rec) {
            $datos = $armador->armar($rec);
            $datos['modo_preview'] = true;
            $datos['multiempresa_activo'] = $emitirMulti;
            $datos['multiempresa_indice'] = $idx + 1;
            $datos['multiempresa_total'] = $cadena->count();
            $bloques[] = $datos;
        }

        return response()->view('sueldos.liquidacion.recibo_anexo_iii_cadena', [
            'bloques' => $bloques,
            'es_pdf' => false,
            'liq' => $liq,
            'recibo' => $recibo,
            'multiempresa' => $emitirMulti,
        ]);
    }

    /**
     * PDF DomPDF del recibo Anexo III (cadena multiempresa si aplica).
     */
    public function reciboPdf(
        Request $request,
        ReciboAnexoIIIArmadorService $armador,
        ReciboMultiempresaService $multi,
        $id,
        $reciboId
    ) {
        can('listar-liquidacion-sueldos');

        $recibo = LiquidacionConfidencialSeguridadSupport::reciboVisibleDeCorrida((int) $id, (int) $reciboId);
        $liq = $recibo->liquidacion ?? $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);
        $emitirMulti = $multi->emitirMultiempresa(
            $liq,
            $request->has('multiempresa') ? $request->boolean('multiempresa') : null
        );
        $cadena = $multi->cadenaEmision($recibo, $emitirMulti);
        $bloques = [];
        foreach ($cadena as $idx => $rec) {
            $datos = $armador->armar($rec);
            $datos['modo_preview'] = false;
            $datos['multiempresa_activo'] = $emitirMulti;
            $datos['multiempresa_indice'] = $idx + 1;
            $datos['multiempresa_total'] = $cadena->count();
            $bloques[] = $datos;
        }

        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadView('sueldos.liquidacion.recibo_anexo_iii_cadena', [
            'bloques' => $bloques,
            'es_pdf' => true,
            'liq' => $liq,
            'recibo' => $recibo,
            'multiempresa' => $emitirMulti,
        ])->setPaper('a4', 'portrait');

        $nombre = 'recibo_'.$recibo->legajo.'_'.$recibo->numero_recibo
            .($emitirMulti && $cadena->count() > 1 ? '_multi' : '').'.pdf';

        return $pdf->stream($nombre);
    }

    /**
     * PDF batch de todos los recibos visibles de la corrida.
     */
    public function recibosPdf(Request $request, ReciboLotePdfService $pdfService, $id)
    {
        can('listar-liquidacion-sueldos');

        $liq = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);
        $multiempresa = $request->boolean('multiempresa');

        try {
            $out = $pdfService->generar($liq, $multiempresa);
        } catch (\Throwable $e) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
            }

            return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
                ->with('error', $e->getMessage());
        }

        return response()
            ->download($out['ruta'], $out['nombre'], ['Content-Type' => 'application/pdf'])
            ->deleteFileAfterSend(true);
    }

    /**
     * Analiza importación de nómina confidencial (dry-run JSON).
     */
    public function analizarConfidencial(Request $request, ImportarAuxconfLiquidacionService $import, $id)
    {
        can('importar-liquidacion-confidencial-sueldos');

        $liq = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);

        try {
            $plan = $import->analizar($liq, (string) $request->input('fuente', 'auto'));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        Session::put('liq_confidencial_plan_'.$liq->id, [
            'plan_hash' => $plan['plan_hash'],
            'fuente' => $plan['fuente'],
            'empresa_anita' => $plan['empresa_anita'],
        ]);

        // No enviar lineas_dto al browser (volumen/PII).
        $visible = $plan;
        $visible['detalle'] = array_map(static function ($d) {
            return [
                'legajo' => $d['legajo'],
                'accion' => $d['accion'],
                'lineas' => $d['lineas'],
                'neto' => $d['neto'],
            ];
        }, $plan['detalle'] ?? []);

        return response()->json(['ok' => true, 'plan' => $visible]);
    }

    /**
     * Ejecuta importación confidencial confirmando el hash del dry-run.
     */
    public function ejecutarConfidencial(Request $request, ImportarAuxconfLiquidacionService $import, $id)
    {
        can('importar-liquidacion-confidencial-sueldos');

        $liq = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);

        $hash = (string) $request->input('plan_hash', '');
        $ses = Session::get('liq_confidencial_plan_'.$liq->id);
        if (! is_array($ses) || ($ses['plan_hash'] ?? '') !== $hash || $hash === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Debe analizar primero y confirmar el mismo plan.'], 422);
        }

        /** @var Usuario|null $usuario */
        $usuario = auth()->user();
        if (! $usuario instanceof Usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'Usuario no autenticado.'], 401);
        }

        try {
            $plan = $import->analizar($liq, (string) ($ses['fuente'] ?? 'auto'), (int) ($ses['empresa_anita'] ?? 0) ?: null);
            if (($plan['plan_hash'] ?? '') !== $hash) {
                throw new \RuntimeException('El plan cambió desde el análisis. Vuelva a analizar antes de confirmar.');
            }
            $resultado = $import->ejecutar($liq, $plan, $usuario, $request->boolean('eliminar_ausentes'));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        Session::forget('liq_confidencial_plan_'.$liq->id);

        return response()->json(['ok' => true, 'resultado' => $resultado]);
    }

    /**
     * Depurador: rastro paso a paso del calculo de un empleado en la corrida.
     */
    public function trazar(Request $request, LiquidacionCalculadorService $calculador, $id, $empleadoId)
    {
        can('listar-liquidacion-sueldos');

        $liq = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);
        $emp = Empleado_Sueldos::query()
            ->where('id', $empleadoId)
            ->where('empresa_id', $liq->empresa_id)
            ->firstOrFail();
        if ((bool) $emp->confidencial && ! LiquidacionConfidencialSeguridadSupport::puedeVerConfidencial()) {
            abort(404);
        }

        $traza = $calculador->trazarEmpleadoCompleto($liq, $emp);

        return view('sueldos.liquidacion.trazar', [
            'liq' => $liq,
            'empleado' => $emp,
            'pasos' => $traza['pasos'] ?? [],
            'traza' => $traza,
        ]);
    }

    /**
     * Permisos para elegir liquidación en formularios operativos (reporte definible, etc.).
     */
    private function puedeConsultarLiquidacionOperativa(): bool
    {
        return can('listar-liquidacion-sueldos', false)
            || can('editar-liquidacion-sueldos', false)
            || can('crear-liquidacion-sueldos', false)
            || can('listar-reporte-sueldos-definible', false)
            || can('editar-reporte-sueldos-definible', false)
            || can('actualizar-reporte-sueldos-definible', false)
            || can('crear-reporte-sueldos-definible', false)
            || can('ejecutar-reporte-sueldos-definible', false);
    }

    public function consultaLiquidacion(Request $request)
    {
        if (! $this->puedeConsultarLiquidacionOperativa()) {
            abort(403);
        }

        $consulta = (string) ($request->input('consulta') ?? '');
        $empresaId = $request->filled('empresa_id') ? (int) $request->input('empresa_id') : null;
        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $data = $this->repository->listadoParaConsulta($consulta, $empresaId, $empresaIds !== [] ? $empresaIds : null);
        $puedeAbrirAbm = can('editar-liquidacion-sueldos', false) || can('listar-liquidacion-sueldos', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="6">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $tipoLabel = Liquidacion_Sueldos::TIPOS[$row->tipo] ?? (string) $row->tipo;
                $estadoLabel = Liquidacion_Sueldos::ESTADOS[$row->estado] ?? (string) $row->estado;
                $desc = trim((string) ($row->descripcion ?? ''));
                $nombre = trim(($row->periodo ?? '').($desc !== '' ? ' · '.$desc : '').' ('.$estadoLabel.')');
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="liquidacion_id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="numeroliquidacion">'.e($row->numero).'</td>';
                $output['data'] .= '<td class="descripcionliquidacion">'.e($nombre).'</td>';
                $output['data'] .= '<td class="tipoliquidacion">'.e($tipoLabel).'</td>';
                $output['data'] .= '<td class="empresaliquidacion">'.e(optional($row->empresa)->nombre ?? '').'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultaliquidacion_sueldos">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_liquidacion_sueldos', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td>';
                $output['data'] .= '</tr>';
            }
        }

        return response()->json($output);
    }

    public function leeUnLiquidacionPorNumero($numero, Request $request)
    {
        if (! $this->puedeConsultarLiquidacionOperativa()) {
            abort(403);
        }

        $numeroInt = (int) preg_replace('/\D+/', '', (string) $numero);
        $empresaId = $request->filled('empresa_id') ? (int) $request->input('empresa_id') : null;
        $liq = $this->repository->findPorNumero($numeroInt, $empresaId);
        if ($liq === null) {
            $cantidad = $this->repository->contarPorNumero($numeroInt, $empresaId);
            if ($cantidad > 1 && ($empresaId === null || $empresaId <= 0)) {
                return response()->json([
                    'error' => 'Hay varias liquidaciones con el número '.$numeroInt.' (una por empresa). Elija la empresa y vuelva a validar.',
                    'ambiguo' => true,
                    'cantidad' => $cantidad,
                ], 409);
            }

            return response()->json(['error' => 'Liquidación no encontrada'], 404);
        }

        return response()->json($this->payloadLiquidacionOperativa($liq));
    }

    public function leeLiquidacion($id, Request $request)
    {
        if (! $this->puedeConsultarLiquidacionOperativa()) {
            abort(403);
        }

        $empresaId = $request->filled('empresa_id') ? (int) $request->input('empresa_id') : null;
        $liq = $this->repository->findParaConsulta((int) $id, $empresaId);
        if ($liq === null) {
            return response()->json(['error' => 'Liquidación no encontrada'], 404);
        }

        return response()->json($this->payloadLiquidacionOperativa($liq));
    }

    /**
     * @return array{id:int,numero:int,descripcion:string,periodo:?string,tipo:?string,tipo_label:string,estado:?string,estado_label:string,empresa_id:?int,empresa:?string}
     */
    private function payloadLiquidacionOperativa($liq): array
    {
        $estadoLabel = Liquidacion_Sueldos::ESTADOS[$liq->estado] ?? (string) ($liq->estado ?? '');
        $tipoLabel = Liquidacion_Sueldos::TIPOS[$liq->tipo] ?? (string) ($liq->tipo ?? '');
        $desc = trim((string) ($liq->descripcion ?? ''));
        $empresaNombre = trim((string) (optional($liq->empresa)->nombre ?? ''));
        $nombre = trim(($liq->periodo ?? '').($desc !== '' ? ' · '.$desc : '').($estadoLabel !== '' ? ' ('.$estadoLabel.')' : ''));
        if ($empresaNombre !== '') {
            $nombre = trim($nombre.($nombre !== '' ? ' · ' : '').$empresaNombre);
        }

        return [
            'id' => (int) $liq->id,
            'numero' => (int) $liq->numero,
            'descripcion' => $nombre !== '' ? $nombre : (string) ($liq->descripcion ?? ''),
            'periodo' => $liq->periodo,
            'tipo' => $liq->tipo,
            'tipo_label' => $tipoLabel,
            'estado' => $liq->estado,
            'estado_label' => $estadoLabel,
            'empresa_id' => $liq->empresa_id ? (int) $liq->empresa_id : null,
            'empresa' => $empresaNombre !== '' ? $empresaNombre : optional($liq->empresa)->nombre,
        ];
    }

    private function empresas()
    {
        return $this->empresaRepository->allFiltrado();
    }

    private function motivosegreso()
    {
        return Motivoegreso_Sueldos::query()->orderBy('codigo')->get(['id', 'codigo', 'descripcion', 'clase']);
    }
}
