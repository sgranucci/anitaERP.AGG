<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionIngresoEgreso;
use App\Repositories\Caja\Caja_MovimientoRepositoryInterface;
use App\Repositories\Caja\Tipotransaccion_CajaRepositoryInterface;
use App\Repositories\Caja\MediopagoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Caja\CajaRepositoryInterface;
use App\Repositories\Caja\ConceptogastoRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Caja\ChequeraRepositoryInterface;
use App\Repositories\Caja\ChequeRepositoryInterface;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Repositories\Compras\Tipotransaccion_CompraRepositoryInterface;
use App\Repositories\Configuracion\CondicionivaRepositoryInterface;
use App\Models\Caja\Cheque;
use App\Models\Compras\Concepto_Ivacompra;
use App\Services\Caja\IngresoEgresoComprobanteIvaPdfIaService;
use App\Services\Caja\IngresoEgresoComprobanteIvaService;
use App\Services\Caja\IngresoEgresoService;
use App\Support\Compras\ComprobanteProveedorTipoTesoreria;
use App\Support\Caja\IngresoEgresoComprobanteIvaValidacionSupport;
use App\Queries\Caja\Caja_MovimientoQueryInterface;
use App\Exports\Caja\Caja_MovimientoExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;
use DB;

class IngresoEgresoController extends Controller
{
    private $caja_movimientoRepository;
    private $caja_movimiento_cuentacajaRepository;
    private $caja_movimiento_estadoRepository;
    private $caja_movimiento_archivoRepository;
    private $tipotransaccion_cajaRepository;
    private $conceptogastoRepository;
    private $cuentacajaRepository;
    private $monedaRepository;
    private $empresaRepository;
    private $cuentacontableRepository;
    private $centrocostoRepository;
    private $caja_movimientoQuery;
    private $ingresoegresoService;
    private $cajaRepository;
    private $chequeraRepository;
    private $chequeRepository;
    private $tipotransaccionCompraRepository;
    private $conceptoIvacompraRepository;
    private $condicionivaRepository;
    private $comprobanteIvaService;
    private $comprobanteIvaPdfIaService;

