<?php

namespace App\Http\Controllers\Compras;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Exclusion;
use App\Models\Configuracion\Pais;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Condicioniva;
use App\Models\Configuracion\Moneda;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionProveedor;
use App\Repositories\Compras\TiposuspensionproveedorRepositoryInterface;
use App\Repositories\Compras\TipoempresaRepositoryInterface;
use App\Repositories\Compras\Tiposervicio_ProveedorRepositoryInterface;
use App\Repositories\Compras\RetenciongananciaRepositoryInterface;
use App\Repositories\Compras\RetencionsussRepositoryInterface;
use App\Repositories\Compras\RetencionivaRepositoryInterface;
use App\Repositories\Compras\CondicionpagoRepositoryInterface;
use App\Repositories\Compras\CondicioncompraRepositoryInterface;
use App\Repositories\Compras\CondicionentregaRepositoryInterface;
use App\Repositories\Compras\EncuestaRepositoryInterface;
use App\Repositories\Caja\ConceptogastoRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Repositories\Caja\TipocuentacajaRepositoryInterface;
use App\Repositories\Caja\BancoRepositoryInterface;
use App\Repositories\Caja\MediopagoRepositoryInterface;
use App\Queries\Compras\ProveedorQueryInterface;
use App\Services\Configuracion\IIBBService;
use App\Services\Compras\RequisicionService;
use App\Services\Compras\OrdencompraService;
use App\Repositories\Configuracion\CondicionIIBBRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Models\Compras\Sector_Legajocompra;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Compras\OrdencompraListadoFiltros;
use App\Services\Arca\ConstanciaInscripcionService;
use App\Support\Ventas\ArcaPadronImpuestosClienteValidacion;
use App\Support\Compras\ProveedorFacturasApocrifasSupport;
use Illuminate\Http\JsonResponse;
use App\Repositories\Compras\Proveedor_ExclusionRepositoryInterface;
use App\Repositories\Compras\Proveedor_ArchivoRepositoryInterface;
use App\Repositories\Compras\Proveedor_FormapagoRepositoryInterface;
use App\Repositories\Compras\Proveedor_EncuestaRepositoryInterface;
use App\Repositories\Compras\Proveedor_Encuesta_PreguntaRepositoryInterface;
use App\Repositories\Compras\Proveedor_CuentacorrienteRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Mail\Compras\ProveedorProvisorio;
use App\Exports\Compras\ProveedorExport;
use App\Support\Compras\ProveedorListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use App\Exports\Compras\ProveedorCuentacorrienteListadoExport;
use App\Support\Compras\ProveedorCuentacorrientePreferenciasUsuario;
use Carbon\Carbon;
use Mail;
use DB;

class ProveedorController extends Controller
{
	private $proveedorRepository;
	private $proveedor_exclusionRepository;
	private $proveedor_archivoRepository;
    private $proveedor_formapagoRepository;
    private $proveedor_encuestaRepository;
    private $proveedor_encuesta_preguntaRepository;
    private $proveedor_cuentacorrienteRepository;
    private $tiposuspensionproveedorRepository;
    private $tipoempresaRepository;
    private $tiposervicio_proveedorRepository;
    private $retenciongananciaRepository;
    private $retencionsussRepository;
    private $retencionivaRepository;
    private $condicionpagoRepository;
    private $condicioncompraRepository;
    private $condicionentregaRepository;
    private $condicionIIBBRepository;
    private $conceptogastoRepository;
	private $iibbService;
    private $requisicionService;
    private $ordencompraService;
	private $proveedorQuery;
    private $formapagoRepository;
    private $monedaRepository;
    private $tipocuentacajaRepository;
    private $bancoRepository;
    private $mediopagoRepository;
    private $centrocostoRepository;
    private $cuentacontableRepository;
    private $encuestaRepository;

