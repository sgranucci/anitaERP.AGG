<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionAsiento;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\Asiento_ArchivoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Queries\Contable\AsientoQueryInterface;
use App\Exports\Contable\AsientoDetalleExport;
use App\Exports\Contable\AsientoExport;
use App\Models\Contable\Asiento;
use App\Models\Contable\Configuracion_AsientoContable;
use App\Services\Contable\AsientoAprobacionService;
use App\Support\Configuracion\AnitaSyncIndexSupport;
use App\Support\Contable\AsientoBalanceSupport;
use App\Support\Contable\AsientoCuentaUsuarioSupport;
use App\Support\Contable\AsientoListadoFiltros;
use App\Support\Contable\AsientoOrigenProcesoSupport;
use App\Support\Contable\AsientoReferenciaAnitaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;
use DB;

class AsientoController extends Controller
{
    private $asientoRepository;
    private $asiento_movimientoRepository;
    private $asiento_archivoRepository;
    private $cuentacontableRepository;
    private $tipoasientoRepository;
    private $centrocostoRepository;
    private $monedaRepository;
    private $empresaRepository;
    private $asientoQuery;
    private $asientoAprobacionService;

	public function __construct(AsientoRepositoryInterface $asientorepository,
                                Asiento_MovimientoRepositoryInterface $asiento_movimientorepository,
                                Asiento_ArchivoRepositoryInterface $asiento_archivorepository,
                                MonedaRepositoryInterface $monedarepository,
                                TipoasientoRepositoryInterface $tipoasientorepository,
                                CuentacontableRepositoryInterface $cuentacontablerepository,
                                CentrocostoRepositoryInterface $centrocostorepository,
                                EmpresaRepositoryInterface $empresarepository,
                                AsientoQueryInterface $asientoquery,
                                AsientoAprobacionService $asientoAprobacionService
                                )
    {
        $this->asientoRepository = $asientorepository;
        $this->asiento_movimientoRepository = $asiento_movimientorepository;
        $this->asiento_archivoRepository = $asiento_archivorepository;
        $this->cuentacontableRepository = $cuentacontablerepository;
        $this->tipoasientoRepository = $tipoasientorepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->monedaRepository = $monedarepository;
        $this->empresaRepository = $empresarepository;
        $this->asientoQuery = $asientoquery;
        $this->asientoAprobacionService = $asientoAprobacionService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-asiento');
		
        $hayAsientos = $this->asientoQuery->first();

		if (! $hayAsientos && AnitaSyncIndexSupport::autoImportHabilitado()) {
			$this->asientoRepository->sincronizarConAnita();
		}

        $filtros = $this->resolverFiltrosListado($request);
        // Memoria de filtros: si el request trae filtros los usa y persiste; si vuelve
        // desde editar (URL sin parámetros) restaura el último filtro de la sesión.
        if (
            $request->has('filtro_valor')
            || $request->has('busqueda')
            || $request->filled('filtro_modo')
            || $request->filled('filtro_campo')
            || $request->filled('filtro_operador')
            || $request->boolean('filtro_busqueda_rapida')
            || $request->filled('empresa_id')
            || $request->has('empresa_todas')
            || $request->input('empresa_scope') === 'todas'
        ) {
            session(['asiento_listado_filtros' => $filtros]);
        } else {
            $sesion = session('asiento_listado_filtros');
            if (is_array($sesion) && $sesion !== []) {
                $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
                $filtros = AsientoListadoFiltros::desdeSesion(
                    $sesion,
                    $empresaDefault ? (int) $empresaDefault : null
                );
            }
        }

		$asientos = $this->asientoQuery->leeAsiento($filtros, true);

        $datas = [
            'asientos' => $asientos,
            'busqueda' => $filtros['valor'] ?? '',
            'filtros' => $filtros,
            'camposFiltro' => AsientoListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'filtrosQuery' => AsientoListadoFiltros::paraQueryString($filtros),
        ];

        return view('contable.asiento.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-asiento'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch($formato)
        {
        case 'PDF':
            $asientos = $this->asientoQuery->leeAsiento($filtros, false);

            $view =  \View::make('contable.asiento.listado', compact('asientos'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_asiento';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new AsientoExport($this->asientoQuery))
                        ->parametros($filtros)
                        ->download('asiento.xlsx');
            break;

        case 'CSV':
            return (new AsientoExport($this->asientoQuery))
                        ->parametros($filtros)
                        ->download('asiento.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        return redirect()->route('asiento', AsientoListadoFiltros::paraQueryString($filtros));
    }

    public function imprimirPdf(Request $request, $id)
    {
        if (! can('listar-asiento', false) && ! can('editar-asiento', false)) {
            return redirect()->route('inicio')->with('mensaje', 'No tienes permisos para imprimir el asiento');
        }

        $data = $this->asientoRepository->find((int) $id);

        $html = view('contable.asiento.pdf', compact('data'))->render();

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'portrait');
        $pdf->loadHTML($html);

        $nombreArchivo = 'Asiento_'.preg_replace('/[^\w\-]+/', '_', (string) $data->numeroasiento).'.pdf';

        if ($request->boolean('inline')) {
            return $pdf->stream($nombreArchivo);
        }

        return $pdf->download($nombreArchivo);
    }

    public function imprimirExcel($id)
    {
        if (! can('listar-asiento', false) && ! can('editar-asiento', false)) {
            return redirect()->route('inicio')->with('mensaje', 'No tienes permisos para exportar el asiento');
        }

        $data = $this->asientoRepository->find((int) $id);
        $nombreArchivo = 'Asiento_'.preg_replace('/[^\w\-]+/', '_', (string) $data->numeroasiento).'.xlsx';

        return (new AsientoDetalleExport())
            ->parametros($data)
            ->download($nombreArchivo);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear(Request $request)
    {
        can('crear-asiento');

        $tipoasiento_query = $this->tipoasientoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $cuentacontable_query = $this->cuentacontableRepository->all();
        $centrocosto_query = $this->centrocostoRepository->all();
        $usuarioTieneRestriccionCuentas = AsientoCuentaUsuarioSupport::usuarioTieneRestriccionCuentas((int) auth()->id());
        $filtrosQuery = AsientoListadoFiltros::paraQueryString($this->resolverFiltrosListado($request));
        
        return view('contable.asiento.crear', compact('tipoasiento_query', 'moneda_query', 
                                                'empresa_query', 'cuentacontable_query',
                                                'centrocosto_query', 'usuarioTieneRestriccionCuentas',
                                                'filtrosQuery'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionAsiento $request)
    {
        session(['empresa_id' => $request->empresa_id]);
        session(['tipoasiento_id' => $request->tipoasiento_id]);

        $cuentacontableIds = $request->input('cuentacontable_ids', []);
        $evaluacion = $this->asientoAprobacionService->evaluarCuentas(
            (int) auth()->id(),
            is_array($cuentacontableIds) ? $cuentacontableIds : []
        );

        if ($evaluacion['requiere_aprobacion'] && ! $request->boolean('confirmar_pendiente_aprobacion')) {
            return response()->json([
                'requiere_aprobacion' => true,
                'cuentas_detalle' => $evaluacion['cuentas_detalle'],
                'mensaje' => 'Hay cuentas fuera de su lista autorizada. Debe solicitar aprobación de contaduría.',
            ]);
        }

        $data = $request->all();
        $data = AsientoReferenciaAnitaSupport::aplicarAPayload($data);
        if ($evaluacion['requiere_aprobacion']) {
            $data['estado_aprobacion'] = Asiento::ESTADO_APROBACION_PENDIENTE;
            $data['cuentas_no_autorizadas'] = json_encode($evaluacion['cuentas_no_autorizadas']);
        }

        try {
            AsientoBalanceSupport::assertValidoParaCrudAsiento($data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['errores' => $e->getMessage()]);
        }

        // ERP primero (omitir Anita); sync ctamov al final dentro de la TX.
        // Evita huérfanos desbalanceados en Anita si falla el alta de movimientos (incidente 363199-363201).
        $data['omitir_anita'] = true;

        DB::beginTransaction();
        try
        {
            $asiento = $this->asientoRepository->create($data);

            if ($asiento == 'Error')
                throw new Exception('Error en grabacion anita.');

            if ($asiento)
            {
                $this->asiento_movimientoRepository->create($request->all(), $asiento->id);
                $this->asiento_archivoRepository->create($request, $asiento->id);
            }

            if (
                $asiento
                && ($data['estado_aprobacion'] ?? Asiento::ESTADO_APROBACION_CONFIRMADO)
                    === Asiento::ESTADO_APROBACION_CONFIRMADO
            ) {
                $fresh = $this->asientoRepository->find($asiento->id);
                $payloadAnita = $this->asientoRepository->armarPayloadAnitaDesdeModelo($fresh);
                foreach (['tipo', 'letra', 'sucursal', 'nro', 'sistema_ctav', 'ctav_o_compra', 'path_sistema'] as $claveAnita) {
                    if (array_key_exists($claveAnita, $data)) {
                        $payloadAnita[$claveAnita] = $data[$claveAnita];
                    }
                }
                $this->asientoRepository->sincronizarCtamovAnita($payloadAnita);
            }

            DB::commit();

            if ($evaluacion['requiere_aprobacion']) {
                $this->asientoAprobacionService->enviarMailAprobacion($asiento->fresh());
            }
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json(['errores' => $e->getMessage()]);
        }

        $urlImpresion = null;
        if (can('listar-asiento', false) || can('editar-asiento', false)) {
            $urlImpresion = Configuracion_AsientoContable::vigente()->urlImpresionAlta((int) $asiento->id);
        }

        if ($evaluacion['requiere_aprobacion']) {
            return response()->json([
                'mensaje' => 'pendiente',
                'asiento_id' => $asiento->id,
                'numeroasiento' => $asiento->numeroasiento,
                'url_impresion_asiento' => $urlImpresion,
            ]);
        }

        return response()->json([
            'mensaje' => 'ok',
            'asiento_id' => $asiento->id,
            'numeroasiento' => $asiento->numeroasiento,
            'url_impresion_asiento' => $urlImpresion,
        ]);
	}

    public function validarCuentasUsuario(Request $request)
    {
        can('crear-asiento');

        $ids = $request->input('cuentacontable_ids', []);
        $evaluacion = $this->asientoAprobacionService->evaluarCuentas(
            (int) auth()->id(),
            is_array($ids) ? $ids : []
        );

        return response()->json($evaluacion);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar(Request $request, $id)
    {
        if (! can('listar-asiento', false) && ! can('editar-asiento', false)) {
            return redirect()->route('inicio')->with('mensaje', 'No tienes permisos para consultar el asiento');
        }

		$data = $this->asientoRepository->find($id);
        $asiento_referencias = AsientoReferenciaAnitaSupport::etiquetasDesdeAsiento($data);

        $tipoasiento_query = $this->tipoasientoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $cuentacontable_query = $this->cuentacontableRepository->all();
        $centrocosto_query = $this->centrocostoRepository->all();
        $filtrosQuery = AsientoListadoFiltros::paraQueryString($this->resolverFiltrosListado($request));

        return view('contable.asiento.editar', compact('data',
                                                    'asiento_referencias',
                                                    'tipoasiento_query', 'moneda_query',
                                                    'empresa_query', 'cuentacontable_query',
                                                    'centrocosto_query', 'filtrosQuery'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionAsiento $request, $id)
    {
        can('actualizar-asiento');

        session(['empresa_id' => $request->empresa_id]);
        session(['tipoasiento_id' => $request->tipoasiento_id]);

        $dataRequest = $request->all();
        try {
            AsientoBalanceSupport::assertValidoParaCrudAsiento($dataRequest);
        } catch (\InvalidArgumentException $e) {
            return ['errores' => $e->getMessage()];
        }
        
        DB::beginTransaction();
        try
        {
            $existente = $this->asientoRepository->find($id);
            $data = AsientoReferenciaAnitaSupport::conservarFksOrigenProceso($dataRequest, $existente);
            $data = AsientoReferenciaAnitaSupport::aplicarAPayload($data);
            // Cabecera ERP primero; Anita se sincroniza al final desde movimientos ya grabados.
            $data['omitir_anita'] = true;
            $asiento = $this->asientoRepository->update($data, $id);

            if ($asiento === 'Error')
                throw new Exception('Error en grabacion anita.');

            // Graba movimientos del asiento
            $this->asiento_movimientoRepository->update($request->all(), $id);

            // Graba archivos del asiento
            $this->asiento_archivoRepository->update($request, $id);

            $fresh = $this->asientoRepository->find($id);
            if (
                ($fresh->estado_aprobacion ?? Asiento::ESTADO_APROBACION_CONFIRMADO)
                === Asiento::ESTADO_APROBACION_CONFIRMADO
            ) {
                $payloadAnita = $this->asientoRepository->armarPayloadAnitaDesdeModelo($fresh);
                foreach (['tipo', 'letra', 'sucursal', 'nro', 'sistema_ctav', 'ctav_o_compra', 'path_sistema'] as $claveAnita) {
                    if (array_key_exists($claveAnita, $data)) {
                        $payloadAnita[$claveAnita] = $data[$claveAnita];
                    }
                }
                $this->asientoRepository->sincronizarCtamovAnita($payloadAnita);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            // delete+reinsert en Anita no es atómico: si falló a mitad, intentar restaurar
            // ctamov desde el asiento ERP (ya revertido) para no dejar el mayor sin el asiento.
            try {
                $restaurar = $this->asientoRepository->find($id);
                if (
                    $restaurar
                    && ($restaurar->estado_aprobacion ?? Asiento::ESTADO_APROBACION_CONFIRMADO)
                        === Asiento::ESTADO_APROBACION_CONFIRMADO
                    && ! empty($restaurar->numeroasiento)
                ) {
                    $this->asientoRepository->sincronizarCtamovAnita(
                        $this->asientoRepository->armarPayloadAnitaDesdeModelo($restaurar)
                    );
                }
            } catch (\Throwable $restoreEx) {
                \Illuminate\Support\Facades\Log::warning('asiento_ctamov.restaurar_tras_fallo_update', [
                    'asiento_id' => $id,
                    'error_original' => $e->getMessage(),
                    'error_restore' => $restoreEx->getMessage(),
                ]);
            }

            return ['errores' => $e->getMessage()];
        }
        return ['mensaje' => 'ok'];
    }

    // Copiar o copia revirtiendo asiento

    public function revertirAsiento(Request $request)
    {
        $request->merge(['revierte' => 1]);

        return $this->copiarAsiento($request);
    }

    public function copiarAsiento(Request $request)
    {
        can('crear-asiento');

        $id = $request->id;
        $fechacopia = $request->input('fechacopia', $request->input('fecha'));
        $flRevierte = false;

        if (isset($request->revierte))
            $flRevierte = true;

        $origen = $this->asientoRepository->find($id);
        $data = $origen->toArray();

        if ($flRevierte && AsientoOrigenProcesoSupport::tieneOrigenProceso($origen)) {
            return [
                'errores' => AsientoOrigenProcesoSupport::mensajeBloqueo($origen, 'revertir'),
            ];
        }

        // Copia/reversión desde ABM no hereda FKs de proceso (evita vínculos fantasma).
        $data = AsientoOrigenProcesoSupport::limpiarFksOrigenEnPayload($data);

        $centrocosto_ids = [];
        $debes = [];
        $haberes = [];
        $cuentacontable_ids = [];
        $observaciones = [];
        $moneda_ids = [];
        $cotizaciones = [];
        foreach ($data['asiento_movimientos'] as $movimiento)
        {
            $centrocosto_ids[] = $movimiento['centrocosto_id'];

            if ($flRevierte)
            {
                if ($movimiento['monto'] >= 0)
                {
                    $haberes[] = $movimiento['monto'];
                    $debes[] = 0;
                }
                else
                {
                    $debes[] = abs($movimiento['monto']);
                    $haberes[] = 0;
                }
            }
            else
            {
                if ($movimiento['monto'] >= 0)
                {
                    $debes[] = $movimiento['monto'];
                    $haberes[] = 0;
                }
                else
                {
                    $haberes[] = abs($movimiento['monto']);
                    $debes[] = 0;
                }
            }

            $cuentacontable_ids[] = $movimiento['cuentacontable_id'];
            $observaciones[] = $movimiento['observacion'];
            $moneda_ids[] = $movimiento['moneda_id'];
            $cotizaciones[] = $movimiento['cotizacion'];
        }
        $nombrearchivos = [];
        foreach ($data['asiento_archivos'] as $archivo) 
            $nombrearchivos[] = $archivo['nombrearchivo'];

        $datas = ['centrocosto_ids' => $centrocosto_ids,
                    'cuentacontable_ids' => $cuentacontable_ids,
                    'moneda_ids' => $moneda_ids,
                    'observaciones' => $observaciones,
                    'cotizaciones' => $cotizaciones,
                    'debes' => $debes,
                    'haberes' => $haberes
                    ];

        // Modifica la observacion
        $data['observacion'] = ($flRevierte ? 'Revierte asiento ' : 'Copiado de ').$data['numeroasiento'].' '.$data['observacion'];

        if (! empty($fechacopia)) {
            $data['fecha'] = $fechacopia;
        }

        $data['alcance_cierre_contable'] = \App\Support\Contable\PeriodoContableCierreSupport::ALCANCE_CONTABLE;

        $payloadBalance = array_merge($data, $datas);
        try {
            AsientoBalanceSupport::assertValidoParaCrudAsiento($payloadBalance);
        } catch (\InvalidArgumentException $e) {
            return ['errores' => $e->getMessage()];
        }

        // Graba el asiento
        DB::beginTransaction();
        try
        {
            $data['omitir_anita'] = true;
            $asiento = $this->asientoRepository->create($data);

            if ($asiento == 'Error')
                throw new Exception('Error en grabacion anita.');

            // Guarda tablas asociadas
            if ($asiento)
            {
                $this->asiento_movimientoRepository->create($datas, $asiento->id);

                foreach($nombrearchivos as $archivo)
                    $this->asiento_archivoRepository->copiaArchivo($id, $archivo, $asiento->id);

                $fresh = $this->asientoRepository->find($asiento->id);
                $payloadAnita = $this->asientoRepository->armarPayloadAnitaDesdeModelo($fresh);
                $this->asientoRepository->sincronizarCtamovAnita($payloadAnita);
            }

            DB::commit();

            return ['asiento_id' => $asiento->id, 'numeroasiento' => $asiento->numeroasiento];

        } catch (\Exception $e) {
            DB::rollback();

            // Borra el asiento creado

            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-asiento');

        $asiento = Asiento::query()->find($id);
        if ($asiento && AsientoOrigenProcesoSupport::tieneOrigenProceso($asiento)) {
            $mensaje = AsientoOrigenProcesoSupport::mensajeBloqueo($asiento, 'borrar');
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ng', 'errores' => $mensaje]);
            }

            return redirect('contable/asiento')->with('mensaje_error', $mensaje);
        }

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->asientoRepository->delete($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            if ($this->asientoRepository->delete($id))
                $mensaje = 'Asiento borrado con éxito';
            else 	
                $mensaje = 'error';

            return redirect('contable/asiento')->with('mensaje', $mensaje);
        }
    }

    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;

        return AsientoListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            $empresaDefault ? (int) $empresaDefault : null
        );
    }
}
