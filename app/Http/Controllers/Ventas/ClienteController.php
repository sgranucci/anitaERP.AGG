<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Cliente_Entrega;
use App\Models\Ventas\Cliente_Archivo;
use App\Models\Ventas\Cliente_Cm05;
use App\Models\Ventas\Zonavta;
use App\Models\Ventas\Subzonavta;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Transporte;
use App\Models\Ventas\Abasto;
use App\Models\Ventas\Coeficiente;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\Distribuidor;
use App\Models\Ventas\Descuentoventa;
use App\Models\Stock\Listaprecio;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Pais;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Condicioniva;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCliente;
use App\Http\Requests\ValidacionClienteProvisorio;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Repositories\Ventas\Cliente_EntregaRepositoryInterface;
use App\Repositories\Ventas\Cliente_SeguimientoRepositoryInterface;
use App\Repositories\Ventas\Cliente_Cm05RepositoryInterface;
use App\Repositories\Ventas\Cliente_Articulo_SuspendidoRepositoryInterface;
use App\Repositories\Ventas\Cliente_ArchivoRepositoryInterface;
use App\Repositories\Ventas\Cliente_CuentacorrienteRepositoryInterface;
use App\Repositories\Ventas\TiposuspensionclienteRepositoryInterface;
use App\Repositories\Ventas\TipoempresaClienteRepositoryInterface;
use App\Repositories\Ventas\DescuentoventaRepositoryInterface;
use App\Repositories\Configuracion\TipodocumentoRepositoryInterface;
use App\Repositories\Configuracion\CondicionIIBBRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Ordenventa\OrdenventaRepositoryInterface;
use App\Queries\Ventas\ClienteQueryInterface;
use App\Queries\Ventas\Cliente_EntregaQueryInterface;
use App\Services\Configuracion\IIBBService;
use App\Services\Ventas\FacturacionService;
use App\Services\Caja\CobranzaService;
use App\Services\Crm\SuitecrmAccountService;
use App\Support\SuitecrmPermiso;
use App\Mail\Ventas\ClienteProvisorio;
use App\Mail\Ventas\ClienteDefinitivo;
use App\Exports\Ventas\ClienteExport;
use App\Exports\Ventas\ClienteListadoExport;
use App\Exports\Ventas\ClienteCuentacorrienteListadoExport;
use App\Support\Ventas\ClienteListadoFiltros;
use App\Support\Ventas\ClienteCuentacorrientePreferenciasUsuario;
use App\Support\Ventas\ArcaPadronImpuestosClienteValidacion;
use App\Services\Arca\ConstanciaInscripcionService;
use Carbon\Carbon;
use Mail;
use DB;

class ClienteController extends Controller
{
	private $clienteRepository;
	private $cliente_entregaRepository;
    private $cliente_seguimientoRepository;
    private $cliente_cm05Repository;
    private $cliente_articulo_suspendidoRepository;
    private $cliente_cuentacorrienteRepository;
	private $cliente_archivoRepository;
    private $descuentoventaRepository;
    private $tiposuspensionclienteRepository;
    private $tipoempresaClienteRepository;
    private $tipodocumentoRepository;
	private $iibbService;
    private $facturacionService;
    private $cobranzaService;
	private $clienteQuery;
	private $cliente_entregaQuery;
    private $ordenventaRepository;
    private $condicionIIBBRepository;
    private $monedaRepository;