    public function __construct(
		IIBBService $iibbService,
        RequisicionService $requisicionService,
        OrdencompraService $ordencompraService,
        TiposuspensionproveedorRepositoryInterface $tiposuspensionproveedorrepository,
        TipoempresaRepositoryInterface $tipoempresarepository,
        Tiposervicio_ProveedorRepositoryInterface $tiposervicio_proveedorrepository,
        RetenciongananciaRepositoryInterface $retenciongananciarepository,
        RetencionivaRepositoryInterface $retencionivarepository,
        RetencionsussRepositoryInterface $retencionsussrepository,
        CondicionpagoRepositoryInterface $condicionpagorepository,
        CondicioncompraRepositoryInterface $condicioncomprarepository,
        CondicionentregaRepositoryInterface $condicionentregarepository,
        CondicionIIBBRepositoryInterface $condicionIIBBrepository,
        ConceptogastoRepositoryInterface $conceptogastorepository,
        FormapagoRepositoryInterface $formapagorepository,
        TipocuentacajaRepositoryInterface $tipocuentacajarepository,
        BancoRepositoryInterface $bancorepository,
        MediopagoRepositoryInterface $mediopagorepository,
        ProveedorRepositoryInterface $proveedorrepository, 
		Proveedor_ExclusionRepositoryInterface $proveedor_exclusionrepository, 
        Proveedor_FormapagoRepositoryInterface $proveedor_formapagorepository, 
		Proveedor_ArchivoRepositoryInterface $proveedor_archivorepository,
        Proveedor_EncuestaRepositoryInterface $proveedor_encuestarepository, 
        Proveedor_Encuesta_PreguntaRepositoryInterface $proveedor_encuesta_preguntarepository, 
        Proveedor_CuentacorrienteRepositoryInterface $proveedor_cuentacorrienterepository, 
        ProveedorQueryInterface $proveedorquery,
        CentrocostoRepositoryInterface $centrocostorepository,
        CuentacontableRepositoryInterface $cuentacontablerepository,
        EncuestaRepositoryInterface $encuestaRepository,
        MonedaRepositoryInterface $monedaRepository)
    {
        $this->proveedorRepository = $proveedorrepository;
        $this->proveedor_exclusionRepository = $proveedor_exclusionrepository;
        $this->proveedor_archivoRepository = $proveedor_archivorepository;
        $this->proveedor_encuestaRepository = $proveedor_encuestarepository;
        $this->proveedor_encuesta_preguntaRepository = $proveedor_encuesta_preguntarepository;
        $this->proveedor_formapagoRepository = $proveedor_formapagorepository;
        $this->proveedor_cuentacorrienteRepository = $proveedor_cuentacorrienterepository;
        $this->tiposuspensionproveedorRepository = $tiposuspensionproveedorrepository;
        $this->tipoempresaRepository = $tipoempresarepository;
        $this->tiposervicio_proveedorRepository = $tiposervicio_proveedorrepository;
        $this->retenciongananciaRepository = $retenciongananciarepository;
        $this->retencionivaRepository = $retencionivarepository;
        $this->retencionsussRepository = $retencionsussrepository;
        $this->condicionpagoRepository = $condicionpagorepository;
        $this->condicioncompraRepository = $condicioncomprarepository;
        $this->condicionentregaRepository = $condicionentregarepository;
        $this->condicionIIBBRepository = $condicionIIBBrepository;
        $this->iibbService = $iibbService;
        $this->requisicionService = $requisicionService;
        $this->ordencompraService = $ordencompraService;
        $this->conceptogastoRepository = $conceptogastorepository;
        $this->formapagoRepository = $formapagorepository;
        $this->tipocuentacajaRepository = $tipocuentacajarepository;
        $this->bancoRepository = $bancorepository;
        $this->mediopagoRepository = $mediopagorepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->cuentacontableRepository = $cuentacontablerepository;
        $this->encuestaRepository = $encuestaRepository;
        $this->monedaRepository = $monedaRepository;

        $this->proveedorQuery = $proveedorquery;
        $this->flRemoto = false;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-proveedor');

        $hay_proveedores = $this->proveedorQuery->first();

		if (!$hay_proveedores)
		{
			$this->proveedorRepository->sincronizarConAnita();
			$this->proveedor_archivoRepository->sincronizarConAnita();
		}

        $filtros = ProveedorListadoFiltros::resolverDesdeRequest($request);

        $proveedores = $this->proveedorRepository->leeProveedor($filtros, true);

        return view('compras.proveedor.index', [
            'proveedores' => $proveedores,
            'busqueda' => $filtros['busqueda'],
            'filtros' => $filtros,
            'filtrosQuery' => ProveedorListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ProveedorListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-proveedor'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ProveedorListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch($formato)
        {
        case 'PDF':
            $proveedores = $this->proveedorRepository->leeProveedor($filtros, false);

            $view =  \View::make('compras.proveedor.listado', compact('proveedores'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_proveedor';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new ProveedorExport($this->proveedorRepository))
                        ->parametros($filtros)
                        ->download('proveedor.xlsx');
            break;

        case 'CSV':
            return (new ProveedorExport($this->proveedorRepository))
                        ->parametros($filtros)
                        ->download('proveedor.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        return redirect()->route('proveedor', ProveedorListadoFiltros::paraQueryString($filtros));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function crear(Request $request, $tipoalta = null)
    {
        can('crear-proveedor');

        $estado_enum = [];
		$this->armaTablasVista($pais_query, $provincia_query, $tipoempresa_query,
            $condicioniva_query, $condicionIIBB_query,
            $retencionganancia_query, $retencioniva_query, $retencionsuss_query,
        	$condicionpago_query, $condicioncompra_query, $condicionentrega_query,
            $cuentacontable_query, $retieneiva_enum, $retieneganancia_enum, 
            $condicionganancia_enum, $retienesuss_enum, $agentepercepcioniva_enum, $agentepercepcionIIBB_enum, 
            $centrocosto_query, $conceptogasto_query,
            $estado_enum, '', $tasaarba, $tasacaba, 
            $formapago_query, $tipocuentacaja_query, $moneda_query, $banco_query, $mediopago_query,
            $tiporetencion_enum, $semaforo_enum, $tiposervicio_proveedor_query, $regimenfacturacion_enum,
            'crear'); 

        $tipoAlta_enum = Proveedor::$enumTipoAlta;
        if (!isset($tipoalta))
            $tipoalta = substr(config("proveedor.tipoalta"),0,1);

        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ProveedorListadoFiltros::class);

        return view('compras.proveedor.crear', compact('pais_query', 'provincia_query', 'tipoempresa_query',
			'condicioniva_query', 'condicionIIBB_query',
            'retencionganancia_query', 'retencioniva_query', 'retencionsuss_query',
			'condicionpago_query', 'condicioncompra_query', 'condicionentrega_query',
            'cuentacontable_query', 'retieneiva_enum', 
            'retieneganancia_enum', 'retienesuss_enum', 'condicionganancia_enum',
            'centrocosto_query', 'conceptogasto_query', 'agentepercepcioniva_enum', 'agentepercepcionIIBB_enum',
            'estado_enum', 'tasaarba', 'tasacaba', 'tipoalta', 'semaforo_enum',
            'formapago_query', 'tipocuentacaja_query', 'moneda_query', 'banco_query', 'mediopago_query',
            'tiporetencion_enum', 'tiposervicio_proveedor_query', 'regimenfacturacion_enum', 'filtrosQuery'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionProveedor $request)
    {
        DB::beginTransaction();
        try
        {
            $data = $request->all();

            $proveedor = $this->proveedorRepository->create($data);

            // Guarda tablas asociadas
            if ($proveedor)
            {
                $proveedor_exclusion = $this->proveedor_exclusionRepository->create($request->all(), $proveedor->id);
                $proveedor_formapago = $this->proveedor_formapagoRepository->create($request->all(), $proveedor->id);
                $proveedor_archivo = $this->proveedor_archivoRepository->create($request, $proveedor->id);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withInput()->withErrors(['errores' => $e->getMessage()]);
        }

        // Procesa envio del correo para aprobacion del proveedor provisorio
        if (substr(config("proveedor.tipoalta"),0,1) == 'P' && config("proveedor.enviamailaprobacion") == 'S')
        {
            $receivers = config("proveedor.emailapruebaalta");

            Mail::to($receivers)->send(new ProveedorProvisorio($request));
        }

        return redirect()->route('proveedor', QueryRetornoListado::desdeRequest($request, ProveedorListadoFiltros::class))
            ->with('mensaje', 'Proveedor creado con exito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar(Request $request, $id)
    {
        can('editar-proveedor');
        $data = $this->proveedorRepository->findOrFail($id);

        $referer = $request->header("referer");

        if (strstr($referer, "/public/compras/proveedor"))
            $tipoconsulta = '';
        else
            $tipoconsulta = 'REMOTA';

        $estado_enum = [];
        $this->armaTablasVista($pais_query, $provincia_query, $tipoempresa_query,
            $condicioniva_query, $condicionIIBB_query,
            $retencionganancia_query, $retencioniva_query, $retencionsuss_query,
        	$condicionpago_query, $condicioncompra_query, $condicionentrega_query,
        	$cuentacontable_query, $retieneiva_enum, $retieneganancia_enum, $condicionganancia_enum,
            $retienesuss_enum, $agentepercepcioniva_enum, $agentepercepcionIIBB_enum, 
            $centrocosto_query, $conceptogasto_query,
            $estado_enum, $data->nroinscripcion, $tasaarba, $tasacaba, 
            $formapago_query, $tipocuentacaja_query, $moneda_query, $banco_query, $mediopago_query,
            $tiporetencion_enum, $semaforo_enum, $tiposervicio_proveedor_query, $regimenfacturacion_enum,
            'editar'); 

        $tiposuspensionproveedor_query = $this->tiposuspensionproveedorRepository->all();
        
		$tipoalta = $data->tipoalta;

        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ProveedorListadoFiltros::class);

        return view('compras.proveedor.editar', compact('data', 'pais_query', 'provincia_query', 'tipoempresa_query',
			'condicioniva_query', 'condicionIIBB_query',
            'retencionganancia_query', 'retencioniva_query', 'retencionsuss_query',
            'condicionpago_query', 'condicioncompra_query', 'condicionentrega_query',
			'cuentacontable_query', 'retieneiva_enum', 
            'retieneganancia_enum', 'retienesuss_enum', 'condicionganancia_enum',
            'centrocosto_query', 'conceptogasto_query',
            'estado_enum', 'tasaarba', 'tasacaba', 'tipoalta', 'semaforo_enum',
		    'tiposuspensionproveedor_query', 'agentepercepcioniva_enum', 'agentepercepcionIIBB_enum',
            'formapago_query', 'tipocuentacaja_query', 'moneda_query', 'banco_query', 'mediopago_query',
            'tiporetencion_enum', 'tipoconsulta', 'tiposervicio_proveedor_query', 'regimenfacturacion_enum', 'filtrosQuery'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionProveedor $request, $id)
    {
        can('actualizar-proveedor');

        DB::beginTransaction();
        try
        {
            // Graba proveedor
            $this->proveedorRepository->update($request->all(), $id);

            // Graba exclusion de retenciones
            $this->proveedor_exclusionRepository->update($request->all(), $id);

            // Graba forma de pago
            $this->proveedor_formapagoRepository->update($request->all(), $id);

            // Graba archivos asociados
            $this->proveedor_archivoRepository->update($request, $id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withInput()->withErrors(['errores' => $e->getMessage()]);
        }

        return redirect()->route('proveedor', QueryRetornoListado::desdeRequest($request, ProveedorListadoFiltros::class))
            ->with('mensaje', 'Proveedor actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-proveedor');

		$proveedor = $this->proveedorRepository->find($id);

		if ($proveedor)
		{
			$codigo = $proveedor->codigo;
	
        	if ($request->ajax()) {
				$proveedor = $this->proveedorRepository->delete($id);
        		if ($proveedor) {
                	return response()->json(['mensaje' => 'ok']);
            	} else {
                	return response()->json(['mensaje' => 'ng']);
            	}
        	} else {
            	abort(404);
        	}
		}
		else
            return response()->json(['mensaje' => 'ng']);
    }

    private function armaTablasVista(&$pais_query, &$provincia_query, &$tipoempresa_query,
        &$condicioniva_query, &$condicionIIBB_query,
        &$retencionganancia_query, &$retencioniva_query, &$retencionsuss_query,
        &$condicionpago_query, &$condicioncompra_query, &$condicionentrega_query,
        &$cuentacontable_query, &$retieneiva_enum, &$retieneganancia_enum, &$condicionganancia_enum,
        &$retienesuss_enum, &$agentepercepcioniva_enum, &$agentepercepcionIIBB_enum,
        &$centrocosto_query, &$conceptogasto_query,
        &$estado_enum, $nroinscripcion, &$tasaarba, &$tasacaba, 
        &$formapago_query, &$tipocuentacaja_query, &$moneda_query, &$banco_query, &$mediopago_query,
        &$tiporetencion_enum, &$semaforo_enum, &$tiposervicio_proveedor_query, &$regimenfacturacion_enum,
        $funcion)
	{
        $pais_query = Pais::orderBy('nombre')->get();
        $provincia_query = Provincia::orderBy('nombre')->get();
        $tipoempresa_query = $this->tipoempresaRepository->all();
        $tiposervicio_proveedor_query = $this->tiposervicio_proveedorRepository->all();
        $condicioniva_query = Condicioniva::orderBy('nombre')->get();
        $condicionIIBB_query = $this->condicionIIBBRepository->all();
        $retencionganancia_query = $this->retenciongananciaRepository->all();
        $retencioniva_query = $this->retencionivaRepository->all();
        $retencionsuss_query = $this->retencionsussRepository->all();
        $condicionpago_query = $this->condicionpagoRepository->all();
        $condicioncompra_query = $this->condicioncompraRepository->all();
        $condicionentrega_query = $this->condicionentregaRepository->all();
        $centrocosto_query = $this->centrocostoRepository->all();
        $conceptogasto_query = $this->conceptogastoRepository->all();
        $cuentacontable_query = $this->cuentacontableRepository->allPrimeraEmpresa();
        $retieneiva_enum = Proveedor::$enumRetieneiva;
        $retieneganancia_enum = Proveedor::$enumRetieneganancia;
        $condicionganancia_enum = Proveedor::$enumCondicionganancia;
        $retienesuss_enum = Proveedor::$enumRetienesuss;
        $agentepercepcioniva_enum = Proveedor::$enumAgentePercepcioniva;
        $agentepercepcionIIBB_enum = Proveedor::$enumAgentePercepcionIIBB;
        $semaforo_enum = Proveedor::$enumSemaforo;
        $regimenfacturacion_enum = Proveedor::$enumRegimenfacturacion;
        $formapago_query = $this->formapagoRepository->all();
        $tipocuentacaja_query = $this->tipocuentacajaRepository->all();
        $banco_query = $this->bancoRepository->all();
        $mediopago_query = $this->mediopagoRepository->all();
        $moneda_query = Moneda::get();
        
        $tiporetencion_enum = Proveedor_Exclusion::$enumTipoRetencion;
		$estado_enum = Proveedor::$enumEstado;

		if ($funcion == 'editar')
		{
            $fechaHoy = Carbon::now();

			$tasaIibbArba = $this->iibbService->leeTasaPercepcion($nroinscripcion, '902', $fechaHoy);
            $tasaIibbCaba = $this->iibbService->leeTasaPercepcion($nroinscripcion, '901', $fechaHoy);

            $tasaArbaValor = $this->tasaPercepcionDesdePadron($tasaIibbArba);
            $tasaarba = $tasaArbaValor === null ? 'No esta en padron' : round($tasaArbaValor, 2).'%';

            $tasaCabaValor = $this->tasaPercepcionDesdePadron($tasaIibbCaba);
            $tasacaba = ($tasaCabaValor === null || $tasaCabaValor < 0.00001)
                ? 'No esta en padron'
                : round($tasaCabaValor, 2).'%';
		}
		else
			$tasaarba = $tasacaba = '';
	}

    /**
     * Tasa de percepción IIBB según registro de padrón (ARBA/CABA: modelo; otras jurisd.: array con "tasa").
     */
    private function tasaPercepcionDesdePadron($registroPadron): ?float
    {
        if (!$registroPadron) {
            return null;
        }

        if (is_array($registroPadron)) {
            $tasa = $registroPadron['tasapercepcion'] ?? $registroPadron['tasa'] ?? null;
        } else {
            $tasa = $registroPadron->tasapercepcion ?? $registroPadron->tasa ?? null;
        }

        return ($tasa !== null && $tasa !== '') ? (float) $tasa : null;
    }

    // Reporte maestro de proveedores
    public function indexReporteProveedor()
    {
        $proveedor_query = $this->proveedorQuery->all();
        $proveedor_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
        $proveedor_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
        $estado_enum = [
            'ACTIVOS' => 'Proveedors activos',
			'SUSPENDIDOS' => 'Proveedors suspendidos',
            'TODOS' => 'Todos los proveedores',
		];
        $tiposuspensionproveedor_query = $this->tiposuspensionproveedorRepository->all();
        $tiposuspensionproveedor_query->prepend((object) ['id'=>'TODOS','nombre'=>'Todos los tipos de suspensión']);
        $vendedor_query = Vendedor::all();
		$vendedor_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$vendedor_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
        
        return view('compras.repproveedor.crear', compact('proveedor_query', 'estado_enum', 
                                                        'tiposuspensionproveedor_query', 'vendedor_query'));
    }

    public function crearReporteProveedor(Request $request)
    {
        switch($request->extension)
        {
        case "Genera Reporte en Excel":
            $extension = "xlsx";
            break;
        case "Genera Reporte en PDF":
            $extension = "pdf";
            break;
        case "Genera Reporte en CSV":
            $extension = "csv";
            break;
        }

        return (new ProveedorExport($this->proveedorQuery, $this->tiposuspensionproveedorRepository))
                ->parametros($request->desdeproveedor_id, 
                             $request->hastaproveedor_id, 
                             $request->estado, 
                             $request->tiposuspensionproveedor_id,
                             $request->desdevendedor_id,
                             $request->hastavendedor_id)
                ->download('proveedor.'.$extension);
    }
    
    // Consulta de proveedores (lupa)
    public function consultaProveedor(Request $request)
    {
        return ($this->proveedorQuery->consultaProveedor($request->consulta));
	}

    /**
     * Consulta ARCA en background desde el ABM; suspende el proveedor si los impuestos no son válidos (RI / Monotributo).
     */
    public function validarArcaPadron(Request $request, int $id): JsonResponse
    {
        can('editar-proveedor');

        if (! filter_var(config('arca.padron_validacion_proveedor.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'ok' => true,
                'skipped' => true,
                'validacion' => null,
            ]);
        }

        $proveedor = $this->proveedorRepository->find($id);
        if (! $proveedor) {
            return response()->json(['ok' => false, 'message' => 'Proveedor inexistente.'], 404);
        }

        $cuit = preg_replace('/\D+/', '', (string) $proveedor->nroinscripcion);
        if (strlen($cuit) !== 11) {
            return response()->json([
                'ok' => false,
                'message' => 'El proveedor no tiene una CUIT válida (11 dígitos) para consultar ARCA.',
            ], 422);
        }

        $condicionivaId = (int) $request->input('condicioniva_id', $proveedor->condicioniva_id);

        try {
            $data = app(ConstanciaInscripcionService::class)->getPersonaV2($cuit);
            $validacion = ArcaPadronImpuestosClienteValidacion::validar(
                $condicionivaId > 0 ? $condicionivaId : null,
                $data
            );
            $suspendido = false;

            if (($validacion['debe_suspender'] ?? false) && ($validacion['aplica'] ?? false)) {
                Proveedor::query()->whereKey($id)->update(['estado' => 'Suspendido']);
                $suspendido = true;
            }

            $httpOk = ! ($validacion['aplica'] ?? false) || ($validacion['ok'] ?? false);

            return response()->json([
                'ok' => $httpOk,
                'message' => $validacion['mensaje'] ?? null,
                'data' => $data,
                'validacion' => $validacion,
                'suspendido' => $suspendido,
                'estado' => $suspendido ? 'Suspendido' : (string) $proveedor->estado,
                'soap' => $data['soap'] ?? null,
            ], $httpOk ? 200 : 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consulta WSAPOC (facturas apócrifas) en background desde el ABM; suspende si figura en APOC.
     */
    public function validarArcaApoc(Request $request, int $id): JsonResponse
    {
        can('editar-proveedor');

        $support = app(ProveedorFacturasApocrifasSupport::class);
        if (! $support->habilitadoParaAbm()) {
            return response()->json([
                'ok' => true,
                'skipped' => true,
                'validacion' => null,
            ]);
        }

        $proveedor = $this->proveedorRepository->find($id);
        if (! $proveedor) {
            return response()->json(['ok' => false, 'message' => 'Proveedor inexistente.'], 404);
        }

        try {
            $validacion = $support->evaluarProveedor($proveedor, suspenderSiApocrifo: true);
            $httpOk = ! ($validacion['aplica'] ?? false) || ($validacion['ok'] ?? false);

            return response()->json([
                'ok' => $httpOk,
                'message' => $validacion['mensaje'] ?? null,
                'validacion' => $validacion,
                'suspendido' => $validacion['suspendido'] ?? false,
                'tiposuspension_id' => $validacion['tiposuspension_id'] ?? null,
                'estado' => ($validacion['suspendido'] ?? false) ? 'Suspendido' : (string) $proveedor->estado,
                'facturas_apocrifas' => (bool) ($validacion['es_apocrifo'] ?? false),
                'soap' => ($validacion['ws']['soap'] ?? null),
            ], $httpOk ? 200 : 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function leeProveedor($proveedor_id)
    {
        return $this->proveedorRepository->find($proveedor_id);
    }

    public function leeProveedorPorCodigo($codigo)
    {
        return $this->proveedorRepository->findPorCodigo($codigo);
    }    

    public function generarEncuesta($codigoProveedor, $encuesta_id, $origen, $hash)
    {
        $proveedor = $this->proveedorRepository->findPorCodigo($codigoProveedor);

        $encuesta = $this->encuestaRepository->find($encuesta_id);

        if (!$encuesta)
            return redirect()->route('inicio')->with('mensaje', 'Encuesta inexistente')->send();

        return view('compras.proveedor.formencuesta', compact('proveedor', 'encuesta', 'origen'));
    }

    public function guardarEncuesta(Request $request)
    {
		DB::beginTransaction();
		try
		{
            $data = $request->all();

            $data['fecha'] = Carbon::now();
            $proveedor_encuesta = $this->proveedor_encuestaRepository->create($data);

            // Guarda tablas asociadas
            if ($proveedor_encuesta)
                $proveedor_encuesta_pregunta = $this->proveedor_encuesta_preguntaRepository->create($request->all(), $proveedor_encuesta->id);     

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            dd($e->getMessage());
            return ['errores' => $e->getMessage()];
        }
        return redirect()->route('inicio')->with('mensaje', 'Encuesta guardada con éxito')->send();
    }

    public function listarEncuesta(Request $request, $proveedor_id)
    {
        $busqueda = '';
        if (isset($request->busqueda))
            $busqueda = $request->busqueda;

        $encuesta_proveedor = $this->proveedor_encuestaRepository->leePorProveedor($proveedor_id, $busqueda);

        $datas = ['encuesta_proveedor' => $encuesta_proveedor, 'busqueda' => $busqueda, 'id' => $proveedor_id];

        return view('compras.proveedor.indexencuesta', $datas);
    }

    public function listarRequisicion(Request $request, $proveedor_id)
    {
        can('listar-requisicion-proveedor');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $formato = $request->formato;
        $busqueda = $request->busqueda;
        $proveedor = $this->proveedorRepository->find($proveedor_id);

        $urlOrigen = request()->headers->get('referer');

        $nombreproveedor = '';
        $codigoproveedor = '';
        if ($proveedor)
        {
            $nombreproveedor = $proveedor->nombre;
            $codigoproveedor = $proveedor->codigo;
        }

        $requisicion = $this->requisicionService->leeRequisicionPorProveedor($busqueda, $proveedor_id);
        $moneda_query = $this->monedaRepository->all();

        if (!isset($requisicion['requisicion']))
            $requisicion['requisicion'] = [];

        if (!isset($requisicion['item']))
            $requisicion['item'] = [];
        
        $datas = ['requisicion' => $requisicion['requisicion'], 'items' => $requisicion['item'], 'busqueda' => $busqueda, 
                    'id' => $proveedor_id, 'codigoproveedor' => $codigoproveedor, 
                    'nombreproveedor' => $nombreproveedor, 'urlOrigen' => $urlOrigen,
                    'moneda_query' => $moneda_query];

        return view('compras.requisicion.proveedor_index', $datas);
    }

    public function listarOrdenCompra(Request $request, $proveedor_id)
    {
        can('listar-ordencompra-proveedor');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $proveedor = $this->proveedorRepository->find($proveedor_id);
        $codigoproveedor = $proveedor ? (string) $proveedor->codigo : '';

        // Reutiliza el índice paginado/filtrable de OC forzando el filtro por proveedor,
        // así comparte vista, filtros y exportaciones con el listado general.
        $filtros = [
            'modo' => OrdencompraListadoFiltros::MODO_CAMPO,
            'campo' => 'codigoproveedor',
            'operador' => 'igual',
            'valor' => $codigoproveedor,
            'valor_hasta' => '',
            'busqueda' => $codigoproveedor,
        ];

        // Sin restricción por sector: la vista del proveedor muestra todas sus OC.
        $ordencompra = app(OrdencompraRepositoryInterface::class)->listadoIndex($filtros, null, true);

        return view('compras.ordencompra.index', [
            'ordencompra' => $ordencompra,
            'busqueda' => $codigoproveedor,
            'filtros' => $filtros,
            'filtrosQuery' => OrdencompraListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => OrdencompraListadoFiltros::CAMPOS,
            'estados' => OrdencompraEstados::todos(),
            'sectores' => Sector_Legajocompra::orderBy('nombre')->get(),
            'sectorUsuario' => null,
        ]);
    }    

    public function listarCuentaCorriente(Request $request, $proveedor_id)
    {
        can('listar-cuentacorriente-proveedor');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $formato = $request->formato;
        $busqueda = (string) ($request->busqueda ?? '');
        $modoVista = ProveedorCuentacorrientePreferenciasUsuario::resolverModoVista($request->input('modo_vista'));

        if ($request->has('modo_vista')) {
            ProveedorCuentacorrientePreferenciasUsuario::persistirModoVista($modoVista);
        }

        $proveedor = $this->proveedorRepository->find($proveedor_id);

        $urlOrigen = request()->headers->get('referer');

        $nombreproveedor = '';
        $codigoproveedor = '';
        if ($proveedor) {
            $nombreproveedor = $proveedor->nombre;
            $codigoproveedor = $proveedor->codigo;
        }

        $saldoCuentaCorriente = $this->proveedor_cuentacorrienteRepository->calcularSaldoCuentaCorriente((int) $proveedor_id);
        $totalDeuda = $this->proveedor_cuentacorrienteRepository->calcularTotalDeudaProveedor((int) $proveedor_id);

        switch ($formato) {
        case 'PDF':
            if ($modoVista === ProveedorCuentacorrientePreferenciasUsuario::MODO_DEUDA) {
                $cuentacorriente = $this->proveedor_cuentacorrienteRepository->listarDeudaProveedor($busqueda, $proveedor_id, false);
            } else {
                $cuentacorriente = $this->proveedor_cuentacorrienteRepository->listarCuentaCorriente($busqueda, $proveedor_id, false);
            }

            $view = \View::make('compras.cuentacorriente.listado', compact(
                'cuentacorriente',
                'nombreproveedor',
                'modoVista',
                'saldoCuentaCorriente',
                'totalDeuda',
            ))->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_cuentacorriente_proveedor';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal', 'landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new ProveedorCuentacorrienteListadoExport($this->proveedor_cuentacorrienteRepository))
                ->parametros($busqueda, (int) $proveedor_id, $modoVista, $nombreproveedor, $saldoCuentaCorriente, $totalDeuda)
                ->download('cuentacorriente_proveedor.xlsx');
            break;

        case 'CSV':
            return (new ProveedorCuentacorrienteListadoExport($this->proveedor_cuentacorrienteRepository))
                ->parametros($busqueda, (int) $proveedor_id, $modoVista, $nombreproveedor, $saldoCuentaCorriente, $totalDeuda, true)
                ->download('cuentacorriente_proveedor.csv', \Maatwebsite\Excel\Excel::CSV);
            break;

        default:
            if ($modoVista === ProveedorCuentacorrientePreferenciasUsuario::MODO_DEUDA) {
                $cuentacorriente = $this->proveedor_cuentacorrienteRepository->listarDeudaProveedor($busqueda, $proveedor_id);
                $saldoAnterior = 0.0;
            } else {
                $cuentacorriente = $this->proveedor_cuentacorrienteRepository->listarCuentaCorriente($busqueda, $proveedor_id);
                $primerRegistro = $cuentacorriente->first();
                $saldoAnterior = $this->proveedor_cuentacorrienteRepository->saldoAnteriorPagina(
                    (int) $proveedor_id,
                    $primerRegistro,
                );
            }

            $moneda_query = $this->monedaRepository->all();

            $datas = [
                'cuentacorriente' => $cuentacorriente,
                'busqueda' => $busqueda,
                'id' => $proveedor_id,
                'nombreproveedor' => $nombreproveedor,
                'codigoproveedor' => $codigoproveedor,
                'urlOrigen' => $urlOrigen,
                'moneda_query' => $moneda_query,
                'modoVista' => $modoVista,
                'saldoCuentaCorriente' => $saldoCuentaCorriente,
                'totalDeuda' => $totalDeuda,
                'saldoAnterior' => $saldoAnterior ?? 0.0,
            ];

            return view('compras.cuentacorriente.index', $datas);
        }
    }

    public function editarCuentaCorriente($cuentacorriente_id)
    {
        can('listar-cuentacorriente-proveedor');

        $cuentacorriente = $this->proveedor_cuentacorrienteRepository->find($cuentacorriente_id);

        if ((int) ($cuentacorriente->comprobante_proveedor_id ?? 0) > 0) {
            return redirect()->route('editar_comprobante_proveedor', [
                'id' => $cuentacorriente->comprobante_proveedor_id,
            ]);
        }

        return 'No encontro movimiento a editar';
    }

    public function leerCuentaCorrienteAplicacion($proveedor_cuentacorriente_id)
    {
        return $this->proveedor_cuentacorrienteRepository->consultarAplicacion($proveedor_cuentacorriente_id);
    }
}

