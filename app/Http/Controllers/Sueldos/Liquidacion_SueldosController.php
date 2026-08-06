<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\LiquidacionSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionLiquidacion_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Models\Sueldos\Motivoegreso_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sueldos\Liquidacion_SueldosRepositoryInterface;
use App\Services\Sueldos\AnitaLiquidacionNovedadSyncService;
use App\Services\Sueldos\LiquidacionCalculadorService;
use App\Services\Sueldos\PlanCuotaLiquidacionService;
use App\Services\Sueldos\ReciboAnexoIIIArmadorService;
use App\Services\Sueldos\ReciboMultiempresaService;
use App\Support\Sueldos\Formula\FormulaException;
use App\Support\Sueldos\LiquidacionSueldosListadoFiltros;
use Illuminate\Http\Request;

class Liquidacion_SueldosController extends Controller
{
    public function __construct(
        private Liquidacion_SueldosRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

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
    public function resultado(Request $request, $id)
    {
        can('listar-liquidacion-sueldos');

        $liq = $this->repository->findOrFail($id);
        $recibos = $liq->recibos()
            ->with(['detalles'])
            ->orderBy('numero_recibo')
            ->paginate(25);

        return view('sueldos.liquidacion.resultado', [
            'liq' => $liq,
            'recibos' => $recibos,
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

        $recibo = $this->reciboDeCorrida($id, $reciboId);
        $liq = $recibo->liquidacion ?? $this->repository->findOrFail($id);
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

        $recibo = $this->reciboDeCorrida($id, $reciboId);
        $liq = $recibo->liquidacion ?? $this->repository->findOrFail($id);
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

    private function reciboDeCorrida($liquidacionId, $reciboId): Liquidacion_Recibo_Sueldos
    {
        return Liquidacion_Recibo_Sueldos::query()
            ->where('liquidacion_id', (int) $liquidacionId)
            ->where('id', (int) $reciboId)
            ->firstOrFail();
    }

    /**
     * Depurador: rastro paso a paso del calculo de un empleado en la corrida.
     */
    public function trazar(Request $request, LiquidacionCalculadorService $calculador, $id, $empleadoId)
    {
        can('listar-liquidacion-sueldos');

        $liq = $this->repository->findOrFail($id);
        $emp = Empleado_Sueldos::findOrFail($empleadoId);

        $pasos = $calculador->trazarEmpleado($liq, $emp);

        return view('sueldos.liquidacion.trazar', [
            'liq' => $liq,
            'empleado' => $emp,
            'pasos' => $pasos,
        ]);
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
