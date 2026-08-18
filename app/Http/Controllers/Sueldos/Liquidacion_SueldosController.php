<?php

namespace App\Http\Controllers\Sueldos;

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
use App\Services\Sueldos\LiquidacionCalculadorService;
use App\Services\Sueldos\PlanCuotaLiquidacionService;
use App\Services\Sueldos\ReciboAnexoIIIArmadorService;
use App\Services\Sueldos\ReciboLotePdfService;
use App\Services\Sueldos\ReciboMultiempresaService;
use App\Support\Sueldos\Formula\FormulaException;
use App\Support\Sueldos\LiquidacionConfidencialSeguridadSupport;
use App\Support\Sueldos\LiquidacionSueldosListadoFiltros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

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

        return view('sueldos.liquidacion.crear', [
            'empresas' => $this->empresas(),
            'motivosegreso' => $this->motivosegreso(),
        ]);
    }

    public function guardar(ValidacionLiquidacion_Sueldos $request)
    {
        can('crear-liquidacion-sueldos');
        $liq = $this->repository->create($request->validated());

        return redirect('sueldos/liquidacion')
            ->with('mensaje', 'Corrida de liquidación N° '.$liq->numero.' creada con éxito');
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
            if (! in_array($liq->estado, $permitidas['reabrir'], true) || $liq->contabilizado) {
                return back()->with('error', 'La corrida no puede reabrirse.');
            }
            $this->repository->cambiarEstado((int) $id, 'calculada');
            // Retrocede el contador de cuotas confirmadas al cerrar.
            $planCuotas->revertir($liq);

            return back()->with('mensaje', 'Corrida reabierta (calculada).');
        }

        if (! isset($permitidas[$destino]) || ! in_array($liq->estado, $permitidas[$destino], true)) {
            return back()->with('error', 'Transición de estado no permitida.');
        }

        $this->repository->cambiarEstado((int) $id, $destino);

        // Planes de cuotas: al cerrar avanza el contador (y finaliza al llegar a N);
        // al anular retrocede lo confirmado y limpia los movimientos de la corrida.
        if ($destino === 'cerrada') {
            $planCuotas->confirmar($liq);
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
        }

        if ($resumen['sin_conceptos']) {
            return redirect('sueldos/liquidacion')
                ->with('error', 'No hay conceptos activos para liquidar. Cargue conceptos antes de calcular.');
        }

        $msg = 'Corrida calculada: '.$resumen['recibos'].' recibo(s). Neto total $ '
            .number_format($resumen['total_neto'], 2, ',', '.');
        $errN = (int) ($resumen['errores_formula_count'] ?? 0);
        if ($errN > 0) {
            $sample = collect($resumen['errores_formula'] ?? [])->take(3)
                ->map(fn ($e) => 'leg.'.$e['legajo'].' conc.'.$e['concepto'])
                ->implode(', ');

            return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
                ->with('mensaje', $msg)
                ->with('error', $errN.' error(es) de fórmula (concepto omitido con importe 0). Ej: '.$sample);
        }

        return redirect()->route('resultado_liquidacion_sueldos', ['id' => $id])
            ->with('mensaje', $msg);
    }

    /**
     * Resultado de la corrida: recibos generados con su detalle.
     */
    public function resultado(Request $request, ReciboMultiempresaService $multi, $id)
    {
        can('listar-liquidacion-sueldos');

        $liq = $this->repository->findOrFail($id);
        LiquidacionConfidencialSeguridadSupport::assertLiquidacionVisible($liq);

        $query = $liq->recibos()
            ->with(['detalles', 'empleado:id,confidencial,nombre']);
        LiquidacionConfidencialSeguridadSupport::aplicarVisibilidadRecibos($query);
        $recibos = $query->orderBy('numero_recibo')->paginate(25);

        $hermanosPorRecibo = $multi->hermanosPorRecibos($recibos->getCollection());
        $totalesVisibles = LiquidacionConfidencialSeguridadSupport::totalesVisiblesCorrida($liq);

        return view('sueldos.liquidacion.resultado', [
            'liq' => $liq,
            'recibos' => $recibos,
            'hermanosPorRecibo' => $hermanosPorRecibo,
            'totalesVisibles' => $totalesVisibles,
            'puedeImportarConfidencial' => LiquidacionConfidencialSeguridadSupport::puedeImportarConfidencial()
                && $liq->esEditable(),
        ]);
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
        $data = $this->repository->listadoParaConsulta($consulta, $empresaId);
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
        $nombre = trim(($liq->periodo ?? '').($desc !== '' ? ' · '.$desc : '').($estadoLabel !== '' ? ' ('.$estadoLabel.')' : ''));

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
            'empresa' => optional($liq->empresa)->nombre,
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
