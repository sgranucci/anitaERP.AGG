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
use App\Repositories\Ventas\TiposuspensionclienteRepositoryInterface;
use App\Repositories\Ventas\DescuentoventaRepositoryInterface;
use App\Repositories\Configuracion\TipodocumentoRepositoryInterface;
use App\Repositories\Ordenventa\OrdenventaRepositoryInterface;
use App\Queries\Ventas\ClienteQueryInterface;
use App\Queries\Ventas\Cliente_EntregaQueryInterface;
use App\Services\Configuracion\IIBBService;
use App\Mail\Ventas\ClienteProvisorio;
use App\Exports\Ventas\ClienteExport;
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
	private $cliente_archivoRepository;
    private $descuentoventaRepository;
    private $tiposuspensionclienteRepository;
    private $tipodocumentoRepository;
	private $iibbService;
	private $clienteQuery;
	private $cliente_entregaQuery;
    private $ordenventaRepository;

    public function __construct(
		ClienteRepositoryInterface $clienteRepository, 
		Cliente_EntregaRepositoryInterface $cliente_entregaRepository, 
		Cliente_ArchivoRepositoryInterface $cliente_archivoRepository, 
        Cliente_SeguimientoRepositoryInterface $cliente_seguimientorepository,
        Cliente_Cm05RepositoryInterface $cliente_cm05repository,
        Cliente_Articulo_SuspendidoRepositoryInterface $cliente_articulo_suspendidorepository,
        DescuentoventaRepositoryInterface $descuentoventarepository,
        TipodocumentoRepositoryInterface $tipodocumentoRepository,
		IIBBService $iibbService,
		ClienteQueryInterface $clientequery,
        TiposuspensionclienteRepositoryInterface $tiposuspensionclienterepository,
        Cliente_EntregaQueryInterface $cliente_entregaquery,
        OrdenventaRepositoryInterface $ordenventarepository)
    {
        $this->clienteRepository = $clienteRepository;
        $this->cliente_entregaRepository = $cliente_entregaRepository;
        $this->cliente_seguimientoRepository = $cliente_seguimientorepository;
        $this->cliente_cm05Repository = $cliente_cm05repository;
        $this->cliente_articulo_suspendidoRepository = $cliente_articulo_suspendidorepository;
        $this->cliente_archivoRepository = $cliente_archivoRepository;
        $this->descuentoventaRepository = $descuentoventarepository;
        $this->tiposuspensionclienteRepository = $tiposuspensionclienterepository;
        $this->iibbService = $iibbService;
        $this->tipodocumentoRepository = $tipodocumentoRepository;

        $this->clienteQuery = $clientequery;
        $this->cliente_entregaQuery = $cliente_entregaquery;

        $this->ordenventaRepository = $ordenventarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-clientes');

        $busqueda = $request->busqueda;

		$clientes = $this->clienteRepository->leeCliente($busqueda, true);

        if ($clientes->isEmpty())
		{
        	$this->clienteRepository->sincronizarConAnita();
			//$this->cliente_entregaRepository->sincronizarConAnita();
			$this->cliente_archivoRepository->sincronizarConAnita();
	
            $clientes = $this->clienteRepository->leeCliente($busqueda, true);
		}

        $datas = ['clientes' => $clientes, 'busqueda' => $busqueda];

        return view('ventas.cliente.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-clientes'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $cliente = $this->clienteRepository->leeCliente($busqueda, false);

            $view =  \View::make('ventas.cliente.listado', compact('cliente'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_cliente';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new ClienteExport($this->clienteRepository))
                        ->parametros($busqueda)
                        ->download('cliente.xlsx');
            break;

        case 'CSV':
            return (new ClienteExport($this->clienteRepository))
                        ->parametros($busqueda)
                        ->download('cliente.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['cliente' => $cliente, 'busqueda' => $busqueda];

		return view('ventas.cliente.indexp', $datas);       
    }

	public function leerCliente_Entrega($cliente_id)
    {
        return $this->cliente_entregaQuery->traeCliente_EntregaporCliente_Id($cliente_id);
    }

	public function leerCliente($cliente_id)
    {
        return $this->clienteQuery->traeClienteporId($cliente_id, ['id','vendedor_id','transporte_id','condicionventa_id','descuento','tiposuspension_id','lugarentrega'])->toArray();
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
            $tipodocumento_query,
            'crear'); 

        if (!isset($tipoalta))
            $tipoalta = config('cliente.tipoalta')['DEFINITIVO'][0];

        return view('ventas.cliente.crear', compact('pais_query', 'provincia_query',
			'condicioniva_query', 'zonavta_query', 'subzonavta_query', 'vendedor_query', 'transporte_query',
			'condicionventa_query', 'listaprecio_query', 'retieneiva_enum', 'condicioniibb_enum', 'cuentacontable_query',
			'vaweb_enum', 'tasaarba', 'tasacaba', 'estado_enum', 'tipoalta',
            'modofacturacion_enum', 'cajaespecial_enum', 'abasto_query', 'coeficiente_query', 'distribuidor_query',
            'emitecertificado_enum', 'emitenotadecredito_enum', 'agregabonificacion_enum', 'descuentoventa_query',
            'tipodocumento_query'));
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
        }

        $idRemoto = $id;

		$this->armaTablasVista($pais_query, $provincia_query, $condicioniva_query, $zonavta_query,
        	$subzonavta_query, $vendedor_query, $transporte_query, $condicionventa_query, $listaprecio_query,
        	$cuentacontable_query, $retieneiva_enum, $condicioniibb_enum, $vaweb_enum, $estado_enum, '', $tasaarba,
			$tasacaba, $modofacturacion_enum, $cajaespecial_enum, $abasto_query, $coeficiente_query, 
            $distribuidor_query,
            $emitecertificado_enum, $emitenotadecredito_enum, $agregabonificacion_enum, $descuentoventa_query,
            $tipodocumento_query, $tipopercepcion_enum, $certificadonoretencion_enum,
            'crear'); 

        if (!isset($tipoalta))
            $tipoalta = config('cliente.tipoalta')['DEFINITIVO'][0];

        return view('ventas.cliente.crear', compact('data', 'pais_query', 'provincia_query',
			'condicioniva_query', 'zonavta_query', 'subzonavta_query', 'vendedor_query', 'transporte_query',
			'condicionventa_query', 'listaprecio_query', 'retieneiva_enum', 'condicioniibb_enum', 'cuentacontable_query',
			'vaweb_enum', 'tasaarba', 'tasacaba', 'estado_enum', 'tipoalta',
            'modofacturacion_enum', 'cajaespecial_enum', 'urlOrigen', 'idRemoto', 'abasto_query', 'coeficiente_query', 
            'emitecertificado_enum', 'emitenotadecredito_enum', 'agregabonificacion_enum',
            'distribuidor_query', 'descuentoventa_query', 'tipodocumento_query', 'tipopercepcion_enum', 'certificadonoretencion_enum'));
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

                if (isset($request->urlOrigen))
                {       
                    if (str_contains($request->urlOrigen, 'ordenventa'))
                        $this->ordenventaRepository->find($request->idRemoto)->update(['cliente_id' => $cliente->id]);

                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return ['errores' => $e->getMessage()];
        }

        if (isset($request->urlOrigen))
            return redirect($request->urlOrigen);

        return redirect('ventas/cliente')->with('mensaje', 'Cliente creado con éxito');
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
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return ['errores' => $e->getMessage()];
        }

        // Procesa envio del correo para aprobacion del cliente provisorio
        $receivers = config('cliente.MAIL_CLIENTE_PROVISORIO');

        Mail::to($receivers)->send(new ClienteProvisorio($request));

        return redirect('ventas/pedido/crear')->with('mensaje', 'Cliente creado con exito');
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
        	$cuentacontable_query, $retieneiva_enum, $condicioniibb_enum, $vaweb_enum, $estado_enum, '', $tasaarba,
			$tasacaba, $modofacturacion_enum, $cajaespecial_enum, $abasto_query, $coeficiente_query, 
            $distribuidor_query,
            $emitecertificado_enum, $emitenotadecredito_enum, $agregabonificacion_enum, $descuentoventa_query,
            $tipodocumento_query, $tipopercepcion_enum, $certificadonoretencion_enum,
            'editar'); 

        $tiposuspensioncliente_query = $this->tiposuspensionclienteRepository->all();
        
		$tipoalta = $data->tipoalta;
        if (!isset($tipoalta))
            $tipoalta = config('cliente.tipoalta')['DEFINITIVO'][0];

        return view('ventas.cliente.editar', compact('data', 'pais_query', 'provincia_query',
			'condicioniva_query', 'zonavta_query', 'subzonavta_query', 'vendedor_query', 'transporte_query',
			'condicionventa_query', 'listaprecio_query', 'retieneiva_enum', 'condicioniibb_enum', 'cuentacontable_query',
			'vaweb_enum', 'tasaarba', 'tasacaba', 'estado_enum', 'tipoalta', 'modofacturacion_enum',
            'cajaespecial_enum',
            'tiposuspensioncliente_query', 'abasto_query', 'coeficiente_query',
            'emitecertificado_enum', 'emitenotadecredito_enum', 'agregabonificacion_enum', 'distribuidor_query', 'descuentoventa_query',
            'tipodocumento_query', 'tipopercepcion_enum', 'certificadonoretencion_enum'));
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
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return ['errores' => $e->getMessage()];
        }

        return redirect('ventas/cliente')->with('mensaje', 'Cliente actualizado con exito');
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
            &$tipodocumento_query, &$tipopercepcion_enum, &$certificadonoretencion_enum,
            $funcion)
	{
        $pais_query = Pais::orderBy('nombre')->get();
        $provincia_query = Provincia::orderBy('nombre')->get();
        $condicioniva_query = Condicioniva::orderBy('nombre')->get();
        $zonavta_query = Zonavta::orderBy('nombre')->get();
        $subzonavta_query = SubZonavta::orderBy('nombre')->get();
        $vendedor_query = Vendedor::orderBy('nombre')->get();
        $transporte_query = Transporte::orderBy('nombre')->get();
        $condicionventa_query = Condicionventa::orderBy('nombre')->get();
        $listaprecio_query = Listaprecio::orderBy('nombre')->get();
        $cuentacontable_query = Cuentacontable::orderBy('nombre')->get();
        $abasto_query = Abasto::orderBy('nombre')->get();
        $coeficiente_query = Coeficiente::orderBy('nombre')->get();
        $distribuidor_query = Distribuidor::orderBy('nombre')->get();
        $descuentoventa_query = Descuentoventa::orderBy('nombre')->get();
        $tipodocumento_query = $this->tipodocumentoRepository->all();
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

            if ($tasaIibbArba == '')
				$tasaarba = 'No esta en padron';
            else    
                $tasaarba = round($tasaIibbArba['tasa'], 2).'%';

			if ($tasaIibbCaba == '' || $tasaIibbCaba['tasa'] < 0.00001)
				$tasacaba = 'No esta en padron';
            else
                $tasacaba = round($tasaIibbCaba, 2).'%';
		}
		else
			$tasaarba = $tasacaba = '';
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
        
        return view('ventas.repcliente.crear', compact('cliente_query', 'estado_enum', 
                                                        'tiposuspensioncliente_query', 'vendedor_query'));
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
                             $request->hastavendedor_id)
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
}