	public function __construct(Caja_MovimientoRepositoryInterface $caja_movimientorepository,
                                Tipotransaccion_CajaRepositoryInterface $tipotransaccion_cajarepository,
                                ConceptogastoRepositoryInterface $conceptogastorepository,
                                CuentacajaRepositoryInterface $cuentacajarepository,
                                MonedaRepositoryInterface $monedarepository,
                                EmpresaRepositoryInterface $empresarepository,
                                CuentacontableRepositoryInterface $cuentacontablerepository,
                                CentroCostoRepositoryInterface $centrocostorepository,
                                Caja_MovimientoQueryInterface $caja_movimientoquery,
                                IngresoEgresoService $ingresoegresoservice,
                                CajaRepositoryInterface $cajarepository,
                                ChequeraRepositoryInterface $chequerarepository,
                                ChequeRepositoryInterface $chequeRepository,
                                Tipotransaccion_CompraRepositoryInterface $tipotransaccionCompraRepository,
                                Concepto_IvacompraRepositoryInterface $conceptoIvacompraRepository,
                                CondicionivaRepositoryInterface $condicionivaRepository,
                                IngresoEgresoComprobanteIvaService $comprobanteIvaService,
                                IngresoEgresoComprobanteIvaPdfIaService $comprobanteIvaPdfIaService,
                                )
    {
        $this->caja_movimientoRepository = $caja_movimientorepository;
        $this->tipotransaccion_cajaRepository = $tipotransaccion_cajarepository;
        $this->conceptogastoRepository = $conceptogastorepository;
        $this->cuentacajaRepository = $cuentacajarepository;
        $this->monedaRepository = $monedarepository;
        $this->empresaRepository = $empresarepository;
        $this->cuentacontableRepository = $cuentacontablerepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->caja_movimientoQuery = $caja_movimientoquery;
        $this->ingresoegresoService = $ingresoegresoservice;
        $this->cajaRepository = $cajarepository;
        $this->chequeraRepository = $chequerarepository;
        $this->chequeRepository = $chequeRepository;
        $this->tipotransaccionCompraRepository = $tipotransaccionCompraRepository;
        $this->conceptoIvacompraRepository = $conceptoIvacompraRepository;
        $this->condicionivaRepository = $condicionivaRepository;
        $this->comprobanteIvaService = $comprobanteIvaService;
        $this->comprobanteIvaPdfIaService = $comprobanteIvaPdfIaService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-ingresos-egresos-caja');
		
        $hayMovimientosCaja = $this->caja_movimientoQuery->first();

		if (!$hayMovimientosCaja)
			$this->caja_movimientoRepository->sincronizarConAnita();

        $busqueda = $request->busqueda;

        $caja_movimiento = $this->caja_movimientoQuery->leeCaja_Movimiento($busqueda, 0, true);

        $datas = ['caja_movimiento' => $caja_movimiento, 'busqueda' => $busqueda];

        return view('caja.ingresoegreso.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-ingresos-egresos-caja'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $caja_movimiento = $this->caja_movimientoQuery->leeCaja_Movimiento($busqueda, 0, false);

            $view =  \View::make('caja.ingresoegreso.listado', compact('caja_movimiento'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_caja_movimiento';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new Caja_MovimientoExport($this->caja_movimientoQuery))
                        ->parametros($busqueda)
                        ->download('caja_movimiento.xlsx');
            break;

        case 'CSV':
            return (new Caja_MovimientoExport($this->caja_movimientoQuery))
                        ->parametros($busqueda)
                        ->download('caja_movimiento.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['caja_movimiento' => $caja_movimiento, 'busqueda' => $busqueda];

		return view('caja.ingresoegreso.indexp', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear($caja_id = null)
    {
        can('crear-ingresos-egresos-caja');

        $tipotransaccion_caja_query = $this->tipotransaccion_cajaRepository->all();
        $conceptogasto_query = $this->conceptogastoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $cuentacaja_query = $this->cuentacajaRepository->all();
        $cuentacontable_query = $this->cuentacontableRepository->all();
        $centrocosto_query = $this->centrocostoRepository->all();
        $chequera_query = $this->chequeraRepository->all();
        $caracter_enum = Cheque::$enumCaracter;

        $nombreCaja = '';
        $origen = 'ingresoegreso';
        if (isset($caja_id))
        {
            $caja = $this->cajaRepository->find($caja_id);

            if ($caja)
                $nombreCaja = $caja->nombre;

            $origen = 'movimientocaja';
        }
        return view('caja.ingresoegreso.crear', array_merge(
            compact('tipotransaccion_caja_query', 'moneda_query',
                'conceptogasto_query',
                'empresa_query', 'cuentacaja_query', 'cuentacontable_query',
                'centrocosto_query', 'chequera_query', 'caracter_enum',
                'caja_id', 'nombreCaja', 'origen'),
            $this->datosComprobantesIva(null),
        ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionIngresoEgreso $request)
    {
        session(['empresa_id' => $request->empresa_id]);

		return $this->ingresoegresoService->guardaIngresoEgreso($request);
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id, $origen = null)
    {
        can('editar-ingresos-egresos-caja');

        if (!isset($origen))
            $origen = 'ingresoegreso';

        $data = $this->caja_movimientoRepository->find($id);

        $tipotransaccion_caja_query = $this->tipotransaccion_cajaRepository->all();
        $conceptogasto_query = $this->conceptogastoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $cuentacaja_query = $this->cuentacajaRepository->all();
        $cuentacontable_query = $this->cuentacontableRepository->all();
        $centrocosto_query = $this->centrocostoRepository->all();
        $chequera_query = $this->chequeraRepository->all();
        $caracter_enum = Cheque::$enumCaracter;
        $caja_id = $data->caja_id;

        $nombreCaja = '';
        if (isset($caja_id))
        {
            $caja = $this->cajaRepository->find($caja_id);

            if ($caja)
                $nombreCaja = $caja->nombre;
        }

        return view('caja.ingresoegreso.editar', array_merge(
            compact('data',
                'tipotransaccion_caja_query', 'moneda_query',
                'conceptogasto_query',
                'empresa_query', 'cuentacaja_query', 'cuentacontable_query',
                'centrocosto_query', 'chequera_query', 'caracter_enum',
                'caja_id', 'nombreCaja', 'origen'),
            $this->datosComprobantesIva((int) $id),
        ));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionIngresoEgreso $request, $id)
    {
        can('actualizar-ingresos-egresos-caja');

        session(['empresa_id' => $request->empresa_id]);
        
        return $this->ingresoegresoService->actualizaIngresoEgreso($request, $id);
    }

    // Copiar o copia revirtiendo ingreso egreso

    public function copiarIngresoEgreso(Request $request)
    {
        return $this->ingresoegresoService->copiarIngresoEgreso($request);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id, $origen = null)
    {
        can('borrar-ingresos-egresos-caja');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->caja_movimientoRepository->delete($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            if ($this->caja_movimientoRepository->delete($id))
                $mensaje = 'Ingreso Egreso borrado con éxito';
            else 	
                $mensaje = 'error';

            if ($origen == 'movimientocaja')
                return redirect('caja/movimientocaja')->with('mensaje', $mensaje);

            return redirect('caja/ingresoegreso')->with('mensaje', $mensaje);
        }
    }

    public function generaAsientoContable(Request $request)
    {
        return $this->ingresoegresoService->generaAsientoContable($request->all());
    }

    public function buscarCheque(Request $request)
    {
        can('listar-ingresos-egresos-caja', false);

        $empresaId = (int) $request->input('empresa_id');
        $numero = trim((string) $request->input('numerocheque', ''));
        $bancoId = (int) $request->input('banco_id', 0);

        if ($empresaId <= 0 || $numero === '') {
            return response()->json(['mensaje' => 'ng']);
        }

        $query = Cheque::query()
            ->where('empresa_id', $empresaId)
            ->where('numerocheque', $numero)
            ->whereNotIn('estado', ['A']);

        if ($bancoId > 0) {
            $query->where('banco_id', $bancoId);
        }

        $cheque = $query->with('bancos')->with('monedas')->with('cuentacajas')->first();
        if ($cheque === null) {
            return response()->json(['mensaje' => 'ng']);
        }

        return response()->json([
            'mensaje' => 'ok',
            'cheque' => [
                'id' => $cheque->id,
                'origen' => $cheque->origen,
                'numerocheque' => $cheque->numerocheque,
                'monto' => $cheque->monto,
                'moneda_id' => $cheque->moneda_id,
                'cotizacion' => $cheque->cotizacion,
                'fechapago' => $cheque->fechapago,
                'banco_id' => $cheque->banco_id,
                'banco' => $cheque->bancos->nombre ?? '',
                'cuentacaja_id' => $cheque->cuentacaja_id,
            ],
        ]);
    }

    public function previewAsientoComprobanteIva(Request $request)
    {
        can('crear-ingresos-egresos-caja', false);
        can('editar-ingresos-egresos-caja', false);

        $payload = json_decode((string) $request->input('comprobante_json', '{}'), true);
        if (! is_array($payload)) {
            return response()->json(['mensaje' => 'ng', 'error' => 'JSON inválido']);
        }

        $empresaId = (int) $request->input('empresa_id', $payload['empresa_id'] ?? 0);

        return response()->json(array_merge(
            ['mensaje' => 'ok'],
            $this->comprobanteIvaService->previewAsientoComprobante($payload, $empresaId),
        ));
    }

    public function previewPdfComprobanteIva(Request $request)
    {
        can('crear-ingresos-egresos-caja', false);
        can('editar-ingresos-egresos-caja', false);

        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:20480',
            'empresa_id' => 'required|integer|min:1',
        ]);

        try {
            $resultado = $this->comprobanteIvaPdfIaService->preview(
                $request->file('pdf'),
                (int) $request->input('empresa_id'),
            );

            return response()->json(array_merge(['mensaje' => 'ok'], $resultado));
        } catch (\Throwable $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()]);
        }
    }

    public function validarTotalesComprobantesIva(Request $request)
    {
        can('crear-ingresos-egresos-caja', false);
        can('editar-ingresos-egresos-caja', false);

        $comprobantes = json_decode((string) $request->input('comprobantes_ivacompra_json', '[]'), true);
        if (! is_array($comprobantes)) {
            return response()->json(['mensaje' => 'ng', 'error' => 'JSON de comprobantes inválido']);
        }

        if ($comprobantes === []) {
            return response()->json(['mensaje' => 'ok', 'valido' => true]);
        }

        $lineasCaja = [];
        $montos = $request->input('montos', []);
        $monedaIds = $request->input('moneda_ids', []);
        $cotizaciones = $request->input('cotizaciones', []);

        if (is_array($montos)) {
            for ($i = 0; $i < count($montos); $i++) {
                $lineasCaja[] = [
                    'montos' => $montos[$i] ?? 0,
                    'moneda_ids' => $monedaIds[$i] ?? 1,
                    'cotizaciones' => $cotizaciones[$i] ?? 1,
                ];
            }
        }

        $monedaRef = (int) ($monedaIds[0] ?? 1);

        try {
            $this->comprobanteIvaService->validarTotalesContraCaja($comprobantes, $lineasCaja, $monedaRef);

            return response()->json([
                'mensaje' => 'ok',
                'valido' => true,
                'total_comprobantes' => IngresoEgresoComprobanteIvaValidacionSupport::totalComprobantes($comprobantes, $monedaRef),
                'total_pago' => IngresoEgresoComprobanteIvaValidacionSupport::totalPagoCaja($lineasCaja, $monedaRef),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'mensaje' => 'ng',
                'valido' => false,
                'error' => $e->getMessage(),
                'total_comprobantes' => IngresoEgresoComprobanteIvaValidacionSupport::totalComprobantes($comprobantes, $monedaRef),
                'total_pago' => IngresoEgresoComprobanteIvaValidacionSupport::totalPagoCaja($lineasCaja, $monedaRef),
            ]);
        }
    }

    public function validarDuplicadoComprobanteIva(Request $request)
    {
        can('crear-ingresos-egresos-caja', false);
        can('editar-ingresos-egresos-caja', false);

        $payload = json_decode((string) $request->input('comprobante_json', '{}'), true);
        if (! is_array($payload)) {
            return response()->json(['mensaje' => 'ng', 'error' => 'JSON inválido']);
        }

        $empresaId = (int) $request->input('empresa_id', $payload['empresa_id'] ?? 0);
        $excluirId = (int) ($payload['id'] ?? 0) ?: null;

        $error = $this->comprobanteIvaService->verificarDuplicadoDesdePayload($payload, $empresaId, $excluirId);

        if ($error !== null) {
            return response()->json(['mensaje' => 'ng', 'valido' => false, 'error' => $error]);
        }

        return response()->json(['mensaje' => 'ok', 'valido' => true]);
    }

    /** @return array<string, mixed> */
    private function datosComprobantesIva(?int $cajaMovimientoId): array
    {
        $tipotransaccion_compra_query = $this->tipotransaccionCompraRepository->all();
        $concepto_ivacompra_query = $this->conceptoIvacompraRepository->all();
        $condicioniva_query = $this->condicionivaRepository->all();
        $tipos_tesoreria = ComprobanteProveedorTipoTesoreria::todos();

        $conceptos_cuenta_meta = Concepto_Ivacompra::query()
            ->with('impuestos')
            ->get()
            ->mapWithKeys(static fn (Concepto_Ivacompra $c) => [
                (string) $c->id => [
                    'nombre' => $c->nombre,
                    'tipoconcepto' => $c->tipoconcepto,
                    'cuenta_debe_id' => (int) ($c->cuentacontabledebe_id ?? 0),
                    'impuesto_tasa' => round((float) ($c->impuestos->valor ?? 0), 3),
                ],
            ])
            ->all();

        $comprobantes_ivacompra_inicial = $cajaMovimientoId
            ? $this->comprobanteIvaService->listarPorCajaMovimiento($cajaMovimientoId)
            : [];

        return compact(
            'tipotransaccion_compra_query',
            'concepto_ivacompra_query',
            'condicioniva_query',
            'tipos_tesoreria',
            'conceptos_cuenta_meta',
            'comprobantes_ivacompra_inicial',
        );
    }
}
