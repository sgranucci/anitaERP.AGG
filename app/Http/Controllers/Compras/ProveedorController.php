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
use App\Repositories\Compras\RetenciongananciaRepositoryInterface;
use App\Repositories\Compras\RetencionsussRepositoryInterface;
use App\Repositories\Compras\RetencionivaRepositoryInterface;
use App\Repositories\Compras\CondicionpagoRepositoryInterface;
use App\Repositories\Compras\CondicioncompraRepositoryInterface;
use App\Repositories\Compras\CondicionentregaRepositoryInterface;
use App\Repositories\Caja\ConceptogastoRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Repositories\Caja\TipocuentacajaRepositoryInterface;
use App\Repositories\Caja\BancoRepositoryInterface;
use App\Repositories\Caja\MediopagoRepositoryInterface;
use App\Queries\Compras\ProveedorQueryInterface;
use App\Services\Configuracion\IIBBService;
use App\Repositories\Configuracion\CondicionIIBBRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Compras\Proveedor_ExclusionRepositoryInterface;
use App\Repositories\Compras\Proveedor_ArchivoRepositoryInterface;
use App\Repositories\Compras\Proveedor_FormapagoRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Mail\Compras\ProveedorProvisorio;
use App\Exports\Compras\ProveedorExport;
use Carbon\Carbon;
use Mail;
use DB;

class ProveedorController extends Controller
{
	private $proveedorRepository;
	private $proveedor_exclusionRepository;
	private $proveedor_archivoRepository;
    private $proveedor_formapagoRepository;
    private $tiposuspensionproveedorRepository;
    private $tipoempresaRepository;
    private $retenciongananciaRepository;
    private $retencionsussRepository;
    private $retencionivaRepository;
    private $condicionpagoRepository;
    private $condicioncompraRepository;
    private $condicionentregaRepository;
    private $condicionIIBBRepository;
    private $conceptogastoRepository;
	private $iibbService;
	private $proveedorQuery;
    private $formapagoRepository;
    private $tipocuentacajaRepository;
    private $bancoRepository;
    private $mediopagoRepository;
    private $centrocostoRepository;
    private $cuentacontableRepository;

    public function __construct(
		IIBBService $iibbService,
        TiposuspensionproveedorRepositoryInterface $tiposuspensionproveedorrepository,
        TipoempresaRepositoryInterface $tipoempresarepository,
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
        ProveedorQueryInterface $proveedorquery,
        CentrocostoRepositoryInterface $centrocostorepository,
        CuentacontableRepositoryInterface $cuentacontablerepository)
    {
        $this->proveedorRepository = $proveedorrepository;
        $this->proveedor_exclusionRepository = $proveedor_exclusionrepository;
        $this->proveedor_archivoRepository = $proveedor_archivorepository;
        $this->proveedor_formapagoRepository = $proveedor_formapagorepository;
        $this->tiposuspensionproveedorRepository = $tiposuspensionproveedorrepository;
        $this->tipoempresaRepository = $tipoempresarepository;
        $this->retenciongananciaRepository = $retenciongananciarepository;
        $this->retencionivaRepository = $retencionivarepository;
        $this->retencionsussRepository = $retencionsussrepository;
        $this->condicionpagoRepository = $condicionpagorepository;
        $this->condicioncompraRepository = $condicioncomprarepository;
        $this->condicionentregaRepository = $condicionentregarepository;
        $this->condicionIIBBRepository = $condicionIIBBrepository;
        $this->iibbService = $iibbService;
        $this->conceptogastoRepository = $conceptogastorepository;
        $this->formapagoRepository = $formapagorepository;
        $this->tipocuentacajaRepository = $tipocuentacajarepository;
        $this->bancoRepository = $bancorepository;
        $this->mediopagoRepository = $mediopagorepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->cuentacontableRepository = $cuentacontablerepository;

        $this->proveedorQuery = $proveedorquery;
        $this->flRemoto = false;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-proveedor');

        $hay_proveedores = $this->proveedorQuery->first();

		if (!$hay_proveedores)
		{
			$this->proveedorRepository->sincronizarConAnita();
			$this->proveedor_archivoRepository->sincronizarConAnita();
		}

		$datas = $this->proveedorQuery->all();

        return view('compras.proveedor.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function crear($tipoalta = null)
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
            $tiporetencion_enum,
            'crear'); 

        $tipoAlta_enum = Proveedor::$enumTipoAlta;
        if (!isset($tipoalta))
            $tipoalta = substr(config("proveedor.tipoalta"),0,1);

        return view('compras.proveedor.crear', compact('pais_query', 'provincia_query', 'tipoempresa_query',
			'condicioniva_query', 'condicionIIBB_query',
            'retencionganancia_query', 'retencioniva_query', 'retencionsuss_query',
			'condicionpago_query', 'condicioncompra_query', 'condicionentrega_query',
            'cuentacontable_query', 'retieneiva_enum', 
            'retieneganancia_enum', 'retienesuss_enum', 'condicionganancia_enum',
            'centrocosto_query', 'conceptogasto_query', 'agentepercepcioniva_enum', 'agentepercepcionIIBB_enum',
            'estado_enum', 'tasaarba', 'tasacaba', 'tipoalta',
            'formapago_query', 'tipocuentacaja_query', 'moneda_query', 'banco_query', 'mediopago_query',
            'tiporetencion_enum'));
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
            $proveedor = $this->proveedorRepository->create($request->all());

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
            return ['errores' => $e->getMessage()];
        }

