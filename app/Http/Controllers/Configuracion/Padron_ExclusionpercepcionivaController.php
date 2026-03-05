<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use App\Imports\Configuracion\Padron_ExclusionpercepcionivaImport;
use App\Http\Requests\ValidacionPadron_Exclusionpercepcioniva;
use App\Repositories\Configuracion\Padron_ExclusionpercepcionivaRepositoryInterface;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use DB;

class Padron_ExclusionpercepcionivaController extends Controller
{
	private $repository;
    private $clienteRepository;

    public function __construct(Padron_ExclusionpercepcionivaRepositoryInterface $repository,
                                ClienteRepositoryInterface $clienteRepository)
    {
        $this->repository = $repository;
        $this->clienteRepository = $clienteRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {
        can('listar-padron-mipyme');

        $busqueda = $request->busqueda;

		$padron_exclusionpercepcionivas = $this->repository->leePadron_Exclusionpercepcioniva($busqueda, true);

        $datas = ['padron_exclusionpercepcionivas' => $padron_exclusionpercepcionivas, 'busqueda' => $busqueda];

        return view('configuracion.padron_exclusionpercepcioniva.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-padron-mipyme'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $padron_exclusionpercepcionivas = $this->repository->leePadron_Exclusionpercepcioniva($busqueda, false);

            $view =  \View::make('configuracion.padron_exclusionpercepcioniva.listado', compact('padron_exclusionpercepcionivas'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_padron_exclusionpercepcioniva';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new Padron_ExclusionpercepcionivaExport($this->localidadRepository))
                        ->parametros($busqueda)
                        ->download('padron_exclusionpercepcioniva.xlsx');
            break;

        case 'CSV':
            return (new Padron_ExclusionpercepcionivaExport($this->localidadRepository))
                        ->parametros($busqueda)
                        ->download('padron_exclusionpercepcioniva.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['padron_exclusionpercepcionivas' => $padron_exclusionpercepcionivas, 'busqueda' => $busqueda];

		return view('configuracion.padron_exclusionpercepcioniva.index', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-padron-mipyme');

        return view('configuracion.padron_exclusionpercepcioniva.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPadron_Exclusionpercepcioniva $request)
    {
		$this->repository->create($request->all());

        return redirect('configuracion/padron_exclusionpercepcioniva')->with('mensaje', 'Padrón Exclusionpercepcioniva creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-padron-mipyme');
        $data = $this->repository->findOrFail($id);

        return view('configuracion.padron_exclusionpercepcioniva.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPadron_Exclusionpercepcioniva $request, $id)
    {
        can('actualizar-padron-mipyme');
        $this->repository->update($request->all(), $id);

        return redirect('configuracion/padron_exclusionpercepcioniva')->with('mensaje', 'Padrón Exclusion percepcion iva actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-padron-mipyme');

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

    public function crearImportacionPadron_Exclusionpercepcioniva()
    {
        can('importar-cliente-congelado-uif');
		
        return view('configuracion.padron_exclusionpercepcioniva.crearimportacion');
    }

	public function importarPadron_Exclusionpercepcioniva(Request $request)
    {
        $this->validate(request(), [
            'file' => 'mimes:csv,txt'
        ]);

        try {
            set_time_limit(0);

            DB::beginTransaction();

            // Borra todo el padron
            DB::table('padron_exclusionpercepcioniva')->delete();

            // Importa csv
            Excel::import(new Padron_ExclusionpercepcionivaImport, request("file"));

            DB::commit();

            return back()
                ->with('mensaje', 'Padrón Exclusion percepcion iva importado correctamente');
        } catch (\Exception $exception) {
            DB::rollBack();
            
            return back()
                ->with('mensaje', $exception->getMessage());
        }
    }

}