    public function __construct(
		ClienteRepositoryInterface $clienteRepository, 
		Cliente_EntregaRepositoryInterface $cliente_entregaRepository, 
		Cliente_ArchivoRepositoryInterface $cliente_archivoRepository, 
        Cliente_SeguimientoRepositoryInterface $cliente_seguimientorepository,
        Cliente_CuentacorrienteRepositoryInterface $cliente_cuentacorrienterepository,
        Cliente_Cm05RepositoryInterface $cliente_cm05repository,
        Cliente_Articulo_SuspendidoRepositoryInterface $cliente_articulo_suspendidorepository,
        DescuentoventaRepositoryInterface $descuentoventarepository,
        TipodocumentoRepositoryInterface $tipodocumentoRepository,
		IIBBService $iibbService,
        FacturacionService $facturacionService,
        CobranzaService $cobranzaService,
		ClienteQueryInterface $clientequery,
        TiposuspensionclienteRepositoryInterface $tiposuspensionclienterepository,
        TipoempresaClienteRepositoryInterface $tipoempresaClienterepository,
        Cliente_EntregaQueryInterface $cliente_entregaquery,
        OrdenventaRepositoryInterface $ordenventarepository,
        CondicionIIBBRepositoryInterface $condicionIIBBrepository,
        MonedaRepositoryInterface $monedaRepository)
    {
        $this->clienteRepository = $clienteRepository;
        $this->cliente_entregaRepository = $cliente_entregaRepository;
        $this->cliente_seguimientoRepository = $cliente_seguimientorepository;
        $this->cliente_cuentacorrienteRepository = $cliente_cuentacorrienterepository;
        $this->cliente_cm05Repository = $cliente_cm05repository;
        $this->cliente_articulo_suspendidoRepository = $cliente_articulo_suspendidorepository;
        $this->cliente_archivoRepository = $cliente_archivoRepository;
        $this->descuentoventaRepository = $descuentoventarepository;
        $this->tiposuspensionclienteRepository = $tiposuspensionclienterepository;
        $this->tipoempresaClienteRepository = $tipoempresaClienterepository;
        $this->iibbService = $iibbService;
        $this->facturacionService = $facturacionService;
        $this->cobranzaService = $cobranzaService;
        $this->tipodocumentoRepository = $tipodocumentoRepository;

        $this->clienteQuery = $clientequery;
        $this->cliente_entregaQuery = $cliente_entregaquery;

        $this->ordenventaRepository = $ordenventarepository;
        $this->condicionIIBBRepository = $condicionIIBBrepository;
        $this->monedaRepository = $monedaRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-clientes');

        // Por si se necesita traer un cliente que no esta en ERP
        //$this->clienteRepository->traerRegistroDeAnita("000105", true);

        $filtros = ClienteListadoFiltros::resolverDesdeRequest($request);

        $clientes = $this->clienteRepository->leeCliente($filtros, true);

        if ($clientes->isEmpty() && ! ClienteListadoFiltros::tieneCriteriosAplicados($filtros))
		{
        	$this->clienteRepository->sincronizarConAnita();
			$this->cliente_entregaRepository->sincronizarConAnita();
			$this->cliente_archivoRepository->sincronizarConAnita();
	
            $clientes = $this->clienteRepository->leeCliente($filtros, true);
		}

        return view('ventas.cliente.index', [
            'clientes' => $clientes,
            'busqueda' => $filtros['busqueda'],
            'filtros' => $filtros,
            'filtrosQuery' => ClienteListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ClienteListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-clientes'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ClienteListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch($formato)
        {
        case 'PDF':
            $clientes = $this->clienteRepository->leeCliente($filtros, false);

            $view =  \View::make('ventas.cliente.listado', compact('clientes'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_cliente';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new ClienteListadoExport($this->clienteRepository))
                        ->parametros($filtros)
                        ->download('cliente.xlsx');
            break;

        case 'CSV':
            return (new ClienteListadoExport($this->clienteRepository))
                        ->parametros($filtros)
                        ->download('cliente.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }

        return redirect()->route('cliente', ClienteListadoFiltros::paraQueryString($filtros));
    }

	public function leerCliente_Entrega($cliente_id)
    {
        return $this->cliente_entregaQuery->traeCliente_EntregaporCliente_Id($cliente_id);
    }

	public function leerCliente($cliente_id)
    {
        return $this->clienteQuery->traeClienteporId($cliente_id, ['id','vendedor_id','transporte_id','condicionventa_id','descuento','tiposuspension_id','lugarentrega','zonavta_id'])->toArray();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function crear($tipoalta = null)
    {
        can('crear-clientes');

		$this->armaTablasVista($pais_query, $provincia_query, $condicioniva_query, $zonavta_query,
        	$subzonavta_query, $vendedor_query, $transporte_query, $condicionventa_query, $listaprecio_query,
        	$cuentacontable_query, $retieneiva_enum, $condicioniibb_enum, $vaweb_enum, $estado_enum, '', $tasaarba,
			$tasacaba, $modofacturacion_enum, $cajaespecial_enum, $abasto_query, $coeficiente_query, 
            $distribuidor_query,
            $emitecertificado_enum, $emitenotadecredito_enum, $agregabonificacion_enum, $descuentoventa_query,
            $tipodocumento_query, $condicioniibb_query, $tipopercepcion_enum, $certificadonoretencion_enum,
            $tipoempresa_cliente_query,
            'crear'); 

        if (!isset($tipoalta))
            $tipoalta = config('cliente.tipoalta')['DEFINITIVO'][0];

        return view('ventas.cliente.crear', compact('pais_query', 'provincia_query',
			'condicioniva_query', 'zonavta_query', 'subzonavta_query', 'vendedor_query', 'transporte_query',
			'condicionventa_query', 'listaprecio_query', 'retieneiva_enum', 'condicioniibb_enum', 'cuentacontable_query',
			'vaweb_enum', 'tasaarba', 'tasacaba', 'estado_enum', 'tipoalta',
            'modofacturacion_enum', 'cajaespecial_enum', 'abasto_query', 'coeficiente_query', 'distribuidor_query',
            'emitecertificado_enum', 'emitenotadecredito_enum', 'agregabonificacion_enum', 'descuentoventa_query',
            'tipopercepcion_enum', 'certificadonoretencion_enum',
            'tipodocumento_query', 'condicioniibb_query', 'tipoempresa_cliente_query'));
    }

    public function crearRemoto(Request $request, $id)
    {
        can('crear-clientes');

        // Trae variables remotas
        $urlOrigen = request()->headers->get('referer');

        // Lee datos de origen
        if (str_contains($urlOrigen, 'ordenventa'))
        {
            $data = $this->ordenventaRepository->find($id);
            $data->nombre = $data->nombrecliente;
            $data->numerodocumento = $data->nroinscripcion;
            $data->fantasia = $data->fantasiacliente;
        }

        $idRemoto = $id;

		$this->armaTablasVista($pais_query, $provincia_query, $condicioniva_query, $zonavta_query,
        	$subzonavta_query, $vendedor_query, $transporte_query, $condicionventa_query, $listaprecio_query,
        	$cuentacontable_query, $retieneiva_enum, $condicioniibb_enum, $vaweb_enum, $estado_enum, '', $tasaarba,
			$tasacaba, $modofacturacion_enum, $cajaespecial_enum, $abasto_query, $coeficiente_query, 
            $distribuidor_query,
            $emitecertificado_enum, $emitenotadecredito_enum, $agregabonificacion_enum, $descuentoventa_query,
            $tipodocumento_query, $condicioniibb_query, $tipopercepcion_enum, $certificadonoretencion_enum,
            $tipoempresa_cliente_query,
            'crear'); 

        if (!isset($tipoalta))
            $tipoalta = config('cliente.tipoalta')['DEFINITIVO'][0];

        return view('ventas.cliente.crear', compact('data', 'pais_query', 'provincia_query',
			'condicioniva_query', 'zonavta_query', 'subzonavta_query', 'vendedor_query', 'transporte_query',
			'condicionventa_query', 'listaprecio_query', 'retieneiva_enum', 'condicioniibb_enum', 'cuentacontable_query',
			'vaweb_enum', 'tasaarba', 'tasacaba', 'estado_enum', 'tipoalta',
            'modofacturacion_enum', 'cajaespecial_enum', 'urlOrigen', 'idRemoto', 'abasto_query', 'coeficiente_query', 
            'emitecertificado_enum', 'emitenotadecredito_enum', 'agregabonificacion_enum',
            'distribuidor_query', 'descuentoventa_query', 'tipodocumento_query', 'tipopercepcion_enum', 'certificadonoretencion_enum',
            'condicioniibb_query', 'tipoempresa_cliente_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCliente $request)
    {
        DB::beginTransaction();
        try
        {
            $cliente = $this->clienteRepository->create($request->all());

            // Guarda tablas asociadas
            if ($cliente)
            {
                $cliente_entrega = $this->cliente_entregaRepository->create($request->all(), $cliente->id);

                $cliente_seguimiento = $this->cliente_seguimientoRepository->create($request->all(), $cliente->id);

                $cliente_cm05 = $this->cliente_cm05Repository->create($request->all(), $cliente->id);

                $cliente_articulo_suspendido = $this->cliente_articulo_suspendidoRepository->create($request->all(), $cliente->id);

                $cliente_archivo = $this->cliente_archivoRepository->create($request, $cliente->id);

                $this->clienteRepository->sincronizarAnitaDespuesDeGrabado($cliente->id);

                if ($request->filled('urlOrigen'))
                {       
                    if (str_contains($request->urlOrigen, 'ordenventa'))
                        $this->ordenventaRepository->find($request->idRemoto)->update(['cliente_id' => $cliente->id]);

                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withInput()->withErrors(['errores' => $e->getMessage()]);
        }

        $suitecrmAviso = $this->sincronizarSuitecrmCuentaTrasGrabado($cliente ?? null);

        if (config('cliente.ENVIA_MAIL_ALTA_CLIENTE_DEFINITIVO') == 'SI')
        {
            // Procesa envio del correo para aprobacion del cliente definitivo
            $receivers = config('cliente.DESTINATARIO_ALTA_CLIENTE_DEFINITIVO');

            Mail::to($receivers)->send(new ClienteDefinitivo($request));
        }

        if ($request->filled('urlOrigen')) {
            return redirect($request->urlOrigen)->with($this->flashSuitecrm($suitecrmAviso, 'Cliente creado con éxito'));
        }

        return redirect('ventas/cliente')->with($this->flashSuitecrm($suitecrmAviso, 'Cliente creado con éxito'));
    }

    public function guardarClienteProvisorio(ValidacionClienteProvisorio $request)
    {
        DB::beginTransaction();
        try
        {
            $cliente = $this->clienteRepository->create($request->all());

            // Guarda tablas asociadas
            if ($cliente)
            {
                $cliente_entrega = $this->cliente_entregaRepository->create($request->all(), $cliente->id);

                $cliente_seguimiento = $this->cliente_seguimientoRepository->create($request->all(), $cliente->id);

                $cliente_cm05 = $this->cliente_cm05Repository->create($request->all(), $cliente->id);

                $cliente_articulo_suspendido = $this->cliente_articulo_suspendidoRepository->create($request->all(), $cliente->id);

                $cliente_archivo = $this->cliente_archivoRepository->create($request, $cliente->id);

                $this->clienteRepository->sincronizarAnitaDespuesDeGrabado($cliente->id);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withInput()->withErrors(['errores' => $e->getMessage()]);
        }

        $suitecrmAviso = $this->sincronizarSuitecrmCuentaTrasGrabado($cliente ?? null);

        // Procesa envio del correo para aprobacion del cliente provisorio
        $receivers = config('cliente.MAIL_CLIENTE_PROVISORIO');

        Mail::to($receivers)->send(new ClienteProvisorio($request));

        return redirect('ventas/pedido/crear')->with($this->flashSuitecrm($suitecrmAviso, 'Cliente creado con exito'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-clientes');
        $data = $this->clienteRepository->findOrFail($id);

        $this->armaTablasVista($pais_query, $provincia_query, $condicioniva_query, $zonavta_query,
        	$subzonavta_query, $vendedor_query, $transporte_query, $condicionventa_query, $listaprecio_query,
        	$cuentacontable_query, $retieneiva_enum, $condicioniibb_enum, $vaweb_enum, $estado_enum,
            $data->numerodocumento ?? '', $tasaarba,
			$tasacaba, $modofacturacion_enum, $cajaespecial_enum, $abasto_query, $coeficiente_query, 
            $distribuidor_query,
            $emitecertificado_enum, $emitenotadecredito_enum, $agregabonificacion_enum, $descuentoventa_query,
            $tipodocumento_query, $condicioniibb_query, $tipopercepcion_enum, $certificadonoretencion_enum,
            $tipoempresa_cliente_query,
            'editar'); 

        // Setea url de origen si no es llamada desde clientes
        $urlOrigen = null;
        $referer = request()->headers->get('referer');
        if (str_contains($referer, 'edita') || str_contains($referer, "crear"))
            $urlOrigen = $referer;
        $tiposuspensioncliente_query = $this->tiposuspensionclienteRepository->all();

        $tipoalta = $data->tipoalta;
        if (!isset($tipoalta))
            $tipoalta = config('cliente.tipoalta')['DEFINITIVO'][0];

        $suitecrmHabilitado = SuitecrmPermiso::puedeVerSolapa();
        $suitecrmPuedeEditar = can('gestionar-notas-suitecrm-cliente', false)
            || can('actualizar-clientes', false);
        $suitecrmPuedeSincronizarCuenta = SuitecrmPermiso::puedeSincronizarCuenta();

        return view('ventas.cliente.editar', compact('data', 'pais_query', 'provincia_query',
			'condicioniva_query', 'zonavta_query', 'subzonavta_query', 'vendedor_query', 'transporte_query',
			'condicionventa_query', 'listaprecio_query', 'retieneiva_enum', 'condicioniibb_enum', 'cuentacontable_query',
			'vaweb_enum', 'tasaarba', 'tasacaba', 'estado_enum', 'tipoalta', 'modofacturacion_enum',
            'cajaespecial_enum', 'urlOrigen',
            'tiposuspensioncliente_query', 'abasto_query', 'coeficiente_query',
            'emitecertificado_enum', 'emitenotadecredito_enum', 'agregabonificacion_enum', 'distribuidor_query', 'descuentoventa_query',
            'tipodocumento_query', 'tipopercepcion_enum', 'certificadonoretencion_enum', 'condicioniibb_query',
            'tipoempresa_cliente_query',
            'suitecrmHabilitado', 'suitecrmPuedeEditar', 'suitecrmPuedeSincronizarCuenta'));
    }

    /**
     * Consulta ARCA al ingresar al ABM y suspende el cliente si los impuestos no son válidos.
     */
    public function validarArcaPadron(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        can('editar-clientes');

        if (! filter_var(config('arca.padron_validacion_cliente.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'ok' => true,
                'skipped' => true,
                'validacion' => null,
            ]);
        }

        $cliente = $this->clienteRepository->findOrFail($id);
        $cuit = preg_replace('/\D+/', '', (string) $cliente->numerodocumento);
        if (strlen($cuit) !== 11) {
            return response()->json([
                'ok' => false,
                'message' => 'El cliente no tiene una CUIT válida (11 dígitos) para consultar ARCA.',
            ], 422);
        }

        $condicionivaId = (int) $request->input('condicioniva_id', $cliente->condicioniva_id);

        try {
            $data = app(ConstanciaInscripcionService::class)->getPersonaV2($cuit);
            $validacion = ArcaPadronImpuestosClienteValidacion::validar($condicionivaId, $data);
            $suspendido = false;

            if (($validacion['debe_suspender'] ?? false) && ($validacion['aplica'] ?? false)) {
                Cliente::query()->whereKey($id)->update(['estado' => '1']);
                $suspendido = true;
            }

            $httpOk = ! ($validacion['aplica'] ?? false) || ($validacion['ok'] ?? false);

            return response()->json([
                'ok' => $httpOk,
                'message' => $validacion['mensaje'] ?? null,
                'data' => $data,
                'validacion' => $validacion,
                'suspendido' => $suspendido,
                'estado' => $suspendido ? '1' : (string) $cliente->estado,
                'soap' => $data['soap'] ?? null,
            ], $httpOk ? 200 : 422);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCliente $request, $id)
    {
        can('actualizar-clientes');

        DB::beginTransaction();
        try
        {
            // Graba cliente
            $cliente = $this->clienteRepository->update($request->all(), $id);

            // Graba lugares de entrega
            $this->cliente_entregaRepository->update($request->all(), $id);

            $cliente_seguimiento = $this->cliente_seguimientoRepository->update($request->all(), $id);
            
            $cliente_cm05 = $this->cliente_cm05Repository->update($request->all(), $id);

            $cliente_articulo_suspendido = $this->cliente_articulo_suspendidoRepository->update($request->all(), $id);

            // Graba archivos asociados
            $this->cliente_archivoRepository->update($request, $id);

            $this->clienteRepository->sincronizarAnitaDespuesDeGrabado($id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withInput()->withErrors(['errores' => $e->getMessage()]);
        }

        $suitecrmAviso = $this->sincronizarSuitecrmCuentaTrasGrabado(Cliente::find($id));

        if ($request->filled('urlOrigen')) {
            return redirect($request->urlOrigen)->with($this->flashSuitecrm($suitecrmAviso, 'Cliente actualizado con exito'));
        }

        return redirect('ventas/cliente')->with($this->flashSuitecrm($suitecrmAviso, 'Cliente actualizado con exito'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-clientes');

		$cliente = $this->clienteRepository->find($id);

		if ($cliente)
		{
			$codigo = $cliente->codigo;
	
        	if ($request->ajax()) {
				$cliente = $this->clienteRepository->delete($id);
				$cliente_entrega = $this->cliente_entregaRepository->delete($id, $codigo);
				$cliente_archivo = $this->cliente_archivoRepository->delete($id, $codigo);
        		if ($cliente) {
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

	private function armaTablasVista(&$pais_query, &$provincia_query, &$condicioniva_query, &$zonavta_query,
        	&$subzonavta_query, &$vendedor_query, &$transporte_query, &$condicionventa_query, &$listaprecio_query,
        	&$cuentacontable_query, &$retieneiva_enum, &$condicioniibb_enum, &$vaweb_enum, &$estado_enum, 
			$nroinscripcion, &$tasaarba, &$tasacaba, &$modofacturacion_enum, &$cajaespecial_enum, &$abasto_query, &$coeficiente_query,
            &$distribuidor_query, 
            &$emitecertificado_enum, &$emitenotadecredito_enum, &$agregabonificacion_enum, &$descuentoventa_query,
            &$tipodocumento_query, &$condicioniibb_query, &$tipopercepcion_enum, &$certificadonoretencion_enum,
            &$tipoempresa_cliente_query,
            $funcion)
	{
        $pais_query = Pais::orderBy('nombre')->get();
        $provincia_query = Provincia::orderBy('nombre')->get();
        $condicioniva_query = Condicioniva::orderBy('nombre')->get();
        $zonavta_query = Zonavta::orderBy('nombre')->get();
        $subzonavta_query = SubZonavta::orderBy('nombre')->get();
        $vendedor_query = Vendedor::orderBy('nombre')->get();
        $transporte_query = Transporte::orderBy('codigo')->get();
        $condicionventa_query = Condicionventa::orderBy('nombre')->get();
        $condicioniibb_query = $this->condicionIIBBRepository->all();
        $listaprecio_query = Listaprecio::orderBy('nombre')->get();
        $cuentacontable_query = Cuentacontable::where('empresa_id', config('cliente.EMPRESA_DEFAULT_ID'))->orderBy('nombre')->get();
        $abasto_query = Abasto::orderBy('nombre')->get();
        $coeficiente_query = Coeficiente::orderBy('nombre')->get();
        $distribuidor_query = Distribuidor::orderBy('nombre')->get();
        $descuentoventa_query = Descuentoventa::orderBy('nombre')->get();
        $tipodocumento_query = $this->tipodocumentoRepository->all();
        $tipoempresa_cliente_query = $this->tipoempresaClienteRepository->all();
		$retieneiva_enum = Cliente::$enumRetieneiva;
		$condicioniibb_enum = Cliente::$enumCondicioniibb;
		$vaweb_enum = Cliente::$enumVaweb;
		$estado_enum = Cliente::$enumEstado;
        $modofacturacion_enum = Cliente::$enumModoFacturacion;
        $cajaespecial_enum = Cliente::$enumCajaEspecial;
        $emitecertificado_enum = Cliente::$enumEmiteCertificado;
        $emitenotadecredito_enum = Cliente::$enumEmiteNotaDeCredito;
        $agregabonificacion_enum = Cliente::$enumAgregaBonificacion;
        $tipopercepcion_enum = Cliente_Cm05::$enumTipoPercepcion;
        $certificadonoretencion_enum = Cliente_Cm05::$enumCertificadoNoRetencion;

		if ($funcion == 'editar')
		{
			$tasaIibbArba = $this->iibbService->leeTasaPercepcion($nroinscripcion, '902');
            $tasaIibbCaba = $this->iibbService->leeTasaPercepcion($nroinscripcion, '901');

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

    // Reporte maestro de clientes
    public function indexReporteCliente()
    {
        $cliente_query = $this->clienteQuery->all();
        $cliente_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
        $cliente_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
        $estado_enum = [
            'ACTIVOS' => 'Clientes activos',
			'SUSPENDIDOS' => 'Clientes suspendidos',
            'TODOS' => 'Todos los clientes',
		];
        $tiposuspensioncliente_query = $this->tiposuspensionclienteRepository->all();
        $tiposuspensioncliente_query->prepend((object) ['id'=>'TODOS','nombre'=>'Todos los tipos de suspensión']);
        $vendedor_query = Vendedor::all();
		$vendedor_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$vendedor_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
        $provincia_query = Provincia::orderBy('nombre')->get();
		$provincia_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$provincia_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
        
        return view('ventas.repcliente.crear', compact('cliente_query', 'estado_enum', 
                                                        'tiposuspensioncliente_query', 'vendedor_query', 'provincia_query'));
    }

    public function crearReporteCliente(Request $request)
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

        return (new ClienteExport($this->clienteQuery, $this->tiposuspensionclienteRepository))
                ->parametros($request->desdecliente_id, 
                             $request->hastacliente_id, 
                             $request->estado, 
                             $request->tiposuspensioncliente_id,
                             $request->desdevendedor_id,
                             $request->hastavendedor_id,
                             $request->desdeprovincia_id ?? 0,
                             $request->hastaprovincia_id ?? 99999999)
                ->download('cliente.'.$extension);
    }
    
    public function consultaCliente(Request $request)
    {
        return ($this->clienteRepository->consultaCliente($request->consulta));
	}

    public function leeUnCliente($cliente_id)
    {
        return ($this->clienteRepository->find($cliente_id));
	}

    public function leeUnClientePorCodigo($cliente_id)
    {
        return ($this->clienteRepository->findPorCodigo($cliente_id));
	}

    public function emiteNc($cliente_id)
    {
        $cliente = $this->clienteRepository->updateEmiteNc($cliente_id);

        return 'success';
    }

    public function listarCuentaCorriente(Request $request, $cliente_id)
    {
        can('listar-cuentacorriente-cliente');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $formato = $request->formato;
        $busqueda = (string) ($request->busqueda ?? '');
        $modoVista = ClienteCuentacorrientePreferenciasUsuario::resolverModoVista($request->input('modo_vista'));

        if ($request->has('modo_vista')) {
            ClienteCuentacorrientePreferenciasUsuario::persistirModoVista($modoVista);
        }

        $cliente = Self::leeUnCliente($cliente_id);

        $urlOrigen = request()->headers->get('referer');

        $nombrecliente = '';
        if ($cliente) {
            $nombrecliente = $cliente->nombre;
        }

        $saldoCuentaCorriente = $this->cliente_cuentacorrienteRepository->calcularSaldoCuentaCorriente((int) $cliente_id);
        $totalDeuda = $this->cliente_cuentacorrienteRepository->calcularTotalDeudaCliente((int) $cliente_id);

        switch ($formato) {
        case 'PDF':
            if ($modoVista === ClienteCuentacorrientePreferenciasUsuario::MODO_DEUDA) {
                $cuentacorriente = $this->cliente_cuentacorrienteRepository->listarDeudaCliente($busqueda, $cliente_id, false);
            } else {
                $cuentacorriente = $this->cliente_cuentacorrienteRepository->listarCuentaCorriente($busqueda, $cliente_id, false);
            }

            $view = \View::make('ventas.cuentacorriente.listado', compact(
                'cuentacorriente',
                'nombrecliente',
                'modoVista',
                'saldoCuentaCorriente',
                'totalDeuda',
            ))->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_cuentacorriente';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal', 'landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new ClienteCuentacorrienteListadoExport($this->cliente_cuentacorrienteRepository))
                ->parametros($busqueda, (int) $cliente_id, $modoVista, $nombrecliente, $saldoCuentaCorriente, $totalDeuda)
                ->download('cuentacorriente_cliente.xlsx');
            break;

        case 'CSV':
            return (new ClienteCuentacorrienteListadoExport($this->cliente_cuentacorrienteRepository))
                ->parametros($busqueda, (int) $cliente_id, $modoVista, $nombrecliente, $saldoCuentaCorriente, $totalDeuda)
                ->download('cuentacorriente_cliente.csv', \Maatwebsite\Excel\Excel::CSV);
            break;

        default:
            if ($modoVista === ClienteCuentacorrientePreferenciasUsuario::MODO_DEUDA) {
                $cuentacorriente = $this->cliente_cuentacorrienteRepository->listarDeudaCliente($busqueda, $cliente_id);
                $saldoAnterior = 0.0;
            } else {
                $cuentacorriente = $this->cliente_cuentacorrienteRepository->listarCuentaCorriente($busqueda, $cliente_id);
                $primerRegistro = $cuentacorriente->first();
                $saldoAnterior = $this->cliente_cuentacorrienteRepository->saldoAnteriorPagina(
                    (int) $cliente_id,
                    $primerRegistro,
                );
            }

            $moneda_query = $this->monedaRepository->all();

            $datas = [
                'cuentacorriente' => $cuentacorriente,
                'busqueda' => $busqueda,
                'id' => $cliente_id,
                'nombrecliente' => $nombrecliente,
                'urlOrigen' => $urlOrigen,
                'moneda_query' => $moneda_query,
                'modoVista' => $modoVista,
                'saldoCuentaCorriente' => $saldoCuentaCorriente,
                'totalDeuda' => $totalDeuda,
                'saldoAnterior' => $saldoAnterior ?? 0.0,
            ];

            return view('ventas.cuentacorriente.index', $datas);
        }
    }

    // Editar cuenta corriente
    public function editarCuentaCorriente($cuentacorriente_id)
    {
        $cuentacorriente = $this->cliente_cuentacorrienteRepository->find($cuentacorriente_id);

        if ($cuentacorriente->cobranza_id > 0)
            return $this->cobranzaService->editaUnaCobranza($cuentacorriente->cobranza_id);

        if ($cuentacorriente->venta_id > 0)
            return $this->facturacionService->editaUnaFactura($cuentacorriente->venta_id);

        return 'No encontro movimiento a editar';
    }    

    // Editar cuenta corriente
    public function consultarDeuda($cliente_id, $empresa_id, $venta_id = null)
    {
        $cuentacorriente = $this->cliente_cuentacorrienteRepository->consultarDeuda($cliente_id, $empresa_id, $venta_id);

        return $cuentacorriente;
    }       

    public function leerCuentaCorrienteAplicacion($cliente_cuentacorriente_id)
    {
        return $this->cliente_cuentacorrienteRepository->consultarAplicacion($cliente_cuentacorriente_id);
    }

    /**
     * Sincroniza account + accounts_cstm en SuiteCRM tras alta/actualización en Anita.
     *
     * @return string|null Aviso si falló o quedó pendiente; null si OK o no aplica
     */
    private function sincronizarSuitecrmCuentaTrasGrabado($cliente): ?string
    {
        if (! $cliente instanceof Cliente) {
            return null;
        }

        $service = app(SuitecrmAccountService::class);
        if (! $service->isHabilitado() || ! SuitecrmPermiso::puedeSincronizarCuenta()) {
            return null;
        }

        try {
            $cliente = Cliente::with(['localidades', 'provincias', 'paises'])->find($cliente->id);
            if (! $cliente) {
                return null;
            }

            $resultado = $service->sincronizar($cliente);
            if ($resultado['ok']) {
                return null;
            }

            return $resultado['mensaje'] ?? 'No se pudo sincronizar con SuiteCRM.';
        } catch (\Throwable $e) {
            return 'SuiteCRM: '.$e->getMessage();
        }
    }

    /**
     * @return array<string, string>
     */
    private function flashSuitecrm(?string $suitecrmAviso, string $mensajeBase): array
    {
        if ($suitecrmAviso !== null && $suitecrmAviso !== '') {
            return ['mensaje' => $mensajeBase.' — '.$suitecrmAviso];
        }

        return ['mensaje' => $mensajeBase];
    }
}