        // Procesa envio del correo para aprobacion del proveedor provisorio
        if (substr(config("proveedor.tipoalta"),0,1) == 'P' && config("proveedor.enviamailaprobacion") == 'S')
        {
            $receivers = config("proveedor.emailapruebaalta");

            Mail::to($receivers)->send(new ProveedorProvisorio($request));
        }

        return redirect('compras/proveedor')->with('mensaje', 'Proveedor creado con exito');
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
            $tiporetencion_enum,
            'editar'); 

        $tiposuspensionproveedor_query = $this->tiposuspensionproveedorRepository->all();
        
		$tipoalta = $data->tipoalta;

        return view('compras.proveedor.editar', compact('data', 'pais_query', 'provincia_query', 'tipoempresa_query',
			'condicioniva_query', 'condicionIIBB_query',
            'retencionganancia_query', 'retencioniva_query', 'retencionsuss_query',
            'condicionpago_query', 'condicioncompra_query', 'condicionentrega_query',
			'cuentacontable_query', 'retieneiva_enum', 
            'retieneganancia_enum', 'retienesuss_enum', 'condicionganancia_enum',
            'centrocosto_query', 'conceptogasto_query',
            'estado_enum', 'tasaarba', 'tasacaba', 'tipoalta', 
		    'tiposuspensionproveedor_query', 'agentepercepcioniva_enum', 'agentepercepcionIIBB_enum',
            'formapago_query', 'tipocuentacaja_query', 'moneda_query', 'banco_query', 'mediopago_query',
            'tiporetencion_enum', 'tipoconsulta'));
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

            dd($e->getMessage());
            return ['errores' => $e->getMessage()];
        }

        return redirect('compras/proveedor')->with('mensaje', 'Proveedor actualizado con exito');
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
        &$tiporetencion_enum,
        $funcion)
	{
        $pais_query = Pais::orderBy('nombre')->get();
        $provincia_query = Provincia::orderBy('nombre')->get();
        $tipoempresa_query = $this->tipoempresaRepository->all();
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
        $cuentacontable_query = $this->cuentacontableRepository->all();
        $retieneiva_enum = Proveedor::$enumRetieneiva;
        $retieneganancia_enum = Proveedor::$enumRetieneganancia;
        $condicionganancia_enum = Proveedor::$enumCondicionganancia;
        $retienesuss_enum = Proveedor::$enumRetienesuss;
        $agentepercepcioniva_enum = Proveedor::$enumAgentePercepcioniva;
        $agentepercepcionIIBB_enum = Proveedor::$enumAgentePercepcionIIBB;
        $formapago_query = $this->formapagoRepository->all();
        $tipocuentacaja_query = $this->tipocuentacajaRepository->all();
        $banco_query = $this->bancoRepository->all();
        $mediopago_query = $this->mediopagoRepository->all();
        $moneda_query = Moneda::get();
        
        $tiporetencion_enum = Proveedor_Exclusion::$enumTipoRetencion;
		$estado_enum = Proveedor::$enumEstado;

		if ($funcion == 'editar')
		{
			$tasaarba = $this->iibbService->leeTasaPercepcion($nroinscripcion, '902');
            $tasacaba = $this->iibbService->leeTasaPercepcion($nroinscripcion, '901');

            if ($tasaarba == '')
				$tasaarba = 'No esta en padron';
            else    
                $tasaarba = round($tasaarba, 2).'%';
			if ($tasacaba == '' || $tasacaba < 0.00001)
				$tasacaba = 'No esta en padron';
            else
                $tasacaba = round($tasacaba, 2).'%';
		}
		else
			$tasaarba = $tasacaba = '';
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

    public function leeProveedor($proveedor_id)
    {
        return $this->proveedorRepository->find($proveedor_id);
    }

}
