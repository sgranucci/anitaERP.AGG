<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use App\Imports\Configuracion\Retencionimpositiva_ArcaImport;
use App\Exports\Configuracion\ConciliaRetencionimpositiva_ArcaExport;
use App\Exports\Configuracion\Retencionimpositiva_ArcaExport;
use App\Http\Requests\ValidacionRetencionimpositiva_Arca;
use App\Repositories\Configuracion\Retencionimpositiva_ArcaRepositoryInterface;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use DB;

class Retencionimpositiva_ArcaController extends Controller
{
	private $repository;
    private $clienteRepository;
    private $empresaRepository;

    public function __construct(Retencionimpositiva_ArcaRepositoryInterface $repository,
                                EmpresaRepositoryInterface $empresaRepository,
                                ClienteRepositoryInterface $clienteRepository)
    {
        $this->repository = $repository;
        $this->empresaRepository = $empresaRepository;
        $this->clienteRepository = $clienteRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {
        can('listar-retencion-impositiva-arca');

        $busqueda = $request->busqueda;

		$retencionimpositiva_arca = $this->repository->leeRetencionimpositiva_Arca($busqueda, true);

        $datas = ['retencionimpositiva_arca' => $retencionimpositiva_arca, 'busqueda' => $busqueda];

        return view('configuracion.retencionimpositiva_arca.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-retencion-impositiva-arca'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $retencionimpositiva_arca = $this->repository->leeRetencionimpositiva_Arca($busqueda, false);

            $view =  \View::make('configuracion.retencionimpositiva_arca.listado', compact('retencionimpositiva_arca'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_retencionimpositiva_arca';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new Retencionimpositiva_ArcaExport($this->repository))
                        ->parametros($busqueda)
                        ->download('retencionimpositiva_arca.xlsx');
            break;

        case 'CSV':
            return (new Retencionimpositiva_ArcaExport($this->repository))
                        ->parametros($busqueda)
                        ->download('retencionimpositiva_arca.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['retencionimpositiva_arca' => $retencionimpositiva_arca, 'busqueda' => $busqueda];

		return view('configuracion.retencionimpositiva_arca.index', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-retencion-impositiva-arca');

        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('configuracion.retencionimpositiva_arca.crear', compact('empresa_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionRetencionimpositiva_Arca $request)
    {
		$this->repository->create($request->all());

        return redirect('configuracion/retencionimpositiva_arca')->with('mensaje', 'Retención Impositiva creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-retencion-impositiva-arca');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('configuracion.retencionimpositiva_arca.editar', compact('data', 'empresa_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionRetencionimpositiva_Arca $request, $id)
    {
        can('actualizar-retencion-impositiva-arca');
        $this->repository->update($request->all(), $id);

        return redirect('configuracion/retencion_impositiva_arca')->with('mensaje', 'Retención Impositiva actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-retencion-impositiva-arca');

        if ($request->ajax()) {
        	if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    // Importar clientes congelados desde excel

    public function crearImportacionRetencionimpositiva_Arca()
    {
        can('importar-retencion-impositiva-arca');
		$empresa_query = $this->empresaRepository->allFiltrado();

	    $agregapisa_enum = [
            'A' => 'Agrega datos',
            'P' => 'Pisa datos',
		];

        return view('configuracion.retencionimpositiva_arca.crearimportacion', compact('empresa_query', 'agregapisa_enum'));
    }

	public function importarRetencionimpositiva_Arca(Request $request)
    {
        try {
            set_time_limit(0);

            DB::beginTransaction();

            // Borra todo el padron
            if ($request->agregapisa == 'P')
                DB::table('retencionimpositiva_arca')->where('empresa_id', $request->empresa_id)->delete();

            // Importa Excel
            Excel::import(new Retencionimpositiva_ArcaImport($request->empresa_id), request("file"));

            DB::commit();

            return back()
                ->with('mensaje', 'Retenciones Impositivas importadas correctamente');
        } catch (\Exception $exception) {
            DB::rollBack();
            
            return back()
                ->with('mensaje', $exception->getMessage());
        }
    }

    public function conciliarRetencionimpositiva_Arca()
    {
        can('conciliar-retencion-impositiva-arca');
		$empresa_query = $this->empresaRepository->allFiltrado();

        // Busca lista de impuestos
        $impuesto_query = $this->repository->leeImpuesto();
        
        // Busca lista de regimenes
        $regimen_query = $this->repository->leeRegimen();
        $regimen_query->prepend((object) ['regimen' => 'TODOS', 'descripcionregimen' => 'Todos los Regímenes']);

        return view('configuracion.retencionimpositiva_arca.crearconciliacion', compact('empresa_query', 'impuesto_query', 'regimen_query'));
    }

    public function procesarConciliacionRetencionimpositiva_Arca(Request $request)
    {
        $retencionimpositiva_arcas = $this->repository->leeRetencionPorEmpresaFecha($request->empresa_id, $request->desdefecha, $request->hastafecha, 
                                                                    $request->impuesto, $request->regimen);

        // Lee de anita las retenciones RIB
        $retencionsistemas = $this->repository->leeRetencionSistemaAnita($request->empresa_id, $request->desdefecha, $request->hastafecha, 
                                                                    $request->impuesto, $request->regimen);

        // primero controla las retenciones del sistema contra arca
        $arrayNoEncontroEnArca = [];
        foreach ($retencionsistemas as $retencionsistema)
        {
            $cuit = str_replace("-", "", $retencionsistema->cuit);

            // Busca en retencion impositiva arca
            $totalMonto = 0;
            foreach ($retencionimpositiva_arcas as $retencionimpositiva_arca)
            {
                if ($cuit == $retencionimpositiva_arca->cuit)
                    $totalMonto += $retencionimpositiva_arca->montoretencion;
            }

            if (($totalMonto != 0 && abs($totalMonto-$retencionsistema->totalretencion) > 0.009) || $totalMonto == 0.)
            {
                $arrayNoEncontroEnArca[] = [
                    'empresa_id' => $request->empresa_id,
                    'codigocliente' => $retencionsistema->codigocliente,
                    'nombrecliente' => $retencionsistema->nombrecliente,
                    'cuit' => $retencionsistema->cuit,
                    'montoarca' => $totalMonto,
                    'montosistema' => $retencionsistema->totalretencion,
                ];
            }
        }

        // controla las retenciones de arca contra el sistema
        $arrayNoEncontroEnSistema = [];
        foreach ($retencionimpositiva_arcas as $retencionimpositiva_arca)
        {
            // Suma el monto total por cuit
            for ($i = 0, $totalMontoArca = 0; $i < count($retencionimpositiva_arcas); $i++)
            {
                if ($retencionimpositiva_arcas[$i]->cuit == $retencionimpositiva_arca->cuit)
                    $totalMontoArca += $retencionimpositiva_arcas[$i]->montoretencion;
            }

            $totalMontoSistema = 0;
            // Busca en retencion impositiva del sistema
            foreach ($retencionsistemas as $retencionsistema)
            {
                $cuit = str_replace("-", "", $retencionsistema->cuit);

                if ($cuit == $retencionimpositiva_arca->cuit)
                    $totalMontoSistema += $retencionsistema->totalretencion;
            }

            if (($totalMontoSistema != 0 && abs($totalMontoSistema-$totalMontoArca) > 0.009) || $totalMontoSistema == 0.)
            {
                // Busca el cliente por CUIT
                $cuit = substr($retencionimpositiva_arca->cuit,0,2).'-'.substr($retencionimpositiva_arca->cuit,2,8).'-'.substr($retencionimpositiva_arca->cuit,10,1);

                // Busca si existe en array de ARCA
                $flEncontro = false;
                foreach ($arrayNoEncontroEnArca as $arrayArca)
                {
                    if ($cuit == $arrayArca['cuit'])
                        $flEncontro = true;
                }

                foreach ($arrayNoEncontroEnSistema as $arraySistema)
                {
                    if ($cuit == $arraySistema['cuit'])
                        $flEncontro = true;
                }

                if (!$flEncontro)
                {
                    $cliente = $this->clienteRepository->findPorNumeroDocumento($cuit);

                    $codigoCliente = $nombreCliente = '';
                    if ($cliente)
                    {
                        $codigoCliente = $cliente->codigo;
                        $nombreCliente = $cliente->nombre;
                    }

                    $arrayNoEncontroEnSistema[] = [
                        'empresa_id' => $request->empresa_id,
                        'codigocliente' => $codigoCliente,
                        'nombrecliente' => $nombreCliente,
                        'cuit' => $cuit,
                        'montoarca' => $totalMontoArca,
                        'montosistema' => $totalMontoSistema
                    ];
                }
            }
        }
        return (new ConciliaRetencionimpositiva_ArcaExport)
                        ->parametros($arrayNoEncontroEnSistema, $arrayNoEncontroEnArca)
                        ->download('conciliaretencionimpositiva_arca.xlsx');
    }
}
