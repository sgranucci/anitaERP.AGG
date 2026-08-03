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
use App\Exports\Contable\AsientoExport;
use App\Models\Contable\Asiento;
use App\Services\Contable\AsientoAprobacionService;
use App\Support\Contable\AsientoCuentaUsuarioSupport;
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

		if (!$hayAsientos)
			$this->asientoRepository->sincronizarConAnita();

        // Memoria de filtros: si el request trae filtros los usa y persiste; si vuelve
        // desde editar (URL sin parámetros) restaura el último filtro de la sesión.
        if ($request->has('busqueda') || $request->has('empresa_id'))
        {
            $busqueda = $request->input('busqueda');
            $empresaId = (int) $request->input('empresa_id', 0);
            session(['asiento_listado_filtros' => ['busqueda' => $busqueda, 'empresa_id' => $empresaId]]);
        }
        else
        {
            $filtros = session('asiento_listado_filtros', []);
            $busqueda = $filtros['busqueda'] ?? null;
            $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        }

		$asientos = $this->asientoQuery->leeAsiento($busqueda, true, $empresaId);

        $datas = [
            'asientos' => $asientos,
            'busqueda' => $busqueda,
            'empresa_id' => $empresaId,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'filtrosQuery' => array_filter([
                'busqueda' => $busqueda,
                'empresa_id' => $empresaId > 0 ? $empresaId : null,
            ], fn ($v) => $v !== null && $v !== ''),
        ];

        return view('contable.asiento.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-asiento'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        // Los filtros llegan por query params (busqueda, empresa_id); se conserva el
        // segmento de ruta {busqueda} por compatibilidad con enlaces antiguos.
        $busqueda = $request->input('busqueda', $busqueda);
        $empresaId = (int) $request->input('empresa_id', 0);

        switch($formato)
        {
        case 'PDF':
            $asientos = $this->asientoQuery->leeAsiento($busqueda, false, $empresaId);

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
                        ->parametros($busqueda, $empresaId)
                        ->download('asiento.xlsx');
            break;

        case 'CSV':
            return (new AsientoExport($this->asientoQuery))
                        ->parametros($busqueda, $empresaId)
                        ->download('asiento.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        return redirect()->route('asiento');
    }

    public function imprimirPdf($id)
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

        return $pdf->download($nombreArchivo);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-asiento');

        $tipoasiento_query = $this->tipoasientoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $cuentacontable_query = $this->cuentacontableRepository->all();
        $centrocosto_query = $this->centrocostoRepository->all();
        $usuarioTieneRestriccionCuentas = AsientoCuentaUsuarioSupport::usuarioTieneRestriccionCuentas((int) auth()->id());
        
        return view('contable.asiento.crear', compact('tipoasiento_query', 'moneda_query', 
                                                'empresa_query', 'cuentacontable_query',
                                                'centrocosto_query', 'usuarioTieneRestriccionCuentas'));
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

        DB::beginTransaction();
        try
        {
            $asiento = $this->asientoRepository->create($data);

            if ($asiento == 'Error')
                throw new Exception('Error en grabacion anita.');

                // Guarda tablas asociadas
            if ($asiento)
            {
                $asiento_movimiento = $this->asiento_movimientoRepository->create($request->all(), $asiento->id);
                $asiento_archivo = $this->asiento_archivoRepository->create($request, $asiento->id);
            }

            DB::commit();

            if ($evaluacion['requiere_aprobacion']) {
                $this->asientoAprobacionService->enviarMailAprobacion($asiento->fresh());
            }
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json(['errores' => $e->getMessage()]);
        }

        if ($evaluacion['requiere_aprobacion']) {
            return response()->json([
                'mensaje' => 'pendiente',
                'asiento_id' => $asiento->id,
                'numeroasiento' => $asiento->numeroasiento,
            ]);
        }

        return response()->json(['mensaje' => 'ok']);
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
    public function editar($id)
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

        return view('contable.asiento.editar', compact('data',
                                                    'asiento_referencias',
                                                    'tipoasiento_query', 'moneda_query',
                                                    'empresa_query', 'cuentacontable_query',
                                                    'centrocosto_query'));
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
        
        DB::beginTransaction();
        try
        {
            $existente = $this->asientoRepository->find($id);
            $data = AsientoReferenciaAnitaSupport::conservarFksOrigenProceso($request->all(), $existente);
            $data = AsientoReferenciaAnitaSupport::aplicarAPayload($data);
            // Graba asiento
            $asiento = $this->asientoRepository->update($data, $id);

            if ($asiento === 'Error')
                throw new Exception('Error en grabacion anita.');

                // Graba movimientos del asiento
            $this->asiento_movimientoRepository->update($request->all(), $id);

            // Graba archivos del asiento
            $this->asiento_archivoRepository->update($request, $id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['errores' => $e->getMessage()];
        }
        return ['mensaje' => 'ok'];
    }

    // Copiar o copia revirtiendo asiento

    public function copiarAsiento(Request $request)
    {
        $id = $request->id;
        $fechacopia = $request->fechacopia;
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

        // Graba el asiento
        DB::beginTransaction();
        try
        {
            $asiento = $this->asientoRepository->create($data);

            if ($asiento == 'Error')
                throw new Exception('Error en grabacion anita.');

            // Guarda tablas asociadas
            if ($asiento)
            {
                $asiento_movimiento = $this->asiento_movimientoRepository->create($datas, $asiento->id);
                
                foreach($nombrearchivos as $archivo)
                    $asiento_archivo = $this->asiento_archivoRepository->copiaArchivo($id, $archivo, $asiento->id);
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
}
